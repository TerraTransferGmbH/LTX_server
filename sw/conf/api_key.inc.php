<?php
	// api_key.inc.php - configuration of the LTX server (minimal file-mode version)
	// (C) TerraTransfer GmbH
	// Edit the values marked *** before first use. The web server needs read access only.

	// Data directory (one sub directory per logger is created automatically).
	// Default: "../data_secret" = directory 'data_secret' next to 'sw/' and 'legacy/'.
	// Recommended: an absolute path OUTSIDE the web root, writable by the web server user,
	// e.g. "/var/lib/ltx-data" (Linux) or "C:/ltx-data" (Windows).
	define ("S_DATA", "../data_secret");

	// *** Password of the web interface ('legacy/'). Change it! ***
	define ("L_KEY", "CHANGE_ME");

	// *** Device key: every logger sends this key with each contact (value agreed with TerraTransfer). ***
	define ("D_API_KEY", "CHANGE_ME");

	// Initial content of 'quota_days.dat' for new loggers (days / lines). Informational only in this version.
	define ("DB_QUOTA", "3650\n1000000");

	// Maximum upload size per contact, depending on the mobile network (bytes)
	define ("MAXM_2GM", 20000);	// 2G / LTE-M
	define ("MAXM_NB", 5000);	// LTE-NB (slow)
	define ("MXGET_MEM", 100000);	// Maximum multiblock size (>= MAXM_2GM)
?>
