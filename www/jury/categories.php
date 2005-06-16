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

$res = $DB->q('SELECT * FROM category ORDER BY catid');

echo "<table>
<tr><th>nr</th><th>name</th></tr>\n";
while($row = $res->next()) {
	echo "<tr><td>" . (int)$row['catid'] .
		"</td><td>" . htmlentities($row['name']) .
		"</td></tr>\n";
}
echo "</table>\n\n";

require('../footer.php');
