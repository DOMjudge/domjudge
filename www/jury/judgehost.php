<?php
/**
 * View judgehost details
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID(false);
if (empty($id)) {
    error("Missing judge hostname");
}

if (isset($_REQUEST['cmd']) &&
    ($_REQUEST['cmd'] == 'activate' || $_REQUEST['cmd'] == 'deactivate')) {
    requireAdmin();

    $DB->q('UPDATE judgehost SET active = %i WHERE hostname = %s',
           ($_REQUEST['cmd'] == 'activate' ? 1 : 0), $id);
    auditlog('judgehost', $id, 'marked ' . ($_REQUEST['cmd']=='activate'?'active':'inactive'));

    // the request came from the overview page
    if (isset($_GET['cmd'])) {
        header("Location: judgehosts.php");
        exit;
    }
}

$data = $DB->q('TUPLE SELECT judgehost.*, r.name AS restrictionname
                FROM judgehost
                LEFT JOIN judgehost_restriction r USING (restrictionid)
                WHERE hostname = %s', $id);

// get the judgings for a specific key and value pair
// select only specific fields to avoid retrieving large blobs
$cids = getCurContests(false);
if (!empty($cids)) {
    $jdata = $DB->q('KEYTABLE SELECT judgingid AS ARRAYKEY, judgingid, submitid,
                     j.starttime, j.endtime, judgehost, result, verified,
                     j.valid, j.rejudgingid, r.valid AS rejudgevalid,
                     (j.endtime IS NULL AND j.valid=0 AND
                      (r.valid IS NULL OR r.valid=0)) AS aborted
                     FROM judging j
                     LEFT JOIN rejudging r USING(rejudgingid)
                     WHERE cid IN (%Ai) AND judgehost = %s
                     ORDER BY j.starttime DESC, judgingid DESC',
                    $cids, $data['hostname']);
}

$reltime = floor(difftime(now(), $data['polltime']));
$status = 'Undefined';
if ($reltime < dbconfig_get('judgehost_warning', 30)) {
    $status = "OK";
} elseif ($reltime < dbconfig_get('judgehost_critical', 120)) {
    $status = "Warning";
} else {
    $status = "Critical";
}

// KLUDGE: Add the following PHP functions to Twig, this should be
// fixed differently.
$twig_safe = array('is_safe' => array('html'));
$twig->addFunction(new Twig_SimpleFunction('delLink', 'delLink', $twig_safe));
$twig->addFunction(new Twig_SimpleFunction('rejudgeForm', 'rejudgeForm', $twig_safe));

renderPage(array(
    'title' => 'Judgehost '.specialchars($data['hostname']),
    'refresh' => array(
        'after' => 15,
        'url' => 'judgehost.php?id='.urlencode($id),
    ),
    'judgehost' => $data,
    'judgingdata' => $jdata,
    'id' => $id,
    'status' => $status,
    'is_admin' => IS_ADMIN,
));
