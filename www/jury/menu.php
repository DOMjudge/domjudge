<nav><div id="menutop">
<a href="index.php" accesskey="h">home</a>
<a href="problems.php" accesskey="p">problems</a>
<?php	if ( IS_ADMIN ) { ?>
<a href="judgehosts.php" accesskey="j">judgehosts</a>
<?php   } ?>
<a href="teams.php" accesskey="t">teams</a>
<a href="users.php" accesskey="u">users</a>
<?php	if ( ( $nunread_clars > 0 ) && checkrole('jury') ) { ?>
<a class="new" href="clarifications.php" accesskey="c" id="menu_clarifications">clarifications (<?php echo $nunread_clars?> new)</a>
<?php	} else { ?>
<a href="clarifications.php" accesskey="c" id="menu_clarifications">clarifications</a>
<?php	} ?>
<a href="submissions.php" accesskey="s">submissions</a>
<?php   if ( have_printing() ) { ?>
<a href="print.php" accesskey="p">print</a>
<?php   } ?>
<a href="scoreboard.php" accesskey="b">scoreboard</a>
<?php
if ( have_logout() ) {
    echo "<a target=\"_top\" href=\"../logout.php\" accesskey=\"l\">logout $username</a>\n";
}
?>
</div>

<div id="menutopright">
<?php

putClock();

$refresh_flag = !isset($_COOKIE["domjudge_refresh"]) || (bool)$_COOKIE["domjudge_refresh"];

if ( isset($refresh) ) {
	echo "<div id=\"refresh\">\n" .
	    addForm('toggle_refresh.php', 'get') .
	    addHidden('enable', ($refresh_flag ? 0 : 1)) .
	    addSubmit(($refresh_flag ? 'Dis' : 'En' ) . 'able refresh', 'submit') .
	    addEndForm() . "</div>\n";
}

echo "</div></nav>\n";
