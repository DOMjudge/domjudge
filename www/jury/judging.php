<?php
/**
 * View the details of a specific judging
 *
 * $Id$
 */

require('init.php');
$title = 'Judging';
require('../header.php');

$id = (int)$_GET['id'];
if(!$id)	error ("Missing judging id");

echo "<h1>Judging $id</h1>\n\n";

$jdata = $DB->q('TUPLE SELECT j.*,s.*,judger.name as judgename
	FROM judging j LEFT JOIN submission s USING(submitid)
	LEFT JOIN judger ON(j.judger=judger.judgerid)
	WHERE judgingid = %i',
	$id);
	
?>

<table>
<tr><td>Team:</td><td><a href="team.php?id=<?=$jdata['team'].'">'. $jdata['team']?></a></td></tr>
<tr><td>Problem:</td><td><?= htmlentities($jdata['probid'])?></td></tr>
<tr><td>Language:</td><td><?= htmlentities($jdata['langid'])?></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($jdata['submittime']) ?></td></tr>
<tr><td>Source:</td><td><tt><?= htmlspecialchars($jdata['source']) ?></tt></td></tr>
<tr><td>Start:</td><td><?=$jdata['starttime']?></td></tr>
<tr><td>End:</td><td><?=$jdata['endtime']?></td></tr>
<tr><td>Judger:</td><td><?=$jdata['judgename'].'/'.$jdata['judger']?></td></tr>
<tr><td>Result:</td><td><?=$jdata['result']?></td></tr>
<tr><td>Valid:</td><td><?=$jdata['valid']?></td></tr>
</table>


<h3>Output compile</h3>

<?php
if(@$jdata['output_compile']) {
	echo "<pre class=\"output_text\">".
		htmlspecialchars(@$jdata['output_compile'])."</pre>\n\n";
} else {
	echo "<p><em>There were no compiler errors or warnings.</em></p>\n";
}
?>


<h3>Output run</h3>

<?php
if(@$jdata['output_run']) {
	echo "<pre class=\"output_text\">".
		htmlspecialchars(@$jdata['output_run'])."</pre>\n\n";
} else {
	echo "<p><em>There were no runtime errors or warnings.</em></p>\n";
}
?>


<h3>Output diff</h3>

<?php
if(@$jdata['output_diff']) {
	echo "<pre class=\"output_text\">".
		htmlspecialchars(@$jdata['output_diff'])."</pre>\n\n";
} else {
	echo "<p><em>There was no diff output.</em></p>\n";
}
?>


<?php
require('../footer.php');
