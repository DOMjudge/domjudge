<?php
/**
 * View current, past and future contests
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/checkers.jury.php');

define('STARTTIME_UPDATE_MIN_SECONDS_BEFORE', 30);

$times = array('activate','start','freeze','end','unfreeze','finalize','deactivate');
$start_actions = array('delay_start', 'resume_start');
$actions = array_merge($times, $start_actions);

$now = now();

if (isset($_POST['donow'])) {
    requireAdmin();

    $docid = $_POST['cid'];
    $docdata = $cdatas[$docid];

    // The first array encodes the contest ID, second level the time.
    $time = key(reset($_POST['donow']));
    if (!in_array($time, $actions, true)) {
        error("Unknown value '$time' for timetype");
    }

    if ($time === 'finalize') {
        header('Location: finalize.php?id=' . (int)$docid);
        exit;
    }

    $now = floor($now);
    $nowstring = strftime('%Y-%m-%d %H:%M:%S ', $now) . date_default_timezone_get();
    auditlog('contest', $docid, $time. ' now', $nowstring);

    // Special case delay/resume start (only sets/unsets starttime_undefined).
    if (in_array($time, $start_actions, true)) {
        $enabled = $time==='delay_start' ? 0 : 1;
        if (difftime($docdata['starttime'], $now) <= STARTTIME_UPDATE_MIN_SECONDS_BEFORE) {
            error("Cannot $time less than ".STARTTIME_UPDATE_MIN_SECONDS_BEFORE.
                  " seconds before contest start.");
        }
        $DB->q('UPDATE contest SET starttime_enabled = %i
                WHERE cid = %i', $enabled, $docid);
        header("Location: ./contests.php?edited=1");
        exit;
    }

    // starttime is special because other, relative times depend on it.
    if ($time == 'start') {
        if ($docdata['starttime_enabled'] &&
            difftime($docdata['starttime'], $now) <= STARTTIME_UPDATE_MIN_SECONDS_BEFORE) {
            error("Cannot update starttime less than ".STARTTIME_UPDATE_MIN_SECONDS_BEFORE.
                  " seconds before contest start.");
        }
        $docdata['starttime'] = $now;
        $docdata['starttime_string'] = $nowstring;
        foreach (array('endtime','freezetime','unfreezetime','activatetime','deactivatetime') as $f) {
            $docdata[$f] = check_relative_time($docdata[$f.'_string'], $docdata['starttime'], $f);
        }
        $DB->q('UPDATE contest SET starttime = %f, starttime_string = %s, starttime_enabled = 1,
                endtime = %f, freezetime = %f, unfreezetime = %f,
                activatetime = %f, deactivatetime = %f
                WHERE cid = %i',
               $docdata['starttime'], $docdata['starttime_string'], $docdata['endtime'],
               $docdata['freezetime'], $docdata['unfreezetime'], $docdata['activatetime'],
               $docdata['deactivatetime'], $docid);
        header("Location: ./contests.php?edited=1");
    } else {
        $DB->q('UPDATE contest SET ' . $time . 'time = %f, ' . $time . 'time_string = %s
                WHERE cid = %i', $now, $nowstring, $docid);
        header("Location: ./contests.php");
    }
    exit;
}

$title = 'Contests';
require(LIBWWWDIR . '/header.php');

echo "<h1>Contests</h1>\n\n";

if (isset($_GET['edited'])) {
    echo addForm('refresh_cache.php') .
            msgbox(
                "Warning: Refresh scoreboard cache",
        "After changing the contest start time, it may be necessary to recalculate any cached scoreboards.<br /><br />" .
        addSubmit('recalculate caches now', 'refresh')
        ) .
        addEndForm();
}

// Display current contest data prominently

echo "<fieldset><legend>Current contests: ";

$curcids = getCurContests(false);

if (empty($curcids)) {
    echo "none</legend>\n\n";

    $row = $DB->q('MAYBETUPLE SELECT * FROM contest
                   WHERE activatetime > UNIX_TIMESTAMP() AND enabled = 1
                   ORDER BY activatetime LIMIT 1');

    if ($row) {
        echo "<form action=\"contests.php\" method=\"post\">\n";
        echo addHidden('cid', $row['cid']);
        echo "<p>No active contest. Upcoming:<br/> <em>" .
             specialchars($row['name']) .
             ' (' . specialchars($row['shortname']) . ')' .
             "</em>; active from " . printtime($row['activatetime'], '%a %d %b %Y %T %Z') .
             "<br /><br />\n";
        if (IS_ADMIN) {
            echo addSubmit("activate now", "donow[$row[cid]][activate]");
        }
        echo "</form>\n\n";
    } else {
        echo "<p class=\"nodata\">No upcoming contest</p>\n";
    }
} else {
    if (empty($curcids)) {
        $rows = array();
    } else {
        $rows = $DB->q('TABLE SELECT * FROM contest WHERE cid IN (%Ai)', $curcids);
    }
    echo "</legend>\n\n";

    foreach ($rows as $row) {
        $prevchecked = false;
        $hasstarted = difftime($row['starttime'], $now) <= 0;
        $hasended = difftime($row['endtime'], $now) <= 0;
        $hasfrozen = !empty($row['freezetime']) &&
                 difftime($row['freezetime'], $now) <= 0;
        $hasunfrozen = !empty($row['unfreezetime']) &&
                   difftime($row['unfreezetime'], $now) <= 0;
        $isfinal = !empty($row['finalizetime']);

        $contestname = specialchars(sprintf('%s (%s - c%d)', $row['name'], $row['shortname'], $row['cid']));

        echo "<form action=\"contests.php\" method=\"post\">\n";
        echo addHidden('cid', $row['cid']);
        echo "<fieldset><legend>${contestname}</legend>\n";

        if (! $row['starttime_enabled']) {
            $hasstarted = $hasended = $hasfrozen = $hasunfrozen = false;
            if ($isfinal) {
                warning("start time is undefined, but contest is finalized!");
            }
        }

        echo "<table>\n";
        foreach ($times as $time) {
            echo "<tr><td>";
            // display checkmark when done or ellipsis when next up
            if (empty($row[$time . 'time'])) {
                // don't display anything before an empty row
            } elseif (difftime($row[$time . 'time'], $now) <= 0) {
                // this event has passed, mark as such
                echo "<img src=\"../images/s_success.png\" alt=\"&#10003;\" class=\"picto\" />\n";
                $prevchecked = true;
            } elseif ($prevchecked) {
                echo "…";
                $prevchecked = false;
            }

            echo "</td><td>" .
                 ucfirst($time) . " time:</td><td" .
                 ($time=='start' && !$row['starttime_enabled'] ? ' class="ignore"' : '') . ">" .
                 printtime($row[$time . 'time'], '%Y-%m-%d %H:%M (%Z)') .
                 "</td><td>";

            // Show a button for setting the time to now(), only when that
            // makes sense. E.g. only for end contest when contest has started.
            // No button for 'activate', because when shown by definition always already active
            if (IS_ADMIN) {
                $now_allowed = true;
                switch ($time) {
                    case 'start':
                        $now_allowed = !$hasstarted;
                        break;
                    case 'end':
                        $now_allowed = $hasstarted && !$hasended && (empty($row['freezetime']) || $hasfrozen);
                        break;
                    case 'deactivate':
                        $now_allowed = $hasended && (empty($row['unfreezetime']) || $hasunfrozen);
                        break;
                    case 'freeze':
                        $now_allowed = $hasstarted && !$hasended && !$hasfrozen;
                        break;
                    case 'unfreeze':
                        $now_allowed = $hasfrozen && !$hasunfrozen && $hasended;
                        break;
                    case 'finalize':
                        $now_allowed = $hasended && !$isfinal;
                        break;
                }
                if ($now_allowed) {
                    echo addSubmit("$time now", "donow[$row[cid]][$time]");
                }
            }

            $close_to_start = difftime($row['starttime'], $now) <= STARTTIME_UPDATE_MIN_SECONDS_BEFORE;
            if ($time=='start' && !$close_to_start) {
                $type = $row['starttime_enabled'] ? 'delay' : 'resume';
                echo addSubmit($type.' start', "donow[$row[cid]][${type}_start]");
            }

            echo "</td></tr>";
        }

        echo "</table>\n";

        echo "</fieldset>\n</form>\n\n";
    }
}

echo "</fieldset>\n\n";


// Get data. Starttime seems most logical sort criterion.
$res = $DB->q('TABLE SELECT contest.*, COUNT(teamid) AS numteams
               FROM contest
               LEFT JOIN contestteam USING (cid)
               GROUP BY cid ORDER BY starttime DESC');
$numprobs = $DB->q('KEYVALUETABLE SELECT cid, COUNT(probid) FROM contest LEFT JOIN contestproblem USING(cid) GROUP BY cid');
if (ALLOW_REMOVED_INTERVALS) {
    $numintvs = $DB->q('KEYVALUETABLE SELECT cid, COUNT(intervalid) FROM removed_interval GROUP BY cid');
}

if (count($res) == 0) {
    echo "<p class=\"nodata\">No contests defined</p>\n\n";
} else {
    echo "<h3>All available contests</h3>\n\n";
    echo "<table class=\"list sortable\">\n<thead>\n" .
         "<tr><th scope=\"col\" class=\"sorttable_numeric\">CID</th>";
    echo "<th scope=\"col\">shortname</th>";
    foreach ($times as $time) {
        echo "<th scope=\"col\">$time</th>";
    }
    if (ALLOW_REMOVED_INTERVALS) {
        echo "<th scope=\"col\">removed<br />intervals</th>";
    }
    echo "<th scope=\"col\">process<br />balloons?</th>";
    echo "<th scope=\"col\">public?</th>";
    echo "<th scope=\"col\" class=\"sorttable_numeric\"># teams</th>";
    echo "<th scope=\"col\" class=\"sorttable_numeric\"># problems</th>";
    echo "<th scope=\"col\">name</th>" .
         (IS_ADMIN ? "<th scope=\"col\"></th>" : '') .
         "</tr>\n</thead>\n<tbody>\n";

    $iseven = false;
    foreach ($res as $row) {
        $link = '<a href="contest.php?id=' . urlencode($row['cid']) . '">';

        echo '<tr class="' .
            ($iseven ? 'roweven': 'rowodd') .
            (!$row['enabled']    ? ' disabled' :'') .
            (in_array($row['cid'], $curcids) ? ' highlight':'') . '">' .
            "<td class=\"tdright\">" . $link .
            "c" . (int)$row['cid'] . "</a></td>\n";
        echo "<td>" . $link . specialchars($row['shortname']) . "</a></td>\n";
        foreach ($times as $time) {
            $timeval = @$row[$time.'time'];
            if (! $row['starttime_enabled'] && $time!='activate') {
                $timeval = null;
            }
            echo "<td title=\"".printtime($timeval, '%Y-%m-%d %H:%M:%S (%Z)') . "\">" .
                  $link . (isset($timeval) ?
                  printtime($timeval) : '-') . "</a></td>\n";
        }
        if (ALLOW_REMOVED_INTERVALS) {
            echo "<td>" . $link . (isset($numintvs[$row['cid']])?$numintvs[$row['cid']]:0) . "</a></td>\n";
        }
        echo "<td>" . $link . ($row['process_balloons'] ? 'yes' : 'no') . "</a></td>\n";
        echo "<td>" . $link . ($row['public'] ? 'yes' : 'no') . "</a></td>\n";
        echo "<td>" . $link . ($row['public'] ? '<em>all</em>' : $row['numteams']) . "</a></td>\n";
        echo "<td>" . $link . $numprobs[$row['cid']] . "</a></td>\n";
        echo "<td>" . $link . specialchars($row['name']) . "</a></td>\n";
        $iseven = ! $iseven;

        if (IS_ADMIN) {
            echo "<td class=\"editdel\">" .
                editLink('contest', $row['cid']) . "&nbsp;" .
                delLink('contest', 'cid', $row['cid'], $row['name']) . "</td>\n";
        }

        echo "</tr>\n";
    }
    echo "</tbody>\n</table>\n\n";
}

if (IS_ADMIN) {
    echo "<p>" . addLink('contest') . "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
