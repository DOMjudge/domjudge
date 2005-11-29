<?php
/**
 * View the categories
 *
 * $Id$
 */

require('init.php');
$title = 'Categories';

require('../header.php');
require('menu.php');

echo "<h1>Categories</h1>\n\n";

$res = $DB->q('SELECT * FROM team_category ORDER BY sortorder,categoryid');

if( $res->count() == 0 ) {
	echo "<p><em>No categories defined</em></p>\n\n";
} else {
	echo "<table>\n".
		"<tr><th>ID</th><th>sort</th><th>name</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr class=\"category". (int)$row['categoryid'] .
			"\"><td>" . (int)$row['categoryid'] .
			"</td><td>" . (int)$row['sortorder'] .
			"</td><td>" . htmlentities($row['name']) .
			"</td></tr>\n";
	}
	echo "</table>\n\n";
}

require('../footer.php');
