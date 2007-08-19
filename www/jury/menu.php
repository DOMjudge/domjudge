<?php

require('init.php');

$cid = getCurContest();

$cnew = $DB->q('VALUE SELECT COUNT(*) FROM clarification
                WHERE sender IS NOT NULL AND cid = %i AND answered = 0',
                $cid);

?>
<div id="menutop">
<a href="index.php" accesskey="h">home</a>
<a href="problems.php" accesskey="p">problems</a>
<a href="judgehosts.php" accesskey="j">judgehosts</a>
<a href="teams.php" accesskey="t">teams</a>
<?php	if ( $cnew ) { ?>
<a class="new" href="clarifications.php" accesskey="c" id="menu_clarifications">clarifications (<?=$cnew?> new)</a>
<?php	} else { ?>
<a href="clarifications.php" accesskey="c" id="menu_clarifications">clarifications</a>
<?php	} ?>
<a href="submissions.php" accesskey="s">submissions</a>
<a href="scoreboard.php" accesskey="b">scoreboard</a>
</div>

<?php putClock();
