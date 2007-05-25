<?php
/**
 * View a row in team_affiliation: an institution, company etc
 *
 * $Id$
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
	echo "<tr><td>Affiliation ID:</td><td>";

	if ( $cmd == 'edit' ) {
		$row = $DB->q('TUPLE SELECT * FROM team_affiliation WHERE affilid = %s',
			$_GET['id']);
		echo addHidden('keydata[0][affilid]', $row['affilid']) .
			htmlspecialchars($row['affilid']);
	} else {
		echo addInput('data[0][affilid]', null, 11, 10);
	}
	echo "</td></tr>\n";

?>
<tr><td>Name:</td><td><?=addInput('data[0][name]', @$row['name'], 40, 50)?></td></tr>
<tr><td>Country:</td><td><?=addInput('data[0][country]', @$row['country'], 3, 2)?></td></tr>
<tr><td valign="top">Comments:</td><td><?=addTextArea('data[0][comments]', @$row['comments'])?></td></tr>
</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','team_affiliation') .
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

echo "<h1>Affiliation: ".htmlentities($data['name'])."</h1>\n\n";

echo "<table>\n";
echo '<tr><td>ID:</td><td>' . htmlspecialchars($data['affilid']) . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' . htmlentities($data['name']) . "</td></tr>\n";

echo '<tr><td>Logo:</td><td>';

if ( is_readable($affillogo) ) {
	echo '<img src="' . $affillogo . '" alt="' .
		htmlspecialchars($data['affilid']) . "\" /></td></tr>\n";
} else {
	echo "not available</td></tr>\n";
}

echo '<tr><td>Country:</td><td>' . htmlspecialchars($data['country']);

if ( is_readable($countryflag) ) {
	echo ' <img src="' . $countryflag . '" alt="' .
		htmlspecialchars($data['country']) . "\" />";
}
echo "</td></tr>\n";

if ( !empty($data['comments']) ) {
	echo '<tr><td valign="top">Comments:</td><td>' .
		nl2br(htmlentities($data['comments'])) . "</td></tr>\n";
}

echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" . 
		editLink('team_affiliation', $data['affilid']) . " " .
		delLink('team_affiliation', 'affilid', $data['affilid']) . "</p>\n\n";
}

echo "<h2>Teams from " . htmlentities($data['name']) . "</h2>\n\n";

$teams = $DB->q('SELECT login,name FROM team WHERE affilid = %s', $id);
if ( $teams->count() == 0 ) {
	echo "<p><em>no teams</em></p>\n\n";
} else {
	echo "<table>\n";
	echo "<tr><th>login</th><th>teamname</th></tr>\n";
	while ($team = $teams->next()) {
		echo "<tr><td class=\"teamid\"><a href=\"team.php?id=" .
			urlencode($team['login']) . "\">" .
			htmlspecialchars($team['login']) . "</a></td><td>" .
			htmlentities($team['name']) . "</td></tr>\n";
	}
	echo "</table>\n\n";
}
	 
require('../footer.php');
