<?php
// FILE: lxu_loglib.php  - Version:04.12.2022 (minimal file-mode version, (C) TerraTransfer GmbH)
// include-module logfile only for LXU modules (path on 1.st level)!

// ---- basic directory service ---
function check_dirs($wcmd = true)
{
	global $dbg, $mac, $dapikey;
	$sdata = S_DATA;

	$newdev = false;
	// Data and LOG always required
	ensure_sdata($sdata);  // MainDirectory (protected against direct web access)
	if (!file_exists($sdata . "/log")) mkdir($sdata . "/log");  // Logfiles
	if(!isset($mac) || strlen($mac)!==16) return;	// No MAC
	if($dapikey === false) return -1; // dir must exist
	if (!file_exists($sdata . "/$mac")) {
		mkdir($sdata . "/$mac");
		file_put_contents($sdata . "/$mac/date0.dat", time()); // Note initial date
		file_put_contents($sdata . "/$mac/quota_days.dat", DB_QUOTA); 
		$newdev = $wcmd;
	}
	if (!file_exists($sdata . "/$mac/cmd")) {
		mkdir($sdata . "/$mac/cmd");
		// Test to get server.lxp
		if ($newdev == true) {
			file_put_contents($sdata . "/$mac/cmd/getdir.cmd", "123");	// 3 Tries to get Directoy
		}
	}
	if (!file_exists($sdata . "/$mac/files")) {	// File mirror
		mkdir($sdata . "/$mac/files");
	}
	if (!file_exists($sdata . "/$mac/get")) {	// List of Files to upload
		mkdir($sdata . "/$mac/get");
		// Test to get server.lxp
		if ($newdev == true) {
			file_put_contents($sdata . "/$mac/get/sys_param.lxp", "123");	// 3 Tries to get sys_param.lxp
		}
	}
	if (!file_exists($sdata . "/$mac/put")) {	// List of Files to download
		mkdir($sdata . "/$mac/put");
	}
	if (!file_exists($sdata . "/$mac/del")) {	// List of Files to delete
		mkdir($sdata . "/$mac/del");
	}
	if (!file_exists($sdata . "/$mac/in_new")) {	// New incomming
		mkdir($sdata . "/$mac/in_new");
	}
	if ($dbg && !file_exists($sdata . "/$mac/dbg")) {
		mkdir($sdata . "/$mac/dbg");
	}
	return 0;	// OK
}

// ----- Quick Exit on errors -----
function exit_error($err)
{
	global $xlog;
	echo "ERROR: '$err'\n";
	$xlog .= "(ERROR:'$err')";
	add_logfile();
	exit();
}

// ------ Write LogFile (carefully) -------- (similar to lxu_xxx.php)
function add_logfile()
{
	global $xlog, $dbg, $mac, $now;

	$sdata = S_DATA;
	$logpath = $sdata . "/log/";
	if (@filesize($logpath . "log.txt") > 100000) {	// Main LOG
		@unlink($logpath . "_log_old.txt");
		rename($logpath . "log.txt", $logpath . "_log_old.txt");
		$xlog .= " (Main 'log.txt' -> '_log_old.txt')";
	}

	if(!isset($mac)) $mac="UNKNOWN_MAC";
	if ($dbg) $xlog .= "(DBG:$dbg)";

	$line = gmdate("d.m.y H:i:s ", $now) . "UTC " . $_SERVER['REMOTE_ADDR'];
	if (strlen($mac)) $line .= " MAC:$mac";
	$line .= " $xlog\n";
	@file_put_contents($sdata . "/log/log.txt", $line, FILE_APPEND | LOCK_EX);
	// User Logfile - Text
	if (strlen($mac) == 16 && file_exists($sdata . "/$mac")) {
		$logpath = $sdata . "/$mac/";
		if (@filesize($logpath . "log.txt") > 50000) {	// Device LOG
			@unlink($logpath . "_log_old.txt");
			rename($logpath . "log.txt", $logpath . "_log_old.txt");

			if (@filesize($logpath . "userio.txt") > 50000) {	// User Commands
				@unlink($logpath . "_userio_old.txt");
				rename($logpath . "userio.txt", $logpath . "_userio_old.txt");
			}
			if (@filesize($logpath . "conn_log.txt") > 50000) {	// Connection Log
				@unlink($logpath . "_conn_log_old.txt");
				rename($logpath . "conn_log.txt", $logpath . "_conn_log_old.txt");
			}
		}

		$line = gmdate("d.m.y H:i:s ", $now) . "UTC $xlog\n";
		@file_put_contents($logpath . "log.txt", $line, FILE_APPEND | LOCK_EX);
	}
}

// ---- Create the main data directory and deny direct web access to it (Apache) ----
function ensure_sdata($sdata)
{
	if (!file_exists($sdata)) @mkdir($sdata, 0750, true);
	if (!file_exists("$sdata/.htaccess")) {
		@file_put_contents("$sdata/.htaccess", "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
	}
}

// ---- Minimal version: append the new upload fragments ('in_new/*.edt') to the device's
// cumulative data file 'out_total/total.edt' (chronological order) and delete them.
// Fragments of parameter files are ignored (the current versions are mirrored in 'files/').
function legacy_trigger()
{
	global $mac, $xlog;
	$dpath = S_DATA . "/$mac";
	$inpath = "$dpath/in_new";
	$flist = @scandir($inpath, SCANDIR_SORT_ASCENDING);	// Names start with the upload time
	if (!$flist) return;
	$outdir = "$dpath/out_total";
	$added = 0;
	$ignored = 0;
	$bytes = 0;
	$of = false;
	foreach ($flist as $fname) {
		if ($fname === '.' || $fname === '..') continue;
		$ffile = "$inpath/$fname";
		if (!is_file($ffile)) continue;
		if (substr($fname, -4) !== '.edt') {	// Only EDT fragments (data, user commands)
			$ignored++;
			@unlink($ffile);
			continue;
		}
		$cont = file_get_contents($ffile);
		if ($cont === false) continue;
		if ($of === false) {
			if (!file_exists($outdir)) @mkdir($outdir);
			$of = @fopen("$outdir/total.edt", 'ab');
			if ($of === false) {
				$xlog .= "(ERROR: can't open 'out_total/total.edt')";
				return;
			}
			flock($of, LOCK_EX);
		}
		if (strlen($cont) && substr($cont, -1) !== "\n") $cont .= "\n";
		fwrite($of, $cont);
		$bytes += strlen($cont);
		$added++;
		@unlink($ffile);
	}
	if ($of !== false) {
		fflush($of);
		flock($of, LOCK_UN);
		fclose($of);
	}
	if ($added || $ignored) $xlog .= "($added fragments/$bytes bytes -> 'out_total/total.edt', $ignored ignored)";
}
