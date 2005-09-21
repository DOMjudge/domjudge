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
function putSubmissions($key = null, $value = null, $isjury = FALSE) {

	global $DB;
	
	/* We need two kind of queries: one for all submissions, and one with
	 * the results for the valid ones. When key & value are supplied we're
	 * looking for the submissions of a specific team or judger, else the
	 * complete list.
	 */
	$keyvalmatch = '';
	if( $key && $value ) {
		$keyvalmatch = " s.$key = \"" . mysql_escape_string($value) . '" AND ';
	}

	$showverified = $isjury && SUBM_VERIFY;
	
	$contdata = getCurContest(TRUE);
	$cid = $contdata['cid'];
 
	$res = $DB->q('SELECT s.submitid, s.team, s.probid, s.langid, s.submittime,
		s.judgerid, t.name as teamname, p.name as probname, l.name as langname
		FROM submission s
		LEFT JOIN team t ON(t.login=s.team)
		LEFT JOIN problem p ON(p.probid=s.probid)
		LEFT JOIN language l ON(l.langid=s.langid)
		WHERE ' . $keyvalmatch . 's.cid = %i ORDER BY s.submittime DESC',$cid);

	// nothing found...
	if( $res->count() == 0 ) {
		echo "<p><em>No submissions</em></p>\n\n";
		return;
	}
	
	$resulttable = $DB->q('KEYTABLE SELECT j.*, submitid AS ARRAYKEY
		FROM judging j WHERE (valid = 1 OR valid IS NULL) AND cid = %i',$cid);
	
	// print the table with the submissions. 
	// table header; leave out the field that is our key (because it's the same
	// for all rows)
	echo "<table>\n<tr>" .
		( $isjury ? "<th>ID</th>" : '' ) .
		"<th>time</th>" .
		($key != 'team'   ? "<th>team</th>"    : '') .
		($key != 'probid' ? "<th>problem</th>" : '') .
		($key != 'langid' ? "<th>lang</th>"    : '') .
		"<th>status</th>" .
		($showverified ? "<th>verified</th>" : '') .
		($isjury ? "<th>last<br />judge</th>" : '') .
		"</tr>\n";
	// print each row with links to detailed information
	while( $row = $res->next() ) {
		$sid = (int)$row['submitid'];
		$isfinished = ($isjury || ! @$resulttable[$sid]['result']);
		echo "<tr>";
		if ( $isjury ) {
			echo "<td><a href=\"submission.php?id=$sid\">s$sid</a></td>";
		}
		echo "<td>" . printtime($row['submittime']) . "</td>";
		if ( $key != 'team' ) {
			echo '<td class="teamid" title="' . htmlentities($row['teamname']) . '">' .
				( $isjury ? '<a href="team.php?id=' . $row['team'] . '">' : '' ) .
				htmlspecialchars($row['team']) .
				( $isjury ? '</a>' : '') . '</td>';
		}
		if ( $key != 'probid' ) {
			echo '<td title="' . htmlentities($row['probname']) . '">' .
				( $isjury ? '<a href="problem.php?id=' . $row['probid'] . '">' : '' ) .
				htmlspecialchars($row['probid']) .
				( $isjury ? '</a>' : '') . '</td>';
		}
		if ( $key != 'langid' ) {
			echo '<td title="' . htmlentities($row['langname']) . '">' .
				( $isjury ? '<a href="language.php?id=' . $row['langid'] . '">' : '' ) .
				htmlspecialchars($row['langid']) .
				( $isjury ? '</a>' : '') . '</td>';
		}
		echo "<td>";
		if ( $isjury ) {
			if ( ! @$resulttable[$sid]['result'] ) {
				if ( $row['submittime'] > $contdata['endtime'] ) {
					echo printresult('too-late', TRUE, TRUE);
				} else {
					echo printresult(@$row['judgerid'] ? '' : 'queued', TRUE, TRUE);
				}
			} else {
				echo '<a href="judging.php?id=' . $resulttable[$sid]['judgingid'] . '">';
				echo printresult(@$resulttable[$sid]['result']) . '</a>';
			}
		} else {
			if ( ! @$resulttable[$sid]['result'] ||
				 ( SUBM_VERIFY==2 && ! @$resulttable[$sid]['verified'] ) ) {
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
		if ( $showverified ) {
			echo "<td>" . printyn(@$resulttable[$sid]['verified']) . "</td>";
		}
		if ( $isjury ) {
			$judger = @$resulttable[$sid]['judgerid'];
			echo '<td><a href="judger.php?id=' . urlencode($judger) . '">' .
				printhost($judger) . '</a></td>';
		}
		echo "</tr>\n";
	}
	echo "</table>\n\n";

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
