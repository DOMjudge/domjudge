<?php

/**
 * Common functions shared between team/public/jury interface
 *
 * $Id$
 */

/**
 * Print a list of submissions, either all or only those that
 * match <key> = <value>. Output is always limited to the
 * current or last contest.
 */
function getSubmissions($key = null, $value = null) {

	global $DB;
	
	$detailed = $key != 'team';

	// we need two queries: one for all submissions, and one with the results for the valid ones.
	// when key & value are supplied we're looking for the submissions of a specific team or judger,
	// else the complete list.
	if($key && $value) {
		$res = $DB->q('SELECT s.submitid,s.team,s.probid,s.langid,s.submittime,s.judgerid,
			t.name as teamname, p.name as probname, l.name as langname
			FROM submission s
			LEFT JOIN team t ON(t.login=s.team)
			LEFT JOIN problem p ON(p.probid=s.probid)
			LEFT JOIN language l ON(l.langid=s.langid)
			WHERE s.'.$key.' = %s AND s.cid = %i ORDER BY s.submittime DESC',
			$value, getCurContest() );
	} else {
		$res = $DB->q('SELECT s.submitid,s.team,s.probid,s.langid,s.submittime,s.judgerid,
			t.name as teamname, p.name as probname, l.name as langname
			FROM submission s
			LEFT JOIN team t ON(t.login=s.team)
			LEFT JOIN problem p ON(p.probid=s.probid)
			LEFT JOIN language l ON(l.langid=s.langid)
			WHERE s.cid = %i ORDER BY s.submittime DESC',
			getCurContest() );
	}

	// nothing found...
	if($res->count() == 0) {
		echo "<p><em>No submissions</em></p>\n\n";
		return;
	}

	$resulttable = $DB->q('KEYTABLE SELECT j.*, submitid AS ARRAYKEY
		FROM judging j
		WHERE (valid = 1 OR valid IS NULL) AND cid = %i', getCurContest() );

	// print the table with the submissions. 
	// table header; leave out the field that is our key (because it's the same
	// for all rows)
	echo "<table>\n<tr>".
		( $detailed ? "<th>ID</th>" : '' ) .
		"<th>time</th>".
		($key != 'team' ? "<th>team</th>" : '') .
		($key != 'probid' ? "<th>problem</th>" : '') .
		($key != 'langid' ? "<th>lang</th>" : '') .
		"<th>status</th>".
		($detailed ? "<th>last<br />judge</th>" : '') .
		"</tr>\n";
	// print each row with links to detailed information
	while($row = $res->next()) {
		$sid = (int)$row['submitid'];
		$isfinished = ($detailed || ! @$resulttable[$row['submitid']]['result']);
		echo "<tr>" .
			($detailed ? "<td><a href=\"submission.php?id=".$sid."\">s".$sid."</a></td>" : '') .
			"<td>" . printtime($row['submittime']) . "</td>" .
			($key != 'team' ? "<td class=\"teamid\" title=\"".htmlentities($row['teamname'])."\">".
				htmlspecialchars($row['team']) . "</td>" : '') .
			($key != 'probid' ? "<td title=\"".htmlentities($row['probname'])."\">".
				htmlspecialchars($row['probid']) . "</td>" : '') .
			($key != 'langid' ? "<td title=\"".htmlentities($row['langname'])."\">".
				htmlspecialchars($row['langid']) . "</td>" : '') .
			"<td>";
		if( ! @$resulttable[$row['submitid']]['result'] ) {
			echo printresult(@$row['judgerid'] ? '' : 'queued', TRUE, isset($value));
		} else {
			// link directly to a specific judging
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
		 	"</tr>\n";
	}
	echo "</table>\n\n";

	return;
}


/**
 * Outputs a list of judgings, limited by key=value
 */
function getJudgings($key, $value) {
	global $DB;

	// get the judgings for a specific key and value pair
	$res = $DB->q('SELECT * FROM judging
		WHERE '.$key.' = %s AND cid = %i ORDER BY starttime DESC',
		$value, getCurContest() );

	if( $res->count() == 0 ) {
		echo "<p><em>No judgings.</em></p>\n\n";
	} else {
		echo "<table>\n".
			"<tr><th>ID</th><th>start</th><th>end</th><th>judge</th><th>result</th><th>valid</th></tr>\n";
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

	if(!$respdata)	error ("Missing clarification response data");

?>
<table>
<tr><td>Contest:</td><td><?=htmlentities($respdata['contestname'])?></td></tr>
<?
	if($showReq) {
		echo "<tr><td>Request:</td><td>";
		if(isset($respdata['reqid'])) {
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
	if(!$reqdata) {
		error ("Missing clarification request data");
	}
	if(isset($login) && $reqdata['login'] != $login) {
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

<?
	return $reqdata;
}

/**
 * Output the general scoreboard.
 * $myteamid can be passed to highlight a specific row.
 * $isjury set to true means the scoreboard will always be current, regardless of the
 *  lastscoreupdate setting in the contesttable.
 */
function putScoreBoard($myteamid = null, $isjury = FALSE) {

	global $DB;

	$cid = getCurContest();
	$contdata = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $cid);
	
	echo "<h1>Scoreboard ".htmlentities($contdata['contestname'])."</h1>\n\n";
	echo "<h4>starts: ".printtime($contdata['starttime']) . ' - ends: ' .printtime($contdata['endtime'])."</h4>\n\n";

	echo "<table class=\"scoreboard\" cellpadding=\"3\">\n";


	// get the teams and problems
	$teams = $DB->q('TABLE SELECT login,name,category
		FROM team');
	$probs = $DB->q('TABLE SELECT probid,name
		FROM problem WHERE allow_submit = 1 ORDER BY probid');


	echo "<colgroup><col id=\"scoreteamname\" /><col id=\"scoresolv\" /><col id=\"scoretotal\" />";
	for($i = 0; $i < count($probs); $i++) {
		echo "<col class=\"scoreprob\" />";
	}
	echo "</colgroup>\n";

	echo "<tr id=\"scoreheader\"><th>TEAM</th>";
	echo "<th>solved</th><th>time</th>\n";
	foreach($probs as $pr) {
		echo "<th title=\"".htmlentities($pr['name'])."\">".htmlentities($pr['probid'])."</th>";
	}
	echo "</tr>\n";

	$THEMATRIX = $SCORES = $TEAMNAMES = array();

	$SUMMARY = array('num_correct' => 0, 'total_time' => 0);

	// for each team, fetch the status of each problem
	foreach($teams as $team) {

		// to lookup the team name at the end
		$TEAMNAMES[$team['login']]=$team['name'];

		// reset vars
		$grand_total_correct = 0;
		$grand_total_time = 0;
		
		// for each problem fetch the result
		foreach($probs as $pr) {

			$result = $DB->q('SELECT result, 
				(UNIX_TIMESTAMP(submittime)-UNIX_TIMESTAMP(c.starttime))/60 as timediff
				FROM judging LEFT JOIN submission s USING(submitid)
				LEFT OUTER JOIN contest c ON(c.cid=s.cid)
				WHERE team = %s AND probid = %s AND valid = 1 AND result IS NOT NULL AND s.cid = %i'
				. ( !$isjury && isset($contdata['lastscoreupdate']) ? ' AND submittime <= c.lastscoreupdate ' : '' )  . '
				ORDER BY submittime',
				$team['login'], $pr['probid'], $cid);

			// reset vars
			$total_submitted = $penalty = $total_time = 0;
			$correct = FALSE;

			// for each submission
			while($row = $result->next()) {
				$total_submitted++;

				// if correct, don't look at any more submissions after this one
				if($row['result'] == 'correct') {

					$correct = TRUE;
					$total_time = round((int)@$row['timediff']);
					
					break;
				}

				// 20 penality minutes for each submission
				// (will only be counted if this problem is correctly solved)
				$penalty += 20;
			}

			// calculate penalty time: only when correct add it to the total
			if(!$correct) {
				$penalty = 0;
			} else {
				$grand_total_correct++;
				$grand_total_time += ($total_time + $penalty);
			}

			// THEMATRIX contains the scores for each problem.
			$THEMATRIX[$team['login']][$pr['probid']] = array (
				'correct' => $correct,
				'submitted' => $total_submitted,
				'time' => $total_time,
				'penalty' => $penalty );

		}

		// SCORES contains the total number correct and time for each team.
		// This is our sorting criterion and alpabetically on teamname otherwise. 
		$SCORES[$team['login']]['num_correct'] = $grand_total_correct;
		$SCORES[$team['login']]['total_time'] = $grand_total_time;
		$SCORES[$team['login']]['teamname'] = $TEAMNAMES[$team['login']];
		$SCORES[$team['login']]['category'] = $team['category'];

	}

	// sort the array using our custom comparison function
	uasort($SCORES, 'cmp');

	// print the whole thing
	foreach($SCORES as $team => $totals) {

		// team name, total correct, total time
		echo "<tr" . (@$myteamid == $team ? ' id="scorethisisme"':'')
			." class=\"category" . $totals['category'] . "\"><td class=\"scoretn\">"
			.htmlentities($TEAMNAMES[$team])
			."</td><td class=\"scorenc\">"
			.$totals['num_correct']."</td><td class=\"scorett\">".$totals['total_time']."</td>";

		$SUMMARY['num_correct'] += $totals['num_correct'];
		$SUMMARY['total_time']  += $totals['total_time'];

		// for each problem
		foreach($THEMATRIX[$team] as $prob => $pdata) {
			echo "<td class=\"";
			// CSS class for correct/incorrect/neutral results
			if( $pdata['correct'] ) { 
				echo 'score_correct';
			} elseif ( $pdata['submitted'] > 0 ) {
				echo 'score_incorrect';
			} else {
				echo 'score_neutral';
			}
			// number of submissions for this problem
			echo "\">" . $pdata['submitted'];
			// if correct, print time scored
			if( ($pdata['time']+$pdata['penalty']) > 0) {
				echo " (" . $pdata['time'] . ' + ' . $pdata['penalty'] . ")";
			}
			echo "</td>";
			@$SUMMARY[$prob]['submissions'] += $pdata['submitted'];
			@$SUMMARY[$prob]['correct'] += ($pdata['correct'] ? 1 : 0);
			if($pdata['time'] > 0) {
				@$SUMMARY[$prob]['times'][] = $pdata['time'];
			}
		}
		echo "</tr>\n";

	}

	// print a summaryline
	echo "\n<tr id=\"scoresummary\"><td>Summary</td>";
	echo "<td class=\"scorenc\">".$SUMMARY['num_correct']."</td><td class=\"scorett\">".
		$SUMMARY['total_time']."</td>";
	foreach($probs as $pr) {
		echo "<td>".$SUMMARY[$pr['probid']]['submissions'].' / '.$SUMMARY[$pr['probid']]['correct'].
			' / '.( isset($SUMMARY[$pr['probid']]['times']) ? min(@$SUMMARY[$pr['probid']]['times']) : '-')."</td>";
	}
	echo "</tr>\n\n";

	
	echo "</table>\n\n";

	$res = $DB->q('SELECT * FROM category ORDER BY catid');

	// only print legend when there's more than one category
	if ($res->count() > 1) {
		echo "<table class=\"scoreboard_legend\"><tr><th>Legenda</th></tr>\n";
		while($row = $res->next()) {
			echo "<tr class=\"category" . $row['catid'] . "\"><td align=\"center\">" . $row['name'] . "</td></tr>";
		}
		echo "</table>\n\n";
	}

	// last modified date, now if we are the jury, else include the lastupdatetime
	if( !$isjury && isset($contdata['lastscoreupdate'])) {
		$lastupdate = min(time(), strtotime($contdata['lastscoreupdate']));
	} else {
		$lastupdate = time();
	}
	echo "<span id=\"lastmod\">Last Update: " . date('j M Y H:i', 
		$lastupdate ) . "</span>\n\n";

	return;
}

// comparison function for scoreboard
function cmp ($a, $b) {
	if ( $a['num_correct'] != $b['num_correct'] ) {
		return $a['num_correct'] > $b['num_correct'] ? -1 : 1;
	}
	if ( $a['total_time'] != $b['total_time'] ) {
		return $a['total_time'] < $b['total_time'] ? -1 : 1;
	}
	if ( $a['teamname'] != $b['teamname'] ) {
		return $a['teamname'] < $b['teamname'] ? -1 : 1;
	}
	return 0;
}


function putDOMjudgeVersion() {
	echo "<hr /><address>DOMjudge/" . DOMJUDGE_VERSION . 
		" at ".$_SERVER['SERVER_NAME']." Port ".$_SERVER['SERVER_PORT']."</address>\n";
}
