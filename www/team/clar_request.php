<?php

/**
 * View and submit clarification requests
 *
 * $Id$
 */

require('init.php');

if (isset($_REQUEST['submit'])
  && !empty($_REQUEST['request']))
{
	$respid = $DB->q('RETURNID INSERT INTO clar_request (cid, submittime, login, body)
		VALUES (%i, now(), %s, %s)', getCurContest(), $login, $_REQUEST['request']);

	// after input, redirect to the appropriate requestpage
	header('Location: '
		.addUrl( getBaseURI() . 'team/request.php?id='.urlencode($respid), $popupTag));
	exit;
}
$title = 'Clarification Request';
include('../header.php');
include('menu.php');

?>
<h1>Clarification Request</h1>

<form action="clar_request.php" method="post">
<table>
<tr><td><b>To:</b></td><td>Jury</td></tr>
<tr><td valign="top"><b>Request:</b></td><td><textarea name="request" cols="80" rows="8"></textarea></td></tr>
<tr><td>&nbsp;</td><td><input type="submit" name="submit" value="Send" /></td></tr>
</table>
</form>
<?

include('../footer.php');
