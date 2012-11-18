<?php
/**
 * Gives a team the details of a problem.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Problem details';
require(LIBWWWDIR . '/header.php');

$pid = @$_GET['id'];

// select also on teamid so we can only select our own submissions
$name = $DB->q('MAYBEVALUE SELECT name FROM problem p WHERE probid=%s AND allow_submit = 1', $pid);

if( ! $name ) {
	echo "<p>Problem " . $pid . " not found.</p>\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

echo "<h1>Problem details</h1>\n";

$solved = $DB->q('VALUE SELECT COUNT(*)
		FROM scoreboard_public
		WHERE probid = %s AND is_correct = 1', $pid);
$unsolved = $DB->q('VALUE SELECT COUNT(*)
		FROM scoreboard_public
		WHERE probid = %s AND is_correct = 0', $pid);
$ratio = sprintf("%3.3lf", ($solved / ($solved + $unsolved)));

$sample_string = "";
$samples = $DB->q("SELECT testcaseid, description FROM testcase
                   WHERE probid=%s AND sample=1 AND description IS NOT NULL
                   ORDER BY rank", $pid);
if ( $samples->count() == 0) {
	$sample_string = '<span class="nodata">no public samples</span>';
} else {
	while ( $sample = $samples->next() ) {
		$sample_string .= ' <a href="sample.php?in=1id=' . $sample['testcaseid'] . '">' . $sample['description'] . '.in<a>';
		$sample_string .= ' <a href="sample.php?in=0id=' . $sample['testcaseid'] . '">' . $sample['description'] . '.out<a>';
	}
}

?>

<table>
<tr><th scope="row">problem:</th>
	<td><?php echo htmlspecialchars($name)?> [<span class="probid"><?php echo
	htmlspecialchars($pid) ?></span>]</td></tr>
<tr><th scope="row">description:</th>
	<td><a href="problem.php?id=<?= urlencode($pid) ?>"><img src="../images/pdf.gif" alt="pdf"/></a></td></tr>
<tr><th scope="row">sample:</th>
	<td><?= $sample_string ?></td></tr>
<tr><th scope="row">#users - solved:</th>
	<td><?php echo $solved ?></td></tr>
<tr><th scope="row">#users - unsolved:</th>
	<td><?php echo $unsolved ?></td></tr>
<tr><th scope="row">ratio:</th>
	<td><?php echo $ratio ?></td></tr>
<tr><th scope="row"><a href="index.php?id=<?= urlencode($pid) ?>#submit">submit</a></th>
	<td/></tr>
<tr><th scope="row"><a href="clarification.php?pid=<?= urlencode($pid) ?>">request clarification</a></th>
	<td/></tr>
</table>

<?php

echo "<h3 class=\"teamoverview\"><a name=\"own\" href=\"#own\">own submissions</a></h3>\n\n";
$restrictions = array( 'probid' => $pid, 'teamid' => $login );
putSubmissions($cdata, $restrictions);
?>
<div style="text-align:center;">
	<span id="showsubs" style="display:none;color:#50508f;font-weight:bold;" onclick="showAllSubmissions(true)">all submissions</span>
</div>
<?php

echo "<h3 class=\"teamoverview\"><a name=\"correct\" href=\"#correct\">correct submissions (from all users)</a></h3>\n\n";
$restrictions = array( 'probid' => $pid, 'correct' => TRUE );
putSubmissions($cdata, $restrictions);
?>
<div style="text-align:center;">
	<span id="showsubs2" style="display:none;color:#50508f;font-weight:bold;" onclick="showAllSubmissions(true)">all submissions</span>
</div>
<script language="javascript">
	function showAllSubmissions(show) {
		var css = document.createElement("style");
		css.type = "text/css";
		showsubs = document.getElementById('showsubs');
		showsubs2 = document.getElementById('showsubs2');
		if (show) {
			showsubs.style.display = "none";
			showsubs2.style.display = "none";
			css.innerHTML = ".old { display: table-row; }";
		} else {
			showsubs.style.display = "inline";
			showsubs2.style.display = "inline";
			css.innerHTML = ".old { display: none; }";
		}
		document.body.appendChild(css);
	}
	showAllSubmissions(false);
</script> 
<?php

require(LIBWWWDIR . '/footer.php');
