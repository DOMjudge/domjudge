<?php
/**
 * View the details of a specific submission
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = (int)$_REQUEST['id'];

require('init.php');
$title = 'Submission '.@$id;

if ( ! $id ) error("Missing or invalid submission id");

$submdata = $DB->q('MAYBETUPLE SELECT s.teamid, s.probid, s.langid, s.submittime,
                    s.sourcefile, c.cid, c.contestname,
                    t.name AS teamname, l.name AS langname, p.name AS probname
                    FROM submission s
                    LEFT JOIN team     t ON (t.login  = s.teamid)
                    LEFT JOIN problem  p ON (p.probid = s.probid)
                    LEFT JOIN language l ON (l.langid = s.langid)
                    LEFT JOIN contest  c ON (c.cid    = s.cid)
                    WHERE submitid = %i', $id);

if ( ! $submdata ) error ("Missing submission data");


require('../header.php');

echo "<h1>Submission ".$id."</h1>\n\n";

?>

<table>
<tr><td>Contest:</td><td><?=htmlentities($submdata['contestname'])?></td></tr>
<tr><td>Team:</td><td>
	<a href="team.php?id=<?=urlencode($submdata['teamid'])?>">
	<span class="teamid"><?=htmlspecialchars($submdata['teamid'])?></span>: 
	<?=htmlentities($submdata['teamname'])?></a></td></tr>
<tr><td>Problem:</td><td>
	<a href="problem.php?id=<?=$submdata['probid']?>">
	<?=htmlentities($submdata['probid'].": ".$submdata['probname'])?></a></td></tr>
<tr><td>Language:</td><td>
	<a href="language.php?id=<?=$submdata['langid']?>">
	<?=htmlentities($submdata['langid'].": ".$submdata['langname'])?></a></td></tr>
<tr><td>Submittime:</td><td><?= htmlspecialchars($submdata['submittime']) ?></td></tr>
<tr><td>Source:</td><td class="filename">
	<a href="show_source.php?id=<?=$id?>">
	<?=htmlspecialchars($submdata['sourcefile'])?></a></td></tr>
</table>

<?php

echo "<p>" . rejudgeForm('submission', $id) . "</p>\n\n";

echo "<h3>Judgings</h3>\n\n";

putJudgings('submitid', $id);

require('../footer.php');
