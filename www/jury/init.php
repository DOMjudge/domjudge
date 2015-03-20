<?php
/**
 * Include required files.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

$pagename = basename($_SERVER['PHP_SELF']);

define('IS_JURY', TRUE);
define('IS_PUBLIC', false);

require_once(LIBDIR . '/init.php');

setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/forms.php');
require_once(LIBWWWDIR . '/printing.php');
require_once(LIBWWWDIR . '/auth.php');

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
	error("You do not have permission to perform that action (Missing role(s): " . implode($REQUIRED_ROLES,',') . ")");
}

require_once(LIBWWWDIR . '/common.jury.php');
if ( $_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES)
  && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0 ) {
	error("POST data exceeded php.ini's 'post_max_size' directive.");
}

$cdatas = getCurContests(TRUE, null, TRUE);
$cids = array_keys($cdatas);

// List of executable script types, used in various places:
$executable_types = array('compare' => 'compare',
                          'compile' => 'compile',
                          'run'     => 'run');

// If the cookie has a existing contest, use it
if ( isset($_COOKIE['domjudge_cid']) )  {
	if ( isset($cdatas[$_COOKIE['domjudge_cid']]) ) {
		$cid = $_COOKIE['domjudge_cid'];
		$cdata = $cdatas[$cid];
	}
} elseif ( count($cids) >= 1 ) {
	// Otherwise, select the first contest
	$cid = $cids[0];
	$cdata = $cdatas[$cid];
}

// Data to be sent as AJAX updates:
$updates = array(
	'clarifications' =>
	( empty($cids) ? array() :
	  $DB->q('TABLE SELECT clarid, submittime, sender, recipient, probid, body
	          FROM clarification
	          WHERE sender IS NOT NULL AND cid IN (%Ai) AND answered = 0', $cids) ),
	'judgehosts' =>
	$DB->q('TABLE SELECT hostname, polltime
	        FROM judgehost
	        WHERE active = 1 AND unix_timestamp()-polltime >= %i',
	       dbconfig_get('judgehost_critical',120)),
	'rejudgings' =>
	$DB->q('TABLE SELECT rejudgingid
	        FROM rejudging
	        WHERE endtime IS NULL'),
);
