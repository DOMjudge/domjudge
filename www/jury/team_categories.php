<?php
/**
 * View the categories
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Categories';

require(LIBWWWDIR . '/header.php');

echo "<h1>Categories</h1>\n\n";

$res = $DB->q('SELECT team_category.*, COUNT(login) AS numteams
               FROM team_category LEFT JOIN team USING (categoryid)
               GROUP BY team_category.categoryid ORDER BY sortorder, categoryid');

if( $res->count() == 0 ) {
	echo "<p class=\"nodata\">No categories defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
		"<tr><th scope=\"col\">ID</th><th scope=\"col\">sort</th>" .
		"<th scope=\"col\">name</th><th scope=\"col\">#teams</th>" .
		"<th scope=\"col\">visible</th></tr>\n" .
		"</thead>\n<tbody>\n";

	while($row = $res->next()) {
		$link = '<a href="team_category.php?id=' . (int)$row['categoryid'] . '">';
		echo '<tr' . (isset($row['color']) ? ' style="background: ' .
		              $row['color'] . ';"' : '') .
			'><td>' . $link. (int)$row['categoryid'] .
			'</a></td><td>' . $link . (int)$row['sortorder'] .
			'</a></td><td>' . $link . htmlspecialchars($row['name']) .
			'</a></td><td align="right">' . $link . (int)$row['numteams'] .
			'</a></td><td align="center">' . $link . printyn($row['visible']) .
			'</a></td>';
		if ( IS_ADMIN ) {
			echo "<td class=\"editdel\">" .
				editLink('team_category', $row['categoryid']) . " " .
				delLink('team_category', 'categoryid', $row['categoryid']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('team_category') . "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
