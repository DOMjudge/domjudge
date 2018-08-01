<?php

/**
 * automatically verifies judgings that have a unique result
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Judging Verifier';
require(LIBWWWDIR . '/header.php');

requireAdmin();

// Turn off output buffering, to see the page as it (slowly) loads.
ob_end_flush();

$verify_multiple = isset($_REQUEST['verify_multiple']);

?>

<h1>Judging Verifier</h1>

<?php

$nchecked = 0;
$nunchecked = 0;

$unexpected = array();
$multiple = array();
$verified = array();

$nomatch = array();
$earlier = array();

$verifier = 'auto-verifier';

$res = null;
if (!empty($cids)) {
    $res = $DB->q("SELECT s.*, j.judgingid, j.result, j.verified, j.jury_member
                   FROM submission s
                   LEFT JOIN judging j ON (s.submitid = j.submitid AND j.valid=1)
                   WHERE s.cid IN (%Ai) AND j.result IS NOT NULL", $cids);
}

$section = 0;

function flushresults($header, $results, $collapse = false)
{
    global $section;

    $section++;

    echo "<h2><a class=\"collapse\" href=\"javascript:collapse($section)\">" .
        "$header</a></h2>\n\n<ul class=\"details\" id=\"detail$section\">\n";
    foreach ($results as $row) {
        echo "<li>$row</li>\n";
    }
    echo "</ul>\n\n";

    if ($collapse) {
        echo "<script type=\"text/javascript\">
<!--
    collapse($section);
// -->
</script>\n\n";
    }

    flush();
}

while (!empty($cids) && $row = $res->next()) {
    $sid = $row['submitid'];
    $results = $row['expected_results'];

    if ($results !== null && $row['verified']==0) {
        $results = dj_json_decode($results);
        $nchecked++;

        $result = mb_strtoupper($row['result']);

        if (!in_array($result, $results)) {
            $unexpected[] = "<a href=\"submission.php?id=" . $sid
                . "\">s$sid</a> has unexpected result '$result', "
                . "should be one of: " . implode(', ', $results);
        } elseif (count($results)>1) {
            if ($verify_multiple) {
                // Judging result is as expected, set judging to verified:
                $DB->q('UPDATE judging SET verified = 1, jury_member = %s
                        WHERE judgingid = %i', $verifier, $row['judgingid']);
                $multiple[] = "<a href=\"submission.php?id=" . $sid
                    . "\">s$sid</a> verified as $result, "
                    . "out of multiple possible outcomes ("
                    . implode(', ', $results) . ")";
            } else {
                $multiple[] = "<a href=\"submission.php?id=" . $sid
                    . "\">s$sid</a> is judged as $result, "
                    . "but has multiple possible outcomes ("
                    . implode(', ', $results) . ")";
            }
        } else {
            // Judging result is as expected, set judging to verified:
            $DB->q('UPDATE judging SET verified = 1, jury_member = %s
                    WHERE judgingid = %i', $verifier, $row['judgingid']);
            $verified[] = "<a href=\"submission.php?id=" . $sid .
                "\">s$sid</a> verified as '$result'";
        }
    } else {
        $nunchecked++;

        if ($results===null) {
            $nomatch[] = "expected results unknown in " .
                "<a href=\"submission.php?id=" . $sid .
                "\">s$sid</a>, leaving submission unchecked";
        } else {
            $earlier[] = "<a href=\"submission.php?id=" . $sid .
                "\">s$sid</a> already verified earlier";
        }
    }
}

echo "$nchecked submissions checked: " .
    count($unexpected) . " unexpected results, " .
    count($multiple) . ($verify_multiple ?
                         " automatically verified (multiple outcomes), " :
                         " to check manually, ").
    count($verified) . " automatically verified<br/>\n";
echo "$nunchecked submissions not checked: " .
    count($earlier) . " verified earlier, " .
    count($nomatch) . " without magic string<br/>\n";

if (count($unexpected)) {
    flushresults("Unexpected results", $unexpected);
}
if (count($multiple)) {
    if ($verify_multiple) {
        flushresults("Automatically verified (multiple outcomes)", $multiple, true);
    } else {
        flushresults("Check manually", $multiple);
        echo "<div class=\"details\" id=\"detail$section\">\n" .
            addForm($pagename) . "<p>Verify all multiple outcome submissions: " .
            addHidden('verify_multiple', '1') . addSubmit('verify') .
            addEndForm() . "</p>\n</div>\n\n";
    }
}
if (count($verified)) {
    flushresults("Automatically verified", $verified, true);
}
if (count($earlier)) {
    flushresults("Verified earlier", $earlier, true);
}
if (count($nomatch)) {
    flushresults("Without magic string", $nomatch, true);
}

require(LIBWWWDIR . '/footer.php');
