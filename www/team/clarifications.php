<?php

/**
 * Display the clarification responses
 *
 * $Id: clarification.php 468 2004-08-29 16:24:19Z nkp0405 $
 */

require('init.php');
$title = 'Clarifications';
include('../header.php');
include('menu.php');

?>
<h1>Clarifications</h1>
<p><table>
<tr><th>Time</th><th>Reponse</th></tr>
<?

$data = $DB->q('TABLE SELECT * FROM clar_response WHERE cid = %i 
	AND (rcpt = %s OR rcpt IS NULL) ORDER BY submittime DESC',
	getCurContest(), $login );

foreach($data as $row) {
	echo "<tr><td>".
		printtime($row['submittime'])."</td><td>".
		"<a href=\"clarification.php?id=".
		urlencode($row['respid'])."\">".
		htmlspecialchars(str_cut($row['body'],50)).
		"</a></td></tr>\n";
}

?>
</table>
</p>

<p><a href="clar_request.php">Request Clarification</a></p>

<?
include('../footer.php');
