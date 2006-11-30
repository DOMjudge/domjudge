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

$res = $DB->q('SELECT team_category.*,count(login) as numteams
	FROM team_category LEFT JOIN team USING(categoryid)
	GROUP BY team.categoryid
	ORDER BY sortorder,categoryid');

if( $res->count() == 0 ) {
	echo "<p><em>No categories defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n" .
		"<tr><th>ID</th><th>sort</th><th>name</th><th>#teams</th></tr>\n";
	while($row = $res->next()) {
		echo '<tr' . (isset($row['color']) ? ' style="background: ' .
		              $row['color'] . ';"' : '') .
			'><td>' .     (int)$row['categoryid'] .
			'</td><td>' . (int)$row['sortorder'] .
			'</td><td>' . htmlentities($row['name']) .
			'</td><td align="right">' . (int)$row['numteams'] .
			"</td></tr>\n";
	}
	echo "</table>\n\n";
}

require('../footer.php');
