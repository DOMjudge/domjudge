<?php
/**
 * Tool to coordinate the handing out of balloons to teams that solved
 * a problem. Similar to the balloons-daemon, but web-based.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Balloon Status';

if ( isset($_POST['done']) ) {
	foreach($_POST['done'] as $done => $dummy) {
		$DB->q('UPDATE balloon SET done=1
			WHERE balloonid = %i',
			$done);
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

setcookie('domjudge_balloonviewall', $viewall);

$refresh = '30;url=balloons.php';
require(LIBWWWDIR . '/header.php');

echo "<h1>Balloon Status</h1>\n\n";

if ( isset($cdata['freezetime']) &&
     time() > strtotime($cdata['freezetime']) ) {
	echo "<h4>Scoreboard is now frozen.</h4>\n\n";
}

echo addForm('balloons.php', 'get') . "<p>\n" .
    addHidden('viewall', ($viewall ? 0 : 1)) .
    addSubmit($viewall ? 'view unsent only' : 'view all') . "</p>\n" .
    addEndForm();

// Problem metadata: colours and names.
$probs_data = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,name,color
		      FROM problem WHERE cid = %i', $cid);

// Get all relevant info from the balloon table.
// Order by done, so we have the unsent balloons at the top.
$res = $DB->q('SELECT b.*, s.probid, s.submittime,
               t.login, t.name AS teamname, t.room, c.name AS catname
               FROM balloon b
               LEFT JOIN submission s USING (submitid)
               LEFT JOIN team t ON (t.login = s.teamid)
               LEFT JOIN team_category c USING(categoryid)
               WHERE s.cid = %i
               ORDER BY done ASC, balloonid DESC',
              $cid);

/* Loop over the result, store the total of balloons for a team
 * (saves a query within the inner loop).
 * We need to store the rows aswell because we can only next()
 * once over the db result.
 */
$BALLOONS = $TOTAL_BALLOONS = array();
while ( $row = $res->next() ) {
	$BALLOONS[] = $row;
	$TOTAL_BALLOONS[$row['login']][] = $row['probid'];

	// keep overwriting these variables - in the end they'll
	// contain the id's of the first balloon in each type
	$first_contest = $first_problem[$row['probid']] = $first_team[$row['login']] = $row['balloonid'];
}

$conteststart  = strtotime($cdata['starttime']);
if ( !empty($cdata['freezetime']) ) {
	$contestfreeze = strtotime($cdata['freezetime']);
}

if ( !empty($BALLOONS) ) {
	echo addForm('balloons.php');

	echo "<table class=\"list sortable balloons\">\n<thead>\n" .
		"<tr><td></td><th class=\"sorttable_numeric\">ID</th>" .
	        "<th>time</th><th>solved</th><th align=\"right\">team</th>" .
	        "<th></th><th>loc.</th><th>category</th><th>total</th>" .
	        "<th></th><th></th></tr>\n</thead>\n";

	foreach ( $BALLOONS as $row ) {

		if ( !$viewall && $row['done'] == 1 ) continue;

		// start a new row, 'disable' if balloon has been handed out already
		echo '<tr'  . ( $row['done'] == 1 ? ' class="disabled"' : '' ) . '>';
		if ( isset($cdata['freezetime']) &&
		     $row['submittime'] > $cdata['freezetime'] ) {
			echo "<td>FROZEN</td>";
		} else {
			echo '<td></td>';
		}
		echo '<td>b' . (int)$row['balloonid'] . '</td>';
		echo '<td>' . printtime( $row['submittime'] ) . '</td>';

		// the balloon earned
		echo '<td class="probid">' .
			'<img class="balloonimage" style="background-color: ' .
		    htmlspecialchars($probs_data[$row['probid']]['color']) .
			';" alt="problem colour ' . htmlspecialchars($probs_data[$row['probid']]['color']) .
		    '" src="../images/circle.png" /> ' . htmlspecialchars($row['probid']) . '</td>';

		// team name, location (room) and category
		echo '<td class="teamid">' . htmlspecialchars($row['login']) . '</td><td>' .
			htmlspecialchars($row['teamname']) . '</td><td>' .
			htmlspecialchars($row['room']) . '</td><td>' .
			htmlspecialchars($row['catname']) . '</td><td>';

		// list of balloons for this team
		sort($TOTAL_BALLOONS[$row['login']]);
		$TOTAL_BALLOONS[$row['login']] = array_unique($TOTAL_BALLOONS[$row['login']]);
		foreach($TOTAL_BALLOONS[$row['login']] as $prob_solved) {
			echo '<img title="' . htmlspecialchars($prob_solved) .
				'" class="balloonimage" style="background-color: ' .
				htmlspecialchars($probs_data[$prob_solved]['color']) .
				';" alt="problem colour ' .
				htmlspecialchars($probs_data[$row['probid']]['color']) .
				'" src="../images/circle.png" /> ';
		}
		echo '</td><td>';

		// 'done' button when balloon has yet to be handed out
		if ( $row['done'] == 0 ) {
			echo '<input type="submit" name="done[' .
				(int)$row['balloonid'] . ']" value="done" />';
		}
		
		echo '</td><td>';

		$comments = array();
		if ( $first_contest == $row['balloonid'] ) {
			$comments[] = 'first in contest';
		} else {
			if ( $first_team[$row['login']] == $row['balloonid'] ) {
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
