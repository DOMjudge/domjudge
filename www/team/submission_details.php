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
include(LIBWWWDIR . '/header.php');

$sid = (int)@$_GET['id'];


// select also on teamid so we can only select our own submissions
$row = $DB->q('MAYBETUPLE SELECT p.probid, p.name AS probname, submittime,
               s.valid, l.name AS langname, result, output_compile
			   FROM judging j
               LEFT JOIN submission s USING (submitid)
               LEFT JOIN language   l USING (langid)
               LEFT JOIN problem    p ON (p.probid = s.probid)
               WHERE j.submitid = %i AND teamid = %s AND j.valid = 1',$sid,$login);

if( ! $row ) {
	echo "<p>Submission not found for this team.</p>\n";
	include(LIBWWWDIR . '/footer.php');
	exit;
}

?>
<h1>Submission details</h1>

<?
if( ! $row['valid'] ) {
?>
<p>This submission is being ignored.<br />
It is not used in determining your score.
</p>
<?
}
?>

<table>
<tr><td scope="row">Problem:</td>
	<td><?=htmlspecialchars($row['probname'])?> [<span class="probid"><?=
	htmlspecialchars($row['probid']) ?></span>]</td></tr>
<tr><td scope="row">Submitted:</td>
	<td><?=printtime($row['submittime'])?></td></tr>
<tr><td scope="row">Language:</td>
	<td><?=htmlspecialchars($row['langname'])?></td></tr>
</table>

<p>Result: <?=printresult($row['result'], TRUE)?></p>
<?php

if ( (SHOW_COMPILE == 2) ||
     (SHOW_COMPILE == 1 && $row['result'] == 'compiler-error') ) {
	 
	echo "<h2>Compilation output</h2>\n\n";

	if(@$row['output_compile']) {
		echo "<pre class=\"output_text\">\n".
			htmlspecialchars(@$row['output_compile'])."\n</pre>\n\n";

		echo "<p><em>Compilation " .
			( $row['result']=='compiler-error' ? 'failed' : 'successful' ) .
			"</em></p>\n";
	} else {
		echo "<p><em>There were no compiler errors or warnings.</em></p>\n";
	}
} else {
	echo "<p><em>Compilation output is disabled.</em></p>\n";
}

include(LIBWWWDIR . '/footer.php');
