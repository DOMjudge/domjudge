<?php
/**
 * Administrator functionality (things not for general jury usage)
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Administrator Functions';
require('../header.php');

requireAdmin();
?>

<h1>Administrator Functions</h1>

<p><a href="genpasswds.php">Manage team passwords</a></p>

<p><a href="checkconfig.php">Config checker</a></p>

<p><a href="recalculate_scores.php">Recalculate the cached scoreboard</a> 
(expensive operation)</p>
	
<?php
require('../footer.php');
