<?php
/**
 * View the languages
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Languages';

require(LIBWWWDIR . '/header.php');

echo "<h1>Languages</h1>\n\n";

$res = $DB->q('SELECT * FROM language ORDER BY name');

if( $res->count() == 0 ) {
	echo "<p><em>No languages defined</em></p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
		"<tr><th scope=\"col\">ID</th><th scope=\"col\">name</th>" .
		"<th scope=\"col\">extension</th><th scope=\"col\">allow<br />submit</th>" .
		"<th scope=\"col\">allow<br />judge</th><th scope=\"col\">timefactor</th>" .
		"</tr>\n</thead>\n<tbody>\n";

	while($row = $res->next()) {
		echo "<tr".
			( $row['allow_judge'] && $row['allow_submit'] ? '': ' class="disabled"').
			"><td><a href=\"language.php?id=".urlencode($row['langid'])."\">".
				htmlspecialchars($row['langid'])."</a>".
			"</td><td><a href=\"language.php?id=".urlencode($row['langid'])."\">".
				htmlspecialchars($row['name'])."</a>".
			"</td><td class=\"filename\">.".htmlspecialchars($row['extension']).
			"</td><td align=\"center\">".printyn($row['allow_submit']).
			"</td><td align=\"center\">".printyn($row['allow_judge']).
			"</td><td>".htmlspecialchars($row['time_factor']);
			if ( IS_ADMIN ) {
				echo "</td><td>" . 
					editLink('language', $row['langid']) . " " .
					delLink('language','langid',$row['langid']);
			}
		echo "</td></tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('language') . "</p>\n\n";
}


require(LIBWWWDIR . '/footer.php');
