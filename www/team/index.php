<?php
/**
 * $Id$
 */

require('init.php');
$title = 'Team overview';
require('../header.php');

echo "<h1>Teampage ".htmlentities($name)."</h1>\n\n";

// get submissions
$res = $DB->q('SELECT probid,submittime,s.submitid,j.result
	FROM submission s LEFT JOIN judging j USING(submitid)
	WHERE (valid = 1 OR valid IS NULL) AND team = %s AND cid = %i
	ORDER BY s.submittime DESC',
	$login, getCurCont() );

if($res->count() == 0) {
	echo "<p><em>Nothing submitted yet.</em></p>\n";

} else {
	echo "<table>\n".
		"<tr><th>Problem</th><th>Time</th><th>Status</th></tr>\n";

	while($row = $res->next()) {

		echo "<tr><td>".htmlspecialchars($row['probid'])."</td><td>".
			printtime($row['submittime']). "</td><td>";
		if(@$row['result']) {
			echo '<a href="submission_details.php?submitid='.(int)$row['submitid'].'">'.
			printresult($row['result'])."</a>";
		} else {
			echo printresult(@$row['result']);
		}
		echo "</td></tr>\n";
			
	}
	echo "</table>\n\n";
}


echo "<p><a href=\"../public/\">Scoreboard</a></p>\n\n";

require('../footer.php');
