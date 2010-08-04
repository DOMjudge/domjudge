<?php
/**
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$refresh = '30;url=index.php';
$title = 'Submissions';
require(LIBWWWDIR . '/header.php');

// Put overview of team submissions (like scoreboard)
echo "<div id=\"teamscoresummary\">\n";
putTeamRow($cdata, $login);
echo "</div>\n";

echo "<h1>Submissions team ".htmlspecialchars($teamdata['name'])."</h1>\n\n";

if ( !empty($_GET['submitted']) ) {
	echo "<p><span class='submissionsuccessful'>Submission succesful!</span></p>\n\n";
}

// call putSubmissions function from common.php for this team.
$restrictions = array( 'teamid' => $login );
putSubmissions($cdata, $restrictions);

require(LIBWWWDIR . '/footer.php');
