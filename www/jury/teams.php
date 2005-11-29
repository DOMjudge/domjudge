<?php
/**
 * View the teams
 *
 * $Id$
 */

require('init.php');
$title = 'Teams';

$res = $DB->q('SELECT t.*,c.name as catname,a.name as affname
               FROM team t
               LEFT JOIN team_category c USING(categoryid)
               LEFT JOIN team_affiliation a ON(t.affilid=a.affilid)
               ORDER BY c.sortorder, t.name');

require('../header.php');
require('menu.php');

echo "<h1>Teams</h1>\n\n";

if( $res->count() == 0 ) {
	echo "<p><em>No teams defined</em></p>\n\n";
} else {
	echo "<table>
	<tr><th>login</th><th>teamname</th><th>category</th><th>affiliation</th><th>host</th><th>room</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr class=\"category" . (int)$row['categoryid'] .
			"\"><td class=\"teamid\"><a href=\"team.php?id=".urlencode($row['login']).
			"\">".htmlspecialchars($row['login'])."</a>".
			"</td><td>".htmlentities($row['name']).
			"</td><td title=\"catid ".(int)$row['categoryid']."\">".
				htmlentities($row['catname']).
			"</td><td title=\"affilid ".htmlspecialchars($row['affilid'])."\">".
				htmlentities($row['affname'])."</td><td title=\"";
			if ( @$row['ipaddress'] ) {
				$host = htmlspecialchars(gethostbyaddr($row['ipaddress']));
				echo htmlspecialchars($row['ipaddress']);
				if ( $host == $row['ipaddress'] ) {
					echo "\">" . printhost($host, TRUE);
				} else {
					echo " - $host\">" . printhost($host);
				}
				
			} else {
				echo "\">-";
			}
			echo "</td><td>".htmlentities($row['room']).
				"</td></tr>\n";
	}
	echo "</table>\n\n";
}
require('../footer.php');
