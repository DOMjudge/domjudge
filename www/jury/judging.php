<?php
/**
 * View the details of a specific judging
 *
 * $Id$
 */

require('init.php');
$title = 'Judging';
require('../header.php');
require('menu.php');

$id = (int)$_GET['id'];
if(!$id)	error ("Missing judging id");

echo "<h1>Judging $id</h1>\n\n";

$jdata = $DB->q('TUPLE SELECT j.*,s.*,judger.name as judgename, c.contestname
	FROM judging j LEFT JOIN submission s USING(submitid)
	LEFT JOIN judger USING(judgerid) LEFT JOIN contest c ON(c.cid=j.cid)
	WHERE judgingid = %i',
	$id);

$unix_start = strtotime($jdata['starttime']);
$sec_queued = $unix_start - strtotime($jdata['submittime']);
if(@$jdata['endtime']) {
	$endtime = htmlspecialchars($jdata['endtime']). ' (judging took '.
		(strtotime($jdata['endtime']) - $unix_start) .' s)';
} else {
	$endtime = 'still judging - busy '.(time()-$unix_start). ' s';
}

?>
<table>
<tr><td>Submission:</td><td>
<a href="submission.php?id=<?=(int)$jdata['submitid'].'"><span class="teamid">'.
	$jdata['team'].	'</span> / '. htmlspecialchars($jdata['probid'].' / '.$jdata['langid'])?></a>
	in <?=htmlentities($jdata['contestname'])?></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($jdata['submittime']) .' (queued for '.
	$sec_queued.' s)'?></td></tr>
<tr><td>Source:</td><td class="filename"><a href="show_source.php?id=<?=
	(int)$jdata['submitid']?>"><?= htmlspecialchars($jdata['sourcefile']) ?></a></td></tr>
<tr><td>Start:</td><td><?=htmlspecialchars($jdata['starttime'])?></td></tr>
<tr><td>End:</td><td><?=$endtime?></td></tr>
<tr><td>Judger:</td><td><a href="judger.php?id=<?=(int)$jdata['judgerid'].'">'.
	printhost($jdata['judgename']).' / '.(int)$jdata['judgerid']?></a></td></tr>
<tr><td>Result:</td><td><?=printresult(@$jdata['result'], $jdata['valid'])?></td></tr>
<tr><td>Valid:</td><td><?=printyn($jdata['valid'])?></td></tr>
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

<h3>Output error</h3>

<?php
if(@$jdata['output_error']) {
	echo "<pre class=\"output_text\">".
		htmlspecialchars(@$jdata['output_error'])."</pre>\n\n";
} else {
	echo "<p><em>There was no error output.</em></p>\n";
}
?>

<?php
require('../footer.php');
