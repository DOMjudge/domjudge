<?php
/**
 * Common functions shared between team/public/jury interface
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Return the base URI for the DOMjudge Webinterface.
 * This is the full URL to the root of the 'www' dir,
 * including the trailing slash. Examples:
 * http://domjudge.example.com/
 * http://www.example.edu/contest/domjudge/
 */
function getBaseURI() {
	return WEBBASEURI;
}

/**
 * Print a list of submissions, either all or only those that
 * match <key> = <value>. Output is always limited to the
 * current or last contest.
 */
function putSubmissions($restrictions, $isjury = FALSE) {

	global $DB;
	
	/* We need two kind of queries: one for all submissions, and one
	 * with the results for the valid ones. Restrictions is an array
	 * of key/value pairs, to which the complete list of submissions
	 * is restricted.
	 */

	$contdata = getCurContest(TRUE);
	$cid = $contdata['cid'];
	
	$res = $DB->q('SELECT s.submitid, s.teamid, s.probid, s.langid,
				   s.submittime, s.judgehost, t.name AS teamname,
				   p.name AS probname, l.name AS langname,
				   j.result, j.judgehost, j.verified
	               FROM submission s
	               LEFT JOIN team     t ON (t.login    = s.teamid)
	               LEFT JOIN problem  p ON (p.probid   = s.probid)
	               LEFT JOIN language l ON (l.langid   = s.langid)
				   LEFT JOIN judging  j ON (s.submitid = j.submitid AND valid=1)
	               WHERE s.cid = %i ' .
	               (!empty($restrictions['teamid']) ? 'AND s.teamid = %s ' : '%_') .
	               (!empty($restrictions['probid']) ? 'AND s.probid = %s ' : '%_') .
	               (!empty($restrictions['langid']) ? 'AND s.langid = %s ' : '%_') .
	               (!empty($restrictions['judgehost']) ? 'AND s.judgehost = %s ' : '%_') .
	               'ORDER BY s.submittime DESC, s.submitid DESC',
	               $cid, @$restrictions['teamid'], @$restrictions['probid'],
	               @$restrictions['langid'], @$restrictions['judgehost']);

	// nothing found...
	if( $res->count() == 0 ) {
		echo "<p><em>No submissions</em></p>\n\n";
		return;
	}
	
	// print the table with the submissions. 
	// table header; leave out the field that is our key (because it's the same
	// for all rows)
	echo "<table class=\"list\">\n<thead>\n<tr>" .
		($isjury ? "<th scope=\"col\">ID</th>" : '') .
		"<th scope=\"col\">time</th><th scope=\"col\">team</th>" .
		"<th scope=\"col\">problem</th><th scope=\"col\">lang</th>" .
		"<th scope=\"col\">status</th>" .
		($isjury ? "<th scope=\"col\">verified</th><th scope=\"col\">last<br />judge</th>" : '') .
		"</tr>\n</thead>\n<tbody>\n";
	
	// print each row with links to detailed information
	$subcnt = $corcnt = 0;
	while( $row = $res->next() ) {
		
		$sid = (int)$row['submitid'];
		$isfinished = ($isjury || ! $row['result']);
		
		echo "<tr>";
		if ( $isjury ) {
			echo "<td><a href=\"submission.php?id=$sid\">s$sid</a></td>";
		}
		echo "<td>" . printtime($row['submittime']) . "</td>";
		echo '<td class="teamid" title="' . htmlentities($row['teamname']) . '">' .
			( $isjury ? '<a href="team.php?id=' . urlencode($row['teamid']) . '">' : '' ) .
			htmlspecialchars($row['teamid']) . ( $isjury ? '</a>' : '') . '</td>';
		echo '<td title="' . htmlentities($row['probname']) . '">' .
			( $isjury ? '<a href="problem.php?id=' . urlencode($row['probid']) . '">' : '' ) .
			htmlspecialchars($row['probid']) . ( $isjury ? '</a>' : '') . '</td>';
		echo '<td title="' . htmlentities($row['langname']) . '">' .
			( $isjury ? '<a href="language.php?id=' . $row['langid'] . '">' : '' ) .
			htmlspecialchars($row['langid']) . ( $isjury ? '</a>' : '') . '</td>';
		echo "<td>";
		if ( $isjury ) {
			if ( ! $row['result'] ) {
				if ( $row['submittime'] > $contdata['endtime'] ) {
					echo printresult('too-late', TRUE, TRUE);
				} else {
					echo printresult($row['judgehost'] ? '' : 'queued', TRUE, TRUE);
				}
			} else {
				echo '<a href="submission.php?id=' . $sid . '">' .
					printresult($row['result']) . '</a>';
			}
		} else {
			if ( ! $row['result'] ||
				 ( VERIFICATION_REQUIRED && ! $row['verified'] ) ) {
				if ( $row['submittime'] > $contdata['endtime'] ) {
					echo printresult('too-late');
				} else {
					echo printresult('', TRUE, FALSE);
				}
			} else {
				echo '<a href="submission_details.php?id=' . $sid . '">';
				echo printresult($row['result']) . '</a>';
			}
		}
		echo "</td>";
		if ( $isjury && isset($row['verified']) ) {
			echo "<td>" . printyn($row['verified']) . "</td>";
		}
		if ( $isjury ) {
			$judgehost = $row['judgehost'];
			if ( empty($judgehost) ) {
				echo '<td></td>';
			} else {
				echo '<td><a href="judgehost.php?id=' . urlencode($judgehost) . '">' .
					printhost($judgehost) . '</a></td>';
			}
		}
		echo "</tr>\n";
		
		$subcnt++;
		if ( $row['result'] == 'correct' ) $corcnt++;
	}
	echo "</tbody>\n</table>\n\n";

	if ( $isjury ) {
		echo "<p>Total correct: $corcnt, submitted: $subcnt</p>\n\n";
	}

	return;
}

