<?php
/**
 * $Id$
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/';
$title = 'Team overview';
require('../header.php');

include('menu.php');

echo "<h1>Teampage ".htmlentities($name)."</h1>\n\n";

// call putSubmissions function from common.php for this team.
putSubmissions('team', $login);

require('../footer.php');
