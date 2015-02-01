<?php
/**
 * Clarifications overview
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$contestfiltertypes = array('all', 'selected');
$contest = 'all';

// Restore most recent contest view setting from cookie (overridden by explicit selection)
if ( isset($_COOKIE['domjudge_clarifcontest']) && in_array($_COOKIE['domjudge_clarifcontest'], $contestfiltertypes) ) {
	$contest = $_COOKIE['domjudge_clarifcontest'];
}

if ( isset($_REQUEST['contest']) ) {
	if ( in_array($_REQUEST['contest'], $contestfiltertypes) ) {
		$contest = $_REQUEST['contest'];
	}
}

require('init.php');

$title = 'Clarification Requests';

// Set cookie of contest view type, expiry defaults to end of session.
setcookie('domjudge_clarifcontest', $contest);

$jury_member = $username;

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/clarification.php');

echo "<h1>Clarifications</h1>\n\n";

if ( empty($cids) ) {
	warning('No active contest(s)');
	require(LIBWWWDIR . '/footer.php');
	exit;
} elseif ( count($cids) > 1 ) {
	echo addForm($pagename, 'get') . "<p>Show contests:\n";
	echo addSubmit('all', 'contest', null, ($contest != 'all'));
	echo addSubmit('selected', 'contest', null, ($contest != 'selected'));
	echo " ('selected' contest can be chosen using dropdown in upper right" .
	     "corner)</p>\n" . addEndForm();
}

if ( $contest == 'selected' ) {
	$cids = array($cid);
}

echo "<p><a href=\"clarification.php\">Send Clarification</a></p>\n";
echo "<p><a href=\"#newrequests\">View New Clarification Requests</a></p>\n";
echo "<p><a href=\"#oldrequests\">View Old Clarification Requests</a></p>\n";
echo "<p><a href=\"#clarifications\">View General Clarifications</a></p>\n\n";

$sqlbody = 'SELECT c.*, cp.shortname, t.name AS toname, f.name AS fromname,
                   co.shortname AS contestshortname
            FROM clarification c
            LEFT JOIN problem p USING(probid)
            LEFT JOIN contestproblem cp USING (probid, cid)
            LEFT JOIN team t ON (t.teamid = c.recipient)
            LEFT JOIN team f ON (f.teamid = c.sender)
            LEFT JOIN contest co USING (cid)
            WHERE c.cid IN (%Ai) ';

$newrequests    = $DB->q($sqlbody .
                         'AND c.sender IS NOT NULL AND c.answered = 0
                          ORDER BY submittime DESC, clarid DESC', $cids);

$oldrequests    = $DB->q($sqlbody .
                         'AND c.sender IS NOT NULL AND c.answered != 0
                          ORDER BY submittime DESC, clarid DESC', $cids);

$clarifications = $DB->q($sqlbody .
                         'AND c.sender IS NULL AND ( c.respid IS NULL OR c.recipient IS NULL )
                          ORDER BY submittime DESC, clarid DESC', $cids);

echo '<h3><a name="newrequests"></a>' .
	"New Requests:</h3>\n";
if ( $newrequests->count() == 0 ) {
	echo "<p class=\"nodata\">No new clarification requests.</p>\n\n";
} else {
	putClarificationList($newrequests,NULL);
}

echo '<h3><a name="oldrequests"></a>' .
	"Old Requests:</h3>\n";
if ( $oldrequests->count() == 0 ) {
	echo "<p class=\"nodata\">No old clarification requests.</p>\n\n";
} else {
	putClarificationList($oldrequests,NULL);
}

echo '<h3><a name="clarifications"></a>' .
	"General Clarifications:</h3>\n";
if ( $clarifications->count() == 0 ) {
	echo "<p class=\"nodata\">No general clarifications.</p>\n\n";
} else {
	putClarificationList($clarifications,NULL);
}

require(LIBWWWDIR . '/footer.php');
