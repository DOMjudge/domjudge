<?php
/**
 * This page calls the logout function.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

define('IS_JURY', false);
define('IS_PUBLIC', false);

require_once(LIBDIR . '/init.php');
// Team login necessary for checking login credentials:
setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/auth.php');

logged_in(); // To fill information if the user is logged in.
do_logout();
