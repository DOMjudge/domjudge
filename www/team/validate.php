<?php

/**
 * This file is included to check whether this is a known team, and sets
 * the $login variable accordingly. It checks this by the IP from the
 * database, if not present it returns an error 403 (Forbidden).
 */

$ip = $_SERVER['REMOTE_ADDR'];
$row = $DB->q('MAYBETUPLE SELECT * FROM team WHERE ipaddress = %s', $ip);

// not found in database
if(!$row) {
	header('HTTP/1.1 403 Forbidden');
	$title = '403 Forbidden';
	include('../header.php');
	echo "<h1>403 Forbidden</h1>\n\n<p>Sorry, no access.</p>\n\n".
		"<hr><address>DOMjudge</address>\n";
	include('../footer.php');
	exit;
}

// set the $login, $teamname, $category variables
extract($row);
