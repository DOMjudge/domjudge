<?php
/**
 * View the details of a specific submission
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = (int)$_REQUEST['id'];
if ( !empty($_GET['jid']) ) $jid = (int)$_GET['jid'];

require('init.php');
$title = 'Submission s'.@$id;

if ( ! $id ) error("Missing or invalid submission id");

$submdata = $DB->q('MAYBETUPLE SELECT s.teamid, s.probid, s.langid, s.submittime,
                    s.sourcefile, c.cid, c.contestname,
                    t.name AS teamname, l.name AS langname, p.name AS probname
                    FROM submission s
                    LEFT JOIN team     t ON (t.login  = s.teamid)
                    LEFT JOIN problem  p ON (p.probid = s.probid)
                    LEFT JOIN language l ON (l.langid = s.langid)
                    LEFT JOIN contest  c ON (c.cid    = s.cid)
                    WHERE submitid = %i', $id);

if ( ! $submdata ) error ("Missing submission data");

require('../header.php');

echo "<h1>Submission s".$id."</h1>\n\n";

$jdata = $DB->q('KEYTABLE SELECT *, judgingid AS ARRAYKEY FROM judging
                 WHERE cid = %i AND submitid = %i
                 ORDER BY starttime ASC, judgingid ASC',
                 getCurContest(), $id);

?>
<table width="100%">
<tr><td valign="top">
<table>
<caption>Submission</caption>
<tr><td scope="row">Contest:</td><td><?=htmlentities($submdata['contestname'])?></td></tr>
<tr><td scope="row">Team:</td><td>
	<a href="team.php?id=<?=urlencode($submdata['teamid'])?>">
	<span class="teamid"><?=htmlspecialchars($submdata['teamid'])?></span>: 
	<?=htmlentities($submdata['teamname'])?></a></td></tr>
<tr><td scope="row">Problem:</td><td>
	<a href="problem.php?id=<?=$submdata['probid']?>">
	<?=htmlentities($submdata['probid'].": ".$submdata['probname'])?></a></td></tr>
<tr><td scope="row">Language:</td><td>
	<a href="language.php?id=<?=$submdata['langid']?>">
	<?=htmlentities($submdata['langname'])?></a></td></tr>
<tr><td scope="row">Submittime:</td><td><?= htmlspecialchars($submdata['submittime']) ?></td></tr>
<tr><td scope="row">Source:</td><td class="filename">
	<a href="show_source.php?id=<?=$id?>">
	<?=htmlspecialchars($submdata['sourcefile'])?></a></td></tr>
</table>


</td><td valign="top">

<?php

if ( count($jdata) > 0 ) { 
	echo "<table class=\"list\">\n" .
		"<caption>Judgings</caption>\n<thead>\n" .
		"<tr><td></td><th scope=\"col\">ID</th><th scope=\"col\">start</th>" .
		"<th scope=\"col\">judgehost</th><th scope=\"col\">result</th>" .
		"<th scope=\"col\">valid</th></tr>\n</thead>\n<tbody>\n";

	// when there's no judging selected through the request, we find
	// out what the best one should be. The valid one, or else the most
	// recent invalid one.
	if ( ! isset($jid) ) {
		$jid = $DB->q('VALUE SELECT judgingid FROM judging WHERE submitid = %i
		               ORDER BY valid DESC, starttime DESC, judgingid DESC LIMIT 1',
                       $id);
	}

	// print the judgings
	foreach( $jdata as $jud ) {

		echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';

		if ( $jud['judgingid'] == $jid ) {
			echo '<td>&rarr;&nbsp;</td><td>j' . (int)$jud['judgingid'] . '</td>';
		} else {
			echo '<td>&nbsp;</td><td><a href="submission.php?id=' . $id .
				'&amp;jid=' . (int)$jud['judgingid'] .  '">j' .
				(int)$jud['judgingid'] .  '</a></td>';
		}

		echo '<td>' . printtime($jud['starttime']) . '</td>';
		echo '<td><a href="judgehost.php?id=' . urlencode(@$jud['judgehost']) .
			'">' . printhost(@$jud['judgehost']) . '</a></td>';
		echo '<td>' . printresult(@$jud['result'], $jud['valid'], TRUE) . '</td>';
		echo '<td align="center">' . printyn($jud['valid']) . '</td>';
		echo "</tr>\n";

	}
    echo "</tbody>\n</table>\n\n";

	echo "<br />\n" . rejudgeForm('submission', $id);
	
} else {
	echo "<em>Not judged yet</em>";
}

echo "</td></tr>\n</table>\n\n";


// Display the details of the selected judging

if ( isset($jid) )  {

	$jud = $jdata[$jid];

	echo "<h2>Judging j" . (int)$jud['judgingid'] .
		($jud['valid'] == 1 ? '' : ' (INVALID)') . "</h2>\n\n";

	// display verification data: verified, and by whom.
	// only if this is a valid judging, otherwise irrelevant
	if ( $jud['valid'] ) {
		if ( ! (VERIFICATION_REQUIRED && $jud['verified']) ) {

			require_once('../forms.php');

			$val = ! $jud['verified'];

			echo addForm('verify.php') .
				addHidden('id',  $jud['judgingid']) .
				addHidden('val', $val);
		}

		echo "<p>Verified: " .
			"<strong>" . printyn($jud['verified']) . "</strong>";
		if ( $jud['verified'] && ! empty($jud['verifier']) ) {
			echo ", by " . htmlentities($jud['verifier']);
		}

		if ( ! (VERIFICATION_REQUIRED && $jud['verified']) ) {
			echo '; <input type="submit" value="' .
					($val ? '' : 'un') . 'mark verified"' .
					( ! @$jud['endtime'] ? ' disabled="disabled"' : '' ) .
					" />\n";
			if ( $val ) {
				echo "by " .
					addInput('verifier_typed', '', 10, 15);
				$verifiers = $DB->q('COLUMN SELECT DISTINCT verifier FROM judging
									 WHERE verifier IS NOT NULL AND verifier != ""
									 ORDER BY verifier');
				if ( count($verifiers) > 0 ) {
					$opts = array(0 => "");
					$opts = array_merge($verifiers, $opts);
					echo "or " .addSelect('verifier_selected', $opts);
				}
			}
			
			echo "</p>" . addEndForm();
		} else {
			echo "</p>\n";
		}
	}


	echo "<h3>Output compile</h3>\n\n";

	if ( @$jud['output_compile'] ) {
		echo "<pre class=\"output_text\">".
			htmlspecialchars($jud['output_compile'])."</pre>\n\n";
	} else {
		echo "<p><em>There were no compiler errors or warnings.</em></p>\n";
	}

	echo "<h3>Output run</h3>\n\n";

	if ( @$jud['output_run'] ) {
		echo "<pre class=\"output_text\">".
			htmlspecialchars($jud['output_run'])."</pre>\n\n";
	} else {
		echo "<p><em>There was no program output.</em></p>\n";
	}

	echo "<h3>Output diff</h3>\n\n";

	if ( @$jud['output_diff'] ) {
		echo "<pre class=\"output_text\">".
			htmlspecialchars($jud['output_diff'])."</pre>\n\n";
	} else {
		echo "<p><em>There was no diff output.</em></p>\n";
	}

	echo "<h3>Output error</h3>\n\n";

	if ( @$jud['output_error'] ) {
		echo "<pre class=\"output_text\">".
			htmlspecialchars($jud['output_error'])."</pre>\n\n";
	} else {
		echo "<p><em>There was no error output.</em></p>\n";
	}
	
	// Time (start, end, used)

	echo "<p class=\"judgetime\">Started: " . htmlspecialchars($jud['starttime']);

	$unix_start = strtotime($jud['starttime']);
	if ( !empty($jud['endtime']) ) {
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

require('../footer.php');
