<?php
/**
 * View the details of a specific submission
 *
 * $Id$
 */

require('init.php');
$title = 'Submissions';
require('../header.php');

$id = (int)$_GET['id'];
if(!$id)	error ("Missing submission id");

echo "<h1>Submission $id</h1>\n\n";

$submdata = $DB->q('TUPLE SELECT s.team,s.probid,s.langid,s.submittime,s.source,
		t.name as teamname, l.name as langname, p.name as probname
	FROM submission s LEFT JOIN team t ON (t.login=s.team)
	LEFT JOIN problem p ON (p.probid=s.probid) LEFT JOIN language l ON (l.langid=s.langid)
	WHERE submitid = %i', $id);
?>

<table>
<tr><td>Team:</td><td><a href="team.php?id=<?=$submdata['team'].'">'. htmlentities($submdata['team'].": ".$submdata['teamname'])?></a></td></tr>
<tr><td>Problem:</td><td><?= htmlentities($submdata['probid'].": ".$submdata['probname'])?></td></tr>
<tr><td>Language:</td><td><?= htmlentities($submdata['langid'].": ".$submdata['langname'])?></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($submdata['submittime']) ?></td></tr>
<tr><td>Source:</td><td><?= htmlspecialchars($submdata['source']) ?></td></tr>
</table>

<h3>Judgings</h3>

<?php
$judgedata = $DB->q('SELECT * FROM judging NATURAL JOIN judger WHERE submitid = %i ORDER BY starttime', $id);
if($judgedata->count() == 0) {
	echo "<em>Submission still queued</em>";
} else {
	echo "<table>\n";
	echo "<tr><th>ID</th><th>start</th><th>end</th><th>judge</th><th>result</th><th>valid</th></tr>\n";
	while($jrow = $judgedata->next()) {
		echo "<tr" . ($jrow['valid'] ? '' : " class=\"invalid\"") .'>';
		echo "<td align=\"right\">".$jrow['judgingid'].
		"</td><td>".printtime($jrow['starttime']).
		"</td><td>".printtime(@$jrow['endtime']).
		"</td><td title=\"".$jrow['judger']."\">".$jrow['name'].
		"</td><td class=\"sol-".(@$jrow['result'] == 'correct' ? 'correct':'incorrect') .
			"\">".@$jrow['result'].
		"</td><td align=\"right\">".$jrow['valid'].
		"</td></tr>\n";

	}
	echo "</table>\n\n";
}



require('../footer.php');
