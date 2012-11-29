<?php
/**
 * Shows problem list.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Problem list';
require(LIBWWWDIR . '/header.php');

echo "<h1>Problems</h1>\n\n";

$res = $DB->q('SELECT probid, name
               FROM problem p
		WHERE cid=%i', $cid);

if ($res->count() == 0) {
	echo "<p class=\"nodata\">No problems.</p>";
} else {
	// TODO: don't query these values over and over again but add another table
	$verdicts = array('correct', 'run-error', 'timelimit', 'wrong-answer', 'presentation-error', 'no-output');
	$cnt = 0;
	foreach ($verdicts as $verdict) {
		$verdictCnt[] = "[" . $cnt . ", " . 
			$DB->q('VALUE SELECT COUNT(*)
			FROM judging j
			LEFT JOIN submission s USING (submitid)
			WHERE
			s.valid=1
			AND j.valid=1
			AND j.result=%s
			AND s.teamid!=%s', $verdict, 'domjudge')
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
				AND langid=%s
				AND teamid!=%s', $lang['langid'], 'domjudge')
			. "]";
		$langId[] = "[" . $cnt . ", '" . $lang['name'] . "']";
		$cnt++;
	}
	$langCnt_string = join(',', $langCnt);
	$lang_string = join(',', $langId);

?>
<div id="verdicts" style="width:550px;height:200px;"></div>
<div id="langs" style="width:550px;height:200px;"></div>

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

<?
	// table header
	echo "<table class=\"list sortable\">\n<thead>\n<tr>" .
		"<th scope=\"col\">problem ID</th>" .
		"<th scope=\"col\">name</th>" .
		"<th scope=\"col\">solved</th>" .
		"<th scope=\"col\">unsolved</th>" .
		"<th scope=\"col\">ratio</th>" .
		"<th scope=\"col\">description</th>" .
		"<th scope=\"col\">sample</th>" .
		"<th scope=\"col\">status</th>" .
		"</tr>\n</thead>\n<tbody>\n";

	$iseven = 0;
	while( $row = $res->next() ) {
		$link = " href=\"problem_details.php?id=" . urlencode($row['probid']) . "\"";
		echo "<tr class=\"" .
			( $iseven ? 'roweven': 'rowodd' ) .
			"\">";
		$iseven = !$iseven;
		echo "<td><a$link>" . $row['probid'] . "</a></td>";
		echo "<td><a$link>" . $row['name'] . "</a></td>";

		$solved = $DB->q('VALUE SELECT COUNT(*)
				FROM scoreboard_public
				WHERE probid = %s AND is_correct = 1 AND teamid!=%s', $row['probid'], 'domjudge');
		echo "<td><a$link>" . $solved . "</a></td>";
		$unsolved = $DB->q('VALUE SELECT COUNT(*)
				FROM scoreboard_public
				WHERE probid = %s AND is_correct = 0 AND teamid!=%s', $row['probid'], 'domjudge');
		echo "<td><a$link>" . $unsolved . "</a></td>";
		$ratio = sprintf("%3.3lf", ($solved / ($solved + $unsolved)));
		echo "<td><a$link>" . $ratio . "</a></td>";
		echo "<td><a href=\"problem.php?id=" . $row['probid'] . "\"><img src=\"../images/pdf.gif\" alt=\"pdf\"/> PDF</a></td>";
		$samples = $DB->q("SELECT testcaseid, description FROM testcase
		                   WHERE probid=%s AND sample=1 AND description IS NOT NULL
		                   ORDER BY rank", $row['probid']);
		if ( $samples->count() == 0) {
			$sample_string = '<span class="nodata">no public samples</span>';
		} else {
			$sample_string = array();
			while ( $sample = $samples->next() ) {
				$sample_string[] = ' <a style="display:inline;"href="sample.php?in=1&id=' . $sample['testcaseid'] . '">' . $sample['description'] . '.in</a>';
				$sample_string[] = ' <a style="display:inline;" href="sample.php?in=0&id=' . $sample['testcaseid'] . '">' . $sample['description'] . '.out</a>';
			}
			$sample_string = join(' | ', $sample_string);
		}
		echo "<td>$sample_string</td>";
		$status = $DB->q('MAYBEVALUE SELECT is_correct FROM scoreboard_public WHERE probid=%s AND teamid=%s', $row['probid'], $login);
		if ( $status === NULL ) {
			$status = "untouched";
		} else if ( $status == 1 ) {
			$status = "solved";
		} else {
			$status = "unsolved";
		}
		
		echo "<td class=\"$status\">" . CIRCLE_SYM . "</td>";
		echo "</tr>\n";
	}

	echo "</tbody></table>\n\n";
}


require(LIBWWWDIR . '/footer.php');
