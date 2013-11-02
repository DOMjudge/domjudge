<?php
/**
 * Include required files.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

define('IS_JURY', TRUE);
define('IS_PUBLIC', false);

require_once(LIBDIR . '/init.php');

setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/forms.php');
require_once(LIBWWWDIR . '/printing.php');
require_once(LIBWWWDIR . '/auth.php');

if ( ! defined('NONINTERACTIVE') ) define('NONINTERACTIVE', false);

// The functions do_login and show_loginpage, if called, do not return.
if ( @$_POST['cmd']=='login' ) do_login();
if ( !logged_in() ) show_loginpage();

if ( checkrole('admin') ) {
	define('IS_ADMIN', true);
} else {
	define('IS_ADMIN', false);
}

if ( !isset($REQUIRED_ROLES) ) $REQUIRED_ROLES = array('jury');
$allowed = false;
foreach ($REQUIRED_ROLES as $role) {
	if ( checkrole($role) ) {
		$allowed = true;
	}
}
if (!$allowed) {
	error("You do not have permission to perform that action(Missing role(s): " . implode($REQUIRED_ROLES,',') . ")");
}

require_once(LIBWWWDIR . '/common.jury.php');

$cdata = getCurContest(TRUE);
$cid = (int)$cdata['cid'];

$nunread_clars = $DB->q('VALUE SELECT COUNT(*) FROM clarification
                         WHERE sender IS NOT NULL AND cid = %i
                         AND answered = 0', $cid);
