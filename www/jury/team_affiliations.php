<?php
/**
 * View all team affiliations
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Affiliations';

require(LIBWWWDIR . '/header.php');

echo "<h1>Affiliations</h1>\n\n";

$SHOW_FLAGS             = dbconfig_get('show_flags', 1);

$res = $DB->q('SELECT a.*, COUNT(teamid) AS cnt FROM team_affiliation a
               LEFT JOIN team USING (affilid)
               GROUP BY affilid ORDER BY name');

if ($res->count() == 0) {
    echo "<p class=\"nodata\">No affiliations defined</p>\n\n";
} else {
    echo "<table class=\"list sortable\">\n<thead>\n" .
        "<tr><th>ID</th>" .
        "<th>shortname</th>" .
        "<th>name</th>" .
        ($SHOW_FLAGS ? "<th>country</th>" : "") .
        "<th>#teams</th>" .
        "<th></th></tr>\n</thead>\n<tbody>\n";

    while ($row = $res->next()) {
        $countryflag = "images/countries/" . urlencode($row['country']) . ".png";
        $link = '<a href="team_affiliation.php?id=' . urlencode($row['affilid']) . '">';
        echo '<tr><td>' . $link . specialchars($row['affilid']) .
             '</a></td><td>' . $link . specialchars($row['shortname']) .
             '</a></td><td>' . $link . specialchars($row['name']) .
             '</a></td>';
        if ($SHOW_FLAGS) {
            echo '<td class="tdcenter">' . $link .
                specialchars($row['country']) .
                (is_readable(WEBAPPDIR.'/web/'.$countryflag) ? ' <img src="../' . $countryflag .
                 '" alt="' . specialchars($row['country']) . '" />' : '&nbsp;') .
                '</a></td>';
        }
        echo '<td class="tdright">' . $link .
             (int)$row['cnt'] .
             '</a></td>';
        if (IS_ADMIN) {
            echo "<td class=\"editdel\">" .
                editLink('team_affiliation', $row['affilid']) . "&nbsp;" .
                delLink('team_affiliation', 'affilid', $row['affilid'], $row['name']) . "</td>";
        }
        echo "</tr>\n";
    }
    echo "</tbody>\n</table>\n\n";
}

if (IS_ADMIN) {
    echo "<p>" . addLink('team_affiliation') . "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
