<?php
/**
 * This page checks a user's credentials against the database, and when successful,
 * adds that IP to that team.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

// please keep any includes synchronised with init.php
require_once('../configure.php');

define('IS_JURY', FALSE);

if( DEBUG & DEBUG_TIMINGS ) {
	require_once (LIBDIR . '/lib.timer.php');
}

require_once(LIBDIR . '/lib.error.php');
require_once(LIBDIR . '/lib.misc.php');
require_once(LIBDIR . '/use_db.php');
require_once(LIBWWWDIR . '/common.php');

setup_database_connection('team');

$ip = $_SERVER['REMOTE_ADDR'];
$row = $DB->q('MAYBETUPLE SELECT * FROM team WHERE ipaddress = %s', $ip);

$user = trim($_POST['login']);
$pass = trim($_POST['passwd']);

$title = 'Authenticate user';
$menu = false;
require(LIBDIR . '/www/header.php');

if ( empty($user) || empty($pass) ) {
	echo "<h1>Not Authenticated</h1>\n\n";
	echo "<p>Please supply a username and password.</p>\n\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

$hostname = gethostbyaddr($ip);
if ( $hostname == $ip ) $hostname = NULL;

$cnt = $DB->q('RETURNAFFECTED UPDATE team SET ipaddress = %s, hostname = %s
               WHERE login = %s AND passwd = %s AND ipaddress IS NULL',
              $ip, $hostname, $user, md5($user."#".$pass));

if ( $cnt == 1 ) {
	echo "<h1>Authenticated</h1>\n\n<p>Successfully authenticated as team " .
		htmlspecialchars($user) . " on " . htmlspecialchars($ip) . ".</p>" .
		"<p><a href=\"./\">Continue to your team page</a>, and good luck!</p>\n\n";
} else if ( $cnt > 1 ) {
	error("multiple database entries that match with team '$user'");
} else {
	sleep(3);
	echo "<h1>Not Authenticated</h1>\n\n";
	echo "<p>Invalid username or password supplied. " .
		"Please try again or contact a staff member.</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
