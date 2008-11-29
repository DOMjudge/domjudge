<?php
/**
 * View the teams
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Teams';

$teams = $DB->q('SELECT t.*,c.name AS catname,a.name AS affname
                 FROM team t
                 LEFT JOIN team_category c USING (categoryid)
                 LEFT JOIN team_affiliation a ON (t.affilid = a.affilid)
                 ORDER BY c.sortorder, t.name');

$nsubmits = $DB->q('KEYTABLE SELECT teamid AS ARRAYKEY, COUNT(teamid) AS cnt
                    FROM submission s
                    WHERE cid = %i GROUP BY teamid', $cid);

$ncorrect = $DB->q('KEYTABLE SELECT teamid AS ARRAYKEY, COUNT(teamid) AS cnt
                    FROM submission s
                    LEFT JOIN judging j USING (submitid)
                    WHERE j.valid = 1 AND j.result = "correct" AND s.cid = %i
                    GROUP BY teamid', $cid);

require(LIBWWWDIR . '/header.php');

echo "<h1>Teams</h1>\n\n";

if( $teams->count() == 0 ) {
	echo "<p><em>No teams defined</em></p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
		"<tr><th scope=\"col\">login</th><th scope=\"col\">teamname</th>" .
		"<th scope=\"col\">category</th><th scope=\"col\">affiliation</th>" .
		"<th scope=\"col\">host</th><th scope=\"col\">room</th>" .
		"<th colspan=\"2\" scope=\"col\">status</th></tr>\n</thead>\n" .
		"<tbody>\n";

	while( $row = $teams->next() ) {

		$status = $numsub = $numcor = 0;
		if ( isset($row['teampage_first_visited']) ) $status = 1;
		if ( isset($nsubmits[$row['login']]) &&
			 $nsubmits[$row['login']]['cnt']>0 ) {
			$status = 2;
			$numsub = (int)$nsubmits[$row['login']]['cnt'];
		}
		if ( isset($ncorrect[$row['login']]) &&
			 $ncorrect[$row['login']]['cnt']>0 ) {
			$status = 3;
			$numcor = (int)$ncorrect[$row['login']]['cnt'];
		}
		
		echo "<tr class=\"category" . (int)$row['categoryid'] . "\">".
			"<td class=\"teamid\"><a href=\"team.php?id=".urlencode($row['login'])."\">".
				htmlspecialchars($row['login'])."</a></td>".
			"<td><a href=\"team.php?id=".htmlspecialchars($row['login'])."\">".
				htmlspecialchars($row['name'])."</a></td>".
			"<td title=\"catid ".(int)$row['categoryid']."\">".
				htmlspecialchars($row['catname'])."</td>".
			"<td title=\"".htmlspecialchars($row['affname'])."\">".
				htmlspecialchars($row['affilid'])."</td><td title=\"";
		
		if ( @$row['ipaddress'] ) {
			$host = (empty($row['hostname'])?'':$row['hostname']);
			echo htmlspecialchars($row['ipaddress']);
			if ( empty($host) ) {
				echo "\">" . htmlspecialchars($row['ipaddress']);
			} else {
				echo " - " . htmlspecialchars($host) . "\">" .
					printhost($host);
			}
		} else {
			echo "\">-";
		}
		echo "</td><td>".htmlspecialchars($row['room'])."</td>";
		echo "<td class=\"";
		switch ( $status ) {
		case 0: echo 'team-nocon" title="no connections made"';
			break;
		case 1: echo 'team-nosub" title="teampage viewed, no submissions"';
			break;
		case 2: echo 'team-nocor" title="submitted, none correct"';
			break;
		case 3: echo 'team-ok" title="correct submission(s)"';
			break;
		}
		echo ">".BALLOON_SYM."</td>";
		echo "<td align=\"right\" title=\"$numcor correct / $numsub submitted\">$numcor / $numsub</td>";
		if ( IS_ADMIN ) {
			echo "<td>" .
				editLink('team', $row['login']) . " " .
				delLink('team','login',$row['login']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" .addLink('team') . "</p>\n";
}

require(LIBWWWDIR . '/footer.php');
