<?php
/**
 * Common page header.
 * Before including this, one can set $title, $refresh and $popup.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if (!defined('DOMJUDGE_VERSION')) die("DOMJUDGE_VERSION not defined.");

header('Content-Type: text/html; charset=' . DJ_CHARACTER_SET);

if ( isset($refresh) ) {
	header('Refresh: ' . $refresh);
}
echo '<?xml version="1.0" encoding="' . DJ_CHARACTER_SET . '" ?>' . "\n";

if(!isset($menu)) {
	$menu = true;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
	<!-- DOMjudge version <?=DOMJUDGE_VERSION?> -->
<title><?=$title?></title>
<link rel="stylesheet" href="<?=getBaseURI()?>style.css" type="text/css" />
<?php
if (defined('IS_JURY')) {
	echo "<link rel=\"stylesheet\" href=\"style_jury.css\" type=\"text/css\" />\n";
	if (isset($printercss)) {
		echo "<link rel=\"stylesheet\" href=\"style_printer.css\" type=\"text/css\" media=\"print\" />\n";
	}
}

/* NOTE: here a local menu.php is included
 *       both jury and team have their own menu.php
 */
if ($menu) {?>
<script type="text/javascript" src="<?=getBaseURI()?>ajax.js"></script>
</head>
<body onload="setInterval('updateClarifications()', 20000)">
<?php include("menu.php");
} else {?>
</head>
<body>
<?php }
