<?php

/**
 * Gives a team the details of a judging of their submission: errors etc.
 *
 * $Id$
 */

require('init.php');
$title = 'Submission details';
include('../header.php');

$sid = (int)$_GET['submitid'];

// select also on teamid so we can only select our own submissions
$row = $DB->q('MAYBETUPLE SELECT probid,submittime,langid,result,output_compile
	FROM judging j LEFT JOIN submission s USING(submitid)
	WHERE j.submitid = %i AND team = %s AND valid = 1',
	$sid, $login);

if(!$row) {
	echo "Submission not found for this team.\n";
	include('../footer.php');
	exit;
}
?>
<h1>Submission details</h1>

<p>
<table>
<tr><td>Problem:</td><td><?=$row['probid']?></td></tr>
<tr><td>Submittime:</td><td><?=printtime($row['submittime'])?></td></tr>
<tr><td>Language:</td><td><?=$row['langid']?></td></tr>
</table>

<p>Status:
<span class="<?=($row['result'] == 'correct'?'sol-correct':'sol-incorrect')?>"><?=$row['result']?></span></p>

<h2>Compiler output:</h2>
<?
if(@$row['output_compile']) {
	echo "<pre class=\"errors\">\n".
		htmlspecialchars(@$row['output_compile'])."\n</pre>\n\n";
} else {
	echo "<p><em>There were no compiler errors or warnings.</em></p>\n";
}

include('../footer.php');
