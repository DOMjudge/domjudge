<?php

/**
 * Gives a team the details of a judging of their submission: errors etc.
 *
 * $Id$
 */

require('init.php');
$title = 'Submission details';
include('../header.php');

$sid = (int)$_GET['id'];


// select also on teamid so we can only select our own submissions
$row = $DB->q('MAYBETUPLE SELECT p.probid, p.name as probname, submittime,
               l.name as langname, result, output_compile FROM judging j
               LEFT JOIN submission s USING(submitid)
               LEFT JOIN language l USING (langid)
               LEFT JOIN problem p ON(p.probid=s.probid)
               WHERE j.submitid = %i AND team = %s AND valid = 1',$sid,$login);

if( ! $row ) {
	echo "Submission not found for this team.\n";
	include('../footer.php');
	exit;
}

// verwijder event
$DB->q("DELETE FROM `team_unread`"
	. " WHERE `mesgid` = %i"
	. "   AND `type` = 'SUBMISSION'"
	. "   AND `team` = %s"
	, $sid
	, $login
	);

?>
<h1>Submission details</h1>

<table>
<tr><td>Problem:</td><td><?=htmlentities($row['probname'].' ['.$row['probid'].']')?></td></tr>
<tr><td>Submittime:</td><td><?=printtime($row['submittime'])?></td></tr>
<tr><td>Language:</td><td><?=htmlentities($row['langname'])?></td></tr>
</table>

<p>Status: <?=printresult($row['result'], TRUE, TRUE)?></p>
<?php

if ( (SHOW_COMPILE == 2) ||
     (SHOW_COMPILE == 1 && $row['result'] == 'compiler-error') ) {
	
	echo "<h2>Compiler output:</h2>\n\n";

	if(@$row['output_compile']) {
		echo "<pre class=\"output_text\">\n".
			htmlspecialchars(@$row['output_compile'])."\n</pre>\n\n";
	} else {
		echo "<p><em>There were no compiler errors or warnings.</em></p>\n";
	}
}

include('../footer.php');
