<?php

/**
 * View and submit clarification requests
 *
 * $Id$
 */

require('init.php');
include('menu.php');
$title = 'Clarification Request';

unset($send);
if (isset($_REQUEST['submit'])
  && !empty($_REQUEST['request']))
{
	$respid = $DB->q('RETURNID INSERT INTO clar_request (cid, submittime, login, body)
		VALUES (%i, now(), %s, %s)', getCurContest(), $login, $_REQUEST['request']);
	$send = true;
	$refresh = '5;url=clarifications.php';
}

include('../header.php');

?>
<h1>Clarification Request</h1>

<?
if(!isset($send))
{
?>
<form action="clar_request.php" method="post">
<table>
<tr><td><b>To:</b></td><td>Jury</td></tr>
<tr><td valign="top"><b>Request:</b></td><td><textarea name="request" cols="80" rows="5"></textarea></td></tr>
<tr><td>&nbsp;</td><td><input type="submit" name="submit" value="Send" /></td></tr>
</table>
</form>
<?
} else  {
?>
<table>
<tr><td><b>To:</b></td><td>Jury</td></tr>
<tr><td valign="top"><b>Request:</b></td><td class="output_text"><?=$_REQUEST['request']?></td></tr>
<tr><td>&nbsp;</td><td>Send!<br />You will be redirected to your team page in 5 seconds.</td></tr>
</table>
<?
}

include('../footer.php');
