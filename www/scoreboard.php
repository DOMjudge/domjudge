<?php

/**
 * Functions for calculating the scoreboard.
 *
 * $Id$
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
 */
function putScoreBoard($myteamid = null, $isjury = FALSE) {

	global $DB;

	$contdata = getCurContest(TRUE);
	if ( empty( $contdata ) ) { echo "<p><em>No contests defined</em></p>\n"; return; }
	$cid = $contdata['cid'];

	// get the teams and problems
	$teams = $DB->q('KEYTABLE SELECT login AS ARRAYKEY,
	                 login, team.name, team.categoryid, team.affilid,
	                 sortorder, color, country, team_affiliation.name AS affilname FROM team
	                 LEFT JOIN team_category ON (team.categoryid = team_category.categoryid)
	                 LEFT JOIN team_affiliation ON (team.affilid = team_affiliation.affilid)');
	$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,
	                 probid, name, color FROM problem
	                 WHERE cid = %i AND allow_submit = 1
	                 ORDER BY probid', $cid);

	// show final scores if contest is over and unfreezetime has been
	// reached, or if contest is over and no freezetime had been set
	$showfinal  = ( !isset($contdata['lastscoreupdate']) &&
		strtotime($contdata['endtime']) <= time() ) ||
		( isset($contdata['unfreezetime']) &&
		strtotime($contdata['unfreezetime']) <= time() );
	// freeze scoreboard if lastscoreupdate time has been reached and
	// we're not showing the final score yet
	$showfrozen = !$showfinal && isset($contdata['lastscoreupdate']) &&
		strtotime($contdata['lastscoreupdate']) <= time();

	// page heading with contestname and start/endtimes
	echo "<h1>Scoreboard " . htmlentities($contdata['contestname']) . "</h1>\n\n";

	if ( $showfinal ) {
		echo "<h4>final standings</h4>\n\n";
	} else {
		echo "<h4>starts: " . printtime($contdata['starttime']) .
				" - ends: " . printtime($contdata['endtime']) ;

		if ( $showfrozen ) {
			echo " (";
			if ( $isjury ) {
				echo "public scoreboard is ";
			}
			echo "frozen since " . printtime($contdata['lastscoreupdate']) .")";
		}
		echo "</h4>\n\n";
	}

	echo '<table class="scoreboard' . ($isjury ? ' scoreboard_jury' : '') . "\">\n";

	// output table column groups (for the styles)
	echo '<colgroup><col id="scoreplace" />' .
		( SHOW_AFFILIATIONS ? '<col id="scoreaffil" />' : '' ) .
		'<col id="scoreteamname" /><col id="scoresolv" /><col id="scoretotal" />' .
		str_repeat('<col class="scoreprob" />', count($probs)) .
		"</colgroup>\n";

	// column headers
	echo '<tr class="scoreheader"><th>' .
		jurylink(null,'#',$isjury) . '</th><th>' .
		( SHOW_AFFILIATIONS ? jurylink('affiliations.php','affil.',$isjury) .
		  '</th><th>' : '' ) .
		jurylink('teams.php','team',$isjury) . '</th><th>' .
		jurylink(null,'solved',$isjury) . '</th><th>' .
		jurylink(null,'time',$isjury) . "</th>\n";
	foreach( $probs as $pr ) {
		echo '<th title="' . htmlentities($pr['name']) . '"' .
			(isset($pr['color']) ? ' style="background: ' .
			 htmlspecialchars($pr['color']) . ';"' : '' ) . '>' .
			jurylink('problem.php?id=' . urlencode($pr['probid']),
			         htmlentities($pr['probid']),$isjury) . '</th>';
	}
	echo "</tr>\n";

	// initialize the arrays we'll build from the data
	$THEMATRIX = $SCORES = array();
	$SUMMARY = array('num_correct' => 0, 'total_time' => 0);

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
		$SCORES[$login]['teamname']    = $team['name'];
		$SCORES[$login]['categoryid']  = $team['categoryid'];
		$SCORES[$login]['sortorder']   = $team['sortorder'];
	}

	// loop all info the scoreboard cache and put it in our own datastructure
	while ( $srow = $scoredata->next() ) {
	
		// skip this row if the team or problem is not known by us
		if ( ! array_key_exists ( $srow['team'], $teams ) ||
		     ! array_key_exists ( $srow['probid'], $probs ) ) continue;
	
		// fill our matrix with the scores from the database,
		// we'll print this out later when we've sorted the teams
		$THEMATRIX[$srow['team']][$srow['probid']] = array (
			'correct' => (bool) $srow['is_correct'],
			'submitted' => $srow['submissions'],
			'time' => $srow['totaltime'],
			'penalty' => $srow['penalty'] );

		// calculate totals for this team
		if ( $srow['is_correct'] ) $SCORES[$srow['team']]['num_correct']++;
		$SCORES[$srow['team']]['total_time'] +=
			$srow['totaltime'] + $srow['penalty'];

	}

	// sort the array using our custom comparison function
	uasort($SCORES, 'cmp');

	// print the whole thing
	$prevsortorder = -1;
	foreach( $SCORES as $team => $totals ) {

		// place, team name, total correct, total time
		echo '<tr';
		if ( $totals['sortorder'] != $prevsortorder ) {
			echo ' class="sortorderswitch"';
			$prevsortorder = $totals['sortorder'];
			$place = 0; // reset team position on switch to different category
			$prevscores = array(-1,-1);
		}
		$place++;
		// check whether this is us, otherwise use category colour
		if ( @$myteamid == $team ) {
			echo ' id="scorethisisme"';
		} else {
			$color = $teams[$team]['color'];
		}
		echo '><td class="scorepl">';
		// Only print place when score is different from the previous team
		if ( $prevscores[0] != $totals['num_correct'] ||
			 $prevscores[1] != $totals['total_time'] ) {
			echo jurylink(null,$place,$isjury);
			$prevscores = array($totals['num_correct'], $totals['total_time']);
		} else {
			echo jurylink(null,'',$isjury);
		}
		echo '</td>';
		if ( SHOW_AFFILIATIONS ) {
			echo '<td>';
			if ( isset($teams[$team]['affilid']) ) {
				if ( $isjury ) {
					echo '<a href="affiliation.php?id=' .
						urlencode($teams[$team]['affilid']) . '">';
				}
				$affillogo = '../images/affiliations/' .
					urlencode($teams[$team]['affilid']) . '.png';
				if ( is_readable($affillogo) ) {
					echo '<img src="' . $affillogo . '"' .
						' alt="'   . htmlentities($teams[$team]['affilid']) . '"' .
						' title="' . htmlentities($teams[$team]['affilname']) . '" />';
				} else {
					echo htmlentities($teams[$team]['affilid']);
				}
				if ( isset($teams[$team]['country']) ) {
					$countryflag = '../images/countries/' .
						urlencode($teams[$team]['country']) . '.png';
					echo ' ';
					if ( is_readable($countryflag) ) {
						echo '<img src="' . $countryflag . '"' .
							' alt="'   . htmlentities($teams[$team]['country']) . '"' .
							' title="' . htmlentities($teams[$team]['country']) . '" />';
					} else {
						echo htmlentities($teams[$team]['country']);
					}
				}
				if ( $isjury ) echo '</a>';
			}
			echo '</td>';	
		}
		echo
			'<td class="scoretn"' .
			(isset($color) ? ' style="background: ' . $color . ';"' : '') .
			($isjury ? ' title="' . htmlspecialchars($team) . '"' : '') . '>' .
			jurylink('team.php?id=' . urlencode($team),
			         htmlentities($teams[$team]['name']),$isjury) .	'</td>' .
			'<td class="scorenc">' . jurylink(null,$totals['num_correct'],$isjury) . '</td>' .
			'<td class="scorett">' . jurylink(null,$totals['total_time'], $isjury) . '</td>';

		// keep summary statistics for the bottom row of our table
		$SUMMARY['num_correct'] += $totals['num_correct'];
		$SUMMARY['total_time']  += $totals['total_time'];

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
			if( ($pdata['time']+$pdata['penalty']) > 0) {
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

	// print a summaryline
	echo '<tr id="scoresummary" title="#submitted / #correct / fastest time">' .
	     '<td title="total teams">' . jurylink(null,count($teams),$isjury) . '</td>' .
	     ( SHOW_AFFILIATIONS ? '<td title=" "></td>' : '' ) .
	     '<td title=" ">' . jurylink(null,'Summary',$isjury) . '</td>';
	echo '<td class="scorenc" title="total solved">' .
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
		echo '<td' . (isset($pr['color']) ? ' style="background: ' .
					  $pr['color'] . ';"' : '') . '>' .
			jurylink('problem.php?id=' . urlencode($pr['probid']),$str,$isjury) . '</td>';
	}
	echo "</tr>\n</table>\n\n";

	$categs = $DB->q('SELECT * FROM team_category ORDER BY categoryid');

	// only print legend when there's more than one category
	if ( $categs->count() > 1 ) {
		echo "<p><br /><br /></p>\n<table class=\"scoreboard" .
			($isjury ? ' scoreboard_jury' : '') . "\">\n" .
			"<tr><th>" . jurylink('categories.php','Legend',$isjury) . "</th></tr>\n";
		while ( $cat = $categs->next() ) {
			echo '<tr' . (isset($cat['color']) ? ' style="background: ' .
			              $cat['color'] . ';"' : '') . '>' .
				'<td align="center" class="scoretn">' .
				jurylink(null,htmlspecialchars($cat['name']),$isjury) .	"</td></tr>\n";
		}
		echo "</table>\n\n";
	}

	// last modified date, now if we are the jury, else include the
	// lastscoreupdate time
	if( ! $isjury && $showfrozen ) {
		$lastupdate = strtotime($contdata['lastscoreupdate']);
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
function putTeamRow($teamid) {

	global $DB;

	$contdata = getCurContest(TRUE);
	if ( empty( $contdata ) ) return;
	$cid = $contdata['cid'];
	
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
	$scoredata = $DB->q("SELECT * FROM scoreboard_jury WHERE cid = %i AND team = %s",
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
		echo '<tr><td title="' . htmlentities($probdata['name']) .
			(isset($probdata['color']) ? '" style="background: ' .
			       htmlspecialchars($probdata['color']) . ';' : '' ) . '">' .
			htmlentities($prob) . '</td><td class="';
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

// function to generate links for jury only
function jurylink($target, $content, $isjury) {

	$res = "";
	if ( $isjury ) {
		$res .= '<a' . (isset($target) ? ' href="' . $target . '"' : '' ) . '>';
	}
	$res .= $content;
	if ( $isjury ) $res .= '</a>';
	
	return $res;
}

// comparison function for scoreboard
function cmp ($a, $b) {
	// first order by our predefined sortorder based on category
	if ( $a['sortorder'] != $b['sortorder'] ) {
		return $a['sortorder'] < $b['sortorder'] ? -1 : 1;
	}
	// more correct than someone else means higher rank
	if ( $a['num_correct'] != $b['num_correct'] ) {
		return $a['num_correct'] > $b['num_correct'] ? -1 : 1;
	}
	// else, less time spent means higher rank
	if ( $a['total_time'] != $b['total_time'] ) {
		return $a['total_time'] < $b['total_time'] ? -1 : 1;
	}
	// else, order by teamname alphabetically
	if ( $a['teamname'] != $b['teamname'] ) {
		return strcasecmp($a['teamname'],$b['teamname']);
	}
	// undecided, should never happen in practice
	return 0;
}

