<?php
/**
 * Clarification Request Management
 *
 * $Id: teams.php 303 2004-06-14 09:55:36Z nkp0405 $
 */

require('init.php');
$title = 'Clarification Request';
require('../header.php');
require('menu.php');

$id = (int)$_REQUEST['id'];
if(!$id)	error ("Missing clarification id");

/** insert a new response */
if (isset($_REQUEST['submit'])
  && !empty($_REQUEST['response']))
{
	if(empty($_REQUEST['sendto'])) {
		$respid = $DB->q('RETURNID INSERT INTO clar_response (reqid, cid, submittime, rcpt, body)
			VALUES (%i, %i, now(), NULL, %s)',
			$id, getCurContest(), $_REQUEST['response']);
	} else {
		$respid = $DB->q('RETURNID INSERT INTO clar_response (reqid, cid, submittime, rcpt, body)
			VALUES (%i, %i, now(), %s, %s)',
			$id, getCurContest(), $_REQUEST['sendto'], $_REQUEST['response']);
	}
	/** redirect back to the original request */
	header('Location: request.php?id='. urlencode($id));
	exit;
}


echo "<h1>Clarification Request q$id</h1>\n\n";

$reqdata = putRequest($id);

$list = $DB->q("SELECT r.respid
	FROM clar_response r
	WHERE r.reqid = $id
	ORDER BY r.submittime DESC");

while ( $row = $list->next())
{
	echo "<h3>Clarification Response r".$row['respid']."</h3>\n\n";
	putResponse($row['respid'], false);
}

?>
<h1>Send Response</h1>

<form action="request.php" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" /></p>
<table>
<tr><td>Send to:</td><td>
<select name="sendto">
  <option value="">ALL</option>
  <option value="<?=htmlspecialchars($reqdata['login'])?>"><?=
  	htmlspecialchars($reqdata['login']).': '.
	htmlentities($reqdata['name'])?></option>
</select>
</td></tr>
<tr><td valign="top">Response:</td><td><textarea name="response" cols="80" rows="5"></textarea></td></tr>
<tr><td>&nbsp;</td><td><input type="submit" name="submit" value="Send" /></td></tr>
</table>
</form>

<?php
require('../footer.php');
