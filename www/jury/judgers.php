<?php
/**
 * View the judgers
 *
 * $Id$
 */

require('init.php');
$title = 'Judgers';
require('../header.php');

echo "<h1>Judgers</h1>\n\n";

$res = $DB->q('SELECT * FROM judger ORDER BY name');

echo "<table>
<tr><th>nr</th><th>name</th><th>active</th></tr>\n";
while($row = $res->next()) {
	echo "<tr".
		( $row['active'] ? '': ' class="disabled"').
		"><td><a href=\"judger.php?id=".$row['judgerid'].'">'.$row['judgerid'].'</a>'.
		"</td><td>".htmlentities($row['name']).
		"</td><td align=\"center\">".$row['active'].
		"</td></tr>\n";
}
echo "</table>\n\n";
require('../footer.php');
