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

?>

<table>
<tr><th scope="row">Problem:</th>
	<td><?php echo htmlspecialchars($name)?> [<span class="probid"><?php echo
	htmlspecialchars($pid) ?></span>]</td></tr>
<tr><th scope="row">Solved:</th>
	<td><?php echo $solved ?></td></tr>
<tr><th scope="row">Unsolved:</th>
	<td><?php echo $unsolved ?></td></tr>
<tr><th scope="row">Ratio:</th>
	<td><?php echo $ratio ?></td></tr>
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
