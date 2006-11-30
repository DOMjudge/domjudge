<?php
/**
 * Display the clarification responses
 *
 * $Id$
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/clarifications.php';
$title = 'Clarifications';
require('../header.php');
require('../clarification.php');

$cid = getCurContest();

// Put overview of team submissions (like scoreboard)
echo "<div id=\"teamscoresummary\">\n";
putTeamRow($login);
echo "</div>\n";

echo "<h1>Clarifications team " . htmlentities($name) ."</h1>\n\n";

echo "<p><a href=\"clarification.php\">Request Clarification</a></p>\n";

echo "<p><a href=\"#clarifications\">View Clarifications</a></p>\n";
echo "<p><a href=\"#requests\">View Clarification Requests</a></p>\n\n";

$requests = $DB->q('SELECT * FROM clarification
	WHERE cid = %i AND sender = %s
	ORDER BY submittime DESC', $cid, $login);

/*
SELECT c.*, e.read
FROM clarification c
	LEFT JOIN event e
		ON ( c.clarid = e.clarid AND e.team = "escapade" )
WHERE c.cid = 2
	AND c.sender IS NULL
	AND ( c.recipient IS NULL OR c.recipient = "escapade" )
ORDER BY c.submittime DESC
*/

$clarifications = $DB->q(
	'SELECT c.*, e.`type` AS `unread` '
	.'FROM clarification AS c '
	.'LEFT JOIN event AS e '
	.		'ON ( c.`clarid` = e.`evid` AND e.`type` AND e.`team` = %s ) '
	.'WHERE c.`cid` = %i '
	.'  AND c.`sender` IS NULL'
	.'  AND ( c.`recipient` IS NULL OR c.`recipient` = %s )'
	.'ORDER BY c.`submittime` DESC'
	, $login, $cid, $login
	);

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

require('../footer.php');
