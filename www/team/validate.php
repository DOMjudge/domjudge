<?php

$ip = $_SERVER['REMOTE_ADDR'];
$row = $DB->q('MAYBETUPLE SELECT * FROM team WHERE ipadres = %s', $ip);

if(!$row) {
	header('HTTP/1.1 403 Forbidden');
	$title = '403 Forbidden';
	include('../header.php');
	echo "<h1>403 Forbidden</h1>\n\nSorry, no access.";
	include('../footer.php');
	exit;
}

extract($row);
