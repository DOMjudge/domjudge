<?php
/**
 * Clarification Request Management
 *
 * $Id$
 */

require('init.php');
$title = 'Clarification Requests';
require('../header.php');
require('menu.php');

echo "<h1>Clarification Requests</h1>\n\n";

$res = $DB->q('SELECT r.reqid, r.submittime, r.login, r.body, e.to
	FROM  clar_request r
	LEFT JOIN contest c ON (c.cid = r.cid)
	LEFT JOIN clar_response e ON (e.respid = r.reqid)
	WHERE e.submittime IS NULL
	ORDER BY r.submittime DESC');

if ( $res->count() == 0 ) {
	echo "<p><em>No new clarification requests.</em></p>\n\n";
} else {
	echo "<table>\n".
		"<tr><th>ReqID</th><th>team</th><th>request time</th><th>request</th>\n";
	while ($req = $res->next())
	{
		echo "<tr>".
			"<td>".$req['reqid']."</td>".
			"<td>".$req['login']."</td>".
			"<td>".$req['submittime']."</td>".
			"<td>".substr($req['body'], 0, 50)."</td>".
			"</tr>\n";
	}
	echo "</table>\n\n";
}

// TODO: beetje dubbele code...

$res = $DB->q('SELECT r.reqid, r.submittime, r.login, s.to, s.submittime as responsetime
	FROM  clar_request r
	LEFT JOIN contest c ON (c.cid = r.cid)
	LEFT JOIN clar_response s ON (s.respid = r.reqid)
	WHERE s.submittime IS NOT NULL
	ORDER BY r.submittime DESC');

if ( $res->count() == 0 ) {
	echo "<p><em>No clarification requests.</em></p>\n\n";
} else {
	echo "<table>\n".
		"<tr><th>ReqID</th><th>team</th><th>request time</th><th>replied to</th><th>response time</th>\n";
	while ($req = $res->next())
	{
		echo "<tr>".
			"<td>".$req['reqid']."</td>".
			"<td>".$req['login']."</td>".
			"<td>".$req['submittime']."</td>".
			"<td>".(isset($req['to'])?$req['to']:'All')."</td>".
			"<td>".$req['responsetime']."</td>".
			"</tr>\n";
	}
	echo "</table>\n\n";
}

require('../footer.php');
