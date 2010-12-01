<?php
/**
 * View the details of a specific submission
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

function parseDiff($difftext){
	$line = strtok($difftext,"\n"); //first line
	if(sscanf($line, "### DIFFERENCES FROM LINE %d ###\n", $firstdiff) != 1)
		return htmlspecialchars($difftext);
	$return = sprintf("### DIFFERENCES FROM <a href='#firstdiff'>LINE %d</a> ###\n", $firstdiff);
	$line = strtok("\n");

	// We use a heuristic search to find the starting location of the
	// middle separator (subtract 4 lineno chars, add 1 initial matched char)
	$midloc = strlen(preg_replace('/[\'_ ] [!=<>\$] \'.*$/', '', $line)) - 4 + 1;

	while(strlen($line) != 0){
		$linenostr = substr($line,0,4);
		if($firstdiff == (int)$linenostr) {
			$linenostr = "<a id='firstdiff'></a>".$linenostr;
		}
		$diffline = substr($line,4);
		$mid = substr($diffline, $midloc, 3);
		switch($mid){
			case ' = ':
				$formdiffline = "<span class='correct'>".htmlspecialchars($diffline)."</span>";
				break;
			case ' ! ':
				$formdiffline = "<span class='differ'>".htmlspecialchars($diffline)."</span>";
				break;
			case ' $ ':
				$formdiffline = "<span class='endline'>".htmlspecialchars($diffline)."</span>";
				break;
			case ' > ':
			case ' < ':
				$formdiffline = "<span class='extra'>".htmlspecialchars($diffline)."</span>";
				break;
			default:
				$formdiffline = htmlspecialchars($diffline);
		}
		$return = $return . $linenostr . " " . $formdiffline . "\n";
		$line = strtok("\n");
	}
	return $return;
}

$pagename = basename($_SERVER['PHP_SELF']);

$id = (int)@$_REQUEST['id'];
if ( !empty($_GET['jid']) ) $jid = (int)$_GET['jid'];

// Also check for $id in claim POST variable as submissions.php cannot
// send the submission ID as a separate variable.
if ( is_array(@$_POST['claim']) ) {
	foreach( $_POST['claim'] as $key => $val ) $id = (int)$key;
}
if ( is_array(@$_POST['unclaim']) ) {
	foreach( $_POST['unclaim'] as $key => $val ) $id = (int)$key;
}

$lastverifier = @$_COOKIE['domjudge_lastverifier'];

require('init.php');

$title = 'Submission s'.@$id;

if ( ! $id ) error("Missing or invalid submission id");

$submdata = $DB->q('MAYBETUPLE SELECT s.teamid, s.probid, s.langid,
                    s.submittime, s.valid, c.cid, c.contestname,
                    t.name AS teamname, l.name AS langname, p.name AS probname,
                    CEILING(time_factor*timelimit) AS maxruntime
                    FROM submission s
                    LEFT JOIN team     t ON (t.login  = s.teamid)
                    LEFT JOIN problem  p ON (p.probid = s.probid)
                    LEFT JOIN language l ON (l.langid = s.langid)
                    LEFT JOIN contest  c ON (c.cid    = s.cid)
                    WHERE submitid = %i', $id);

if ( ! $submdata ) error ("Missing submission data");

$jdata = $DB->q('KEYTABLE SELECT judgingid AS ARRAYKEY, result, valid, starttime,
                 judgehost, verified, verifier
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

if ( isset($_POST['claim']) || isset($_POST['unclaim']) ) {

	// Set $lastverifier for default in select-box.
	$lastverifier = $verifier = getVerifier();

	if ( isset($_POST['unclaim']) ) $verifier = FALSE;

	// Set verifier cookie
	setVerifier($verifier);

	// Send headers now: after cookies, before possible warning messages.
	require_once(LIBWWWDIR . '/header.php');

	if ( !isset($jid) ) {
		warning("Cannot claim this submission: no valid judging found.");
	} else if ( $jdata[$jid]['verified'] ) {
		warning("Cannot claim this submission: judging already verified.");
	} else if ( empty($verifier) && $verifier!==FALSE ) {
		warning("Cannot claim this submission: no verifier specified.");
	} else {
		if ( !empty($jdata[$jid]['verifier']) && $verifier!==FALSE ) {
			warning("Submission claimed and previous owner " .
			        @$jdata[$jid]['verifier'] . " replaced.");
		}
		$DB->q('UPDATE judging SET verifier = ' . ($verifier===FALSE ? 'NULL %_ ' : '%s ') .
		       'WHERE judgingid = %i', $verifier, $jid);
	}
}

// Headers might already have been included.
require_once(LIBWWWDIR . '/header.php');

echo "<h1>Submission s".$id;
if ( $submdata['valid'] ) {
	echo "</h1>\n\n";
} else {
	echo " (ignored)</h1>\n\n";
	echo "<p>This submission is not used during the scoreboard
		  calculations.</p>\n\n";
}

?>
<table width="100%">
<tr><td>
<table>
<caption>Submission</caption>
<tr><td scope="row">Contest:</td><td>
	<a href="contest.php?id=<?php echo urlencode($submdata['cid'])?>">
	<?php echo htmlspecialchars($submdata['contestname'])?></a></td></tr>
<tr><td scope="row">Team:</td><td>
	<a href="team.php?id=<?php echo urlencode($submdata['teamid'])?>">
	<span class="teamid"><?php echo htmlspecialchars($submdata['teamid'])?></span>:
	<?php echo htmlspecialchars($submdata['teamname'])?></a></td></tr>
<tr><td scope="row">Problem:</td><td>
	<a href="problem.php?id=<?php echo $submdata['probid']?>">
	<span class="probid"><?php echo htmlspecialchars($submdata['probid'])?></span>:
	<?php echo htmlspecialchars($submdata['probname'])?></a></td></tr>
<tr><td scope="row">Language:</td><td>
	<a href="language.php?id=<?php echo $submdata['langid']?>">
	<?php echo htmlspecialchars($submdata['langname'])?></a></td></tr>
<tr><td scope="row">Submitted:</td><td><?php echo  htmlspecialchars($submdata['submittime']) ?></td></tr>
<tr><td scope="row">Source:</td><td>
	<a href="show_source.php?id=<?php echo $id?>">view source code</a></td></tr>
<tr><td scope="row">Max runtime:</td><td>
	<?php echo  htmlspecialchars($submdata['maxruntime']) ?> sec</td></tr>
</table>


</td><td>

<?php

if ( count($jdata) > 0 ) {
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

	echo "<br />\n" . rejudgeForm('submission', $id);

} else {
	echo "<em>Not judged yet</em>";
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

echo "</td></tr>\n</table>\n\n";


// Display the details of the selected judging

if ( isset($jid) )  {

	$jud = $DB->q('TUPLE SELECT * FROM judging WHERE judgingid = %i', $jid);

	// sanity check
	if ($jud['submitid'] != $id) error(
		sprintf("judingid j%d belongs to submitid s%d, not s%d",
			$jid, $jud['submitid'], $id));

	echo "<h2>Judging j" . (int)$jud['judgingid'] .
		($jud['valid'] == 1 ? '' : ' (INVALID)') . "</h2>\n\n";

	if ( !$jud['verified'] ) {
		echo addForm($pagename . '?id=' . urlencode($id) . '&jid=' . urlencode($jid));

		echo "<p>Claimed: " .
		    "<strong>" . printyn(!empty($jud['verifier'])) . "</strong>";
		if ( empty($jud['verifier']) ) {
			echo '; ';
		} else {
			echo ', by ' . htmlspecialchars($jud['verifier']) . '; ' .
			    addSubmit('unclaim', 'unclaim') . ' or ';
		}
		echo addSubmit('claim', 'claim') . ' as ' . addVerifierSelect($lastverifier) .
		    addEndForm();
	}

	$judging_ended = !empty($jud['endtime']);

	// display following data only when the judging has been completed
	if ( $judging_ended ) {

		// display verification data: verified, and by whom.
		// only if this is a valid judging, otherwise irrelevant
		if ( $jud['valid'] ) {
			if ( ! (VERIFICATION_REQUIRED && $jud['verified']) ) {

				$val = ! $jud['verified'];

				echo addForm('verify.php') .
				    addHidden('id',  $jud['judgingid']) .
				    addHidden('val', $val);
			}

			echo "<p>Verified: " .
			    "<strong>" . printyn($jud['verified']) . "</strong>";
			if ( $jud['verified'] && ! empty($jud['verifier']) ) {
				echo ", by " . htmlspecialchars($jud['verifier']);
			}

			if ( ! (VERIFICATION_REQUIRED && $jud['verified']) ) {
				echo '; ' . addSubmit(($val ? '' : 'un') . 'mark verified', 'verify');
				if ( $val ) echo ' by ' . addVerifierSelect($lastverifier);
				echo "</p>" . addEndForm();
			} else {
				echo "</p>\n";
			}
		}
	} else { // judging not ended yet
			echo "<p><b>Judging is not finished yet!</b></p>\n";
	}

	// Time (start, end, used)
	echo "<p class=\"judgetime\">Judging started: " . htmlspecialchars($jud['starttime']);

	$unix_start = strtotime($jud['starttime']);
	if ( $judging_ended ) {
		echo ', ended: ' . htmlspecialchars($jud['endtime']) .
			' (judging took '.
				printtimediff($unix_start, strtotime($jud['endtime']) ) . ')';
	} elseif ( $jud['valid'] ) {
		echo ' [still judging - busy ' . printtimediff($unix_start) . ']';
	} else {
		echo ' [aborted]';
	}
	echo "</p>\n\n";

	echo "<h3 id=\"compile\">Compilation output</h3>\n\n";

	if ( @$jud['output_compile'] ) {
		echo "<pre class=\"output_text\">".
			htmlspecialchars($jud['output_compile'])."</pre>\n\n";
	} elseif ( $jud['output_compile']===NULL ) {
		echo "<p class=\"nodata\">Compilation not finished yet.</p>\n";
	} else {
		echo "<p class=\"nodata\">There were no compiler errors or warnings.</p>\n";
	}

	// If compilation failed, there's no more info to show, so stop here
	if ( @$jud['result']=='compiler-error' ) {
		require(LIBWWWDIR . '/footer.php');
		exit(0);
	}

	// Display testcase runs
	$runs = $DB->q('SELECT r.*, t.rank, t.description FROM testcase t
	                LEFT JOIN judging_run r ON ( r.testcaseid = t.testcaseid AND
	                                             r.judgingid = %i )
	                WHERE t.probid = %s ORDER BY rank',
	               $jid, $submdata['probid']);
	$runinfo = $runs->gettable();

	echo "<h3 id=\"testcases\">Testcase runs</h3>\n\n";

	echo "<table class=\"list\">\n<thead>\n" .
		"<tr><th scope=\"col\">#</th><th scope=\"col\">runtime</th>" .
	    "<th scope=\"col\">result</th><th scope=\"col\">description</th>" .
	    "</tr>\n</thead>\n<tbody>\n";

	foreach ( $runinfo as $run ) {
		$link = '#run-' . $run['rank'];
		echo "<tr><td><a href=\"$link\">$run[rank]</a></td>".
		    "<td><a href=\"$link\">$run[runtime]</a></td>" .
		    "<td><a href=\"$link\"><span class=\"sol ";
		switch ( $run['runresult'] ) {
		case 'correct':
			echo 'sol_correct'; break;
		case NULL:
			echo 'disabled'; break;
		default:
			echo 'sol_incorrect';
		}
		echo "\">$run[runresult]</span></a></td>" .
		    "<td><a href=\"$link\">" .
		    htmlspecialchars(str_cut($run['description'],20)) . "</a></td>" .
			"</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";

	foreach ( $runinfo as $run ) {

		echo "<h4 id=\"run-$run[rank]\">Run $run[rank]</h4>\n\n";

		if ( $run['runresult']===NULL ) {
			echo "<p class=\"nodata\">Run not finished yet.</p>\n";
			continue;
		}

		echo "<table>\n" .
		    "<tr><td>Description:</td><td>" .
		    htmlspecialchars($run['description']) . "</td></tr>" .
		    "<tr><td>Runtime:</td><td>$run[runtime] sec</td></tr>" .
		    "<tr><td>Result: </td><td><span class=\"sol sol_" .
		    ( $run['runresult']=='correct' ? '' : 'in' ) .
		    "correct\">$run[runresult]</span></td></tr>" .
		    "</table>\n\n";

		echo "<h5>Diff output</h5>\n";
		if ( @$run['output_diff'] ) {
			echo "<pre class=\"output_text\">";
			echo parseDiff($run['output_diff']);
			echo "</pre>\n\n";
		} else {
			echo "<p class=\"nodata\">There was no diff output.</p>\n";
		}

		echo "<h5>Program output</h5>\n";
		if ( @$run['output_run'] ) {
			echo "<pre class=\"output_text\">".
			    htmlspecialchars($run['output_run'])."</pre>\n\n";
		} else {
			echo "<p class=\"nodata\">There was no program output.</p>\n";
		}

		echo "<h5>Error output (info/debug/errors)</h5>\n";
		if ( @$run['output_error'] ) {
			echo "<pre class=\"output_text\">".
			    htmlspecialchars($run['output_error'])."</pre>\n\n";
		} else {
			echo "<p class=\"nodata\">There was no stderr output.</p>\n";
		}
	}
}

// We're done!

require(LIBWWWDIR . '/footer.php');
