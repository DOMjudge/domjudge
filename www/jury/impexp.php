<?php declare(strict_types=1);
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

<h2>Import / Export via file down/upload</h2>

<ul>
<li><a href="impexp_contestyaml.php">Contest data (contest.yaml)</a></li>
<li><a href="problems">Problem archive</a></li>
<li>Tab separated, export:
    <a href="impexp_tsv.php?act=ex&amp;fmt=groups">groups.tsv</a>,
    <a href="impexp_tsv.php?act=ex&amp;fmt=teams">teams.tsv</a>,
    <a href="impexp_tsv.php?act=ex&amp;fmt=scoreboard">scoreboard.tsv</a>,
    <a href="impexp_tsv.php?act=ex&amp;fmt=results">results.tsv</a>
</li>
<li>HTML, export:
    <a href="impexp_results.php" target="_blank">results.html</a>,
    <a href="impexp_results.php?mode=icpcsite" target="_blank">results.html for on ICPC site</a>,
    <a href="impexp_clarifications.php" target="_blank">clarifications.html</a>
</li>
<li>
<?php echo addForm('impexp_tsv.php', 'post', null, 'multipart/form-data') .
    'Tab separated, import: ' .
    '<label for="fmt">type:</label> ' .
    addSelect('fmt', array('groups','teams','accounts')) .
        ', <label for="tsv">file:</label>' .
        addFileField('tsv') .
        addHidden('act', 'im') .
        addSubmit('import') .
        addEndForm();
?>
</li>
</ul>

<?php

require(LIBWWWDIR . '/footer.php');
