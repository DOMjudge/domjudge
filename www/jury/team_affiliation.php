<?php
/**
 * View a row in team_affiliation: an institution, company etc
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = @$_GET['id'];

require('init.php');

$cmd = @$_GET['cmd'];

if ( IS_ADMIN && ($cmd == 'add' || $cmd == 'edit') ) {

	require('../forms.php');

	$title = "Affiliation: $cmd";

	require('../header.php');
	echo "<h2>" . ucfirst($cmd) . " affiliation</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Affiliation ID:</td><td>";
		$row = $DB->q('TUPLE SELECT * FROM team_affiliation WHERE affilid = %s',
			$_GET['id']);
		echo addHidden('keydata[0][affilid]', $row['affilid']) .
			htmlspecialchars($row['affilid']);
	} else {
		echo "<tr><td><label for=\"data_0__affilid_\">Affiliation ID:</label></td><td>";
		echo addInput('data[0][affilid]', null, 11, 10);
	}
	echo "</td></tr>\n";

?>

<tr><td><label for="data_0__name_">Name:</label></td>
<td><?=addInput('data[0][name]', @$row['name'], 40, 255)?></td></tr>

<tr><td><label for="data_0__country_">Country:</label></td>
<td><?=addInput('data[0][country]', @$row['country'], 3, 2)?></td></tr>

<tr><td valign="top"><label for="data_0__comments_">Comments:</label></td>
<td><?=addTextArea('data[0][comments]', @$row['comments'])?></td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','team_affiliation') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addEndForm();

	require('../footer.php');
	exit;
}




if ( ! $id ) error("Missing or invalid affiliation id");
$title = "Affiliation: " .htmlspecialchars(@$id);


require('../header.php');

$data = $DB->q('TUPLE SELECT * FROM team_affiliation WHERE affilid = %s', $id);

$affillogo = "../images/affiliations/" . urlencode($data['affilid']) . ".png";
$countryflag = "../images/countries/" . urlencode($data['country']) . ".png";

echo "<h1>Affiliation: ".htmlspecialchars($data['name'])."</h1>\n\n";

echo "<table>\n";
echo '<tr><td scope="row">ID:</td><td>' . htmlspecialchars($data['affilid']) . "</td></tr>\n";
echo '<tr><td scope="row">Name:</td><td>' . htmlspecialchars($data['name']) . "</td></tr>\n";

echo '<tr><td scope="row">Logo:</td><td>';

if ( is_readable($affillogo) ) {
	echo '<img src="' . $affillogo . '" alt="' .
		htmlspecialchars($data['affilid']) . "\" /></td></tr>\n";
} else {
	echo "not available</td></tr>\n";
}

echo '<tr><td scope="row">Country:</td><td>' . htmlspecialchars($data['country']);

if ( is_readable($countryflag) ) {
	echo ' <img src="' . $countryflag . '" alt="' .
		htmlspecialchars($data['country']) . "\" />";
}
echo "</td></tr>\n";

if ( !empty($data['comments']) ) {
	echo '<tr><td valign="top" scope="row">Comments:</td><td>' .
		nl2br(htmlspecialchars($data['comments'])) . "</td></tr>\n";
}

echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" . 
		editLink('team_affiliation', $data['affilid']) . "\n" .
		delLink('team_affiliation', 'affilid', $data['affilid']) . "</p>\n\n";
}

echo "<h2>Teams from " . htmlspecialchars($data['name']) . "</h2>\n\n";

$teams = $DB->q('SELECT login,name FROM team WHERE affilid = %s', $id);
if ( $teams->count() == 0 ) {
	echo "<p><em>no teams</em></p>\n\n";
} else {
	echo "<table>\n<thead>\n" .
		"<tr><th scope=\"col\">login</th><th scope=\"col\">teamname</th></tr>\n" .
		"</thead>\n<tbody>\n";
	while ($team = $teams->next()) {
		echo "<tr><td class=\"teamid\"><a href=\"team.php?id=" .
			urlencode($team['login']) . "\">" .
			htmlspecialchars($team['login']) . "</a></td><td>" .
			htmlspecialchars($team['name']) . "</td></tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}
	 
require('../footer.php');
