<?php

/**
 * Recalculate scoreboard cache data in DOMjudge.
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

$contests = getCurContests(FALSE);

if ( isset($_REQUEST['cid']) ) {
	$contests = array($_REQUEST['cid']);
} elseif ( isset($_COOKIE['domjudge_cid']) && $_COOKIE['domjudge_cid']>=1 )  {
	$contests = array($_COOKIE['domjudge_cid']);
}

if ( ! isset($_REQUEST['refresh']) ) {
	if ( count($contests)==1 ) {
		$cname = $DB->q('VALUE SELECT shortname FROM contest
		                 WHERE cid = %i', reset($contests));
	}
	echo addForm($pagename);
	echo msgbox('Significant database impact',
	       'Refreshing the scoreboard cache can have a significant impact on the database load, ' .
	       'and is not necessary in normal operating circumstances.<br /><br />' .
	       'Refresh scoreboard cache for ' .
	       ( count($contests)==1 ? "contest '$cname'" : 'all active contests' ) .
	       ' now?<br /><br />' .
	       addSubmit(" Refresh now! ", 'refresh') );
        echo addEndForm();

	require(LIBWWWDIR . '/footer.php');
	exit;
}

$time_start = microtime(TRUE);

auditlog('scoreboard', null, 'refresh cache');

// no output buffering... we want to see what's going on real-time
ob_implicit_flush();

foreach ($contests as $contest) {
	// get the contest, teams and problems
	$teams = $DB->q('TABLE SELECT t.teamid FROM team t
	                 INNER JOIN contest c ON c.cid = %i
	                 LEFT JOIN contestteam ct ON ct.teamid = t.teamid AND ct.cid = c.cid
	                 WHERE (c.public = 1 OR ct.teamid IS NOT NULL) ORDER BY teamid',
	                $contest);
	$probs = $DB->q('TABLE SELECT probid, cid FROM problem
	                 INNER JOIN contestproblem USING (probid)
	                 WHERE cid = %i ORDER BY shortname',
	                $contest);

	echo "<p>Recalculating all values for the scoreboard cache for contest c$contest (" .
	     count($teams) . " teams, " . count($probs) . " problems)...</p>\n\n<pre>\n";

	if ( count($teams) == 0 ) {
		echo "No teams defined, doing nothing.</pre>\n\n";
		continue;
	}
	if ( count($probs) == 0 ) {
		echo "No problems defined, doing nothing.</pre>\n\n";
		continue;
	}

// for each team, fetch the status of each problem
	foreach ($teams as $team) {

		echo "Team t" . htmlspecialchars($team['teamid']) . ":";

		// for each problem fetch the result
		foreach ($probs as $pr) {
			echo " p" . htmlspecialchars($pr['probid']);
			calcScoreRow($pr['cid'], $team['teamid'], $pr['probid']);
		}

		// Now recompute the rank for both jury and public
		echo " rankcache";
		updateRankCache($contest, $team['teamid'], true);
		updateRankCache($contest, $team['teamid'], false);

		echo "\n";
		ob_flush();
	}

	echo "</pre>\n\n";
}

echo "<p>Deleting irrelevant data...</p>\n\n";

// Drop all teams and problems that do not exist in each contest
foreach ($contests as $contest) {
	$probids = $DB->q('COLUMN SELECT probid FROM problem
	                   INNER JOIN contestproblem USING (probid)
	                   WHERE cid = %i ORDER BY shortname', $contest);
	$teamids = $DB->q('COLUMN SELECT t.teamid FROM team t
	                   INNER JOIN contest c ON c.cid = %i
	                   LEFT JOIN contestteam ct ON ct.teamid = t.teamid AND ct.cid = c.cid
	                   WHERE (c.public = 1 OR ct.teamid IS NOT NULL) ORDER BY teamid',
	                  $contest);
	// probid -1 will never happen, but otherwise the array is empty and that is not supported
	if ( empty($probids) ) {
		$probids = array(-1);
	}
	// Same for teamids
	if ( empty($teamids) ) {
		$teamids = array(-1);
	}
	// drop all contests that are not current, teams and problems that do not exist
	$DB->q('DELETE FROM scorecache_jury   WHERE cid = %i AND probid NOT IN (%Ai)',
	       $contest, $probids);
	$DB->q('DELETE FROM scorecache_public WHERE cid = %i AND probid NOT IN (%Ai)',
	       $contest, $probids);
	$DB->q('DELETE FROM scorecache_jury   WHERE cid = %i AND teamid NOT IN (%Ai)',
	       $contest, $teamids);
	$DB->q('DELETE FROM scorecache_public WHERE cid = %i AND teamid NOT IN (%Ai)',
	       $contest, $teamids);

	$DB->q('DELETE FROM rankcache_jury   WHERE cid = %i AND teamid NOT IN (%Ai)',
	       $contest, $teamids);
	$DB->q('DELETE FROM rankcache_public WHERE cid = %i AND teamid NOT IN (%Ai)',
	       $contest, $teamids);
}

$time_end = microtime(TRUE);

echo "<p>Scoreboard cache refresh completed in ".round($time_end - $time_start,2)." seconds.</p>\n\n";

require(LIBWWWDIR . '/footer.php');
