<?php
/***********************************************************
 * w_pcp.php - push-cmd-pull worker fuer einzelne Logger LTX
 *
 * Entwickler: Juergen Wickenhaeuser, joembedded@gmail.com
 *
 * Beispielaufrufe (ggfs. $dbg) setzen):
 *
 * Fuer einfache CMDs per URL (z.B. interne Aufrufe): k kann auch S_API_KEY sein!

//Basis-Aufruf / Ausgabe Version
http://localhost/ltx/sw/w_php/w_pcp.php?cmd

// Listet alle Devives zu diesem Key auf
http://localhost/ltx/sw/w_php/w_pcp.php?k=ABC&cmd=list 

// Device Details (device-Eintrag aus DB)
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=details

// Daten Zeilen aus DB ausgeben (opt. limits minid/maxid)
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=getdata&minid=1800 
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k='S_API_KEY'&cmd=getdata&minid=1800

// Parameter 'iparam.lxp' zu diesem Device mit Beschreibung ausgeben
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=iparam
http://localhost/ltx/sw/w_php/w_pcp.php?s=DDC2FB99207A7E7E&k='S_API_KEY'&cmd=iparam

// Parameter 'iparam.lxp' zu diesem Device aendern 
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=iparamchange&iparam[5]=NeuMeriva&iparam[6]=3601

// Pending Parameter zu diesem Device entfernen
http://localhost/ltx/sw/w_php/w_pcp.php?s=26FEA299F444F836&k=ABC&cmd=iparamunpend

 * Um kompliziertere Sachen per JSON zu uebergeben z.B. per Script und jquery:
 * (Geht nat. umstaendlich auch per URL, z.B. ```&jo[lang]="de"&jo[age]=58```)
   http://localhost/wrk/pushpull/call_pcp.php
 *
 * Parameter:
 * cmd: Kommando
 * k: AccessKey (aus 'quota_days.dat') (opt.)
 * s: MAC(16-Digits) (opt.)
 * d: Debug-Level (1,2) - erfordert k=S_API_KEY, aktiviert pcplog-Logging
 * 
 * cmd:
 * '':		Version
 * list:	Alle MACs mit Zugriff auflisten (nur 'k' benoetigt)
 * details:	Kompletten Record zu dieser MAC aus 'devices'. Enth. noch viel optionale Platzhalter
 * iparam:	Parameter-File 'iparam.lxp' zu diesem Device
 * iparamrunpend:	iparam-pendinge-loeschen
 * iparamchange:	Parameter aendern und speichern
 * getdata:	mMAC ausgeben mit opt. minid und/oder maxid
 * getfile:	Datei(en) aus files/ lesen (file=data.edt, bak=1 fuer .bak dazu, limit=N)
 * deviceinfo:	device_info.dat als JSON (fw, signal, dBm..)
 * 
 * Status-Returns:
 * 0:	OK
 * 100: Keine Tabelle mMAC fuer diese MAC
 * 101: Keine Parameter gefunden fuer diese MAC
 * 102: Unbekanntes Kommando cmd
 * 103: Mehr als 90 Messkanaele nicht moeglich
 * 104,105: Index Error bei 'iparam'
 * 106: Keine geaenderten Parameter gefunden
 * 108: Ungueltiger 'iparam' Payload
 * ...
 * 300-699: Wie BlueBlx.cs (siehe checkiparam())
 * ...
 */

define('VERSION', "LTX V1.13 04.03.2026");
define('VERSION_TT', "TT V0.1 16.03.2026");

error_reporting(E_ALL);
ini_set("display_errors", true);

header("Content-type: application/json; charset=utf-8");
header('Access-Control-Allow-Origin: *');	// CORS enabler

$mtmain_t0 = microtime(true);         // fuer Benchmark
$tzo = timezone_open('UTC');
$now = time();

require_once("../conf/config.inc.php");	// DB Access 
require_once("../conf/api_key.inc.php"); // APIs
require_once("../inc/db_funcs.inc.php"); // Init DB

$dbg = (isset($_REQUEST['d']) && $_REQUEST['k'] === S_API_KEY) ? intval($_REQUEST['d']) : 0;
$fpath = "../" . S_DATA;	// Globaler Pfad auf Daten
$xlog = ""; // Log-String

/************************************************************
 * Falls gewunscht koennten alle Zeilen mit Infos versehen
 * werden. Bei Bedarf bitte Bescheid geben
 * Die Parameter werden ja selten transportiert, daher kann man
 * sich denke ich den Luxus der Beschreibung erlauben
 ************************************************************/
