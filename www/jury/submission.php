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
	$loc = (strlen($line) - 5) / 2;
	while(strlen($line) != 0){
		$linenostr = substr($line,0,4);
		if($firstdiff == (int)$linenostr) {
			$linenostr = "<a id='firstdiff'></a>".$linenostr;
		}
		$diffline = substr($line,4);
		$mid = substr($diffline, $loc-1, 3);
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

$id = (int)$_REQUEST['id'];
if ( !empty($_GET['jid']) ) $jid = (int)$_GET['jid'];

$lastverifier = @$_COOKIE['domjudge_lastverifier'];

require('init.php');
require_once(SYSTEM_ROOT . '/lib/www/forms.php');

$title = 'Submission s'.@$id;

if ( ! $id ) error("Missing or invalid submission id");

$submdata = $DB->q('MAYBETUPLE SELECT s.teamid, s.probid, s.langid,
					s.submittime, s.valid, c.cid, c.contestname, 
          t.name AS teamname, l.name AS langname, p.name AS probname
                    FROM submission s
                    LEFT JOIN team     t ON (t.login  = s.teamid)
                    LEFT JOIN problem  p ON (p.probid = s.probid)
                    LEFT JOIN language l ON (l.langid = s.langid)
                    LEFT JOIN contest  c ON (c.cid    = s.cid)
                    WHERE submitid = %i', $id);

if ( ! $submdata ) error ("Missing submission data");

require(SYSTEM_ROOT . '/lib/www/header.php');

echo "<h1>Submission s".$id;
if ( $submdata['valid'] ) {
	echo "</h1>\n\n";
} else {
	echo " (ignored)</h1>\n\n";
	echo "<p>This submission is not used during the scoreboard
		  calculations.</p>\n\n";
}

$jdata = $DB->q('KEYTABLE SELECT judgingid AS ARRAYKEY, result, valid, starttime, judgehost
                 FROM judging
                 WHERE cid = %i AND submitid = %i
                 ORDER BY starttime ASC, judgingid ASC',
                 $cid, $id);

?>
<table width="100%">
<tr><td valign="top">
<table>
<caption>Submission</caption>
<tr><td scope="row">Contest:</td><td><?=htmlspecialchars($submdata['contestname'])?></td></tr>
<tr><td scope="row">Team:</td><td>
	<a href="team.php?id=<?=urlencode($submdata['teamid'])?>">
	<span class="teamid"><?=htmlspecialchars($submdata['teamid'])?></span>:
	<?=htmlspecialchars($submdata['teamname'])?></a></td></tr>
<tr><td scope="row">Problem:</td><td>
	<a href="problem.php?id=<?=$submdata['probid']?>">
	<span class="probid"><?=htmlspecialchars($submdata['probid'])?></span>:
	<?=htmlspecialchars($submdata['probname'])?></a></td></tr>
<tr><td scope="row">Language:</td><td>
	<a href="language.php?id=<?=$submdata['langid']?>">
	<?=htmlspecialchars($submdata['langname'])?></a></td></tr>
<tr><td scope="row">Submitted:</td><td><?= htmlspecialchars($submdata['submittime']) ?></td></tr>
<tr><td scope="row">Source:</td><td class="filename">
	<a href="show_source.php?id=<?=$id?>">
	<?=htmlspecialchars(getSourceFilename($submdata['cid'],$id,$submdata['teamid'],
		$submdata['probid'],$submdata['langid']))?></a></td></tr>
</table>


</td><td valign="top">

<?php

if ( count($jdata) > 0 ) { 
	echo "<table class=\"list\">\n" .
		"<caption>Judgings</caption>\n<thead>\n" .
		"<tr><td></td><th scope=\"col\">ID</th><th scope=\"col\">start</th>" .
		"<th scope=\"col\">judgehost</th><th scope=\"col\">result</th>" .
		"</tr>\n</thead>\n<tbody>\n";

	// when there's no judging selected through the request, we find
	// out what the best one should be. The valid one, or else the most
	// recent invalid one.
	if ( ! isset($jid) ) {
		$jid = $DB->q('VALUE SELECT judgingid FROM judging WHERE submitid = %i
		               ORDER BY valid DESC, starttime DESC, judgingid DESC LIMIT 1',
                       $id);
	}

	// print the judgings
	foreach( $jdata as $judgingid => $jud ) {

		echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';

		if ( $judgingid == $jid ) {
			echo '<td>&rarr;&nbsp;</td><td>j' . $judgingid . '</td>';
		} else {
			echo '<td>&nbsp;</td><td><a href="submission.php?id=' . $id .
				'&amp;jid=' . $judgingid .  '">j' . $judgingid . '</a></td>';
		}

		echo '<td>' . printtime($jud['starttime']) . '</td>';
		echo '<td><a href="judgehost.php?id=' . urlencode(@$jud['judgehost']) .
			'">' . printhost(@$jud['judgehost']) . '</a></td>';
		echo '<td>' . printresult(@$jud['result'], $jud['valid'], TRUE) . '</td>';
		echo "</tr>\n";

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
			'ignore this submission" onclick="return confirm(\'Really ' . $unornot . 
			'ignore this submission?\');" /></form>' .
			"\n";
}

echo "</td></tr>\n</table>\n\n";


// Display the details of the selected judging

if ( isset($jid) )  {

	$jud = $DB->q('TUPLE SELECT *, judgingid AS ARRAYKEY FROM judging
	               WHERE judgingid = %i', $jid);

	echo "<h2>Judging j" . (int)$jud['judgingid'] .
		($jud['valid'] == 1 ? '' : ' (INVALID)') . "</h2>\n\n";

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
			echo '; <input type="submit" value="' .
					($val ? '' : 'un') . 'mark verified"' .
					" />\n";
			if ( $val ) {
				echo "by " .addInput('verifier_typed', '', 10, 15);
				$verifiers = $DB->q('COLUMN SELECT DISTINCT verifier FROM judging
									 WHERE verifier IS NOT NULL AND verifier != ""
									 ORDER BY verifier');
				if ( count($verifiers) > 0 ) {
					$opts = array(0 => "");
					$opts = array_merge($verifiers, $opts);
					$default = null;
					if ( in_array($lastverifier,$verifiers) ) {
						$default = $lastverifier;
					}
					echo "or " .addSelect('verifier_selected', $opts, $default);
				}
			}
			
			echo "</p>" . addEndForm();
		} else {
			echo "</p>\n";
		}
	}

	echo '<p>Go to output of ' .
		'<a href="#compile">compile</a>, ' .
		'<a href="#run">run</a>, ' .
		'<a href="#diff">diff</a> or ' .
		'<a href="#error">error</a>.' . "</p>\n\n";

	echo "<h3><a name=\"compile\"></a>Output compile</h3>\n\n";

	if ( @$jud['output_compile'] ) {
		echo "<pre class=\"output_text\">".
			htmlspecialchars($jud['output_compile'])."</pre>\n\n";
	} else {
		echo "<p><em>There were no compiler errors or warnings.</em></p>\n";
	}

	echo "<h3><a name=\"run\"></a>Output run</h3>\n\n";

	if ( @$jud['output_run'] ) {
		echo "<pre class=\"output_text\">".
			htmlspecialchars($jud['output_run'])."</pre>\n\n";
	} else {
		echo "<p><em>There was no program output.</em></p>\n";
	}

	echo "<h3><a name=\"diff\"></a>Output diff</h3>\n\n";

	if ( @$jud['output_diff'] ) {
		echo "<pre class=\"output_text\">";
		echo parseDiff($jud['output_diff']);
		echo "</pre>\n\n";
	} else {
		echo "<p><em>There was no diff output.</em></p>\n";
	}

	echo "<h3><a name=\"error\"></a>Output stderr (info/debug/errors)</h3>\n\n";

	if ( @$jud['output_error'] ) {
		echo "<pre class=\"output_text\">".
			htmlspecialchars($jud['output_error'])."</pre>\n\n";
	} else {
		echo "<p><em>There was no stderr output.</em></p>\n";
	}
	
	} // if ($judging_ended)


	// Time (start, end, used)
	echo "<p class=\"judgetime\">Started: " . htmlspecialchars($jud['starttime']);

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
}

// We're done!

require(SYSTEM_ROOT . '/lib/www/footer.php');
