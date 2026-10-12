<?php
// A small file browser for a given subdirectory for the
// incomming LTX data...
// Show Header in RED for filebrowser
// (C) TerraTransfer GmbH
//
// V1.10: Tested PHP8
error_reporting(E_ALL);

include_once(__DIR__ . "/_legacy.inc.php");
session_start();
if (isset($_REQUEST['k'])) {
	$api_key = is_string($_REQUEST['k']) ? $_REQUEST['k'] : '';
	if (hash_equals(L_KEY, $api_key)) $_SESSION['key'] = L_KEY;
} else $api_key = @$_SESSION['key'];
if (!strcmp($api_key, L_KEY)) {
	$dev = 1;	// Dev-Funktionen anzeigen
} else {
	$dev = 0;	// Dev-Funktionen anzeigen
}
?>
<!DOCTYPE HTML>
<html>

<head>
	<title>Legacy LTX Browser V1.10 </title>
</head>

<body>
	<?php
	if (!$dev) {
		echo "ERROR: Access denied!";
		exit();
	}

	// --- Convert to Timestring
	function  secs2period($secs)
	{
		if ($secs >= 86400) return "over " . floor($secs / 3600) . "h";
		return gmdate('H\h\r i\m\i\n s\s\e\c', $secs);
	}

	//---- MAIN -----
	$now = time();
	$base = @realpath(LG_DATA);
	$dir = @$_GET['dir'];
	if (!is_string($dir) || !strlen($dir)) $dir = $base; 	// Default Dir is DATA
	$actpath = @realpath($dir);
	$anz = 0;
	echo "<p><b><big><font color=\"red\">Legacy LTX Browser V1.10 - Directory '" . htmlspecialchars((string)$actpath) . "'</font></big></b><br></p>";
	echo "<p><a href=\"index.php\">Legacy LTX Home</a><br></p>";

	// --- Only the data directory and its sub directories ---
	if (!$base || !$actpath || strncmp($actpath . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR, strlen($base) + 1)) {
		echo "<p><b>Invalid path!</b></p>";
		$dir = "";	// -> Gen. Error: Directory not found
	} else {
		$dir = $actpath;
		if (strcmp($dir, $base)) {	// Not higher than data...
			echo "<p><a href=\"browse.php?dir=" . urlencode(dirname($dir)) . "\">[ . . ]</a><br></p>";
		}
	}
	$rel = ($dir !== "") ? str_replace('\\', '/', substr($dir, strlen($base) + 1)) : "";	// relative to the data directory

	if (file_exists($dir)) {
		$list = scandir($dir);
		// Directories first
		$dircnt = 0;
		foreach ($list as $file) {
			if ($file == '.' || $file == '..') continue;
			if (is_dir("$dir/$file")) {
				echo "<a href=\"browse.php?dir=" . urlencode("$dir/$file") . "\">[$file]</a><br>";
				$dircnt++;
			}
		}
		if ($dircnt) echo "($dircnt Directories)<br>";
		echo "</p><p>";

		$dstot = 0;
		// Then files
		foreach ($list as $file) {
			if ($file == '.' || $file == '..') continue;
			if (is_dir("$dir/$file")) continue;
			$ds = filesize("$dir/$file");
			$dstot += $ds;
			$fa = secs2period($now - filemtime("$dir/$file"));
			$link = "";	// Files are shown via view.php (the data directory is not reachable by the web server)
			if ($rel === 'log') $link = "view.php?s=log&f=" . urlencode($file);
			else if (preg_match('/^([0-9A-F]{16})(?:\/(files|cmd|get|put|del|in_new|out_total|dbg))?$/', $rel, $m)) {
				$link = "view.php?s=$m[1]&f=" . urlencode((isset($m[2]) ? "$m[2]/" : "") . $file);
			}
			if ($link) echo "<a href=\"$link\">$file</a> ($ds Bytes, Age: $fa)<br>";
			else echo "$file ($ds Bytes, Age: $fa)<br>";
			$anz++;
		}
		if ($anz) echo "($anz Files, total: $dstot Bytes)<br>";
		else echo "(No files)<br>";
		echo "</p>";
	} else {
		echo "<p><b>ERROR: Directory not found!</b></p>";
	}
	?>
</body>

</html>
