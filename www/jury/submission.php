<?php
/**
 * View the details of a specific submission
 *
 * $Id$
 */

require('init.php');
$title = 'Submissions';
require('../header.php');

if(isset($_POST['id'])) {
	$id = (int)$_POST['id'];
} else {
	$id = (int)$_GET['id'];
}
if(!$id)	error ("Missing submission id");

if(isset($_POST['cmd']) && $_POST['cmd'] == 'rejudge') {
	$DB->q('UPDATE judging SET valid = 0 WHERE submitid = %i', $id);
	$DB->q('UPDATE submission SET judger = NULL, judgemark = NULL WHERE submitid = %i', $id);
}

echo "<h1>Submission $id</h1>\n\n";

$submdata = $DB->q('TUPLE SELECT s.team,s.probid,s.langid,s.submittime,s.source,
		t.name as teamname, l.name as langname, p.name as probname
	FROM submission s LEFT JOIN team t ON (t.login=s.team)
	LEFT JOIN problem p ON (p.probid=s.probid) LEFT JOIN language l ON (l.langid=s.langid)
	WHERE submitid = %i', $id);
?>

<table>
<tr><td>Team:</td><td><a href="team.php?id=<?=$submdata['team'].'">'. htmlentities($submdata['team'].": ".$submdata['teamname'])?></a></td></tr>
<tr><td>Problem:</td><td><a href="problem.php?id=<?=$submdata['probid'].'">'.
	htmlentities($submdata['probid'].": ".$submdata['probname'])?></a></td></tr>
<tr><td>Language:</td><td><a href="language.php?id=<?=$submdata['langid'].'">'.
	htmlentities($submdata['langid'].": ".$submdata['langname'])?></a></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($submdata['submittime']) ?></td></tr>
<tr><td>Source:</td><td><a href="show_source.php?id=<?=$id?>"><tt><?= htmlspecialchars($submdata['source']) ?></tt></a></td></tr>
</table>

<h3>Judgings</h3>

<?php
$hasfinal = FALSE;

$judgedata = $DB->q('SELECT * FROM judging LEFT JOIN judger ON(judger=judgerid)
	WHERE submitid = %i ORDER BY starttime', $id);

if($judgedata->count() == 0) {
	echo "<em>Submission still queued</em>";
} else {
	echo "<table>\n";
	echo "<tr><th>ID</th><th>start</th><th>end</th><th>judge</th><th>result</th><th>valid</th></tr>\n";
	while($jrow = $judgedata->next()) {
		echo "<tr" . ($jrow['valid'] ? '' : " class=\"invalid\"") .'>';
		echo "<td align=\"right\"><a href=\"judging.php?id=".$jrow['judgingid']."\">".$jrow['judgingid']."</a>".
		"</td><td>".printtime($jrow['starttime']).
		"</td><td>".printtime(@$jrow['endtime']).
		"</td><td title=\"".$jrow['judger']."\">".$jrow['name'].
		"</td><td>".printresult(@$jrow['result']).
		"</td><td align=\"right\">".$jrow['valid'].
		"</td></tr>\n";
		
		if($jrow['valid'] == 1) $hasfinal = TRUE;
	}
	echo "</table>\n\n";
}

?>
<p>
<form action="submission.php" method="post">
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="rejudge" />
<input type="submit" value=" Rejudge Me! " <?=($hasfinal?'':'disabled="1" ')?>/>
</form>

<?php

require('../footer.php');
