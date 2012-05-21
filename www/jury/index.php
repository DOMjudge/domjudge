<?php
/**
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$title = 'Jury interface';
require(LIBWWWDIR . '/header.php');

echo "<h1>DOMjudge Jury interface</h1>\n\n";

if ( is_readable('../images/DOMjudgelogo.png') ) {
	echo "<p><a href=\"http://domjudge.sourceforge.net/\">" .
		"<img src=\"../images/DOMjudgelogo.png\" id=\"djlogo\" " .
		"alt=\"DOMjudge logo\" title=\"The DOMjudge logo: free as in beer!\" /></a></p>\n\n";
}

?>


<h3>Overviews:</h3>
<ul>
<li><a href="balloons.php">Balloon Status</a></li>
<li><a href="clarifications.php">Clarifications</a></li>
<li><a href="contests.php">Contests</a></li>
<li><a href="judgehosts.php">Judgehosts</a></li>
<li><a href="languages.php">Languages</a></li>
<li><a href="problems.php">Problems</a></li>
<li><a href="scoreboard.php">Scoreboard</a></li>
<li><a href="submissions.php">Submissions</a></li>
<li><a href="teams.php">Teams</a></li>
<li><a href="team_categories.php">Team Categories</a></li>
<li><a href="team_affiliations.php">Team Affiliations</a></li>
</ul>

<?php if ( IS_ADMIN ): ?>

<h3>Administrator:</h3>

<ul>
<li><a href="config.php">Configuration settings</a></li>
<li><a href="checkconfig.php">Config checker</a></li>
<li><a href="genpasswds.php">Manage team passwords</a></li>
<li><a href="refresh_cache.php">Refresh scoreboard cache</a></li>
<li><a href="check_judgings.php">Judging verifier</a></li>
<li><a href="auditlog.php">Activity log</a></li>
</ul>

<?php endif; ?>

<h3>Documentation:</h3>

<ul>
<li><a href="doc/judge/judge-manual.html">Judge manual</a>
	(also <a href="doc/judge/judge-manual.pdf">PDF</a>)</li>
<li><a href="doc/admin/admin-manual.html">Administrator manual</a>
	(also <a href="doc/admin/admin-manual.pdf">PDF</a>)</li>
<li><a href="doc/team/team-manual.pdf">Team manual</a>
	(PDF only)</li>
</ul>


<p><br /><br /><br /><br /></p>

<?php
putDOMjudgeVersion();

require(LIBWWWDIR . '/footer.php');
