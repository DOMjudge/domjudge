<?php
/**
 * Include required files.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

define('IS_JURY', false);

if( DEBUG & DEBUG_TIMINGS ) {
	require_once(LIBDIR . '/lib.timer.php');
}

require_once(LIBDIR . '/lib.error.php');
if ( !defined('WEBBASEURI') || WEBBASEURI == "" || stristr(WEBBASEURI, 'example.com') !== FALSE ) {
	error('WEBBASEURI not configured. ' .
		'Please set the WEBBASEURI in ' . htmlspecialchars('domserver-config.php') .
		' to the full URI to your DOMjudge installation.');
}
require_once(LIBDIR . '/lib.misc.php');
require_once(LIBDIR . '/lib.dbconfig.php');
require_once(LIBDIR . '/use_db.php');

setup_database_connection('public');

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
