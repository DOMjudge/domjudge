<?php
/**
 * Gives a team the details of a judging of their submission: errors etc.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Submission details';
include('../header.php');

$sid = (int)@$_GET['id'];


// select also on teamid so we can only select our own submissions
$row = $DB->q('MAYBETUPLE SELECT p.probid, p.name AS probname, submittime,
               l.name AS langname, result, output_compile FROM judging j
               LEFT JOIN submission s USING (submitid)
               LEFT JOIN language   l USING (langid)
               LEFT JOIN problem    p ON (p.probid = s.probid)
               WHERE j.submitid = %i AND teamid = %s AND valid = 1',$sid,$login);

if( ! $row ) {
	echo "Submission not found for this team.\n";
	include('../footer.php');
	exit;
}

// remove event of new submission
// does not work yet
//$DB->q('DELETE FROM team_unread
//        WHERE mesgid = %i AND type = "submission" AND teamid = %s', $sid, $login);

?>
<h1>Submission details</h1>

<table>
<tr><td scope="row">Problem:</td>
	<td><?=htmlspecialchars($row['probname'].' ['.$row['probid'].']')?></td></tr>
<tr><td scope="row">Submittime:</td>
	<td><?=printtime($row['submittime'])?></td></tr>
<tr><td scope="row">Language:</td>
	<td><?=htmlspecialchars($row['langname'])?></td></tr>
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
