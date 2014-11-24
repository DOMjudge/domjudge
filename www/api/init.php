<?php
/**
 * DOMjudge REST API
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

require_once(LIBDIR . '/init.php');

setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/scoreboard.php');
require_once(LIBWWWDIR . '/auth.php');
require_once(LIBWWWDIR . '/restapi.php');

$cdatas = getCurContests(TRUE, -1);
$cids = array_keys($cdatas);

if ( ! logged_in() &&
     isset($_SERVER['PHP_AUTH_USER']) &&
     isset($_SERVER['PHP_AUTH_PW']) ) {
	do_login_native($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
	$userdata['roles'] = get_user_roles($userdata['userid']);
}
