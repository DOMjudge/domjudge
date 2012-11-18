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
               FROM problem p');

if ($res->count() == 0) {
	echo "<p class=\"nodata\">No problems.</p>";
} else {
	// table header
	echo "<table class=\"list sortable\">\n<thead>\n<tr>" .
		"<th scope=\"col\">problem ID</th>" .
		"<th scope=\"col\">name</th>" .
		"<th scope=\"col\">solved</th>" .
		"<th scope=\"col\">unsolved</th>" .
		"<th scope=\"col\">ratio</th>" .
		"<th scope=\"col\">description</th>" .
		"<th scope=\"col\">sample</th>" .
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
				WHERE probid = %s AND is_correct = 1', $row['probid']);
		echo "<td><a$link>" . $solved . "</a></td>";
		$unsolved = $DB->q('VALUE SELECT COUNT(*)
				FROM scoreboard_public
				WHERE probid = %s AND is_correct = 0', $row['probid']);
		echo "<td><a$link>" . $unsolved . "</a></td>";
		$ratio = sprintf("%3.3lf", ($solved / ($solved + $unsolved)));
		echo "<td><a$link>" . $ratio . "</a></td>";
		echo "<td><a href=\"problem.php?id=" . $row['probid'] . "\"><img src=\"../images/pdf.gif\" alt=\"pdf\"/></a></td>";
		$sample_string = "";
		$samples = $DB->q("SELECT testcaseid, description FROM testcase
		                   WHERE probid=%s AND sample=1 AND description IS NOT NULL
		                   ORDER BY rank", $row['probid']);
		if ( $samples->count() == 0) {
			$sample_string = '<span class="nodata">no public samples</span>';
		} else {
			while ( $sample = $samples->next() ) {
				$sample_string .= ' <a href="sample.php?in=1id=' . $sample['testcaseid'] . '">' . $sample['description'] . '.in<a>';
				$sample_string .= ' <a href="sample.php?in=0id=' . $sample['testcaseid'] . '">' . $sample['description'] . '.out<a>';
			}
		}
		echo "<td>$sample_string</td>";
		echo "</tr>\n";
	}

	echo "</tbody></table>\n\n";
}


require(LIBWWWDIR . '/footer.php');
