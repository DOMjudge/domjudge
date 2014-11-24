<?php
/**
 * View the submissionqueue
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$viewtypes = array(0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'all', 4 => 'externaldiff');

$contestfiltertypes = array('all', 'selected');

$view = 0;
$contest = 'all';

// Restore most recent view from cookie (overridden by explicit selection)
if ( isset($_COOKIE['domjudge_submissionview']) && isset($viewtypes[$_COOKIE['domjudge_submissionview']]) ) {
	$view = $_COOKIE['domjudge_submissionview'];
}

// Restore most recent contest view setting from cookie (overridden by explicit selection)
if ( isset($_COOKIE['domjudge_submissioncontest']) && in_array($_COOKIE['domjudge_submissioncontest'], $contestfiltertypes) ) {
	$contest = $_COOKIE['domjudge_submissioncontest'];
}

if ( isset($_REQUEST['view']) ) {
	// did someone press any of the four view buttons?
	foreach ($viewtypes as $i => $name) {
		if ( isset($_REQUEST['view'][$i]) ) $view = $i;
	}
}

if ( isset($_REQUEST['contest']) ) {
	if ( in_array($_REQUEST['contest'], $contestfiltertypes) ) {
		$contest = $_REQUEST['contest'];
	}
}

require('init.php');
$refresh = '15;url=submissions.php?' .
	   urlencode('view[' . $view . ']') . '=' . urlencode($viewtypes[$view]) .
	   '&contest=' . urlencode($contest);
$title = 'Submissions';

// Set cookie of submission view type, expiry defaults to end of session.
setcookie('domjudge_submissionview', $view);

// Set cookie of contest view type, expiry defaults to end of session.
setcookie('domjudge_submissioncontest', $contest);

$jury_member = $username;

require(LIBWWWDIR . '/header.php');

echo "<h1>$title</h1>\n\n";

$restrictions = array();
if ( $viewtypes[$view] == 'unverified' ) $restrictions['verified'] = 0;
if ( $viewtypes[$view] == 'unjudged' ) $restrictions['judged'] = 0;
if ( $viewtypes[$view] == 'externaldiff' ) $restrictions['externaldiff'] = 1;

echo addForm($pagename, 'get') . "<p>Show submissions:\n";
for($i=0; $i<count($viewtypes); ++$i) {
	echo addSubmit($viewtypes[$i], 'view['.$i.']', null, ($view != $i));
}
echo "</p>\n" . addEndForm();

if ( count($cids) > 1 ) {
	echo addForm($pagename, 'get') . "<p>Show contests:\n";
	echo addSubmit('all', 'contest', null, ($contest != 'all'));
	echo addSubmit('selected', 'contest', null, ($contest != 'selected'));
	echo " ('selected' contest can be chosen using dropdown in upper right" .
	     "corner)</p>\n" . addEndForm();
}

if ( $contest == 'selected' ) {
	$cdatas = array($cid => $cdata);
}

putSubmissions($cdatas, $restrictions, ($viewtypes[$view] == 'newest' ? 50 : 0));

require(LIBWWWDIR . '/footer.php');
