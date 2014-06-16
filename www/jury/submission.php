<?php
/**
 * View the details of a specific submission
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

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
		(empty($output) ? '' : "<a href=\"javascript:collapse('compile')\">") .
		"Compilation <span style=\"color:$color;\">$msg</span>" .
		(empty($output) ? '' : "</a>" ) . "</h3>\n\n";

	if ( !empty($output) ) {
		echo '<pre class="output_text details" id="detailcompile">' .
			htmlspecialchars($output)."</pre>\n\n";
	} else {
		echo '<p class="nodata details" id="detailcompile">' .
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

require('init.php');

$id = getRequestID();
if ( !empty($_GET['jid']) ) $jid = (int)$_GET['jid'];

// Also check for $id in claim POST variable as submissions.php cannot
// send the submission ID as a separate variable.
if ( is_array(@$_POST['claim']) ) {
	foreach( $_POST['claim'] as $key => $val ) $id = (int)$key;
}
if ( is_array(@$_POST['unclaim']) ) {
	foreach( $_POST['unclaim'] as $key => $val ) $id = (int)$key;
}

// If jid is set but not id, try to deduce it from the database.
if ( isset($jid) && ! $id ) {
	$id = $DB->q('MAYBEVALUE SELECT submitid FROM judging
	              WHERE judgingid = %i', $jid);
}

$title = 'Submission s'.@$id;

if ( ! $id ) error("Missing or invalid submission id");

$submdata = $DB->q('MAYBETUPLE SELECT s.teamid, s.probid, s.langid,
                    s.submittime, s.valid, c.cid, c.contestname,
                    t.name AS teamname, l.name AS langname, p.shortname, p.name AS probname,
                    CEILING(time_factor*timelimit) AS maxruntime
                    FROM submission s
                    LEFT JOIN team     t ON (t.teamid = s.teamid)
                    LEFT JOIN problem  p ON (p.probid = s.probid)
                    LEFT JOIN language l ON (l.langid = s.langid)
                    LEFT JOIN contest  c ON (c.cid    = s.cid)
                    WHERE submitid = %i', $id);

if ( ! $submdata ) error ("Missing submission data");

$jdata = $DB->q('KEYTABLE SELECT judgingid AS ARRAYKEY, result, valid, starttime,
                 judgehost, verified, jury_member, verify_comment
                 FROM judging
                 WHERE cid = %i AND submitid = %i
                 ORDER BY starttime ASC, judgingid ASC',
                 $cid, $id);

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

echo "<br/><h1 style=\"display:inline;\">Submission s".$id;
if ( $submdata['valid'] ) {
	echo "</h1>\n\n";
} else {
	echo " (ignored)</h1>\n\n";
	echo "<p>This submission is not used during the scoreboard
		  calculations.</p>\n\n";
}
if ( IS_ADMIN ) {
	$val = ! $submdata['valid'];
	$unornot = $val ? 'un' : '';
	echo "\n" . addForm('ignore.php') .
		addHidden('id',  $id) .
		addHidden('val', $val) .
			'<input type="submit" value="' . $unornot .
			'IGNORE this submission" onclick="return confirm(\'Really ' . $unornot .
			"ignore submission s$id?');\" /></form>\n";
}
echo '<br/><br/>';

?>

<img title="team" alt="Team:" src="../images/team.png"/> <a href="team.php?id=<?php echo urlencode($submdata['teamid'])?>">
	<?php echo htmlspecialchars($submdata['teamname'] . " (t" . $submdata['teamid'].")")?></a>,
<img title="problem" alt="Problem:" src="../images/problem.png"/> <a href="problem.php?id=<?php echo $submdata['probid']?>">
	<span class="probid"><?php echo htmlspecialchars($submdata['shortname'])?></span>:
	<?php echo htmlspecialchars($submdata['probname'])?></a>,
<img title="language" alt="Language:" src="../images/lang.png"/> <a href="language.php?id=<?php echo $submdata['langid']?>">
	<?php echo htmlspecialchars($submdata['langname'])?></a>,
<img title="submittime" alt="Submittime:" src="../images/submittime.png"/> <?php echo printtime($submdata['submittime']) ?>,
<img title="allowed runtime" alt="Allowed runtime:" src="../images/allowedtime.png"/>
	<?php echo  htmlspecialchars($submdata['maxruntime']) ?>s,
<img title="view source code" alt="" src="../images/code.png"/>
<a href="show_source.php?id=<?= $id ?>" style="font-weight:bold;">view source code</a>

<?php

if ( count($jdata) > 1 ) {
	echo "<br/><br/>";
	echo "<table class=\"list\">\n" .
		"<caption>Judgings</caption>\n<thead>\n" .
		"<tr><td></td><th scope=\"col\">ID</th><th scope=\"col\">start</th>" .
		"<th scope=\"col\">judgehost</th><th scope=\"col\">result</th>" .
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

		echo '<td>' . $link . 'j' . $judgingid . '</a></td>' .
			'<td>' . $link . printtime($jud['starttime']) . '</a></td>' .
			'<td>' . $link . printhost(@$jud['judgehost']) . '</a></td>' .
			'<td>' . $link . printresult(@$jud['result'], $jud['valid']) . '</a></td>' .
			"</tr>\n";

	}
    echo "</tbody>\n</table>\n\n";

} else if ( count($jdata) == 0 ) {
	echo "<br/><br/><em>Not judged yet</em>";
}


// Display the details of the selected judging

if ( isset($jid) )  {

	$jud = $DB->q('TUPLE SELECT * FROM judging WHERE judgingid = %i', $jid);

	// sanity check
	if ($jud['submitid'] != $id) error(
		sprintf("judingid j%d belongs to submitid s%d, not s%d",
			$jid, $jud['submitid'], $id));
	
	// Display testcase runs
	$runs = $DB->q('SELECT r.*, t.rank, t.description FROM testcase t
	                LEFT JOIN judging_run r ON ( r.testcaseid = t.testcaseid AND
	                                             r.judgingid = %i )
	                WHERE t.probid = %s ORDER BY rank',
	               $jid, $submdata['probid']);
	$runinfo = $runs->gettable();

	$lastsubmitid = $DB->q('MAYBEVALUE SELECT submitid
	                        FROM submission
	                        WHERE teamid = %i AND probid = %i AND submittime < %s
	                        ORDER BY submittime DESC LIMIT 1',
	                       $submdata['teamid'],$submdata['probid'],
	                       $submdata['submittime']);
	$lastjud = NULL;
	if ( $lastsubmitid !== NULL ) {
		$lastjud = $DB->q('MAYBETUPLE SELECT judgingid, result, verify_comment, endtime
		                   FROM judging
		                   WHERE submitid = %s AND valid = 1
		                   ORDER BY judgingid DESC LIMIT 1', $lastsubmitid);
		if ( $lastjud !== NULL ) {
			$lastruns = $DB->q('SELECT r.runtime, r.runresult, rank, description FROM testcase t
			                    LEFT JOIN judging_run r ON ( r.testcaseid = t.testcaseid AND
			                                                 r.judgingid = %i )
			                    WHERE t.probid = %s ORDER BY rank',
			                   $lastjud['judgingid'], $submdata['probid']);
			$lastruninfo = $lastruns->gettable();
		}
	}

	$judging_ended = !empty($jud['endtime']);
	list($tclist, $sum_runtime, $max_runtime) = display_runinfo($runinfo, $judging_ended);
	$tclist = "<tr><td>testcase runs:</td><td>" . $tclist . "</td></tr>\n";

	if ( $lastjud !== NULL ) {
		$lastjudging_ended = !empty($lastjud['endtime']);
		list($lasttclist, $sum_lastruntime, $max_lastruntime) = display_runinfo($lastruninfo, $lastjudging_ended);
		$lasttclist = "<tr class=\"lasttcruns\"><td><a href=\"submission.php?id=$lastsubmitid\">s$lastsubmitid</a> runs:</td><td>" .
				$lasttclist . "</td></tr>\n";
	}

	echo "<br/><h2 style=\"display:inline;\">Judging j" . (int)$jud['judgingid'] .
		($jud['valid'] == 1 ? '' : ' (INVALID)') .
		"</h2>\n\n";
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
	echo rejudgeForm('submission', $id);

	echo ( $lastjud === NULL ? '' :
	      "<span class=\"testcases_prev\">" .
	      "<a href=\"javascript:togglelastruns();\">show/hide</a> results of previous " .
	      "<a href=\"submission.php?id=$lastsubmitid\">submission s$lastsubmitid</a>" .
	          ( empty($lastjud['verify_comment']) ? '' :
		    "<span class=\"prevsubmit\"> (verify comment: '" . $lastjud['verify_comment'] . "')</span>"
                  ) .
	      "</span>" );

	echo '<br/><br/>';


	echo 'Result: ' . printresult($jud['result'], $jud['valid']) . ( $lastjud === NULL ? '' :
		'<span class="lastresult"> (<a href="submission.php?id=' . $lastsubmitid . '">s' . $lastsubmitid. '</a>: '
		. @$lastjud['result'] . ')</span>' ) . ', ' .
		'Judgehost: <a href="judgehost.php?id=' . urlencode($jud['judgehost']) . '">' . 
		printhost($jud['judgehost']) . '</a>, ';

	// Time (start, end, used)
	echo "<span class=\"judgetime\">Judging started: " . printtime($jud['starttime'],'%H:%M:%S');

	if ( $judging_ended ) {
		echo ', ended: ' . printtime($jud['endtime'],'%H:%M:%S') .
			' (judging took '.
				printtimediff($jud['starttime'], $jud['endtime']) . ')';
	} elseif ( $jud['valid'] ) {
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
	
	// display following data only when the judging has been completed
	if ( $judging_ended ) {

		// display verification data: verified, by whom, and comment.
		// only if this is a valid judging, otherwise irrelevant
		if ( $jud['valid'] ) {
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

	foreach ( $runinfo as $run ) {

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
			if ( preg_match('/timelimit exceeded.*hard wall time/',$run['output_system']) ) {
				$timelimit_str = '<b>(terminated)</b>';
			} else {
				$timelimit_str = '<b>(finished late)</b>';
			}
		}
		echo "<table>\n" .
		    "<tr><td>Description:</td><td>" .
		    htmlspecialchars($run['description']) . "</td></tr>" .
		    "<tr><td>Download: </td><td>" .
		    "<a href=\"testcase.php?probid=" . htmlspecialchars($submdata['probid']) .
		    "&amp;rank=" . $run['rank'] . "&amp;fetch=input\">Input</a> / " .
		    "<a href=\"testcase.php?probid=" . htmlspecialchars($submdata['probid']) .
		    "&amp;rank=" . $run['rank'] . "&amp;fetch=output\">Reference Output</a> / " .
		    "<a href=\"team_output.php?runid=" . $run['runid'] . "\">Team Output</a>" .
		    "</td></tr>" .
		    "<tr><td>Runtime:</td><td>$run[runtime] sec $timelimit_str</td></tr>" .
		    "<tr><td>Result: </td><td><span class=\"sol sol_" .
		    ( $run['runresult']=='correct' ? '' : 'in' ) .
		    "correct\">$run[runresult]</span></td></tr>" .
		    "</table>\n\n";

		echo "<h5>Diff output</h5>\n";
		if ( strlen(@$run['output_diff']) > 0 ) {
			echo "<pre class=\"output_text\">";
			echo parseRunDiff($run['output_diff']);
			echo "</pre>\n\n";
		} else {
			echo "<p class=\"nodata\">There was no diff output.</p>\n";
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
