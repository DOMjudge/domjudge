<?php
/**
 * Include required files.
 *
 * $Id$
 */

require_once('../../etc/config.php');

if( DEBUG ) {
	include_once (SYSTEM_ROOT . '/lib/lib.timer.php');
}

require_once(SYSTEM_ROOT . '/lib/lib.error.php');
require_once(SYSTEM_ROOT . '/lib/use_db_jury.php');
require_once(SYSTEM_ROOT . '/lib/lib.misc.php');

require_once('../common.php');
require_once('../print.php');
require_once('validate.php');
require_once('common.php');
