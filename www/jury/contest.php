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

$res = $DB->q('TABLE SELECT *,
	UNIX_TIMESTAMP(starttime) as start_u, UNIX_TIMESTAMP(endtime) as end_u
	FROM contest ORDER BY starttime DESC');

if(count($res) == 0) {
	echo "<p><em>No contests found</em></p>";
	include('footer.php');
	exit;
}

$mostrecent = array_shift($res);

if($mostrecent['start_u'] < time()) { 
	if($mostrecent['end_u'] > time()) {
		echo "<h3>Current Contest</h3>\n";
	} else {
		echo "<h3>Most Recent Contest</h3>\n";
	}
} else {
	echo "<h3>Upcoming Contest</h3>\n";
}
echo "<table>\n".
	"<tr><td>Contest:</td><td>".htmlentities($mostrecent['contestname']).
		' ['.(int)$mostrecent['cid']."]</td></tr>\n".
	"<tr><td>Start:</td><td>".htmlspecialchars($mostrecent['starttime'])."</td></tr>\n".
	"<tr><td>End:</td><td>".htmlspecialchars($mostrecent['endtime'])."</td></tr>\n".
	"</table>\n\n";

echo "<h3>Past Contests</h3>\n\n";

echo "<table>\n<tr><th>Contest</th><th>Start</th><th>End</th></tr>\n";
foreach($res as $row) {
	echo "<tr><td>".htmlentities($row['contestname']).
	        ' ['.(int)$row['cid'].
			"]</td><td>" . htmlspecialchars($row['starttime']).
			"</td><td>" . htmlspecialchars($row['endtime']).
			"</td></tr>\n";
}
echo "</table>\n\n";


require('../footer.php');
