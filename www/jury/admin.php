<?php
/**
 * Administrator functionality (things not for general jury usage)
 *
 * $Id$
 */

require('init.php');
$title = 'Administrator Functions';
require('../header.php');

requireAdmin();
?>

<h1>Administrator Functions</h1>

<p><a href="genpasswds.php">Generate team passwords</a></p>

<p><a href="checkconfig.php">Config checker</a></p>

<p><a href="recalculate_scores.php">Recalculate the cached scoreboard</a> 
(expensive operation)</p>
	
<?php
require('../footer.php');
