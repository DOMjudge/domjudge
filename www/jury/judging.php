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

if ( isset($_POST['cmd']) ) {
	if ( $_POST['cmd'] == 'verified' || $_POST['cmd'] == 'deverified' ) {
		$DB->q('UPDATE judging SET verified = %i WHERE judgingid = %i',
		       ($_POST['cmd'] == 'verified' ? 1 : 0), $id);
	}
}

$jdata = $DB->q('TUPLE SELECT j.*,s.*,t.*, c.contestname
	FROM judging j
	LEFT JOIN submission s USING(submitid)
	LEFT JOIN team t ON(t.login=s.team)
	LEFT JOIN contest c ON(c.cid=j.cid)
	WHERE judgingid = %i', $id);

$sid = (int)$jdata['submitid'];

require('../header.php');
require('menu.php');

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
	$jdata['team'].	'</span> / '. htmlspecialchars($jdata['probid'].' / '.$jdata['langid'])?></a></td></tr>
<tr><td>Contest:</td><td><?=htmlentities($jdata['contestname'])?></td></tr>
<tr><td>Team:</td><td><a href="team.php?id=<?=urlencode($jdata['team']).
	'"><span class="teamid">'. htmlspecialchars($jdata['team'])."</span>: ".
	htmlentities($jdata['name'])?></a></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($jdata['submittime']) .' (queued for '.
	printtimediff(strtotime($jdata['submittime']), $unix_start) .
	')'?></td></tr>
<tr><td>Source:</td><td class="filename"><a href="show_source.php?id=<?=
	$sid?>"><?= htmlspecialchars($jdata['sourcefile']) ?></a></td></tr>
<tr><td>Start:</td><td><?=htmlspecialchars($jdata['starttime'])?></td></tr>
<tr><td>End:</td><td><?=$endtime?></td></tr>
<tr><td>Judger:</td><td><a href="judger.php?id=<?=urlencode($jdata['judgerid']).'">'.
	printhost($jdata['judgerid'])?></a></td></tr>
<tr><td>Result:</td><td><?=printresult(@$jdata['result'], $jdata['valid'])?></td></tr>
<tr><td>Valid:</td><td><?=printyn($jdata['valid'])?></td></tr>
<tr><td>Verified:</td><td><?=printyn($jdata['verified'])?></td></tr>
</table>

<?php

if ( SUBM_VERIFY ) {
	$cmd = ( $jdata['verified'] == 1 ? 'deverified' : 'verified' );
?>
<form action="<?= $pagename.'?id='.$id ?>" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="<?=$cmd?>" />
<input type="submit" value="<?= ($cmd=='verified' ? '' : 'un')?>mark verified"<?=
	( ! @$jdata['endtime'] ? ' disabled="disabled"' : '' )?> />
</p>
</form>
	
<?php
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
