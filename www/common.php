<?php

/**
 * Common functions shared between team/public/jury interface
 *
 * $Id$
 */

function getSubmissions($key = null, $value = null) {

	global $DB;

	// we need two queries: one for all submissions, and one with the results for the valid ones.
	if($key && $value) {
		$res = $DB->q('SELECT * FROM submission WHERE '.$key.' = %s ORDER BY submittime',
			$value);
	} else {
		$res = $DB->q('SELECT * FROM submission ORDER BY submittime');
	}

	if($res->count() == 0) {
		echo "<p><em>No submissions</em></p>\n\n";
		return;
	}

	$resulttable = $DB->q('KEYTABLE SELECT j.*,submitid AS ARRAYKEY,judger.name AS judgername
		FROM judging j LEFT JOIN judger USING(judgerid)
		WHERE (valid = 1 OR valid IS NULL)');

	echo "<table>\n".
		"<tr><th>ID".
		"</th><th>time".
		($key != 'team' ? "</th><th>team" : '').
		($key != 'probid' ? "</th><th>problem" : '').
		($key != 'langid' ? "</th><th>lang" : '').
		"</th><th>status".
		"</th><th>last<br>judge</th></tr>\n";
	while($row = $res->next()) {
		$sid = (int)$row['submitid'];
		echo "<tr><td><a href=\"submission.php?id=".$sid."\">".$sid."</a>".
			"</td><td>".printtime($row['submittime']).
			($key != 'team' ? "</td><td class=\"teamid\">".htmlspecialchars($row['team']) : '').
			($key != 'probid' ? "</td><td>".htmlspecialchars($row['probid']) : '').
			($key != 'langid' ? "</td><td>".htmlspecialchars($row['langid']) : '').
			"</td><td>".
				printresult( @$row['judgerid'] ? @$resulttable[$row['submitid']]['result'] : 'queued');

		echo "</td><td>".htmlspecialchars(@$resulttable[$row['submitid']]['judgername']);
		echo "</td></tr>\n";
	}
	echo "</table>\n\n";

}

// prints result with correct style, '' -> judging
function printresult($result, $valid = TRUE) {

	$start = '<span class="sol ';
	$end   = '</span>';

	switch($result) {
		case '':
			$result = 'judging';
		case 'judging':
		case 'queued':
			$style = 'queued';
			break;
		case 'correct':
			$style = 'correct';
			break;
		default:
			$style = 'incorrect';
	}

	return $start . ($valid ? $style : 'disabled') . '">' . $result . $end;

}

// print a yes/no field, input: something that evaluates to a boolean
function printyn ($val) {
	return ($val ? '1':'0');
}
