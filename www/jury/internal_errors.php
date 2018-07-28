<?php
/**
 * View the internal errors
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Internal Errors';
$refresh = array(
    'after' => 15,
    'url' => 'internal_errors.php',
);

require(LIBWWWDIR . '/header.php');

echo "<h1>Internal Errors</h1>\n\n";

$res = $DB->q('SELECT errorid, judgingid, description, time, status
               FROM internal_error
               ORDER BY status, errorid');

if ($res->count() == 0) {
    echo "<p class=\"nodata\">No internal errors found</p>\n\n";
} else {
    echo "<table class=\"list sortable\">\n<thead>\n" .
         "<tr><th scope=\"col\">ID</th>" .
         "<th scope=\"col\">jid</th>" .
         "<th scope=\"col\">description</th>" .
         "<th scope=\"col\">time</th>" .
         "<th scope=\"col\">status</th></tr>\n" .
         "</thead>\n<tbody>\n";
    while ($row = $res->next()) {
        $link = '<a href="internal_error.php?id=' . urlencode($row['errorid']) . '">';
        $class = '';
        if ($row['status'] != 'open') {
            $class = 'class="disabled"';
        } else {
            $class = 'class="unseen"';
        }
        echo "<tr $class>" .
            "<td>" . $link . ($row['errorid']) . '</a></td>' .
            "<td>" . $link . (empty($row['judgingid']) ? '' : 'j' . $row['judgingid']) . '</a></td>' .
            "<td>" . $link . specialchars($row['description']) .  "</a></td>" .
            "<td>" . $link . printtime($row['time'], '%F %T') .  "</a></td>" .
            "<td>" . $link . ($row['status']) . '</a></td>' .
            "</tr>\n";
    }
    echo "</tbody>\n</table>\n\n";
}

require(LIBWWWDIR . '/footer.php');
