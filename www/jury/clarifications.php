<?php
/**
 * Clarifications overview
 *
 * $Id$
 */

require('init.php');
$refresh = '15;url='.getBaseURI().'jury/clarifications.php';
$title = 'Clarification Requests';
require('../header.php');
require('../clarification.php');
require('menu.php');

$cid = getCurContest();

echo "<h1>Clarifications</h1>\n\n";

echo "<p><a href=\"clarification.php\">Send Clarification</a></p>\n";
echo "<p><a href=\"#newrequests\">View New Clarification Requests</a></p>\n";
echo "<p><a href=\"#oldrequests\">View Old Clarification Requests</a></p>\n";
echo "<p><a href=\"#clarifications\">View General Clarifications</a></p>\n\n";

$newrequests = $DB->q('SELECT * FROM clarification
	WHERE sender IS NOT NULL AND cid = %i AND answered = 0
	ORDER BY submittime DESC', $cid);

$oldrequests = $DB->q('SELECT * FROM clarification
	WHERE sender IS NOT NULL AND cid = %i AND answered != 0
	ORDER BY submittime DESC', $cid);

$clarifications = $DB->q('SELECT * FROM clarification
	WHERE sender IS NULL AND cid = %i
	AND ( respid IS NULL OR recipient IS NULL )
	ORDER BY submittime DESC', $cid);

echo '<h3><a name="newrequests"></a>' .
	"New Requests:</h3>\n";
if ( $newrequests->count() == 0 ) {
	echo "<p><em>No new clarification requests.</em></p>\n\n";
} else {
	putClarificationList($newrequests,NULL,TRUE);
}

echo '<h3><a name="oldrequests"></a>' .
	"Old Requests:</h3>\n";
if ( $oldrequests->count() == 0 ) {
	echo "<p><em>No old clarification requests.</em></p>\n\n";
} else {
	putClarificationList($oldrequests,NULL,TRUE);
}

echo '<h3><a name="clarifications"></a>' .
	"General Clarifications:</h3>\n";
if ( $clarifications->count() == 0 ) {
	echo "<p><em>No general clarifications.</em></p>\n\n";
} else {
	putClarificationList($clarifications,NULL,TRUE);
}

require('../footer.php');
