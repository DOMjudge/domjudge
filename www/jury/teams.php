<?php
/**
 * View the teams
 *
 * $Id$
 */

require('init.php');
$title = 'Teams';

$teams = $DB->q('SELECT t.*,c.name as catname,a.name as affname,s.submitid
                 FROM team t
                 LEFT JOIN team_category c USING(categoryid)
                 LEFT JOIN team_affiliation a ON(t.affilid=a.affilid)
                 LEFT JOIN submission s ON(t.login=s.team)
                 GROUP BY t.login ORDER BY c.sortorder, t.name');

$judgings = $DB->q('KEYTABLE SELECT j.*, submitid AS ARRAYKEY FROM judging j
                    WHERE valid = 1 AND result = "correct" AND cid = %i',
				   getCurContest());

require('../header.php');
require('menu.php');

echo "<h1>Teams</h1>\n\n";

if( $teams->count() == 0 ) {
	echo "<p><em>No teams defined</em></p>\n\n";
} else {
	echo "<table>\n" .
		"<tr><th>login</th><th>teamname</th><th>category</th>" .
		"<th>affiliation</th><th>host</th><th>room</th><th>status</th></tr>\n";
	while( $row = $teams->next() ) {
		
		$status = 0;
		if ( isset($row['teampage_first_visited']) ) $status = 1;
		if ( isset($row['submitid']) ) {
			$status = 2;
			if ( isset($judgings[$row['submitid']]) ) $status = 3;
		}
		
		echo "<tr class=\"category" . (int)$row['categoryid'] .
			"\"><td class=\"teamid\"><a href=\"team.php?id=".
			urlencode($row['login'])."\">".htmlspecialchars($row['login']).
			"</a></td><td>".htmlentities($row['name']).
			"</td><td title=\"catid ".(int)$row['categoryid']."\">".
			htmlentities($row['catname']).
			"</td><td title=\"affilid ".htmlspecialchars($row['affilid'])."\">".
			htmlentities($row['affname'])."</td><td title=\"";
			if ( @$row['ipaddress'] ) {
				$host = htmlspecialchars(gethostbyaddr($row['ipaddress']));
				echo htmlspecialchars($row['ipaddress']);
				if ( $host == $row['ipaddress'] ) {
					echo "\">" . printhost($host, TRUE);
				} else {
					echo " - $host\">" . printhost($host);
				}
				
			} else {
				echo "\">-";
			}
			echo "</td><td>".htmlentities($row['room'])."</td>";
			echo "<td class=\"teamstatus\"><img ";
			switch ( $status ) {
			case 0: echo 'src="gray.png" alt="gray" ' .
						'title="no connections made"';
				break;
			case 1: echo 'src="red.png" alt="red" ' .
						'title="teampage viewed, no submissions"';
				break;
			case 2: echo 'src="yellow.png" alt="yellow" ' .
						'title="submitted, none correct"';
				break;
			case 3: echo 'src="green.png" alt="green" ' .
						'title="correct submission(s)"';
				break;
			}
			echo " width=\"16\" height=\"16\" /></td></tr>\n";
	}
	echo "</table>\n\n";
}
require('../footer.php');
