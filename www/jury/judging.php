<?php
/**
 * View the details of a specific judging
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = (int)$_REQUEST['id'];

require('init.php');
$title = 'Judging j'.@$id;

if ( ! $id ) error ("Missing judging id");

$jdata = $DB->q('TUPLE SELECT j.*, s.*, t.*, c.contestname FROM judging j
                 LEFT JOIN submission s USING (submitid)
                 LEFT JOIN team    t ON (t.login = s.teamid)
                 LEFT JOIN contest c ON (c.cid = j.cid)
                 WHERE judgingid = %i', $id);

$sid = (int)$jdata['submitid'];

if ( @$_POST['cmd'] == 'verify' ) {
	$verifier = "";
	if ( ! empty($_POST['verifier_selected']) ) $verifier = $_POST['verifier_selected'];
	if ( ! empty($_POST['verifier_typed'])    ) $verifier = $_POST['verifier_typed'];
	
	$DB->q('UPDATE judging SET verified = %i, verifier = %s WHERE judgingid = %i',
	       $_POST['val'], $verifier, $id);
	
	$jdata['verified'] = $_POST['val'];
	$jdata['verifier'] = $verifier;
	
	if ( VERIFICATION_REQUIRED ) {
		calcScoreRow($jdata['cid'], $jdata['teamid'], $jdata['probid']);

		// log to event table if successful (case of no verification
		// required is handled in judge/judgedaemon.php)
		if ( $jdata['result'] == 'correct' ) {
		$DB->q('INSERT INTO event (cid, teamid, langid, probid, submitid, description)
		        VALUES(%i, %i, %s, %s, %i, "problem solved")',
		       $cid, $jdata['teamid'], $jdata['langid'], $jdata['probid'], $jdata['submitid']);
		}
	}
}

require('../header.php');

echo "<h1>Judging j$id / s$sid</h1>\n\n";

$unix_start = strtotime($jdata['starttime']);
if ( @$jdata['endtime'] ) {
	$endtime = htmlspecialchars($jdata['endtime']). ' (judging took '.
		printtimediff($unix_start, strtotime($jdata['endtime']) ) . ')';
} else {
	$endtime = 'still judging - busy ' . printtimediff($unix_start);
}

?>
<table>
<tr><td>Submission:</td><td>
<a href="submission.php?id=<?=$sid.'">s'.$sid.' / <span class="teamid">'.
	$jdata['teamid'].	'</span> / '. htmlspecialchars($jdata['probid'].' / '.$jdata['langid'])?></a></td></tr>
<tr><td>Contest:</td><td><?=htmlentities($jdata['contestname'])?></td></tr>
<tr><td>Team:</td><td><a href="team.php?id=<?=urlencode($jdata['teamid']).
	'"><span class="teamid">'. htmlspecialchars($jdata['teamid'])."</span>: ".
	htmlentities($jdata['name'])?></a></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($jdata['submittime']) .' (queued for '.
	printtimediff(strtotime($jdata['submittime']), $unix_start) .
	')'?></td></tr>
<tr><td>Source:</td><td class="filename"><a href="show_source.php?id=<?=
	$sid?>"><?= htmlspecialchars($jdata['sourcefile']) ?></a></td></tr>
<tr><td>Start:</td><td><?=htmlspecialchars($jdata['starttime'])?></td></tr>
<tr><td>End:</td><td><?=$endtime?></td></tr>
<tr><td>Judgehost:</td><td><a href="judgehost.php?id=<?=urlencode($jdata['judgehost']).'">'.
	printhost($jdata['judgehost'])?></a></td></tr>
<tr><td>Result:</td><td><?=printresult(@$jdata['result'], $jdata['valid'])?></td></tr>
<tr><td>Valid:</td><td><?=printyn($jdata['valid'])?></td></tr>
<tr><td>Verified:</td><td><?=printyn($jdata['verified'])?>
<?php
if ( $jdata['verified'] && ! empty($jdata['verifier']) ) {
	echo ", by " . htmlentities($jdata['verifier']);
} ?></td></tr>
</table>

<?php

if ( ! (VERIFICATION_REQUIRED && $jdata['verified']) ) {
	require_once('../forms.php');

	$val = ! $jdata['verified'];

	echo addForm('judging.php?id=' . $id) .
		addHidden('id',  $id) .
		addHidden('cmd', 'verify') .
		addHidden('val', $val) .
		'<input type="submit" value="' .
			($val ? '' : 'un') . 'mark verified"' .
			( ! @$jdata['endtime'] ? ' disabled="disabled"' : '' ) .
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
}

echo "<h3>Output compile</h3>\n\n";

if(@$jdata['output_compile']) {
	echo "<pre class=\"output_text\">".
		htmlspecialchars(@$jdata['output_compile'])."</pre>\n\n";
} else {
	echo "<p><em>There were no compiler errors or warnings.</em></p>\n";
}

echo "<h3>Output run</h3>\n\n";

if(@$jdata['output_run']) {
	echo "<pre class=\"output_text\">".
		htmlspecialchars(@$jdata['output_run'])."</pre>\n\n";
} else {
	echo "<p><em>There was no program output.</em></p>\n";
}

echo "<h3>Output diff</h3>\n\n";

if(@$jdata['output_diff']) {
	echo "<pre class=\"output_text\">".
		htmlspecialchars(@$jdata['output_diff'])."</pre>\n\n";
} else {
	echo "<p><em>There was no diff output.</em></p>\n";
}

echo "<h3>Output error</h3>\n\n";

if(@$jdata['output_error']) {
	echo "<pre class=\"output_text\">".
		htmlspecialchars(@$jdata['output_error'])."</pre>\n\n";
} else {
	echo "<p><em>There was no error output.</em></p>\n";
}

require('../footer.php');
