<?php
/**
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/index.php';
$title = 'Submissions';
require('../header.php');

// Put overview of team submissions (like scoreboard)
echo "<div id=\"teamscoresummary\">\n";
putTeamRow($login);
echo "</div>\n";

echo "<h1>Submissions team ".htmlentities($name)."</h1>\n\n";

// call putSubmissions function from common.php for this team.
$restrictions = array( 'teamid' => $login );
putSubmissions($restrictions);

require('../footer.php');
