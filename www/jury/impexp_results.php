<?php
/**
 * Code to import and export HTML formats as specified by the ICPC
 * Contest Control System Standard.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');
require(LIBDIR . '/lib.impexp.php');

requireAdmin();

$categs = $DB->q('COLUMN SELECT categoryid FROM team_category WHERE visible = 1');
$sdata = genScoreBoard($cdata, true, array('categoryid' => $categs));
$teams = $sdata['teams'];
$team_names = [];
foreach ($teams as $team) {
    $team_names[$team['externalid']] = $team['name'];
}

$awarded = [];
$ranked = [];
$honorable = [];
$region_winners = [];

$useIcpcLayout = isset($_GET['mode']) && $_GET['mode'] === 'icpcsite';
$download = isset($_GET['download']);
if ($download) {
    header('Content-type: text/html');
    header('Content-disposition: attachment; filename=results.html');
}

foreach (tsv_results_get() as $row) {
    $team = $team_names[$row[0]];

    if ($row[6] !== '') {
        $region_winners[] = [
            'group' => $row[6],
            'team' => $team,
        ];
    }

    $row = [
        'team' => $team,
        'rank' => $row[1],
        'award' => $row[2],
        'solved' => $row[3],
        'total_time' => $row[4],
        'max_time' => $row[5],
    ];
    if (preg_match('/^(.*) Medal$/', $row['award'], $matches)) {
        $row['class'] = strtolower($matches[1]);
    } else {
        $row['class'] = '';
    }
    if ($row['rank'] === '') {
        $honorable[] = $row['team'];
    } elseif ($row['award'] === 'Ranked') {
        $ranked[] = $row;
    } else {
        $awarded[] = $row;
    }
}

usort($region_winners, function ($a, $b) {
    return $a['group'] <=> $b['group'];
});

$collator = new Collator('en_US');
$collator->sort($honorable);

$probs = $sdata['problems'];
$matrix = $sdata['matrix'];
$summary = $sdata['summary'];
$first_to_solve = [];
foreach ($probs as $probData) {
    $first_to_solve[$probData['probid']] = [
        'problem' => $probData['shortname'],
        'problem_name' => $probData['name'],
        'team' => null,
        'time' => null,
    ];
    foreach ($teams as $teamData) {
        if (!in_array($teamData['categoryid'], $categs)) {
            continue;
        }
        if ($matrix[$teamData['teamid']][$probData['probid']]['is_correct'] && first_solved(
            $matrix[$teamData['teamid']][$probData['probid']]['time'],
                @$summary['problems'][$probData['probid']]['best_time_sort'][$teamData['sortorder']]
        )) {
            $first_to_solve[$probData['probid']] = [
                'problem' => $probData['shortname'],
                'problem_name' => $probData['name'],
                'team' => $team_names[$teamData['externalid']],
                'time' => scoretime($matrix[$teamData['teamid']][$probData['probid']]['time']),
            ];
        }
    }
}

usort($first_to_solve, function ($a, $b) {
    if ($a['time'] === null) {
        $a['time'] = PHP_INT_MAX;
    }
    if ($b['time'] === null) {
        $b['time'] = PHP_INT_MAX;
    }
    if ($a['time'] === $b['time']) {
        return $a['problem'] <=> $b['problem'];
    }
    return $a['time'] <=> $b['time'];
});

?>
<?php if ($useIcpcLayout): ?>
    <?php if (!$download): ?>
        <a href="?mode=icpcsite&download">Download</a>
    <?php endif; ?>
    <div id="xwikicontent">
        <style type="text/css">
            table {
                border-collapse: collapse;
                border: 1px solid #ccc;
                border-bottom: 0;
                width: 52.7em;
                margin-bottom: 2em;
            }

            body {
                font-family: verdana, arial, tahoma, sans-serif;
            }

            table th {
                text-align: center;
                background: #247eca;
                color: white;
                padding: 0em;
                border: outset 2px #eee8aa;
            }

            table td {
                border-bottom: 1px solid #DDD;
                padding: .0em .0em .0em .5em;
            }

            table tr td.rank {
                background: transparent;
                border: 2px outset #ffffff;
            }

            table tr.gold td.rank {
                background: #f9d923;
                border: outset 2px #ffd700;
            }

            table tr.silver td.rank {
                background: Silver;
                border: 2px outset silver;
            }

            table tr.bronze td.rank {
                background: #c08e55;
                border: outset 2px #c9960c;
            }

            table td.name {
                padding-left: 1.2em;
            }

            table th.name {
                padding-left: 3em;
            }

            td.rank, td.solved {
                text-align: center;
                padding: 0px;
            }

            td.time {
                text-align: right;
                padding-right: 0.5em;
            }

            td.lastTime {
                text-align: right;
                padding-right: 1.2em;
            }

            table td.firstSol {
                text-align: right;
                padding: 0 1em;
            }

            table tr.even td {
                background: #F7F7F7;
            }

            table tr:hover td {
                background: #c4defa !important;
            }

            table tr td.r12 {
                background: #fdf993;
                border: 2px outset #DCDCDC;
            }

            table tr td.r11 {
                background: #fddd99;
                border: 2px outset #DCDCDC;
            }

            table tr td.r10 {
                background: #e9d923;
                border: 2px outset #DCDCDC;
            }

            table tr td.r9 {
                background: #e1d963;
                border: 2px outset #DCDCDC;
            }

            table tr td.r8 {
                background: #DDD7AA;
                border: 2px outset #DCDCDC;
            }

            table tr td.r7 {
                background: #d2d2d2;
                border: 2px outset #DCDCDC;
            }

            table tr td.r6 {
                background: #DDCDBD;
                border: 2px outset #DCDCDC;
            }

            table tr td.r5 {
                background: #e6e6e6;
                border: 2px outset #DCDCDC;
            }

            table tr td.r4 {
                background: #eee;
                border: 2px outset #f3f3f3;
            }

            table tr td.r3 {
                background: #F7f7f7;
                border: 2px outset #f7f7f7;
            }

            #uniTable td {
                border: 1px solid #DDD;
            }

            div.tail {
                font-size: .8em;
                color: #888;
                width: 65.875em;
                border: 1px solid #ccc;
            }

            span.right {
                float: right;
            }

            @media print {
                table#rankTable {
                    page-break-after: always;
                }

                .gold td.rank::before {
                    content: "G ";
                }

                .silver td.rank::before {
                    content: "S ";
                }

                .bronze td.rank::before {
                    content: "B ";
                }
            }
        </style>


        <script type="text/javascript"><!--
            function zebraTable(id) {
                var table = document.getElementById(id);
                if (table == null) {
                    return;
                } else {
                    for (var i = 0; i < table.rows.length; i++) {
                        if (i & 1) {
                            table.rows[i].className = table.rows[i].className + " even";
                        } else {
                            table.rows[i].className = table.rows[i].className + " odd";
                        }
                    }
                }
            }

            --></script>
        <table cellspacing="0" id="medalTable">
            <tbody>
            <tr>
                <th><strong>Place</strong></th>
                <th><strong>Name</strong></th>
                <th><strong>Solved</strong></th>
                <th><strong>Time</strong></th>
                <th><strong>Last solved</strong></th>
            </tr>
            <?php foreach ($awarded as $idx => $row): ?>
                <tr class="row<?= $idx + 1 ?> row <?= $row['class'] ?>">
                    <td class="rank"><?= $row['rank'] ?></td>
                    <td class="name"><?= $row['team'] ?></td>
                    <td class="solved r<?= $row['solved'] ?>"><?= $row['solved'] ?></td>
                    <td class="time"><?= $row['total_time'] ?></td>
                    <td class="lastTime"><?= $row['max_time'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table cellspacing="0" id="rankTable">
            <tbody>
            <tr>
                <th><strong>Place</strong></th>
                <th><strong>Name</strong></th>
                <th><strong>Solved</strong></th>
            </tr>
            <?php foreach ($ranked as $row): ?>
                <tr class="row row">
                    <td class="rank"><?= $row['rank'] ?></td>
                    <td class="name"><?= $row['team'] ?></td>
                    <td class="solved r<?= $row['solved'] ?>"><?= $row['solved'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table cellspacing="0" id="uniTable">
            <tbody>
            <tr>
                <th colspan="2">Honorable mention</th>
            </tr>
            <tr>
                <?php foreach ($honorable as $idx => $team): ?>
                <td class="name"><?= $team ?></td>
                <?php if ($idx % 2 === 1 && $team !== end($honorable)): ?>
            </tr>
            <tr><?php endif; ?>
                <?php endforeach; ?>
            </tr>
            </tbody>
        </table>
        <table cellspacing="0" id="regionTable">
            <tbody>
            <tr>
                <th>Region</th>
                <th>Champion</th>
            </tr>
            <?php foreach ($region_winners as $row): ?>
                <tr>
                    <td class="name"><?= $row['group'] ?></td>
                    <td class="name"><?= $row['team'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table cellspacing="0" id="firstTable">
            <tbody>
            <tr>
                <th>Problem</th>
                <th>Team</th>
                <th>Time</th>
            </tr>
            <?php foreach ($first_to_solve as $row): ?>
                <tr>
                    <td class="name"><?= $row['problem_name'] ?></td>
                    <td class="name">
                        <?php if ($row['team'] !== null): ?>
                            <?= $row['team'] ?>
                        <?php else: ?>
                            Not solved
                        <?php endif ?>
                    </td>
                    <td class="name">
                        <?php if ($row['time'] !== null): ?>
                            <?= $row['time'] ?>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <script type="text/javascript">
            zebraTable('medalTable');
            zebraTable('rankTable');
            zebraTable('uniTable');
            zebraTable('regionTable');
            zebraTable('firstTable');
        </script>
    </div>
<?php else: ?>
    <?php
    $title = sprintf('Results for %s', $cdata['name']);
    require(LIBWWWDIR . '/impexp_header.php');
    ?>
    <h2>Awards</h2>
    <table class="table">
        <thead>
        <tr>
            <th scope="col">Place</th>
            <th scope="col">Team</th>
            <th scope="col">Award</th>
            <th scope="col">Solved problems</th>
            <th scope="col">Total time</th>
            <th scope="col">Time of last submission</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($awarded as $row): ?>
            <tr>
                <th scope="row"><?= $row['rank'] ?></th>
                <th scope="row"><?= $row['team'] ?></th>
                <td><?= $row['award'] ?></td>
                <td><?= $row['solved'] ?></td>
                <td><?= $row['total_time'] ?></td>
                <td><?= $row['max_time'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Other ranked teams</h2>
    <table class="table">
        <thead>
        <tr>
            <th scope="col">Rank</th>
            <th scope="col">Team</th>
            <th scope="col">Solved problems</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($ranked as $row): ?>
            <tr>
                <th scope="row"><?= $row['rank'] ?></th>
                <th scope="row"><?= $row['team'] ?></th>
                <td><?= $row['solved'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Honorable mentions</h2>
    <table class="table">
        <tbody>
        <tr>
            <?php foreach ($honorable as $idx => $team): ?>
            <td><?= $team ?></td>
            <?php if ($idx % 2 === 1 && $team !== end($honorable)): ?>
        </tr>
        <tr><?php endif; ?>
            <?php endforeach; ?>
        </tr>
        </tbody>
    </table>

    <h2>Region winners</h2>
    <table class="table">
        <thead>
        <tr>
            <th scope="col">Region</th>
            <th scope="col">Team</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($region_winners as $row): ?>
            <tr>
                <th scope="row"><?= $row['group'] ?></th>
                <td><?= $row['team'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>First to solve</h2>
    <table class="table">
        <thead>
        <tr>
            <th scope="col">Problem</th>
            <th scope="col">Team</th>
            <th scope="col">Time</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($first_to_solve as $row): ?>
            <tr>
                <th scope="row"><?= $row['problem'] ?>: <?= $row['problem_name'] ?></th>
                <td>
                    <?php if ($row['team'] !== null): ?>
                        <?= $row['team'] ?>
                    <?php else: ?>
                        <i>Not solved</i>
                    <?php endif ?>
                </td>
                <td>
                    <?php if ($row['time'] !== null): ?>
                        <?= $row['time'] ?>
                    <?php else: ?>
                        <i>-</i>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    require(LIBWWWDIR . '/impexp_footer.php');
    ?>
<?php endif; ?>
