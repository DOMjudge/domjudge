<?php

/**
 * Functions for calculating the scoreboard.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */


/** 
 * The calcScoreRow is in lib/lib.misc.php because it's used by other
 * parts of the system aswell.
 */


/**
 * Output the general scoreboard based on the cached data in table
 * 'scoreboard'. $myteamid can be passed to highlight a specific row.
 * $isjury set to true means the scoreboard will always be current,
 * regardless of the lastscoreupdate setting in the contesttable.
 * $static omits output unsuitable for static html pages.
 */
function putScoreBoard($cdata, $myteamid = null, $isjury = FALSE, $static = FALSE) {

	global $DB;

	if ( empty( $cdata ) ) { echo "<p><em>No active contest</em></p>\n"; return; }
	$cid = $cdata['cid'];

	// get the teams and problems
	$teams = $DB->q('KEYTABLE SELECT login AS ARRAYKEY,
	                 login, team.name, team.categoryid, team.affilid, sortorder,
	                 color, visible, country, team_affiliation.name AS affilname
	                 FROM team
	                 LEFT JOIN team_category
	                        ON (team_category.categoryid = team.categoryid)
	                 LEFT JOIN team_affiliation
	                        ON (team_affiliation.affilid = team.affilid)');
	$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,
	                 probid, name, color FROM problem
	                 WHERE cid = %i AND allow_submit = 1
	                 ORDER BY probid', $cid);

	// Show final scores if contest is over and unfreezetime has been
	// reached, or if contest is over and no freezetime had been set.
	// We can compare $now and the dbfields stringwise.
	$now = now();
	$showfinal  = ( !isset($cdata['lastscoreupdate']) &&
		strcmp($cdata['endtime'],$now) <= 0 ) ||
		( isset($cdata['unfreezetime']) &&
		strcmp($cdata['unfreezetime'], $now) <= 0 );
	// freeze scoreboard if lastscoreupdate time has been reached and
	// we're not showing the final score yet
	$showfrozen = !$showfinal && isset($cdata['lastscoreupdate']) &&
		strcmp($cdata['lastscoreupdate'],$now) <= 0;

	// page heading with contestname and start/endtimes
	echo "<h1>Scoreboard " . htmlspecialchars($cdata['contestname']) . "</h1>\n\n";

	if ( $showfinal ) {
		echo "<h4>final standings</h4>\n\n";
	} else {
		echo "<h4>starts: " . printtime($cdata['starttime']) .
				" - ends: " . printtime($cdata['endtime']) ;

		if ( $showfrozen ) {
			echo " (";
			if ( $isjury ) {
				echo "public scoreboard is ";
			}
			echo "frozen since " . printtime($cdata['lastscoreupdate']) .")";
		}
		echo "</h4>\n\n";
	}

	echo '<table class="scoreboard' . ($isjury ? ' scoreboard_jury' : '') . "\">\n";

	// output table column groups (for the styles)
	echo '<colgroup><col id="scorerank" />' .
		( SHOW_AFFILIATIONS ? '<col id="scoreaffil" />' : '' ) .
		'<col id="scoreteamname" /></colgroup><colgroup><col id="scoresolv" />' .
		"<col id=\"scoretotal\" /></colgroup>\n<colgroup>" .
		str_repeat('<col class="scoreprob" />', count($probs)) .
		"</colgroup>\n";

	// column headers
	echo "<thead>\n";
	echo '<tr class="scoreheader">' .
		'<th title="rank" scope="col">' . jurylink(null,'#',$isjury) . '</th>' .
		( SHOW_AFFILIATIONS ? '<th title="team affiliation" scope="col">' .
		jurylink('team_affiliations.php','affil.',$isjury) . '</th>' : '' ) .
		'<th title="team name" scope="col">' . jurylink('teams.php','team',$isjury) . '</th>' .
		'<th title="problems solved" scope="col">' . jurylink(null,'solved',$isjury) . '</th>' .
		'<th title="penalty time" scope="col">' . jurylink(null,'time',$isjury) . "</th>\n";
	foreach( $probs as $pr ) {
		echo '<th title="problem \'' . htmlspecialchars($pr['name']) . '\'" scope="col">' .
			jurylink('problem.php?id=' . urlencode($pr['probid']),
				htmlspecialchars($pr['probid']) . 
				(!empty($pr['color']) ? ' <span style="color: ' .
				htmlspecialchars($pr['color']) . ';">' . BALLOON_SYM . '</span>' : '' ) 
			, $isjury) .
			'</th>';
	}
	echo "</tr>\n</thead>\n\n<tbody>\n";

	// initialize the arrays we'll build from the data
	$THEMATRIX = $SCORES = array();
	$SUMMARY = array('num_correct' => 0, 'total_time' => 0,
		'affils' => array(), 'countries' => array(),
		'visible_teams' => count($teams));

	// scoreboard_jury is always up to date, scoreboard_public might be frozen.	
	if ( $isjury || $showfinal ) {
		$cachetable = 'scoreboard_jury';
	} else {
		$cachetable = 'scoreboard_public';
	}

	// Get all stuff from the cached table, but don't bother with outdated
	// info from previous contests.
	$scoredata = $DB->q("SELECT * FROM $cachetable WHERE cid = %i", $cid);

	// the SCORES table contains the totals for each team which we will
	// use for determining the ranking. Initialise them here
	foreach ($teams as $login => $team ) {
		$SCORES[$login]['num_correct'] = 0;
		$SCORES[$login]['total_time']  = 0;
		$SCORES[$login]['last_solved'] = 0;
		$SCORES[$login]['teamname']    = $team['name'];
		$SCORES[$login]['categoryid']  = $team['categoryid'];
		$SCORES[$login]['sortorder']   = $team['sortorder'];
	}

	// loop all info the scoreboard cache and put it in our own datastructure
	while ( $srow = $scoredata->next() ) {
	
		// skip this row if the team or problem is not known by us
		if ( ! array_key_exists ( $srow['teamid'], $teams ) ||
		     ! array_key_exists ( $srow['probid'], $probs ) ) continue;
	
		// fill our matrix with the scores from the database,
		// we'll print this out later when we've sorted the teams
		$THEMATRIX[$srow['teamid']][$srow['probid']] = array (
			'correct' => (bool) $srow['is_correct'],
			'submitted' => $srow['submissions'],
			'time' => $srow['totaltime'],
			'penalty' => $srow['penalty'] );

		// calculate totals for this team
		if ( $srow['is_correct'] ) {
			$SCORES[$srow['teamid']]['num_correct']++;
			if ( $srow['totaltime'] > $SCORES[$srow['teamid']]['last_solved'] ) {
				$SCORES[$srow['teamid']]['last_solved'] = $srow['totaltime'];
			}
			$SCORES[$srow['teamid']]['total_time'] +=	$srow['totaltime'] + $srow['penalty'];
		}

	}

	// sort the array using our custom comparison function
	uasort($SCORES, 'cmp');

	// print the whole thing
	$prevsortorder = -1;
	foreach( $SCORES as $team => $totals ) {

		// skip displaying if this is an invisible team (except for jury sb)
		if ( ! $isjury && ! $teams[$team]['visible'] ) {
			$SUMMARY['visible_teams']--;
			continue;
		}
		
		// rank, team name, total correct, total time
		echo '<tr';
		if ( $totals['sortorder'] != $prevsortorder ) {
			echo ' class="sortorderswitch"';
			$prevsortorder = $totals['sortorder'];
			$rank = 0; // reset team position on switch to different category
			$prevteam = null;
		}
		$rank++;
		// check whether this is us, otherwise use category colour
		if ( @$myteamid == $team ) {
			echo ' id="scorethisisme"';
			unset($color);
		} else {
			$color = $teams[$team]['color'];
		}
		echo '><td class="scorepl">';
		// Only print rank when score is different from the previous team
		if ( !isset($prevteam) || cmpscore($SCORES[$prevteam], $totals)!=0 ) {
			echo jurylink(null,$rank,$isjury);
		} else {
			echo jurylink(null,'',$isjury);
		}
		$prevteam = $team;
		echo '</td>';
		if ( SHOW_AFFILIATIONS ) {
			echo '<td class="scoreaf">';
			if ( isset($teams[$team]['affilid']) ) {
				if ( $isjury ) {
					echo '<a href="team_affiliation.php?id=' .
						urlencode($teams[$team]['affilid']) . '">';
				}
				$affillogo = '../images/affiliations/' .
					urlencode($teams[$team]['affilid']) . '.png';
				if ( is_readable($affillogo) ) {
					echo '<img src="' . $affillogo . '"' .
						' alt="'   . htmlspecialchars($teams[$team]['affilid']) . '"' .
						' title="' . htmlspecialchars($teams[$team]['affilname']) . '" />';
				} else {
					echo htmlspecialchars($teams[$team]['affilid']);
				}
				if ( isset($teams[$team]['country']) ) {
					$countryflag = '../images/countries/' .
						urlencode($teams[$team]['country']) . '.png';
					echo ' ';
					if ( is_readable($countryflag) ) {
						echo '<img src="' . $countryflag . '"' .
							' alt="'   . htmlspecialchars($teams[$team]['country']) . '"' .
							' title="' . htmlspecialchars($teams[$team]['country']) . '" />';
					} else {
						echo htmlspecialchars($teams[$team]['country']);
					}
				}
				if ( $isjury ) echo '</a>';
			}
			echo '</td>';	
		}
		echo
			'<td class="scoretn"' .
			(!empty($color) ? ' style="background: ' . $color . ';"' : '') .
			($isjury ? ' title="' . htmlspecialchars($team) . '"' : '') . '>' .
			($static ? '' : '<a href="team.php?id=' . urlencode($team) . '">') .
			htmlspecialchars($teams[$team]['name']) .
			($static ? '' : '</a>') .
			'</td>';
		echo
			'<td class="scorenc">' . jurylink(null,$totals['num_correct'],$isjury) . '</td>' .
			'<td class="scorett">' . jurylink(null,$totals['total_time'], $isjury) . '</td>';

		// keep summary statistics for the bottom row of our table
		$SUMMARY['num_correct'] += $totals['num_correct'];
		$SUMMARY['total_time']  += $totals['total_time'];
		if ( SHOW_AFFILIATIONS ) {
			if ( ! empty($teams[$team]['affilid']) )
				@$SUMMARY['affils'][$teams[$team]['affilid']]++;
			if ( ! empty($teams[$team]['country']) )
				@$SUMMARY['countries'][$teams[$team]['country']]++;
		}
		
		// for each problem
		foreach ( array_keys($probs) as $prob ) {

			// if we have scores, use them, else, provide the defaults
			// (happens when nothing submitted for this problem,team yet)
			if ( isset ( $THEMATRIX[$team][$prob] ) ) {
				$pdata = $THEMATRIX[$team][$prob];
			} else {
				$pdata = array ( 'submitted' => 0, 'correct' => 0,
				                 'time' => 0, 'penalty' => 0);
			}

			echo '<td class=';
			// CSS class for correct/incorrect/neutral results
			if( $pdata['correct'] ) { 
				echo '"score_correct"';
			} elseif ( $pdata['submitted'] > 0 ) {
				echo '"score_incorrect"';
			} else {
				echo '"score_neutral"';
			}
			// number of submissions for this problem
			$str = $pdata['submitted'];
			// if correct, print time scored
			if( $pdata['correct'] ) {
				$str .= ' (' . $pdata['time'] . ' + ' . $pdata['penalty'] . ')';
			}
			echo '>' . jurylink('team.php?id=' . urlencode($team ) .
								'&amp;restrict=probid:' . urlencode($prob),
			                    $str,$isjury) . '</td>';
			
			// update summary data for the bottom row
			@$SUMMARY[$prob]['submissions'] += $pdata['submitted'];
			@$SUMMARY[$prob]['correct'] += ($pdata['correct'] ? 1 : 0);
			if( $pdata['time'] > 0 ) {
				@$SUMMARY[$prob]['times'][] = $pdata['time'];
			}
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n\n<tbody>\n";
	
	// print a summaryline
	echo '<tr id="scoresummary" title="#submitted / #correct / fastest time">' .
		'<td title="total teams">' .
		jurylink(null,$SUMMARY['visible_teams'],$isjury) . '</td>' .
		( SHOW_AFFILIATIONS ? '<td class="scoreaffil" title="#affiliations / #countries">' .
		  jurylink('team_affiliations.php',count($SUMMARY['affils']) . ' / ' .
				   count($SUMMARY['countries']),$isjury) . '</td>' : '' ) .
		'<td title=" ">' . jurylink(null,'Summary',$isjury) . '</td>' .
		'<td class="scorenc" title="total solved">' .
		jurylink(null,$SUMMARY['num_correct'],$isjury) . '</td>' .
		'<td class="scorett" title="total time">' .
		jurylink(null,$SUMMARY['total_time'],$isjury) . '</td>';

	foreach( $probs as $pr ) {
		if ( !isset($SUMMARY[$pr['probid']]) ) {
			$SUMMARY[$pr['probid']]['submissions'] = 0;
			$SUMMARY[$pr['probid']]['correct'] = 0;
		}
		$str = $SUMMARY[$pr['probid']]['submissions'] . ' / ' .
		       $SUMMARY[$pr['probid']]['correct'] . ' / ';
		if ( isset($SUMMARY[$pr['probid']]['times']) ) {
			$str .= min(@$SUMMARY[$pr['probid']]['times']);
		} else {
			$str .= '-';
		}
		echo '<td>' .
			jurylink('problem.php?id=' . urlencode($pr['probid']),$str,$isjury) .
			'</td>';
	}
	echo "</tr>\n</tbody>\n</table>\n\n";

	$categs = $DB->q('SELECT * FROM team_category ' .
	                 ($isjury ? '' : 'WHERE visible = 1 ' ) .
	                 'ORDER BY categoryid');

	// only print legend when there's more than one category
	if ( $categs->count() > 1 ) {
		echo "<p><br /><br /></p>\n<table class=\"scoreboard" .
			($isjury ? ' scoreboard_jury' : '') . "\">\n" .
			"<thead><tr><th scope=\"col\">" .
			jurylink('team_categories.php','Legend',$isjury) .
			"</th></tr></thead>\n<tbody>\n";
		while ( $cat = $categs->next() ) {
			echo '<tr' . (!empty($cat['color']) ? ' style="background: ' .
				          $cat['color'] . ';"' : '') . '>' .
				'<td align="center" class="scoretn">' .
				jurylink('team_category.php?id=' . urlencode($cat['categoryid']),
					htmlspecialchars($cat['name']),$isjury) .	"</td></tr>\n";
		}
		echo "</tbody>\n</table>\n\n";
	}

	// last modified date, now if we are the jury, else include the
	// lastscoreupdate time
	if( ! $isjury && $showfrozen ) {
		$lastupdate = strtotime($cdata['lastscoreupdate']);
	} else {
		$lastupdate = time();
	}
	echo "<p id=\"lastmod\">Last Update: " .
		date('j M Y H:i', $lastupdate) . "</p>\n\n";

	return;
}

/**
 * Output a team row from the scoreboard based on the cached data in
 * table 'scoreboard'.
 */
function putTeamRow($cdata, $teamid) {

	global $DB;

	if ( empty( $cdata ) ) return;
	$cid = $cdata['cid'];
	
	echo '<table class="scoreboard">' . "\n";

	$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, probid, name, color
	                 FROM problem WHERE cid = %i AND allow_submit = 1
	                 ORDER BY probid', $cid);

	// column headers
	echo "<tr class=\"scoreheader\"><th>problem</th><th>score</th></tr>\n";

	// initialize the arrays we'll build from the data
	$THEMATRIX = array();

	// for a team, we always display the "current" information, that is,
	// from scoreboard_jury
	$scoredata = $DB->q('SELECT * FROM scoreboard_jury WHERE cid = %i AND teamid = %s',
	                    $cid, $teamid);

	// loop all info the scoreboard cache and put it in our own datastructure
	while ( $srow = $scoredata->next() ) {
		// skip this row if the problem is not known by us
		if ( ! array_key_exists ( $srow['probid'], $probs ) ) continue;
	
		// fill our matrix with the scores from the database,
		$THEMATRIX[$srow['probid']] = array (
			'correct' => (bool) $srow['is_correct'],
			'submitted' => $srow['submissions'],
			'time' => $srow['totaltime'],
			'penalty' => $srow['penalty'] );
	}

	$SUMMARY = array('num_correct' => 0, 'total_time' => 0);
	// for each problem
	foreach ( $probs as $prob => $probdata ) {
		// if we have scores, use them, else, provide the defaults
		// (happens when nothing submitted for this problem,team yet)
		if ( isset ( $THEMATRIX[$prob] ) ) {
			$pdata = $THEMATRIX[$prob];
		} else {
			$pdata = array ( 'submitted' => 0, 'correct' => 0,
			                 'time' => 0, 'penalty' => 0);
		}
		echo '<tr><td class="probid" title="' .
			htmlspecialchars($probdata['name']) . '">' .
			(!empty($probdata['color']) ? '<span style="color: ' .
			       htmlspecialchars($probdata['color']) . ';">' .
			       BALLOON_SYM . '</span> ' : '' ) .
			htmlspecialchars($prob) . '</td><td class="';
		// CSS class for correct/incorrect/neutral results
		if( $pdata['correct'] ) { 
			echo 'score_correct';
		} elseif ( $pdata['submitted'] > 0 ) {
			echo 'score_incorrect';
		} else {
			echo 'score_neutral';
		}
		// number of submissions for this problem
		echo '">' . $pdata['submitted'];
		// if correct, print time scored
		if( ($pdata['time']+$pdata['penalty']) > 0) {
			echo " (" . $pdata['time'] . ' + ' . $pdata['penalty'] . ")";
			$SUMMARY['num_correct'] ++;
			$SUMMARY['total_time'] += $pdata['time'] + $pdata['penalty'];
		}
		echo "</td></tr>\n";
	}

	echo "<tr id=\"scoresummary\" title=\"#correct / time\"><td>Summary</td>".
		"<td>" . $SUMMARY['num_correct'] . " / " . $SUMMARY['total_time'] . "</td></tr>\n";

	echo "</table>\n\n";

	return;
}

/**
 * Generate scoreboard links for jury only.
 */
function jurylink($target, $content, $isjury) {

	$res = "";
	if ( $isjury ) {
		$res .= '<a' . (isset($target) ? ' href="' . $target . '"' : '' ) . '>';
	}
	$res .= $content;
	if ( $isjury ) $res .= '</a>';
	
	return $res;
}

/**
 * Main score comparison function, called from the 'cmp' wrapper
 * below. Scores two arrays, $a and $b, based on the following
 * criteria:
 * - highest number of correct solutions;
 * - least amount of total time spent on these solutions;
 * - fastest submission time for their most recent correct solution.
 */
function cmpscore($a, $b) {
	// more correct than someone else means higher rank
	if ( $a['num_correct'] != $b['num_correct'] ) {
		return $a['num_correct'] > $b['num_correct'] ? -1 : 1;
	}
	// else, less time spent means higher rank
	if ( $a['total_time'] != $b['total_time'] ) {
		return $a['total_time'] < $b['total_time'] ? -1 : 1;
	}
	// else, fastest submission time for latest correct problem
	if ( $a['last_solved'] != $b['last_solved'] ) {
		return $a['last_solved'] < $b['last_solved'] ? -1 : 1;
	}
	return 0;
}

/**
 * Scoreboard sorting function. Given two arrays with team information
 * $a and $b, decides on how to order these. It uses the following
 * criteria:
 * - First, use the sortorder override from the team_category table
 *   (e.g. score regular contestants always over spectators);
 * - Then, use the cmpscore function to determine the actual ordering
 *   based on number of problems solved and the time it took;
 * - If still equal, order on team name alphabetically.
 */
function cmp($a, $b) {
	// first order by our predefined sortorder based on category
	if ( $a['sortorder'] != $b['sortorder'] ) {
		return $a['sortorder'] < $b['sortorder'] ? -1 : 1;
	}
	// then compare scores
	$scorecmp = cmpscore($a, $b);
	if ( $scorecmp != 0 ) return $scorecmp;
	// else, order by teamname alphabetically
	if ( $a['teamname'] != $b['teamname'] ) {
		return strcasecmp($a['teamname'],$b['teamname']);
	}
	// undecided, should never happen in practice
	return 0;
}

