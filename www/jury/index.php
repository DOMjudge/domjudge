<?php
/**
 * $Id$
 */

require('init.php');
$title = 'Jury interface';
require('../header.php');
require('menu.php');

echo "<h1>DOMjudge Jury interface</h1>\n\n";
?>

<h3>Overviews:</h3>
<ul>
<li><a href="contests.php">Contests</a></li>
<li><a href="submissions.php">Submissions</a></li>
<li><a href="clarifications.php">Clarifications</a></li>
<li><a href="teams.php">Teams</a></li>
<li><a href="judgers.php">Judgers</a></li>
<li><a href="categories.php">Categories</a></li>
<li><a href="languages.php">Languages</a></li>
<li><a href="problems.php">Problems</a></li>
</ul>

<h3>Administrator:</h3>

<ul>
<li><a href="admin.php">Admin functions</a></li>
</ul>

<p><br /><br /><br /><br /></p>

<?php
putDOMjudgeVersion();

require('../footer.php');
