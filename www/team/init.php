<?php declare(strict_types=1);
/**
 * Include required files.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

$pagename = basename($_SERVER['PHP_SELF']);

define('IS_JURY', false);
define('IS_PUBLIC', false);

require_once(LIBDIR . '/init.php');

setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/clarification.php');
require_once(LIBWWWDIR . '/scoreboard.php');
require_once(LIBWWWDIR . '/printing.php');
require_once(LIBWWWDIR . '/auth.php');
require_once(LIBWWWDIR . '/forms.php');

logged_in();
if (!checkrole('team')) {
    error("You do not have permission to perform that action (Missing role: 'team')");
}
if (empty($teamdata)) {
    error("You do not have a team associated with your account.  Please contact a staff member.");
}

if ($teamdata['enabled'] != 1) {
    error("Team is not enabled.");
}

$cdatas = getCurContests(true, $teamdata['teamid']);
$cids = array_keys($cdatas);

// If the cookie has a existing contest, use it
if (isset($_COOKIE['domjudge_cid']) && isset($cdatas[$_COOKIE['domjudge_cid']])) {
    $cid = (int)$_COOKIE['domjudge_cid'];
    $cdata = $cdatas[$cid];
} elseif (count($cids) >= 1) {
    // Otherwise, select the first contest
    $cid = $cids[0];
    $cdata = $cdatas[$cid];
}
