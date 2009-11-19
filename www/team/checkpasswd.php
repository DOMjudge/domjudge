<?php
/**
 * This page checks a user's credentials against the database, and
 * when successful, adds that IP to that team or starts a new PHP session.
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

$user = trim($_POST['login']);
$pass = trim($_POST['passwd']);

$title = 'Authenticate user';
$menu = false;

if ( empty($user) || empty($pass) ) {
	require(LIBWWWDIR . '/header.php');
	echo "<h1>Not Authenticated</h1>\n\n";
	echo "<p>Please supply a username and password.</p>\n\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

$ip = $_SERVER['REMOTE_ADDR'];
$hostname = gethostbyaddr($ip);
if ( $hostname == $ip ) $hostname = NULL;

$row = $DB->q('MAYBETUPLE SELECT * FROM team
               WHERE login = %s AND passwd = %s' .
              (PHP_SESSIONS ? '' : ' AND ipaddress IS NULL'),
              $user, md5($user."#".$pass));

if ( !$row ) {
	sleep(3);
	require(LIBWWWDIR . '/header.php');
	echo "<h1>Not Authenticated</h1>\n\n";
	echo "<p>Invalid username or password supplied. " .
		"Please try again or contact a staff member.</p>\n\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

if ( PHP_SESSIONS ) {
	session_start();
	$_SESSION['teamid'] = $user;
}

$cnt = $DB->q('RETURNAFFECTED UPDATE team SET ipaddress = %s, hostname = %s
	           WHERE login = %s', $ip, $hostname, $user);

if ( $cnt != 1 ) error("cannot set IP/hostname for user '$user'");

require(LIBWWWDIR . '/header.php');

echo "<h1>Authenticated</h1>\n\n<p>Successfully authenticated as team " .
	htmlspecialchars($user) . " on " . htmlspecialchars($ip) . ".</p>" .
	"<p><a href=\"./\">Continue to your team page</a>, and good luck!</p>\n\n";

require(LIBWWWDIR . '/footer.php');
