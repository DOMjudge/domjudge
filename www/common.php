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
	
	$keyvalmatch = '';
	foreach ( $restrictions as $restriction ) {
		$keyvalmatch .= ' s.' . $restriction['key'] . ' = "' .
			mysql_escape_string($restriction['value']) . '" AND';
	}

	$contdata = getCurContest(TRUE);
	$cid = $contdata['cid'];
 
	$res = $DB->q('SELECT s.submitid, s.team, s.probid, s.langid, s.submittime,
		s.judgehost, t.name as teamname, p.name as probname, l.name as langname
		FROM submission s
		LEFT JOIN team t ON(t.login=s.team)
		LEFT JOIN problem p ON(p.probid=s.probid)
		LEFT JOIN language l ON(l.langid=s.langid)
		WHERE ' . $keyvalmatch . ' s.cid = %i ORDER BY s.submittime DESC',$cid);

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
			( $isjury ? '<a href="team.php?id=' . urlencode($row['team']) . '">' : '' ) .
			htmlspecialchars($row['team']) . ( $isjury ? '</a>' : '') . '</td>';
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
