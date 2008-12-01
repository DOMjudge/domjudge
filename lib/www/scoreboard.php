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
require_once(LIBDIR . '/lib.misc.php');

/**
 * Generate scoreboard data based on the cached data in table
 * 'scoreboard_{public,jury}'. If the function is called while
 * IS_JURY is defined, the scoreboard will always be current,
 * regardless of the freezetime setting in the contesttable.
 *
 * This function returns an array (scores, summary, matrix)
 * containing the following:
 *
 * scores[login](num_correct, total_time, solve_times[], rank,
 *               teamname, categoryid, sortorder, country, affilid)
 *
 * matrix[login][probid](is_correct, num_submissions, time, penalty)
 *
 * summary(num_correct, total_time, affils[affilid], countries[country], problems[probid])
 *    probid(num_submissions, num_correct, best_time)
 */
function genScoreBoard($cdata) {

	global $DB;

	$cid = $cdata['cid'];

	// Show final scores if contest is over and unfreezetime has been
	// reached, or if contest is over and no freezetime had been set.
	// We can compare $now and the dbfields stringwise.
	$now = now();
	$showfinal  = ( !isset($cdata['freezetime']) &&
		difftime($cdata['endtime'],$now) <= 0 ) ||
		( isset($cdata['unfreezetime']) &&
		difftime($cdata['unfreezetime'], $now) <= 0 );
	// contest is active but has not yet started
	$cstarted = difftime($cdata['starttime'],$now) <= 0;

	// Don't leak information before start of contest
	if ( ! $cstarted && ! IS_JURY ) return;
	
	// get the teams and problems
	$teams = $DB->q('KEYTABLE SELECT login AS ARRAYKEY, login, team.name,
	                 team.categoryid, team.affilid, country, sortorder
	                 FROM team
	                 LEFT JOIN team_category
	                        ON (team_category.categoryid = team.categoryid)
	                 LEFT JOIN team_affiliation
	                        ON (team_affiliation.affilid = team.affilid)' .
	                ( IS_JURY ? '' : ' WHERE visible = 1' ) );
	
	$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, probid FROM problem
	                 WHERE cid = %i AND allow_submit = 1
	                 ORDER BY probid', $cid);
	
	// initialize the arrays we'll build from the data
	$MATRIX = $SCORES = array();
	$SUMMARY = array('num_correct' => 0, 
	                 'affils' => array(), 'countries' => array(),
					 'problems' => array());

	// scoreboard_jury is always up to date, scoreboard_public might be frozen.	
	if ( IS_JURY || $showfinal ) {
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
		$SCORES[$login]['solve_times'] = array();
		$SCORES[$login]['rank']        = 0;
		$SCORES[$login]['teamname']    = $team['name'];
		$SCORES[$login]['categoryid']  = $team['categoryid'];
		$SCORES[$login]['sortorder']   = $team['sortorder'];
		$SCORES[$login]['affilid']     = $team['affilid'];
		$SCORES[$login]['country']     = $team['country'];
	}
	
	// loop all info the scoreboard cache and put it in our own datastructure
	while ( $srow = $scoredata->next() ) {
		
		// skip this row if the team or problem is not known by us
		if ( ! array_key_exists ( $srow['teamid'], $teams ) ||
		     ! array_key_exists ( $srow['probid'], $probs ) ) continue;
	
		// fill our matrix with the scores from the database
		$MATRIX[$srow['teamid']][$srow['probid']] = array (
			'is_correct'      => (bool) $srow['is_correct'],
			'num_submissions' => $srow['submissions'],
			'time'            => $srow['totaltime'],
			'penalty'         => $srow['penalty'] );

		// calculate totals for this team
		if ( $srow['is_correct'] ) {
			$SCORES[$srow['teamid']]['num_correct']++;
			$SCORES[$srow['teamid']]['solve_times'][] = $srow['totaltime'];
			$SCORES[$srow['teamid']]['total_time'] += $srow['totaltime'] + $srow['penalty'];
		}
	}

	// sort the array using our custom comparison function
	uasort($SCORES, 'cmp');

	// loop over all teams to calculate ranks and totals
	$prevsortorder = -1;
	foreach( $SCORES as $team => $totals ) {

		// rank, team name, total correct, total time
		if ( $totals['sortorder'] != $prevsortorder ) {
			$prevsortorder = $totals['sortorder'];
			$rank = 0; // reset team position on switch to different category
			$prevteam = null;
		}
		$rank++;
		// Use previous' team rank when scores are equal
		if ( isset($prevteam) && cmpscore($SCORES[$prevteam], $totals)==0 ) {
			$SCORES[$team]['rank'] = $SCORES[$prevteam]['rank'];
		} else {
			$SCORES[$team]['rank'] = $rank;
		}
		$prevteam = $team;

		// keep summary statistics for the bottom row of our table
		$SUMMARY['num_correct'] += $totals['num_correct'];
		if ( ! empty($teams[$team]['affilid']) ) @$SUMMARY['affils'][$totals['affilid']]++;
		if ( ! empty($teams[$team]['country']) ) @$SUMMARY['countries'][$totals['country']]++;
		
		// for each problem
		foreach ( array_keys($probs) as $prob ) {

			// provide default scores when nothing submitted for this team,problem yet
			if ( ! isset ( $MATRIX[$team][$prob] ) ) {
				$MATRIX[$team][$prob] = array ( 'num_submissions' => 0, 'is_correct' => 0,
				                                'time' => 0, 'penalty' => 0);
			}
			$pdata = $MATRIX[$team][$prob];

			// update summary data for the bottom row
			@$SUMMARY['problems'][$prob]['num_submissions'] += $pdata['num_submissions'];
			@$SUMMARY['problems'][$prob]['num_correct'] += ($pdata['is_correct'] ? 1 : 0);
			if ( $pdata['is_correct'] ) {
				@$SUMMARY['problems'][$prob]['times'][] = $pdata['time'];
			}
		}
	}

	// Fill all problems with data if not set already
	foreach( array_keys($probs) as $prob ) {
		if ( !isset($SUMMARY['problems'][$prob]) ) {
			$SUMMARY['problems'][$prob]['num_submissions'] = 0;
			$SUMMARY['problems'][$prob]['num_correct'] = 0;
		}
		if ( isset($SUMMARY['problems'][$prob]['times']) ) {
			$SUMMARY['problems'][$prob]['best_time'] = min(@$SUMMARY['problems'][$prob]['times']);
		} else {
			$SUMMARY['problems'][$prob]['best_time'] = NULL;
		}
		
	}

	return array( 'matrix' => $MATRIX, 'scores' => $SCORES, 'summary' => $SUMMARY );
}

