<?php
/**
 * Display the clarification responses
 *
 * $Id: clarification.php 468 2004-08-29 16:24:19Z nkp0405 $
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/clarifications.php';
$title = 'Clarifications';
require('../header.php');
require('../clarification.php');
require('menu.php');

$cid = getCurContest();

echo "<h1>Clarifications</h1>\n\n";

echo '<p><a href="' . addUrl('clarification.php',$popupTag) .
	"\">Request Clarification</a></p>\n";

echo "<p><a href=\"#clarifications\">View Clarifications</a></p>\n";
echo "<p><a href=\"#requests\">View Clarification Requests</a></p>\n\n";

$requests = $DB->q('SELECT * FROM clarification
	WHERE cid = %i AND sender = %s
	ORDER BY submittime DESC', $cid, $login);

$clarifications = $DB->q('SELECT * FROM clarification
	WHERE cid = %i AND sender IS NULL
	AND ( recipient IS NULL OR recipient = %s )
	ORDER BY submittime DESC', $cid, $login,
	(isset($_REQUEST['stamp']) ? $_REQUEST['stamp'] : 0));

echo '<h3><a name="Clarifications" id="clarifications">' .
	"Clarifications:</a></h3>\n";
if ( $clarifications->count() == 0 ) {
	echo "<p><em>No clarifications.</em></p>\n\n";
} else {
	putClarificationList($clarifications,$login);
}

echo '<h3><a name="Clarification Requests" id="requests">' .
	"Clarification Requests:</a></h3>\n";
if ( $requests->count() == 0 ) {
	echo "<p><em>No clarification requests.</em></p>\n\n";
} else {
	putClarificationList($requests,$login);
}

require('../footer.php');
