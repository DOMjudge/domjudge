<?php
/**
 * $Id$
 */

require('init.php');
$title = 'Jury interface';
require('../header.php');

echo "<h1>DOMjudge Jury interface</h1>\n\n";

if ( is_readable('../images/DOMjudgelogo.png') ) {
	echo "<p><a href=\"http://domjudge.sourceforge.net/\">" .
		"<img src=\"../images/DOMjudgelogo.png\" id=\"djlogo\" " .
		"alt=\"DOMjudge logo\" /></a></p>\n\n";
}
?>

<h3>Overviews:</h3>
<ul>
<li><a href="clarifications.php">Clarifications</a></li>
<li><a href="contests.php">Contests</a></li>
<li><a href="judgehosts.php">Judgehosts</a></li>
<li><a href="languages.php">Languages</a></li>
<li><a href="problems.php">Problems</a></li>
<li><a href="scoreboard.php">Scoreboard</a></li>
<li><a href="submissions.php">Submissions</a></li>
<li><a href="teams.php">Teams</a></li>
<li><a href="categories.php">Team Categories</a></li>
<li><a href="affiliations.php">Team Affiliations</a></li>
</ul>

<h3>Documentation:</h3>

<ul>
<li><a href="doc/judge/judge-manual.html">Judge manual</a> (also <a href="doc/judge/judge-manual.pdf">PDF</a>)</li>
<li><a href="doc/admin/admin-manual.html">Administrator manual</a> (also <a href="doc/admin/admin-manual.pdf">PDF</a>)</li>
<li><a href="doc/team/team-manual.pdf">Team manual</a> (PDF only)</li>
</ul>

<h3>Administrator:</h3>

<ul>
<li><a href="admin.php">Admin functions</a></li>
</ul>

<p><br /><br /><br /><br /></p>

<?php
putDOMjudgeVersion();

require('../footer.php');
