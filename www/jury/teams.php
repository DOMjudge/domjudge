<?php
/**
 * View the teams
 *
 * $Id$
 */

require('init.php');
$title = 'Teams';
require('../header.php');
require('menu.php');

echo "<h1>Teams</h1>\n\n";

$res = $DB->q('SELECT t.*,c.name as catname FROM team t LEFT JOIN category c ON(t.category=c.catid) ORDER BY t.name');

echo "<table>
<tr><th>login</th><th>teamname</th><th>cat.</th><th>IP address</th></tr>\n";
while($row = $res->next()) {
	echo "<tr><td class=\"teamid\"><a href=\"team.php?id=".urlencode($row['login']).
		"\">".htmlspecialchars($row['login'])."</a>".
		"</td><td>".htmlentities($row['name']).
		"</td><td title=\"".htmlentities($row['catname'])."\">".(int)$row['category'].
		"</td><td>".htmlspecialchars(@$row['ipaddress']).
		"</td></tr>\n";
}
echo "</table>\n\n";
require('../footer.php');
