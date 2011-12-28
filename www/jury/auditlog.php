<?php

/**
 * Display the auditlog entries.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Activity log';
require(LIBWWWDIR . '/header.php');

requireAdmin();

echo "<h1>Activity log</h1>\n\n";

$res = $DB->q('SELECT * FROM auditlog ORDER BY logtime DESC');

if ( $res->count() == 0 ) {
	echo '<p class="nodata">No entries</p>';
	require(LIBWWWDIR . '/footer.php');
	exit;
}

echo "<table class=\"sortable\">\n" .
     "<tr><th>id</th><th>when</th><th>cid</th><th>who</th>" .
     "<th colspan=\"3\">what</th><th>extra info</th></tr>\n";
while ( $logline = $res->next() ) {
	echo "<tr><td>" .
	htmlspecialchars($logline['logid']) . "</td>" .
	"<td title=\"" . htmlspecialchars($logline['logtime']) . "\">" .
	printtime($logline['logtime']) . "</td><td>" .
	(empty($logline['cid'])?'':'c'.$logline['cid']) . "</td><td>" .
	htmlspecialchars($logline['user']) . "</td><td>" .
	htmlspecialchars($logline['datatype']) . "</td><td>";

	$tables = array ('balloon' => 'b',
		'clarification' => '',
		'contest' => 'c',
		'judgehost' => '',
		'judging' => 'j',
		'language' => '',
		'problem' => '',
		'submission' => 's',
		'team' => '',
		'team_affiliation' => '',
		'team_category' => '',
		'testcase' => '');

	if ( !empty($logline['dataid']) ) {
		echo "<a href=\"" .
		urlencode($logline['datatype']) . '.php?id=' .
		urlencode($logline['dataid']) . "\">" .
		$tables[$logline['datatype']] .
		htmlspecialchars($logline['dataid']) . "</a>";
	}

	echo "</td><td>" .
	htmlspecialchars($logline['action']) . "</td><td>" .
	htmlspecialchars($logline['extrainfo']) . "</td><td>" .
	"</td></tr>\n";
}
echo "</table>\n\n";


require(LIBWWWDIR . '/footer.php');
