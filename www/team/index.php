<?php
/**
 * $Id$
 */

require('init.php');
$title = 'Team overview';
require('../header.php');

echo "<h1>Teampage ".htmlentities($name)."</h1>\n\n";

getSubmissions('team', $login, FALSE);

echo "<p><a href=\"../public/\">Scoreboard</a></p>\n\n";

require('../footer.php');
