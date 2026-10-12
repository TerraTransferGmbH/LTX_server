<?php
// _legacy.inc.php - shared helpers of the web interface (LTX server, minimal version)
// (C) TerraTransfer GmbH
include_once(__DIR__ . "/../sw/conf/api_key.inc.php");

// Absolute data directory (S_DATA may be given relative to 'sw/' and 'legacy/')
define("LG_DATA", preg_match('~^(/|[A-Za-z]:[\\\\/])~', S_DATA) ? S_DATA : __DIR__ . "/" . S_DATA);

// Stop with a plain text error message
function lg_fail($msg)
{
	if (!headers_sent()) header('Content-Type: text/plain');
	echo "ERROR: $msg\n";
	exit();
}

// Device address: exactly 16 hex digits, returned in upper case
function lg_mac($mac)
{
	if (!is_string($mac) || !preg_match('/^[0-9A-Fa-f]{16}$/', $mac)) lg_fail("MAC format");
	return strtoupper($mac);
}

// File name inside a device directory, optionally with one of the allowed sub directories
// ($subdirs: regular expression alternatives, e.g. "files|cmd|get|put|del")
function lg_fname($fname, $subdirs = "")
{
	if (!is_string($fname) || !strlen($fname) || strlen($fname) > 64) lg_fail("File name");
	$name = '[A-Za-z0-9_][A-Za-z0-9_.\\-]*';
	$re = strlen($subdirs) ? "/^(?:(?:$subdirs)\\/)?$name$/" : "/^$name$/";
	if (!preg_match($re, $fname) || strpos($fname, "..") !== false) lg_fail("File name");
	return $fname;
}

// Name of a temporary CSV file in '<data>/stemp' (created by csview)
function lg_tmpname($fname)
{
	if (!is_string($fname) || !preg_match('/^t[0-9]{1,24}\\.csv$/', $fname)) lg_fail("File name");
	return $fname;
}
?>
