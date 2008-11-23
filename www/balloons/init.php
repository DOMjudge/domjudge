<?php
/**
 * Include required files.
 *
 * $Id: init.php 2102 2008-05-31 11:14:57Z eldering $
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

require_once('../../etc/config.php');

if( DEBUG & DEBUG_TIMINGS ) {
	include_once (SYSTEM_ROOT . '/lib/lib.timer.php');
}

require_once(SYSTEM_ROOT . '/lib/lib.error.php');
require_once(SYSTEM_ROOT . '/lib/use_db_jury.php');
require_once(SYSTEM_ROOT . '/lib/lib.misc.php');

require_once(SYSTEM_ROOT . '/lib/www/common.php');
require_once(SYSTEM_ROOT . '/lib/www/print.php');

require_once(SYSTEM_ROOT . '/lib/www/validate.jury.php');
require_once(SYSTEM_ROOT . '/lib/www/common.jury.php');

$cdata = getCurContest(TRUE);
$cid = (int)$cdata['cid'];

