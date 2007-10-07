<?php
/**
 * View the problems
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
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
	echo "<table class=\"list\">\n<thead>\n" .
		"<tr><th scope=\"col\">ID</th><th scope=\"col\">name</th>" .
		"<th scope=\"col\">contest</th><th scope=\"col\">allow<br />submit</th>" .
		"<th scope=\"col\">allow<br />judge</th><th scope=\"col\">testdata</th>" .
		"<th scope=\"col\">timelimit</th>" .
		"<th scope=\"col\">colour</th></tr>" .
		"</thead>\n<tbody>\n";

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
			"</td>".
			( isset($row['color'])
			? '<td style="background: '.htmlspecialchars($row['color']).';">'.
			  htmlspecialchars($row['color'])
			: '<td>' );
			if ( IS_ADMIN ) {
				echo "</td><td>" . 
					editLink('problem', $row['probid']) . " " . 
					delLink('problem','probid',$row['probid']);
			}
			echo "</td></tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('problem') . "</p>\n\n";
}

require('../footer.php');
