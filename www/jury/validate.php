<?php
/**
 * Make sure one's allowed to view the jury system
 *
 * $Id$
 */

$ip = $_SERVER['REMOTE_ADDR'];

/* this needs some validation */
if($ip != '131.211.224.224') {
	header('HTTP/1.1 403 Forbidden');
	$title = '403 Forbidden';
	include('../header.php');
	echo "<h1>403 Forbidden</h1>\n\nSorry, no access.";
	include('../footer.php');
	exit;
}

