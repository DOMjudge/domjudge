<?php
/**
 * Clarification Request Management
 *
 * $Id$
 */

require('init.php');
$refresh = '15;url='.getBaseURI().'jury/clarifications.php';
$title = 'Clarification Requests';
require('../header.php');
require('menu.php');

?>
<p>
<a href="clarification.php">Send Clarification Response</a>
</p>
<?

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
		"<tr><th>ID</th><th>team</th><th>time</th><th>request</th></tr>\n";
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
		"<tr><th>ID</th><th>team</th><th>time</th><th>request</th></tr>\n";
	while ($req = $res->next())
	{
		$req['reqid'] = (int)$req['reqid'];
		echo "<tr>".
			"<td><a href=\"request.php?id=".$req['reqid']."\">q".$req['reqid']."</a></td>".
			"<td class=\"teamid\"><a href=\"team.php?id=".urlencode($req['login']). "\">".
				htmlspecialchars($req['login'])."</a></td>".
			"<td>".printtime($req['submittime'])."</td>".
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
		"<tr><th>ID</th><th>team</th><th>time</th><th>message</th></tr>\n";
	while ($req = $res->next())
	{
		$team = (isset($req['rcpt'])
				?"<a href=\"team.php?id=".urlencode($req['rcpt']). "\">".htmlspecialchars($req['rcpt'])."</a>"
				:'All');

		$req['respid'] = (int)$req['respid'];
		echo "<tr>".
			"<td><a href=\"response.php?id=".$req['respid']."\">r".$req['respid']."</a></td>".
			"<td class=\"teamid\">".$team."</td>".
			"<td>".printtime($req['submittime'])."</td>".
			"<td><a href=\"response.php?id=".$req['respid']."\">".
				htmlspecialchars(str_cut($req['body'], 50)).
			"</a></td>";
		echo "</tr>\n";
	}
	echo "</table>\n\n";
}

require('../footer.php');
