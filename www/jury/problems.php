<?php
/**
 * View the problems
 *
 * $Id$
 */

require('init.php');
$title = 'Problems';
require('../header.php');
require('menu.php');

echo "<h1>Problems</h1>\n\n";

$res = $DB->q('SELECT * FROM problem NATURAL JOIN contest ORDER BY probid');
$curcid = getCurContest();

echo "<table>
<tr><th>ID</th><th>name</th><th>contest</th><th>allow<br />submit</th>
	<th>allow<br />judge</th><th>testdata</th><th>timelimit</th></tr>\n";
while($row = $res->next()) {
	echo "<tr" . ($row['cid'] == $curcid ? '' : ' class="disabled"').
		"><td><a href=\"problem.php?id=".htmlspecialchars($row['probid'])."\">".
		htmlspecialchars($row['probid']).
		"</a></td><td>".htmlentities($row['name']).
		"</td><td title=\"".htmlentities($row['contestname'])."\">".htmlspecialchars($row['cid']).
		"</td><td align=\"center\">".printyn($row['allow_submit']).
		"</td><td align=\"center\">".printyn($row['allow_judge']).
		"</td><td class=\"filename\">".htmlspecialchars($row['testdata']).
		"</td><td>".(int)$row['timelimit'].
		"</td></tr>\n";
}
echo "</table>\n\n";
require('../footer.php');
