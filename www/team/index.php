<?php
/**
 * Part of the DOMjudge Programming Contest Jury System and licenced
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

if ($fdata['started'] || checkrole('jury')) {
    $probdata = $DB->q('TABLE SELECT probid, shortname, name FROM problem
                        INNER JOIN contestproblem USING (probid)
                        WHERE cid = %i AND allow_submit = 1
                        ORDER BY shortname', $cid);

    putgetMainExtension($langdata);

    echo "function getProbDescription(probid)\n{\n";
    echo "\tswitch(probid) {\n";
    foreach ($probdata as $probinfo) {
        echo "\t\tcase '" . specialchars($probinfo['shortname']) .
            "': return '" . specialchars($probinfo['name']) . "';\n";
    }
    echo "\t\tdefault: return '';\n\t}\n}\n\n";
}

echo "initReload(" . $refreshtime . ");\n";
echo "// -->\n</script>\n";

// Put overview of team submissions (like scoreboard)
putTeamRow($cdata, array($teamid));

//echo "<div id=\"submitlist\">\n";

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

/* submitform moved away
if ( ($fdata['started'] || checkrole('jury') )) {
    echo <<<HTML
<script type="text/javascript">
$(function() {
    var matches = location.hash.match(/submitted=(\d+)/);
    if (matches) {
        var \$p = \$('<p class="submissiondone" />').html('submission done <a href="#">x</a>');
        \$('#submitlist > .teamoverview').after(\$p);
        \$('table.submissions tr[data-submission-id=' + matches[1] + ']').addClass('highlight');

        \$('.submissiondone a').on('click', function() {
            \$(this).parent().remove();
            \$('table.submissions tr.highlight').removeClass('highlight');
            reloadLocation = 'index.php';
        });
    }
});
</script>
HTML;
    $maxfiles = dbconfig_get('sourcefiles_limit',100);

    echo addForm('upload.php','post',null,'multipart/form-data', null,
             ' onreset="resetUploadForm('.$refreshtime .', '.$maxfiles.');"') .
        "<p id=\"submitform\">\n\n";

    echo "<input type=\"file\" name=\"code[]\" id=\"maincode\" required";
    if ( $maxfiles > 1 ) {
        echo " multiple";
    }
    echo " />\n";


    echo addSelect('langid', $langs, '', true);

    echo addSubmit('submit', 'submit',
               "return checkUploadForm();");

    echo addReset('cancel');

    if ( $maxfiles > 1 ) {
        echo "<br /><span id=\"auxfiles\"></span>\n" .
            "<input type=\"button\" name=\"addfile\" id=\"addfile\" " .
            "value=\"Add another file\" onclick=\"addFileUpload();\" " .
            "disabled=\"disabled\" />\n";
    }
    echo "<script type=\"text/javascript\">initFileUploads($maxfiles);</script>\n\n";

    echo "</p>\n</form>\n\n";
}
// call putSubmissions function from common.php for this team.
*/

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
    putClarificationList($clarifications, $teamid);
}

echo "<h3 class=\"teamoverview\">Clarification Requests</h3>\n";

if ($requests->count() == 0) {
    echo "<p class=\"nodata\">No clarification requests.</p>\n\n";
} else {
    putClarificationList($requests, $teamid);
}

?>
<div class="m-1"><a href="clarification.php" class="btn btn-secondary btn-sm">request clarification</a></div>

</div>

</div>
<?php
require(LIBWWWDIR . '/footer.php');
