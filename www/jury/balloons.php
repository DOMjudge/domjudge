<?php
/**
 * Tool to coordinate the handing out of balloons to teams that solved
 * a problem. Similar to the balloons-daemon, but web-based.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Reads balloon filter settings from a cookie and explicit POST of
 * filter settings. Also sets the cookie, so must be called before
 * headers are sent. Returns the balloon filter settings array.
 */
function initBalloonfilter()
{
    $balloonfilter = array();

    // Read balloon filter options from cookie and explicit POST
    if (isset($_COOKIE['domjudge_balloonfilter'])) {
        $balloonfilter = dj_json_decode($_COOKIE['domjudge_balloonfilter']);
    }

    if (isset($_REQUEST['clear'])) {
        $balloonfilter = array();
    }

    if (isset($_REQUEST['filter'])) {
        $balloonfilter = array();
        foreach (array('affilid', 'room') as $type) {
            if (!empty($_REQUEST[$type])) {
                $balloonfilter[$type] = $_REQUEST[$type];
            }
        }
    }

    dj_setcookie('domjudge_balloonfilter', dj_json_encode($balloonfilter));

    return $balloonfilter;
}

$REQUIRED_ROLES = array('jury','balloon');
require('init.php');
$title = 'Balloon Status';

// This reads and sets a cookie, so must be called before headers are sent.
$filter = initBalloonfilter();

if (isset($_POST['done'])) {
    foreach ($_POST['done'] as $done => $dummy) {
        $DB->q('UPDATE balloon SET done=1 WHERE balloonid = %i', $done);
        auditlog('balloon', $done, 'marked done');
    }
    header('Location: balloons.php');
}

$viewall = true;

// Restore most recent view from cookie (overridden by explicit selection)
if (isset($_COOKIE['domjudge_balloonviewall'])) {
    $viewall = $_COOKIE['domjudge_balloonviewall'];
}

// Did someone press the view button?
if (isset($_REQUEST['viewall'])) {
    $viewall = $_REQUEST['viewall'];
}

dj_setcookie('domjudge_balloonviewall', $viewall);

$refresh = array(
    'after' => 15,
    'url' => 'balloons.php',
);
require(LIBWWWDIR . '/header.php');

echo "<h1>Balloon Status</h1>\n\n";

foreach ($cdatas as $cdata) {
    if (isset($cdata['freezetime']) &&
        difftime($cdata['freezetime'], now()) <= 0
    ) {
        echo "<h4>Scoreboard of c${cdata['cid']} (${cdata['shortname']}) is now frozen.</h4>\n\n";
    }
}

echo addForm($pagename, 'get') . "<p>\n" .
    addHidden('viewall', ($viewall ? 0 : 1)) .
    addSubmit($viewall ? 'view unsent only' : 'view all') . "</p>\n" .
    addEndForm();


$contestids = $cids;
if ($cid !== null) {
    $contestids = array($cid);
}

