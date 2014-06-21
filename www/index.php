<?php

/**
 * Switch a user to the right site based on whether they can be
 * authenticated as team, jury, or nothing (public).
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('configure.php');

require_once(LIBDIR . '/lib.error.php');
require_once(LIBDIR . '/lib.misc.php');
require_once(LIBDIR . '/use_db.php');
// Team login necessary for checking login credentials:
setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/auth.php');

$target = 'public/';
if ( logged_in() ) {
	if     ( checkrole('jury') )       $target = 'jury/';
	elseif ( checkrole('team',false) ) $target = 'team/';
	elseif ( checkrole('balloon') )    $target = 'jury/balloons.php';
}

header('HTTP/1.1 302 Please see this page');
header('Location: ' . $target);
