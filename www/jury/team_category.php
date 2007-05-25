<?php
/**
 * View a row in a team_category
 *
 * $Id$
 */

$id = (int)@$_GET['id'];

require('init.php');

$cmd = @$_GET['cmd'];

if ( IS_ADMIN && ($cmd == 'add' || $cmd == 'edit') ) {

	require('../forms.php');

	$title = "Category: $cmd";

	require('../header.php');
	echo "<h2>" . ucfirst($cmd) . " category</h2>\n\n";

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
<tr><td>Description:</td><td><?=addInput('data[0][name]', @$row['name'], 20, 255)?></td></tr>
<tr><td>Sort order:</td><td><?=addInput('data[0][sortorder]', @$row['sortorder'], 2, 1)?></td></tr>
<tr><td>Colour:</td><td><?=addInput('data[0][color]', @$row['color'], 8, 10)?></td></tr>
</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','team_category') .
	addSubmit('Save') .
	addEndForm();

	require('../footer.php');
	exit;
}

if ( ! $id ) error("Missing or invalid category id");

$title = "Category: " .htmlspecialchars(@$id);

require('../header.php');

$data = $DB->q('TUPLE SELECT * FROM team_category WHERE categoryid = %s', $id);

echo "<h1>Category: ".htmlentities($data['name'])."</h1>\n\n";

echo "<table>\n";
echo '<tr><td>ID:</td><td>' . htmlspecialchars($data['categoryid']) . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' . htmlentities($data['name']) . "</td></tr>\n";
echo '<tr><td>Sortorder:</td><td>' . htmlspecialchars($data['sortorder']) . "</td></tr>\n";
if ( isset($data['color']) ) {
	echo '<tr><td>Colour:       </td><td style="background: ' . htmlspecialchars($data['color']) .
		';">' . htmlspecialchars($data['color']) . "</td></tr>\n";
}


echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		editLink('team_category', $data['categoryid']) . " " .
		delLink('team_category','categoryid',$data['categoryid']) . "</p>\n\n";
}

echo "<h2>Teams in " . htmlentities($data['name']) . "</h2>\n\n";

$teams = $DB->q('SELECT login,name FROM team WHERE categoryid = %i', $id);
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
