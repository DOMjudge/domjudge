<?php
/**
 * View the details of a specific submission
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = (int)$_REQUEST['id'];

require('init.php');
$title = 'Submission '.@$id;

if ( ! $id ) error("Missing or invalid submission id");

$submdata = $DB->q('MAYBETUPLE SELECT s.team, s.probid, s.langid, s.submittime,
                    s.sourcefile, c.cid, c.contestname,
                    t.name AS teamname, l.name AS langname, p.name AS probname
                    FROM submission s
                    LEFT JOIN team     t ON (t.login  = s.team)
                    LEFT JOIN problem  p ON (p.probid = s.probid)
                    LEFT JOIN language l ON (l.langid = s.langid)
                    LEFT JOIN contest  c ON (c.cid    = s.cid)
                    WHERE submitid = %i', $id);

if ( ! $submdata ) error ("Missing submission data");

$iscorrect = (bool)$DB->q('VALUE SELECT count(judgingid) FROM judging WHERE
                           submitid = %i AND valid = 1 AND result = "correct"', $id);

if ( isset($_POST['cmd']) && $_POST['cmd'] == 'rejudge' ) {
	if ( $iscorrect ) error("Submission already judged as valid, not rejudging.");
	rejudge('submission.submitid',$id);
	header('Location: '.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id));
	exit;
}

require('../header.php');

echo "<h1>Submission ".$id."</h1>\n\n";

?>

<table>
<tr><td>Contest:</td><td><?=htmlentities($submdata['contestname'])?></td></tr>
<tr><td>Team:</td><td>
	<a href="team.php?id=<?=urlencode($submdata['team'])?>">
	<span class="teamid"><?=htmlspecialchars($submdata['team'])?></span>: 
	<?=htmlentities($submdata['teamname'])?></a></td></tr>
<tr><td>Problem:</td><td>
	<a href="problem.php?id=<?=$submdata['probid']?>">
	<?=htmlentities($submdata['probid'].": ".$submdata['probname'])?></a></td></tr>
<tr><td>Language:</td><td>
	<a href="language.php?id=<?=$submdata['langid']?>">
	<?=htmlentities($submdata['langid'].": ".$submdata['langname'])?></a></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($submdata['submittime']) ?></td></tr>
<tr><td>Source:</td><td class="filename">
	<a href="show_source.php?id=<?=$id?>">
	<?=htmlspecialchars($submdata['sourcefile'])?></a></td></tr>
</table>

<form action="<?=$pagename?>" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="rejudge" />
<input type="submit" value=" REJUDGE! "
 <?=($iscorrect?'disabled="disabled "':'')?> />
</p>
</form>

<h3>Judgings</h3>

<?php
putJudgings('submitid', $id);

require('../footer.php');
