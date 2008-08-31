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

if( DEBUG & DEBUG_TIMINGS ) {
	include_once (WWWLIB_PATH."/.." . '/lib/lib.timer.php');
}

require_once(WWWLIB_PATH."/.." . '/lib/lib.error.php');
require_once(WWWLIB_PATH."/.." . '/lib/use_db_public.php');
require_once(WWWLIB_PATH."/.." . '/lib/lib.misc.php');
require_once(WWWLIB_PATH."/.." . '/lib/www/common.php');
require_once(WWWLIB_PATH."/.." . '/lib/www/print.php');