/**
 * Output the general scoreboard based on the cached data in table
 * 'scoreboard'. $myteamid can be passed to highlight a specific row.
 * If this function is called while IS_JURY is defined, the scoreboard
 * will always be current, regardless of the freezetime setting in the
 * contesttable. $static omits output unsuitable for static html pages.
 */
function putScoreBoard($cdata, $myteamid = null, $static = FALSE) {

	global $DB;

	if ( empty( $cdata ) ) { echo "<p><em>No active contest</em></p>\n"; return; }
	$cid = $cdata['cid'];

	// Show final scores if contest is over and unfreezetime has been
	// reached, or if contest is over and no freezetime had been set.
	// We can compare $now and the dbfields stringwise.
	$now = now();
	$showfinal  = ( !isset($cdata['freezetime']) &&
		difftime($cdata['endtime'],$now) <= 0 ) ||
		( isset($cdata['unfreezetime']) &&
		difftime($cdata['unfreezetime'], $now) <= 0 );
	// freeze scoreboard if freeze time has been reached and
	// we're not showing the final score yet
	$showfrozen = !$showfinal && isset($cdata['freezetime']) &&
		difftime($cdata['freezetime'],$now) <= 0;
	// contest is active but has not yet started
	$cstarted = difftime($cdata['starttime'],$now) <= 0;

	// page heading with contestname and start/endtimes
	echo "<h1>Scoreboard " . htmlspecialchars($cdata['contestname']) . "</h1>\n\n";

	if ( $showfinal ) {
		echo "<h4>final standings</h4>\n\n";
	} elseif ( ! $cstarted ) {
		echo "<h4>scheduled to start at " . printtime($cdata['starttime']) . "</h4>\n\n";
		// Stop here (do not leak problem number, descriptions etc).
		// Alternatively we could only display the list of teams?
		if ( ! IS_JURY ) return;
	} else {
		echo "<h4>starts: " . printtime($cdata['starttime']) .
				" - ends: " . printtime($cdata['endtime']) ;

		if ( $showfrozen ) {
			echo " (";
			if ( IS_JURY ) {
				echo "public scoreboard is ";
			}
			echo "frozen since " . printtime($cdata['freezetime']) .")";
		}
		echo "</h4>\n\n";
	}
	
	// get the teams and problems
	$teams = $DB->q('KEYTABLE SELECT login AS ARRAYKEY,
	                 login, team.name, team.categoryid, team.affilid, sortorder,
	                 color, country, team_affiliation.name AS affilname
	                 FROM team
	                 LEFT JOIN team_category
	                        ON (team_category.categoryid = team.categoryid)
	                 LEFT JOIN team_affiliation
	                        ON (team_affiliation.affilid = team.affilid)' .
			( IS_JURY ? '' : ' WHERE visible = 1' ) );
	$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,
	                 probid, name, color FROM problem
	                 WHERE cid = %i AND allow_submit = 1
	                 ORDER BY probid', $cid);

	echo '<table class="scoreboard' . (IS_JURY ? ' scoreboard_jury' : '') . "\">\n";

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
		'<th title="rank" scope="col">' . jurylink(null,'#') . '</th>' .
		( SHOW_AFFILIATIONS ? '<th title="team affiliation" scope="col">' .
		jurylink('team_affiliations.php','affil.') . '</th>' : '' ) .
		'<th title="team name" scope="col">' . jurylink('teams.php','team') . '</th>' .
		'<th title="# solved / penalty time" colspan="2" scope="col">' . jurylink(null,'score') . "</th>\n";
	foreach( $probs as $pr ) {
		echo '<th title="problem \'' . htmlspecialchars($pr['name']) . '\'" scope="col">' .
			jurylink('problem.php?id=' . urlencode($pr['probid']),
				htmlspecialchars($pr['probid']) . 
				(!empty($pr['color']) ? ' <span style="color: ' .
				htmlspecialchars($pr['color']) . ';">' . BALLOON_SYM . '</span>' : '' ) 
			) .
			'</th>';
	}
	echo "</tr>\n</thead>\n\n<tbody>\n";
	
	// now get the real scoreboard data
	$tmp = genScoreBoard($cdata);
	if ( !empty($tmp) ) {
		$SCORES  = $tmp['scores'];
		$MATRIX  = $tmp['matrix'];
		$SUMMARY = $tmp['summary'];
	}
	unset($tmp);

	// print the main scoreboard rows
	$prevsortorder = -1;
	foreach( $SCORES as $team => $totals ) {

		// rank, team name, total correct, total time
		echo '<tr';
		if ( $totals['sortorder'] != $prevsortorder ) {
			echo ' class="sortorderswitch"';
			$prevsortorder = $totals['sortorder'];
			$prevteam = null;
		}
		// check whether this is us, otherwise use category colour
		if ( @$myteamid == $team ) {
			echo ' id="scorethisisme"';
			unset($color);
		} else {
			$color = $teams[$team]['color'];
		}
		echo '><td class="scorepl">';
		// Only print rank when score is different from the previous team
		if ( !isset($prevteam) || $SCORES[$prevteam]['rank']!=$totals['rank'] ) {
			echo jurylink(null,$totals['rank']);
		} else {
			echo jurylink(null,'');
		}
		$prevteam = $team;
		echo '</td>';
		if ( SHOW_AFFILIATIONS ) {
			echo '<td class="scoreaf">';
			if ( isset($teams[$team]['affilid']) ) {
				if ( IS_JURY ) {
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
				if ( IS_JURY ) echo '</a>';
			}
			echo '</td>';
		}
		echo
			'<td class="scoretn"' .
			(!empty($color) ? ' style="background: ' . $color . ';"' : '') .
			(IS_JURY ? ' title="' . htmlspecialchars($team) . '"' : '') . '>' .
			($static ? '' : '<a href="team.php?id=' . urlencode($team) . '">') .
			htmlspecialchars($teams[$team]['name']) .
			($static ? '' : '</a>') .
			'</td>';
		echo
			'<td class="scorenc">' . jurylink(null,$totals['num_correct']) . '</td>' .
			'<td class="scorett">' . jurylink(null,$totals['total_time'] ) . '</td>';

		// for each problem
		foreach ( array_keys($probs) as $prob ) {

			echo '<td class=';
			// CSS class for correct/incorrect/neutral results
			if( $MATRIX[$team][$prob]['is_correct'] ) { 
				echo '"score_correct"';
			} elseif ( $MATRIX[$team][$prob]['num_submissions'] > 0 ) {
				echo '"score_incorrect"';
			} else {
				echo '"score_neutral"';
			}
			// number of submissions for this problem
			$str = $MATRIX[$team][$prob]['num_submissions'];
			// if correct, print time scored
			if( $MATRIX[$team][$prob]['is_correct'] ) {
				$str .= ' (' . $MATRIX[$team][$prob]['time'] . ' + ' .
				               $MATRIX[$team][$prob]['penalty'] . ')';
			}
			echo '>' . jurylink('team.php?id=' . urlencode($team) .
								'&amp;restrict=probid:' . urlencode($prob),
			                    $str) . '</td>';
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n\n<tbody>\n";
	
	// print a summaryline
	echo '<tr id="scoresummary" title="#submitted / #correct / fastest time">' .
		'<td title="total teams">' .
		jurylink(null,count($teams)) . '</td>' .
		( SHOW_AFFILIATIONS ? '<td class="scoreaffil" title="#affiliations / #countries">' .
		  jurylink('team_affiliations.php',count($SUMMARY['affils']) . ' / ' .
				   count($SUMMARY['countries'])) . '</td>' : '' ) .
		'<td title=" ">' . jurylink(null,'Summary') . '</td>' .
		'<td title="total solved" class="scorenc">' . jurylink(null,$SUMMARY['num_correct'])  . '</td><td title=" "></td>';

	foreach( array_keys($probs) as $prob ) {
		$str = $SUMMARY['problems'][$prob]['num_submissions'] . ' / ' .
		       $SUMMARY['problems'][$prob]['num_correct'] . ' / ' .
			   ( isset($SUMMARY['problems'][$prob]['best_time']) ? 
				 $SUMMARY['problems'][$prob]['best_time'] : '-' );
		echo '<td>' .
			jurylink('problem.php?id=' . urlencode($prob),$str) .
			'</td>';
	}
	echo "</tr>\n</tbody>\n</table>\n\n";

	$categs = $DB->q('SELECT * FROM team_category ' .
	                 (IS_JURY ? '' : 'WHERE visible = 1 ' ) .
	                 'ORDER BY sortorder,name,categoryid');

	// only print legend when there's more than one category
	if ( $categs->count() > 1 ) {
		echo "<p><br /><br /></p>\n<table id=\"legend\" class=\"scoreboard" .
			(IS_JURY ? ' scoreboard_jury' : '') . "\">\n" .
			"<thead><tr><th scope=\"col\">" .
			jurylink('team_categories.php','Legend') .
			"</th></tr></thead>\n<tbody>\n";
		while ( $cat = $categs->next() ) {
			echo '<tr' . (!empty($cat['color']) ? ' style="background: ' .
				          $cat['color'] . ';"' : '') . '>' .
				'<td align="center" class="scoretn">' .
				jurylink('team_category.php?id=' . urlencode($cat['categoryid']),
					htmlspecialchars($cat['name'])) .	"</td></tr>\n";
		}
		echo "</tbody>\n</table>\n\n";
	}

	// last modified date, now if we are the jury, else include the
	// freeze time
	if( ! IS_JURY && $showfrozen ) {
		$lastupdate = strtotime($cdata['freezetime']);
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

	if ( empty( $cdata )  || difftime($cdata['starttime'],now()) > 0 ) return;
	$cid = $cdata['cid'];
	
	echo '<table class="scoreboard">' . "\n";

	$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, probid, name, color
	                 FROM problem WHERE cid = %i AND allow_submit = 1
	                 ORDER BY probid', $cid);

	// column headers
	echo "<tr class=\"scoreheader\"><th>problem</th><th>score</th></tr>\n";

	// initialize the arrays we'll build from the data
	$MATRIX = array();

	// for a team, we always display the "current" information, that is,
	// from scoreboard_jury
	$scoredata = $DB->q('SELECT * FROM scoreboard_jury WHERE cid = %i AND teamid = %s',
	                    $cid, $teamid);

	// loop all info the scoreboard cache and put it in our own datastructure
	while ( $srow = $scoredata->next() ) {
		// skip this row if the problem is not known by us
		if ( ! array_key_exists ( $srow['probid'], $probs ) ) continue;
	
		// fill our matrix with the scores from the database,
		$MATRIX[$srow['probid']] = array (
			'is_correct'      => (bool) $srow['is_correct'],
			'num_submissions' => $srow['submissions'],
			'time'            => $srow['totaltime'],
			'penalty'         => $srow['penalty'] );
	}

	$SUMMARY = array('num_correct' => 0, 'total_time' => 0);
	// for each problem
	foreach ( $probs as $prob => $probdata ) {
		// if we have scores, use them, else, provide the defaults
		// (happens when nothing submitted for this problem,team yet)
		if ( isset ( $MATRIX[$prob] ) ) {
			$pdata = $MATRIX[$prob];
		} else {
			$pdata = array ( 'num_submissions' => 0, 'is_correct' => 0,
			                 'time' => 0, 'penalty' => 0);
		}
		echo '<tr><td class="probid" title="' .
			htmlspecialchars($probdata['name']) . '">' .
			(!empty($probdata['color']) ? '<span style="color: ' .
			       htmlspecialchars($probdata['color']) . ';">' .
			       BALLOON_SYM . '</span> ' : '' ) .
			htmlspecialchars($prob) . '</td><td class="';
		// CSS class for correct/incorrect/neutral results
		if( $pdata['is_correct'] ) { 
			echo 'score_correct';
		} elseif ( $pdata['num_submissions'] > 0 ) {
			echo 'score_incorrect';
		} else {
			echo 'score_neutral';
		}
		// number of submissions for this problem
		echo '">' . $pdata['num_submissions'];
		// if correct, print time scored
		if( $pdata['is_correct'] ) {
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
function jurylink($target, $content) {

	$res = "";
	if ( IS_JURY ) {
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
	// else tie-breaker rule: fastest submission time for latest
	// correct problem, when times are equal, compare one-to-latest,
	// etc...
	$atimes = $a['solve_times'];
	$btimes = $b['solve_times'];
	rsort($atimes);
	rsort($btimes);
	for($i = 0; $i < count($atimes); $i++) {
		if ( $atimes[$i] != $btimes[$i] ) return $atimes[$i] < $btimes[$i] ? -1 : 1;
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
