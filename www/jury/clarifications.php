<?php
/**
 * Clarification Request Management
 *
 * $Id$
 */

require('init.php');
$refresh = '15;url=clarifications.php';
$title = 'Clarification Requests';
require('../header.php');
require('menu.php');

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
}

echo "<h1>Clarification Requests</h1>\n\n";

$res = $DB->q('SELECT q.*
	FROM  clar_request q
	LEFT JOIN clar_response r ON (r.reqid = q.reqid)
	WHERE r.reqid IS NULL AND q.cid = %i
	ORDER BY q.submittime DESC', getCurContest());

if ( $res->count() == 0 ) {
	echo "<p><em>No new clarification requests.</em></p>\n\n";
} else {
	echo "<h3>New Requests:</h3>\n";
	echo "<table>\n".
		"<tr><th>ID</th><th>team</th><th>request time</th><th>request</th>\n";
	while ($req = $res->next())
	{
		$req['reqid'] = (int)$req['reqid'];
		echo "<tr>".
			"<td><a href=\"request.php?id=".$req['reqid']."\">q".$req['reqid']."</a></td>".
			"<td class=\"teamid\"><a href=\"team.php?id=".urlencode($req['login']). "\">".
				htmlspecialchars($req['login'])."</a></td>".
			"<td>".printtime($req['submittime'])."</td>".
			"<td><a href=\"request.php?id=".$req['reqid']."\">".
				htmlspecialchars(str_cut($req['body'],50)).
			"</a></td>".
			"</tr>\n";
	}
	echo "</table>\n\n";
}

echo "<p>\n\n";

$res = $DB->q('SELECT DISTINCT q.*
	FROM  clar_request q
	LEFT JOIN clar_response r ON (r.reqid = q.reqid)
	WHERE r.reqid IS NOT NULL AND q.cid = %i
	ORDER BY q.submittime DESC', getCurContest());

if ( $res->count() == 0 ) {
	echo "<p><em>No old clarification requests.</em></p>\n\n";
} else {
	echo "<h3>Old Requests:</h3>\n";
	echo "<table>\n".
		"<tr><th>ID</th><th>team</th><th>request time</th><th>request</th>\n";
	while ($req = $res->next())
	{
		$req['reqid'] = (int)$req['reqid'];
		echo "<tr>".
			"<td><a href=\"request.php?id=".$req['reqid']."\">q".$req['reqid']."</a></td>".
			"<td class=\"teamid\"><a href=\"team.php?id=".urlencode($req['login']). "\">".
				htmlspecialchars($req['login'])."</a></td>".
			"<td>".$req['submittime']."</td>".
			"<td><a href=\"request.php?id=".$req['reqid']."\">".
				htmlspecialchars(str_cut($req['body'], 50)).
			"</a></td>".
			"</tr>\n";
	}
	echo "</table>\n\n";
}

echo "<h3>Clarification Responses:</h3>\n\n";

$res = $DB->q('SELECT r.*
	FROM  clar_response r
	WHERE r.cid = %i
	ORDER BY r.submittime DESC', getCurContest());

if ( $res->count() == 0 ) {
	echo "<p><em>No clarification responses.</em></p>\n\n";
} else {
	echo "<table>\n".
		"<tr><th>ID</th><th>team</th><th>time</th><th>message</th>\n";
	while ($req = $res->next())
	{
		$team = (isset($req['rcpt'])
				?"<a href=\"team.php?id=".urlencode($req['rcpt']). "\">".htmlspecialchars($req['rcpt'])."</a>"
				:'All');

		$req['respid'] = (int)$req['respid'];
		echo "<tr>".
			"<td><a href=\"response.php?id=".$req['respid']."\">r".$req['respid']."</a></td>".
			"<td class=\"teamid\">".$team."</td>".
			"<td>".$req['submittime']."</td>".
			"<td><a href=\"response.php?id=".$req['respid']."\">".
				htmlspecialchars(str_cut($req['body'], 50)).
			"</a></td></tr>";
		echo "</tr>\n";
	}
	echo "</table>\n\n";
}

?>
<h1>Send Response</h1>
<form action="clarifications.php" method="post">
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
<?

require('../footer.php');
