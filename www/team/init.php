<?php
/**
 * Include required files.
 *
 * $Id$
 */

require_once('../../lib/lib.error.php');

/** If config not found, exit with an error */
if( ! @include_once('../../etc/config.php') ) {
	error('Failed to include etc/config.php! '.
		'Check if you configured DOMjudge through editing "etc/global.cfg" '.
		'and then run \'make\'.');
}

require_once('../../lib/lib.misc.php');
require_once('../../lib/use_db_team.php');
require_once('../common.php');
require_once('validate.php');
require_once('../print.php');
require_once('popupcheck.php');
