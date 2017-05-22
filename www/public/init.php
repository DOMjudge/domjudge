<?php
/**
 * Include required files.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

$pagename = basename($_SERVER['PHP_SELF']);

define('IS_JURY', false);
define('IS_PUBLIC', true);

require_once(LIBDIR . '/init.php');

setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/auth.php');
require_once(LIBWWWDIR . '/scoreboard.php');
require_once(LIBWWWDIR . '/forms.php');

$cdatas = getCurContests(TRUE, -1);
$cids = array_keys($cdatas);

// If the cookie has a existing contest, use it
if ( isset($_COOKIE['domjudge_cid']) && isset($cdatas[$_COOKIE['domjudge_cid']]) )  {
	$cid = $_COOKIE['domjudge_cid'];
	$cdata = $cdatas[$cid];
} elseif ( count($cids) >= 1 ) {
	// Otherwise, select the first contest
	$cid = $cids[0];
	$cdata = $cdatas[$cid];
}
