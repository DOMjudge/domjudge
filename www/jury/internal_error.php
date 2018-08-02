<?php
/**
 * View the details of a specific internal error
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$id = getRequestID();

$refresh = array(
    'after' => 15,
    'url' => 'internal_error.php?id=' . urlencode($id),
);

$title = 'Internal Error e'.@$id;

if (! $id) {
    error("Missing or invalid internal error id");
}

$edata = $DB->q('TUPLE SELECT * FROM internal_error WHERE errorid=%i', $id);

if (! $edata) {
    error("Missing internal error data for e" . $id);
}

$disabled = dj_json_decode($edata['disabled']);

if (isset($_REQUEST['ignore']) || isset($_REQUEST['resolve'])) {
    if (isset($_REQUEST['ignore'])) {
        $status = "ignored";
    }
    if (isset($_REQUEST['resolve'])) {
        $status = "resolved";
    }
    $DB->q('UPDATE internal_error SET status=%s WHERE errorid=%i', $status, $id);
    if ($status == 'resolved') {
        set_internal_error($disabled, $edata['cid'], 1);
    }
    auditlog('internal_error', $id, 'internal error: ' + $status, '');
    header('Location: internal_error.php?id='.urlencode($id));
}

require(LIBWWWDIR . '/header.php');

echo '<br/><h1>Internal Error e' . $id . "</h1>\n\n";

echo "<table>\n";
echo "<tr><td>Description:</td><td>";
if (empty($edata['description'])) {
    echo '<span class="nodata">none</span>';
} else {
    echo specialchars($edata['description']);
}
echo "</td></tr>\n";

echo "<tr><td>Time:</td><td>"
    . printtime($edata['time'], '%F %T')
    . "</td></tr>\n";
if (isset($edata['judgingid'])) {
    echo "<tr><td>Related Judging:</td><td>"
        . "<a href=\"submission.php?jid=" . urlencode($edata['judgingid']) . "\">j"
        . specialchars($edata['judgingid']) . "</a>"
        . "</td></tr>\n";
}
if (isset($edata['cid'])) {
    echo "<tr><td>Related Contest:</td><td>"
        . "<a href=\"contest.php?id=" . urlencode($edata['cid']) . "\">c"
        . specialchars($edata['cid']) . "</a>"
        . "</td></tr>\n";
}

$kind = $disabled['kind'];

echo "<tr><td>Affected " . specialchars($kind) . ":</td><td>";
switch ($kind) {
    case 'problem':
        $probid = $disabled['probid'];
        $shortname = $DB->q('VALUE SELECT shortname FROM contestproblem WHERE probid=%i AND cid=%i',
                            $probid, $edata['cid']);
        $name = $DB->q('VALUE SELECT name FROM problem WHERE probid=%i', $probid);
        echo "<a href=\"problem.php?id=" . urlencode($probid) . "\">" . specialchars($shortname . " - " . $name) . "</a>";
        break;
    case 'judgehost':
        $judgehost = $disabled['hostname'];
        echo "<a href=\"judgehost.php?id=" . urlencode($judgehost) . "\">" . specialchars($judgehost) . "</a>";
        break;
    case 'language':
        $langid = $disabled['langid'];
        echo "<a href=\"language.php?id=" . urlencode($langid) . "\">" . specialchars($langid) . "</a>";
        break;
    default:
        // FIXME

}

echo "<tr><td>Judgehost log snippet:</td><td>";
echo "<pre class=\"output_text\">\n";
echo specialchars(base64_decode($edata['judgehostlog']));
echo "</pre></td></tr>\n</table>\n\n";


if ($edata['status'] == 'open') {
    echo addForm($pagename . '?id=' . urlencode($id))
        . addSubmit('ignore error', 'ignore')
        . addEndForm();

    echo addForm($pagename . '?id=' . urlencode($id))
        . addSubmit('mark as resolved and re-enable ' . specialchars($disabled['kind']), 'resolve')
        . addEndForm();
}

require(LIBWWWDIR . '/footer.php');
