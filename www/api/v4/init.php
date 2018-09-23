<?php
/**
 * DOMjudge REST API
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../../configure.php');

global $apiFromInternal;
if (!isset($apiFromInternal) || !$apiFromInternal) {
    define('IS_JURY', false);
    define('IS_PUBLIC', true);

    require_once(LIBDIR . '/init.php');

    setup_database_connection();
}

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/scoreboard.php');
require_once(LIBWWWDIR . '/auth.php');
require_once(LIBWWWDIR . '/restapi.php');

logged_in();

// For jury roles, also get the non-public contests. Otherwise, also get the contests the user belongs to
if (checkrole('jury')) {
    $teamid = null;
} elseif (isset($userdata['teamid'])) {
    $teamid = $userdata['teamid'];
} else {
    $teamid = -1;
}
$cdatas = getCurContests(true, $teamid);
$cids = array_keys($cdatas);
