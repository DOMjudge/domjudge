<?php
/**
 * Display the clarification responses
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/clarifications.php';
$ajaxtitle = 'Clarifications';
$title = $ajaxtitle.($nunread_clars ? ' ('.$nunread_clars.' new)' : '');
require(LIBWWWDIR . '/header.php');

// Put overview of team submissions (like scoreboard)
echo "<div id=\"teamscoresummary\">\n";
putTeamRow($cdata, $login);
echo "</div>\n";

echo "<h1>Clarifications team " . htmlspecialchars($name) ."</h1>\n\n";

echo "<p><a href=\"clarification.php\">Request Clarification</a></p>\n";

echo "<p><a href=\"#clarifications\">View Clarifications</a></p>\n";
echo "<p><a href=\"#requests\">View Clarification Requests</a></p>\n\n";

$requests = $DB->q('SELECT * FROM clarification
                    WHERE cid = %i AND sender = %s
                    ORDER BY submittime DESC, clarid DESC', $cid, $login);

$clarifications = $DB->q('SELECT c.*, u.type AS unread FROM clarification c
                          LEFT JOIN team_unread u ON
                          (c.clarid=u.mesgid AND u.type="clarification" AND u.teamid = %s)
                          WHERE c.cid = %i AND c.sender IS NULL
                          AND ( c.recipient IS NULL OR c.recipient = %s )
                          ORDER BY c.submittime DESC, c.clarid DESC',
                          $login, $cid, $login);

echo '<h3><a name="clarifications"></a>' .
	"Clarifications:</h3>\n";
if ( $clarifications->count() == 0 ) {
	echo "<p><em>No clarifications.</em></p>\n\n";
} else {
	putClarificationList($clarifications,$login);
}

echo '<h3><a name="requests"></a>' .
	"Clarification Requests:</h3>\n";
if ( $requests->count() == 0 ) {
	echo "<p><em>No clarification requests.</em></p>\n\n";
} else {
	putClarificationList($requests,$login);
}

require(LIBWWWDIR . '/footer.php');