/**
 * Output team information (for team and public interface)
 */
function putTeam($login) {

	global $DB;

	$team = $DB->q('MAYBETUPLE SELECT t.*, c.name AS catname,
	                a.name AS affname, a.country FROM team t
	                LEFT JOIN team_category c USING (categoryid)
	                LEFT JOIN team_affiliation a ON (t.affilid = a.affilid)
	                WHERE login = %s', $login);

	if ( empty($team) ) error ("No team found by this id.");

	$affillogo = "../images/affiliations/" . urlencode($team['affilid']) . ".png";
	$countryflag = "../images/countries/" . urlencode($team['country']) . ".png";
	$teamimage = "../images/teams/" . urlencode($team['login']) . ".jpg";

	if ( is_readable($teamimage) ) {
		echo '<img id="teampicture" src="' . $teamimage .
			'" alt="Picture of team ' .
			htmlentities($team['name']) . '" />';
	}

	echo "<h1>Team ".htmlentities($team['name'])."</h1>\n\n";
?>

<table>
<tr><td scope="row">Name:    </td><td><?=htmlentities($team['name'])?></td></tr>
<tr><td scope="row">Category:</td><td><?=htmlentities($team['catname'])?></td></tr>
<?php
	 
	if ( !empty($team['members']) ) {
		echo '<tr><td valign="top" scope="row">Members:</td><td>' .
			nl2br(htmlentities($team['members'])) . "</td></tr>\n";
	}
	
	if ( !empty($team['affilid']) ) {
		echo '<tr><td scope="row">Affiliation:</td><td>';
		if ( is_readable($affillogo) ) {
			echo '<img src="' . $affillogo . '" alt="' .
				htmlspecialchars($team['affilid']) . '" /> ';
		} else {
			echo htmlspecialchars($team['affilid']) . ' - ';
		}
		echo htmlentities($team['affname']);
		echo "</td></tr>\n";
		if ( !empty($team['country']) ) {
			echo '<tr><td scope="row">Country:</td><td>';
			if ( is_readable($countryflag) ) {
				echo '<img src="' . $countryflag . '" alt="' .
					htmlspecialchars($team['country']) . '" /> ';
			}
			echo htmlspecialchars($team['country']) . "</td></tr>\n";
		}
	}
	
	if ( !empty($team['room']) ) {
		echo '<tr><td scope="row">Room:</td><td>' .
			htmlentities($team['room']) . "</td></tr>\n";
	}
	
	echo "</table>\n\n";
}

/**
 * Output clock
 */
function putClock() {
	echo '<div id="clock">' . strftime('%a %e %b %Y %T') . "</div>\n\n";
}

/**
 * Output a footer for pages containing the DOMjudge version and server host/port.
 */
function putDOMjudgeVersion() {
	echo "<hr /><address>DOMjudge/" . DOMJUDGE_VERSION . 
		" at ".$_SERVER['SERVER_NAME']." Port ".$_SERVER['SERVER_PORT']."</address>\n";
}

/**
 * Check whether the logged in user has DOMjudge administrator level,
 * as defined in passwords.php. If not, error and stop further execution.
 */
function requireAdmin() {
	if ( ! IS_ADMIN ) error ("This function is only accessible to administrators.");
}
