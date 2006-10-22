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
	
	// page heading with contestname and start/endtimes
	echo "<h1>Scoreboard " . htmlentities($contdata['contestname']) . "</h1>\n\n";
	echo "<h4>starts: " . printtime($contdata['starttime']) .
	        " - ends: " . printtime($contdata['endtime']) ;

	if ( isset($contdata['lastscoreupdate']) &&
		strtotime($contdata['lastscoreupdate']) <= time() ) {
		echo " (";
		if ( $isjury ) {
			echo "public scoreboard is ";
		}
		echo "frozen since " . printtime($contdata['lastscoreupdate']) .")";
	}
	echo "</h4>\n\n";

	echo '<table class="scoreboard" cellpadding="3">' . "\n";

	// get the teams and problems
	$teams = $DB->q('KEYTABLE SELECT login AS ARRAYKEY,
	                 login, team.name, team.categoryid, sortorder FROM team
	                 LEFT JOIN team_category USING (categoryid)');
	$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,
	                 probid, name FROM problem
	                 WHERE cid = %i AND allow_submit = 1
                         ORDER BY probid', $cid);

	// output table column groups (for the styles)
	echo '<colgroup><col id="scoreplace" /><col id="scoreteamname" /><col id="scoresolv" />' .
		'<col id="scoretotal" />' .
		str_repeat('<col class="scoreprob" />', count($probs)) .
		"</colgroup>\n";

	// column headers
	echo '<tr class="scoreheader"><th>#</th><th>team</th>';
	echo "<th>solved</th><th>time</th>\n";
	foreach( $probs as $pr ) {
		echo '<th title="' . htmlentities($pr['name']). '">' .
			htmlentities($pr['probid']) . '</th>';
	}
	echo "</tr>\n";

	// initialize the arrays we'll build from the data
	$THEMATRIX = $SCORES = array();
	$SUMMARY = array('num_correct' => 0, 'total_time' => 0);

	// Get all stuff from the cached table, but don't bother with outdated
	// info from previous contests.
	
	if ( $isjury ) {
		$cachetable = 'scoreboard_jury';
	} else {
		$cachetable = 'scoreboard_public';
	}
	
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

		// team name, total correct, total time
		echo '<tr' . ( @$myteamid == $team ? ' id="scorethisisme"' : '' ) ;
		if ( $totals['sortorder'] != $prevsortorder ) {
			echo ' class="sortorderswitch"';
			$prevsortorder = $totals['sortorder'];
			$place = 0; // reset team position on switch to different categories
			$prevscores = array(-1,-1);
		}
		$place++;
		echo
			'><td class="scorepl">';
		if ( $prevscores[0] != $totals['num_correct'] ||
			 $prevscores[1] != $totals['total_time'] ) {
			echo $place;
			$prevscores = array($totals['num_correct'], $totals['total_time']);
		}
		echo
			'</td>' .
			'<td class="scoretn category' . $totals['categoryid'] . '">' .
			htmlentities($teams[$team]['name']) . '</td>' .
			'<td class="scorenc">' . $totals['num_correct'] . '</td>' .
			'<td class="scorett">' . $totals['total_time'] . '</td>';

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
			echo '>' . $pdata['submitted'];
			// if correct, print time scored
			if( ($pdata['time']+$pdata['penalty']) > 0) {
				echo " (" . $pdata['time'] . ' + ' . $pdata['penalty'] . ")";
			}
			echo "</td>";
			
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
	     '<td></td><td title=" ">Summary</td>';
	echo '<td class="scorenc" title="total solved">' . $SUMMARY['num_correct'] . '</td>' .
	     '<td class="scorett" title="total time">' . $SUMMARY['total_time'] . '</td>';

	foreach( $probs as $pr ) {
		if ( !isset($SUMMARY[$pr['probid']]) ) {
			echo '<td> 0 / 0 / -</td>';
		} else {
			echo '<td>' . $SUMMARY[$pr['probid']]['submissions'] . ' / ' .
				$SUMMARY[$pr['probid']]['correct'] . ' / ' .
				( isset($SUMMARY[$pr['probid']]['times']) ?
				  min(@$SUMMARY[$pr['probid']]['times']) : '-' ) . "</td>";
		}
	}
	echo "</tr>\n\n";

	echo "</table>\n\n";

	$res = $DB->q('SELECT * FROM team_category ORDER BY categoryid');

	// only print legend when there's more than one category
	if ( $res->count() > 1 ) {
		echo "<p><br /><br /></p>\n<table class=\"scoreboard\"><tr>" .
			"<th>Legend</th></tr>\n";
		while ( $row = $res->next() ) {
			echo '<tr class="category' . $row['categoryid'] . '">' .
				'<td align="center" class="scoretn">' .	$row['name'] .
				"</td></tr>";
		}
		echo "</table>\n\n";
	}

	// last modified date, now if we are the jury, else include the
	// lastscoreupdate time
	if( ! $isjury && isset($contdata['lastscoreupdate']) ) {
		$lastupdate = min(time(), strtotime($contdata['lastscoreupdate']));
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
	
	echo '<table class="scoreboard" cellpadding="3">' . "\n";

	$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, probid, name
	                 FROM problem WHERE cid = %i AND allow_submit = 1
	                 ORDER BY probid', $cid);

	// column headers
	echo "<tr class=\"scoreheader\"><th>problem</th><th>score</th></tr>\n";

	// initialize the arrays we'll build from the data
	$THEMATRIX = array();
	
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
		echo '<tr><td title="' . htmlentities($probdata['name']) . '">' .
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