// Beschreibung der Parameter damit leichter lesbar
$p100beschr = array( // SIZE der gemeinsamen Parameter hat MINIMALE Groesse
	"*@100_System",
	"*DEVICE_TYP", // WICHTIG: Zeilen mit '*' duerfen NICHT vom User geendert werden
	"*MAX_CHANNELS", // *
	"*HK_FLAGS",     // *
	"*NewCookie [Parameter 10-digit Timestamp.32]", // (*) Bei aenderung neuer Zeitstempel hier eintragen!
	"Device_Name[BLE:$11/total:$41]",
	"Period_sec[10..86400]",	// Mess-Periode
	"Period_Offset_sec[0..(Period_sec-1)]",
	"Period_Alarm_sec[0..Period_sec]",
	"Period_Internet_sec[0..604799]",   // Internet-Uebertragungs-Periode
	"Period_Internet_Alarm_sec[0..Period_Internet_sec]",
	"UTC_Offset_sec[-43200..43200]",
	"Flags (B0:Rec B1:Ring) (0: RecOff)",
	"HK_flags (B0:Bat B1:Temp B2.Hum B3.Perc)",
	"HK_reload[0..255]",
	"Net_Mode (0:Off 1:OnOff 2:On_5min 3:Online)",
	"ErrorPolicy (O:None 1:RetriesForAlarms, 2:RetriesForAll)",
	"MinTemp_oC[-40..10]",
	"Config0_U31 (B0:OffPer.Inet:On/Off B1,2:BLE:On/Mo/Li/MoLi B3:EnDS B4:CE:Off/On B5:Live:Off/On)",
	"Configuration_Command[$79]",	
	"Internet_Offset[0..86399]"	
);
$pkanbeschr = array( // SIZE eines Kanals ist absolut FIX
	"*@ChanNo",  // (*) Neue Kanaele dazufuegen ist erlaubt, sofer aufsteigend und komplett
	"Action[0..65535] (B0:Meas B1:Cache B2:Alarms)",
	"Physkan_no[0..65535]",
	"Kan_caps_str[$8]",
	"Src_index[0..255]",
	"Unit[$8]",
	"Mem_format[0..255]",
	"DB_id[0..2e31]",
	"Offset[float]",
	"Factor[float]",
	"Alarm_hi[float]",
	"Alarm_lo[float]",
	"Messbits[0..65535]",
	"Xbytes[$32]"
);

$parLastChanIdx=-1; 
$parChan0Idx=-1;
$parChanSize=-1;
$parLastChanNo=-1;


