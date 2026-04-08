<?php
// Add idx_line_ts index to all existing m$MAC data tables.
// Run once: php sw/service/alter_mtables_index.php

error_reporting(E_ALL);

if (!isset($_SERVER['SERVER_NAME'])) {
	$_SERVER['SERVER_NAME'] = "localhost";
}

include_once(__DIR__ . "/../conf/api_key.inc.php");
include_once(__DIR__ . "/../conf/config.inc.php");

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$count = 0;
$skipped = 0;

foreach ($tables as $table) {
	if (strlen($table) != 17 || $table[0] !== 'm') continue; // m + 16 hex chars
	if (!preg_match('/^m[0-9A-F]{16}$/', $table)) continue;

	// Check if index already exists
	$indexes = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = 'idx_line_ts'")->fetchAll();
	if (count($indexes)) {
		$skipped++;
		continue;
	}

	echo "ALTER TABLE $table ADD INDEX idx_line_ts (line_ts)...";
	$pdo->exec("ALTER TABLE `$table` ADD INDEX idx_line_ts (`line_ts`)");
	echo " OK\n";
	$count++;
}

echo "Done. $count tables altered, $skipped already had index.\n";
