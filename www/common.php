<?php

/**
 * Common functions shared between team/public/jury interface
 *
 * $Id$
 */

/**
 * Return the base URI for the DOMjudge Webinterface.
 */
function getBaseURI() {
	return WEBBASEURI;
}

/**
 * Print a list of submissions, either all or only those that
 * match <key> = <value>. Output is always limited to the
 * current or last contest.
 */
function putSubmissions($key = null, $value = null, $isjury = FALSE) {

	global $DB;
	
	/* We need two kind of queries: one for all submissions, and one with
	 * the results for the valid ones. When key & value are supplied we're
	 * looking for the submissions of a specific team or judger, else the
	 * complete list.
	 */
	$keyvalmatch = '';
	if( $key && $value ) {
		$keyvalmatch = " s.$key = \"" . mysql_escape_string($value) . '" AND ';
	}

	$cid = getCurContest();

	$res = $DB->q('SELECT s.submitid, s.team, s.probid, s.langid, s.submittime,
		s.judgerid,	t.name as teamname, p.name as probname, l.name as langname
		FROM submission s
		LEFT JOIN team t ON(t.login=s.team)
		LEFT JOIN problem p ON(p.probid=s.probid)
		LEFT JOIN language l ON(l.langid=s.langid)
		WHERE ' . $keyvalmatch . 's.cid = %i ORDER BY s.submittime DESC',
		$cid );

	// nothing found...
	if( $res->count() == 0 ) {
		echo "<p><em>No submissions</em></p>\n\n";
		return;
	}

	$resulttable = $DB->q('KEYTABLE SELECT j.*, submitid AS ARRAYKEY
		FROM judging j
		WHERE (valid = 1 OR valid IS NULL) AND cid = %i', $cid );

	// print the table with the submissions. 
	// table header; leave out the field that is our key (because it's the same
	// for all rows)
	echo "<table>\n<tr>" .
		( $isjury ? "<th>ID</th>" : '' ) .
		"<th>time</th>" .
		($key != 'team'   ? "<th>team</th>"    : '') .
		($key != 'probid' ? "<th>problem</th>" : '') .
		($key != 'langid' ? "<th>lang</th>"    : '') .
		"<th>status</th>" .
		($isjury ? "<th>last<br />judge</th>" : '') .
		"</tr>\n";
	// print each row with links to detailed information
	while( $row = $res->next() ) {
		$sid = (int)$row['submitid'];
		$isfinished = ($isjury || ! @$resulttable[$row['submitid']]['result']);
		echo "<tr>";
		if ( $isjury ) {
			echo "<td><a href=\"submission.php?id=$sid\">s$sid</a></td>";
		}
		echo "<td>" . printtime($row['submittime']) . "</td>";
		if ( $key != 'team' ) {
			echo '<td class="teamid" title="' . htmlentities($row['teamname']) . '">' .
				( $isjury ? '<a href="team.php?id=' . $row['team'] . '">' : '' ) .
				htmlspecialchars($row['team']) .
				( $isjury ? '</a>' : '') . '</td>';
		}
		if ( $key != 'probid' ) {
			echo '<td title="' . htmlentities($row['probname']) . '">' .
				( $isjury ? '<a href="problem.php?id=' . $row['probid'] . '">' : '' ) .
				htmlspecialchars($row['probid']) .
				( $isjury ? '</a>' : '') . '</td>';
		}
		if ( $key != 'langid' ) {
			echo '<td title="' . htmlentities($row['langname']) . '">' .
				( $isjury ? '<a href="language.php?id=' . $row['langid'] . '">' : '' ) .
				htmlspecialchars($row['langid']) .
				( $isjury ? '</a>' : '') . '</td>';
		}
		echo "<td>";
		if( ! @$resulttable[$row['submitid']]['result'] ) {
			echo printresult(@$row['judgerid'] ? '' : 'queued', TRUE, isset($value));
		} else {
			// link directly to a specific judging
			if ( $isjury ) {
				echo '<a href="judging.php?id=' .
					$resulttable[$row['submitid']]['judgingid'] . '">';
			} else {
				echo '<a href="submission_details.php?id=' . $sid . '">';
			}
			echo printresult(@$resulttable[$row['submitid']]['result']) . '</a>';
		}
		echo "</td>";
		if ( $isjury ) {
			$judger = @$resulttable[$row['submitid']]['judgerid'];
			echo '<td><a href="judger.php?id=' . urlencode($judger) . '">' .
				printhost($judger) . '</a></td>';
		}
		echo "</tr>\n";
	}
	echo "</table>\n\n";

	return;
}


/**
 * Outputs a list of judgings, limited by key=value
 */
function putJudgings($key, $value) {
	global $DB;

	// get the judgings for a specific key and value pair
	$res = $DB->q('SELECT * FROM judging
		WHERE '.$key.' = %s AND cid = %i ORDER BY starttime DESC',
		$value, getCurContest() );

	if( $res->count() == 0 ) {
		echo "<p><em>No judgings.</em></p>\n\n";
	} else {
		echo "<table>\n<tr><th>ID</th><th>start</th><th>end</th>";
		if ( $key != 'judge' ) echo "<th>judge</th>";
		echo "<th>result</th><th>valid</th></tr>\n";
		while( $jud = $res->next() ) {
			echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';
			echo '<td><a href="judging.php?id=' . (int)$jud['judgingid'] .
				'">j' .	(int)$jud['judgingid'] . '</a></td>';
			echo '<td>' . printtime($jud['starttime']) . '</td>';
			echo '<td>' . printtime(@$jud['endtime'])  . '</td>';
			echo '<td><a href="judger.php?id=' . urlencode(@$jud['judgerid']) .
				'">' . printhost(@$jud['judgerid']) . '</a></td>';
			echo '<td><a href="judging.php?id=' . (int)$jud['judgingid'] . '">' .
				printresult(@$jud['result'], $jud['valid']) . '</a></td>';
			echo '<td align="center">' . printyn($jud['valid']) . '</td>';
			echo "</tr>\n";
		}
		echo "</table>\n\n";
	}

	return;
}


/**
 * Output clock
 */
function putClock() {
	echo '<div id="clock">' . strftime('%a %e %b %Y %T') . "</div>\n\n";
}

/**
 * Output a clarification response with id $id.
 * $showReq determines if the corresponding request should also be
 * displayed, and $teamlink whether the teamname has to be linked.
 */
function putResponse($id, $showReq = true, $teamlink = true) {
	global $DB;

	$respdata = $DB->q('MAYBETUPLE SELECT r.*, c.contestname, t.name
		FROM  clar_response r
		LEFT JOIN contest c ON (c.cid = r.cid)
		LEFT JOIN team t ON (t.login = r.rcpt)
		WHERE r.respid = %i', $id);

	if( ! $respdata ) error ("Missing clarification response data");

?>
<table>
<tr><td>Contest:</td><td><?=htmlentities($respdata['contestname'])?></td></tr>
<?php
	if( $showReq ) {
		echo "<tr><td>Request:</td><td>";
		if( isset($respdata['reqid']) ) {
			echo '<a href="request.php?id=' . urlencode($respdata['reqid']) .
				'">q' . htmlspecialchars($respdata['reqid']) . '</a>';
		} else {
			echo 'none';
		}
		echo "</td></tr>\n";
	}
?>
<tr><td>Sent to:</td><td><?=isset($respdata['rcpt'])?
	($teamlink?'<a href="team.php?id='.urlencode($respdata['rcpt']).'">':'')
	.'<span class="teamid">'
	.htmlspecialchars($respdata['rcpt']).'</span>: '
	.htmlentities($respdata['name'])
	.($teamlink?'</a>':'')
	:'ALL'?></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($respdata['submittime']) ?></td></tr>
<tr><td valign="top">Response:</td><td class="filename"><pre class="output_text"><?=
	wordwrap(htmlspecialchars($respdata['body'])) ?></pre></td></tr>
</table>
<?php
}

/**
 * Output a clarification request with id $id.
 * $login can be used to check if your team may view this request.
 */
function putRequest($id, $login = NULL) {
	global $DB;

	$reqdata = $DB->q('MAYBETUPLE SELECT q.*, c.contestname, t.name
		FROM  clar_request q
		LEFT JOIN contest c ON (c.cid = q.cid)
		LEFT JOIN team t ON (q.login = t.login)
		WHERE q.reqid = %i', $id);
	if( ! $reqdata ) {
		error ("Missing clarification request data");
	}
	if( isset($login) && $reqdata['login'] != $login ) {
		error ("Not your clarification request");
	}

?>

<table>
<tr><td>Contest:</td><td><?=htmlentities($reqdata['contestname'])?></td></tr>
<tr><td>From:</td><td>
<?=!isset($login)?'<a href="team.php?id='.urlencode($reqdata['login']).'">':''?>
<?= '<span class="teamid">' .
	htmlspecialchars($reqdata['login']) . '</span>: '.
	htmlentities($reqdata['name'])?>
<?=!isset($login)?'</a>':''?>
</td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($reqdata['submittime']) ?></td></tr>
<tr><td valign="top">Request:</td><td class="filename"><pre class="output_text"><?=
	wordwrap(htmlspecialchars($reqdata['body'])) ?></pre></td></tr>
</table>

<?php
	return $reqdata;
}


function putDOMjudgeVersion() {
	echo "<hr /><address>DOMjudge/" . DOMJUDGE_VERSION . 
		" at ".$_SERVER['SERVER_NAME']." Port ".$_SERVER['SERVER_PORT']."</address>\n";
}
