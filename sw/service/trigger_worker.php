<?php

/*************************************************************
 * trigger_worker.php - Async Trigger Worker for LTrax
 *
 * Polls trigger_queue table and runs triggers in sequence.
 * Run as systemd service (multiple instances for parallelism):
 *   systemctl start ltx-trigger@1
 *   systemctl start ltx-trigger@2
 *
 * Uses SELECT ... FOR UPDATE SKIP LOCKED to prevent
 * double-processing across multiple worker instances.
 *
 ***************************************************************/

error_reporting(E_ALL);
ini_set("display_errors", true);

ignore_user_abort(true);
define('WORKER_MAX_RUNTIME', 300); // Exit after 5 min, systemd restarts
set_time_limit(WORKER_MAX_RUNTIME + 30); // Hard kill safety net

//*** For CLI access: Edit $_SERVER to match your production server (see service.php) ***
if (!isset($_SERVER['SERVER_NAME'])) {
	$_SERVER['SERVER_NAME'] = "localhost";
	$_SERVER['REMOTE_ADDR'] = "trigger_worker";
	$_SERVER['PHP_SELF'] = "/ltx/sw/service/trigger_worker.php";
	$_SERVER['HTTP_HOST'] = "localhost";
	$_SERVER['SERVER_PORT'] = 80;
}

include_once(__DIR__ . "/../conf/api_key.inc.php");
include_once(__DIR__ . "/../conf/config.inc.php");

// Queue DB connection (separate from trigger's $pdo)
$qpdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
$qpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$qpdo->query("SET @@session.time_zone='+00:00'");

$worker_id = $argv[1] ?? getmypid();
$worker_start = time();
echo "trigger_worker #$worker_id started\n";

$idle_count = 0;

while (time() - $worker_start < WORKER_MAX_RUNTIME) {
	try {
		$qpdo->beginTransaction();
		$stmt = $qpdo->query("SELECT * FROM trigger_queue ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED");
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$qpdo->rollBack();
			$idle_count++;
			// Backoff: 100ms -> 500ms -> 1s after prolonged idle
			if ($idle_count > 100) usleep(1000000);
			else if ($idle_count > 10) usleep(500000);
			else usleep(100000);
			continue;
		}

		// Delete before processing (claim the job)
		$qpdo->prepare("DELETE FROM trigger_queue WHERE id = ?")->execute([$row['id']]);
		$qpdo->commit();
		$idle_count = 0;

		// Run trigger in isolated scope
		$mac = $row['mac'];
		$xlog = "(trigger_worker:#$worker_id)";
		$dbg = 0;

		if (@file_exists(S_DATA . "/$mac/cmd/dbg.cmd")) $dbg = 1;

		include_once(__DIR__ . "/../lxu_loglib.php");
		include_once(__DIR__ . "/../lxu_trigger.php");

		$now = time();
		run_trigger($row['mac'], $row['reason'], $row['vpnf'] ? '' : null);

	} catch (Exception $e) {
		if ($qpdo->inTransaction()) $qpdo->rollBack();
		// Log error and continue
		$errmsg = gmdate("Y-m-d H:i:s") . " UTC trigger_worker #$worker_id ERROR: " . $e->getMessage() . "\n";
		@file_put_contents(S_DATA . "/log/trigger_worker.log", $errmsg, FILE_APPEND);
		usleep(1000000); // 1s pause on error
	}
}

