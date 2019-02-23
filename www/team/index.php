<?php declare(strict_types=1);
/**
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = specialchars($teamdata['name']);
require(LIBWWWDIR . '/header.php');

// Don't use HTTP meta refresh, but javascript: otherwise we cannot FIXME still relevant?
$refreshtime = 30;

$submitted = @$_GET['submitted'];

$fdata = calcFreezeData($cdata);
$langdata = $DB->q('KEYTABLE SELECT langid AS ARRAYKEY, name, extensions, require_entry_point, entry_point_description
                    FROM language WHERE allow_submit = 1');

echo "<script type=\"text/javascript\">\n<!--\n";
echo "initReload(" . $refreshtime . ");\n";
echo "// -->\n</script>\n";

// Put overview of team submissions (like scoreboard)
if ($cdata == NULL) {
    echo "<h1 id=\"teamwelcome\">welcome team <span id=\"teamwelcometeam\">" .
        specialchars($teamdata['name']) . "</span>!</h1>\n\n" .
        "<h2 id=\"contestnotstarted\">There's no active contest for you (yet).</h2>\n\n";
    require(LIBWWWDIR . '/footer.php');
    return;
} else {
    putTeamRow($cdata, array($teamid));
}

if (!checkrole('jury') && !$fdata['started']) {
    // No need to display anything else for non-jury teams at this point.
    require(LIBWWWDIR . '/footer.php');
    return;
}

if ($submitted):
?>

<div class="mt-4 alert alert-success alert-dismissible show" role="alert">
  <a href="./" class="close" aria-label="Close">
    <span aria-hidden="true">&times;</span>
  </a>
  <strong>Submission done!</strong> Watch for the verdict in the list below.
</div>

<?php
endif;
?>

<div class="row">
<div class="col">
<h3 class="teamoverview">Submissions</h3>

<?php

$restrictions = array( 'teamid' => $teamid );
putSubmissions(array($cdata['cid'] => $cdata), $restrictions, null, $submitted);
?>
</div>
<div class="col">
<?php

$requests = $DB->q('SELECT c.*, cp.shortname, t.name AS toname, f.name AS fromname
                    FROM clarification c
                    LEFT JOIN problem p USING(probid)
                    LEFT JOIN contestproblem cp USING (probid, cid)
                    LEFT JOIN team t ON (t.teamid = c.recipient)
                    LEFT JOIN team f ON (f.teamid = c.sender)
                    WHERE c.cid = %i AND c.sender = %i
                    ORDER BY submittime DESC, clarid DESC', $cid, $teamid);

$clarifications = $DB->q('SELECT c.*, cp.shortname, t.name AS toname, f.name AS fromname,
                          u.mesgid AS unread
                          FROM clarification c
                          LEFT JOIN problem p USING (probid)
                          LEFT JOIN contestproblem cp USING (probid, cid)
                          LEFT JOIN team t ON (t.teamid = c.recipient)
                          LEFT JOIN team f ON (f.teamid = c.sender)
                          LEFT JOIN team_unread u ON (c.clarid=u.mesgid AND u.teamid = %i)
                          WHERE c.cid = %i AND c.sender IS NULL
                          AND ( c.recipient IS NULL OR c.recipient = %i )
                          ORDER BY c.submittime DESC, c.clarid DESC',
                         $teamid, $cid, $teamid);

echo "<h3 class=\"teamoverview\">Clarifications</h3>\n";

# FIXME: column width and wrapping/shortening of clarification text
if ($clarifications->count() == 0) {
    echo "<p class=\"nodata\">No clarifications.</p>\n\n";
} else {
    putClarificationList($clarifications, (int)$teamid);
}

echo "<h3 class=\"teamoverview\">Clarification Requests</h3>\n";

if ($requests->count() == 0) {
    echo "<p class=\"nodata\">No clarification requests.</p>\n\n";
} else {
    putClarificationList($requests, (int)$teamid);
}

?>
<div class="m-1"><a href="clarification.php" class="btn btn-secondary btn-sm">request clarification</a></div>

</div>

</div>
<?php
require(LIBWWWDIR . '/footer.php');
