<?php
/**
 * Show clarification thread and reply box.
 * When no id is given, show clarification request box.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();

if (isset($id)) {
    $req = $DB->q('MAYBETUPLE SELECT * FROM clarification
                   WHERE cid = %i AND clarid = %i', $cid, $id);
    if (! $req) {
        error("clarification $id not found");
    }
    if (! canViewClarification($teamid, $req)) {
        error("Permission denied");
    }
    $myrequest = ($req['sender'] == $teamid);

    $respid = empty($req['respid']) ? $id : $req['respid'];
}

// insert a request (if posted)
if (isset($_POST['submit']) && !empty($_POST['bodytext'])) {
    list($cid, $probid) = explode('-', $_POST['problem']);
    $category = null;
    $queue = null;
    if (!ctype_digit($probid)) {
        $category = $probid;
        $probid = null;
    } else {
        $queue = dbconfig_get('clar_default_problem_queue');
        if ($queue === "") {
            $queue = null;
        }
    }

    // Disallow problems that are not submittable or
    // before contest start.
    if (!problemVisible($probid)) {
        $probid = null;
    }

    $newid = $DB->q('RETURNID INSERT INTO clarification
                     (cid, submittime, sender, probid, category, queue, body)
                     VALUES (%i, %s, %i, %i, %s, %s, %s)',
                    $cid, now(), $teamid, $probid, $category, $queue, $_POST['bodytext']);

    eventlog('clarification', $newid, 'create', $cid);
    auditlog('clarification', $newid, 'added', null, null, $cid);

    // redirect back to the original location
    header('Location: ./');
    exit;
}

$title = 'Clarifications';
require(LIBWWWDIR . '/header.php');

if (isset($id)) {
    // display clarification thread
    if ($myrequest) {
        echo "<h1>Clarification Request</h1>\n\n";
    } else {
        echo "<h1>Clarification</h1>\n\n";
    }
    echo "<div class=\"container clarificationform\"><div class=\"card card-body\">";
    putClarification($respid, $teamid);

    echo '</div><div class="mt-3"><button class="btn btn-secondary btn-sm" data-toggle="collapse" data-target="#collapsereplyform" aria-expanded="false" aria-controls="collapsereplyform">reply to this clarification</button></div>';
    echo "</div>";

    echo '<div class="collapse mt-3 container clarificationform" id="collapsereplyform"><div class="card card-body">';
    putClarificationForm("clarification.php", $id, $cid);
    echo '</div></div>';
} else {
    // display a clarification request send box
    echo "<h1>Send Clarification Request</h1>\n\n";
    putClarificationForm("clarification.php", null, $cid);
}

require(LIBWWWDIR . '/footer.php');
