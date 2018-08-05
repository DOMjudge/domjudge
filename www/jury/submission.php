<?php
/**
 * View the details of a specific submission
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

// Returns a piece of SQL code to return a field, truncated to a
// configured character length, with a message if truncation happened.
function truncate_SQL_field($field)
{
    $size = (int) dbconfig_get('output_display_limit', 2000);
    // $size == -1 means never perform truncation:
    if ($size < 0) {
        return $field;
    }

    $msg = "\n[output display truncated after $size B]\n";
    return "IF( CHAR_LENGTH($field)>$size , CONCAT(LEFT($field,$size),'$msg') , $field)";
}

function display_compile_output($output, $success)
{
    $color = "#6666FF";
    $msg = "not finished yet";
    if ($output !== null) {
        if ($success) {
            $color = '#1daa1d';
            $msg = 'successful';
            if (!empty($output)) {
                $msg .= ' (with ' . mb_substr_count($output, "\n") . ' line(s) of output)';
            }
        } else {
            $color = 'red';
            $msg = 'unsuccessful';
        }
    }

    echo '<h3 id="compile">' .
        (empty($output) ? '' : "<a class=\"collapse\" href=\"javascript:collapse('compile')\">") .
        "Compilation <span style=\"color:$color;\">$msg</span>" .
        (empty($output) ? '' : "</a>") . "</h3>\n\n";

    if (!empty($output)) {
        echo '<pre class="output_text" id="detailcompile">' .
            specialchars($output)."</pre>\n\n";
    } else {
        echo '<p class="nodata" id="detailcompile">' .
            "There were no compiler errors or warnings.</p>\n";
    }

    // Collapse compile output when compiled succesfully.
    if ($success) {
        echo "<script type=\"text/javascript\">
<!--
    collapse('compile');
// -->
</script>\n";
    }
}

function display_runinfo($runinfo, $is_final)
{
    $sum_runtime = 0;
    $max_runtime = 0;
    $tclist = "";
    foreach ($runinfo as $key => $run) {
        $link = '#run-' . $run['rank'];
        $class = ($is_final ? "tc_unused" : "tc_pending");
        $short = "?";
        switch ($run['runresult']) {
        case 'correct':
            $class = "tc_correct";
            $short = "✓";
            break;
        case null:
            break;
        default:
            $short = substr($run['runresult'], 0, 1);
            $class = "tc_incorrect";
        }
        $tclist .= "<a title=\"desc: " . specialchars($run['description']) .
            ($run['runresult'] !== null ?  ", runtime: " . $run['runtime'] . "s, result: " . $run['runresult'] : '') .
            "\" href=\"$link\"" .
            ($run['runresult'] == 'correct' ? ' onclick="display_correctruns(true);" ' : '') .
            "><span class=\"$class tc_box\">" . $short . "</span></a>";

        $sum_runtime += $run['runtime'];
        $max_runtime = max($max_runtime, $run['runtime']);
    }
    return array($tclist, $sum_runtime, $max_runtime);
}

function compute_lcsdiff($line1, $line2)
{
    $tokens1 = preg_split('/\s+/', $line1);
    $tokens2 = preg_split('/\s+/', $line2);
    $cutoff = 100; // a) LCS gets inperformant, b) the output is not longer readable

    $n1 = min($cutoff, sizeof($tokens1));
    $n2 = min($cutoff, sizeof($tokens2));

    // compute longest common sequence length
    $dp = array_fill(0, $n1+1, array_fill(0, $n2+1, 0));
    for ($i = 1; $i < $n1 + 1; $i++) {
        for ($j = 1; $j < $n2 + 1; $j++) {
            if ($tokens1[$i-1] == $tokens2[$j-1]) {
                $dp[$i][$j] = $dp[$i-1][$j-1] + 1;
            } else {
                $dp[$i][$j] = max($dp[$i-1][$j], $dp[$i][$j-1]);
            }
        }
    }

    if ($n1 == $n2 && $n1 == $dp[$n1][$n2]) {
        return array(false, specialchars($line1) . "\n");
    }

    // reconstruct lcs
    $i = $n1;
    $j = $n2;
    $lcs = array();
    while ($i > 0 && $j > 0) {
        if ($tokens1[$i-1] == $tokens2[$j-1]) {
            $lcs[] = $tokens1[$i-1];
            $i--;
            $j--;
        } elseif ($dp[$i-1][$j] > $dp[$i][$j-1]) {
            $i--;
        } else {
            $j--;
        }
    }
    $lcs = array_reverse($lcs);

    // reconstruct diff
    $diff = "";
    $l = sizeof($lcs);
    $i = 0;
    $j = 0;
    for ($k = 0; $k < $l ; $k++) {
        while ($i < $n1 && $tokens1[$i] != $lcs[$k]) {
            $diff .= "<del>" . specialchars($tokens1[$i]) . "</del> ";
            $i++;
        }
        while ($j < $n2 && $tokens2[$j] != $lcs[$k]) {
            $diff .= "<ins>" . specialchars($tokens2[$j]) . "</ins> ";
            $j++;
        }
        $diff .= $lcs[$k] . " ";
        $i++;
        $j++;
    }
    while ($i < $n1 && ($k >= $l || $tokens1[$i] != $lcs[$k])) {
        $diff .= "<del>" . specialchars($tokens1[$i]) . "</del> ";
        $i++;
    }
    while ($j < $n2 && ($k >= $l || $tokens2[$j] != $lcs[$k])) {
        $diff .= "<ins>" . specialchars($tokens2[$j]) . "</ins> ";
        $j++;
    }

    if ($cutoff < sizeof($tokens1) || $cutoff < sizeof($tokens2)) {
        $diff .= "[cut off rest of line...]";
    }
    $diff .= "\n";

    return array(true, $diff);
}

require('init.php');

$id = getRequestID();

if (!empty($_GET['jid'])) {
    $jid = (int)$_GET['jid'];
}
if (!empty($_GET['rejudgingid'])) {
    $rejudgingid = (int)$_GET['rejudgingid'];
}
if (!empty($_GET['ext_id'])) {
    $ext_id = (int)@$_GET['ext_id'];
}

// Also check for $id in claim POST variable as submissions.php cannot
// send the submission ID as a separate variable.
if (is_array(@$_POST['claim'])) {
    foreach ($_POST['claim'] as $key => $val) {
        $id = (int)$key;
    }
}
if (is_array(@$_POST['unclaim'])) {
    foreach ($_POST['unclaim'] as $key => $val) {
        $id = (int)$key;
    }
}

if (isset($jid) && isset($rejudgingid)) {
    error("You cannot specify jid and rejudgingid at the same time.");
}

// If jid is set but not id, try to deduce it the id from the database.
if (isset($jid) && ! $id) {
    $id = $DB->q('MAYBEVALUE SELECT submitid FROM judging
                  WHERE judgingid = %i', $jid);
}

// If jid is not set but rejudgingid is, try to deduce the jid from the database.
if (!isset($jid) && isset($rejudgingid)) {
    $jid = $DB->q('MAYBEVALUE SELECT judgingid FROM judging
                   WHERE submitid=%i AND rejudgingid = %i', $id, $rejudgingid);
}

// If external id is set but not id, try to deduce it from the database.
if (isset($ext_id) && ! $id) {
    if (!isset($cid)) {
        error("Cannot determine submission from external ID without " .
              "selecting a contest.");
    }
    $id = $DB->q('MAYBEVALUE SELECT submitid FROM submission
                  WHERE cid = %i AND externalid = %i', $cid, $ext_id);
}

$title = 'Submission s'.@$id;

if (! $id) {
    error("Missing or invalid submission id");
}

$submdata = $DB->q('MAYBETUPLE SELECT s.teamid, s.probid, s.langid, s.origsubmitid,
                    s.submittime, s.valid, c.cid, c.shortname AS contestshortname,
                    s.externalid, s.externalresult,
                    c.name AS contestname, t.name AS teamname, l.name AS langname,
                    cp.shortname AS probshortname, p.name AS probname,
                    time_factor*timelimit AS maxruntime
                    FROM submission s
                    LEFT JOIN team     t ON (t.teamid = s.teamid)
                    LEFT JOIN problem  p ON (p.probid = s.probid)
                    LEFT JOIN language l ON (l.langid = s.langid)
                    LEFT JOIN contest  c ON (c.cid    = s.cid)
                    LEFT JOIN contestproblem cp ON (cp.probid = p.probid AND cp.cid = c.cid)
                    WHERE submitid = %i', $id);

if (! $submdata) {
    error("Missing submission data");
}

$jdata = $DB->q('KEYTABLE SELECT judgingid AS ARRAYKEY, result, j.valid, j.starttime,
                 j.endtime, j.judgehost, j.verified, j.jury_member, j.verify_comment,
                 r.reason, r.rejudgingid,
                 MAX(jr.runtime) AS max_runtime,
                 (j.endtime IS NULL AND j.valid=0 AND
                  (r.valid IS NULL OR r.valid=0)) AS aborted
                 FROM judging j
                 LEFT JOIN judging_run jr USING(judgingid)
                 LEFT JOIN rejudging r USING (rejudgingid)
                 WHERE cid = %i AND submitid = %i
                 GROUP BY (j.judgingid)
                 ORDER BY starttime ASC, judgingid ASC',
                $submdata['cid'], $id);

// When there's no judging selected through the request, we select the
// valid one.
if (!isset($jid)) {
    $jid = null;
    foreach ($jdata as $judgingid => $jud) {
        if ($jud['valid']) {
            $jid = $judgingid;
        }
    }
}

$jury_member = $username;

if (isset($_REQUEST['claim']) || isset($_REQUEST['unclaim'])) {

    // Send headers before possible warning messages.
    require_once(LIBWWWDIR . '/header.php');

    $unornot = isset($_REQUEST['unclaim']) ? 'un' : '';

    if (!isset($jid)) {
        warning("Cannot " . $unornot . "claim this submission: no valid judging found.");
    } elseif ($jdata[$jid]['verified']) {
        warning("Cannot " . $unornot . "claim this submission: judging already verified.");
    } elseif (empty($jury_member) && $unornot==='') {
        warning("Cannot claim this submission: no jury member specified.");
    } else {
        if (!empty($jdata[$jid]['jury_member']) && isset($_REQUEST['claim']) &&
            $jury_member !== $jdata[$jid]['jury_member'] &&
            !isset($_REQUEST['forceclaim'])) {

            // Don't use warning() here since it implies that a
            // recoverable error has occurred. Also, it generates
            // invalid HTML (using an unclosed <b> tag) to detect such
            // issues.
            echo "<fieldset class=\"warning\"><legend>Warning</legend>" .
                 "Submission has been claimed by " . @$jdata[$jid]['jury_member'] .
                 ". Claim again on this page to force an update.</fieldset>";
            goto claimdone;
        }
        $DB->q('UPDATE judging SET jury_member = ' .
               ($unornot==='un' ? 'NULL %_ ' : '%s ') .
               'WHERE judgingid = %i', $jury_member, $jid);
        auditlog('judging', $jid, $unornot . 'claimed');

        if ($unornot==='un') {
            header('Location: submissions.php');
        } else {
            header('Location: submission.php?id=' . $id);
        }
        exit;
    }
}
claimdone:

if (!isset($jid)) {
    // Automatically refresh page while we wait for judging data.
    $refresh = array(
        'after' => 15,
        'url' => 'submission.php?id=' . urlencode($id)
    );
}

// Headers might already have been included.
require_once(LIBWWWDIR . '/header.php');

echo "<br/><h1 style=\"display:inline;\">Submission s" . $id .
    (isset($submdata['origsubmitid']) ?
      ' (resubmit of <a href="submission.php?id='. urlencode($submdata['origsubmitid']) .
      '">s' . specialchars($submdata['origsubmitid']) . '</a>)' : '') .
    ($submdata['valid'] ? '' : ' (ignored)') . "</h1>\n\n";
if (IS_ADMIN) {
    $val = ! $submdata['valid'];
    $unornot = $val ? 'un' : '';
    echo "&nbsp;\n" . addForm('ignore.php') .
        addHidden('id', $id) .
        addHidden('val', $val) .
            '<input type="submit" value="' . $unornot .
            'IGNORE this submission" onclick="return confirm(\'Really ' . $unornot .
            "ignore submission s$id?');\" /></form>\n";
}
if (! $submdata['valid']) {
    echo "<p>This submission is not used during scoreboard calculations.</p>\n\n";
}

// Condensed submission info on a single line with icons:
?>

<p>
<img title="team" alt="Team:" src="../images/team.png"/> <a href="team.php?id=<?php echo urlencode($submdata['teamid'])?>&amp;cid=<?php echo urlencode($submdata['cid'])?>">
    <?php echo specialchars($submdata['teamname'] . " (t" . $submdata['teamid'].")")?></a>&nbsp;&nbsp;
<img title="contest" alt="Contest:" src="../images/contest.png"/> <a href="contest.php?id=<?php echo $submdata['cid']?>">
    <span class="contestid"><?php echo specialchars($submdata['contestshortname'])?></span></a>&nbsp;&nbsp;
<img title="problem" alt="Problem:" src="../images/problem.png"/> <a href="problem.php?id=<?php echo $submdata['probid']?>&amp;cid=<?php echo urlencode($submdata['cid'])?>">
    <span class="probid"><?php echo specialchars($submdata['probshortname'])?></span>:
    <?php echo specialchars($submdata['probname'])?></a>&nbsp;&nbsp;
<img title="language" alt="Language:" src="../images/lang.png"/> <a href="language.php?id=<?php echo $submdata['langid']?>">
    <?php echo specialchars($submdata['langname'])?></a>&nbsp;&nbsp;
<img title="submittime" alt="Submittime:" src="../images/submittime.png"/>
    <?php echo '<span title="' . printtime($submdata['submittime'], '%Y-%m-%d %H:%M:%S (%Z)') . '">' .
               printtime($submdata['submittime'], null, $submdata['cid']) . '</span>' ?>&nbsp;&nbsp;
<img title="allowed runtime" alt="Allowed runtime:" src="../images/allowedtime.png"/>
    <?php echo  specialchars($submdata['maxruntime']) ?>s&nbsp;&nbsp;
<img title="view source code" alt="" src="../images/code.png"/>
<a href="show_source.php?id=<?= $id ?>" style="font-weight:bold;">view source code</a>
</p>

<?php

if (isset($submdata['externalid']) && defined('EXT_CCS_URL')) {
    echo "&nbsp;&nbsp;External ID: <a href=\"" . EXT_CCS_URL . urlencode($submdata['externalid']) .
        "\" target=\"extCCS\">" . specialchars($submdata['externalid']) . "</a>, " .
        printresult($submdata['externalresult']);
}

echo "</p>\n";

if (count($jdata) > 1 || (count($jdata)==1 && !isset($jid))) {
    echo "<table class=\"list\">\n" .
        "<caption>Judgings</caption>\n<thead>\n" .
        "<tr><td></td><th scope=\"col\">ID</th><th scope=\"col\">start</th>" .
        "<th scope=\"col\">max runtime</th>" .
        "<th scope=\"col\">judgehost</th><th scope=\"col\">result</th>" .
        "<th scope=\"col\">rejudging</th>" .
        "</tr>\n</thead>\n<tbody>\n";

    // print the judgings
    foreach ($jdata as $judgingid => $jud) {
        echo '<tr' . ($jud['valid'] ? '' : ' class="disabled"') . '>';
        $link = '<a href="submission.php?id=' . $id . '&amp;jid=' . $judgingid . '">';

        if ($judgingid == $jid) {
            echo '<td>' . $link . '&rarr;&nbsp;</a></td>';
        } else {
            echo '<td>' . $link . '&nbsp;</a></td>';
        }

        $rinfo = isset($jud['rejudgingid']) ? 'r' . $jud['rejudgingid'] . ' (' . $jud['reason'] . ')' : '';

        echo '<td>' . $link . 'j' . $judgingid . '</a></td>' .
            '<td>' . $link . printtime($jud['starttime'], null, $submdata['cid']) . '</a></td>' .
            '<td>' . $link . specialchars($jud['max_runtime']) .
                             (isset($jud['max_runtime']) ? ' s' : '') . '</a></td>' .
            '<td>' . $link . printhost(@$jud['judgehost']) . '</a></td>' .
            '<td>' . $link . printresult(@$jud['result'], $jud['valid']) .
                             printjudgingbusy($jud) . '</a></td>' .
            '<td>' . $link . specialchars($rinfo) . '</a></td>' .
            "</tr>\n";
    }
    echo "</tbody>\n</table><br />\n\n";
}

if (!isset($jid)) {
    echo "<p><em>Not (re)judged yet</em></p>\n\n";

    // Check if there is an active judgehost that can judge this
    // submission. Otherwise, generate an error.
    $judgehosts = $DB->q('TABLE SELECT hostname, restrictionid, restrictions
                          FROM judgehost
                          LEFT JOIN judgehost_restriction USING (restrictionid)
                          WHERE active = 1');
    $can_be_judged = false;

    foreach ($judgehosts as $judgehost) {
        if ($judgehost['restrictionid'] === null) {
            $can_be_judged = true;
            break;
        }

        // Get judgehost restrictions
        $contests = array();
        $problems = array();
        $languages = array();
        if (isset($judgehost['restrictions'])) {
            $restrictions = dj_json_decode($judgehost['restrictions']);
            $contests = @$restrictions['contest'];
            $problems = @$restrictions['problem'];
            $languages = @$restrictions['language'];
        }

        $extra_join = '';
        $extra_where = '';
        if (empty($contests)) {
            $extra_where .= '%_ ';
        } else {
            $extra_where .= 'AND s.cid IN (%Ai) ';
        }

        if (empty($problems)) {
            $extra_where .= '%_ ';
        } else {
            $extra_join  .= 'LEFT JOIN problem p USING (probid) ';
            $extra_where .= 'AND s.probid IN (%Ai) ';
        }

        if (empty($languages)) {
            $extra_where .= '%_ ';
        } else {
            $extra_where .= 'AND s.langid IN (%As) ';
        }

        $submitid = $DB->q('MAYBEVALUE SELECT s.submitid
                            FROM submission s
                            LEFT JOIN language l USING (langid)
                            LEFT JOIN contestproblem cp USING (probid, cid) ' .
                           $extra_join .
                           'WHERE s.submitid = %i AND s.judgehost IS NULL
                            AND l.allow_judge = 1 AND cp.allow_judge = 1 AND s.valid = 1 ' .
                           $extra_where . 'LIMIT 1',
                           $id, $contests, $problems, $languages);

        if (isset($submitid)) {
            $can_be_judged = true;
        }
    }

    if (!$can_be_judged) {
        error("No active judgehost can judge this submission. Edit judgehost restrictions!");
    }

    $lang_allowed = $DB->q('VALUE SELECT allow_judge
                            FROM language
                            LEFT JOIN submission USING (langid)
                            WHERE submitid = %i', $id);
    if ($lang_allowed == 0) {
        error("Submission language is currently not allowed to be judged!");
    }

    $prob_allowed = $DB->q('VALUE SELECT allow_judge
                            FROM contestproblem cp
                            LEFT JOIN submission USING (probid)
                            WHERE submitid = %i AND cp.cid = %i', $id, $submdata['cid']);
    if ($prob_allowed == 0) {
        error("Problem is currently not allowed to be judged!");
    }

    // nothing more to display
    require(LIBWWWDIR . '/footer.php');
    exit;
}


// Display the details of the selected judging

$jud = $DB->q('TUPLE SELECT j.*, r.valid AS rvalid
               FROM judging j
               LEFT JOIN rejudging r USING (rejudgingid)
               WHERE judgingid = %i', $jid);

// sanity check
if ($jud['submitid'] != $id) {
    error(sprintf("judingid j%d belongs to submitid s%d, not s%d", $jid, $jud['submitid'], $id));
}

// Display testcase runs
$runs = $DB->q('TABLE SELECT r.runid, r.judgingid,
                r.testcaseid, r.runresult, r.runtime, ' .
                truncate_SQL_field('r.output_run')    . ' AS output_run, ' .
                truncate_SQL_field('r.output_diff')   . ' AS output_diff, ' .
                truncate_SQL_field('r.output_error')  . ' AS output_error, ' .
                truncate_SQL_field('r.output_system') . ' AS output_system, ' .
                truncate_SQL_field('t.output')        . ' AS output_reference,
                t.rank, t.description, t.image_type, t.image_thumb
                FROM testcase t
                LEFT JOIN judging_run r ON ( r.testcaseid = t.testcaseid AND
                                             r.judgingid = %i )
                WHERE t.probid = %s ORDER BY rank',
               $jid, $submdata['probid']);

// Use original submission as previous, or try to find a previous
// submission/judging of the same team/problem.
if (isset($submdata['origsubmitid'])) {
    $lastsubmitid = $submdata['origsubmitid'];
} else {
    $lastsubmitid = $DB->q('MAYBEVALUE SELECT submitid
                            FROM submission
                            WHERE teamid = %i AND probid = %i AND submittime < %s
                            ORDER BY submittime DESC LIMIT 1',
                           $submdata['teamid'], $submdata['probid'], $submdata['submittime']);
}

$lastjud = null;
if ($lastsubmitid !== null) {
    $lastjud = $DB->q('MAYBETUPLE SELECT judgingid, result, verify_comment, endtime
                       FROM judging
                       WHERE submitid = %s AND valid = 1
                       ORDER BY judgingid DESC LIMIT 1', $lastsubmitid);
    if ($lastjud !== null) {
        $lastruns = $DB->q('TABLE SELECT r.runtime, r.runresult, rank, description
                            FROM testcase t
                            LEFT JOIN judging_run r ON ( r.testcaseid = t.testcaseid AND
                            r.judgingid = %i )
                            WHERE t.probid = %s ORDER BY rank',
                           $lastjud['judgingid'], $submdata['probid']);
    }
}

$judging_ended = !empty($jud['endtime']);
list($tclist, $sum_runtime, $max_runtime) = display_runinfo($runs, $judging_ended);
$tclist = "<tr><td>testcase runs:</td><td>" . $tclist . "</td></tr>\n";

if ($lastjud !== null) {
    $lastjudging_ended = !empty($lastjud['endtime']);
    list($lasttclist, $sum_lastruntime, $max_lastruntime) = display_runinfo($lastruns, $lastjudging_ended);
    $lasttclist = "<tr class=\"lasttcruns\"><td><a href=\"submission.php?id=$lastsubmitid\">s$lastsubmitid</a> runs:</td><td>" .
            $lasttclist . "</td></tr>\n";
}

$state = '';
if (isset($jud['rejudgingid'])) {
    $reason = $DB->q('VALUE SELECT reason FROM rejudging WHERE rejudgingid=%i', $jud['rejudgingid']);
    $state = ' (rejudging <a href="rejudging.php?id=' .
         urlencode($jud['rejudgingid']) . '">r' .
         specialchars($jud['rejudgingid']) .
         '</a>, reason: ' .
         specialchars($reason) . ')';
} elseif ($jud['valid'] != 1) {
    $state = ' (INVALID)';
}

echo rejudgeForm('submission', $id) . "<br /><br />\n\n";

echo "<h2 style=\"display:inline;\">Judging j" . (int)$jud['judgingid'] .  $state .
    "</h2>\n\n&nbsp;";
if (!$jud['verified']) {
    echo addForm($pagename . '?id=' . urlencode($id) . '&amp;jid=' . urlencode($jid));

    if (!empty($jud['jury_member'])) {
        echo ' (claimed by ' . specialchars($jud['jury_member']) . ') ';
        echo addHidden('forceclaim', '1');
    }
    if ($jury_member == @$jud['jury_member']) {
        echo addSubmit('unclaim', 'unclaim');
    } else {
        echo addSubmit('claim', 'claim');
    }
    echo addEndForm();
}
echo "<br /><br />\n\n";

echo 'Result: ' . printresult($jud['result'], $jud['valid']) . ($lastjud === null ? '' :
    '<span class="lastresult"> (<a href="submission.php?id=' . $lastsubmitid . '">s' . $lastsubmitid. '</a>: '
    . @$lastjud['result'] . ')</span>') . ', ' .
    'Judgehost: <a href="judgehost.php?id=' . urlencode($jud['judgehost']) . '">' .
    printhost($jud['judgehost']) . '</a>, ';

// Time (start, end, used)
echo "<span class=\"judgetime\">Judging started: " . printtime($jud['starttime'], '%H:%M:%S');

if ($judging_ended) {
    echo ', finished in '.
            printtimediff($jud['starttime'], $jud['endtime']) . ' s';
} elseif ($jud['valid'] || isset($jud['rejudgingid'])) {
    echo ' [still judging - busy ' . printtimediff($jud['starttime']) . ']';
} else {
    echo ' [aborted]';
}


echo "</span>\n";

if (@$jud['result']!=='compiler-error') {
    echo ", max/sum runtime: " . sprintf('%.2f/%.2fs', $max_runtime, $sum_runtime);
    if (isset($max_lastruntime)) {
        echo " <span class=\"lastruntime\">(<a href=\"submission.php?id=$lastsubmitid\">s$lastsubmitid</a>: "
            . sprintf('%.2f/%.2fs', $max_lastruntime, $sum_lastruntime) .
            ")</span>\n";
    }

    echo "<table>\n$tclist";
    if ($lastjud !== null) {
        echo $lasttclist;
    }
    echo "</table>\n";
}

// Show JS toggle of previous submission results.
if ($lastjud!==null) {
    echo "<span class=\"testcases_prev\">" .
         "<a href=\"javascript:togglelastruns();\">show/hide</a> results of previous " .
         "<a href=\"submission.php?id=$lastsubmitid\">submission s$lastsubmitid</a>" .
         (empty($lastjud['verify_comment']) ? '' :
           "<span class=\"prevsubmit\"> (verify comment: '" .
           $lastjud['verify_comment'] . "')</span>") . "</span>";
}

// Display following data only when the judging result is known.
// Note that the judging may still not be finished yet when lazy
// evaluation is off.
if (!empty($jud['result'])) {

    // We cannot revert a verification of a valid judging when
    // verification_required is set.
    $verification_required = dbconfig_get('verification_required', 0);
    $verify_change_allowed = !($verification_required && $jud['verified'] && $jud['valid']);
    if ($verify_change_allowed) {
        $val = ! $jud['verified'];

        echo addForm('verify.php') .
            addHidden('id', $jud['judgingid']) .
            addHidden('val', $val) .
            addHidden('redirect', @$_SERVER['HTTP_REFERER']);
    }

    // Display verification data: verified, by whom, and comment.
    echo "<p>Verified: <strong>" . printyn($jud['verified']) . "</strong>";
    if ($jud['verified'] && ! empty($jud['jury_member'])) {
        echo ", by " . specialchars($jud['jury_member']);
        if (!empty($jud['verify_comment'])) {
            echo ' with comment "'.specialchars($jud['verify_comment']).'"';
        }
    }

    if ($verify_change_allowed) {
        echo '; ' . addSubmit(($val ? '' : 'un') . 'mark verified', 'verify');

        if ($val) {
            echo ' with comment ' . addInput('comment', '', 25);
        }

        if ($val && defined('ICAT_URL')) {
            $url = ICAT_URL.'/insert_entry.php';
            echo addInputField(
                'button',
                'post_icat',
                'post to iCAT',
                " onclick=\"postVerifyCommentToICAT(".
                "'$url','$username','".
                $submdata['teamid']."','" .
                $submdata['probshortname']."','".
                $submdata['externalid']."');".
                " alert('Comment posted to iCAT.');\""
            );
        }

        echo "</p>" . addEndForm();
    } else {
        echo "</p>\n";
    }

    if (!empty($submdata['externalresult'])
        && $jud['result'] !== $submdata['externalresult']
        && defined('EXT_CCS_URL')) {
        echo msgbox(
            'results differ',
            'This submission was judged as ' .
            '<a href="' . EXT_CCS_URL . urlencode($submdata['externalid']) . '" target="extCCS">' .
            printresult($submdata['externalresult']) . '</a>' .
            ' by the external CCS, but as ' .
            printresult($jud['result']) . ' by DOMjudge.'
        );
    }
} else { // judging does not have a result yet
    echo "<p><b>Judging is not ready yet!</b></p>\n";
}

?>
<script type="text/javascript">
<!--
togglelastruns();
// -->
</script>
<?php

display_compile_output(@$jud['output_compile'], @$jud['result']!=='compiler-error');

// If compilation is not finished yet or failed, there's no more info to show, so stop here
if (@$jud['output_compile'] === null || @$jud['result']=='compiler-error') {
    require(LIBWWWDIR . '/footer.php');
    exit(0);
}

foreach ($runs as $run) {
    if ($run['runresult'] == 'correct') {
        echo "<div class=\"run_correct\">";
    }
    echo "<h4 id=\"run-$run[rank]\">Run $run[rank]</h4>\n\n";

    if ($run['runresult']===null) {
        echo "<p class=\"nodata\">" .
            ($jud['result'] === null ? 'Run not started/finished yet.' : 'Run not used for final result.') .
            "</p>\n";
        continue;
    }

    $timelimit_str = '';
    if ($run['runresult']=='timelimit') {
        if (preg_match('/timelimit exceeded.*hard (wall|cpu) time/', $run['output_system'])) {
            $timelimit_str = '<b>(terminated)</b>';
        } else {
            $timelimit_str = '<b>(finished late)</b>';
        }
    }
    echo "<table>\n<tr><td>";
    echo "<table>\n" .
        "<tr><td>Description:</td><td>" .
        descriptionExpand($run['description']) . "</td></tr>" .
        "<tr><td>Download: </td><td>" .
        "<a href=\"testcase.php?probid=" . specialchars($submdata['probid']) .
        "&amp;rank=" . $run['rank'] . "&amp;fetch=input\">Input</a> / " .
        "<a href=\"testcase.php?probid=" . specialchars($submdata['probid']) .
        "&amp;rank=" . $run['rank'] . "&amp;fetch=output\">Reference Output</a> / " .
        "<a href=\"team_output.php?runid=" . $run['runid'] . "&amp;cid=" .
        $submdata['cid'] . "\">Team Output</a></td></tr>" .
        "<tr><td>Runtime:</td><td>$run[runtime] sec $timelimit_str</td></tr>" .
        "<tr><td>Result: </td><td><span class=\"sol sol_" .
        ($run['runresult']=='correct' ? '' : 'in') .
        "correct\">$run[runresult]</span></td></tr>" .
        "</table>\n\n";
    echo "</td><td>";
    if (isset($run['image_thumb'])) {
        $imgurl = "./testcase.php?probid=" .  urlencode($submdata['probid']) .
            "&amp;rank=" . $run['rank'] . "&amp;fetch=image";
        echo "<a href=\"$imgurl\">";
        echo '<img src="data:image/' . $run['image_type'] . ';base64,' .
            base64_encode($run['image_thumb']) . '"/>';
        echo "</a>";
    }
    echo "</td></tr></table>\n\n";

    echo "<h5>Diff output</h5>\n";
    if (strlen(@$run['output_diff']) > 0) {
        echo "<pre class=\"output_text\">";
        echo parseRunDiff($run['output_diff']);
        echo "</pre>\n\n";
    } else {
        echo "<p class=\"nodata\">There was no diff output.</p>\n";
    }

    if ($run['runresult'] !== 'correct') {
        // TODO: can be improved using diffposition.txt
        // FIXME: only show when diffposition.txt is set?
        // FIXME: cut off after XXX lines
        $lines_team = preg_split('/\n/', trim($run['output_run']));
        $lines_ref  = preg_split('/\n/', trim($run['output_reference']));

        $diffs = array();
        $firstErr = sizeof($lines_team) + 1;
        $lastErr  = -1;
        $n = min(sizeof($lines_team), sizeof($lines_ref));
        for ($i = 0; $i < $n; $i++) {
            $lcs = compute_lcsdiff($lines_team[$i], $lines_ref[$i]);
            if ($lcs[0] === true) {
                $firstErr = min($firstErr, $i);
                $lastErr  = max($lastErr, $i);
            }
            $diffs[] = $lcs[1];
        }
        $contextLines = 5;
        $firstErr -= $contextLines;
        $lastErr  += $contextLines;
        $firstErr = max(0, $firstErr);
        $lastErr  = min(sizeof($diffs)-1, $lastErr);
        echo "<br/>\n<table class=\"lcsdiff output_text\">\n";
        if ($firstErr > 0) {
            echo "<tr><td class=\"linenr\">[...]</td><td/></tr>\n";
        }
        for ($i = $firstErr; $i <= $lastErr; $i++) {
            echo "<tr><td class=\"linenr\">" . ($i + 1) . "</td><td>" . $diffs[$i] . "</td></tr>";
        }
        if ($lastErr < sizeof($diffs) - 1) {
            echo "<tr><td class=\"linenr\">[...]</td><td/></tr>\n";
        }
        echo "</table>\n";
    }

    echo "<h5>Program output</h5>\n";
    if (strlen(@$run['output_run']) > 0) {
        echo "<pre class=\"output_text\">".
            specialchars($run['output_run'])."</pre>\n\n";
    } else {
        echo "<p class=\"nodata\">There was no program output.</p>\n";
    }

    echo "<h5>Program error output</h5>\n";
    if (strlen(@$run['output_error']) > 0) {
        echo "<pre class=\"output_text\">".
            specialchars($run['output_error'])."</pre>\n\n";
    } else {
        echo "<p class=\"nodata\">There was no stderr output.</p>\n";
    }

    echo "<h5>Judging system output (info/debug/errors)</h5>\n";
    if (strlen(@$run['output_system']) > 0) {
        echo "<pre class=\"output_text\">".
            specialchars($run['output_system'])."</pre>\n\n";
    } else {
        echo "<p class=\"nodata\">There was no judging system output.</p>\n";
    }

    if ($run['runresult'] == 'correct') {
        echo "</div>";
    }
}

?>
<script type="text/javascript">
    function display_correctruns(show) {
        elements = document.getElementsByClassName('run_correct');
        for (i = 0; i < elements.length; i++) {
            elements[i].style.display = show ? 'block' : 'none';
        }
    }
    display_correctruns(false);
</script>
<?php

// We're done!

require(LIBWWWDIR . '/footer.php');
