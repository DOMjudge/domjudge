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
 * 'scorecache'. If the function is called while $jury set to true,
 * the scoreboard will always be current, regardless of the freezetime
 * setting in the contest table.
 *
 * The $filter argument may contain subarrays 'affilid', 'country',
 * 'categoryid' of values to filter on these.
 *
 * The $visible_only determines whether only publicly visible teams
 * are included, or all. Only relevant when $jury is true.
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
function genScoreBoard($cdata, $jury = false, $filter = null, $visible_only = false)
{
    global $DB;

    $cid = $cdata['cid'];

    $fdata = calcFreezeData($cdata);

    // Don't leak information before start of contest
    if (! $fdata['started'] && ! $jury) {
        return;
    }

    // get the teams, problems and categories
    $teams = getTeams($filter, $jury && !$visible_only, $cdata);
    $probs = getProblems($cdata);
    $categs = getCategories($jury && !$visible_only);

    // initialize the arrays we'll build from the data
    $MATRIX = array();
    $SUMMARY = initSummary($probs);
    $SCORES = initScores($teams);

    // The scorecache for the jury is always up to date, for public might be frozen.
    if ($jury || $fdata['showfinal']) {
        $variant = 'restricted';
    } else {
        $variant = 'public';
    }

    // Get all stuff from the cached table from this contest
    $query = "SELECT points,
              teamid, probid,
              submissions_$variant AS submissions,
              pending_$variant AS pending,
              solvetime_$variant AS solvetime,
              is_correct_$variant AS is_correct
              FROM scorecache JOIN contestproblem USING(probid,cid) WHERE cid = %i";
    $scoredata = $DB->q($query, $cid);

    // loop all info the scoreboard cache and put it in our own datastructure
    while ($srow = $scoredata->next()) {

        // skip this row if the team or problem is not known by us
        if (! array_key_exists($srow['teamid'], $teams) ||
            ! array_key_exists($srow['probid'], $probs)) {
            continue;
        }

        $penalty = calcPenaltyTime($srow['is_correct'], $srow['submissions']);

        // fill our matrix with the scores from the database
        $MATRIX[$srow['teamid']][$srow['probid']] = array(
            'is_correct'      => (bool) $srow['is_correct'],
            'num_submissions' => $srow['submissions'],
            'num_pending'     => $srow['pending'],
            'time'            => $srow['solvetime'],
            'penalty'         => $penalty );

        // calculate totals for this team
        if ($srow['is_correct']) {
            $SCORES[$srow['teamid']]['num_points'] += $srow['points'];
            $SCORES[$srow['teamid']]['solve_times'][] = scoretime($srow['solvetime']);
            $SCORES[$srow['teamid']]['total_time'] += scoretime($srow['solvetime']) + $penalty;
        }
    }

    // sort the array using our custom comparison function
    uasort($SCORES, 'cmp');

    // loop over all teams to calculate ranks and totals
    $prevsortorder = -1;
    foreach ($SCORES as $team => $totals) {

        // rank, team name, total correct, total time
        if ($totals['sortorder'] != $prevsortorder) {
            $prevsortorder = $totals['sortorder'];
            $rank = 0; // reset team position on switch to different category
            $prevteam = null;
        }
        $rank++;
        // Use previous' team rank when scores are equal
        if (isset($prevteam) && cmpscore($SCORES[$prevteam], $totals)==0) {
            $SCORES[$team]['rank'] = $SCORES[$prevteam]['rank'];
        } else {
            $SCORES[$team]['rank'] = $rank;
        }
        $prevteam = $team;

        // keep summary statistics for the bottom row of our table
        // The num_points summary is useful only if they're all 1-point problems.
        $SUMMARY['num_points'] += $totals['num_points'];
        if (! empty($teams[$team]['affilid'])) {
            @$SUMMARY['affils'][$totals['affilid']]++;
        }
        if (! empty($teams[$team]['country'])) {
            @$SUMMARY['countries'][$totals['country']]++;
        }

        // for each problem
        foreach (array_keys($probs) as $prob) {

            // provide default scores when nothing submitted for this team,problem yet
            if (! isset($MATRIX[$team][$prob])) {
                $MATRIX[$team][$prob] = array('num_submissions' => 0, 'num_pending' => 0,
                                              'is_correct' => false, 'time' => 0, 'penalty' => 0);
            }
            $pdata = $MATRIX[$team][$prob];
            $psum = &$SUMMARY['problems'][$prob];

            // update summary data for the bottom row
            @$psum['num_submissions'] += $pdata['num_submissions'];
            @$psum['num_pending'] += $pdata['num_pending'];
            @$psum['num_correct'] += ($pdata['is_correct'] ? 1 : 0);

            if ($pdata['is_correct']) {
                // store per sortorder the first solve time
                if (!isset($psum['best_time_sort'][$totals['sortorder']]) ||
                    $pdata['time']<$psum['best_time_sort'][$totals['sortorder']]) {
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
function getProblems($cdata)
{
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
function getTeams($filter, $jury, $cdata)
{
    global $DB;

    return $DB->q('KEYTABLE SELECT team.teamid AS ARRAYKEY, team.teamid, team.externalid,
                   team.name, team.categoryid, team.affilid, penalty, sortorder,
                   country, color, team_affiliation.name AS affilname,
               team_affiliation.externalid AS affilid_external
                   FROM team
                   INNER JOIN contest ON (contest.cid = %i)
                   LEFT JOIN contestteam ct USING (teamid, cid)
                   LEFT JOIN team_category USING (categoryid)
                   LEFT JOIN team_affiliation USING (affilid)
                   WHERE team.enabled = 1 AND (ct.teamid IS NOT NULL OR contest.public = 1)' .
                  ($jury ? '' : ' AND visible = 1') .
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
function getCategories($jury)
{
    global $DB;

    return $DB->q('KEYTABLE SELECT categoryid AS ARRAYKEY,
                   categoryid, name, color FROM team_category ' .
                  ($jury ? '' : 'WHERE visible = 1 ') .
                  'ORDER BY sortorder,name,categoryid');
}

/**
 * Helper function for genScoreBoard.
 *
 * Initialize SCORES table contains the totals for each team which are
 * used for determining the ranking.
 */
function initScores($teams)
{
    $SCORES = array();
    foreach ($teams as $teamid => $team) {
        $SCORES[$teamid]['num_points']       = 0;
        $SCORES[$teamid]['total_time']       = $team['penalty'];
        $SCORES[$teamid]['solve_times']      = array();
        $SCORES[$teamid]['rank']             = 0;
        $SCORES[$teamid]['teamname']         = $team['name'];
        $SCORES[$teamid]['categoryid']       = $team['categoryid'];
        $SCORES[$teamid]['sortorder']        = $team['sortorder'];
        $SCORES[$teamid]['affilid']          = $team['affilid'];
        $SCORES[$teamid]['affilid_external'] = $team['affilid_external'];
        $SCORES[$teamid]['country']          = $team['country'];
    }
    return $SCORES;
}

/**
 * Helper function for genScoreBoard.
 *
 * Initialize SUMMARY table.
 */
function initSummary($probs)
{
    $SUMMARY = array(
        'num_points' => 0,
        'affils'      => array(),
        'countries'   => array(),
        'problems'    => array()
    );

    // initialize all problems with data
    foreach (array_keys($probs) as $prob) {
        if (!isset($SUMMARY['problems'][$prob])) {
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
 * 'scorecache'. $myteamid can be passed to highlight a
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
function renderScoreBoardTable(
    $sdata,
    $myteamid = null,
    $static = false,
    $limitteams = null,
    $displayrank = true,
    $center = true,
    $showlegends = true
) {
    // 'unpack' the scoreboard data:
    $scores  = $sdata['scores'];
    $matrix  = $sdata['matrix'];
    $summary = $sdata['summary'];
    $teams   = $sdata['teams'];
    $probs   = $sdata['problems'];
    $categs  = $sdata['categories'];
    unset($sdata);

    // configuration
    $SHOW_FLAGS             = dbconfig_get('show_flags', 1);
    $SHOW_AFFILIATION_LOGOS = dbconfig_get('show_affiliation_logos', 0);
    $SHOW_AFFILIATIONS      = dbconfig_get('show_affiliations', 1);
    $SHOW_PENDING           = dbconfig_get('show_pending', 0);

    // Do not show points if they are all 1
    $showpoints = false;
    foreach ($probs as $pr) {
        if ($pr['points'] != 1) {
            $showpoints = true;
            break;
        }
    }
    echo '<table class="scoreboard' . (IS_JURY ? ' scoreboard_jury' : '') . ($center ? ' center' : '') . "\">\n";

    // output table column groups (for the styles)
    echo '<colgroup><col id="scorerank" />' .
        ($SHOW_FLAGS ? '<col id="scoreflags" />' : '') .
        ($SHOW_AFFILIATION_LOGOS ? '<col id="scorelogos" />' : '') .
        '<col id="scoreteamname" /></colgroup><colgroup><col id="scoresolv" />' .
        "<col id=\"scoretotal\" /></colgroup>\n<colgroup>" .
        (IS_JURY || dbconfig_get('show_teams_submissions', 1) ?
          str_repeat('<col class="scoreprob" />', count($probs)) : '') .
        "</colgroup>\n";

    // column headers
    $team_colspan = 1;
    if ($SHOW_FLAGS) {
        $team_colspan++;
    }
    if ($SHOW_AFFILIATION_LOGOS) {
        $team_colspan++;
    }
    echo "<thead>\n";
    echo '<tr class="scoreheader">' .
        '<th title="rank" scope="col">' . jurylink(null, 'rank') . '</th>' .
        '<th title="team name" scope="col"' .
        ($team_colspan > 1 ? ' colspan="' . $team_colspan . '"' : '') .
        '>' . jurylink(null, 'team') . '</th>' .
        '<th title="# solved / penalty time" colspan="2" scope="col">' .
        jurylink(null, 'score') . '</th>' . "\n";
    // Display the per-problem column headers if the display is
    // for the jury or if the per-problem information is being
    // displayed to the contestants and the public.
    if (IS_JURY || dbconfig_get('show_teams_submissions', 1)) {
        foreach ($probs as $pr) {
            echo '<th title="problem \'' . specialchars($pr['name']) . '\'" scope="col">';
            $str = specialchars($pr['shortname']) .
                   (!empty($pr['color']) ? ' <div class="circle" style="background: ' .
                   specialchars($pr['color']) . ';"></div>' : '') ;

            if (!$static && (IS_JURY || $pr['hastext']>0)) {
                echo '<a href="problem.php?id=' . urlencode($pr['probid']) .
                     '">' . $str . '</a>';
            } else {
                echo '<a>' . $str . '</a>';
            }
            if ($showpoints) {
                $points = $pr['points'];
                $pts = ($points == 1 ? '1 point' : "$points points");
                echo "<span class='problempoints'>[$pts]</span>";
            }
            echo '</th>';
        }
    }
    echo "</tr>\n</thead>\n\n<tbody>\n";

    // print the main scoreboard rows
    $prevsortorder = -1;
    $usedCategories = array();
    $bestInCat = array();
    foreach ($scores as $team => $totals) {
        // skip if we have limitteams and the team is not listed
        if (!empty($limitteams) && !in_array($team, $limitteams)) {
            continue;
        }
        if (isset($teams[$team]['categoryid'])) {
            $categoryId = $teams[$team]['categoryid'];
            $usedCategories[$categoryId] = true;
        }
    }
    foreach ($scores as $team => $totals) {
        // skip if we have limitteams and the team is not listed
        if (!empty($limitteams) && !in_array($team, $limitteams)) {
            continue;
        }

        $region_leader = '';
        if (count($usedCategories) > 1 && isset($teams[$team]['categoryid'])) {
            $categoryId = $teams[$team]['categoryid'];
            if ($totals['num_points'] &&
                (!isset($bestInCat[$categoryId]) || $scores[$bestInCat[$categoryId]]['rank'] == $totals['rank'])) {
                $region_leader = '<span class="badge badge-warning" style="margin-right: 2em; font-weight: normal;">' .
                    $categs[$categoryId]['name'] . '</span>';
                $bestInCat[$categoryId] = $team;
            }
        }

        // rank, team name, total points, total time
        echo '<tr';
        $classes = array();
        if ($totals['sortorder'] != $prevsortorder) {
            $classes[] = "sortorderswitch";
            $prevsortorder = $totals['sortorder'];
            $prevteam = null;
        }
        // check whether this is us, otherwise use category colour
        if (@$myteamid == $team) {
            $classes[] = "scorethisisme";
            unset($color);
        } else {
            $color = $teams[$team]['color'];
        }
        if (count($classes)>0) {
            echo ' class="' . implode(' ', $classes) . '"';
        }
        echo ' id="team:' . $teams[$team]['teamid'] . '"';
        echo '><td class="scorepl">';
        // Only print rank when score is different from the previous team
        if (! $displayrank) {
            echo jurylink(null, '?');
        } elseif (!isset($prevteam) || $scores[$prevteam]['rank']!=$totals['rank']) {
            echo jurylink(null, $totals['rank']);
        } else {
            echo jurylink(null, '');
        }
        $prevteam = $team;
        echo '</td>';
        if ($SHOW_FLAGS) {
            echo '<td class="scoreaf">';
            if (isset($teams[$team]['affilid'])) {
                if (IS_JURY) {
                    echo '<a href="team_affiliation.php?id=' .
                        urlencode($teams[$team]['affilid']) . '">';
                }
                if (isset($teams[$team]['country'])) {
                    $countryflag = 'images/countries/' .
                        urlencode($teams[$team]['country']) . '.png';
                    echo ' ';
                    if (is_readable(WEBAPPDIR.'/web/'.$countryflag)) {
                        echo '<img src="../' . $countryflag . '"' .
                            ' alt="'   . specialchars($teams[$team]['country']) . '"' .
                            ' title="' . specialchars($teams[$team]['country']) . '" />';
                    } else {
                        echo specialchars($teams[$team]['country']);
                    }
                }
                if (IS_JURY) {
                    echo '</a>';
                }
            }
            echo '</td>';
        }
        if ($SHOW_AFFILIATION_LOGOS) {
            echo '<td class="scoreaf">';
            if (isset($teams[$team]['affilid'])) {
                $affilid = $teams[$team]['affilid'];
                if (isset($teams[$team]['affilid_external'])) {
                    // prefer external affiliation id over internal
                    $affilid = $teams[$team]['affilid_external'];
                }
                if (IS_JURY) {
                    echo '<a href="team_affiliation.php?id=' .
                        urlencode($teams[$team]['affilid']) . '">';
                }
                $affillogo = 'images/affiliations/' .  urlencode($affilid) . '.png';
                echo ' ';
                if (is_readable(WEBAPPDIR.'/web/'.$affillogo)) {
                    echo '<img src="../' . $affillogo . '"' .
                        ' alt="'   . specialchars($teams[$team]['affilname']) . '"' .
                        ' title="' . specialchars($teams[$team]['affilname']) . '" />';
                } else {
                    echo specialchars($affilid);
                }
                if (IS_JURY) {
                    echo '</a>';
                }
            }
            echo '</td>';
        }
        $affilname = '';
        if ($SHOW_AFFILIATIONS && isset($teams[$team]['affilid'])) {
            $affilname = specialchars($teams[$team]['affilname']);
        }
        echo
            '<td class="scoretn"' .
            (!empty($color) ? ' style="background: ' . $color . ';"' : '') .
            (IS_JURY ? ' title="' . specialchars($team) . '"' : '') . '>' .
            ($static ? '' : '<a href="team.php?id=' . urlencode($team) . '">') .
            $region_leader .
            specialchars($teams[$team]['name']) .
            ($SHOW_AFFILIATIONS ? '<br /><span class="univ">' . $affilname .
             '</span>' : '') .
            ($static ? '' : '</a>') .
            '</td>';
        $totalTime = $totals['total_time'];
        if (dbconfig_get('score_in_seconds', 0)) {
            $totalTime = printtimerel($totalTime);
        }
        echo
            '<td class="scorenc">' . jurylink(null, $totals['num_points']) . '</td>' .
            '<td class="scorett">' . jurylink(null, $totalTime) . '</td>';

        // For each problem, display specific information if the
        // display is for the jury or if the per-problem information
        // is to be displayed to contestants and the public.
        if (IS_JURY || dbconfig_get('show_teams_submissions', 1)) {
            foreach (array_keys($probs) as $prob) {

                // CSS class for correct/incorrect/neutral results
                $score_css_class = 'score_neutral';
                if ($matrix[$team][$prob]['is_correct']) {
                    $score_css_class = 'score_correct';
                    if (first_solved(
                        $matrix[$team][$prob]['time'],
                        @$summary['problems'][$prob]['best_time_sort'][$totals['sortorder']]
                    )) {
                        $score_css_class .= ' score_first';
                    }
                } elseif ($matrix[$team][$prob]['num_pending'] > 0 && $SHOW_PENDING) {
                    $score_css_class = 'score_pending';
                } elseif ($matrix[$team][$prob]['num_submissions'] > 0) {
                    $score_css_class = 'score_incorrect';
                }

                // number of submissions for this problem
                $number_of_subs = $matrix[$team][$prob]['num_submissions'];
                // add pending submissions
                if ($matrix[$team][$prob]['num_pending'] > 0 && $SHOW_PENDING) {
                    $number_of_subs .= ' + ' . $matrix[$team][$prob]['num_pending'];
                }


                // If correct, print time scored. The format will vary
                // depending on the scoreboard resolution setting.
                $time = '&nbsp;';
                if ($matrix[$team][$prob]['is_correct']) {
                    if (dbconfig_get('score_in_seconds', 0)) {
                        $time = printtimerel(scoretime($matrix[$team][$prob]['time']));
                        // Display penalty time.
                        if ($matrix[$team][$prob]['num_submissions'] > 1) {
                            $time .= ' + ' . printtimerel(calcPenaltyTime(true, $matrix[$team][$prob]['num_submissions']));
                        }
                    } else {
                        $time = scoretime($matrix[$team][$prob]['time']);
                    }
                }

                echo '<td class="score_cell">';
                // Only add data if there's anything interesting to display
                if ($number_of_subs != '0') {
                    $tries = $number_of_subs . ($number_of_subs == "1" ? " try" : " tries");
                    $div = '<div class="' . $score_css_class . '">' . $time
                        . '<span>' . $tries . '</span>' . '</div>';
                    $url = 'team.php?id=' . urlencode($team) . '&amp;restrict=probid:' . urlencode($prob);
                    echo jurylink($url, $div);
                }
                echo '</td>';
            }
        }
        echo "</tr>\n";
    }
    echo "</tbody>\n\n";

    if (empty($limitteams)) {
        // print a summaryline. Exclude the "total solved" cell if using
        // perproblem points as it's actually total points and not useful
        if (!$showpoints) {
            $totalCell = '<td title="total solved" class="scorenc">' .
                         jurylink(null, $summary['num_points'])  . '</td>';
        } else {
            $totalCell = '<td class="scorenc" title=" "></td>';  // Empty
        }
        // Print the summary line details only if the display
        // is for the jury or the per-problem information is
        // being shown to contestants and the public.
        if (IS_JURY || dbconfig_get('show_teams_submissions', 1)) {
            echo '<tbody><tr style="border-top: 2px solid black;">' .
                 '<td id="scoresummary" title="Summary" colspan=3>Summary</td>' .
                 $totalCell . '<td title=" "></td>';

            foreach (array_keys($probs) as $prob) {
                $numAccepted = '<span class="octicon octicon-thumbsup"> </span>' .
                    '<span style="font-size:90%;" title="number of accepted submissions"> ' .
                    $summary['problems'][$prob]['num_correct'] .
                    '</span>';
                $numRejected = '<span class="octicon octicon-thumbsdown"> </span>' .
                    '<span style="font-size:90%;" title="number of rejected submissions"> ' .
                    ($summary['problems'][$prob]['num_submissions'] - $summary['problems'][$prob]['num_correct']) .
                    '</span>';
                $numPending = '<span class="octicon octicon-question"> </span>' .
                    '<span style="font-size:90%;" title="number of pending submissions"> ' .
                    $summary['problems'][$prob]['num_pending'] .
                    '</span>';
                $best = @$summary['problems'][$prob]['best_time_sort'][0];
                $best = empty($best) ? 'n/a' : ((int)($best/60)) . 'min';
                $best = '<span class="octicon octicon-clock"> </span>' .
                    '<span style="font-size:90%;" title="first solved"> ' . $best . '</span>';

                $str =
                    $numAccepted . '<br />' .
                    $numRejected . '<br />' .
                    $numPending . '<br />' .
                    $best;
                echo '<td style="text-align: left;">' .
                    jurylink('problem.php?id=' . urlencode($prob), $str) .
                    '</td>';
            }
            echo "</tr>\n</tbody>\n";
        }
    }

    echo "</table>\n\n";

    if ($showlegends) {
        echo "<p><br /><br /></p>\n";

        // only print legend when there's more than one category
        if (empty($limitteams) && count($usedCategories) > 1) {
            $catColors = array();
            foreach ($categs as $cat) {
                if (!empty($cat['color'])) {
                    $catColors[$cat['color']] = true;
                }
            }
            if (count($catColors)) {
                echo "<table id=\"categ_legend\" class=\"scoreboard scorelegend" .
                    (IS_JURY ? ' scoreboard_jury' : '') . "\">\n" .
                    "<thead><tr><th scope=\"col\">" .
                    jurylink('team_categories.php', 'Categories') .
                    "</th></tr></thead>\n<tbody>\n";
                foreach ($categs as $cat) {
                    if (!isset($usedCategories[$cat['categoryid']])) {
                        continue;
                    }
                    echo '<tr' . (!empty($cat['color']) ? ' style="background: ' .
                              $cat['color'] . ';"' : '') . '>' .
                        '<td>' .
                        jurylink(
                            'team_category.php?id=' . urlencode($cat['categoryid']),
                             specialchars($cat['name'])
                        ) . "</td></tr>\n";
                }
                echo "</tbody>\n</table>\n&nbsp;\n";
            }
        }

        // Print legend of scorecell colors if per-problem
        // information is being shown.
        if (IS_JURY || dbconfig_get('show_teams_submissions', 1)) {
            $cellcolors = array('first'     => 'Solved first',
                                'correct'   => 'Solved',
                                'incorrect' => 'Tried, incorrect',
                                'pending'   => 'Tried, pending',
                                'neutral'   => 'Untried');

            echo "<table id=\"cell_legend\" class=\"scoreboard scorelegend" .
                 (IS_JURY ? ' scoreboard_jury' : '') . "\">\n" .
                 "<thead><tr><th scope=\"col\">" . jurylink(null, 'Cell colours') .
                 "</th></tr></thead>\n<tbody>\n";
            foreach ($cellcolors as $color => $desc) {
                if ($color=='pending' && !dbconfig_get('show_pending', 0)) {
                    continue;
                }
                echo '<tr class="score_' . $color . '">' .
                     '<td>' . jurylink(null, $desc) . "</td></tr>\n";
            }
            echo "</tbody>\n</table>\n\n";
        }
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
function putScoreBoard($cdata, $myteamid = null, $static = false, $filter = false, $sdata = null)
{
    global $DB, $pagename;

    if (empty($cdata)) {
        echo "<p class=\"nodata\">No active contest</p>\n";
        return;
    }

    $fdata = calcFreezeData($cdata);
    if ($sdata === null) {
        $sdata = genScoreBoard($cdata, IS_JURY, $filter);
    }

    $moreinfo = '';
    $warning = '';
    if ($fdata['showfinal']) {
        if (empty($cdata['finalizetime'])) {
            $moreinfo = "preliminary results - not final";
        } else {
            $moreinfo = "final standings";
        }
    } elseif ($fdata['stopped']) {
        $moreinfo = "contest over, waiting for results";
    } elseif (! $fdata['started']) {
        $moreinfo = printContestStart($cdata);
    } else {
        $moreinfo = "starts: " . printtime($cdata['starttime']) .
                " - ends: " . printtime($cdata['endtime']);
    }

    if (IS_JURY) {
        echo '<div style="margin-top: 4em;"></div>';
    }
    echo '<div class="card">';
    // page heading with contestname and start/endtimes
    echo '<div class="card-header" style="font-family: Roboto, sans-serif; display: flex;">';
    echo '<span style="font-weight: bold;">' . specialchars($cdata['name']) . '</span>'
        . ' <span style="color: DimGray; margin-left: auto;">' . $moreinfo . '</span>';
    echo '</div>';
    if ($static && $fdata['started'] && !$fdata['stopped']) {
        putProgressBar();
    }
    echo '</div>'; // card

    // Stop here (do not leak problem number, descriptions etc).
    // Display list of teams by group. This is targeted for World Finals.
    if (! $fdata['started'] && ! IS_JURY) {
        $affils = $DB->q('TABLE SELECT ta.externalid, ta.name AS taname, cat.name AS catname, categoryid
                          FROM team_affiliation ta
                          LEFT JOIN team t USING (affilid)
                          INNER JOIN contest c ON (c.cid = %i)
                          LEFT JOIN contestteam ct ON (ct.teamid = t.teamid AND ct.cid = c.cid)
                          LEFT JOIN team_category cat USING (categoryid)
                          WHERE c.cid = %i AND (c.public = 1 OR ct.teamid IS NOT NULL)
                          AND cat.visible = 1
                          ORDER BY catname, taname',
                         $cdata['cid'], $cdata['cid']);

        $lastCat = null;
        $lastAffil = null;
        $numCats = 0;
        foreach ($affils as $affil) {
            if ($lastAffil == $affil['taname']) {
                continue;
            }
            if ($affil['categoryid'] != $lastCat) {
                if ($lastCat != null) {
                    echo '</ul>';
                    echo '</div>';
                    echo '</div>';
                }
                if ($numCats % 3 == 0) {
                    // close previous deck, but not for the very first one
                    if ( $numCats != 0 ) {
                        echo '</div>';
                    }
                    echo '<br /><br />';
                    echo '<div class="card-deck">';
                }
                $numCats++;
                $lastCat = $affil['categoryid'];
                echo '<div class="card" style="font-family: Roboto, sans-serif;">';
                echo '<div class="card-header">' . $affil['catname'] . '</div>';
                echo '<div class="card-body">';
                echo '<ul class="list-group list-group-flush">';
            }
            $affillogo = 'images/affiliations/' .  urlencode($affil['externalid']) . '.png';
            $logoHTML = '';
            if (is_readable(WEBAPPDIR.'/web/'.$affillogo)) {
                $logoHTML = '<img src="../' . $affillogo . '" style="padding-right: 10px;" />';
            }
            print '<li class="list-group-item">' . $logoHTML . $affil['taname'] . '</li>';
            $lastAffil = $affil['taname'];
        }
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        while ($numCats % 3 != 0) {
            $numCats++;
            echo '<div class="card" style="border: none;"></div>';
        }
        echo '</div>';
        return;
    }

    if ($fdata['showfrozen']) {
        $timerem = floor(($cdata['endtime'] - $cdata['freezetime'])/60);
        if (IS_JURY) {
            $warning = '<a href="../public/">The public scoreboard</a> ' .
                "was frozen with $timerem minutes remaining";
        } else {
            $warning = "The scoreboard was frozen with $timerem minutes " .
                "remaining - solutions submitted in the last $timerem " .
                "minutes of the contest are still shown as pending.";
        }
        echo '<div class="alert alert-warning" role="alert" style="font-size: 80%;">' .
            $warning . '</div>';
    }

    // The static scoreboard does not support filtering
    if ($filter!==false && $static!==true) {
        $SHOW_FLAGS             = dbconfig_get('show_flags', 1);
        $SHOW_AFFILIATIONS      = dbconfig_get('show_affiliations', 1);

        $categids = $DB->q('KEYVALUETABLE SELECT categoryid, name FROM team_category ' .
                           (IS_JURY ? '' : 'WHERE visible = 1 '));
        // show only affilids/countries with visible teams
        if (empty($categids) || !$SHOW_AFFILIATIONS) {
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
        foreach ($affils as $id => $affil) {
            $affilids[$id] = $affil['name'];
            if ($SHOW_FLAGS && isset($affil['country'])) {
                $countries[] = $affil['country'];
            }
        }

        $countries = array_unique($countries);
        sort($countries);
        asort($affilids, SORT_FLAG_CASE);

        $filteron = array();
        $filtertext = "";
        foreach (array('affilid' => 'affiliation', 'country' => 'country', 'categoryid' => 'category') as $type => $text) {
            if (isset($filter[$type])) {
                $filteron[] = $text;
            }
        }
        if (sizeof($filteron) > 0) {
            $filtertext = "(filtered on " . implode(", ", $filteron) . ")";
        }

        require_once(LIBWWWDIR . '/forms.php'); ?>

<table class="scorefilter">
<tr>
<td><a class="scorecollapse" href="javascript:collapse('filter')"><img src="../images/filter.png" alt="filter&hellip;" title="filter&hellip;" class="picto" /></a></td>
<td><?= $filtertext ?></td>
<td><div id="detailfilter">
<?php

        echo addForm($pagename, 'get') .
            (count($affilids) > 1 ? addSelect('affilid[]', $affilids, @$filter['affilid'], true, 8) : "") .
            (count($countries)> 1 ? addSelect('country[]', $countries, @$filter['country'], false, 8) : "") .
            (count($categids) > 1 ? addSelect('categoryid[]', $categids, @$filter['categoryid'], true, 8) : "") .
            addSubmit('filter', 'filter') . addSubmit('clear', 'clear') .
            addEndForm(); ?>
</div></td></tr>
</table>
<script type="text/javascript">
<!--
collapse("filter");
// -->
</script>
        <?php
    } else {
        echo '<br />';
    }

    renderScoreBoardTable($sdata, $myteamid, $static, null, true, !IS_JURY);

    // last modified date, now if we are the jury, else include the
    // freeze time
    $lastupdate = printtime(now(), '%a %d %b %Y %T %Z');
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
    if (isset($_COOKIE['domjudge_scorefilter'])) {
        $scorefilter = dj_json_decode($_COOKIE['domjudge_scorefilter']);
    }

    if (isset($_REQUEST['clear'])) {
        $scorefilter = array();
    }

    if (isset($_REQUEST['filter'])) {
        $scorefilter = array();
        foreach (array('affilid', 'country', 'categoryid') as $type) {
            if (!empty($_REQUEST[$type])) {
                $scorefilter[$type] = $_REQUEST[$type];
            }
        }
    }

    dj_setcookie('domjudge_scorefilter', dj_json_encode($scorefilter));

    return $scorefilter;
}

/**
 * Output a team row from the scoreboard based on the cached data in
 * table 'scoreboard'.
 */
function putTeamRow($cdata, $teamids)
{
    global $DB;

    if (empty($cdata)) {
        return;
    }

    $fdata = calcFreezeData($cdata);
    $displayrank = IS_JURY || !$fdata['showfrozen'];
    $cid = $cdata['cid'];

    if (! $fdata['started']) {
        if (! IS_JURY) {
            global $teamdata;
            echo "<h1 id=\"teamwelcome\">welcome team <span id=\"teamwelcometeam\">" .
                specialchars($teamdata['name']) . "</span>!</h1>\n\n";
            echo "<h2 id=\"contestnotstarted\">contest " .
                printContestStart($cdata) . "</h2>\n\n";
        }

        return;
    }

    // For computing team row, use smart trick when only a single team is requested such
    // that we don't need to compute the whole scoreboard.
    // This does not fully populate the summary, so the first correct problem per problem
    // is not computed and hence not shown in the individual team row.
    if (count($teamids) == 1) {
        $teams   = getTeams(array("teams" => $teamids), true, $cdata);
        $probs   = getProblems($cdata);
        $SCORES  = initScores($teams);
        $SUMMARY = initSummary($probs);

        // Calculate rank, num points and total time from rank cache
        foreach ($teams as $teamid => $team) {
            $totals = $DB->q("MAYBETUPLE SELECT points_restricted AS points,
                              totaltime_restricted AS totaltime
                              FROM rankcache
                              WHERE cid = %i
                              AND teamid = %i", $cid, $teamid);
            if ($totals != null) {
                $SCORES[$teamid]['num_points'] = $totals['points'];
                $SCORES[$teamid]['total_time'] = $totals['totaltime'];
            }
            if ($displayrank) {
                $SCORES[$teamid]['rank'] = calcTeamRank($cdata, $teamid, $totals, true);
            }
        }

        // Get values for this team about problems from scoreboard cache
        $MATRIX = array();
        $scoredata = $DB->q("SELECT cid, teamid, probid, submissions_restricted AS submissions,
                             pending_restricted AS pending, solvetime_restricted AS solvetime,
                             is_correct_restricted AS is_correct
                             FROM scorecache WHERE cid = %i AND teamid = %i",
                            $cid, current($teamids));

        // loop all info the scoreboard cache and put it in our own datastructure
        while ($srow = $scoredata->next()) {

            // skip this row if the problem is not known by us
            if (! array_key_exists($srow['probid'], $probs)) {
                continue;
            }

            $penalty = calcPenaltyTime($srow['is_correct'], $srow['submissions']);

            // fill our matrix with the scores from the database
            $MATRIX[$srow['teamid']][$srow['probid']] = array(
                'is_correct'      => (bool) $srow['is_correct'],
                'num_submissions' => $srow['submissions'],
                'num_pending'     => $srow['pending'],
                'time'            => $srow['solvetime'],
                'penalty'         => $penalty
            );
        }

        // Fill in empty places in the matrix
        foreach (array_keys($teams) as $team) {
            foreach (array_keys($probs) as $prob) {
                // provide default scores when nothing submitted for this team,problem yet
                if (! isset($MATRIX[$team][$prob])) {
                    $MATRIX[$team][$prob] = array(
                        'is_correct'      => false,
                        'num_submissions' => 0,
                        'num_pending'     => 0,
                        'time'            => 0,
                        'penalty'         => 0
                    );
                }
            }
        }

        // Combine into data as genScoreBoard returns it
        $sdata = array(
            'matrix'     => $MATRIX,
            'scores'     => $SCORES,
            'summary'    => $SUMMARY,
            'teams'      => $teams,
            'problems'   => $probs,
            'categories' => null
        );
    } else {
        // Otherwise, calculate scoreboard as jury to display non-visible teams
        $sdata = genScoreBoard($cdata, true);
    }

    // Render the row based on this info
    $myteamid = null;
    $static = false;

    if (! IS_JURY) {
        echo "<div id=\"teamscoresummary\">\n";
    }
    renderScoreBoardTable(
        $sdata,
        $myteamid,
        $static,
        $teamids,
        $displayrank,
        true,
        false
    );
    if (! IS_JURY) {
        echo "</div>\n\n";
    }

    return;
}

/**
 * Calculate the rank for a single team based on the cache tables
 */
function calcTeamRank($cdata, $teamid, $teamtotals, $jury = false)
{
    global $DB;

    if (empty($cdata)) {
        return;
    }

    $fdata = calcFreezeData($cdata);
    $cid = $cdata['cid'];

    // Use jury scoreboard when jury or final scoreboard should be displayed
    $variant = $jury || $fdata['showfinal'] ? 'restricted' : 'public';

    $points    = (isset($teamtotals['points'])    ? $teamtotals['points']    : 0);
    $totaltime = (isset($teamtotals['totaltime']) ? $teamtotals['totaltime'] : 0);

    $sortorder = $DB->q('VALUE SELECT sortorder
                         FROM team_category
                         LEFT JOIN team USING (categoryid)
                         WHERE teamid = %i', $teamid);

    // Number of teams that definitely ranked higher
    $better = $DB->q("VALUE SELECT COUNT(team.teamid)
                      FROM rankcache AS rc
                      LEFT JOIN team USING (teamid)
                      LEFT JOIN team_category USING (categoryid)
                      WHERE cid = %i AND sortorder = %i AND enabled = 1
                      AND (points_$variant > %i OR
                      (points_$variant = %i AND totaltime_$variant < %i))",
                     $cid, $sortorder, $points, $points, $totaltime);
    $rank = $better + 1;

    // Resolve ties based on latest correctness points, only necessary when we actually
    // solved at least one problem, so this list should usually be short
    if ($points > 0) {
        $tied = $DB->q("COLUMN SELECT team.teamid
                        FROM rankcache AS rc
                        LEFT JOIN team USING (teamid)
                        LEFT JOIN team_category USING (categoryid)
                        WHERE cid = %i AND sortorder = %i AND enabled = 1
                        AND points_$variant = %i AND totaltime_$variant = %i",
                       $cid, $sortorder, $points, $totaltime);

        // All teams that are tied for this position, in most cases this will
        // only be the team we are finding the rank for, only retrieve rest of
        // the data when there are actual ties
        if (count($tied) > 1) {
            // initialize teamdata for each team
            $teamdata = array();
            foreach ($tied as $tiedid) {
                $teamdata[$tiedid]['solve_times'] = array();
            }

            // Get submission times for each of the teams
            $scoredata = $DB->q("SELECT teamid, solvetime_$variant AS solvetime
                                 FROM scorecache AS sc
                                 LEFT JOIN problem p USING (probid)
                                 LEFT JOIN contestproblem cp USING (probid, cid)
                                 WHERE sc.cid = %i AND is_correct_$variant = 1
                                 AND allow_submit = 1 AND teamid IN (%Ai)",
                                $cid, $tied);
            while ($srow = $scoredata->next()) {
                $teamdata[$srow['teamid']]['solve_times'][] = scoretime($srow['solvetime']);
            }

            // Now check for each team if it is ranked higher than $teamid
            foreach ($tied as $tiedid) {
                if ($tiedid == $teamid) {
                    continue;
                }
                if (tiebreaker($teamdata[$tiedid], $teamdata[$teamid]) < 0) {
                    $rank++;
                }
            }
        }
    }

    return $rank;
}

/**
 * Generate scoreboard links for jury only.
 */
function jurylink($target, $content)
{
    $res = "";
    if (IS_JURY) {
        $res .= '<a' . (isset($target) ? ' href="' . $target . '"' : '') . '>';
    }
    $res .= $content;
    if (IS_JURY) {
        $res .= '</a>';
    }

    return $res;
}

/**
 * Print contest start time
 */
function printContestStart($cdata)
{
    $res = "scheduled to start ";
    if (!$cdata['starttime_enabled']) {
        $res = "start delayed, was scheduled ";
    }
    if (printtime(now(), '%Y%m%d') == printtime($cdata['starttime'], '%Y%m%d')) {
        // Today
        $res .= "at " . printtime($cdata['starttime']);
    } else {
        // Print full date
        $res .= "on " . printtime($cdata['starttime'], '%a %d %b %Y %T %Z');
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
function cmpscore($a, $b)
{
    // more correctness points than someone else means higher rank
    if ($a['num_points'] != $b['num_points']) {
        return $a['num_points'] > $b['num_points'] ? -1 : 1;
    }
    // else, less time spent means higher rank
    if ($a['total_time'] != $b['total_time']) {
        return $a['total_time'] < $b['total_time'] ? -1 : 1;
    }
    // else tie-breaker rule
    return tiebreaker($a, $b);
}

/**
 * Tie-breaker comparison function, called from the 'cmpscore' function
 * above. Scores two arrays, $a and $b, based on the following criterion:
 * - fastest submission time for latest correct problem
 */
function tiebreaker($a, $b)
{
    $atimes = $a['solve_times'];
    $btimes = $b['solve_times'];
    rsort($atimes);
    rsort($btimes);
    if (isset($atimes[0])) {
        if ($atimes[0] != $btimes[0]) {
            return $atimes[0] < $btimes[0] ? -1 : 1;
        }
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
function cmp($a, $b)
{
    // first order by our predefined sortorder based on category
    if ($a['sortorder'] != $b['sortorder']) {
        return $a['sortorder'] < $b['sortorder'] ? -1 : 1;
    }
    // then compare scores
    $scorecmp = cmpscore($a, $b);
    if ($scorecmp != 0) {
        return $scorecmp;
    }
    // else, order by teamname alphabetically
    if ($a['teamname'] != $b['teamname']) {
        return strcasecmp($a['teamname'], $b['teamname']);
    }
    // undecided, should never happen in practice
    return 0;
}
