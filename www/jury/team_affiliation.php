<?php
/**
 * View a row in team_affiliation: an institution, company etc
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');

$id = getRequestID();
$title = ucfirst((empty($_GET['cmd']) ? '' : specialchars($_GET['cmd']) . ' ') .
                 'affiliation' . ($id ? ' '.specialchars(@$id) : ''));

require(LIBWWWDIR . '/header.php');

if (!empty($_GET['cmd'])):

    requireAdmin();

    $cmd = $_GET['cmd'];

    echo "<h2>$title</h2>\n\n";

    echo addForm('edit.php');

    echo "<table>\n";

    if ($cmd == 'edit') {
        $row = $DB->q('MAYBETUPLE SELECT * FROM team_affiliation WHERE affilid = %s', $id);
        if (!$row) {
            error("Missing or invalid affiliation id");
        }

        echo "<tr><td>Affiliation ID:</td><td>" .
            addHidden('keydata[0][affilid]', $row['affilid']) .
            specialchars($row['affilid']) . "</td></tr>\n";
    }

?>
<tr><td><label for="data_0__shortname_">Shortname:</label></td>
<td><?php echo addInput('data[0][shortname]', @$row['shortname'], 40, 30, 'required')?></td></tr>

<tr><td><label for="data_0__name_">Name:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 40, 255, 'required')?></td></tr>

<tr><td><label for="data_0__country_">Country:</label></td>
<td><?php echo addInput('data[0][country]', @$row['country'], 4, 3, 'pattern="[A-Z]{3}" title="three uppercase letters (ISO-3166-1 alpha-3)"')?>
<a target="_blank"
href="http://en.wikipedia.org/wiki/ISO_3166-1_alpha-3#Current_codes"><img
src="../images/b_help.png" class="smallpicto" alt="?" /></a></td></tr>

<tr><td><label for="data_0__comments_">Comments:</label></td>
<td><?php echo addTextArea('data[0][comments]', @$row['comments'])?></td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
    addHidden('table', 'team_affiliation') .
    addHidden('referrer', @$_GET['referrer']) .
    addSubmit('Save') .
    addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
    addEndForm();

require(LIBWWWDIR . '/footer.php');
exit;

endif;


$data = $DB->q('MAYBETUPLE SELECT * FROM team_affiliation WHERE affilid = %s', $id);
if (! $data) {
    error("Missing or invalid affiliation id");
}

$SHOW_FLAGS = dbconfig_get('show_flags', 1);

$affillogo = "images/affiliations/" . urlencode($data['affilid']) . ".png";
$countryflag = "images/countries/" . urlencode($data['country']) . ".png";

echo "<h1>Affiliation: ".specialchars($data['name'])."</h1>\n\n";

echo "<table>\n";
echo '<tr><td>ID:</td><td>' . specialchars($data['affilid']) . "</td></tr>\n";
echo '<tr><td>Shortname:</td><td>' . specialchars($data['shortname']) . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' . specialchars($data['name']) . "</td></tr>\n";

echo '<tr><td>Logo:</td><td>';

if (is_readable(WEBAPPDIR.'/web/'.$affillogo)) {
    echo '<img src="../' . $affillogo . '" alt="' .
        specialchars($data['shortname']) . "\" /></td></tr>\n";
} else {
    echo "not available</td></tr>\n";
}

if ($SHOW_FLAGS) {
    echo '<tr><td>Country:</td><td>' . specialchars($data['country']);

    if (is_readable(WEBAPPDIR.'/web/'.$countryflag)) {
        echo ' <img src="../' . $countryflag . '" alt="' .
            specialchars($data['country']) . "\" />";
    }
    echo "</td></tr>\n";
}

if (!empty($data['comments'])) {
    echo '<tr><td>Comments:</td><td>' .
        nl2br(specialchars($data['comments'])) . "</td></tr>\n";
}

echo "</table>\n\n";

if (IS_ADMIN) {
    echo "<p>" .
        editLink('team_affiliation', $data['affilid']) . "\n" .
        delLink('team_affiliation', 'affilid', $data['affilid'], $data['name']) . "</p>\n\n";
}

echo "<h2>Teams from " . specialchars($data['name']) . "</h2>\n\n";

$listteams = array();
$teams = $DB->q('SELECT teamid,name FROM team WHERE affilid = %s', $id);
if ($teams->count() == 0) {
    echo "<p class=\"nodata\">no teams</p>\n\n";
} else {
    echo "<table class=\"list\">\n<thead>\n" .
        "<tr><th scope=\"col\">ID</th><th scope=\"col\">teamname</th></tr>\n" .
        "</thead>\n<tbody>\n";
    while ($team = $teams->next()) {
        $listteams[] = $team['teamid'];
        $link = '<a href="team.php?id=' . urlencode($team['teamid']) . '">';
        echo "<tr><td>" .
        $link . "t" .specialchars($team['teamid']) . "</a></td><td>" .
        $link . specialchars($team['name']) . "</a></td></tr>\n";
    }
    echo "</tbody>\n</table>\n\n";

    putTeamRow($cdata, $listteams);
}

require(LIBWWWDIR . '/footer.php');
