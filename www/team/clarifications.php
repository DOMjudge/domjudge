<?php

/**
 * Display the clarification responses
 *
 * $Id: clarification.php 468 2004-08-29 16:24:19Z nkp0405 $
 */

require('init.php');
$refresh = '30;url=clarifications.php';
$title = 'Clarifications';
include('../header.php');
include('menu.php');

?>
<p><a href="clar_request.php">Request Clarification</a></p>

<h1>Clarifications</h1>
<p>
<?

$data = $DB->q('TABLE SELECT * FROM clar_response WHERE cid = %i 
	AND (rcpt = %s OR rcpt IS NULL) ORDER BY submittime DESC',
	getCurContest(), $login );

if(count($data) == 0 ) {
	echo "<em>No responses yet</em>\n";
} else {
	echo "<table>\n<tr><th>Time</th><th>Response</th></tr>\n";
	foreach($data as $row) {
		echo "<tr><td>".
			printtime($row['submittime'])."</td><td>".
			'<a href="'.
			addUrl("clarification.php?id=".urlencode($row['respid']),$popupTag).
			'">'.
				htmlspecialchars(str_cut($row['body'],50)).
			"</a></td></tr>\n";
	}
	echo "</table>\n";
}

?>
</p>

<h1>Requests</h1>

<p>
<?php
$res = $DB->q('SELECT q.*
	FROM  clar_request q
	WHERE q.cid = %i AND q.login = %s
	ORDER BY q.submittime DESC', getCurContest(), $login);

if( $res->count() == 0 ) {
	echo "<em>No requests done</em>\n";
} else {
	echo "<table>\n".
		"<tr><th>Time</th><th>Request</th>\n";
	while ($req = $res->next())
	{
		$req['reqid'] = (int)$req['reqid'];
		echo "<tr>".
			"<td>".printtime($req['submittime'])."</td>".
			"<td><a href=\"request.php?id=".$req['reqid']."\">".
				htmlspecialchars(str_cut($req['body'],50)).
			"</a></td>".
			"</tr>\n";
	}
	echo "</table>\n";
}
echo "</p>\n\n";

include('../footer.php');
