<?php
/**
 * Finalize the contest.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = "Finalize contest";

$id = getRequestID(true);

require(LIBWWWDIR . '/header.php');

echo "<h2>$title</h2>\n\n";

requireAdmin();

if (isset($_POST['cancel'])) {
    header('Location: contest.php?id=' . $id);
    exit;
}

$row = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $id);

// Contest may not be finalized when:
// - The contest is still running (i.e., the contest time is not over).
// - There are unjudged runs.
// - There are runs judged as Judging System Error. (NB: does not happen in DOMjudge)
// - There are unanswered clarifications.

$blockers = array();
if (difftime($row['endtime'], now()) > 0) {
    $blockers[] = 'Contest not ended yet (will end at ' . printtime($row['endtime'], '%Y-%m-%d %H:%M:%S (%Z)') . ')';
}
$subms = $DB->q('COLUMN SELECT s.submitid FROM submission s LEFT JOIN judging j ON (s.submitid = j.submitid AND j.valid=1)
                 WHERE s.cid = %i AND s.valid=1 AND result IS NULL ORDER BY submitid', $id);
if (count($subms) > 0) {
    $blockers[] = 'Unjudged submissions found: s' . implode(', s', $subms);
}

$clars = $DB->q('COLUMN SELECT clarid FROM clarification WHERE cid = %i AND answered = 0', $id);
if (count($clars) > 0) {
    $blockers[] = 'Unanswered clarifications found: ' . implode(', ', $clars);
}

if (count($blockers) > 0) {
    echo "<p>Contest can not yet be finalized:</p>\n <ul>";
    foreach ($blockers as $blocker) {
        echo "  <li>" . specialchars($blocker) . "</li>\n";
    }
    echo "</ul>\n\n";

    require(LIBWWWDIR . '/footer.php');
    exit;
}

// OK, contest can be finalized

if (isset($_POST['cmd']) && $_POST['cmd'] == 'finalize') {
    $DB->q('UPDATE contest SET
            finalizetime = %f, finalizecomment = %s, b = %i
            WHERE cid = %i', now(), $_POST['finalizecomment'], $_POST['b'], $id);
    auditlog('contest', $id, 'finalized', $_POST['finalizecomment']);

    header('Location: contest.php?id=' . $id);

    exit;
}

echo addForm('');
echo "<table>\n";
echo "<tr><td>Contest ID:</td><td>";
echo addHidden('id', $id) . 'c' . $id . "</td></tr>\n";
echo "<tr><td>Contest name:</td><td>";
echo specialchars($row['name']) . "</td></tr>\n";

echo "<tr><td>Started:</td><td>";
echo printtime($row['starttime']) .
     ', ended ' . printtime($row['endtime']) .
    "</td></tr>\n";
echo "<tr><td><label for=\"b\">B:</label></td>" .
     "<td>" . addInput('b', (int)@$row['b'], 4, 10) .
     "</td></tr>\n";
echo "<tr><td><label for=\"finalizecomment\">Comment:</label></td>" .
     "<td>" . addTextArea('finalizecomment', (empty($row['finalizecomment'])?'Finalized by: '.$userdata['name']:$row['finalizecomment'])) .
     "</td></tr>\n";

echo "</table>\n\n";

echo addHidden('cmd', 'finalize') .
     addSubmit('Finalize') .
     addSubmit('Cancel', 'cancel') .
     addEndForm();

require(LIBWWWDIR . '/footer.php');
