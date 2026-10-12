<?php
	// index.php - redirect to the web interface (LTX server, minimal version)
	// (C) TerraTransfer GmbH
	$uri = $_SERVER['PHP_SELF'] ?? '/index.php';
	$base = substr($uri, 0, strrpos($uri, '/'));
	header("Location: $base/legacy/index.php");
?>
<!DOCTYPE HTML>
<html>
 <head><title>LTX server</title><meta name="robots" content="noindex,nofollow"></head>
 <body><?php echo "<a href='$base/legacy/index.php'>Web interface</a>"; ?></body>
</html>
