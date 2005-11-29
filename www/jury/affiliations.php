<?php
/**
 * View all team affiliations
 *
 * $Id$
 */

require('init.php');
$title = 'Affiliations';

require('../header.php');
require('menu.php');

echo "<h1>Affiliations</h1>\n\n";

$res = $DB->q('SELECT a.*,count(login) as cnt FROM team_affiliation a
				LEFT JOIN team USING(affilid)
				GROUP BY affilid
				ORDER BY name');

if( $res->count() == 0 ) {
	echo "<p><em>No affiliations defined</em></p>\n\n";
} else {
	echo "<table>
	<tr><th>ID</th><th>name</th><th>country</th><th>#teams</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr><td><a href=\"affiliation.php?id=" .
			urlencode($row['affilid']) . "\">" .
			htmlspecialchars($row['affilid']).
			"</a></td><td>" .
			htmlentities($row['name']) .
			"</td><td>" .
			htmlspecialchars($row['country']) .
			"</td><td align=\"right\">".
			(int)$row['cnt'] .
			"</td></tr>\n";
	}
	echo "</table>\n\n";
}
require('../footer.php');
