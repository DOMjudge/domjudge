<?php
/**
 * View current, past and future contests
 *
 * $Id$
 */

require('init.php');
$title = 'Contests';
require('../header.php');
require('menu.php');

echo "<h1>Contests</h1>\n\n";

$curcont = getCurContest();
$res = $DB->q('TABLE SELECT *,
               UNIX_TIMESTAMP(starttime) as start_u,
               UNIX_TIMESTAMP(endtime) as end_u
               FROM contest ORDER BY starttime DESC');

if( count($res) == 0 ) {
	echo "<p><em>No contests defined</em></p>\n\n";
} else {
	echo "<table>\n<tr><th>CID</th><th>starts</th><th>ends</th>" .
		"<th>last<br />scoreupdate</th><th>name</th></tr>\n";
	foreach($res as $row) {
		echo "<tr" .
			($row['cid'] == $curcont ? ' class="highlight"':'') . ">" .
			"\t<td align=\"right\">c" . htmlentities($row['cid']) . "</td>\n" .
			"\t<td title=\"" . htmlentities($row['starttime']) . "\">" .
				printtime($row['starttime'])."</td>\n".
			"\t<td title=\"".htmlentities($row['endtime']) . "\">" .
				printtime($row['endtime'])."</td>\n".
			"\t<td title=\"".htmlentities(@$row['lastscoreupdate']) . "\">" .
			( isset($row['lastscoreupdate']) ?
			  printtime($row['lastscoreupdate']) : '-' ) . "</td>\n" .
			"\t<td>" . htmlentities($row['contestname']) . "</td>\n" .
			"</tr>\n";
	}
	echo "</table>\n\n";
}

require('../footer.php');
