<?php
/**
 * $Id$
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/';
$title = 'Submissions';
require('../header.php');
require('../scoreboard.php');

include('menu.php');

echo "<h1>Submissions team ".htmlentities($name)."</h1>\n\n";

// Put overview of team submissions (like scoreboard)
echo "<div id=\"teamscoresummary\">\n";
putTeamRow($login);
echo "</div>\n";

// call putSubmissions function from common.php for this team.
putSubmissions('team', $login);

require('../footer.php');
