<?php
/**
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$title = 'Import / Export';
require(LIBWWWDIR . '/header.php');

requireAdmin();

?>
<h1>Import and Export</h1>

<ul>
<li><a href="import-export-config.php">Contest data (contest.yaml)</a></li>
<li><a href="problems.php#problem_archive">Problem archive</a></li>
<li>tsv's:
	<a href="impexp_tsv.php?fmt=groups">groups.tsv</a>,
	<a href="impexp_tsv.php?fmt=teams">teams.tsv</a>,
	<a href="impexp_tsv.php?fmt=scoreboard">scoreboard.tsv</a>
</li>
</ul>


<p><br /><br /><br /><br /></p>

<?php
require(LIBWWWDIR . '/footer.php');
