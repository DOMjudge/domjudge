<?php
/**
 * View a row in team_affiliation: an institution, company etc
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = $_REQUEST['id'];

require('init.php');
$title = "Affiliation: " .htmlspecialchars(@$id);

if ( ! $id ) error("Missing or invalid affiliation id");

require('../header.php');

$data = $DB->q('TUPLE SELECT * FROM team_affiliation WHERE affilid = %s', $id);

$affillogo = "../images/affiliations/" . urlencode($data['affilid']) . ".png";
$countryflag = "../images/countries/" . urlencode($data['country']) . ".png";

echo "<h1>Affiliation: ".htmlentities($data['name'])."</h1>\n\n";

?>
<table>
<tr><td>ID:          </td><td><?=htmlspecialchars($data['affilid'])?></td></tr>
<tr><td>Name:        </td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Country:     </td><td><?php

echo htmlspecialchars($data['country']);
if ( is_readable($countryflag) ) {
	echo ' <img src="' . $countryflag . '" alt="' .
		htmlspecialchars($data['country']) . "\" /></td></tr>\n";
}

echo "</td></tr>\n";
echo "<tr><td>Logo:        </td><td>";

if ( is_readable($affillogo) ) {
	echo '<img src="' . $affillogo . '" alt="' .
		htmlspecialchars($data['affilid']) . "\" /></td></tr>\n";
} else {
	echo "not available</td></tr>\n";
}

if ( !empty($data['comments']) ) {
	echo '<tr><td valign="top">Comments:</td><td>' .
		nl2br(htmlentities($data['comments'])) . "</td></tr>\n";
}

echo "</table>\n\n";
echo "<h2>Teams from " . htmlentities($data['name']) . "</h2>\n\n";

$teams = $DB->q('SELECT login,name FROM team WHERE affilid = %s', $id);
if ( $teams->count() == 0 ) {
	echo "<p><em>no teams</em></p>\n\n";
} else {
	echo "<table>\n";
	while ($team = $teams->next()) {
		echo "<tr><td class=\"teamid\"><a href=\"team.php?id=" .
			urlencode($team['login']) . "\">" .
			htmlspecialchars($team['login']) . "</a></td><td>" .
			htmlentities($team['name']) . "</td></tr>\n";
	}
	echo "</table>\n\n";
}
	 
require('../footer.php');
