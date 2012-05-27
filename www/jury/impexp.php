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
<li>Tab separated, export:
	<a href="impexp_tsv.php?act=ex&amp;fmt=groups">groups.tsv</a>,
	<a href="impexp_tsv.php?act=ex&amp;fmt=teams">teams.tsv</a>,
	<a href="impexp_tsv.php?act=ex&amp;fmt=scoreboard">scoreboard.tsv</a>
<li>
<?php echo addForm('impexp_tsv.php', 'post', null, 'multipart/form-data') .
	'Tab separated, import: ' .
	'<label for="fmt">type:</label> ' .
	addSelect('fmt',array('groups','teams')) .
        ', <label for="tsv">file:</label>' .
        addFileField('tsv') .
        addHidden('act','im') .
        addSubmit('import') .
        addEndForm();
?>
</li>
</ul>


<?php
require(LIBWWWDIR . '/footer.php');
