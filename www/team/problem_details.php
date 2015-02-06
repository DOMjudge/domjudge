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
$name = $DB->q('MAYBEVALUE SELECT shortname
		FROM contestproblem
		WHERE probid=%i AND cid=%i AND allow_submit = 1', $pid, $cid);

if( ! $name ) {
	echo "<p>Problem " . $pid . " not found.</p>\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

echo "<h1>Problem details</h1>\n";

$solvedunsolved = $DB->q('KEYVALUETABLE SELECT is_correct, COUNT(*)
		          FROM scorecache_public
		          LEFT JOIN team USING (teamid)
		          LEFT JOIN team_category USING (categoryid)
		          WHERE probid = %i AND enabled = 1 AND visible = 1
		          GROUP BY is_correct',
                          $pid);

$unsolved = isset($solvedunsolved[0]) ? $solvedunsolved[0] : 0;
$solved   = isset($solvedunsolved[1]) ? $solvedunsolved[1] : 0;
$ratio = sprintf("%3.3lf", ($solved / ($solved + $unsolved)));

$samples = $DB->q("SELECT testcaseid, description, rank FROM testcase
                   WHERE probid=%i AND sample=1
                   ORDER BY rank", $pid);
if ( $samples->count() == 0) {
	$sample_string = '<span class="nodata">no public samples</span>';
} else {
	$sample_string = array();
	while ( $sample = $samples->next() ) {
		$filename = htmlspecialchars($name . '_' . $sample['rank']);
		$sample_string[] = ' <a href="sample.php?in=1&id=' . urlencode($sample['testcaseid']) . '">' . $filename . '.in</a>';
		$sample_string[] = ' <a href="sample.php?in=0&id=' . urlencode($sample['testcaseid']) . '">' . $filename . '.out</a>';
	}
	$sample_string = join(' | ', $sample_string);
}

// TODO: don't query these values over and over again but add another table
$verdicts = array('correct', 'run-error', 'timelimit', 'wrong-answer', 'presentation-error', 'no-output');
$cnt = 0;
foreach ($verdicts as $verdict) {
	$verdictCnt[] = "[" . $cnt . ", " . 
		$DB->q('VALUE SELECT COUNT(*)
		FROM judging j
		LEFT JOIN submission s USING (submitid)
		WHERE s.probid=%i
		AND s.valid=1
		AND j.valid=1
		AND j.result=%s
		AND s.teamid!=%i', $pid, $verdict, 'domjudge')
		. "]";
	$verdictId[] = "[" . $cnt . ", '" . $verdict . "']";
	$cnt++;
}
$verdictCnt_string = join(',', $verdictCnt);
$verdict_string = join(',', $verdictId);

$langs = $DB->q('SELECT langid,name FROM language WHERE allow_submit=1');
$cnt = 0;
while ( $lang = $langs->next() ) {
	$langCnt[] = "[" . $cnt . ", " .
		$DB->q('VALUE SELECT COUNT(*)
			FROM submission
			WHERE valid=1
			AND probid=%i
			AND langid=%s
			AND teamid!=%i', $pid, $lang['langid'], 'domjudge')
		. "]";
	$langId[] = "[" . $cnt . ", '" . $lang['name'] . "']";
	$cnt++;
}
$langCnt_string = join(',', $langCnt);
$lang_string = join(',', $langId);

$problemtext_type = $DB->q('MAYBEVALUE SELECT problemtext_type
			    FROM problem
			    WHERE probid=%i', $pid);

?>

<table>
<tr><th scope="row">problem:</th>
	<td><?php echo htmlspecialchars($name)?> [<span class="probid"><?php echo
	htmlspecialchars($pid) ?></span>]</td></tr>
<?php
if ( isset($problemtext_type) ) {
?>
<tr><th scope="row">description:</th>
	<td><a href="problem.php?id=<?= urlencode($pid) ?>">
		<img src="../images/<?= urlencode($problemtext_type) ?>.gif" alt="<?= urlencode($problemtext_type) ?>"/>
		prob-<?= htmlspecialchars($name) ?>.<?= htmlspecialchars($problemtext_type) ?>
	</a></td></tr>
<?php
} // end if problemtext
?>
<tr><th scope="row">sample:</th>
	<td><?= $sample_string ?></td></tr>
<tr><th scope="row">#users - solved:</th>
	<td><?php echo $solved ?></td></tr>
<tr><th scope="row">#users - unsolved:</th>
	<td><?php echo $unsolved ?></td></tr>
<tr><th scope="row">ratio:</th>
	<td><?php echo $ratio ?></td></tr>
</table>

<h3 class="teamoverview">submission graphs</h3>
<div>
<div id="verdicts" style="width:45%;height:200px;float:left;"></div>
<div id="langs" style="width:45%;height:200px;float:right;"></div>
</div>

<script type="text/javascript">
$.plot(
   $("#verdicts"),
   [
    {
      label: null,
      data: [ <?= $verdictCnt_string ?> ],
      bars: {
        show: true,
        barWidth: 0.5,
	lineWidth: 0,
        align: "center"
      }   
    }
 ],
 {
   xaxis: {
     ticks: [ <?= $verdict_string ?> ]
   },
   yaxis: {
     minTickSize: 1,
     tickDecimals: 0
   }
 }
);
$.plot(
   $("#langs"),
   [
    {
      label: null,
      data: [ <?= $langCnt_string ?> ],
      bars: {
        show: true,
        barWidth: 0.5,
	lineWidth: 0,
        align: "center"
      }   
    }
 ],
 {
   xaxis: {
     ticks: [ <?= $lang_string ?> ]
   },
   yaxis: {
     minTickSize: 1,
     tickDecimals: 0
   }
 }
);
</script>

<?php

echo "<br style=\"clear:both;\"/>\n";

echo "<div id=\"submitlist\">\n";

echo "<h3 class=\"teamoverview\">own submissions</h3>\n\n";
$restrictions = array( 'probid' => $pid, 'teamid' => $teamid );
putSubmissions(array($cid => $cdata), $restrictions);
?>
<div style="text-align:center;">
	<span id="showsubs" style="display:none;color:#50508f;font-weight:bold;" onclick="showAllSubmissions(true)">all submissions</span>
</div>
<?php

echo "<h3 class=\"teamoverview\">correct submissions (from all users)</h3>\n\n";
$restrictions = array( 'probid' => $pid, 'correct' => 1 );
putSubmissions(array($cid => $cdata), $restrictions);
?>
<div style="text-align:center;">
	<span id="showsubs2" style="display:none;color:#50508f;font-weight:bold;" onclick="showAllSubmissions(true)">all submissions</span>
</div>

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

echo "<div id=\"clarlist\">\n";

$requests = $DB->q('SELECT * FROM clarification
                    WHERE cid = %i AND sender = %s AND probid = %s
                    ORDER BY submittime DESC, clarid DESC', $cid, $login, $pid);

$clarifications = $DB->q('SELECT c.* FROM clarification c
                          LEFT JOIN team_unread u ON
                          (c.clarid=u.mesgid AND u.teamid = %i)
                          WHERE c.cid = %i AND c.sender IS NULL
                          AND ( c.recipient IS NULL OR c.recipient = %i )
			  AND probid = %i
                          ORDER BY c.submittime DESC, c.clarid DESC',
                          $teamid, $cid, $teamid, $pid);

echo "<h3 class=\"teamoverview\">Clarifications</h3>\n";

# FIXME: column width and wrapping/shortening of clarification text 
if ( $clarifications->count() == 0 ) {
	echo "<p class=\"nodata\">No clarifications.</p>\n\n";
} else {
	putClarificationList($clarifications,$login);
}

echo "<h3 class=\"teamoverview\">Clarification Requests</h3>\n";

if ( $requests->count() == 0 ) {
	echo "<p class=\"nodata\">No clarification requests.</p>\n\n";
} else {
	putClarificationList($requests,$login);
}

echo addForm('clarification.php','get') .
	addHidden('pid', htmlspecialchars($pid)) . 
	"<p>" . addSubmit('request clarification') . "</p>" .
	addEndForm();

echo "</div>\n";

require(LIBWWWDIR . '/footer.php');
