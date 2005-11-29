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
require('menu.php');

$data = $DB->q('TUPLE SELECT * FROM team_affiliation WHERE affilid = %s', $id);

echo "<h1>Affiliation: ".htmlentities($data['name'])."</h1>\n\n";

?>
<table>
<tr><td>ID:          </td><td><?=htmlspecialchars($data['affilid'])?></td></tr>
<tr><td>Name:        </td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Country:     </td><td><?=htmlspecialchars($data['country'])?></td></tr>
<tr><td>Logo:        </td><td><?=printyn($data['has_logo'])?></td></tr>
<?php if ( !empty($data['comments']) ): ?>
<tr><td valign="top">Comments:</td><td><?=
	nl2br(htmlentities($data['comments']))?></td></tr>
<?php endif; ?>
</table>

<h2>Teams from <?=htmlentities($data['name'])?></h2>

<?php
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
