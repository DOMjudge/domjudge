<?php
/**
 * View all team affiliations
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Affiliations';

require(LIBWWWDIR . '/header.php');

echo "<h1>Affiliations</h1>\n\n";

$res = $DB->q('SELECT a.*, COUNT(login) AS cnt FROM team_affiliation a
               LEFT JOIN team USING (affilid)
               GROUP BY affilid ORDER BY name');

if( $res->count() == 0 ) {
	echo "<p class=\"nodata\">No affiliations defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
		"<tr><th>ID</th>" .
		"<th>name</th>" .
		"<th class=\"sorttable_nosort\">logo</th>" .
		"<th>country</th>" .
		"<th>#teams</th></tr>\n</thead>\n<tbody>\n";

	while($row = $res->next()) {
		$affillogo = "../images/affiliations/" . urlencode($row['affilid']) . ".png";
		$countryflag = "../images/countries/" . urlencode($row['country']) . ".png";
		$link = '<a href="team_affiliation.php?id=' . urlencode($row['affilid']) . '">';
		echo '<tr><td>' . $link . htmlspecialchars($row['affilid']) .
			'</a></td><td>' . $link . htmlspecialchars($row['name']) .
			'</a></td><td align="center">' . $link .
			( is_readable($affillogo) ? '<img src="' . $affillogo .
			  '" alt="' . htmlspecialchars($row['name']) . '" />' : '&nbsp;' ) .
			'</a></td><td align="center">' . $link .
			htmlspecialchars($row['country']) .
			( is_readable($countryflag) ? ' <img src="' . $countryflag .
			  '" alt="' . htmlspecialchars($row['country']) . '" />' : '&nbsp;' ) .
			'</a></td><td align="right">' . $link .
			(int)$row['cnt'] .
			'</a></td>';
		if ( IS_ADMIN ) {
			echo "<td class=\"editdel\">" .
				editLink('team_affiliation', $row['affilid']) . " " .
				delLink('team_affiliation', 'affilid', $row['affilid']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('team_affiliation') . "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
