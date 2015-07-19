<?php

/**
 * Functions for calculating the scoreboard.
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
 * 'scorecache_{public,jury}'. If the function is called while
 * $jury set to true, the scoreboard will always be
 * current, regardless of the freezetime setting in the contesttable.
 *
 * The $filter argument may contain subarrays 'affilid', 'country',
 * 'categoryid' of values to filter on these.
 *
 * This function returns an array (scores, summary, matrix)
 * containing the following:
 *
 * scores[teamid](num_points, total_time, solve_times[], rank,
 *               teamname, categoryid, sortorder, country, affilid)
 *
 * matrix[teamid][probid](is_correct, num_submissions, num_pending, time, penalty)
 *
 * summary(num_points, total_time, affils[affilid], countries[country], problems[probid]
 *    probid(num_submissions, num_pending, num_correct, best_time_sort[sortorder] )
 */
function genScoreBoard($cdata, $jury = FALSE, $filter = NULL) {

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
	if ( ! $cstarted && ! $jury ) return;

	// get the teams, problems and categories
	$teams = getTeams($filter, $jury, $cdata);
	$probs = getProblems($cdata);
	$categs = getCategories($jury);

	// initialize the arrays we'll build from the data
	$MATRIX = array();
	$SUMMARY = initSummary($probs);
	$SCORES = initScores($teams);

	// scorecache_jury is always up to date, scorecache_public might be frozen.
	if ( $jury || $showfinal ) {
		$cachetable = 'scorecache_jury';
	} else {
		$cachetable = 'scorecache_public';
	}

	// Get all stuff from the cached table from this contest
	$query = "SELECT points, $cachetable.* FROM $cachetable JOIN contestproblem USING(probid,cid) WHERE cid = %i";
	$scoredata = $DB->q($query, $cid);

	// loop all info the scoreboard cache and put it in our own datastructure
	while ( $srow = $scoredata->next() ) {

		// skip this row if the team or problem is not known by us
		if ( ! array_key_exists ( $srow['teamid'], $teams ) ||
		     ! array_key_exists ( $srow['probid'], $probs ) ) continue;

		$penalty = calcPenaltyTime( $srow['is_correct'], $srow['submissions'] );

		// fill our matrix with the scores from the database
		$MATRIX[$srow['teamid']][$srow['probid']] = array (
			'is_correct'      => (bool) $srow['is_correct'],
			'num_submissions' => $srow['submissions'],
			'num_pending'     => $srow['pending'],
			'time'            => $srow['totaltime'],
			'penalty'         => $penalty );

		// calculate totals for this team
		if ( $srow['is_correct'] ) {
			$SCORES[$srow['teamid']]['num_points'] += $srow['points'];
			$SCORES[$srow['teamid']]['solve_times'][] = $srow['totaltime'];
			$SCORES[$srow['teamid']]['total_time'] += $srow['totaltime'] + $penalty;
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
		// The num_points summary is useful only if they're all 1-point problems.
		$SUMMARY['num_points'] += $totals['num_points'];
		if ( ! empty($teams[$team]['affilid']) ) @$SUMMARY['affils'][$totals['affilid']]++;
		if ( ! empty($teams[$team]['country']) ) @$SUMMARY['countries'][$totals['country']]++;

		// for each problem
		foreach ( array_keys($probs) as $prob ) {

			// provide default scores when nothing submitted for this team,problem yet
			if ( ! isset ( $MATRIX[$team][$prob] ) ) {
				$MATRIX[$team][$prob] = array('num_submissions' => 0, 'num_pending' => 0,
				                              'is_correct' => false, 'time' => 0, 'penalty' => 0);
			}
			$pdata = $MATRIX[$team][$prob];
			$psum = &$SUMMARY['problems'][$prob];

			// update summary data for the bottom row
			@$psum['num_submissions'] += $pdata['num_submissions'];
			@$psum['num_pending'] += $pdata['num_pending'];
			@$psum['num_correct'] += ($pdata['is_correct'] ? 1 : 0);

			if ( $pdata['is_correct'] ) {
				// store per sortorder the first solve time
				if ( !isset($psum['best_time_sort'][$totals['sortorder']]) ||
				     $pdata['time']<$psum['best_time_sort'][$totals['sortorder']] ) {
					@$psum['best_time_sort'][$totals['sortorder']] = $pdata['time'];
				}
			}
		}
	}

	return array( 'matrix'     => $MATRIX,
	              'scores'     => $SCORES,
	              'summary'    => $SUMMARY,
	              'teams'      => $teams,
	              'problems'   => $probs,
	              'categories' => $categs );
}

/**
 * Helper function for genScoreBoard.
 *
 * Return the problems for a given contest.
 */
function getProblems($cdata) {
	global $DB;

	return $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, probid, points, shortname,
	               name, color, LENGTH(problemtext) AS hastext
	               FROM problem
	               INNER JOIN contestproblem USING (probid)
	               WHERE cid = %i AND allow_submit = 1
	               ORDER BY shortname', $cdata['cid']);
}

/**
 * Helper function for genScoreBoard.
 *
 * Return all teams of current contest, possibly filtered.
 */
function getTeams($filter, $jury, $cdata) {
	global $DB;

	return $DB->q('KEYTABLE SELECT team.teamid AS ARRAYKEY, team.teamid, externalid,
	               team.name, team.categoryid, team.affilid, penalty, sortorder,
	               country, color, team_affiliation.name AS affilname
	               FROM team
	               INNER JOIN contest ON (contest.cid = %i)
	               LEFT JOIN contestteam ct USING (teamid, cid)
	               LEFT JOIN team_category USING (categoryid)
	               LEFT JOIN team_affiliation USING (affilid)
	               WHERE team.enabled = 1 AND (ct.teamid IS NOT NULL OR contest.public = 1)' .
	              ( $jury ? '' : ' AND visible = 1' ) .
	              (isset($filter['affilid']) ? ' AND team.affilid IN (%As) ' : ' %_') .
	              (isset($filter['country']) ? ' AND country IN (%As) ' : ' %_') .
	              (isset($filter['categoryid']) ? ' AND team.categoryid IN (%As) ' : ' %_') .
	              (isset($filter['teams']) ? ' AND team.teamid IN (%Ai) ' : ' %_'),
	              $cdata['cid'], @$filter['affilid'], @$filter['country'],
	              @$filter['categoryid'], @$filter['teams']);
}

/**
 * Helper function for genScoreBoard.
 *
 * Get all team categories.
 */
function getCategories($jury) {
	global $DB;

	return $DB->q('KEYTABLE SELECT categoryid AS ARRAYKEY,
	               categoryid, name, color FROM team_category ' .
	              ($jury ? '' : 'WHERE visible = 1 ' ) .
	              'ORDER BY sortorder,name,categoryid');
}

/**
 * Helper function for genScoreBoard.
 *
 * Initialize SCORES table contains the totals for each team which are
 * used for determining the ranking.
 */
function initScores($teams) {
	$SCORES = array();
	foreach ($teams as $teamid => $team ) {
		$SCORES[$teamid]['num_points']  = 0;
		$SCORES[$teamid]['total_time']  = $team['penalty'];
		$SCORES[$teamid]['solve_times'] = array();
		$SCORES[$teamid]['rank']        = 0;
		$SCORES[$teamid]['teamname']    = $team['name'];
		$SCORES[$teamid]['categoryid']  = $team['categoryid'];
		$SCORES[$teamid]['sortorder']   = $team['sortorder'];
		$SCORES[$teamid]['affilid']     = $team['affilid'];
		$SCORES[$teamid]['country']     = $team['country'];
	}
	return $SCORES;
}

/**
 * Helper function for genScoreBoard.
 *
 * Initialize SUMMARY table.
 */
function initSummary($probs) {
	$SUMMARY = array('num_points' => 0,
	                 'affils'      => array(),
	                 'countries'   => array(),
	                 'problems'    => array());

	// initialize all problems with data
	foreach( array_keys($probs) as $prob ) {
		if ( !isset($SUMMARY['problems'][$prob]) ) {
			$SUMMARY['problems'][$prob]['num_submissions'] = 0;
			$SUMMARY['problems'][$prob]['num_pending'] = 0;
			$SUMMARY['problems'][$prob]['num_correct'] = 0;
			$SUMMARY['problems'][$prob]['best_time_sort'] = array();
		}
	}
	return $SUMMARY;
}

/**
 * Output the general scoreboard based on the cached data in table
 * 'scorecache_{team,jury}'. $myteamid can be passed to highlight a
 * specific row.
 * If this function is called while IS_JURY is defined, the scoreboard
 * will always be current, regardless of the freezetime setting in the
 * contesttable.
 * $static generates output suitable for standalone static html pages,
 * that is without references/links to other parts of the DOMjudge
 * interface.
 * $limitteams is an array of teamid's whose rows will be the only
 * ones displayed. The function still needs the complete scoreboard
 * data or it will not know the rank.
 * if $displayrank is false the first column will not display the
 * team's current rank but a question mark.
 */
function renderScoreBoardTable($sdata, $myteamid = null, $static = FALSE,
	$limitteams = null, $displayrank = TRUE, $center = FALSE, $showlegends = TRUE)
{
	// 'unpack' the scoreboard data:
	$scores  = $sdata['scores'];
	$matrix  = $sdata['matrix'];
	$summary = $sdata['summary'];
	$teams   = $sdata['teams'];
	$probs   = $sdata['problems'];
	$categs  = $sdata['categories'];
	unset($sdata);

	// configuration
	$SHOW_AFFILIATIONS = dbconfig_get('show_affiliations', 1);
	$SHOW_PENDING      = dbconfig_get('show_pending', 0);

	// Do not show points if they are all 1
	$showpoints = FALSE;
	foreach( $probs as $pr ) {
		if ($pr['points'] != 1) {
			$showpoints = TRUE;
			break;
		}
	}
	echo '<table class="scoreboard' . (IS_JURY ? ' scoreboard_jury' : '') . ($center ? ' center' : '') . "\">\n";

	// output table column groups (for the styles)
	echo '<colgroup><col id="scorerank" />' .
		( $SHOW_AFFILIATIONS ? '<col id="scoreaffil" />' : '' ) .
		'<col id="scoreteamname" /></colgroup><colgroup><col id="scoresolv" />' .
		"<col id=\"scoretotal\" /></colgroup>\n<colgroup>" .
		str_repeat('<col class="scoreprob" />', count($probs)) .
		"</colgroup>\n";

	// column headers
	echo "<thead>\n";
	echo '<tr class="scoreheader">' .
		'<th title="rank" scope="col">' . jurylink(null,'rank') . '</th>' .
		'<th title="team name" scope="col"' .
		( $SHOW_AFFILIATIONS ? ' colspan="2"' : '' ) .
		'>' . jurylink(null, 'team') . '</th>' .
		'<th title="# solved / penalty time" colspan="2" scope="col">' .
		jurylink(null, 'score') . '</th>' . "\n";
	foreach( $probs as $pr ) {
		echo '<th title="problem \'' . htmlspecialchars($pr['name']) . '\'" scope="col">';
		$str = htmlspecialchars($pr['shortname']) .
		       (!empty($pr['color']) ? ' <div class="circle" style="background: ' .
			htmlspecialchars($pr['color']) . ';"></div>' : '') ;

		if ( !$static && (IS_JURY || $pr['hastext']>0) ) {
			echo '<a href="problem.php?id=' . urlencode($pr['probid']) .
				'">' . $str . '</a>';
		} else {
			echo '<a>' . $str . '</a>';
		}
		if ($showpoints) {
			$points = $pr['points'];
			$pts = ( $points == 1 ? '1 point' : "$points points" );
			echo "<span class='problempoints'>[$pts]</span>";
		}
		echo '</th>';
	}
	echo "</tr>\n</thead>\n\n<tbody>\n";

	// print the main scoreboard rows
	$prevsortorder = -1;
	foreach( $scores as $team => $totals ) {
		// skip if we have limitteams and the team is not listed
		if ( !empty($limitteams) && !in_array($team,$limitteams) ) continue;

		// rank, team name, total points, total time
		echo '<tr';
		$classes = array();
		if ( $totals['sortorder'] != $prevsortorder ) {
			$classes[] = "sortorderswitch";
			$prevsortorder = $totals['sortorder'];
			$prevteam = null;
		}
		// check whether this is us, otherwise use category colour
		if ( @$myteamid == $team ) {
			$classes[] = "scorethisisme";
			unset($color);
		} else {
			$color = $teams[$team]['color'];
		}
		if ( count($classes)>0 ) {
			echo ' class="' . implode(' ', $classes) . '"';
		}
		echo ' id="team:' . $teams[$team]['teamid'] . '"';
		echo '><td class="scorepl">';
		// Only print rank when score is different from the previous team
		if ( ! $displayrank ) {
			echo jurylink(null,'?');
		} elseif ( !isset($prevteam) || $scores[$prevteam]['rank']!=$totals['rank'] ) {
			echo jurylink(null,$totals['rank']);
		} else {
			echo jurylink(null,'');
		}
		$prevteam = $team;
		echo '</td>';
		if ( $SHOW_AFFILIATIONS ) {
			echo '<td class="scoreaf">';
			if ( isset($teams[$team]['affilid']) ) {
				if ( IS_JURY ) {
					echo '<a href="team_affiliation.php?id=' .
						urlencode($teams[$team]['affilid']) . '">';
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
		$affilname = '';
		if ( $SHOW_AFFILIATIONS && isset($teams[$team]['affilid']) ) {
				$affilname = htmlspecialchars($teams[$team]['affilname']);
		}
		echo
			'<td class="scoretn"' .
			(!empty($color) ? ' style="background: ' . $color . ';"' : '') .
			(IS_JURY ? ' title="' . htmlspecialchars($team) . '"' : '') . '>' .
			($static ? '' : '<a href="team.php?id=' . urlencode($team) . '">') .
			htmlspecialchars($teams[$team]['name']) .
			($SHOW_AFFILIATIONS ? '<br /><span class="univ">' . $affilname .
			 '</span>' : '') .
			($static ? '' : '</a>') .
			'</td>';
		echo
			'<td class="scorenc">' . jurylink(null,$totals['num_points']) . '</td>' .
			'<td class="scorett">' . jurylink(null,$totals['total_time'] ) . '</td>';

		// for each problem
		foreach ( array_keys($probs) as $prob ) {

			echo '<td class=';
			// CSS class for correct/incorrect/neutral results
			if( $matrix[$team][$prob]['is_correct'] ) {
				// The best times for each problem may not have been
				// calculated (if called from putTeamRow()), so we
				// have to suppress an undefined index here.
				echo '"score_correct' .
					( @$summary['problems'][$prob]['best_time_sort'][$totals['sortorder']]
				      ===$matrix[$team][$prob]['time'] ? ' score_first' : '') . '"';
			} elseif ( $matrix[$team][$prob]['num_pending'] > 0 && $SHOW_PENDING ) {
				echo '"score_pending"';
			} elseif ( $matrix[$team][$prob]['num_submissions'] > 0 ) {
				echo '"score_incorrect"';
			} else {
				echo '"score_neutral"';
			}
			// number of submissions for this problem
			$str = $matrix[$team][$prob]['num_submissions'];
			// add pending submissions
			if( $matrix[$team][$prob]['num_pending'] > 0 && $SHOW_PENDING ) {
				$str .= ' + ' . $matrix[$team][$prob]['num_pending'];
			}
			// if correct, print time scored
			if( $matrix[$team][$prob]['is_correct'] ) {
				$str .= '/' . $matrix[$team][$prob]['time'];
			}
			echo '>' . jurylink('team.php?id=' . urlencode($team) .
								'&amp;restrict=probid:' . urlencode($prob),
			                    $str) . '</td>';
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n\n";

	if ( empty($limitteams) ) {
		// print a summaryline. Exclude the "total solved" cell if using
		// perproblem points as it's actually total points and not useful
		if (!$showpoints) {
			$totalCell = '<td title="total solved" class="scorenc">' .
			             jurylink(null,$summary['num_points'])  . '</td>';
		} else {
			$totalCell = '<td class="scorenc" title=" "></td>';  // Empty
		}
		echo '<tbody><tr id="scoresummary" title="#submitted / #correct">' .
			'<td title="total teams">' .
			jurylink(null,count($matrix)) . '</td>' .
			( $SHOW_AFFILIATIONS ? '<td class="scoreaffil" title="#affiliations / #countries">' .
			  jurylink('team_affiliations.php',count($summary['affils']) . ' / ' .
			           count($summary['countries'])) . '</td>' : '' ) .
			'<td title=" ">' . jurylink(null,'Summary') . '</td>' .
			$totalCell . '<td title=" "></td>';

		foreach( array_keys($probs) as $prob ) {
			$str = $summary['problems'][$prob]['num_submissions'] . '/' .
			       $summary['problems'][$prob]['num_correct'];
			echo '<td>' .
				jurylink('problem.php?id=' . urlencode($prob),$str) .
				'</td>';
		}
		echo "</tr>\n</tbody>\n";
	}

	echo "</table>\n\n";

	if ( $showlegends ) {
		echo "<p><br /><br /></p>\n";

		// only print legend when there's more than one category
		if ( empty($limitteams) && count($categs) > 1 ) {
			echo "<table id=\"categ_legend\" class=\"scoreboard scorelegend" .
			    (IS_JURY ? ' scoreboard_jury' : '') . "\">\n" .
			    "<thead><tr><th scope=\"col\">" .
			    jurylink('team_categories.php','Categories') .
			    "</th></tr></thead>\n<tbody>\n";
			foreach( $categs as $cat ) {
				echo '<tr' . (!empty($cat['color']) ? ' style="background: ' .
				              $cat['color'] . ';"' : '') . '>' .
				    '<td>' .
				    jurylink('team_category.php?id=' . urlencode($cat['categoryid']),
				             htmlspecialchars($cat['name'])) .	"</td></tr>\n";
			}
			echo "</tbody>\n</table>\n&nbsp;\n";
		}

		// print legend of scorecell colors
		$cellcolors = array('first'     => 'Solved first',
		                    'correct'   => 'Solved',
		                    'incorrect' => 'Tried, incorrect',
		                    'pending'   => 'Tried, pending',
		                    'neutral'   => 'Untried');


		echo "<table id=\"cell_legend\" class=\"scoreboard scorelegend" .
		    (IS_JURY ? ' scoreboard_jury' : '') . "\">\n" .
		    "<thead><tr><th scope=\"col\">" . jurylink(null,'Cell colours') .
		    "</th></tr></thead>\n<tbody>\n";
		foreach( $cellcolors as $color => $desc ) {
			if ( $color=='pending' && !dbconfig_get('show_pending', 0) ) continue;
			echo '<tr class="score_' . $color . '">' .
			    '<td>' . jurylink(null, $desc) . "</td></tr>\n";
		}
		echo "</tbody>\n</table>\n\n";
	}

	return;
}

/**
 * Function to output a complete scoreboard.
 * This takes care of outputting the headings, start/endtimes and footer
 * of the scoreboard. It calls genScoreBoard to generate the data and
 * renderScoreBoardTable for displaying the actual table.
 *
 * Arguments:
 * $cdata       current contest data, as from an index in 'getCurContests(TRUE)'
 * $myteamid    set to highlight that teamid in the scoreboard
 * $static      generate a static scoreboard, e.g. for external use
 * $filter      set to TRUE to generate filter options, or pass array
 *              with keys 'affilid', 'country', 'categoryid' pointing
 *              to array of values to filter on these.
 * $sdata       if not NULL, use this as scoreboard data instead of fetching it locally
 */
function putScoreBoard($cdata, $myteamid = NULL, $static = FALSE, $filter = FALSE, $sdata = NULL)
{
	global $DB, $pagename;

	if ( empty( $cdata ) ) { echo "<p class=\"nodata\">No active contest</p>\n"; return; }

	$fdata = calcFreezeData($cdata);
	if ( $sdata === NULL ) {
		$sdata = genScoreBoard($cdata, IS_JURY, $filter);
	}

	// page heading with contestname and start/endtimes
	echo "<h1>Scoreboard " . htmlspecialchars($cdata['name']) . "</h1>\n\n";

	if ( $fdata['showfinal'] ) {
		echo "<h4>final standings</h4>\n\n";
	} elseif ( ! $fdata['cstarted'] ) {
		echo "<h4>" . printContestStart($cdata) . "</h4>\n\n";
		// Stop here (do not leak problem number, descriptions etc).
		// Alternatively we could only display the list of teams?
		if ( ! IS_JURY ) return;
	} else {
		echo "<h4>starts: " . printtime($cdata['starttime']) .
				" - ends: " . printtime($cdata['endtime']) ;

		if ( $fdata['showfrozen'] ) {
			echo " (";
			if ( IS_JURY ) {
				echo '<a href="../public/">the public scoreboard</a> is ';
			}
			echo "frozen since " . printtime($cdata['freezetime']) .")";
		}
		echo "</h4>\n\n";
	}

	// The static scoreboard does not support filtering
	if ( $filter!==FALSE && $static!==TRUE ) {

		$categids = $DB->q('KEYVALUETABLE SELECT categoryid, name FROM team_category ' .
		                   (IS_JURY ? '' : 'WHERE visible = 1 ' ));
		// show only affilids/countries with visible teams
		if ( empty($categids) ) {
			$affils = array();
		} else {
			$affils = $DB->q('KEYTABLE SELECT affilid AS ARRAYKEY,
			                  team_affiliation.name, country
			                  FROM team_affiliation
			                  LEFT JOIN team t USING (affilid)
			                  INNER JOIN contest c ON (c.cid = %i)
			                  LEFT JOIN contestteam ct ON (ct.teamid = t.teamid AND ct.cid = c.cid)
			                  WHERE categoryid IN (%As) AND c.cid = %i AND
			                  (c.public = 1 OR ct.teamid IS NOT NULL)
			                  GROUP BY affilid',
			                 $cdata['cid'], array_keys($categids), $cdata['cid']);
		}

		$affilids  = array();
		$countries = array();
		foreach( $affils as $id => $affil ) {
			$affilids[$id] = $affil['name'];
			if ( isset($affil['country']) ) $countries[] = $affil['country'];
		}

		$countries = array_unique($countries);
		sort($countries);

		$filteron = array();
		$filtertext = "";
		foreach (array('affilid' => 'affiliation', 'country' => 'country', 'categoryid' => 'category') as $type => $text) {
			if ( isset($filter[$type]) ) {
				$filteron[] = $text;
			}
		}
		if ( sizeof($filteron) > 0 ) {
			$filtertext = "(filtered on " . implode(", ", $filteron) . ")";
		}

		require_once(LIBWWWDIR . '/forms.php');
		?>

<table class="scorefilter">
<tr>
<td><a class="collapse" href="javascript:collapse('filter')"><img src="../images/filter.png" alt="filter&hellip;" title="filter&hellip;" class="picto" /></a></td>
<td><?= $filtertext ?></td>
<td><div id="detailfilter">
<?php

		echo addForm($pagename, 'get') .
			( count($affilids) > 1 ? addSelect('affilid[]',    $affilids,  @$filter['affilid'],    TRUE,  8) : "" ) .
			( count($countries)> 1 ? addSelect('country[]',    $countries, @$filter['country'],    FALSE, 8) : "" ) .
			( count($categids) > 1 ? addSelect('categoryid[]', $categids,  @$filter['categoryid'], TRUE,  8) : "" ) .
			addSubmit('filter', 'filter') . addSubmit('clear', 'clear') .
			addEndForm();
		?>
</div></td></tr>
</table>
<script type="text/javascript">
<!--
collapse("filter");
// -->
</script>
		<?php
	}

	renderScoreBoardTable($sdata,$myteamid,$static);

	// last modified date, now if we are the jury, else include the
	// freeze time
	if( ! IS_JURY && $fdata['showfrozen'] ) {
		$lastupdate = printtime($cdata['freezetime'],'%a %d %b %Y %T %Z');
	} else {
		$lastupdate = printtime(now(),'%a %d %b %Y %T %Z');
	}
	echo "<p id=\"lastmod\">Last Update: $lastupdate<br />\n" .
	     "using <a href=\"https://www.domjudge.org/\">DOMjudge</a></p>\n\n";

	return;
}

/**
 * Reads scoreboard filter settings from a cookie and explicit POST of
 * filter settings. Also sets the cookie, so must be called before
 * headers are sent. Returns the scoreboard filter settings array.
 */
function initScorefilter()
{
	$scorefilter = array();

	// Read scoreboard filter options from cookie and explicit POST
	if ( isset($_COOKIE['domjudge_scorefilter']) ) {
		$scorefilter = json_decode($_COOKIE['domjudge_scorefilter'], TRUE);
	}

	if ( isset($_REQUEST['clear']) ) $scorefilter = array();

	if ( isset($_REQUEST['filter']) ) {
		$scorefilter = array();
		foreach( array('affilid', 'country', 'categoryid') as $type ) {
			if ( !empty($_REQUEST[$type]) ) {
				$scorefilter[$type] = $_REQUEST[$type];
			}
		}
	}

	dj_setcookie('domjudge_scorefilter', json_encode($scorefilter));

	return $scorefilter;
}

/**
 * Given an array of contest data, calculates whether the contest
 * has already started ('cstarted'), and if scoreboard is currently
 * frozen ('showfrozen') or final ('showfinal').
 */
function calcFreezeData($cdata)
{
	$fdata = array();

	if ( $cdata == null ) {
		return array(
			'showfinal' => false,
			'showfrozen' => false,
			'cstarted' => false
		);
	}

	// Show final scores if contest is over and unfreezetime has been
	// reached, or if contest is over and no freezetime had been set.
	// We can compare $now and the dbfields stringwise.
	$now = now();
	$fdata['showfinal']  = ( !isset($cdata['freezetime']) &&
	                difftime($cdata['endtime'],$now) <= 0 ) ||
	              ( isset($cdata['unfreezetime']) &&
	                difftime($cdata['unfreezetime'], $now) <= 0 );
	// freeze scoreboard if freeze time has been reached and
	// we're not showing the final score yet
	$fdata['showfrozen'] = !$fdata['showfinal'] && isset($cdata['freezetime']) &&
	              difftime($cdata['freezetime'],$now) <= 0;
	// contest is active but has not yet started
	$fdata['cstarted'] = difftime($cdata['starttime'],$now) <= 0;

	return $fdata;
}

/**
 * Output a team row from the scoreboard based on the cached data in
 * table 'scoreboard'.
 */
function putTeamRow($cdata, $teamids) {
	global $DB;

	if ( empty($cdata) ) return;

	$fdata = calcFreezeData($cdata);
	$displayrank = IS_JURY || !$fdata['showfrozen'];
	$cid = $cdata['cid'];

	if ( ! $fdata['cstarted'] ) {
		if ( ! IS_JURY ) {

			global $teamdata;
			echo "<h2 id=\"teamwelcome\">welcome team <span id=\"teamwelcometeam\">" .
				htmlspecialchars($teamdata['name']) . "</span>!</h2>\n\n";
			echo "<h3 id=\"contestnotstarted\">contest is " .
				printContestStart($cdata) . "</h3>\n\n";
		}

		return;
	}

	// For computing team row, use smart trick when only a single team is requested such
	// that we don't need to compute the whole scoreboard.
	// This does not fully populate the summary, so the first correct problem per problem
	// is not computed and hence not shown in the individual team row.
	if ( count($teamids) == 1 ) {
		$teams   = getTeams(array("teams" => $teamids), true, $cdata);
		$probs   = getProblems($cdata);
		$SCORES  = initScores($teams);
		$SUMMARY = initSummary($probs);

		// Calculate rank, num points and total time from rank cache
		foreach ($teams as $teamid => $team ) {
			$totals = $DB->q("MAYBETUPLE SELECT points, totaltime
			                  FROM rankcache_jury
			                  WHERE cid = %i
			                  AND teamid = %i", $cid, $teamid);
			if ( $totals != null ) {
				$SCORES[$teamid]['num_points'] = $totals['points'];
				$SCORES[$teamid]['total_time']  = $totals['totaltime'];
			}
			if ($displayrank) $SCORES[$teamid]['rank'] = calcTeamRank($cdata, $teamid, $totals, true);
		}

		// Get values for this team about problems from scoreboard cache
		$MATRIX = array();
		$scoredata = $DB->q("SELECT * FROM scorecache_jury WHERE cid = %i AND teamid = %i", $cid,
		                    current($teamids));

		// loop all info the scoreboard cache and put it in our own datastructure
		while ( $srow = $scoredata->next() ) {

			// skip this row if the problem is not known by us
			if ( ! array_key_exists ( $srow['probid'], $probs ) ) continue;

			$penalty = calcPenaltyTime( $srow['is_correct'], $srow['submissions'] );

			// fill our matrix with the scores from the database
			$MATRIX[$srow['teamid']][$srow['probid']] = array (
				'is_correct'      => (bool) $srow['is_correct'],
				'num_submissions' => $srow['submissions'],
				'num_pending'     => $srow['pending'],
				'time'            => $srow['totaltime'],
				'penalty'         => $penalty );
		}

		// Fill in empty places in the matrix
		foreach ( array_keys($teams) as $team ) {
			foreach ( array_keys($probs) as $prob ) {
				// provide default scores when nothing submitted for this team,problem yet
				if ( ! isset($MATRIX[$team][$prob]) ) {
					$MATRIX[$team][$prob] = array(
						'is_correct'      => FALSE,
						'num_submissions' => 0,
						'num_pending'     => 0,
						'time'            => 0,
						'penalty'         => 0);
				}
			}
		}

		// Combine into data as genScoreBoard returns it
		$sdata = array( 'matrix'     => $MATRIX,
		                'scores'     => $SCORES,
		                'summary'    => $SUMMARY,
		                'teams'      => $teams,
		                'problems'   => $probs,
		                'categories' => null );
	}
	else {
		// Otherwise, calculate scoreboard as jury to display non-visible teams
		$sdata = genScoreBoard($cdata, TRUE);
	}

	// Render the row based on this info
	$myteamid = null;
	$static = FALSE;

	if ( ! IS_JURY ) echo "<div id=\"teamscoresummary\">\n";
	renderScoreBoardTable($sdata,$myteamid,$static,
	                      $teamids,$displayrank,TRUE,FALSE);
	if ( ! IS_JURY ) echo "</div>\n\n";

	return;
}

/**
 * Calculate the rank for a single team based on the cache tables
 */
function calcTeamRank($cdata, $teamid, $teamtotals, $jury = FALSE) {

	global $DB;

	if ( empty($cdata) ) return;

	$fdata = calcFreezeData($cdata);
	$cid = $cdata['cid'];

	// Use jury scoreboard when jury or final scoreboard should be displayed
	$tblname = $jury || $fdata['showfinal'] ? 'jury' : 'public';

	$points    = (isset($teamtotals['points'])    ? $teamtotals['points']    : 0);
	$totaltime = (isset($teamtotals['totaltime']) ? $teamtotals['totaltime'] : 0);

	$sortorder = $DB->q('VALUE SELECT sortorder
	                     FROM team_category
	                     LEFT JOIN team USING (categoryid)
	                     WHERE teamid = %i', $teamid);

	// Number of teams that definitely ranked higher
	$better = $DB->q("VALUE SELECT COUNT(team.teamid)
	                  FROM rankcache_$tblname AS rc
	                  LEFT JOIN team USING (teamid)
	                  LEFT JOIN team_category USING (categoryid)
	                  WHERE cid = %i AND sortorder = %i AND enabled = 1
	                  AND (points > %i OR (points = %i AND totaltime < %i))",
	                 $cid, $sortorder, $points, $points, $totaltime);
	$rank = $better + 1;

	// Resolve ties based on latest correctness points, only necessary when we actually
	// solved at least one problem, so this list should usually be short
	if ( $points > 0 ) {
		$tied = $DB->q("COLUMN SELECT team.teamid
		                FROM rankcache_$tblname AS rc
		                LEFT JOIN team USING (teamid)
		                LEFT JOIN team_category USING (categoryid)
		                WHERE cid = %i AND sortorder = %i AND enabled = 1
		                AND points = %i AND totaltime = %i",
		               $cid, $sortorder, $points, $totaltime);

		// All teams that are tied for this position, in most cases this will
		// only be the team we are finding the rank for, only retrieve rest of
		// the data when there are actual ties
		if ( count($tied) > 1 ) {
			// initialize teamdata for each team
			$teamdata = array();
			foreach ( $tied as $tiedid ) {
				$teamdata[$tiedid]['solve_times'] = array();
			}

			// Get submission times for each of the teams
			$scoredata = $DB->q("SELECT teamid, totaltime
			                     FROM scorecache_$tblname AS sc
			                     LEFT JOIN problem p USING (probid)
			                     LEFT JOIN contestproblem cp USING (probid, cid)
			                     WHERE sc.cid = %i AND is_correct = 1
			                     AND allow_submit = 1 AND teamid IN (%Ai)",
			                    $cid, $tied);
			while ( $srow = $scoredata->next() ) {
				$teamdata[$srow['teamid']]['solve_times'][] = $srow['totaltime'];
			}

			// Now check for each team if it is ranked higher than $teamid
			foreach ( $tied as $tiedid ) {
				if ( $tiedid == $teamid ) continue;
				if ( tiebreaker($teamdata[$tiedid], $teamdata[$teamid]) < 0)
					$rank++;
			}
		}
	}

	return $rank;
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
	if ( IS_JURY ) $res .= '</a>';

	return $res;
}

/**
 * Print contest start time
 */
function printContestStart($cdata)
{
	$res = "scheduled to start ";
	if ( printtime(now(),'%Y%m%d') == printtime($cdata['starttime'],'%Y%m%d') ) {
		// Today
		$res .= "at " . printtime($cdata['starttime']);
	} else {
		// Print full date
		$res .= "on " . printtime($cdata['starttime'],'%a %d %b %Y %T %Z');
	}
	return $res;
}

/**
 * Main score comparison function, called from the 'cmp' wrapper
 * below. Scores two arrays, $a and $b, based on the following
 * criteria:
 * - highest points from correct solutions;
 * - least amount of total time spent on these solutions;
 * - the tie-breaker function below
 */
function cmpscore($a, $b) {
	// more correctness points than someone else means higher rank
	if ( $a['num_points'] != $b['num_points'] ) {
		return $a['num_points'] > $b['num_points'] ? -1 : 1;
	}
	// else, less time spent means higher rank
	if ( $a['total_time'] != $b['total_time'] ) {
		return $a['total_time'] < $b['total_time'] ? -1 : 1;
	}
	// else tie-breaker rule
	return tiebreaker($a, $b);
}

/**
 * Tie-breaker comparison function, called from the 'cmpscore' function
 * above. Scores two arrays, $a and $b, based on the following criterion:
 * - fastest submission time for latest correct problem, when times
 *   are equal, compare one-to-latest, etc...
 */
function tiebreaker($a, $b) {
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
