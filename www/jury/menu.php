<nav><div id="menutop">
<a href="index.php" accesskey="h">home</a>
<?php	if ( checkrole('balloon') ) { ?>
<a href="balloons.php" accesskey="b">balloons</a>
<?php	} ?>
<?php	if ( checkrole('jury') ) { ?>
<a href="problems.php" accesskey="p">problems</a>
<?php	} ?>
<?php	if ( IS_ADMIN ) {
	$ndown = count($updates['judgehosts']);
	if ( $ndown > 0 ) { ?>
<a class="new" href="judgehosts.php" accesskey="j" id="menu_judgehosts">judgehosts (<?php echo $ndown ?> down)</a>
<?php	} else { ?>
<a href="judgehosts.php" accesskey="j" id="menu_judgehosts">judgehosts</a>
<?php	}
	} ?>
<?php	if ( checkrole('jury') ) { ?>
<a href="teams.php" accesskey="t">teams</a>
<a href="users.php" accesskey="u">users</a>
<?php
	$nunread = count($updates['clarifications']);
	if ( $nunread > 0 ) { ?>
<a class="new" href="clarifications.php" accesskey="c" id="menu_clarifications">clarifications (<?php echo $nunread ?> new)</a>
<?php	} else { ?>
<a href="clarifications.php" accesskey="c" id="menu_clarifications">clarifications</a>
<?php	} ?>
<a href="submissions.php" accesskey="s">submissions</a>
<?php
	$nrejudgings = count($updates['rejudgings']);
	if ( $nrejudgings > 0 ) { ?>
<a class="new" href="rejudgings.php" accesskey="r" id="menu_rejudgings">rejudgings (<?php echo $nrejudgings ?> active)</a>
<?php	} else { ?>
<a href="rejudgings.php" accesskey="r" id="menu_rejudgings">rejudgings</a>
<?php	} ?>
<?php   } /* checkrole('jury') */ ?>
<?php	if ( have_printing() ) { ?>
<a href="print.php" accesskey="p">print</a>
<?php	} ?>
<?php	if ( checkrole('jury') ) { ?>
<a href="scoreboard.php" accesskey="b">scoreboard</a>
<?php	} ?>
<?php
if ( checkrole('team') ) {
	echo "<a target=\"_top\" href=\"../team/\" accesskey=\"t\">→team</a>\n";
}
?>
</div>

<div id="menutopright">
<?php

putClock();

$notify_flag  =  isset($_COOKIE["domjudge_notify"])  && (bool)$_COOKIE["domjudge_notify"];
$refresh_flag = !isset($_COOKIE["domjudge_refresh"]) || (bool)$_COOKIE["domjudge_refresh"];

echo "<div id=\"toggles\">\n";
if ( isset($refresh) ) {
	echo addForm('toggle_refresh.php', 'get') .
	    addHidden('enable', ($refresh_flag ? 0 : 1)) .
	    addSubmit(($refresh_flag ? 'Dis' : 'En' ) . 'able refresh', 'toggle_refresh') .
	    addEndForm();
}

// Default hide this from view, only show when javascript and
// notifications are available:
echo '<div id="notify" style="display: none">' .
	addForm('toggle_notify.php', 'get') .
	addHidden('enable', ($notify_flag ? 0 : 1)) .
	addSubmit(($notify_flag ? 'Dis' : 'En' ) . 'able notifications', 'toggle_notify',
	          'return toggleNotifications(' . ($notify_flag ? 'false' : 'true') . ')') .
	addEndForm() . "</div>";

?>
<script type="text/javascript">
<!--
    if ( 'Notification' in window ) {
		document.getElementById('notify').style.display = 'block';
	}
// -->
</script>

</div>
</div></nav>
