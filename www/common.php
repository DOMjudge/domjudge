<?php

/**
 * Common functions shared between team/public/jury interface
 *
 * $Id$
 */

/**
 * Print a list of submissions, either all or only those that
 * match <key> = <value>. Output is always limited to the
 * current or last contest. $detailed will be set to false
 * for a team page.
 */
function getSubmissions($key = null, $value = null, $detailed = TRUE) {

	global $DB;

	// we need two queries: one for all submissions, and one with the results for the valid ones.
	if($key && $value) {
		$res = $DB->q('SELECT submitid,team,probid,langid,submittime,judgerid
			FROM submission WHERE '.$key.' = %s AND cid = %i ORDER BY submittime DESC',
			$value, getCurContest() );
	} else {
		$res = $DB->q('SELECT submitid,team,probid,langid,submittime,judgerid
			FROM submission WHERE cid = %i ORDER BY submittime DESC',
			getCurContest() );
	}

	if($res->count() == 0) {
		echo "<p><em>No submissions</em></p>\n\n";
		return;
	}

	$resulttable = $DB->q('KEYTABLE SELECT j.*, submitid AS ARRAYKEY
		FROM judging j
		WHERE (valid = 1 OR valid IS NULL) AND cid = %i', getCurContest() );

	echo "<table>\n<tr>".
		( $detailed ? "<th>ID</th>" : '' ) .
		"<th>time</th>".
		($key != 'team' ? "<th>team</th>" : '') .
		($key != 'probid' ? "<th>problem</th>" : '') .
		($key != 'langid' ? "<th>lang</th>" : '') .
		"<th>status</th>".
		($detailed ? "<th>last<br>judge</th>" : '') .
		"</tr>\n";
	while($row = $res->next()) {
		$sid = (int)$row['submitid'];
		$isfinished = ($detailed || ! @$resulttable[$row['submitid']]['result']);
		echo "<tr>" .
			($detailed ? "<td><a href=\"submission.php?id=".$sid."\">s".$sid."</a></td>" : '') .
			"<td>" . printtime($row['submittime']) . "</td>" .
			($key != 'team' ? "<td class=\"teamid\">".htmlspecialchars($row['team']) . "</td>" : '') .
			($key != 'probid' ? "<td>".htmlspecialchars($row['probid']) . "</td>" : '') .
			($key != 'langid' ? "<td>".htmlspecialchars($row['langid']) . "</td>" : '') .
			"<td>";
		if( ! @$resulttable[$row['submitid']]['result'] ) {
			echo printresult(@$row['judgerid'] ? '' : 'queued');
		} else {
			if ( $detailed ) {
				echo '<a href="judging.php?id=' . $resulttable[$row['submitid']]['judgingid'] . '">';
			} else {
				echo '<a href="submission_details.php?id=' . $sid . '">';
			}
			echo printresult( @$resulttable[$row['submitid']]['result'] ) .
				'</a>';
		}
		echo "</td>" .
		 	( $detailed ? "<td>".printhost(@$resulttable[$row['submitid']]['judgerid']) . "</td>" : '') .
		 	"</td></tr>\n";
	}
	echo "</table>\n\n";

	return;
}


/**
 * Outputs a list of judgings, limited by key=value
 */
function getJudgings($key, $value) {
	global $DB;

	$res = $DB->q('SELECT * FROM judging
		WHERE '.$key.' = %s AND cid = %i ORDER BY starttime DESC',
		$value, getCurContest() );

	if( $res->count() == 0 ) {
		echo "<p><em>No judgings.</em></p>\n\n";
	} else {
		echo "<table>\n".
			"<tr><th>ID</th><th>start</th><th>end</th><th>judge</th><th>result</th><th>valid</th>\n";
		while( $jud = $res->next() ) {
			echo "<tr" . ( $jud['valid'] ? '':' class="disabled"' ).
				"><td><a href=\"judging.php?id=" . (int)$jud['judgingid'] . '">j' .
					(int)$jud['judgingid'] . "</a>" .
				"</td><td>".printtime($jud['starttime']) .
				"</td><td>".printtime(@$jud['endtime']) .
				"</td><td>".printhost(@$jud['judgerid']) .
				"</td><td><a href=\"judging.php?id=" . (int)$jud['judgingid'] . '">' .
					printresult(@$jud['result'], $jud['valid']) . "</a>" .
				"</td><td align=\"center\">".printyn($jud['valid']) .
				"</td></tr>\n";
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

function putResponse($id, $showReq = true) {
	global $DB;

	$respdata = $DB->q('MAYBETUPLE SELECT r.*, c.contestname
		FROM  clar_response r
		LEFT JOIN contest c ON (c.cid = r.cid)
		WHERE r.respid = %i', $id);

	if(!$respdata)	error ("Missing clarification response data");

?>
<table>
<tr><td>Contest:</td><td><?=htmlentities($respdata['contestname'])?></td></tr>
<?
	if($showReq) {
		echo "<tr><td>Request:</td><td>";
		if(isset($respdata['reqid'])) {
			echo '<a href="request.php?id=',$respdata['reqid'].'">q'.$respdata['reqid'].'</a>';
		} else {
			echo 'none';
		}
		echo "</td></tr>\n";
	}
?>
<tr><td>Send to:</td><td><?=isset($respdata['rcpt'])?'<a href="team.php?id='.urlencode($respdata['rcpt']).'"><span class="teamid">'. htmlspecialchars($respdata['rcpt'])."</span></a>":"All"?></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($respdata['submittime']) ?></td></tr>
<tr><td>Response:</td><td class="filename"><pre class="output_text"><?=htmlspecialchars($respdata['body']) ?></pre></td></tr>
</table>
<?
}

function putRequest($id) {
	global $DB;

	$reqdata = $DB->q('MAYBETUPLE SELECT q.*, c.contestname
		FROM  clar_request q
		LEFT JOIN contest c ON (c.cid = q.cid)
		WHERE q.reqid = %i', $id);
	if(!$reqdata)	error ("Missing clarification response data");

?>

<table>
<tr><td>Contest:</td><td><?=htmlentities($reqdata['contestname'])?></td></tr>
<tr><td>From:</td><td><a href="team.php?id=<?=urlencode($reqdata['login'])?>"><span class="teamid"><?=htmlspecialchars($reqdata['login'])?></span></a></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($reqdata['submittime']) ?></td></tr>
<tr><td>Request:</td><td class="filename"><pre class="output_text"><?=htmlspecialchars($reqdata['body']) ?></pre></td></tr>
</table>

<?
	return $reqdata;
}
