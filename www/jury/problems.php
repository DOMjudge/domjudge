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

$res = $DB->q('SELECT * FROM problem ORDER BY probid');

echo "<table>
<tr><th>ID</th><th>name</th><th>allow<br>submit</th><th>allow<br>judge</th><th>testdata</th><th>timelimit</th></tr>\n";
while($row = $res->next()) {
	echo "<tr><td><a href=\"problem.php?id=".$row['probid']."\">".$row['probid'].
		"</a></td><td>".htmlentities($row['name']).
		"</td><td align=\"center\">".$row['allow_submit'].
		"</td><td align=\"center\">".$row['allow_judge'].
		"</td><td><tt>".$row['testdata']."</tt>".
		"</td><td>".$row['timelimit'].
		"</td></tr>\n";
}
echo "</table>\n\n";
require('../footer.php');
