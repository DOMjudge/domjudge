<?php
/**
 * $Id$
 */

require('init.php');
$title = 'Team overview';
require('../header.php');

echo "<h1>Teampage ".htmlentities($name)."</h1>\n\n";

// get submissions
$res = $DB->q('SELECT * FROM submission LEFT JOIN judging USING(submitid)
	WHERE (valid = 1 OR valid IS NULL) AND team = %s ORDER BY submittime DESC',
	$login);

if($res->count() == 0) {
	echo "<p><em>Nothing submitted yet.</em></p>\n";

} else {
	echo "<table>\n".
		"<tr><th>Problem</th><th>Time</th><th>Status</th></tr>\n";

	while($row = $res->next()) {

		echo "<tr><td>".htmlspecialchars($row['probid'])."</td><td>".
			printtime(htmlspecialchars($row['submittime'])). "</td><td>";
		if(!@$row['judgingid']) {
			echo "<span class=\"sol-queued\">queued</span>";
		} elseif (!@$row['endtime']) {
			echo "<span class=\"sol-queued\">judging</span>";
		} else {
			echo '<a href="submission_details.php?submitid='.(int)$row['submitid'].'" '.
				'class="'.($row['result']=='correct'?'sol-correct':'sol-incorrect').'">'.
				htmlspecialchars($row['result']).'</a>';
		}
		echo "</td></tr>\n";
			
	}
	echo "</table>\n\n";
}


echo "<p><a href=\"../public/\">Scoreboard</a></p>\n\n";

require('../footer.php');
