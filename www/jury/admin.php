<?php
/**
 * Administrator functionality (things not for general jury usage)
 *
 * $Id$
 */

require('init.php');
$title = 'Administrator Functions';
require('../header.php');
require('menu.php');

echo "<h1>Administrator Functions</h1>\n\n";

echo "<p><a href=\"checkconfig.php\">Config checker</a></p>\n\n";

echo "<p><a href=\"recalculate_scores.php\">Recalculate the cached scoreboard</a> " .
	"(expensive operation)</p>\n\n";
	

require('../footer.php');
