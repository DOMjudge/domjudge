<?php
/**
 * View the teams
 *
 * $Id$
 */

require('init.php');
$title = 'Teams';
require('../header.php');

echo "<h1>Teams</h1>\n\n";

$res = $DB->q('SELECT * FROM team ORDER BY name');

echo "<table>
<tr><th>login</th><th>teamname</th><th>cat.</th><th>IP address</th></tr>\n";
while($row = $res->next()) {
	echo "<tr><td><a href=\"team.php?id=".$row['login']."\">".$row['login']."</a>".
		"</td><td>".htmlentities($row['name']).
		"</td><td>".$row['category'].
		"</td><td>".@$row['ipaddress'].
		"</td></tr>\n";
}
echo "</table>\n\n";
require('../footer.php');
