<?php
/**
 * View current and past contests
 *
 * $Id$
 */

require('init.php');
$title = 'Contest';
require('../header.php');
require('menu.php');

echo "<h1>Contests</h1>\n\n";

$curcont = getCurContest();
$res = $DB->q('TABLE SELECT *,
               UNIX_TIMESTAMP(starttime) as start_u,
               UNIX_TIMESTAMP(endtime) as end_u
               FROM contest ORDER BY starttime DESC');

echo "<table>\n<tr><th>CID</th><th>starttime</th><th>endtime</th><th>name</th></tr>\n";
foreach($res as $row) {
	echo "<tr" .
		($row['cid'] == $curcont ? ' class="highlight"':'') . ">" .
		"<td align=\"right\">" . htmlentities($row['cid']) . "</td>" .
		"<td title=\"" . htmlentities($row['starttime']) . "\">" .
			printtime($row['starttime'])."</td>".
		"<td title=\"".htmlentities($row['endtime']) . "\">" .
			printtime($row['endtime'])."</td>".
		"<td>" . htmlentities($row['contestname']) . "</td>" .
		"</tr>\n";
	

}
echo "</table>\n\n";

require('../footer.php');
