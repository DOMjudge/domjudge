<?php
/**
 * Gives a team the details of a judging of their submission: errors etc.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Submission details';
require(LIBWWWDIR . '/header.php');

$id = getRequestID();


// select also on teamid so we can only select our own submissions
$row = $DB->q('MAYBETUPLE SELECT p.probid, j.cid, cp.shortname, p.name AS probname, submittime,
               s.valid, l.name AS langname, result, output_compile, verified, judgingid
               FROM judging j
               LEFT JOIN submission s      USING (submitid)
               LEFT JOIN language   l      USING (langid)
               LEFT JOIN problem    p      ON (p.probid = s.probid)
               LEFT JOIN contestproblem cp ON (cp.probid = p.probid AND cp.cid = s.cid)
               WHERE j.submitid = %i AND teamid = %i AND j.valid = 1', $id, $teamid);

if (!$row || $row['submittime'] >= $cdata['endtime'] ||
    (dbconfig_get('verification_required', 0) && !$row['verified'])) {
    echo "<div class=\"alert alert-danger\">Submission not found for this team or not judged yet.</div>\n";
    require(LIBWWWDIR . '/footer.php');
    exit;
}

// update seen status when viewing submission
$DB->q("UPDATE judging j SET j.seen = 1 WHERE j.submitid = %i", $id);
?>

<h1>Submission details</h1>

<div class="container">
<?php if (! $row['valid']): ?>
<div class="alert alert-warning">This submission is being ignored. It is not used in determining your score.</div>
<?php endif; ?>

<div class="d-flex flex-row">
<div class="p-2">Problem: <b><span class="probid"><?=specialchars($row['shortname'])?></span> -
    <?=specialchars($row['probname'])?></b></div>
<div class="p-2">Submitted: <b><?=printtime($row['submittime'], null, $row['cid'])?></b></div>
<div class="p-2">Language: <b><?=specialchars($row['langname'])?></b></div>
<div class="p-2">Compilation:
<?php if ($row['result'] == 'compiler-error'): ?>
<span class="badge badge-danger">failed</span></div>
</div>
<?php else: ?>
<span class="badge badge-success">successful</span></div>
</div>
<div class="d-flex flex-row">
<div class="p-2">Run result: <?php echo printresult($row['result'], true)?></div>
</div>
<?php endif; ?>

<?php
$show_compile = dbconfig_get('show_compile', 2);

if (($show_compile == 2) ||
    ($show_compile == 1 && $row['result'] == 'compiler-error')) {
    echo "<hr />\n\n";

    echo "<h3>Compilation output</h3>\n\n";

    if (strlen(@$row['output_compile']) > 0) {
        echo "<pre class=\"output_text pre-scrollable\">\n".
            specialchars(@$row['output_compile'])."\n</pre>\n\n";
    } else {
        echo "<p class=\"nodata\">There were no compiler errors or warnings.</p>\n";
    }
}

$show_sample = dbconfig_get('show_sample_output', 0);

if ($show_sample && @$row['result']!='compiler-error') {
    $runs = $DB->q('TABLE SELECT r.*, t.rank, t.description FROM testcase t
                    LEFT JOIN judging_run r ON ( r.testcaseid = t.testcaseid AND
                                                 r.judgingid = %i )
                    WHERE t.probid = %i AND t.sample = 1 ORDER BY rank',
                   $row['judgingid'], $row['probid']);

    echo "<hr />\n\n";

    echo '<h3>Run(s) on the provided sample data</h3>';

    if (count($runs)==0) {
        echo "<p class=\"nodata\">No sample cases available.</p>\n";
    }

    foreach ($runs as $run) {
        echo "<h4 id=\"run-$run[rank]\">Run $run[rank]</h4>\n\n";
        if ($run['runresult']===null) {
            echo "<p class=\"nodata\">Run not finished yet.</p>\n";
            continue;
        }
        echo "<table>\n" .
            "<tr><td>Description:</td><td>" .
            specialchars($run['description']) . "</td></tr>" .
            "<tr><td>Runtime:</td><td>$run[runtime] sec</td></tr>" .
            "<tr><td>Result: </td><td><span class=\"sol sol_" .
            ($run['runresult']=='correct' ? '' : 'in') .
            "correct\">$run[runresult]</span></td></tr>" .
            "</table>\n\n";
        echo "<h5>Program output</h5>\n";
        if (@$run['output_run']) {
            echo "<pre class=\"output_text\">".
                specialchars($run['output_run'])."</pre>\n\n";
        } else {
            echo "<p class=\"nodata\">There was no program output.</p>\n";
        }
        echo "<h5>Diff output</h5>\n";
        if (@$run['output_diff']) {
            echo "<pre class=\"output_text\">";
            echo parseRunDiff($run['output_diff']);
            echo "</pre>\n\n";
        } else {
            echo "<p class=\"nodata\">There was no diff output.</p>\n";
        }
        echo "<h5>Error output (info/debug/errors)</h5>\n";
        if (@$run['output_error']) {
            echo "<pre class=\"output_text\">".
                specialchars($run['output_error'])."</pre>\n\n";
        } else {
            echo "<p class=\"nodata\">There was no stderr output.</p>\n";
        }
    }
}

echo "</div>\n\n";

require(LIBWWWDIR . '/footer.php');
