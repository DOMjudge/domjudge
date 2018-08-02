<?php
/**
 * View the details of a specific rejudging
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$id = getRequestID();

$viewtypes = array(0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => "diff", 4 => 'all');

$view = 1; // default view == unverified

if (isset($_REQUEST['view'])) {
    // did someone press any of the view buttons?
    foreach ($viewtypes as $i => $name) {
        if (isset($_REQUEST['view'][$i])) {
            $view = $i;
        }
    }
}

if (!isset($_REQUEST['apply'])) {
    $refresh = array(
        'after' => 15,
        'url' => 'rejudging.php?id=' . urlencode($id) . '&' .
            urlencode('view[' . $view . ']') . '=' . urlencode($viewtypes[$view]) .
            (isset($_REQUEST['old_verdict']) ? '&old_verdict=' . urlencode($_REQUEST['old_verdict']) : '') .
            (isset($_REQUEST['new_verdict']) ? '&new_verdict=' . urlencode($_REQUEST['new_verdict']) : ''),
    );
}

$title = 'Rejudging r'.@$id;

require(LIBWWWDIR . '/header.php');

if (! $id) {
    error("Missing or invalid rejudging id");
}

$rejdata = $DB->q('TUPLE SELECT * FROM rejudging WHERE rejudgingid=%i', $id);

if (! $rejdata) {
    error("Missing rejudging data");
}

if (isset($_REQUEST['apply']) || isset($_REQUEST['cancel'])) {
    $request = isset($_REQUEST['apply']) ? 'apply' : 'cancel';

    $time_start = microtime(true);

    // no output buffering... we want to see what's going on real-time
    header('X-Accel-Buffering: no');
    echo "<br/><p>Applying rejudge may take some time, please be patient:</p>\n";
    while (ob_get_level()) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    // clear GET array because otherwise the eventlog subrequest will still include the rejudging id
    $_GET = array();
    echo "<p>\n";

    rejudging_finish($id, $request, $userdata['userid'], true);

    echo "\n</p>\n";

    // Start output buffering again to not crash the FallbackController
    ob_start();

    $time_end = microtime(true);

    echo "<p>Rejudging <a href=\"rejudging.php?id=" . urlencode($id) .
        "\">r$id</a> ".($request=='apply' ? 'applied' : 'canceled').
        " in ".sprintf('%.2f', $time_end - $time_start)." seconds.</p>\n\n";

    require(LIBWWWDIR . '/footer.php');
    return;
}

$todo = $DB->q('VALUE SELECT COUNT(*) FROM submission
                WHERE rejudgingid=%i', $id);
$done = $DB->q('VALUE SELECT COUNT(*) FROM judging
                WHERE rejudgingid=%i AND endtime IS NOT NULL', $id);
$todo -= $done;

$userdata = $DB->q('KEYVALUETABLE SELECT userid, name FROM user
                    WHERE userid=%i OR userid=%i',
                   $rejdata['userid_start'], @$rejdata['userid_finish']);

echo '<br/><h1 style="display:inline;">Rejudging r' . $id .
    ($rejdata['valid'] ? '' : ' (canceled)') . "</h1>\n\n";

echo "<table>\n";
echo "<tr><td>Reason:</td><td>";
if (empty($rejdata['reason'])) {
    echo '<span class="nodata">none</span>';
} else {
    echo specialchars($rejdata['reason']);
}
echo "</td></tr>\n";
foreach (array('userid_start' => 'Issued by',
               'userid_finish' => ($rejdata['valid'] ? 'Accepted' : 'Canceled') . ' by')
          as $user => $msg) {
    $time = $user == 'userid_start' ? 'starttime' : 'endtime';
    if (isset($rejdata[$time])) {
        echo "<tr><td>$msg:</td><td>" .
            (isset($rejdata[$user]) ?
             '<a href="user.php?id=' . urlencode($rejdata[$user]) . '">' .
             specialchars($userdata[$rejdata[$user]]) . '</a>' :
             '<span class="nodata">unknown</span>') .
            "</td></tr>\n";
    }
}
foreach (array('starttime' => 'Start time', 'endtime' => 'Apply time') as $time => $msg) {
    echo "<tr><td>$msg:</td><td>";
    if (isset($rejdata[$time])) {
        echo '<span title="' . printtime($rejdata[$time], '%Y-%m-%d %H:%M:%S (%Z)') . '">' .
            printtime($rejdata[$time]) . '</span>';
    } else {
        echo '<span class="nodata">-</span>';
    }
    echo "</td></tr>\n";
}
if ($todo > 0) {
    echo "<tr><td>Queued:</td><td>$todo unfinished judgings</td>\n";
}
echo "</table>\n\n";

if (!isset($rejdata['endtime'])) {
    echo addForm($pagename . '?id=' . urlencode($id))
        . addSubmit('cancel rejudging', 'cancel')
        . addEndForm();

    if ($todo == 0) {
        echo addForm($pagename . '?id=' . urlencode($id))
            . addSubmit('apply rejudging', 'apply')
            . addEndForm();
    }
}

$verdicts = $VERDICTS;

$orig_verdicts = $DB->q('KEYVALUETABLE SELECT submitid, result
                         FROM judging
                         WHERE judgingid IN
                         ( SELECT prevjudgingid
                           FROM judging
                           WHERE rejudgingid=%i AND endtime IS NOT NULL
                         )', $id);
$new_verdicts = $DB->q('KEYVALUETABLE SELECT submitid, result
                        FROM judging
                        WHERE rejudgingid=%i AND endtime IS NOT NULL', $id);

$table = array();
$used  = array();

// pre-fill $table to get a consistent ordering
foreach ($verdicts as $verdict => $abbrev) {
    foreach ($verdicts as $verdict2 => $abbrev2) {
        $table[$verdict][$verdict2] = array();
    }
}

// add unknown verdicts
// - to the end of $table (rows *and* columns)
// - as their own abbreviation to $verdicts
function addVerdict($unknownVerdict, &$verdicts, &$table)
{
    // add column to existing rows
    foreach ($verdicts as $verdict => $abbrev) {
        $table[$verdict][$unknownVerdict] = array();
    }
    // add verdict to known verdicts
    $verdicts[$unknownVerdict] = $unknownVerdict;
    // add row
    $table[$unknownVerdict] = array();
    foreach ($verdicts as $verdict => $abbrev) {
        $table[$unknownVerdict][$verdict] = array();
    }
}

// generates a list with links to submissions
foreach ($new_verdicts as $submitid => $new_verdict) {
    $orig_verdict = $orig_verdicts[$submitid];

    // add verdicts to data structures if they are unknown up to now
    foreach (array($new_verdict, $orig_verdict) as $verdict) {
        if (!array_key_exists($verdict, $verdicts)) {
            addVerdict($verdict, $verdicts, $table);
        }
    }

    // mark them as used, so we can filter out unused cols/rows later
    $used[$orig_verdict] = true;
    $used[$new_verdict]  = true;

    // append submitid to list of orig->new verdicts
    $table[$orig_verdict][$new_verdict][] = $submitid;
}

echo "<h2>Overview of changes</h2>\n";
echo '<table class="rejudgetable">' . "\n";
echo "<tr><th title=\"old vs. new verdicts\">-\+</th>"; // first column are table headers as well
// write table header
foreach ($verdicts as $verdict => $abbrev) {
    if (!isset($used[$verdict])) {
        // filter out unused cols
        continue;
    }
    echo "<th title=\"$verdict (new)\">$abbrev</th>\n";
}
echo "</tr>";

foreach ($table as $orig_verdict => $changed_verdicts) {
    if (!isset($used[$orig_verdict])) {
        // filter out unused rows
        continue;
    }

    $orig_verdict_abbrev = $verdicts[$orig_verdict];
    echo "<tr><th title=\"$orig_verdict (old)\">$orig_verdict_abbrev</th>";
    foreach ($changed_verdicts as $new_verdict => $submitids) {
        if (!isset($used[$new_verdict])) {
            // filter out unused cols
            continue;
        }
        $new_verdict_abbrev = $verdicts[$new_verdict];
        $cnt = sizeof($submitids);
        $link = '';
        if ($orig_verdict == $new_verdict) {
            $class = "identical";
        } elseif ($cnt == 0) {
            $class = "zero";
        } else {
            // this case is the interesting one
            $class = "changed";
            $link = '<a href="rejudging.php?id=' . urlencode($id) .
                '&amp;' . urlencode('view[4]=all') .
                '&amp;old_verdict=' . urlencode($orig_verdict) .
                '&amp;new_verdict=' . urlencode($new_verdict) . '">';
        }
        echo "<td class=\"$class\">$link$cnt" .  (empty($link) ? '' : '</a>') . "</td>\n";
    }
    echo "</tr>\n";
}
echo "</table>\n";

echo "<h2>Details</h2>\n";

$restrictions = array('rejudgingid' => $id);
if ($viewtypes[$view] == 'unverified') {
    $restrictions['verified'] = 0;
}
if ($viewtypes[$view] == 'unjudged') {
    $restrictions['judged'] = 0;
}
if ($viewtypes[$view] == 'diff') {
    $restrictions['rejudgingdiff'] = 1;
}
if (isset($_REQUEST['old_verdict']) && $_REQUEST['old_verdict'] != 'all') {
    $restrictions['old_result'] = $_REQUEST['old_verdict'];
}
if (isset($_REQUEST['new_verdict']) && $_REQUEST['new_verdict'] != 'all') {
    $restrictions['result'] = $_REQUEST['new_verdict'];
}

echo "<p>Show submissions:</p>\n" .
    addForm($pagename, 'get') .
    addHidden('id', $id);
for ($i=0; $i<count($viewtypes); ++$i) {
    echo addSubmit($viewtypes[$i], 'view['.$i.']', null, ($view != $i));
}
if (isset($_REQUEST['old_verdict'])) {
    echo addHidden('old_verdict', $_REQUEST['old_verdict']);
}
if (isset($_REQUEST['new_verdict'])) {
    echo addHidden('new_verdict', $_REQUEST['new_verdict']);
}
echo addEndForm() . "<br />\n";

echo addForm($pagename, 'get') .
    addHidden('id', $id) .
    addHidden("view[$view]", $viewtypes[$view]);
$verdicts = array_keys($verdicts);
array_unshift($verdicts, 'all');
echo "old verdict: " .
    addSelect(
        'old_verdict',
        $verdicts,
        (isset($_REQUEST['old_verdict']) ? $_REQUEST['old_verdict'] : 'all')
    );
echo ", new verdict: " .
    addSelect(
        'new_verdict',
        $verdicts,
        (isset($_REQUEST['new_verdict']) ? $_REQUEST['new_verdict'] : 'all')
    );
echo addSubmit('filter') . addEndForm();

echo addForm($pagename, 'get') .
    addHidden('id', $id) .
    addHidden("view[$view]", $viewtypes[$view]) .
    addSubmit('clear') . addEndForm() . "<br /><br />\n";

$filtered = $DB->q('VALUE SELECT COUNT(s.submitid)
                    FROM submission s
                    LEFT JOIN judging j ON (s.submitid = j.submitid AND j.rejudgingid = %i)
                    WHERE s.cid NOT IN (%As) AND (s.rejudgingid = %i OR j.rejudgingid = %i)',
                   $id, $cids, $id, $id);

if ($filtered > 0) {
    echo "<p class=\"nodata\">$filtered submissions are not displayed " .
        "because they are not part of any active contest(s).</p>\n\n";
}

putSubmissions($cdatas, $restrictions);

require(LIBWWWDIR . '/footer.php');
