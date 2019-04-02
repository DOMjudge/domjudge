<?php declare(strict_types=1);

/**
 * Functions for calculating the scoreboard.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
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
 *
 * @deprecated Use ScoreboardService instead
 */
function genScoreBoard(array $cdata, bool $jury = false, $filter = null, bool $visible_only = false): array
{
    // Use symfony Scoreboard Service to get the scoreboard and transform it to the old data
    /** @var \DOMJudgeBundle\Service\ScoreboardService $G_SCOREBOARD_SERVICE */
    /** @var \DOMJudgeBundle\Service\DOMJudgeService $G_SYMFONY */
    global $G_SYMFONY, $G_SCOREBOARD_SERVICE;
    $contest      = $G_SYMFONY->getContest($cdata['cid']);
    $filterObject = new \DOMJudgeBundle\Utils\Scoreboard\Filter(
        $filter['affilid'] ?? [],
        $filter['country'] ?? [],
        $filter['categoryid'] ?? [],
        $filter['teams'] ?? []
    );
    $scoreboard   = $G_SCOREBOARD_SERVICE->getScoreboard($contest, $jury, $filterObject, $visible_only);
    if (!$scoreboard) {
        return [];
    }

    return convertScoreboard($scoreboard);
}

function convertScoreboard(\DOMJudgeBundle\Utils\Scoreboard\Scoreboard $scoreboard)
{
    // Transform scoreboard data
    $matrix = [];
    foreach ($scoreboard->getMatrix() as $teamId => $matrixItemsForTeam) {
        /** @var \DOMJudgeBundle\Utils\Scoreboard\ScoreboardMatrixItem $matrixItem */
        foreach ($matrixItemsForTeam as $problemId => $matrixItem) {
            $matrix[$teamId][$problemId] = [
                'is_correct' => $matrixItem->isCorrect(),
                'num_submissions' => (string)$matrixItem->getNumberOfSubmissions(),
                'num_pending' => (string)$matrixItem->getNumberOfPendingSubmissions(),
                'time' => (string)$matrixItem->getTime(),
                'penalty' => $matrixItem->getPenaltyTime(),
            ];
        }
    }

    $scores = array_map(function (\DOMJudgeBundle\Utils\Scoreboard\TeamScore $teamScore) {
        $team = $teamScore->getTeam();
        return [
            'num_points' => $teamScore->getNumberOfPoints(),
            'total_time' => $teamScore->getTotalTime(),
            'solve_times' => $teamScore->getSolveTimes(),
            'rank' => $teamScore->getRank(),
            'teamname' => $team->getName(),
            'categoryid' => (string)$team->getCategoryid(),
            'sortorder' => (string)$team->getCategory()->getSortorder(),
            'affilid' => $team->getAffilid() ? (string)$team->getAffilid() : null,
            'affilid_external' => $team->getAffiliation() ? $team->getAffiliation()->getExternalid() : null,
            'country' => ($team->getAffiliation() && $team->getAffiliation()->getCountry()) ? $team->getAffiliation()->getCountry() : null,
        ];
    }, $scoreboard->getScores());

    $teams = array_map(function (\DOMJudgeBundle\Entity\Team $team) {
        return [
            'teamid' => (string)$team->getTeamid(),
            'externalid' => $team->getExternalid(),
            'name' => $team->getName(),
            'categoryid' => (string)$team->getCategoryid(),
            'affilid' => $team->getAffilid() ? (string)$team->getAffilid() : null,
            'penalty' => (string)$team->getPenalty(),
            'sortorder' => (string)$team->getCategory()->getSortorder(),
            'country' => ($team->getAffiliation() && $team->getAffiliation()->getCountry()) ? $team->getAffiliation()->getCountry() : null,
            'color' => $team->getCategory()->getColor(),
            'affilname' => $team->getAffiliationName(),
            'affilid_external' => $team->getAffiliation() ? $team->getAffiliation()->getExternalid() : null,
        ];
    }, $scoreboard->getTeams());

    $summary = [
        'num_points' => $scoreboard->getSummary()->getNumberOfPoints(),
        'affils' => $scoreboard->getSummary()->getAffiliations(),
        'countries' => $scoreboard->getSummary()->getCountries(),
        'problems' => array_map(function (\DOMJudgeBundle\Utils\Scoreboard\ProblemSummary $problemSummary) {
            return [
                'num_submissions' => $problemSummary->getNumberOfSubmissions(),
                'num_pending' => $problemSummary->getNumberOfPendingSubmissions(),
                'num_correct' => $problemSummary->getNumberOfCorrectSubmissions(),
                'best_time_sort' => $problemSummary->getBestTimes(),
            ];
        }, $scoreboard->getSummary()->getProblems()),
    ];

    $problems = array_map(function (\DOMJudgeBundle\Entity\ContestProblem $contestProblem) {
        return [
            'probid' => (string)$contestProblem->getProbid(),
            'points' => (string)$contestProblem->getPoints(),
            'shortname' => $contestProblem->getShortname(),
            'name' => $contestProblem->getProblem()->getName(),
            'color' => $contestProblem->getColor(),
            'hastext' => $contestProblem->getProblem()->hasProblemtext(),
        ];
    }, $scoreboard->getProblems());

    $categories = array_map(function (\DOMJudgeBundle\Entity\TeamCategory $teamCategory) {
        return [
            'categoryid' => (string)$teamCategory->getCategoryid(),
            'name' => $teamCategory->getName(),
            'color' => $teamCategory->getColor(),
        ];
    }, $scoreboard->getCategories());

    return [
        'matrix' => $matrix,
        'scores' => $scores,
        'summary' => $summary,
        'teams' => $teams,
        'problems' => $problems,
        'categories' => $categories,
    ];
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
    array $sdata,
    $myteamid = null,
    bool $static = false,
    $limitteams = null,
    bool $displayrank = true,
    bool $center = true,
    bool $showlegends = true
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
                echo '<a href="problem.php?id=' . urlencode((string)$pr['probid']) .
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
            echo jurylink(null, (string)$totals['rank']);
        } else {
            echo jurylink(null, '');
        }
        $prevteam = $team;
        echo '</td>';
        if ($SHOW_FLAGS) {
            echo '<td class="scoreaf">';
            if (isset($teams[$team]['affilid'])) {
                if (IS_JURY) {
                    echo '<a href="affiliations/' .
                        urlencode((string)$teams[$team]['affilid']) . '">';
                }
                if (isset($teams[$team]['country'])) {
                    $countryflag = 'images/countries/' .
                        urlencode($teams[$team]['country']) . '.png';
                    echo ' ';
                    if (is_readable(WEBAPPDIR.'/web/'.$countryflag)) {
                        echo '<img src="../' . $countryflag . '" class="countryflag"' .
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
                    echo '<a href="affiliations/' .
                        urlencode((string)$teams[$team]['affilid']) . '">';
                }
                $affillogo = 'images/affiliations/' .  urlencode((string)$affilid) . '.png';
                echo ' ';
                if (is_readable(WEBAPPDIR.'/web/'.$affillogo)) {
                    echo '<img src="../' . $affillogo . '"' .
                        ' alt="'   . specialchars($teams[$team]['affilname']) . '"' .
                        ' title="' . specialchars($teams[$team]['affilname']) . '" />';
                } else {
                    echo specialchars((string)$affilid);
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
            (IS_JURY ? ' title="' . specialchars((string)$team) . '"' : '') . '>' .
            ($static ? '' : '<a href="team.php?id=' . urlencode((string)$team) . '">') .
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
            '<td class="scorenc">' . jurylink(null, (string)$totals['num_points']) . '</td>' .
            '<td class="scorett">' . jurylink(null, (string)$totalTime) . '</td>';

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
                        (float)$matrix[$team][$prob]['time'],
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
                        $time = printtimerel(scoretime((float)$matrix[$team][$prob]['time']));
                        // Display penalty time.
                        if ($matrix[$team][$prob]['num_submissions'] > 1) {
                            $time .= ' + ' . printtimerel(calcPenaltyTime(true, $matrix[$team][$prob]['num_submissions']));
                        }
                    } else {
                        $time = scoretime((float)$matrix[$team][$prob]['time']);
                    }
                }

                echo '<td class="score_cell">';
                // Only add data if there's anything interesting to display
                if ($number_of_subs != '0') {
                    $tries = $number_of_subs . ($number_of_subs == "1" ? " try" : " tries");
                    $div = '<div class="' . $score_css_class . '">' . $time
                        . '<span>' . $tries . '</span>' . '</div>';
                    $url = 'team.php?id=' . urlencode((string)$team)
                        . '&amp;restrict=probid:' . urlencode((string)$prob);
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
                jurylink(null, (string)$summary['num_points'])  . '</td>';
        } else {
            $totalCell = '<td class="scorenc" title=" "></td>';  // Empty
        }
        // Print the summary line details only if the display
        // is for the jury or the per-problem information is
        // being shown to contestants and the public.
        if (IS_JURY || dbconfig_get('show_teams_submissions', 1)) {
            $summary_colspan = 2;
            if ($SHOW_FLAGS) {
                $summary_colspan++;
            }
            if ($SHOW_AFFILIATION_LOGOS) {
                $summary_colspan++;
            }
            echo '<tbody><tr style="border-top: 2px solid black;">' .
                 '<td id="scoresummary" title="Summary" colspan="' . $summary_colspan . '">Summary</td>' .
                 $totalCell . '<td title=" "></td>';

            foreach (array_keys($probs) as $prob) {
                $numAccepted = '<i class="fas fa-thumbs-up fa-fw"> </i>' .
                    '<span style="font-size:90%;" title="number of accepted submissions"> ' .
                    $summary['problems'][$prob]['num_correct'] .
                    '</span>';
                $numRejected = '<i class="fas fa-thumbs-down fa-fw"> </i>' .
                    '<span style="font-size:90%;" title="number of rejected submissions"> ' .
                    ($summary['problems'][$prob]['num_submissions'] - $summary['problems'][$prob]['num_correct']) .
                    '</span>';
                $numPending = '<i class="fas fa-question-circle fa-fw"> </i>' .
                    '<span style="font-size:90%;" title="number of pending submissions"> ' .
                    $summary['problems'][$prob]['num_pending'] .
                    '</span>';
                $best = @$summary['problems'][$prob]['best_time_sort'][0];
                $best = empty($best) ? 'n/a' : ((int)($best/60)) . 'min';
                $best = '<i class="fas fa-clock fa-fw"> </i>' .
                    '<span style="font-size:90%;" title="first solved"> ' . $best . '</span>';

                $str =
                    $numAccepted . '<br />' .
                    $numRejected . '<br />' .
                    $numPending . '<br />' .
                    $best;
                echo '<td style="text-align: left;">' .
                    jurylink('problem.php?id=' . urlencode((string)$prob), $str) .
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
                    jurylink('categories', 'Categories') .
                    "</th></tr></thead>\n<tbody>\n";
                foreach ($categs as $cat) {
                    if (!isset($usedCategories[$cat['categoryid']])) {
                        continue;
                    }
                    echo '<tr' . (!empty($cat['color']) ? ' style="background: ' .
                              $cat['color'] . ';"' : '') . '>' .
                        '<td>' .
                        jurylink(
                            'categories/' . urlencode((string)$cat['categoryid']),
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
function putScoreBoard(array $cdata = null, $myteamid = null, bool $static = false, $filter = false, $sdata = null)
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
            $affillogo = 'images/affiliations/' .  urlencode((string)$affil['externalid']) . '.png';
            $logoHTML = '';
            if (is_readable(WEBAPPDIR.'/web/'.$affillogo)) {
                $logoHTML = '<img class="affiliation-logo" src="../' . $affillogo . '" style="margin-right: 10px;" />';
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
                              WHERE categoryid IN (%Ai) AND c.cid = %i AND
                              (c.public = 1 OR ct.teamid IS NOT NULL)
                              GROUP BY affilid',
                             (int)$cdata['cid'], array_keys($categids), (int)$cdata['cid']);
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
<script>
collapse("filter");
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
	    "using <a href=\"https://www.domjudge.org/\" target=\"_top\">DOMjudge</a></p>\n\n";

    return;
}

/**
 * Reads scoreboard filter settings from a cookie and explicit POST of
 * filter settings. Also sets the cookie, so must be called before
 * headers are sent. Returns the scoreboard filter settings array.
 */
function initScorefilter() : array
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
function putTeamRow(array $cdata, array $teamids)
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
        // Use symfony Scoreboard Service to get the scoreboard and transform it to the old data
        /** @var \DOMJudgeBundle\Service\ScoreboardService $G_SCOREBOARD_SERVICE */
        /** @var \DOMJudgeBundle\Service\DOMJudgeService $G_SYMFONY */
        global $G_SYMFONY, $G_SCOREBOARD_SERVICE;
        $contest    = $G_SYMFONY->getContest($cdata['cid']);
        $scoreboard = $G_SCOREBOARD_SERVICE->getTeamScoreboard($contest, (int)reset($teamids), true);
        if ($scoreboard === null) {
            return;
        }
        $sdata      = convertScoreboard($scoreboard);
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
 * Generate scoreboard links for jury only.
 */
function jurylink($target, string $content) : string
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
function printContestStart(array $cdata) : string
{
    $res = "scheduled to start ";
    if (!$cdata['starttime_enabled']) {
        $res = "start delayed, was scheduled ";
    }
    $res .= "on " . printtime($cdata['starttime'], '%a %d %b %Y at %T %Z');
    return $res;
}

// TODO: the below three functions are currently only here because of misc-tools/combined_scoreboard. Maybe move them for now?

/**
 * Main score comparison function, called from the 'cmp' wrapper
 * below. Scores two arrays, $a and $b, based on the following
 * criteria:
 * - highest points from correct solutions;
 * - least amount of total time spent on these solutions;
 * - the tie-breaker function below
 */
function cmpscore(array $a, array $b) : int
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
function tiebreaker(array $a, array $b) : int
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
function cmp(array $a, array $b) : int
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
        $collator = new \Collator('en');
        return $collator->compare($a['teamname'], $b['teamname']);
    }
    // undecided, should never happen in practice
    return 0;
}
