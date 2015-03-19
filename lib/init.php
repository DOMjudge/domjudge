<?php
if ( !defined('LIBDIR') ) die ("LIBDIR not defined.");

if( DEBUG & DEBUG_TIMINGS ) {
	require_once(LIBDIR . '/lib.timer.php');
}

require_once(LIBDIR . '/lib.error.php');
require_once(LIBDIR . '/lib.misc.php');
require_once(LIBDIR . '/lib.dbconfig.php');
require_once(LIBDIR . '/use_db.php');

// Initialize default timezone to system default. PHP generates
// E_NOTICE warning messages otherwise.
@date_default_timezone_set(@date_default_timezone_get());

// Set for using mb_* functions:
mb_internal_encoding(DJ_CHARACTER_SET);
