<?php
/**
 * View the teams
 *
 * $Id$
 */

require('init.php');
$title = 'Teams';

$res = $DB->q('SELECT t.*,c.name as catname FROM team t
               LEFT JOIN category c ON(t.category=c.catid) ORDER BY t.name');

require('../header.php');
require('menu.php');

echo "<h1>Teams</h1>\n\n";

if( $res->count() == 0 ) {
	echo "<p><em>No teams defined</em></p>\n\n";
} else {
	echo "<table>
	<tr><th>login</th><th>teamname</th><th>cat.</th><th>host</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr><td class=\"teamid\"><a href=\"team.php?id=".urlencode($row['login']).
			"\">".htmlspecialchars($row['login'])."</a>".
			"</td><td>".htmlentities($row['name']).
			"</td><td title=\"".htmlentities($row['catname'])."\">".(int)$row['category'].
			"</td><td title=\"";
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
			"</td></tr>\n";
	}
	echo "</table>\n\n";
}
require('../footer.php');
