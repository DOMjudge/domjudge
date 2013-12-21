<?php
/**
 * Shows online judge ranking.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Ranking';
require(LIBWWWDIR . '/header.php');

echo "<h1>Ranking</h1>\n\n";

$res = $DB->q('SELECT t.name, s.teamid, SUM(is_correct) AS score, SUM(submissions) AS subcnt, SUM(totaltime) AS time
               FROM scorecache_public s, team t
               WHERE categoryid = 1 AND s.teamid = t.login
	       GROUP BY s.teamid
	       ORDER BY score DESC, subcnt ASC, time ASC');

if ($res->count() == 0) {
	echo "<p class=\"nodata\">No correct submissions.</p>";
} else {
	// table header
	echo "<table class=\"list sortable\">\n<thead>\n<tr>" .
		"<th scope=\"col\">rank</th>" .
		"<th scope=\"col\">team</th>" .
		"<th scope=\"col\">score</th>" .
		"<th scope=\"col\">submissions</th>" .
		"<th scope=\"col\">time</th>" .
		"</tr>\n</thead>\n<tbody>\n";

	$iseven = 0;
	$rank = 1;
	while( $row = $res->next() ) {
		$link = " href=\"team.php?id=" . $row['teamid'] . "\"";
		echo "<tr class=\"" .
			( $iseven ? 'roweven': 'rowodd' ) .
			"\">";
		$iseven = !$iseven;
		echo "<td><a$link>" . $rank++ . "</a></td>";
		echo "<td><a$link>" . $row['name'] . "</a></td>";
		echo "<td><a$link>" . $row['score'] . "</a></td>";
		echo "<td><a$link>" . $row['subcnt'] . "</a></td>";
		echo "<td><a$link>" . $row['time'] . "</a></td>";
		echo "</tr>";
	}
}


require(LIBWWWDIR . '/footer.php');
