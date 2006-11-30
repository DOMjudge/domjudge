<?php
/**
 * Include required files.
 *
 * $Id$
 */

// please keep any includes synchronised with checkpasswd.php
require_once('../../etc/config.php');

if( DEBUG ) {
	include_once (SYSTEM_ROOT . '/lib/lib.timer.php');
}

require_once(SYSTEM_ROOT . '/lib/lib.error.php');
require_once(SYSTEM_ROOT . '/lib/lib.misc.php');
require_once(SYSTEM_ROOT . '/lib/use_db_team.php');

require_once('../common.php');
require_once('../print.php');
require_once('../scoreboard.php');
require_once('validate.php');
