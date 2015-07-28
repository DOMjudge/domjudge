<?php
/**
 * View the details of a specific submission
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

// Returns a piece of SQL code to return a field, truncated to a fixed
// character length, and with a message if truncation happened.
function truncate_SQL_field($field)
{
	$size = 50000;
	$msg = "\n[output truncated after 50,000 B]\n";
	return "IF( CHAR_LENGTH($field)>$size , CONCAT(LEFT($field,$size),'$msg') , $field)";
}

function display_compile_output($output, $success) {
	$color = "#6666FF";
	$msg = "not finished yet";
	if ( $output !== NULL ) {
		if ($success) {
			$color = '#1daa1d';
			$msg = 'successful';
			if ( !empty($output) ) {
				$msg .= ' (with ' . mb_substr_count($output, "\n") . ' line(s) of output)';
			}
		} else {
			$color = 'red';
			$msg = 'unsuccessful';
		}
	}

	echo '<h3 id="compile">' .
		(empty($output) ? '' : "<a class=\"collapse\" href=\"javascript:collapse('compile')\">") .
		"Compilation <span style=\"color:$color;\">$msg</span>" .
		(empty($output) ? '' : "</a>" ) . "</h3>\n\n";

	if ( !empty($output) ) {
		echo '<pre class="output_text" id="detailcompile">' .
			htmlspecialchars($output)."</pre>\n\n";
	} else {
		echo '<p class="nodata" id="detailcompile">' .
			"There were no compiler errors or warnings.</p>\n";
	}

	// Collapse compile output when compiled succesfully.
	if ( $success ) {
		echo "<script type=\"text/javascript\">
<!--
	collapse('compile');
// -->
</script>\n";
	}
}

function display_runinfo($runinfo, $is_final) {
	$sum_runtime = 0;
	$max_runtime = 0;
	$tclist = "";
	foreach ( $runinfo as $key => $run ) {
		$link = '#run-' . $run['rank'];
		$class = ( $is_final ? "tc_unused" : "tc_pending" );
		$short = "?";
		switch ( $run['runresult'] ) {
		case 'correct':
			$class = "tc_correct";
			$short = "✓";
			break;
		case NULL:
			break;
		default:
			$short = substr($run['runresult'], 0, 1);
			$class = "tc_incorrect";
		}
		$tclist .= "<a title=\"desc: " . htmlspecialchars($run['description']) .
			($run['runresult'] !== NULL ?  ", runtime: " . $run['runtime'] . "s, result: " . $run['runresult'] : '') .
			"\" href=\"$link\"" .
			($run['runresult'] == 'correct' ? ' onclick="display_correctruns(true);" ' : '') .
			"><span class=\"$class tc_box\">" . $short . "</span></a>";

		$sum_runtime += $run['runtime'];
		$max_runtime = max($max_runtime,$run['runtime']);
	}
	return array($tclist, $sum_runtime, $max_runtime);
}

function compute_lcsdiff($line1, $line2) {
	$tokens1 = preg_split('/\s+/', $line1);
	$tokens2 = preg_split('/\s+/', $line2);
	$cutoff = 100; // a) LCS gets inperformant, b) the output is not longer readable

	$n1 = min($cutoff, sizeof($tokens1));
	$n2 = min($cutoff, sizeof($tokens2));

	// compute longest common sequence length
	$dp = array_fill(0, $n1+1, array_fill(0, $n2+1, 0));
	for ($i = 1; $i < $n1 + 1; $i++) {
		for ($j = 1; $j < $n2 + 1; $j++) {
			if ($tokens1[$i-1] == $tokens2[$j-1]) {
				$dp[$i][$j] = $dp[$i-1][$j-1] + 1;
			} else {
				$dp[$i][$j] = max($dp[$i-1][$j], $dp[$i][$j-1]);
			}
		}
	}

	if ($n1 == $n2 && $n1 == $dp[$n1][$n2]) {
		return array(false, htmlspecialchars($line1) . "\n");
	}

	// reconstruct lcs
	$i = $n1;
	$j = $n2;
	$lcs = array();
	while ($i > 0 && $j > 0) {
		if ($tokens1[$i-1] == $tokens2[$j-1]) {
			$lcs[] = $tokens1[$i-1];
			$i--;
			$j--;
		} else if ($dp[$i-1][$j] > $dp[$i][$j-1]) {
			$i--;
		} else {
			$j--;
		}
	}
	$lcs = array_reverse($lcs);

	// reconstruct diff
	$diff = "";
	$l = sizeof($lcs);
	$i = 0;
	$j = 0;
	for ($k = 0; $k < $l ; $k++) {
		while ($i < $n1 && $tokens1[$i] != $lcs[$k]) {
			$diff .= "<del>" . htmlspecialchars($tokens1[$i]) . "</del> ";
			$i++;
		}
		while ($j < $n2 && $tokens2[$j] != $lcs[$k]) {
			$diff .= "<ins>" . htmlspecialchars($tokens2[$j]) . "</ins> ";
			$j++;
		}
		$diff .= $lcs[$k] . " ";
		$i++;
		$j++;
	}
	while ($i < $n1 && ($k >= $l || $tokens1[$i] != $lcs[$k])) {
		$diff .= "<del>" . htmlspecialchars($tokens1[$i]) . "</del> ";
		$i++;
	}
	while ($j < $n2 && ($k >= $l || $tokens2[$j] != $lcs[$k])) {
		$diff .= "<ins>" . htmlspecialchars($tokens2[$j]) . "</ins> ";
		$j++;
	}

	if ($cutoff < sizeof($tokens1) || $cutoff < sizeof($tokens2)) {
		$diff .= "[cut off rest of line...]";
	}
	$diff .= "\n";

	return array(TRUE, $diff);
}

require('init.php');

$id = getRequestID();
if ( !empty($_GET['jid']) ) $jid = (int)$_GET['jid'];
if ( !empty($_GET['rejudgingid']) ) $rejudgingid = (int)$_GET['rejudgingid'];

// Also check for $id in claim POST variable as submissions.php cannot
// send the submission ID as a separate variable.
if ( is_array(@$_POST['claim']) ) {
	foreach( $_POST['claim'] as $key => $val ) $id = (int)$key;
}
if ( is_array(@$_POST['unclaim']) ) {
	foreach( $_POST['unclaim'] as $key => $val ) $id = (int)$key;
}

if ( isset($jid) && isset($rejudgingid) ) {
	error("You cannot specify jid and rejudgingid at the same time.");
}

// If jid is set but not id, try to deduce it the id from the database.
if ( isset($jid) && ! $id ) {
	$id = $DB->q('MAYBEVALUE SELECT submitid FROM judging
	              WHERE judgingid = %i', $jid);
}

// If jid is not set but rejudgingid, try to deduce the jid from the database.
if ( !isset($jid) && isset($rejudgingid) ) {
	$jid = $DB->q('MAYBEVALUE SELECT judgingid FROM judging
	               WHERE submitid=%i AND rejudgingid = %i', $id, $rejudgingid);
}

$title = 'Submission s'.@$id;

if ( ! $id ) error("Missing or invalid submission id");

$submdata = $DB->q('MAYBETUPLE SELECT s.teamid, s.probid, s.langid, s.origsubmitid,
                    s.submittime, s.valid, c.cid, c.shortname AS contestshortname,
                    c.name AS contestname, t.name AS teamname, l.name AS langname,
                    cp.shortname AS probshortname, p.name AS probname,
                    CEILING(time_factor*timelimit) AS maxruntime
                    FROM submission s
                    LEFT JOIN team     t ON (t.teamid = s.teamid)
                    LEFT JOIN problem  p ON (p.probid = s.probid)
                    LEFT JOIN language l ON (l.langid = s.langid)
                    LEFT JOIN contest  c ON (c.cid    = s.cid)
                    LEFT JOIN contestproblem cp ON (cp.probid = p.probid AND cp.cid = c.cid)
                    WHERE submitid = %i', $id);

if ( ! $submdata ) error ("Missing submission data");

$jdata = $DB->q('KEYTABLE SELECT judgingid AS ARRAYKEY, result, j.valid, j.starttime,
                 j.judgehost, j.verified, j.jury_member, j.verify_comment,
                 r.reason, r.rejudgingid,
                 MAX(jr.runtime) AS max_runtime
                 FROM judging j
                 LEFT JOIN judging_run jr USING(judgingid)
                 LEFT JOIN rejudging r USING (rejudgingid)
                 WHERE cid = %i AND submitid = %i
                 GROUP BY (j.judgingid)
                 ORDER BY starttime ASC, judgingid ASC',
                $submdata['cid'], $id);

// When there's no judging selected through the request, we select the
// valid one.
if ( !isset($jid) ) {
	$jid = NULL;
	foreach( $jdata as $judgingid => $jud ) {
		if ( $jud['valid'] ) $jid = $judgingid;
	}
}

$jury_member = $username;

if ( isset($_REQUEST['claim']) || isset($_REQUEST['unclaim']) ) {

	// Send headers before possible warning messages.
	if ( !isset($_REQUEST['unclaim']) ) require_once(LIBWWWDIR . '/header.php');

	if ( !isset($jid) ) {
		warning("Cannot claim this submission: no valid judging found.");
	} else if ( $jdata[$jid]['verified'] ) {
		warning("Cannot claim this submission: judging already verified.");
	} else if ( empty($jury_member) && isset($_REQUEST['claim']) ) {
		warning("Cannot claim this submission: no jury member specified.");
	} else {
		if ( !empty($jdata[$jid]['jury_member']) && isset($_REQUEST['claim']) && $jury_member !== $jdata[$jid]['jury_member'] ) {
			warning("Submission claimed and previous owner " .
			        @$jdata[$jid]['jury_member'] . " replaced.");
		}
		$DB->q('UPDATE judging SET jury_member = ' .
		       (isset($_REQUEST['unclaim']) ? 'NULL %_ ' : '%s ') .
		       'WHERE judgingid = %i', $jury_member, $jid);
		auditlog('judging', $jid, isset($_REQUEST['unclaim']) ? 'unclaimed' : 'claimed');

		if ( isset($_REQUEST['unclaim']) ) header('Location: submissions.php');
	}
}

// Headers might already have been included.
require_once(LIBWWWDIR . '/header.php');

echo "<br/><h1 style=\"display:inline;\">Submission s" . $id .
    ( isset($submdata['origsubmitid']) ?
      ' (resubmit of <a href="submission.php?id='. urlencode($submdata['origsubmitid']) .
      '">s' . htmlspecialchars($submdata['origsubmitid']) . '</a>)' : '' ) .
	( $submdata['valid'] ? '' : ' (ignored)' ) . "</h1>\n\n";
if ( IS_ADMIN ) {
	$val = ! $submdata['valid'];
	$unornot = $val ? 'un' : '';
	echo "&nbsp;\n" . addForm('ignore.php') .
		addHidden('id',  $id) .
		addHidden('val', $val) .
			'<input type="submit" value="' . $unornot .
			'IGNORE this submission" onclick="return confirm(\'Really ' . $unornot .
			"ignore submission s$id?');\" /></form>\n";
}
if ( ! $submdata['valid'] ) {
	echo "<p>This submission is not used during scoreboard calculations.</p>\n\n";
}

// Condensed submission info on a single line with icons:
?>

<p>
<img title="team" alt="Team:" src="../images/team.png"/> <a href="team.php?id=<?php echo urlencode($submdata['teamid'])?>&amp;cid=<?php echo urlencode($submdata['cid'])?>">
    <?php echo htmlspecialchars($submdata['teamname'] . " (t" . $submdata['teamid'].")")?></a>&nbsp;&nbsp;
<img title="contest" alt="Contest:" src="../images/contest.png"/> <a href="contest.php?id=<?php echo $submdata['cid']?>">
	<span class="contestid"><?php echo htmlspecialchars($submdata['contestshortname'])?></span></a>&nbsp;&nbsp;
<img title="problem" alt="Problem:" src="../images/problem.png"/> <a href="problem.php?id=<?php echo $submdata['probid']?>&amp;cid=<?php echo urlencode($submdata['cid'])?>">
	<span class="probid"><?php echo htmlspecialchars($submdata['probshortname'])?></span>:
	<?php echo htmlspecialchars($submdata['probname'])?></a>&nbsp;&nbsp;
<img title="language" alt="Language:" src="../images/lang.png"/> <a href="language.php?id=<?php echo $submdata['langid']?>">
	<?php echo htmlspecialchars($submdata['langname'])?></a>&nbsp;&nbsp;
<img title="submittime" alt="Submittime:" src="../images/submittime.png"/>
	<?php echo '<span title="' . printtime($submdata['submittime'],'%Y-%m-%d %H:%M:%S (%Z)') . '">' .
	           printtime($submdata['submittime']) . '</span>' ?>&nbsp;&nbsp;
<img title="allowed runtime" alt="Allowed runtime:" src="../images/allowedtime.png"/>
	<?php echo  htmlspecialchars($submdata['maxruntime']) ?>s&nbsp;&nbsp;
<img title="view source code" alt="" src="../images/code.png"/>
<a href="show_source.php?id=<?= $id ?>" style="font-weight:bold;">view source code</a>
</p>

<?php

if ( count($jdata) > 1 || ( count($jdata)==1 && !isset($jid) ) ) {
	echo "<table class=\"list\">\n" .
		"<caption>Judgings</caption>\n<thead>\n" .
		"<tr><td></td><th scope=\"col\">ID</th><th scope=\"col\">start</th>" .
		"<th scope=\"col\">max runtime</th>" .
		"<th scope=\"col\">judgehost</th><th scope=\"col\">result</th>" .
		"<th scope=\"col\">rejudging</th>" .
		"</tr>\n</thead>\n<tbody>\n";

	// print the judgings
	foreach( $jdata as $judgingid => $jud ) {

		echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';
		$link = '<a href="submission.php?id=' . $id . '&amp;jid=' . $judgingid . '">';

		if ( $judgingid == $jid ) {
			echo '<td>' . $link . '&rarr;&nbsp;</a></td>';
		} else {
			echo '<td>' . $link . '&nbsp;</a></td>';
		}

		$rinfo = isset($jud['rejudgingid']) ? 'r' . $jud['rejudgingid'] . ' (' . $jud['reason'] . ')' : '';

		echo '<td>' . $link . 'j' . $judgingid . '</a></td>' .
			'<td>' . $link . printtime($jud['starttime']) . '</a></td>' .
			'<td>' . $link . htmlspecialchars($jud['max_runtime']) . ' s</a></td>' .
			'<td>' . $link . printhost(@$jud['judgehost']) . '</a></td>' .
			'<td>' . $link . printresult(@$jud['result'], $jud['valid']) . '</a></td>' .
			'<td>' . $link . htmlspecialchars($rinfo) . '</a></td>' .
			"</tr>\n";

	}
	echo "</tbody>\n</table><br />\n\n";
}

if ( !isset($jid) ) {
	echo "<p><em>Not (re)judged yet</em></p>\n\n";
}


// Display the details of the selected judging

if ( isset($jid) )  {

	$jud = $DB->q('TUPLE SELECT j.*, r.valid AS rvalid
	               FROM judging j
	               LEFT JOIN rejudging r USING (rejudgingid)
	               WHERE judgingid = %i', $jid);

	// sanity check
	if ($jud['submitid'] != $id) error(
		sprintf("judingid j%d belongs to submitid s%d, not s%d",
			$jid, $jud['submitid'], $id));

	// Display testcase runs
	$runs = $DB->q('TABLE SELECT r.runid, r.judgingid,
	                r.testcaseid, r.runresult, r.runtime, ' .
	                truncate_SQL_field('r.output_run')    . ' AS output_run, ' .
	                truncate_SQL_field('r.output_diff')   . ' AS output_diff, ' .
	                truncate_SQL_field('r.output_error')  . ' AS output_error, ' .
	                truncate_SQL_field('r.output_system') . ' AS output_system, ' .
	                truncate_SQL_field('t.output')        . ' AS output_reference,
	                t.rank, t.description, t.image_type, t.image_thumb
	                FROM testcase t
	                LEFT JOIN judging_run r ON ( r.testcaseid = t.testcaseid AND
	                                             r.judgingid = %i )
	                WHERE t.probid = %s ORDER BY rank',
	               $jid, $submdata['probid']);

	// Use original submission as previous, or try to find a previous
	// submission/judging of the same team/problem.
	if ( isset($submdata['origsubmitid']) ) {
		$lastsubmitid = $submdata['origsubmitid'];
	} else {
		$lastsubmitid = $DB->q('MAYBEVALUE SELECT submitid
		                        FROM submission
		                        WHERE teamid = %i AND probid = %i AND submittime < %s
		                        ORDER BY submittime DESC LIMIT 1',
		                       $submdata['teamid'],$submdata['probid'],
		                       $submdata['submittime']);
	}

	$lastjud = NULL;
	if ( $lastsubmitid !== NULL ) {
		$lastjud = $DB->q('MAYBETUPLE SELECT judgingid, result, verify_comment, endtime
		                   FROM judging
		                   WHERE submitid = %s AND valid = 1
		                   ORDER BY judgingid DESC LIMIT 1', $lastsubmitid);
		if ( $lastjud !== NULL ) {
			$lastruns = $DB->q('TABLE SELECT r.runtime, r.runresult, rank, description
			                    FROM testcase t
			                    LEFT JOIN judging_run r ON ( r.testcaseid = t.testcaseid AND
			                                                 r.judgingid = %i )
			                    WHERE t.probid = %s ORDER BY rank',
			                   $lastjud['judgingid'], $submdata['probid']);
		}
	}

	$judging_ended = !empty($jud['endtime']);
	list($tclist, $sum_runtime, $max_runtime) = display_runinfo($runs, $judging_ended);
	$tclist = "<tr><td>testcase runs:</td><td>" . $tclist . "</td></tr>\n";

	if ( $lastjud !== NULL ) {
		$lastjudging_ended = !empty($lastjud['endtime']);
		list($lasttclist, $sum_lastruntime, $max_lastruntime) = display_runinfo($lastruns, $lastjudging_ended);
		$lasttclist = "<tr class=\"lasttcruns\"><td><a href=\"submission.php?id=$lastsubmitid\">s$lastsubmitid</a> runs:</td><td>" .
				$lasttclist . "</td></tr>\n";
	}

	$state = '';
	if ( isset($jud['rejudgingid']) ) {
		$reason = $DB->q('VALUE SELECT reason FROM rejudging WHERE rejudgingid=%i', $jud['rejudgingid']);
		$state = ' (rejudging <a href="rejudging.php?id=' .
			 urlencode($jud['rejudgingid']) . '">r' .
			 htmlspecialchars($jud['rejudgingid']) .
			 '</a>, reason: ' .
			 htmlspecialchars($reason) . ')';
	} else if ( $jud['valid'] != 1 ) {
		$state = ' (INVALID)';
	}

	echo "<h2 style=\"display:inline;\">Judging j" . (int)$jud['judgingid'] .  $state .
		"</h2>\n\n&nbsp;";
	if ( !$jud['verified'] ) {
		echo addForm($pagename . '?id=' . urlencode($id) . '&amp;jid=' . urlencode($jid));

		if ( !empty($jud['jury_member']) ) {
			echo ' (claimed by ' . htmlspecialchars($jud['jury_member']) . ') ';
		}
		if ( $jury_member == @$jud['jury_member']) {
			echo addSubmit('unclaim', 'unclaim');
		} else {
			echo addSubmit('claim', 'claim');
		}
		echo addEndForm();
	}
	echo rejudgeForm('submission', $id) . '<br/><br/>';

	echo 'Result: ' . printresult($jud['result'], $jud['valid']) . ( $lastjud === NULL ? '' :
		'<span class="lastresult"> (<a href="submission.php?id=' . $lastsubmitid . '">s' . $lastsubmitid. '</a>: '
		. @$lastjud['result'] . ')</span>' ) . ', ' .
		'Judgehost: <a href="judgehost.php?id=' . urlencode($jud['judgehost']) . '">' .
		printhost($jud['judgehost']) . '</a>, ';

	// Time (start, end, used)
	echo "<span class=\"judgetime\">Judging started: " . printtime($jud['starttime'],'%H:%M:%S');

	if ( $judging_ended ) {
		echo ', finished in '.
				printtimediff($jud['starttime'], $jud['endtime']) . ' s';
	} elseif ( $jud['valid'] || isset($jud['rejudgingid']) ) {
		echo ' [still judging - busy ' . printtimediff($jud['starttime']) . ']';
	} else {
		echo ' [aborted]';
	}


	echo "</span>\n";

	if ( @$jud['result']!=='compiler-error' ) {
		echo ", max/sum runtime: " . sprintf('%.2f/%.2fs',$max_runtime,$sum_runtime);
		if ( isset($max_lastruntime) ) {
			echo " <span class=\"lastruntime\">(<a href=\"submission.php?id=$lastsubmitid\">s$lastsubmitid</a>: "
				. sprintf('%.2f/%.2fs',$max_lastruntime,$sum_lastruntime) .
				")</span>";
		}

		echo "<table>\n$tclist";
		if ( $lastjud !== NULL ) {
			echo $lasttclist;
		}
		echo "</table>\n";
	}

	// Show JS toggle of previous submission results.
	if ( $lastjud!==NULL ) {
		echo "<span class=\"testcases_prev\">" .
		     "<a href=\"javascript:togglelastruns();\">show/hide</a> results of previous " .
		     "<a href=\"submission.php?id=$lastsubmitid\">submission s$lastsubmitid</a>" .
		     ( empty($lastjud['verify_comment']) ? '' :
		       "<span class=\"prevsubmit\"> (verify comment: '" .
		       $lastjud['verify_comment'] . "')</span>" ) . "</span>";
	}

	// display following data only when the judging has been completed
	if ( $judging_ended ) {

		// display verification data: verified, by whom, and comment.
		// only if this is a valid judging, otherwise irrelevant
		if ( $jud['valid'] || (isset($jud['rejudgingid']) && $jud['rvalid'])) {
			$verification_required = dbconfig_get('verification_required', 0);
			if ( ! ($verification_required && $jud['verified']) ) {

				$val = ! $jud['verified'];

				echo addForm('verify.php') .
				    addHidden('id',  $jud['judgingid']) .
				    addHidden('val', $val) .
				    addHidden('redirect', @$_SERVER['HTTP_REFERER']);
			}

			echo "<p>Verified: " .
			    "<strong>" . printyn($jud['verified']) . "</strong>";
			if ( $jud['verified'] && ! empty($jud['jury_member']) ) {
				echo ", by " . htmlspecialchars($jud['jury_member']);
				if ( !empty($jud['verify_comment']) ) {
					echo ' with comment "'.htmlspecialchars($jud['verify_comment']).'"';
				}
			}

			if ( ! ($verification_required && $jud['verified']) ) {
				echo '; ' . addSubmit(($val ? '' : 'un') . 'mark verified', 'verify');
				if ( $val ) echo ' with comment ' . addInput('comment', '', 25);
				echo "</p>" . addEndForm();
			} else {
				echo "</p>\n";
			}
		}
	} else { // judging not ended yet
			echo "<p><b>Judging is not finished yet!</b></p>\n";
	}

?>
<script type="text/javascript">
<!--
togglelastruns();
// -->
</script>
<?php

	display_compile_output(@$jud['output_compile'], @$jud['result']!=='compiler-error');

	// If compilation is not finished yet or failed, there's no more info to show, so stop here
	if ( @$jud['output_compile'] === NULL || @$jud['result']=='compiler-error' ) {
		require(LIBWWWDIR . '/footer.php');
		exit(0);
	}

	foreach ( $runs as $run ) {

		if ( $run['runresult'] == 'correct' ) {
			echo "<div class=\"run_correct\">";
		}
		echo "<h4 id=\"run-$run[rank]\">Run $run[rank]</h4>\n\n";

		if ( $run['runresult']===NULL ) {
			echo "<p class=\"nodata\">" .
				( $jud['result'] === NULL ? 'Run not started/finished yet.' : 'Run not used for final result.' ) .
				"</p>\n";
			continue;
		}

		$timelimit_str = '';
		if ( $run['runresult']=='timelimit' ) {
			if ( preg_match('/timelimit exceeded.*hard (wall|cpu) time/',$run['output_system']) ) {
				$timelimit_str = '<b>(terminated)</b>';
			} else {
				$timelimit_str = '<b>(finished late)</b>';
			}
		}
		echo "<table>\n<tr><td>";
		echo "<table>\n" .
		    "<tr><td>Description:</td><td>" .
		    htmlspecialchars($run['description']) . "</td></tr>" .
		    "<tr><td>Download: </td><td>" .
		    "<a href=\"testcase.php?probid=" . htmlspecialchars($submdata['probid']) .
		    "&amp;rank=" . $run['rank'] . "&amp;fetch=input\">Input</a> / " .
		    "<a href=\"testcase.php?probid=" . htmlspecialchars($submdata['probid']) .
		    "&amp;rank=" . $run['rank'] . "&amp;fetch=output\">Reference Output</a> / " .
		    "<a href=\"team_output.php?runid=" . $run['runid'] . "&amp;cid=" .
		    $submdata['cid'] . "\">Team Output</a></td></tr>" .
		    "<tr><td>Runtime:</td><td>$run[runtime] sec $timelimit_str</td></tr>" .
		    "<tr><td>Result: </td><td><span class=\"sol sol_" .
		    ( $run['runresult']=='correct' ? '' : 'in' ) .
		    "correct\">$run[runresult]</span></td></tr>" .
		    "</table>\n\n";
		echo "</td><td>";
		if ( isset($run['image_thumb']) ) {
			$imgurl = "./testcase.php?probid=" .  urlencode($submdata['probid']) .
			    "&amp;rank=" . $run['rank'] . "&amp;fetch=image";
			echo "<a href=\"$imgurl\">";
			echo '<img src="data:image/' . $run['image_type'] . ';base64,' .
			    base64_encode($run['image_thumb']) . '"/>';
			echo "</a>";
		}
		echo "</td></tr></table>\n\n";

		echo "<h5>Diff output</h5>\n";
		if ( strlen(@$run['output_diff']) > 0 ) {
			echo "<pre class=\"output_text\">";
			echo parseRunDiff($run['output_diff']);
			echo "</pre>\n\n";
		} else {
			echo "<p class=\"nodata\">There was no diff output.</p>\n";
		}

		if ( $run['runresult'] !== 'correct' ) {
			echo "<pre class=\"output_text\">";
			// TODO: can be improved using diffposition.txt
			// FIXME: only show when diffposition.txt is set?
			// FIXME: cut off after XXX lines
			$lines_team = preg_split('/\n/', trim($run['output_run']));
			$lines_ref  = preg_split('/\n/', trim($run['output_reference']));

			$diffs = array();
			$firstErr = sizeof($lines_team) + 1;
			$lastErr  = -1;
			for ($i = 0; $i < min(sizeof($lines_team), sizeof($lines_ref)); $i++) {
				$lcs = compute_lcsdiff($lines_team[$i], $lines_ref[$i]);
				if ( $lcs[0] === TRUE ) {
					$firstErr = min($firstErr, $i);
					$lastErr  = max($lastErr, $i);
				}
				$diffs[] = $lcs[1];
			}
			$contextLines = 5;
			$firstErr -= $contextLines;
			$lastErr  += $contextLines;
			$firstErr = max(0, $firstErr);
			$lastErr  = min(sizeof($diffs)-1, $lastErr);
			echo "<table class=\"lcsdiff\">\n";
			if ($firstErr > 0) {
				echo "<tr><td class=\"linenr\">[...]</td><td/></tr>\n";
			}
			for ($i = $firstErr; $i <= $lastErr; $i++) {
				echo "<tr><td class=\"linenr\">" . ($i + 1) . "</td><td>" . $diffs[$i] . "</td></tr>";
			}
			if ($lastErr < sizeof($diffs) - 1) {
				echo "<tr><td class=\"linenr\">[...]</td><td/></tr>\n";
			}
			echo "</table>";

			echo "</pre>\n\n";
		}

		echo "<h5>Program output</h5>\n";
		if ( strlen(@$run['output_run']) > 0 ) {
			echo "<pre class=\"output_text\">".
			    htmlspecialchars($run['output_run'])."</pre>\n\n";
		} else {
			echo "<p class=\"nodata\">There was no program output.</p>\n";
		}

		echo "<h5>Program error output</h5>\n";
		if ( strlen(@$run['output_error']) > 0 ) {
			echo "<pre class=\"output_text\">".
			    htmlspecialchars($run['output_error'])."</pre>\n\n";
		} else {
			echo "<p class=\"nodata\">There was no stderr output.</p>\n";
		}

		echo "<h5>Judging system output (info/debug/errors)</h5>\n";
		if ( strlen(@$run['output_system']) > 0 ) {
			echo "<pre class=\"output_text\">".
			    htmlspecialchars($run['output_system'])."</pre>\n\n";
		} else {
			echo "<p class=\"nodata\">There was no judging system output.</p>\n";
		}

		if ( $run['runresult'] == 'correct' ) {
			echo "</div>";
		}
	}

?>
	<script type="text/javascript">
		function display_correctruns(show) {
			elements = document.getElementsByClassName('run_correct');
			for (i = 0; i < elements.length; i++) {
				elements[i].style.display = show ? 'block' : 'none';
			}
		}
		display_correctruns(false);
	</script>
<?php


}

// We're done!

require(LIBWWWDIR . '/footer.php');
