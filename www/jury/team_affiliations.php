<?php
/**
 * View all team affiliations
 *
 * $Id$
 */

require('init.php');
$title = 'Affiliations';

require('../header.php');

echo "<h1>Affiliations</h1>\n\n";

$res = $DB->q('SELECT a.*, COUNT(login) AS cnt FROM team_affiliation a
               LEFT JOIN team USING (affilid)
               GROUP BY affilid ORDER BY name');

if( $res->count() == 0 ) {
	echo "<p><em>No affiliations defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n" .
		"<tr><th>ID</th><th>name</th><th>country</th><th>#teams</th></tr>\n";
	while($row = $res->next()) {
		echo '<tr><td><a href="team_affiliation.php?id=' .
			urlencode($row['affilid']) . '">' .
			htmlspecialchars($row['affilid']).
			'</a></td><td><a href="team_affiliation.php?id=' .
			urlencode($row['affilid']) . '">' .
			htmlentities($row['name']) .
			'</a></td><td>' .
			htmlspecialchars($row['country']) .
			'</td><td align="right">' .
			(int)$row['cnt'] .
			"</td>";
		if ( IS_ADMIN ) {
			echo "<td>" . delLink('team_affiliation', 'affilid', $row['affilid']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</table>\n\n";
}
require('../footer.php');
