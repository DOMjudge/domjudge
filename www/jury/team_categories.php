<?php
/**
 * View the categories
 *
 * $Id$
 */

require('init.php');
$title = 'Categories';

require('../header.php');

echo "<h1>Categories</h1>\n\n";

$res = $DB->q('SELECT team_category.*, COUNT(login) AS numteams
               FROM team_category LEFT JOIN team USING (categoryid)
               GROUP BY team.categoryid ORDER BY sortorder, categoryid');

if( $res->count() == 0 ) {
	echo "<p><em>No categories defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n<thead>\n" .
		"<tr><th>ID</th><th>sort</th><th>name</th><th>#teams</th></tr>\n" .
		"</thead>\n<tbody>\n";

	while($row = $res->next()) {
		echo '<tr' . (isset($row['color']) ? ' style="background: ' .
		              $row['color'] . ';"' : '') .
			'><td><a href="team_category.php?id=' . (int)$row['categoryid'] .
			'">' . (int)$row['categoryid'] .
			'</td><td>' . (int)$row['sortorder'] .
			'</td><td>' . htmlentities($row['name']) .
			'</td><td align="right">' . (int)$row['numteams'] . "</td>";
		if ( IS_ADMIN ) {
			echo "<td>" .
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

require('../footer.php');
