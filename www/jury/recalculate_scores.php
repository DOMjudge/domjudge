<?php

/**
 * Recalculate all values for the scoreboard from scratch. Use this
 * sparingly since it requires (3 x #teams x #problems) queries.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Recalculate Scoreboard Cache';
include('../header.php');
require('../scoreboard.php');

requireAdmin();

// no output buffering... we want to see what's going on real-time
ob_implicit_flush();

echo "<h1>Recalculate Scoreboard Cache</h1>\n\n";

// get the contest, teams and problems
$cid = getCurContest();
$teams = $DB->q('COLUMN SELECT login FROM team ORDER BY login');
$probs = $DB->q('COLUMN SELECT probid FROM problem
                 WHERE cid = %i ORDER BY probid', $cid);

echo "<p>Recalculating all values for the scoreboard cache (" .
	count($teams) . " teams, " . count($probs) ." problems, contest c" .
	htmlspecialchars($cid) . ")...</p>\n\n<pre>\n";

if ( count($teams) == 0 ) {
	echo "No teams defined, doing nothing.</pre>\n\n";
	include('../footer.php');
	exit;
}
if ( count($probs) == 0 ) {
	echo "No problems defined, doing nothing.</pre>\n\n";
	include('../footer.php');
	exit;
}

// for each team, fetch the status of each problem
foreach( $teams as $team ) {

	echo "Team " . htmlspecialchars($team) . ":";

	// for each problem fetch the result
	foreach( $probs as $pr ) {
		echo " " .htmlspecialchars($pr);
		calcScoreRow($cid, $team, $pr);
	}

	echo "\n";
}

echo "</pre>\n\n<p>Deleting irrelevant data...</p>\n\n";

// drop all contests that are not current, teams and problems that do not exist
$DB->q('DELETE FROM scoreboard_jury
        WHERE cid != %i OR teamid NOT IN (%As) OR probid NOT IN (%As)',
       $cid, $teams, $probs);
$DB->q('DELETE FROM scoreboard_public
        WHERE cid != %i OR teamid NOT IN (%As) OR probid NOT IN (%As)',
       $cid, $teams, $probs);

echo "<p>Finished.</p>\n\n";

include('../footer.php');
