<?php

/**
 * Display a clarification response
 *
 * $Id$
 */

require('init.php');
$title = 'Clarification Response';
include('../header.php');
include('menu.php');

$respid = (int)$_GET['id'];

// select also on teamid so we can only select our own submissions
$row = $DB->q('MAYBETUPLE SELECT *
	FROM clar_response
	WHERE cid = %i AND (rcpt = %s OR rcpt IS NULL) AND respid = %i',
	getCurContest(), $login, $respid);

if(!$row) {
	echo "Clarification Response not found for this team.\n";
	include('../footer.php');
	exit;
}
?>
<h1>Clarification Response</h1>

<p>
<table>
<tr><td><b>To:</b></td><td><?=htmlentities(empty($row['rcpt']) ? 'All' : $row['rcpt'])?></td></tr>
<tr><td><b>Time:</b></td><td><?=printtime($row['submittime'])?></td></tr>
<tr><td valign="top"><b>Response:</b></td><td class="output_text"><?=nl2br(htmlspecialchars($row['body']))?></td></tr>
</table>
</p>


<?php
include('../footer.php');
