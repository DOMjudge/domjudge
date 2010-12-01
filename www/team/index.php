<?php
/**
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$refresh = '30;url=index.php';
$title = htmlspecialchars($teamdata['name']);
require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/forms.php');

$submitted = @$_GET['submitted'];

#FIXME: JavaScript must be in page <head>
echo "<script type=\"text/javascript\">\n<!--\n";
echo "function getMainExtension(ext)\n{\n";
echo "\tswitch(ext) {\n";
$exts = explode(" ", LANG_EXTS);
foreach($exts as $ext) {
	$langexts = explode(',', $ext);
	for ($i = 1; $i < count($langexts); $i++) {
		echo "\t\tcase '" . $langexts[$i]. "': return '" .$langexts[1] . "';\n";
	}
}
echo "\t\tdefault: return '';\n\t}\n}\n";
echo "// -->\n</script>\n";

// Put overview of team submissions (like scoreboard)
echo "<p />";
putTeamRow($cdata, array($login));

echo "<div id=\"submitlist\">\n";

echo "<h3 class=\"teamoverview\">Submissions</h3>\n\n";

if ( ENABLE_WEBSUBMIT_SERVER ) {
	if ( $submitted ) {
		echo "<p class=\"submissiondone\">submission done <a href=\"./\" style=\"color: red\">x</a></p>\n\n";
	} else {
		echo addForm('upload.php','post',null,'multipart/form-data') .
		"<p id=\"submitform\">\n\n" .
		"<input type=\"file\" name=\"code\" id=\"code\" size=\"10\" onchange='detectProblemLanguage(document.getElementById(\"code\").value);' /> ";

		$probs = $DB->q('KEYVALUETABLE SELECT probid, CONCAT(probid) as name FROM problem
				 WHERE cid = %i AND allow_submit = 1
				 ORDER BY probid', $cid);
		$probs[''] = 'problem';

		echo addSelect('probid', $probs, '', true);
		$langs = $DB->q('KEYVALUETABLE SELECT extension, name FROM language
				 WHERE allow_submit = 1 ORDER BY name');
		$langs[''] = 'language';
		echo addSelect('langext', $langs, '', true);

		echo addSubmit('GO', 'submit',
			       "return checkUploadForm();");

		echo "</p>\n</form>\n\n";
	}
}
// call putSubmissions function from common.php for this team.
$restrictions = array( 'teamid' => $login );
putSubmissions($cdata, $restrictions, null, $submitted);

echo "</div>\n\n";

echo "<div id=\"clarlist\">\n";

$requests = $DB->q('SELECT * FROM clarification
                    WHERE cid = %i AND sender = %s
                    ORDER BY submittime DESC, clarid DESC', $cid, $login);

$clarifications = $DB->q('SELECT c.*, u.type AS unread FROM clarification c
                          LEFT JOIN team_unread u ON
                          (c.clarid=u.mesgid AND u.type="clarification" AND u.teamid = %s)
                          WHERE c.cid = %i AND c.sender IS NULL
                          AND ( c.recipient IS NULL OR c.recipient = %s )
                          ORDER BY c.submittime DESC, c.clarid DESC',
                          $login, $cid, $login);

echo "<h3 class=\"teamoverview\">Clarifications</h3>\n";

# FIXME: column width and wrapping/shortening of clarification text 
if ( $clarifications->count() == 0 ) {
	echo "<p class=\"nodata\">No clarifications.</p>\n\n";
} else {
	putClarificationList($clarifications,$login);
}

echo "<h3 class=\"teamoverview\">Clarification Requests</h3>\n";

if ( $requests->count() == 0 ) {
	echo "<p class=\"nodata\">No clarification requests.</p>\n\n";
} else {
	putClarificationList($requests,$login);
}

echo "<div><form action='clarification.php' method='get'>
	<input type='submit' value='request clarification' /></form></div>";
echo "</div>\n";

require(LIBWWWDIR . '/footer.php');
