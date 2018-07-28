<?php
/**
 * View the languages
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Languages';

require(LIBWWWDIR . '/header.php');

echo "<h1>Languages</h1>\n\n";

$res = $DB->q('SELECT * FROM language ORDER BY name');

if ($res->count() == 0) {
    echo "<p class=\"nodata\">No languages defined</p>\n\n";
} else {
    echo "<table class=\"list sortable\">\n<thead>\n" .
        "<tr><th scope=\"col\">ID/ext</th><th scope=\"col\">name</th>" .
        "<th scope=\"col\">entry<br />point</th>" .
        "<th scope=\"col\">allow<br />submit</th>" .
        "<th scope=\"col\">allow<br />judge</th><th scope=\"col\">timefactor</th>" .
        "<th scope=\"col\">extensions</th><th scope=\"col\"></th>" .
        "</tr>\n</thead>\n<tbody>\n";

    while ($row = $res->next()) {
        $link = '<a href="language.php?id=' . urlencode($row['langid']) . '">';
        echo "<tr".
            ($row['allow_submit'] ? '': ' class="disabled"').
            "><td>" . $link . specialchars($row['langid'])."</a>".
            "</td><td>" . $link . specialchars($row['name'])."</a>".
            "</td><td " . ($row['require_entry_point'] && !empty($row['entry_point_description']) ?
                ' class="has_tooltip" title="' . specialchars($row['entry_point_description']) . '"' :
                '') . ">" . $link .
                printyn($row['require_entry_point']) . "</a>" .
            "</td><td>" . $link .
                printyn($row['allow_submit']) . "</a>" .
            "</td><td>" . $link .
                printyn($row['allow_judge']) . "</a>" .
            "</td><td>" . $link . specialchars($row['time_factor']) . "</a>" .
            "</td><td>" . $link .
            specialchars(implode(', ', dj_json_decode($row['extensions']))) . "</a>";
        if (IS_ADMIN) {
            echo "</td><td class=\"editdel\">" .
                    editLink('language', $row['langid']) . "&nbsp;" .
                    delLink('language', 'langid', $row['langid']);
        }
        echo "</td></tr>\n";
    }
    echo "</tbody>\n</table>\n\n";
}

if (IS_ADMIN) {
    echo "<p>" . addLink('language') . "</p>\n\n";
}


require(LIBWWWDIR . '/footer.php');
