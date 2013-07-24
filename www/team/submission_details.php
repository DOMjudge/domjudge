<?php
/**
 * Gives a team the details of a judging of their submission: errors etc.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Submission details';
require(LIBWWWDIR . '/header.php');

$sid = (int)@$_GET['id'];


// select also on teamid so we can only select our own submissions
$row = $DB->q('MAYBETUPLE SELECT p.probid, p.name AS probname, submittime,
               s.valid, l.name AS langname, result, output_compile, verified
               FROM judging j
               LEFT JOIN submission s USING (submitid)
               LEFT JOIN language   l USING (langid)
               LEFT JOIN problem    p ON (p.probid = s.probid)
               WHERE j.submitid = %i AND teamid = %s AND j.valid = 1',$sid,$teamid);

if( ! $row || (dbconfig_get('verification_required',0) && !$row['verified']) ) {
	echo "<p>Submission not found for this team or not judged yet.</p>\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

// update seen status when viewing submission
$DB->q("UPDATE judging j SET j.seen = 1 WHERE j.submitid = %i", $sid);

echo "<h1>Submission details</h1>\n";

if( ! $row['valid'] ) {
	echo "<p>This submission is being ignored.<br />\n" .
		"It is not used in determining your score.</p>\n\n";
}
?>

<table>
<tr><td>Problem:</td>
	<td><?php echo htmlspecialchars($row['probname'])?> [<span class="probid"><?php echo
	htmlspecialchars($row['probid']) ?></span>]</td></tr>
<tr><td>Submitted:</td>
	<td><?php echo printtime($row['submittime'])?></td></tr>
<tr><td>Language:</td>
	<td><?php echo htmlspecialchars($row['langname'])?></td></tr>
</table>

<p>Result: <?php echo printresult($row['result'], TRUE)?></p>
<?php

$show_compile = dbconfig_get('show_compile', 2);

if ( ( $show_compile == 2 ) ||
     ( $show_compile == 1 && $row['result'] == 'compiler-error') ) {

	echo "<h2>Compilation output</h2>\n\n";

	if ( strlen(@$row['output_compile']) > 0 ) {
		echo "<pre class=\"output_text\">\n".
			htmlspecialchars(@$row['output_compile'])."\n</pre>\n\n";
	} else {
		echo "<p class=\"nodata\">There were no compiler errors or warnings.</p>\n";
	}

	if ( $row['result'] == 'compiler-error' ) {
		echo "<p class=\"compilation-error\">Compilation failed.</p>\n";
	} else {
		echo "<p class=\"compilation-success\">Compilation successful.</p>\n";
	}
} else {
	echo "<p class=\"nodata\">Compilation output is disabled.</p>\n";
}

require(LIBWWWDIR . '/footer.php');
