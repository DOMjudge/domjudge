<?php
/**
 * Include required files.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

// please keep any includes synchronised with checkpasswd.php
require_once('../../etc/config.php');

if( DEBUG & DEBUG_TIMINGS ) {
	include_once (SYSTEM_ROOT . '/lib/lib.timer.php');
}

if ( ! defined('NONINTERACTIVE') ) define('NONINTERACTIVE', false);

require_once(SYSTEM_ROOT . '/lib/lib.error.php');
require_once(SYSTEM_ROOT . '/lib/lib.misc.php');
require_once(SYSTEM_ROOT . '/lib/use_db_team.php');

require_once(SYSTEM_ROOT . '/lib/www/common.php');
require_once(SYSTEM_ROOT . '/lib/www/print.php');
require_once(SYSTEM_ROOT . '/lib/www/scoreboard.php');
require_once(SYSTEM_ROOT . '/lib/www/validate.team.php');

$cdata = getCurContest(TRUE);
$cid = $cdata['cid'];