// Filtering by affiliation or room
$affils = $DB->q('TABLE SELECT affilid,
                  team_affiliation.name, room
                  FROM team t
                  LEFT JOIN team_affiliation USING (affilid)
                  INNER JOIN contest c ON (c.cid IN (%Ai))
                  LEFT JOIN contestteam ct ON (ct.teamid = t.teamid AND ct.cid = c.cid)
                  WHERE c.public = 1 OR ct.teamid IS NOT NULL
                  GROUP BY affilid, room', $contestids);

// all possible filter values for the select field
$affilids  = array();
$rooms = array();
foreach ($affils as $affil) {
    if (isset($affil['affilid'])) {
        $affilids[$affil['affilid']] = $affil['name'];
    }
    if (isset($affil['room'])) {
        $rooms[] = $affil['room'];
    }
}
$rooms = array_unique($rooms);
natcasesort($rooms);
asort($affilids, SORT_FLAG_CASE);

// the 'filtered on' text
$filteron = array();
$filtertext = "";
foreach (array('affilid' => 'affiliation', 'room' => 'room') as $type => $text) {
    if (isset($filter[$type])) {
        $filteron[] = $text;
    }
}
if (sizeof($filteron) > 0) {
    $filtertext = "(filtered on " . implode(", ", $filteron) . ")";
}
?>

<table class="balloonfilter">
<tr>
<td><a class="collapse" href="javascript:collapse('filter')"><img src="../images/filter.png" alt="filter&hellip;" title="filter&hellip;" class="picto" /></a></td>
<td><?= $filtertext ?></td>
<td><div id="detailfilter">
<?php

        echo addForm($pagename, 'get') .
            (count($affilids) > 1 ? addSelect('affilid[]', $affilids, @$filter['affilid'], true, 8) : "") .
            (count($rooms)    > 1 ? addSelect('room[]', $rooms, @$filter['room'], false, 8) : "") .
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

// Problem metadata: colours and names.
if (empty($cids)) {
    $probs_data = array();
} else {
    $probs_data = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,name,color,cid
                          FROM problem
                          INNER JOIN contestproblem USING (probid)
                          WHERE cid IN (%Ai)', $contestids);
}

$freezecond = array();
if (!dbconfig_get('show_balloons_postfreeze', 0)) {
    foreach ($cdatas as $cdata) {
        if (isset($cdata['freezetime'])) {
            $freezecond[] = '(submittime < "' . $cdata['freezetime'] . '" AND s.cid = ' . $cdata['cid'] . ')';
        } else {
            $freezecond[] = '(s.cid = ' . $cdata['cid'] . ')';
        }
    }
}

if (empty($freezecond)) {
    $freezecond = '';
} else {
    $freezecond = 'AND (' . implode(' OR ', $freezecond) . ')';
}

// Get all relevant info from the balloon table.
// Order by done, so we have the unsent balloons at the top.
$res = null;
if (!empty($contestids)) {
    $res = $DB->q("SELECT b.*, s.submittime, p.probid, cp.shortname AS probshortname,
                   t.teamid, t.name AS teamname, t.room, c.name AS catname,
                   s.cid, co.shortname
                   FROM balloon b
                   LEFT JOIN submission s USING (submitid)
                   LEFT JOIN problem p USING (probid)
                   LEFT JOIN contestproblem cp USING (probid, cid)
                   LEFT JOIN team t USING(teamid)
                   LEFT JOIN team_category c USING(categoryid)
                   LEFT JOIN contest co USING (cid)
                   WHERE s.cid IN (%Ai) $freezecond" .
                   (isset($filter['affilid']) ? ' AND t.affilid IN (%As) ' : ' %_') .
                   (isset($filter['room']) ? ' AND t.room IN (%As) ' : ' %_') .
                   " ORDER BY done ASC, (1-2*CAST(done AS SIGNED))*CAST(balloonid AS SIGNED) ASC",
                  $contestids, @$filter['affilid'], @$filter['room']);
}

/* Loop over the result, store the total of balloons for a team
 * (saves a query within the inner loop).
 * We need to store the rows aswell because we can only next()
 * once over the db result.
 */
$BALLOONS = $TOTAL_BALLOONS = array();
while (!empty($contestids) && $row = $res->next()) {
    $BALLOONS[] = $row;
    $TOTAL_BALLOONS[$row['teamid']][] = $row['probid'];

    // keep overwriting these variables - in the end they'll
    // contain the id's of the first balloon in each type
    $first_contest[$row['cid']] = $first_problem[$row['probid']] = $first_team[$row['teamid']] = $row['balloonid'];
}

if (!empty($BALLOONS)) {
    echo addForm($pagename);

    echo "<table class=\"list sortable balloons\">\n<thead>\n" .
         "<tr><th class=\"sorttable_numeric\">ID</th>" .
         "<th>time</th>" . (count($contestids) > 1 ? "<th>contest</th>" : "") .
         "<th>solved</th><th>team</th>" .
         "<th></th><th>loc.</th><th>category</th><th>total</th>" .
         "<th></th><th></th></tr>\n</thead>\n";

    foreach ($BALLOONS as $row) {
        if (!$viewall && $row['done'] == 1) {
            continue;
        }

        // start a new row, 'disable' if balloon has been handed out already
        echo '<tr'  . ($row['done'] == 1 ? ' class="disabled"' : '') . '>';
        echo '<td>b' . (int)$row['balloonid'] . '</td>';
        echo '<td>' . printtime($row['submittime'], null, $row['cid']) . '</td>';

        if (count($contestids) > 1) {
            // contest of this problem, only when more than one active
            echo '<td>' . specialchars($row['shortname']) . '</td>';
        }

        // the balloon earned
        echo '<td class="probid">' .
            '<div class="circle" style="background-color: ' .
            specialchars($probs_data[$row['probid']]['color']) .
            ';"></div> ' . specialchars($row['probshortname']) . '</td>';

        // team name, location (room) and category
        echo '<td>t' . specialchars($row['teamid']) . '</td><td>' .
            specialchars($row['teamname']) . '</td><td>' .
            specialchars($row['room']) . '</td><td>' .
            specialchars($row['catname']) . '</td><td>';

        // list of balloons for this team
        sort($TOTAL_BALLOONS[$row['teamid']]);
        $TOTAL_BALLOONS[$row['teamid']] = array_unique($TOTAL_BALLOONS[$row['teamid']]);
        foreach ($TOTAL_BALLOONS[$row['teamid']] as $prob_solved) {
            echo '<div title="' . specialchars($prob_solved) .
                '" class="circle" style="background-color: ' .
                specialchars($probs_data[$prob_solved]['color']) .
                ';"></div> ';
        }
        echo '</td><td>';

        // 'done' button when balloon has yet to be handed out
        if ($row['done'] == 0) {
            echo '<input type="submit" name="done[' .
                (int)$row['balloonid'] . ']" value="done" />';
        }

        echo '</td><td>';

        $comments = array();
        if ($first_contest[$row['cid']] == $row['balloonid']) {
            $comments[] = 'first in contest';
        } else {
            if ($first_team[$row['teamid']] == $row['balloonid']) {
                $comments[] = 'first for team';
            }
            if ($first_problem[$row['probid']] == $row['balloonid']) {
                $comments[] = 'first for problem';
            }
        }
        echo implode('; ', $comments);

        echo "</td></tr>\n";
    }

    echo "</table>\n\n" . addEndForm();
} else {
    echo "<p class=\"nodata\">No correct submissions yet... keep posted!</p>\n\n";
}


require(LIBWWWDIR . '/footer.php');
