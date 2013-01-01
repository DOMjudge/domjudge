<?php
/**
 * View a row in team_affiliation: an institution, company etc
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');

$id = @$_GET['id'];
$title = "Affiliation: " .htmlspecialchars(@$id);

if ( ! preg_match('/^' . IDENTIFIER_CHARS . '*$/', $id) ) error("Invalid affiliation id");

$cmd = @$_GET['cmd'];

if ( IS_ADMIN && ($cmd == 'add' || $cmd == 'edit') ) {

	$title = "Affiliation: " . htmlspecialchars($cmd);

	require(LIBWWWDIR . '/header.php');
	echo "<h2>" . htmlspecialchars(ucfirst($cmd)) . " affiliation</h2>\n\n";

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
<td><?php echo addInput('data[0][name]', @$row['name'], 40, 255)?></td></tr>

<tr><td><label for="data_0__country_">Country:</label></td>
<td><?php echo addInput('data[0][country]', @$row['country'], 4, 3)?>
<a target="_blank"
href="http://en.wikipedia.org/wiki/ISO_3166-1_alpha-3#Current_codes"><img
src="../images/b_help.png" class="smallpicto" alt="?" /></a></td></tr>

<tr><td><label for="data_0__comments_">Comments:</label></td>
<td><?php echo addTextArea('data[0][comments]', @$row['comments'])?></td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','team_affiliation') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel') .
	addEndForm();

	require(LIBWWWDIR . '/footer.php');
	exit;
}


require(LIBWWWDIR . '/header.php');

$data = $DB->q('TUPLE SELECT * FROM team_affiliation WHERE affilid = %s', $id);

if ( ! $data ) error("Missing or invalid affiliation id");

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
	echo '<tr><td scope="row">Comments:</td><td>' .
		nl2br(htmlspecialchars($data['comments'])) . "</td></tr>\n";
}

echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		editLink('team_affiliation', $data['affilid']) . "\n" .
		delLink('team_affiliation', 'affilid', $data['affilid']) . "</p>\n\n";
}

echo "<h2>Teams from " . htmlspecialchars($data['name']) . "</h2>\n\n";

$listteams = array();
$teams = $DB->q('SELECT login,name FROM team WHERE affilid = %s', $id);
if ( $teams->count() == 0 ) {
	echo "<p class=\"nodata\">no teams</p>\n\n";
} else {
	echo "<table class=\"list\">\n<thead>\n" .
		"<tr><th scope=\"col\">login</th><th scope=\"col\">teamname</th></tr>\n" .
		"</thead>\n<tbody>\n";
	while ($team = $teams->next()) {
		$listteams[] = $team['login'];
		$link = '<a href="team.php?id=' . urlencode($team['login']) . '">';
		echo "<tr><td class=\"teamid\">" .
		$link . htmlspecialchars($team['login']) . "</a></td><td>" .
		$link . htmlspecialchars($team['name']) . "</a></td></tr>\n";
	}
	echo "</tbody>\n</table>\n\n";

	putTeamRow($cdata,$listteams);
}

require(LIBWWWDIR . '/footer.php');
