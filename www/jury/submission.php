<?php
/**
 * View the details of a specific submission
 *
 * $Id$
 */

$id = (int)$_REQUEST['id'];

require('init.php');
$title = 'Submission s'.@$id;
require('../header.php');
require('menu.php');

if ( ! $id ) error ("Missing submission id");

$iscorrect = (bool) $DB->q('VALUE SELECT count(judgingid) FROM judging WHERE submitid = %i
	AND valid = 1 AND result = "correct"', $id);

if ( isset($_POST['cmd']) && $_POST['cmd'] == 'rejudge' ) {
	if ( $iscorrect ) error("Submission already judged as valid, not rejudging.");

	$DB->q('UPDATE judging SET valid = 0 WHERE submitid = %i', $id);
	$DB->q('UPDATE submission SET judgerid = NULL, judgemark = NULL WHERE submitid = %i', $id);
}

echo "<h1>Submission s$id</h1>\n\n";

$submdata = $DB->q('MAYBETUPLE SELECT s.team,s.probid,s.langid,s.submittime,s.sourcefile,
	t.name as teamname, l.name as langname, p.name as probname, c.contestname
	FROM submission s LEFT JOIN team t ON (t.login=s.team)
	LEFT JOIN problem p ON (p.probid=s.probid) LEFT JOIN language l ON (l.langid=s.langid)
	LEFT JOIN contest c ON (c.cid = s.cid)
	WHERE submitid = %i', $id);

if ( ! $submdata ) error ("Missing submission data");
?>

<table>
<tr><td>Contest:</td><td><?=htmlentities($submdata['contestname'])?></td></tr>
<tr><td>Team:</td><td><a href="team.php?id=<?=urlencode($submdata['team']).
	'"><span class="teamid">'. htmlspecialchars($submdata['team'])."</span>: ".
	htmlentities($submdata['teamname'])?></a></td></tr>
<tr><td>Problem:</td><td><a href="problem.php?id=<?=$submdata['probid'].'">'.
	htmlentities($submdata['probid'].": ".$submdata['probname'])?></a></td></tr>
<tr><td>Language:</td><td><a href="language.php?id=<?=$submdata['langid'].'">'.
	htmlentities($submdata['langid'].": ".$submdata['langname'])?></a></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($submdata['submittime']) ?></td></tr>
<tr><td>Source:</td><td class="filename"><a href="show_source.php?id=<?=$id?>"><?= htmlspecialchars($submdata['sourcefile']) ?></a></td></tr>
</table>

<h3>Judgings</h3>

<?php
getJudgings('submitid', $id);

?>

<form action="submission.php" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="rejudge" />
<input type="submit" value=" Rejudge Me! " <?=($iscorrect?'disabled="disabled "':'')?>/>
</p>
</form>

<?php

require('../footer.php');
