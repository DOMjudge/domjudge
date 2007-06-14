<?php

/**
 * This page checks a user's credentials against the database, and when successful,
 * adds that IP to that team.
 *
 * $Id$
 */

require_once('../../etc/config.php');

if( DEBUG ) {
	include_once (SYSTEM_ROOT . '/lib/lib.timer.php');
}

require_once(SYSTEM_ROOT . '/lib/lib.error.php');
require_once(SYSTEM_ROOT . '/lib/lib.misc.php');
require_once(SYSTEM_ROOT . '/lib/use_db_team.php');

$ip = $_SERVER['REMOTE_ADDR'];
$row = $DB->q('MAYBETUPLE SELECT * FROM team WHERE ipaddress = %s', $ip);

$user = trim($_POST['login']);
$pass = trim($_POST['passwd']);

$title = 'Authenticate user';
$menu = false;
include('../header.php');

if ( empty($user) || empty($pass) ) {
	echo "<h1>Not Authenticated</h1>\n\n";
	echo "<p>Please supply a username and password.</p>\n\n";
	include('../footer.php');
	exit;
}

$cnt = $DB->q('VALUE SELECT COUNT(*) FROM team
               WHERE login = %s AND passwd = %s AND ipaddress IS NULL',
              $user, md5($pass));

if ( $cnt == 1 ) {
	$DB->q('UPDATE team SET ipaddress = %s WHERE login = %s', $ip, $user);
	echo "<h1>Authenticated</h1>\n\n<p>Successfully authenticated as team " .
		htmlspecialchars($user) . " on " . htmlspecialchars($ip) . ".</p>" .
		"<p><a href=\"./\">Continue to your team page</a>, and good luck!</p>\n\n";
} else {
	sleep(3);
	echo "<h1>Not Authenticated</h1>\n\n";
	echo "<p>Invalid username or password supplied. " .
		"Please try again or contact a staff member.</p>\n\n";
}

include('../footer.php');
