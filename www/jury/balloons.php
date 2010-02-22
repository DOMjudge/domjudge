<?php
/**
 * Tool to coordinate the handing out of balloons to teams that solved
 * a problem. Similar to the balloons-daemon, but web-based.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Balloon Status';

if ( isset($_POST['done']) ) {
	foreach($_POST['done'] as $done => $dummy) {
		$parts = explode(';', $done);
		$DB->q('UPDATE scoreboard_jury SET balloon=1
			WHERE probid = %s AND teamid = %s AND cid = %i',
			$parts[0], $parts[1], $parts[2]);
	}
	header('Location: balloons.php');
}

$refresh = '30;url=balloons.php';
require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/forms.php');

echo "<h1>Balloon Status</h1>\n\n";

if ( isset($cdata['freezetime']) &&
     time() > strtotime($cdata['freezetime']) ) {
	echo "<h4>Scoreboard is now frozen.</h4>\n\n";
}

// Problem metadata: colours and names.
$probs_data = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,name,color
		      FROM problem WHERE cid = %i', $cid);

// Get all relevant info from the scoreboard_jury table.
// Order by balloon, so we have the unsent balloons at the top.
// Then by submittime, so the newest will also rank highest.
$res = $DB->q('SELECT s.*,t.login,t.name as teamname,t.room
       FROM scoreboard_jury s
       LEFT JOIN team t ON (t.login = s.teamid)
       WHERE s.cid = %i AND s.is_correct = 1
       ORDER BY s.balloon, s.totaltime DESC',
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
}

$conteststart  = strtotime($cdata['starttime']);
if ( !empty($cdata['freezetime']) ) {
	$contestfreeze = strtotime($cdata['freezetime']);
}

if ( !empty($BALLOONS) ) {
	echo addForm('balloons.php');

	echo "<table class=\"list sortable balloons\">\n" .
		"<tr><th>Time</th><th>Solved</th><th colspan=\"2\">Team</th>\n" .
		"<th>Room</th><th>Total</th><th></th></tr>\n";

	foreach ( $BALLOONS as $row ) {

		// start a new row, 'disable' if balloon has been handed out already
		echo '<tr'  . ( $row['balloon'] == 1 ? ' class="disabled"' : '' ) . '>';

		// time the balloon was earned (contest start + total time (in minutes))
		// display an "F" after the time if this is after the freeze.
		$balloontime = $conteststart + ($row['totaltime']*60);
		$frozen = (isset($contestfreeze) && $balloontime >= $contestfreeze ?
			' <span title="After Scoreboard Freeze">F</span>' : '');
		
		echo '<td>' . printtime( date(MYSQL_DATETIME_FORMAT, $balloontime) ) .
			$frozen . '</td>';

		// the balloon earned
		echo '<td class="probid">' .
			'<img style="background-color: ' .
		    htmlspecialchars($probs_data[$row['probid']]['color']) .
			';" alt="problem colour ' . htmlspecialchars($probs_data[$row['probid']]['color']) .
		    '" src="../images/circle.png" /> ' . htmlspecialchars($row['probid']) . '</td>';

		// team name and room
		echo '<td class="teamid">' . htmlspecialchars($row['login']) . '</td><td>' .
			htmlspecialchars($row['teamname']) . '</td><td>' .
			htmlspecialchars($row['room']) . '</td><td>';

		// list of balloons for this team
		sort($TOTAL_BALLOONS[$row['login']]);
		foreach($TOTAL_BALLOONS[$row['login']] as $prob_solved) {
			echo '<img title="' . htmlspecialchars($prob_solved) .
				'" style="background-color: ' .
				htmlspecialchars($probs_data[$prob_solved]['color']) .
				';" alt="problem colour ' .
				htmlspecialchars($probs_data[$row['probid']]['color']) .
				'" src="../images/circle.png" /> ';
		}
		echo '</td><td>';

		// 'done' button when balloon has yet to be handed out
		if ( $row['balloon'] == 0 ) {
			echo '<input type="submit" name="done[' .
				htmlspecialchars($row['probid']) . ';' .
				htmlspecialchars($row['teamid']) . ';' .
				htmlspecialchars($row['cid']) . ']" value="done" />';
		}
		echo "</td></tr>\n";
	}

	echo "</table>\n\n" . addEndForm();
} else {
	echo "<p><em>No correct submissions yet... keep posted!</em></p>\n\n";
}


require(LIBWWWDIR . '/footer.php');
