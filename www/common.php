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
		FROM judging j LEFT JOIN judger ON(j.judger=judger.judgerid)
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
		echo "<tr><td><a href=\"submission.php?id=".$row['submitid']."\">".$row['submitid']."</a>".
			"</td><td>".printtime($row['submittime']).
			($key != 'team' ? "</td><td>".$row['team'] : '').
			($key != 'probid' ? "</td><td>".$row['probid'] : '').
			($key != 'langid' ? "</td><td>".$row['langid'] : '').
			"</td><td class=\"sol-";
		
			if(! @$row['judger'] ) {
				echo "queued\">queued";
			} elseif( @!$resulttable[$row['submitid']]['result'] ) {
				echo "queued\">judging";
			} elseif( $resulttable[$row['submitid']]['result'] == 'correct') {
				echo "correct\">correct";
			} else {
				echo "incorrect\">".$resulttable[$row['submitid']]['result'];
			}

		echo "</td><td>".@$resulttable[$row['submitid']]['judgername'];
		echo "</td></tr>\n";
	}
	echo "</table>\n\n";

}
