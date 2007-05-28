<?php
/**
 * Make sure one's allowed to view the jury system
 *
 * $Id$
 */

$ip = $_SERVER['REMOTE_ADDR'];

/* This is disabled because we use htaccess to control access here */
if(FALSE) {
	header('HTTP/1.1 403 Forbidden');
	$title = '403 Forbidden';
	include('../header.php');
	echo "<h1>403 Forbidden</h1>\n\nSorry, no access.";
	putDOMjudgeVersion();
	include('../footer.php');
	exit;
}

/* Check whether the logged-in user is an administrator.
 * When no REMOTE_USER is available, we're not using HTTP Authentication and so
 * cannot discern between admins and non-admins.
 */
if ( !isset($_SERVER['REMOTE_USER']) || in_array($_SERVER['REMOTE_USER'], $DOMJUDGE_ADMINS) ) {
	define('IS_ADMIN', true);
} else {
	define('IS_ADMIN', false);
}

