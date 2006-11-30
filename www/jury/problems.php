<?php
/**
 * View the problems
 *
 * $Id$
 */

require('init.php');
$title = 'Problems';

require('../header.php');

echo "<h1>Problems</h1>\n\n";

$res = $DB->q('SELECT * FROM problem NATURAL JOIN contest ORDER BY problem.cid, probid');
$curcid = getCurContest();

if( $res->count() == 0 ) {
	echo "<p><em>No problems defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n" .
		"<tr><th>ID</th><th>name</th><th>contest</th><th>allow<br />submit</th>" .
		"<th>allow<br />judge</th><th>testdata</th><th>timelimit</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr" . ($row['cid'] == $curcid ? '' : ' class="disabled"').
			"><td><a href=\"problem.php?id=".htmlspecialchars($row['probid'])."\">".
				htmlspecialchars($row['probid'])."</a>".
			"</td><td><a href=\"problem.php?id=".htmlspecialchars($row['probid'])."\">".
			htmlentities($row['name'])."</a>".
			"</td><td title=\"".htmlentities($row['contestname'])."\">".
			htmlspecialchars($row['cid']).
			"</td><td align=\"center\">".printyn($row['allow_submit']).
			"</td><td align=\"center\">".printyn($row['allow_judge']).
			"</td><td class=\"filename\">".htmlspecialchars($row['testdata']).
			"</td><td>".(int)$row['timelimit'].
			"</td></tr>\n";
	}
	echo "</table>\n\n";
}
require('../footer.php');
