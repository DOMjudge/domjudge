<?php
/**
 * View the languages
 *
 * $Id$
 */

require('init.php');
$title = 'Languages';
require('../header.php');

echo "<h1>Languages</h1>\n\n";

$res = $DB->q('SELECT * FROM language ORDER BY name');

echo "<table>
<tr><th>ID</th><th>name</th><th>extension</th><th>allow<br>judge</th><th>timefactor</th></tr>\n";
while($row = $res->next()) {
	echo "<tr".
		( $row['allow_judge'] ? '': ' class="disabled"').
		"><td><a href=\"language.php?id=".$row['langid']."\">".$row['langid']."</a>".
		"</td><td>".htmlentities($row['name']).
		"</td><td><tt>.".$row['extension']."</tt>".
		"</td><td align=\"center\">".$row['allow_judge'].
		"</td><td>".$row['time_factor'].
		"</td></tr>\n";
}
echo "</table>\n\n";
require('../footer.php');
