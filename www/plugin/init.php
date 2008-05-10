<?php
/**
 * Include required files.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../../etc/config.php');

if ( @$_SERVER['REMOTE_USER'] == "jury" ) {
	define('IS_JURY', 1);
	require_once(SYSTEM_ROOT . '/lib/use_db_jury.php');
} else {
	define('IS_JURY', 0);
	require_once(SYSTEM_ROOT . '/lib/use_db_team.php');
}

if( DEBUG ) {
	include_once (SYSTEM_ROOT . '/lib/lib.timer.php');
}

require_once(SYSTEM_ROOT . '/lib/lib.error.php');
require_once(SYSTEM_ROOT . '/lib/lib.misc.php');

require_once(SYSTEM_ROOT . '/lib/www/common.php');
require_once(SYSTEM_ROOT . '/lib/www/print.php');

$cdata = getCurContest(TRUE);
$cid = (int)$cdata['cid'];
