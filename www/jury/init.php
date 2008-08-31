<?php
/**
 * Include required files.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

// Sanity check whether webserver basic authentication (e.g in
// apache.conf) is configured correctly
if (empty($_SERVER['REMOTE_USER']) || $_SERVER['AUTH_TYPE'] != "Basic") {
	die("Authentication not enabled, check webserver config");
}

define('IS_JURY', 1);

require_once('../configure.php');
require_once(WWWETC_PATH.'/domserver-config.php');

if( DEBUG & DEBUG_TIMINGS ) {
	include_once (WWWLIB_PATH."/.." . '/lib/lib.timer.php');
}

require_once(WWWLIB_PATH."/.." . '/lib/lib.error.php');
require_once(WWWLIB_PATH."/.." . '/lib/use_db.php');
setup_database_connection('jury');
require_once(WWWLIB_PATH."/.." . '/lib/lib.misc.php');

require_once(WWWLIB_PATH."/.." . '/lib/www/validate.jury.php');
require_once(WWWLIB_PATH."/.." . '/lib/www/common.jury.php');

$cdata = getCurContest(TRUE);
$cid = (int)$cdata['cid'];

