<?php
/**
 * View a row in a team_category
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$id = (int)@$_GET['id'];

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');

$cmd = @$_GET['cmd'];

if ( IS_ADMIN && ($cmd == 'add' || $cmd == 'edit') ) {

	$title = "Category: " . htmlspecialchars($cmd);
	$jscolor = true;

	require(LIBWWWDIR . '/header.php');
	echo "<h2>" . htmlspecialchars(ucfirst($cmd)) . " category</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Category ID:</td><td>";
		$row = $DB->q('TUPLE SELECT * FROM team_category WHERE categoryid = %i',
			$_GET['id']);
		echo addHidden('keydata[0][categoryid]', $row['categoryid']) .
			htmlspecialchars($row['categoryid']) .
			"</td></tr>\n";
	}

?>

<tr><td><label for="data_0__name_">Description:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 15, 255)?></td></tr>

<tr><td><label for="data_0__sortorder_">Sort order:</label></td>
<td><?php echo addInput('data[0][sortorder]', (empty($row['sortorder'])?0:$row['sortorder']), 2, 1)?></td></tr>

<tr><td><label for="data_0__color_">Colour:</label></td>
<td><?php echo addInputField('text','data[0][color]', @$row['color'],
	'size="15" maxlength="25" class="color {required:false,adjust:false,hash:true,caps:false}"')?>
<a target="_blank"
href="http://www.w3schools.com/cssref/css_colornames.asp"><img
src="../images/b_help.png" class="smallpicto" alt="?" /></a></td></tr>
</td></tr>

<tr><td>Visible:</td>
<td><?php echo addRadioButton('data[0][visible]', (!isset($row['visible']) || $row['visible']), 1)?> <label for="data_0__visible_1">yes</label>
<?php echo addRadioButton('data[0][visible]', (isset($row['visible']) && !$row['visible']), 0)?> <label for="data_0__visible_0">no</label></td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','team_category') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel') .
	addEndForm();

	require(LIBWWWDIR . '/footer.php');
	exit;
}

if ( ! $id ) error("Missing or invalid category id");

$data = $DB->q('TUPLE SELECT * FROM team_category WHERE categoryid = %s', $id);

$title = "Category: " . htmlspecialchars($data['name']);

require(LIBWWWDIR . '/header.php');

echo "<h1>$title</h1>\n\n";

echo "<table>\n";
echo '<tr><td scope="row">ID:</td><td>' . htmlspecialchars($data['categoryid']) . "</td></tr>\n";
echo '<tr><td scope="row">Name:</td><td>' . htmlspecialchars($data['name']) . "</td></tr>\n";
echo '<tr><td scope="row">Sortorder:</td><td>' . htmlspecialchars($data['sortorder']) . "</td></tr>\n";
if ( isset($data['color']) ) {
	echo '<tr><td scope="row">Colour:       </td><td style="background: ' .
		htmlspecialchars($data['color']) .
		';">' . htmlspecialchars($data['color']) . "</td></tr>\n";
}
echo '<tr><td scope="row">Visible:</td><td>' . printyn($data['visible']) . "</td></tr>\n";


echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		editLink('team_category', $data['categoryid']) . "\n" .
		delLink('team_category','categoryid',$data['categoryid']) . "</p>\n\n";
}

echo "<h2>Teams in " . htmlspecialchars($data['name']) . "</h2>\n\n";

$listteams = array();
$teams = $DB->q('SELECT login,name FROM team WHERE categoryid = %i', $id);
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

