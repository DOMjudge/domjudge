<div id="menutop">
<a href="index.php" accesskey="h">home</a>
<a href="problems.php" accesskey="p">problems</a>
<?php	if ( IS_ADMIN ) { ?>
<a href="judgehosts.php" accesskey="j">judgehosts</a>
<?php   } ?>
<a href="teams.php" accesskey="t">teams</a>
<?php	if ( $nunread_clars > 0 ) { ?>
<a class="new" href="clarifications.php" accesskey="c" id="menu_clarifications">clarifications (<?php echo $nunread_clars?> new)</a>
<?php	} else { ?>
<a href="clarifications.php" accesskey="c" id="menu_clarifications">clarifications</a>
<?php	} ?>
<a href="submissions.php" accesskey="s">submissions</a>
<a href="scoreboard.php" accesskey="b">scoreboard</a>
</div>

<?php putClock();
