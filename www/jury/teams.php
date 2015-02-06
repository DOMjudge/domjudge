<?php
/**
 * View the teams
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Teams';

$teams = $DB->q('SELECT t.*, c.name AS catname,
                 a.shortname AS affshortname, a.name AS affname,
                 COUNT(co.cid) AS numcontests
                 FROM team t
                 INNER JOIN contest co
                 LEFT JOIN contestteam ct USING (teamid, cid)
                 LEFT JOIN team_category c USING (categoryid)
                 LEFT JOIN team_affiliation a USING (affilid)
                 WHERE (co.public = 1 OR ct.cid IS NOT NULL)
                 GROUP BY teamid
                 ORDER BY c.sortorder, t.name COLLATE utf8_general_ci');

if ( empty($cids) ) {
	$nsubmits = array();
	$ncorrect = array();
} else {
	$nsubmits = $DB->q('KEYTABLE SELECT teamid AS ARRAYKEY, COUNT(teamid) AS cnt
	                    FROM submission s
	                    WHERE cid IN (%Ai) GROUP BY teamid', $cids);

	$ncorrect = $DB->q('KEYTABLE SELECT teamid AS ARRAYKEY, COUNT(teamid) AS cnt
	                    FROM submission s
	                    LEFT JOIN judging j USING (submitid)
	                    WHERE j.valid = 1 AND j.result = "correct" AND s.cid IN (%Ai)
	                    GROUP BY teamid', $cids);
}

require(LIBWWWDIR . '/header.php');

echo "<h1>Teams</h1>\n\n";

if( $teams->count() == 0 ) {
	echo "<p class=\"nodata\">No teams defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
		"<tr><th class=\"sorttable_numeric\" scope=\"col\">ID</th><th scope=\"col\">teamname</th>" .
		"<th scope=\"col\">category</th><th scope=\"col\">affiliation</th>" .
		"<th scope=\"col\" class=\"sorttable_numeric\"># contests</th>" .
		"<th scope=\"col\">host</th><th scope=\"col\">room</th>" .
		"<th class=\"sorttable_nosort\"></th><th class=\"thleft\" " .
		"scope=\"col\">status</th><th></th>" .
		"</tr>\n</thead>\n<tbody>\n";

	while( $row = $teams->next() ) {

		$status = $numsub = $numcor = 0;
		if ( isset($row['teampage_first_visited']) ) $status = 1;
		if ( isset($nsubmits[$row['teamid']]) &&
			 $nsubmits[$row['teamid']]['cnt']>0 ) {
			$status = 2;
			$numsub = (int)$nsubmits[$row['teamid']]['cnt'];
		}
		if ( isset($ncorrect[$row['teamid']]) &&
			 $ncorrect[$row['teamid']]['cnt']>0 ) {
			$status = 3;
			$numcor = (int)$ncorrect[$row['teamid']]['cnt'];
		}
		$link = '<a href="team.php?id='.urlencode($row['teamid']) . '">';
		echo "<tr class=\"category" . (int)$row['categoryid']  .
			($row['enabled'] == 1 ? '' : ' sub_ignore') .  "\">".
			"<td>" . $link . "t" .
				htmlspecialchars($row['teamid'])."</a></td>".
			"<td>" . $link .
				htmlspecialchars($row['name'])."</a></td>".
			"<td>" . $link .
				htmlspecialchars($row['catname'])."</a></td>".
			"<td title=\"".htmlspecialchars($row['affname'])."\">" . $link .
				($row['affshortname'] ? htmlspecialchars($row['affshortname']) : '&nbsp;') .
			"</a></td><td>" . $link . $row['numcontests']."</a></td><td title=\"";

		if ( @$row['hostname'] ) {
			echo htmlspecialchars($row['hostname']) . "\">" . $link .
			    printhost($row['hostname']);
		} else {
			echo "\">" . $link . "-";
		}
		echo "</a></td><td>" . $link .
			($row['room'] ? htmlspecialchars($row['room']) : '&nbsp;') . "</a></td>";
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
		echo ">$link" . CIRCLE_SYM . "</a></td>";
		echo "<td class=\"teamstat\" title=\"$numcor correct / $numsub submitted\">$link$numcor / $numsub</a></td>";
		if ( IS_ADMIN ) {
			echo "<td class=\"editdel\">" .
				editLink('team', $row['teamid']) . " " .
				delLink('team','teamid',$row['teamid']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" .addLink('team') . "</p>\n";
}

require(LIBWWWDIR . '/footer.php');
