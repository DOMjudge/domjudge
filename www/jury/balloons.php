<?php
/**
 * Tool to coordinate the handing out of balloons to teams that solved
 * a problem. Similar to the balloons-daemon, but web-based.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$REQUIRED_ROLES = array('jury','balloon');
require('init.php');
$title = 'Balloon Status';

if ( isset($_POST['done']) ) {
	foreach($_POST['done'] as $done => $dummy) {
		$DB->q('UPDATE balloon SET done=1 WHERE balloonid = %i', $done);
		auditlog('balloon', $done, 'marked done');
	}
	header('Location: balloons.php');
}

$viewall = TRUE;

// Restore most recent view from cookie (overridden by explicit selection)
if ( isset($_COOKIE['domjudge_balloonviewall']) ) {
	$viewall = $_COOKIE['domjudge_balloonviewall'];
}

// Did someone press the view button?
if ( isset($_REQUEST['viewall']) ) $viewall = $_REQUEST['viewall'];

dj_setcookie('domjudge_balloonviewall', $viewall);

$refresh = '15;url=balloons.php';
require(LIBWWWDIR . '/header.php');

echo "<h1>Balloon Status</h1>\n\n";

foreach ($cdatas as $cdata) {
	if ( isset($cdata['freezetime']) &&
	     difftime($cdata['freezetime'], now()) <= 0
	) {
		echo "<h4>Scoreboard of c${cdata['cid']} (${cdata['shortname']}) is now frozen.</h4>\n\n";
	}
}

echo addForm($pagename, 'get') . "<p>\n" .
    addHidden('viewall', ($viewall ? 0 : 1)) .
    addSubmit($viewall ? 'view unsent only' : 'view all') . "</p>\n" .
    addEndForm();

$contestids = $cids;
if ( $cid !== null ) {
	$contestids = array($cid);
}

// Problem metadata: colours and names.
if ( empty($cids) ) {
	$probs_data = array();
} else {
	$probs_data = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,name,color,cid
	                      FROM problem
	                      INNER JOIN contestproblem USING (probid)
	                      WHERE cid IN (%Ai)', $contestids);
}

$freezecond = array();
if ( !dbconfig_get('show_balloons_postfreeze',0)) {
	foreach ($cdatas as $cdata) {
		if ( isset($cdata['freezetime']) ) {
			$freezecond[] = '(submittime <= "' . $cdata['freezetime'] . '" AND s.cid = ' . $cdata['cid'] . ')';
		} else {
			$freezecond[] = '(s.cid = ' . $cdata['cid'] . ')';
		}
	}
}

if ( empty($freezecond) ) {
	$freezecond = '';
} else {
	$freezecond = 'AND (' . implode(' OR ', $freezecond) . ')';
}

// Get all relevant info from the balloon table.
// Order by done, so we have the unsent balloons at the top.
$res = null;
if ( !empty($contestids) ) {
	$res = $DB->q("SELECT b.*, s.submittime, p.probid, cp.shortname AS probshortname,
	               t.teamid, t.name AS teamname, t.room, c.name AS catname,
 	               s.cid, co.shortname
	               FROM balloon b
	               LEFT JOIN submission s USING (submitid)
	               LEFT JOIN problem p USING (probid)
	               LEFT JOIN contestproblem cp USING (probid, cid)
	               LEFT JOIN team t USING(teamid)
	               LEFT JOIN team_category c USING(categoryid)
	               LEFT JOIN contest co USING (cid)
	               WHERE s.cid IN (%Ai) $freezecond
	               ORDER BY done ASC, balloonid DESC",
	              $contestids);
}

/* Loop over the result, store the total of balloons for a team
 * (saves a query within the inner loop).
 * We need to store the rows aswell because we can only next()
 * once over the db result.
 */
$BALLOONS = $TOTAL_BALLOONS = array();
while ( !empty($contestids) && $row = $res->next() ) {
	$BALLOONS[] = $row;
	$TOTAL_BALLOONS[$row['teamid']][] = $row['probid'];

	// keep overwriting these variables - in the end they'll
	// contain the id's of the first balloon in each type
	$first_contest[$row['cid']] = $first_problem[$row['probid']] = $first_team[$row['teamid']] = $row['balloonid'];
}

if ( !empty($BALLOONS) ) {
	echo addForm($pagename);

	echo "<table class=\"list sortable balloons\">\n<thead>\n" .
	     "<tr><th class=\"sorttable_numeric\">ID</th>" .
	     "<th>time</th>" . ( count($contestids) > 1 ? "<th>contest</th>" : "") .
	     "<th>solved</th><th>team</th>" .
	     "<th></th><th>loc.</th><th>category</th><th>total</th>" .
	     "<th></th><th></th></tr>\n</thead>\n";

	foreach ( $BALLOONS as $row ) {

		if ( !$viewall && $row['done'] == 1 ) continue;

		// start a new row, 'disable' if balloon has been handed out already
		echo '<tr'  . ( $row['done'] == 1 ? ' class="disabled"' : '' ) . '>';
		echo '<td>b' . (int)$row['balloonid'] . '</td>';
		echo '<td>' . printtime( $row['submittime'] ) . '</td>';

		if ( count($contestids) > 1 ) {
			// contest of this problem, only when more than one active
			echo '<td>' . htmlspecialchars($row['shortname']) . '</td>';
		}

		// the balloon earned
		echo '<td class="probid">' .
			'<div class="circle" style="background-color: ' .
		    htmlspecialchars($probs_data[$row['probid']]['color']) .
			';"></div> ' . htmlspecialchars($row['probshortname']) . '</td>';

		// team name, location (room) and category
		echo '<td>t' . htmlspecialchars($row['teamid']) . '</td><td>' .
			htmlspecialchars($row['teamname']) . '</td><td>' .
			htmlspecialchars($row['room']) . '</td><td>' .
			htmlspecialchars($row['catname']) . '</td><td>';

		// list of balloons for this team
		sort($TOTAL_BALLOONS[$row['teamid']]);
		$TOTAL_BALLOONS[$row['teamid']] = array_unique($TOTAL_BALLOONS[$row['teamid']]);
		foreach($TOTAL_BALLOONS[$row['teamid']] as $prob_solved) {
			echo '<div title="' . htmlspecialchars($prob_solved) .
				'" class="circle" style="background-color: ' .
				htmlspecialchars($probs_data[$prob_solved]['color']) .
				';"></div> ';
		}
		echo '</td><td>';

		// 'done' button when balloon has yet to be handed out
		if ( $row['done'] == 0 ) {
			echo '<input type="submit" name="done[' .
				(int)$row['balloonid'] . ']" value="done" />';
		}

		echo '</td><td>';

		$comments = array();
		if ( $first_contest[$row['cid']] == $row['balloonid'] ) {
			$comments[] = 'first in contest';
		} else {
			if ( $first_team[$row['teamid']] == $row['balloonid'] ) {
				$comments[] = 'first for team';
			}
			if ( $first_problem[$row['probid']] == $row['balloonid'] ) {
				$comments[] = 'first for problem';
			}
		}
		echo implode('; ', $comments);

		echo "</td></tr>\n";
	}

	echo "</table>\n\n" . addEndForm();
} else {
	echo "<p class=\"nodata\">No correct submissions yet... keep posted!</p>\n\n";
}


require(LIBWWWDIR . '/footer.php');