// ------ Write LogFile (carefully and out-of-try()/catch()) -------- (similar to lxu_xxx.php)
function add_logfile()
{
	global $xlog, $dbg, $mac, $now, $fpath;
	if (@filesize("$fpath/log/pcplog.txt") > 100000) {	// Main LOG
		@unlink("$fpath/log/_pcplog_old.txt");
		rename("$fpath/log/pcplog.txt", "$fpath/log/_pcplog_old.txt");
		$xlog .= " (Main 'pcplog.txt' -> '_pcplog_old.txt')";
	}

	if (!isset($mac)) $mac = "UNKNOWN_MAC";
	if ($dbg) $xlog .= "(DBG:$dbg)";

	$dstr=gmdate("d.m.y H:i:s ", $now) . "UTC ";
	$log = @fopen("$fpath/log/pcplog.txt", 'a');
	if ($log) {
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log,  $dstr. $_SERVER['REMOTE_ADDR']. " PCP");        // Write file
		if (strlen($mac)) fputs($log, " MAC:$mac"); // mac only for global lock
		fputs($log, " $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
	// User Logfile - Text
	if (strlen($mac) == 16 && file_exists("$fpath/$mac")) {
		if (@filesize("$fpath/$mac/pcplog.txt") > 50000) {	// Device LOG
			@unlink("$fpath/$mac/_pcplog_old.txt");
			rename("$fpath/$mac/pcplog.txt", "$fpath/$mac/_pcplog_old.txt");
		}

		$log = fopen("$fpath/$mac/pcplog.txt", 'a');
		if (!$log) return;
		while (!flock($log, LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
		fputs($log, $dstr."PCP $xlog\n");        // evt. add extras
		flock($log, LOCK_UN);
		fclose($log);
	}
}

try {
	// Check Access-Token for this Device
	// Uses APCu cache if available (php-apcu), falls back to file read.
	// Cache is invalidated by filemtime change (e.g. after quota edit via w_rad.php or legacy).
	function checkAccess($lmac, $ckey)
	{
		global $fpath;
		if ($ckey == S_API_KEY) return true;	// S_API_KEY valid for ALL

		$keys = null;
		$qfile = "$fpath/$lmac/quota_days.dat";
		$cache_key = "quota_keys_$lmac";

		if (function_exists('apcu_fetch')) {
			$cached = apcu_fetch($cache_key);
			if ($cached !== false) {
				$mtime = @filemtime($qfile);
				if ($mtime === $cached['mtime']) {
					$keys = $cached['keys'];
				}
			}
		}

		if ($keys === null) {
			$keys = [];
			$quota = @file($qfile, FILE_IGNORE_NEW_LINES);
			if (isset($quota[2]) && strlen($quota[2])) {
				$qpar = explode(' ', trim(preg_replace('/\s+/', ' ', $quota[2])));
				for ($i = 1; $i < count($qpar); $i++) $keys[] = $qpar[$i];
			}
			if (function_exists('apcu_store')) {
				apcu_store($cache_key, ['keys' => $keys, 'mtime' => @filemtime($qfile)], 3600);
			}
		}

		return in_array($ckey, $keys);
	}

	function isValidMac($mac)
	{
		return preg_match('/^[A-F0-9]{16}$/', $mac) === 1;
	}

	function getcurrentiparam()
	{
		global $fpath, $mac, $retResult;
		$par = @file("$fpath/$mac/put/iparam.lxp", FILE_IGNORE_NEW_LINES); // pending Parameters?
		if ($par != false) {
			$retResult['par_pending'] = true;	// Return - Pending Parameters!
		} else {
			$par = @file("$fpath/$mac/files/iparam.lxp", FILE_IGNORE_NEW_LINES); // No NL, but empty Lines OK
			$retResult['par_pending'] = false;	// On Dev.
		}
		return $par;
	}

	// Prueft Zahlenwert auf Grenzen
	function nverify($str, $ilow, $ihigh){
		if(!is_numeric($str)) return true;
		$val = intval($str);
		if($val<$ilow || $val>$ihigh) return true;	// Fehler
		return false;
	}
	function nisfloat($str){	// PHP recht relaxed, alles als Float OK, daher mind. 1 char.
		if(!is_numeric($str)) return true;
		return false;
	}
	// Pruefen einer Parameterdatei - return NULL (OK) oder Status - (wie in BlueShell: BlueBlx.cs)
	function checkiparam($par)
	{
		global $parLastChanIdx, $parLastChanNo, $parChanSize, $pkanbeschr, $parChan0Idx;

		$parLastChanIdx=-1; // unset incompat. to global
		$parChan0Idx=-1;
		// 1. Teil Pruefen der Gemeinsamen Parameter
		if ($par[0] !== '@100') return "301 File Format (No valid 'iparam.lxp', ID must be '@100')";
		for ($i = 1; $i < count($par); $i++) {	// Scan for last parameter in Src
			if (@$par[$i][0] == '@') {
				$parLastChanIdx = $i;
				if($parChan0Idx<0) $parChan0Idx = $i;
			}
		}
		if ($parLastChanIdx<0) return "300 File Size 'iparam.lxp' (too small)";
		$parLastChanNo = intval(substr($par[$parLastChanIdx], 1));
		if ($parLastChanNo<0 || $parLastChanNo > 89) return "399 Invalid Parameters 'iparam.lxp";
		if ($parLastChanNo>= intval($par[2])) return "398 Too many channels";
		$parChanSize = count($par) - $parLastChanIdx;
		if($parChanSize!=count($pkanbeschr)) return "300 File Size 'iparam.lxp' (too small)";
		// Anfangsteil checken 
		if(nverify($par[1],0,9999)) return "302 Illegal DEVICE_TYP";
		if(nverify($par[2],1,90)) return "303 MAX_CHANNELS out of range";
		if(nverify($par[3],0,255)) return "304 HK_FLAGS out of range";
		if(strlen($par[4])!= 10) return "305 Cookie (must be exactly 10 Digits)";
		if(strlen($par[5])>41) return "306 Device Name Len"; // len=0: Use DefaultAdvertising Name
		if(nverify($par[6],10,86400)) return "307 Measure Period out of range";
		if(nverify($par[7],0,intval($par[6])-1)) return "308 Period Offset (must be < than Period)";
		if(nverify($par[8],0,intval($par[6]))) return "309 Alarm Period out of range";
		if(nverify($par[9],0,604799)) return "310 Internet Period out or range";
		if(nverify($par[10],0,intval($par[9]))) return "311 Internet Alarm Period (must be <= than Internet Period)";
		if(nverify($par[11],-43200,43200)) return "312: UTC Offset out or range";

		if(nverify($par[12],0,255)) return "313 Record Flags out of range";
		if(nverify($par[13],0,255)) return "314 HK Flags out of range";
		if(nverify($par[14],0,255)) return "315 HK Reload out of range";
		if(nverify($par[15],0,255)) return "316 Net Mode out of range";
		if(nverify($par[16],0,255)) return "317 Error Policy out of range";
		if(nverify($par[17],-40,10)) return "318 MinTemp oC out of range";
		if(nverify($par[18],0,0x7FFFFFFF)) return "319 U31_Unused";
		if(strlen($par[19])>79) return "320 Configuration Command Len"; 

		$pidx = $parChan0Idx;
		if($pidx< 19 ) return "600: Missing Channel #0 (at least 1 channel required)"; // Min. iparam
		$chan = 0;
		for(;;){
			if (@$par[$pidx][0] != '@' || intval(substr($par[$pidx],1)!=$chan))	return "615 Unexpected Line in Channel #$chan";

			if(nverify($par[$pidx+1],0,255)) return "602 Action for Channel #$chan";
			if(nverify($par[$pidx+2],0,65535)) return "603 PhysChan for Channel #$chan";
			if(strlen($par[$pidx+3])>8 ) return "604 KanCaps Len for Channel #$chan";
			if(nverify($par[$pidx+4],0,255)) return "605 SrcIndex for Channel #$chan";
			if(strlen($par[$pidx+5])>8) return "606 Unit Len for Channel #$chan";
			if(nverify($par[$pidx+6],0,255)) return "607 Number Format  for Channel #$chan";
			if(nverify($par[$pidx+7],0,0x7FFFFFFF)) return "608 DB_ID for Channel #$chan";
			if(nisfloat($par[$pidx+8])) return "609 Offset for Channel #$chan (Format requires Decimal Point)";
			if(nisfloat($par[$pidx+9])) return "610 Factor for Channel #$chan (Format requires Decimal Point)";
			if(nisfloat($par[$pidx+10])) return "611 Alarm_Hi for Channel #$chan (Format requires Decimal Point)";
			if(nisfloat($par[$pidx+11])) return "612 Alarm_Low for Channel #$chan (Format requires Decimal Point)";
			if(nverify($par[$pidx+12],0,65535)) return "613 MeasBits for Channel #$chan";
			if(strlen($par[$pidx+13])>32) return "614 XBytes Len for Channel #$chan";
			if($pidx == $parLastChanIdx ) break;
			$chan++;
			$pidx +=  $parChanSize;
		}
			
		// Kanalteil checken
		return null;	// OK
	}

	//=========== MAIN ==========
	$retResult = array();

	if ($dbg > 1) print_r($_REQUEST); // Was wollte man DBG (2)

	$cmd_str = @$_REQUEST['cmd'];
	if (!isset($cmd_str)) $cmd_str = "";
	$cmds = array_map('trim', explode(',', $cmd_str));
	$cmd_statuses = array();
	$has_cmd_success = false;
	$has_cmd_error = false;
	$first_error_status = null;
	$ckey = @$_REQUEST['k'];	// s always MAC (k: API-Key, r: Reason)
	if($ckey == S_API_KEY) $xlog = "(cmd:'$cmd_str')"; // internal
	else $xlog = "(cmd:'$cmd_str', k:'$ckey')";

	// --- cmd PreStart - CMD vorfiltern ---
	// Check if version or list requested
	if (in_array('', $cmds) && count($cmds) == 1) {
		$retResult['version'] = VERSION;
	}
	if (in_array('list', $cmds)) {
		db_init();
		$statement = $pdo->prepare("SELECT mac FROM devices");
		$qres = $statement->execute();
		if ($qres == false) throw new Exception("DB 'devices'");
		$macarr = array();
		while ($ldev = $statement->fetch()) {
			$lmac = $ldev['mac'];
			if (checkAccess($lmac, $ckey)) {
				$macarr[] = $lmac;
			}
		}
		if (!count($macarr)) throw new Exception("No Access");
		$retResult['list_count'] = count($macarr);
		$retResult['list_mac'] = $macarr;
	}

	// Check if any device-specific command present
	$needs_device = false;
	$needs_db = false;
	$has_data_table = true;
	foreach ($cmds as $c) {
		if ($c !== '' && $c !== 'list') $needs_device = true;
		if (in_array($c, array('details', 'getdata', 'iparamchange', 'iparamunpend'))) $needs_db = true;
	}

	if ($needs_device) {
		$mac = @$_REQUEST['s'];
		if (!isset($mac)) $mac = "";
		$mac = strtoupper($mac);
		if (!isValidMac($mac)) {
			throw new Exception("MAC format");
		}
		if (!checkAccess($mac, $ckey)) {
			throw new Exception("No Access");
		}

		// DB and overview only if needed
		if ($needs_db) {
			db_init();
			if (in_array('details', $cmds)) {
				$deviceSelect = "SELECT * FROM devices WHERE mac = ?";
			} else {
				$deviceSelect = "SELECT mac, last_change, last_seen, name, units, vals, cookie, transfer_cnt, lines_cnt, warnings_cnt, alarms_cnt, err_cnt, anz_lines FROM devices WHERE mac = ?";
			}
			$statement = $pdo->prepare($deviceSelect);
			$qres = $statement->execute(array($mac));
			if ($qres == false) throw new Exception("MAC $mac not in 'devices'");
			$devres = $statement->fetch();

			$ovv = array();
			$ovv['mac'] = $mac;
			$ovv['db_now'] = gmdate("Y-m-d H:i:s");

			$statement = @$pdo->prepare("SELECT MIN(id) as minid, MAX(id) as maxid FROM m$mac");
			if ($statement === false || $statement->execute() === false) {
				$has_data_table = false;
				$maxid = $minid = -1;
			} else {
				$mm = $statement->fetch();
				$maxid = $mm['maxid'];
				$minid = $mm['minid'];
			}
			$ovv['min_id'] = $minid;
			$ovv['max_id'] = $maxid;
			if (!in_array('details', $cmds)) {
				$ovv['last_change'] = $devres['last_change'];
				$ovv['last_seen'] = $devres['last_seen'];
				$ovv['name'] = $devres['name'];
				$ovv['units'] = $devres['units'];
				$ovv['vals'] = $devres['vals'];
				$ovv['cookie'] = $devres['cookie'];
				$ovv['transfer_cnt'] = $devres['transfer_cnt'];
				$ovv['lines_cnt'] = $devres['lines_cnt'];
				$ovv['warnings_cnt'] = $devres['warnings_cnt'];
				$ovv['alarms_cnt'] = $devres['alarms_cnt'];
				$ovv['err_cnt'] = $devres['err_cnt'];
				$ovv['anz_lines'] = $devres['anz_lines'];
			}
			$retResult['overview'] = $ovv;
		}
	}

	// --- cmd Main Start - CMD auswerten (loop for multi-cmd support) ---
	foreach ($cmds as $cmd) {
	$cmd_status = null;
	switch ($cmd) {
		case '': // VERSION
		case 'list': // Liste schon fertig
			break;

		case 'details':	// Einfach ALLES fuer diese MAC
			$retResult['details'] = $devres;
			break;

		case 'deviceinfo': // Device Info file (fw, dBm..)
			$di_file = "$fpath/$mac/device_info.dat";
			if (!file_exists($di_file)) {
				$retResult['deviceinfo'] = "No device information file found";
				break;
			}
			$lines = file($di_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$data = array();
			foreach ($lines as $line) {
				$parts = explode("\t", $line, 2);
				if (count($parts) !== 2) continue;
				$key = $parts[0];
				$value = $parts[1];
				if ($key === 'signal' && strlen($value)) {
					$signalArray = array();
					$subParts = explode(" ", $value);
					foreach ($subParts as $subPart) {
						if (strpos($subPart, ':') !== false) {
							list($subKey, $subValue) = explode(':', $subPart, 2);
							if (is_numeric($subValue)) $subValue = intval($subValue);
							$signalArray[$subKey] = $subValue;
						}
					}
					$data[$key] = $signalArray;
				} else {
					if (is_numeric($value)) $value = intval($value);
					$data[$key] = $value;
				}
			}
			$retResult['deviceinfo'] = $data;
			break;

		case 'iparam': // Parameterfile fuer diese MAC
			$par = getcurrentiparam();
			if ($par == false) {
				$cmd_status = "101 No Parameters found for MAC:$mac";
				break;
			}
			$chkres = checkiparam($par);
			if($chkres != null) { 
				$cmd_status = $chkres;
				break;
			}
			$vkarr = [];	// Ausgabe der Parameter etwas verzieren fuer leichtere Lesbarkeit
			foreach ($par as $p) {
				if (strlen($p) && $p[0] == '@') {
					$ktyp = intval(substr($p, 1));
					if ($ktyp < 0 || $ktyp > 100) throw new Exception("Parameter 'iparam.lxp' invalid");
					if ($ktyp == 100) {
						$infoarr = $p100beschr;
						$lcnt = 0;	// Damit MUSS es beginnen
						$erkl = "Common";
					} else {
						$infoarr = $pkanbeschr;
						$erkl = "Chan $ktyp";
					}
					$ridx = 0;
				}
				$info = @$infoarr[$ridx] . " ($erkl, Line $lcnt)"; // Juer jede Zeile: Erklaere Bedeutung
				$vkarr[] = array('line' => $p, 'info' => $info); // Line, Value, Text
				$ridx++;
				$lcnt++;
			}
			$ipov = array(); // Parameter Overview
			$ipov['chan0_idx'] =  $parChan0Idx; // Index Channel 0
			$ipov['chan_anz'] = $parLastChanNo+1; // Anzahl Channels
			$ipov['lines_per_chan'] =  $parChanSize; // Lines per channel

			$retResult['iparam_meta'] = $ipov;

			$retResult['iparam'] = $vkarr;
			break;

		case 'iparamchange': // Parameter changes sorgfaeltig einpflegen. Probleme melden
			$opar = $par = getcurrentiparam();
			if ($par == false) {
				$cmd_status = "101 No Parameters found for MAC:$mac";
				break;
			}
			$chkres = checkiparam($par);
			if($chkres != null) { 
				$cmd_status = $chkres;
				break;
			}
			$nparlist = $_REQUEST['iparam'];
			if (!isset($nparlist) || !is_array($nparlist)) {
				$cmd_status = "108 Invalid iparam payload";
				break;
			}
			foreach ($nparlist as $npk => $npv) {
				$idx = intval($npk);
				if ($idx < 5 || $idx > 1999) { // 0..3: Header
					$cmd_status = "104 Index Error";
					break;
				}

				// Bei Bedarf neue Kanaele erzeugen, solange neuer Idx ausserhalb von existierendem:
				while ($idx > count($par)) { 
					$parLastChanNo++;
					$par[] = '@' . $parLastChanNo;
					$par[] = 0;	// ACTION als 0 vorgeben
					for ($i = 2; $i < $parChanSize; $i++) $par[] = $par[$parLastChanIdx + $i]; // Letzten Kanal duplizieren
					$parLastChanIdx+=$parChanSize;
				}
				if($parLastChanNo>89){
					$cmd_status = "103 Too many Channels"; // max. 90 Kanaele
					break;
				}
				if ($idx >= $parLastChanIdx && ($idx - $parLastChanIdx) % $parChanSize == 0) {
					$cmd_status = "105 Index Error (Index/Line $idx)"; // Kanalnr. nicht aenderbar '@xx'
					break;
				}

			}
			if (isset($cmd_status)) break;
			// Nun alles OK, Alte Werte durch neue ersetzen
			foreach ($nparlist as $npk => $npv) {
				$idx = intval($npk);
				$par[$idx] = $npv;
			}
			// Parameter vom Ende her kompaktieren. 
			while($parLastChanNo>0){
				if(intval($par[$parLastChanIdx+1])) break;	// Action is 0: Wird also nicht verwendet, kann raus
				for($i=0;$i<$parChanSize;$i++) array_pop($par);	// Kanal unbelegt, entfernen
				$parLastChanNo--;
				$parLastChanIdx-=$parChanSize;
			}

			$chkres = checkiparam($par); // Nochmal pruefen
			if($chkres != null) { 
				$cmd_status = $chkres;
				break;
			}
			
			// Auf Delta pruefen 
			if(count($opar)==count($par)){
				for($i=0;$i<count($opar);$i++){
					if($opar[$i]!=$par[$i]) break;
				}
					if($i==count($opar)){
						$cmd_status = "106 No Changes found"; // Keine Aederungen
						break;
					}
				}

			// Aus Array File erzeugen
			$par[4]=time();	// Neuen Cookie dafuer
			$nparstr = implode("\n", $par) . "\n";
			$ilen = strlen($nparstr);
			@unlink("$fpath/$mac/cmd/iparam.lxp.pmeta");
			if ($ilen > 32)	$slen = file_put_contents("$fpath/$mac/put/iparam.lxp", $nparstr);
			else $slen = -1;
			if ($ilen == $slen) {
				file_put_contents("$fpath/$mac/cmd/iparam.lxp.pmeta", "sent\t0\n");
				$wnpar = @file("$fpath/$mac/put/iparam.lxp", FILE_IGNORE_NEW_LINES); // Set NewName?
				if ($wnpar != false) {
					$snn = $pdo->prepare("UPDATE devices SET name = ? WHERE mac = ?");
					$snn->execute(array(@$par[5], $mac));
					$retResult['overview']['name']=@$par[5];
				}
				$xlog .= "(New Hardware-Parameter 'iparam.lxp':$ilen)";
					$retResult['par_pending'] = true;
				} else {
					$xlog .= "(ERROR: Write 'iparam.lxp':$slen/$ilen Bytes)";
					$cmd_status = "107 Write Parameter";
				}
				break;

		case 'iparamunpend':
			@unlink("$fpath/$mac/cmd/iparam.lxp.pmeta");
			@unlink("$fpath/$mac/put/iparam.lxp");
			$par = getcurrentiparam();
			if ($par == false) {
				$cmd_status = "101 No Parameters found for MAC:$mac";
				break;
			}
			$snn = $pdo->prepare("UPDATE devices SET name = ? WHERE mac = ?");
			$snn->execute(array(@$par[5], $mac));
			$retResult['overview']['name']=@$par[5];
			$xlog .= "(Remove pending Hardware-Parameter'iparam.lxp')";
			break;

		case 'getdata':
			if (!$has_data_table) {
				$cmd_status = "100 No Data for MAC";
				break;
			}

			// Default ist der in der Overview ermittelte Bereich, Request-Werte koennen ihn ueberschreiben.
			$req_minid = $minid;
			$req_maxid = $maxid;
			if (isset($_REQUEST['minid'])) {
				$req_minid = intval($_REQUEST['minid']);
			}
			if (isset($_REQUEST['maxid'])) {
				$req_maxid = intval($_REQUEST['maxid']);
			}
			$xlog .= "([" . $req_minid . ".." . $req_maxid . "]";

			$statement = $pdo->prepare("SELECT id, line_ts, calc_ts, dataline FROM m$mac WHERE (id >= ? AND id <= ?) ORDER BY id");
			$qres = $statement->execute(array($req_minid, $req_maxid));
			if ($qres == false) throw new Exception("getdata");
			$valarr = array();
			while ($user_row = $statement->fetch()) {
				$line_ts = $user_row['line_ts'];	// Wann eingepflegt in DB (UTC)
				$calc_ts = $user_row['calc_ts'];	// RTC des Gerates (UTC)
				$id = $user_row['id']; 				// Zeilen ID. *** Achtung: Nach ClearDevice beginnt die wieder bei 1 ***
				if ($calc_ts == null) $calc_ts = $line_ts; // Sinnvoller Default falls Geraet ohne Zeit, z.B. nach RESET
				$line = $user_row['dataline'];	// Daten oder Messages - extrem flach organisiert fuer max. Flexibilitaet
				$ltyp = "msg";
				try {
					if (strlen($line) > 11 && $line[0] == '!' && is_numeric($line[1])) {
						$line = substr($line, strpos($line, ' ') + 1);
						$ltyp = "val";
					}
				} catch (Exception $e) {
					$ltyp = "error";	// Markieren, aber durchreichen
				}
				/************************************************************
				 * HIER WERDEN DIE DATEN ERSTMAL ***QUASI ROH** eingepflegt,
				 * mit IT klaeren, welches Format GENAU gewuenscht 07.02.2023 JoWI
				 ************************************************************/
				$valarr[] = array('id' => $id, 'line_ts' => $line_ts, 'calc_ts' => $calc_ts, 'type' => $ltyp, 'line' => $line);
			}
			$retResult['get_count'] = count($valarr); // Allowed Devices
			$retResult['get_data'] = $valarr;
			$xlog .= "]: " . count($valarr) . " Lines)";

			break;

			case 'getfile':
				// Read EDT file(s) from files/ directory
				$fname = @$_REQUEST['file'];
				if (!strlen($fname)) $fname = "data.edt";
				// Security: only allow specific filenames
				if (!in_array($fname, ['data.edt', 'data.edt.bak', 'iparam.lxp', 'sys_param.lxp'])) {
					$cmd_status = "110 Invalid filename";
					break;
				}
			$bak = isset($_REQUEST['bak']) && $_REQUEST['bak'];  // Include .bak file
			$limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 0;

			$alllines = array();
			$files_read = array();

			// Read .bak file first if 'bak' requested (older data first)
			if ($bak && $fname == "data.edt") {
				$bakfile = "$fpath/$mac/files/data.edt.bak";
				if (file_exists($bakfile)) {
					$baklines = @file($bakfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
					if ($baklines !== false) {
						$alllines = array_merge($alllines, $baklines);
						$files_read[] = "data.edt.bak";
					}
				}
			}

			// Read main file
			$mainfile = "$fpath/$mac/files/$fname";
			if (file_exists($mainfile)) {
				$mainlines = @file($mainfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				if ($mainlines !== false) {
					$alllines = array_merge($alllines, $mainlines);
					$files_read[] = $fname;
				}
			}

				if (empty($files_read)) {
					$cmd_status = "111 File(s) not found";
					break;
				}

			// Apply limit (from end, newest data)
			$total_lines = count($alllines);
			if ($limit > 0 && $total_lines > $limit) {
				$alllines = array_slice($alllines, -$limit);
			}

			// Parse lines into same format as getdata
			$valarr = array();
			$id = 0;
			$unixt = 0;
			foreach ($alllines as $line) {
				$id++;
				// Decompress if needed
				if (strlen($line) && $line[0] === '$') {
					$decoded = @base64_decode(substr($line, 1));
					if ($decoded !== false) {
						$line = @gzuncompress($decoded);
						if ($line === false) $line = $decoded;
					}
				}

				$ltyp = "msg";
				$calc_ts = null;
				$outline = $line;

				if (strlen($line) && $line[0] == '!') {
					if ($line[1] != 'U') {  // Not units line, contains values
						$tmp = explode(' ', $line);
						if ($tmp[0][1] == '+') {
							$rtime = intval(substr($tmp[0], 2));
							$unixt += $rtime;
						} else {
							$unixt = intval(substr($tmp[0], 1));
						}
						if ($unixt > 1526030617 && $unixt < 0xF0000000) {
							$calc_ts = gmdate("Y-m-d H:i:s", $unixt);
							$ltyp = "val";
							$outline = substr($line, strpos($line, ' ') + 1);  // Remove timestamp
						}
					}
				}

				$valarr[] = array(
					'id' => $id,
					'line_ts' => null,
					'calc_ts' => $calc_ts,
					'type' => $ltyp,
					'line' => $outline
				);
			}

			$retResult['files'] = $files_read;
			$retResult['total_lines'] = $total_lines;
			$retResult['get_count'] = count($valarr);
			$retResult['get_data'] = $valarr;
			$xlog .= "(getfile:" . implode('+', $files_read) . ", $total_lines lines)";
			break;

		default:
			$cmd_status = "102 Unknown Cmd '$cmd'";
	} // --- cmd Main Ende ---
	$cmd_status_key = ($cmd === '') ? 'version' : $cmd;
	if ($cmd_status == null) {
		$cmd_statuses[] = array('cmd' => $cmd_status_key, 'status' => "0 OK");
		$has_cmd_success = true;
	} else {
		$cmd_statuses[] = array('cmd' => $cmd_status_key, 'status' => $cmd_status);
		$has_cmd_error = true;
		if ($first_error_status == null) $first_error_status = $cmd_status;
	}
	} // --- foreach cmds ---

	// Benchmark am Ende
	$mtrun = round((microtime(true) - $mtmain_t0) * 1000, 4);
	if (count($cmds) > 1) $retResult['cmd_status'] = $cmd_statuses;
	if ($has_cmd_error && !$has_cmd_success) $status = $first_error_status;
	else $status = "0 OK";	// Im Normalfall Status '0 OK'
	$retResult['version_tt'] = VERSION_TT;
	$retResult['status'] = $status . " ($mtrun msec)";	// plus Time

	$ares = json_encode($retResult); // assoc array always as object
	if (!strlen($ares))  throw new Exception("json_encode()");
	if ($dbg) var_export($retResult);
	else echo $ares;
} catch (Exception $e) {
	$errm = "#ERROR: '" . $e->getMessage() . "'";
	echo $errm;
	$xlog .= "($errm)";
}

if ($dbg || $has_cmd_error) add_logfile(); // Log only on debug or errors
// ***
