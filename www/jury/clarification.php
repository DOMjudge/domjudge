<?php
/**
 * Send Clarification Response
 *
 * $Id: clarifications.php 615 2004-10-15 21:23:40Z nkp0405 $
 */

require('init.php');

/** insert new response */
if (isset($_REQUEST['submit'])
  && !empty($_REQUEST['response']))
{
	if(empty($_REQUEST['sendto'])) {
		$respid = $DB->q('RETURNID INSERT INTO clar_response (reqid, cid, submittime, rcpt, body)
			VALUES (NULL, %i, now(), NULL, %s)', getCurContest(), $_REQUEST['response']);
	} else {
		$respid = $DB->q('RETURNID INSERT INTO clar_response (reqid, cid, submittime, rcpt, body)
			VALUES (NULL, %i, now(), %s, %s)', getCurContest(), $_REQUEST['sendto'], $_REQUEST['response']);
	}

	/** redirect back to the clarifications overview */
	header('Location: ' . getBaseURI() . 'jury/clarifications.php');
	exit;
}

$title = 'Clarification Requests';
require('../header.php');
require('menu.php');

?>
<h1>Send Clarification Response</h1>

<form action="clarification.php" method="post">
<table>
<tr><td>Send to:</td><td>
<select name="sendto">
<option value="">ALL</option>
<?

	$res = $DB->q('SELECT login, name FROM team ORDER  BY category ASC, name ASC');
	while ($row = $res->next()) {
		echo "<option value=\"" . htmlspecialchars($row['login']) . "\">" .
			htmlentities($row['login'] . ": " . $row['name']) . "</option>\n";
	}
?>
</select>
</td></tr>
<tr><td valign="top">Response:</td><td><textarea name="response" cols="80" rows="5"></textarea></td></tr>
<tr><td>&nbsp;</td><td><input type="submit" name="submit" value="Send" /></td></tr>
</table>
</form>

<?php
require('../footer.php');
