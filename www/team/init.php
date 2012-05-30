<?php
/**
 * Include required files.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

define('IS_JURY', false);

if ( ! defined('NONINTERACTIVE') ) define('NONINTERACTIVE', false);

if( DEBUG & DEBUG_TIMINGS ) {
	require_once(LIBDIR . '/lib.timer.php');
}

require_once(LIBDIR . '/lib.error.php');
require_once(LIBDIR . '/lib.misc.php');
require_once(LIBDIR . '/lib.dbconfig.php');
require_once(LIBDIR . '/use_db.php');

parseLangExts();

set_exception_handler('exception_handler');
setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/clarification.php');
require_once(LIBWWWDIR . '/scoreboard.php');
require_once(LIBWWWDIR . '/auth.team.php');

// The functions do_login and show_loginpage, if called, do not return.
if ( @$_POST['cmd']=='login' ) do_login();

if ( !logged_in() ) show_loginpage();

if ( $teamdata['enabled'] != 1 ) {
	error("Team is not enabled.");
}

$cdata = getCurContest(TRUE);
$cid = $cdata['cid'];

$nunread_clars = $DB->q('VALUE SELECT COUNT(*) FROM team_unread
                         LEFT JOIN clarification ON(mesgid=clarid)
                         WHERE type="clarification" AND teamid = %s
                         AND cid = %i', $login, $cid);
