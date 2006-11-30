<?php
/**
 * Divide the page in two frames.
 *
 * $Id: $
 */

require('init.php');

echo '<?xml version="1.0" encoding="iso-8859-1" ?>' . "\n";

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN"
	"http://www.w3.org/TR/html4/frameset.dtd">
<html lang="en">
<head>
	<!-- DOMjudge version <?= DOMJUDGE_VERSION ?> -->
<title>DOMjudge v<?=DOMJUDGE_VERSION?></title>
<link rel="stylesheet" href="style.css" type="text/css" />
</head>

<frameset rows="35, *" border="0px">
	<frame src="menu.php" name="menu">
	<frame src="submissions.php" name="content">
</frameset>

</html>
