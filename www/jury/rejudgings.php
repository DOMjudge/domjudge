<?php
/**
 * View the rejudgings
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Rejudgings';
$refresh = '15;url=rejudgings.php';

require(LIBWWWDIR . '/header.php');

echo "<h1>Rejudgings</h1>\n\n";

$res = $DB->q('SELECT rejudgingid, starttime, endtime, reason, valid,
               s.name AS startuser, a.name AS finishuser
               FROM rejudging
               LEFT JOIN user s ON (s.userid = userid_start)
               LEFT JOIN user a ON (a.userid = userid_finish)
               ORDER BY valid DESC, endtime, rejudgingid');

if( $res->count() == 0 ) {
	echo "<p class=\"nodata\">No rejudgings defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\">ID</th>" .
		 "<th scope=\"col\">reason</th>" .
		 "<th scope=\"col\">startuser</th>" .
		 "<th scope=\"col\">finishuser</th>" .
		 "<th scope=\"col\">starttime</th>" .
		 "<th scope=\"col\">endtime</th>" .
		 "<th scope=\"col\">status</th></tr>\n" .
		 "</thead>\n<tbody>\n";
	while($row = $res->next()) {
		$todo = $DB->q('VALUE SELECT COUNT(*) FROM submission
		                WHERE rejudgingid=%i', $row['rejudgingid']);
		$done = $DB->q('VALUE SELECT COUNT(*) FROM judging
		                WHERE rejudgingid=%i AND endtime IS NOT NULL', $row['rejudgingid']);
		$todo -= $done;
		$link = '<a href="rejudging.php?id=' . urlencode($row['rejudgingid']) . '">';
		$class = '';
		if ( isset($row['endtime']) ) {
			$class = 'class="disabled"';
		} else {
				$class = ( $todo > 0 ? '' : 'class="unseen"' );
		}
		echo "<tr $class>" .
			"<td>" . $link . ($row['rejudgingid']) . '</a></td>' .
			"<td>" . $link . htmlspecialchars($row['reason']) . '</a></td>' .
			"<td>" . $link . htmlspecialchars($row['startuser']) .  "</a></td>" .
			"<td>" . $link . htmlspecialchars($row['finishuser']) .  "</a></td>" .
			"<td>" . $link . printtime($row['starttime']) .  "</a></td>" .
			"<td>" . $link . printtime($row['endtime']) .  "</a></td>" .
			"<td>" . $link;

		if ( isset($row['endtime']) ) {
			echo $row['valid'] ? 'applied' : 'canceled';
		} else if ( $todo > 0 ) {
			$perc = (int) (100*((double)$done/(double)($done + $todo)));
			echo "$perc% done";
		} else {
			echo 'ready';
		}
		echo "</a></td></tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

require(LIBWWWDIR . '/footer.php');
