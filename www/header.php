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
<link rel="stylesheet" href="style.css" type="text/css" />
<?php if (isset($sourcecss)) {  ?>
<link rel="stylesheet" href="style_source.css" type="text/css" />
<?php }
if ($menu) {?>
<script type="text/javascript" src="ajax.js"></script>
</head>
<body onload="setInterval('updateClarifications()', 20000)">
<?php include("menu.php");
} else {?>
</head>
<body>
<?php }
