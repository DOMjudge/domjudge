<?php
/**
 * View the submissionqueue
 *
 * $Id$
 */

require('init.php');
$title = 'Submissions';
require('../header.php');

echo "<h1>Submissions</h1>\n\n";

// we need two queries: one for all submissions, and one with the results for the valid ones.
$res = $DB->q('SELECT * FROM submission ORDER BY submittime');
$resulttable = $DB->q('KEYTABLE SELECT *,submitid AS ARRAYKEY
		FROM judging WHERE (valid = 1 OR valid IS NULL)');

echo "<table>
<tr><th>ID</th><th>time</th><th>team</th><th>problem</th><th>lang</th><th>status</th><th>last<br>judge</th></tr>\n";
while($row = $res->next()) {
	echo "<tr><td><a href=\"submission.php?id=".$row['submitid']."\">".$row['submitid']."</a>".
		"</td><td>".printtime($row['submittime']).
		"</td><td>".$row['team'].
		"</td><td>".$row['probid'].
		"</td><td>".$row['langid'].
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

	echo "</td><td>".@$row['judger'];
	echo "</td></tr>\n";
}
echo "</table>\n\n";
require('../footer.php');
