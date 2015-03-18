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

<h2>Import via file upload</h2>

<ul>
<li><a href="impexp_contestyaml.php">Contest data (contest.yaml)</a></li>
<li><a href="problems.php#problem_archive__">Problem archive</a></li>
<li>Tab separated, export:
	<a href="impexp_tsv.php?act=ex&amp;fmt=groups">groups.tsv</a>,
	<a href="impexp_tsv.php?act=ex&amp;fmt=teams">teams.tsv</a>,
	<a href="impexp_tsv.php?act=ex&amp;fmt=scoreboard">scoreboard.tsv</a>,
	<a href="impexp_tsv.php?act=ex&amp;fmt=results">results.tsv</a>
<li>
<?php echo addForm('impexp_tsv.php', 'post', null, 'multipart/form-data') .
	'Tab separated, import: ' .
	'<label for="fmt">type:</label> ' .
	addSelect('fmt',array('groups','teams','accounts')) .
        ', <label for="tsv">file:</label>' .
        addFileField('tsv') .
        addHidden('act','im') .
        addSubmit('import') .
        addEndForm();
?>
</li>
</ul>

<h2>Import teams / Upload standings from / to icpc.baylor.edu</h2>

<p>
Create a "Web Services Token" with appropriate rights in the "Export" section
for your contest at <a
href="https://icpc.baylor.edu/login">https://icpc.baylor.edu/login</a>. You can
find the Contest ID (e.g. <code>Southwestern-Europe-2014</code>) in the URL.
</p>

<?php

echo addForm("impexp_baylor.php");
echo "<table>\n";
echo "<tr><td><label for=\"contest\">Contest ID:</label></td>" .
	"<td>" . addInput('contest', @$contest, null, null, 'required') . "</td></tr>\n";
echo "<tr><td><label for=\"token\">Access token:</label></td>" .
	"<td>" . addInput('token', @$token, null, null, 'required') . "</td></tr>\n";
echo "</table>\n";
echo addSubmit('Fetch teams', 'fetch') .
     addSubmit('Upload standings', 'upload') . addEndForm();

require(LIBWWWDIR . '/footer.php');
