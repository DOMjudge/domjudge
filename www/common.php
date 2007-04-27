<?php

/**
 * Common functions shared between team/public/jury interface
 *
 * $Id$
 */

/**
 * Return the base URI for the DOMjudge Webinterface.
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
	
	$res = $DB->q('SELECT s.submitid, s.teamid, s.probid, s.langid, s.submittime, s.judgehost,
	               t.name AS teamname, p.name AS probname, l.name AS langname
	               FROM submission s
	               LEFT JOIN team     t ON (t.login  = s.teamid)
	               LEFT JOIN problem  p ON (p.probid = s.probid)
	               LEFT JOIN language l ON (l.langid = s.langid)
	               WHERE s.cid = %i ' .
	               (!empty($restrictions['teamid']) ? 'AND s.teamid = %s ' : '%_') .
	               (!empty($restrictions['probid']) ? 'AND s.probid = %s ' : '%_') .
	               (!empty($restrictions['langid']) ? 'AND s.langid = %s ' : '%_') .
	               (!empty($restrictions['judgehost']) ? 'AND s.judgehost = %s ' : '%_') .
	               'ORDER BY s.submittime DESC',
	               $cid, @$restrictions['teamid'], @$restrictions['probid'],
	               @$restrictions['langid'], @$restrictions['judgehost']);

	// nothing found...
	if( $res->count() == 0 ) {
		echo "<p><em>No submissions</em></p>\n\n";
		return;
	}
	
	$resulttable = $DB->q('KEYTABLE SELECT j.*, submitid AS ARRAYKEY
	                       FROM judging j WHERE valid = 1 AND cid = %i',$cid);
	
	// print the table with the submissions. 
	// table header; leave out the field that is our key (because it's the same
	// for all rows)
	echo "<table class=\"list\">\n<tr>" .
		($isjury ? "<th>ID</th>" : '') .
		"<th>time</th><th>team</th>" .
		"<th>problem</th><th>lang</th><th>status</th>" .
		($isjury ? "<th>verified</th><th>last<br />judge</th>" : '') .
		"</tr>\n";
	
	// print each row with links to detailed information
	$subcnt = $corcnt = 0;
	while( $row = $res->next() ) {
		$sid = (int)$row['submitid'];
		$isfinished = ($isjury || ! @$resulttable[$sid]['result']);
		
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
			if ( ! @$resulttable[$sid]['result'] ) {
				if ( $row['submittime'] > $contdata['endtime'] ) {
					echo printresult('too-late', TRUE, TRUE);
				} else {
					echo printresult(@$row['judgehost'] ? '' : 'queued', TRUE, TRUE);
				}
			} else {
				echo '<a href="judging.php?id=' . urlencode($resulttable[$sid]['judgingid'])
					. '">' . printresult(@$resulttable[$sid]['result']) . '</a>';
			}
		} else {
			if ( ! @$resulttable[$sid]['result'] ||
				 ( VERIFICATION_REQUIRED && ! @$resulttable[$sid]['verified'] ) ) {
				if ( $row['submittime'] > $contdata['endtime'] ) {
					echo printresult('too-late');
				} else {
					echo printresult('', TRUE, FALSE);
				}
			} else {
				echo '<a href="submission_details.php?id=' . $sid . '">';
				echo printresult(@$resulttable[$sid]['result']) . '</a>';
			}
		}
		echo "</td>";
		if ( $isjury && isset($resulttable[$sid]['verified']) ) {
			echo "<td>" . printyn(@$resulttable[$sid]['verified']) . "</td>";
		}
		if ( $isjury ) {
			$judgehost = @$resulttable[$sid]['judgehost'];
			if ( empty($judgehost) ) {
				echo '<td></td>';
			} else {
				echo '<td><a href="judgehost.php?id=' . urlencode($judgehost) . '">' .
					printhost($judgehost) . '</a></td>';
			}
		}
		echo "</tr>\n";
		
		$subcnt++;
		if ( @$resulttable[$sid]['result'] == 'correct' ) $corcnt++;
	}
	echo "</table>\n\n";

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

	$team = $DB->q('TUPLE SELECT t.*, c.name AS catname,
	                a.name AS affname, a.country FROM team t
	                LEFT JOIN team_category c USING (categoryid)
	                LEFT JOIN team_affiliation a ON (t.affilid = a.affilid)
	                WHERE login = %s', $login);

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
<tr><td>Name:    </td><td><?=htmlentities($team['name'])?></td></tr>
<tr><td>Category:</td><td><?=htmlentities($team['catname'])?></td></tr>
<?php
	 
	if ( !empty($team['members']) ) {
		echo '<tr><td valign="top">Members:</td><td>' .
			nl2br(htmlentities($team['members'])) . "</td></tr>\n";
	}
	
	if ( !empty($team['affilid']) ) {
		echo '<tr><td>Affiliation:</td><td>';
		if ( is_readable($affillogo) ) {
			echo '<img src="' . $affillogo . '" alt="' .
				htmlspecialchars($team['affilid']) . '" /> ';
		} else {
			echo htmlspecialchars($team['affilid']) . ' - ';
		}
		echo htmlentities($team['affname']);
		echo "</td></tr>\n";
		if ( !empty($team['country']) ) {
			echo '<tr><td>Country:</td><td>';
			if ( is_readable($countryflag) ) {
				echo '<img src="' . $countryflag . '" alt="' .
					htmlspecialchars($team['country']) . '" /> ';
			}
			echo htmlspecialchars($team['country']) . "</td></tr>\n";
		}
	}
	
	if ( !empty($team['room']) ) {
		echo '<tr><td>Room:</td><td>' . htmlentities($team['room']) .
			"</td></tr>\n";
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
 * Check for DOMjudge admin level, and if not, error.
 */
function requireAdmin() {
	if ( ! IS_ADMIN ) error ("This function is only accessible to administrators.");
}
