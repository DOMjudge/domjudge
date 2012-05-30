<?php

/**
 * Recalculate all cached data in DOMjudge:
 * - The scoreboard.
 * Use this sparingly since it requires
 * (3 x #teams x #problems) queries.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Refresh Cache';
require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

echo "<h1>Refresh Cache</h1>\n\n";

requireAdmin();

if ( ! isset($_REQUEST['refresh']) ) {
	echo addForm('');
	echo msgbox('Significant database impact',
	       'Refreshing the scoreboard cache can have a significant impact on the database load, ' .
	       'and is not necessary in normal operating circumstances.<br /><br />Refresh scoreboard cache now?' .
	       '<br /><br />' .
               addSubmit(" Refresh now! ", 'refresh') );
        echo addEndForm();

	require(LIBWWWDIR . '/footer.php');
	exit;	
}

$time_start = microtime(TRUE);

auditlog('scoreboard', null, 'refresh cache');

// no output buffering... we want to see what's going on real-time
ob_implicit_flush();

// get the contest, teams and problems
$teams = $DB->q('TABLE SELECT login FROM team ORDER BY login');
$probs = $DB->q('COLUMN SELECT probid FROM problem
                 WHERE cid = %i ORDER BY probid', $cid);

echo "<p>Recalculating all values for the scoreboard cache (" .
	count($teams) . " teams, " . count($probs) ." problems, contest c" .
	htmlspecialchars($cid) . ")...</p>\n\n<pre>\n";

if ( count($teams) == 0 ) {
	echo "No teams defined, doing nothing.</pre>\n\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}
if ( count($probs) == 0 ) {
	echo "No problems defined, doing nothing.</pre>\n\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

$teamlist = array();

// for each team, fetch the status of each problem
foreach( $teams as $team ) {

	$teamlist[] = $team['login'];

	echo "Team " . htmlspecialchars($team['login']) . ":";

	// for each problem fetch the result
	foreach( $probs as $pr ) {
		echo " " .htmlspecialchars($pr);
		calcScoreRow($cid, $team['login'], $pr);
	}

	echo "\n";
	ob_flush();
}

echo "</pre>\n\n<p>Deleting irrelevant data...</p>\n\n";

// drop all contests that are not current, teams and problems that do not exist
$DB->q('DELETE FROM scoreboard_jury
        WHERE cid != %i OR teamid NOT IN (%As) OR probid NOT IN (%As)',
       $cid, $teamlist, $probs);
$DB->q('DELETE FROM scoreboard_public
        WHERE cid != %i OR teamid NOT IN (%As) OR probid NOT IN (%As)',
       $cid, $teamlist, $probs);

$time_end = microtime(TRUE);

echo "<p>Scoreboard cache refresh completed in ".round($time_end - $time_start,2)." seconds.</p>\n\n";

require(LIBWWWDIR . '/footer.php');
