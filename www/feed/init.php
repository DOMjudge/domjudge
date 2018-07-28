<?php
/**
 * Include required files.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

/* For plugins to have jury access rights to the DB, they should
 * successfully authenticate as user 'jury'.
 */
require_once(LIBDIR . '/init.php');
require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/auth.php');

setup_database_connection();
logged_in();

if (!checkrole('full_event_reader')) {
    error("User role full_event_reader required.");
}

define('IS_JURY', true);
define('IS_PUBLIC', false);


$cdatas = getCurContests(true);
$cids = array_keys($cdatas);
