<?php

/**
 * Switch a user to the right site based on IP (from database)
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('configure.php');

require_once(LIBDIR . '/lib.error.php');
require_once(LIBDIR . '/use_db.php');
setup_database_connection('public');

require_once(LIBWWWDIR . '/common.php');

$ip = $_SERVER['REMOTE_ADDR'];
$res = $DB->q('SELECT authtoken FROM team WHERE authtoken = %s', $ip);
if( $res->count() > 0 ) {
	$target = 'team/';
} else {
	$target = 'public/';
}

header('HTTP/1.1 302 Please see this page');
header('Location: ' . $target);
