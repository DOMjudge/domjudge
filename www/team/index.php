<?php
/**
 * $Id$
 */

require('init.php');
$title = 'Team overview';
require('../header.php');

include('menu.php');

putClock();

echo "<h1>Teampage ".htmlentities($name)."</h1>\n\n";

getSubmissions('team', $login, FALSE);

require('../footer.php');
