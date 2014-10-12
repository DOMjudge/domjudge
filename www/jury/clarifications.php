<?php
/**
 * Clarifications overview
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$title = 'Clarification Requests';

$jury_member = $username;

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/clarification.php');

echo "<h1>Clarifications</h1>\n\n";

echo "<p><a href=\"clarification.php\">Send Clarification</a></p>\n";
echo "<p><a href=\"#newrequests\">View New Clarification Requests</a></p>\n";
echo "<p><a href=\"#oldrequests\">View Old Clarification Requests</a></p>\n";
echo "<p><a href=\"#clarifications\">View General Clarifications</a></p>\n\n";

$cids = getCurContests(FALSE);

$newrequests    = $DB->q('SELECT c.*, p.shortname, t.name AS toname, f.name AS fromname
                          FROM clarification c
                          LEFT JOIN problem p USING(probid)
                          LEFT JOIN team t ON (t.teamid = c.recipient)
                          LEFT JOIN team f ON (f.teamid = c.sender)
			  WHERE c.sender IS NOT NULL AND c.cid IN (%Ai) AND c.answered = 0
			  ORDER BY submittime DESC, clarid DESC', $cids);

$oldrequests    = $DB->q('SELECT c.*, p.shortname, t.name AS toname, f.name AS fromname
                          FROM clarification c
                          LEFT JOIN problem p USING(probid)
                          LEFT JOIN team t ON (t.teamid = c.recipient)
                          LEFT JOIN team f ON (f.teamid = c.sender)
			  WHERE c.sender IS NOT NULL AND c.cid IN (%Ai) AND c.answered != 0
			  ORDER BY submittime DESC, clarid DESC', $cids);

$clarifications = $DB->q('SELECT c.*, p.shortname, t.name AS toname, f.name AS fromname
                          FROM clarification c
                          LEFT JOIN problem p USING(probid)
                          LEFT JOIN team t ON (t.teamid = c.recipient)
                          LEFT JOIN team f ON (f.teamid = c.sender)
			  WHERE c.sender IS NULL AND c.cid IN (%Ai)
                          AND ( c.respid IS NULL OR c.recipient IS NULL )
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
