<?php
/**
 * Make sure one's allowed to view the jury system
 *
 * $Id$
 */

$ip = $_SERVER['REMOTE_ADDR'];

/* FIXME: this needs some validation, how? */
if(FALSE) {
	header('HTTP/1.1 403 Forbidden');
	$title = '403 Forbidden';
	include('../header.php');
	echo "<h1>403 Forbidden</h1>\n\nSorry, no access.";
	putDOMjudgeVersion();
	include('../footer.php');
	exit;
}

