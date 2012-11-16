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
		echo "<tr class=\"" .
			( $iseven ? 'roweven': 'rowodd' ) .
			"\">";
		$iseven = !$iseven;
		echo "<td>" . $row['probid'] . "</td>";
		echo "<td>" . $row['name'] . "</td>";

		$solved = $DB->q('VALUE SELECT COUNT(*)
				FROM scoreboard_public
				WHERE probid = %s AND is_correct = 1', $row['probid']);
		echo "<td>" . $solved . "</td>";
		$unsolved = $DB->q('VALUE SELECT COUNT(*)
				FROM scoreboard_public
				WHERE probid = %s AND is_correct = 0', $row['probid']);
		echo "<td>" . $unsolved . "</td>";
		$ratio = sprintf("%3.3lf", ($solved / ($solved + $unsolved)));
		echo "<td>" . $ratio . "</td>";
		echo "<td><a href=\"problem.php?id=" . $row['probid'] . "\"><img src=\"../images/pdf.gif\" alt=\"pdf\"/></a></td>";
		echo "<td>n/a</td>";
		echo "</tr>\n";
	}

	echo "</tbody></table>\n\n";
}


require(LIBWWWDIR . '/footer.php');
