<?php declare(strict_types=1);
global $updates;
?>
<nav><div id="menutop">
<a href="index.php" accesskey="h"><i class="fas fa-home"></i> home</a>
<?php if (checkrole('balloon')) {
    ?>
<a href="balloons.php" accesskey="b"><i class="fas fa-map-marker-alt"></i> balloons</a>
<?php
} ?>
<?php if (checkrole('jury')) {
        ?>
<a href="problems" accesskey="p"><i class="fas fa-book-open"></i> problems</a>
<?php
    } ?>
<?php if (IS_ADMIN) {
        $ndown = count($updates['judgehosts']);
        if ($ndown > 0) {
            ?>
<a class="new" href="judgehosts.php" accesskey="j" id="menu_judgehosts"><i class="fas fa-server"></i> judgehosts (<?php echo $ndown ?> down)</a>
<?php
        } else {
            ?>
<a href="judgehosts.php" accesskey="j" id="menu_judgehosts"><i class="fas fa-server"></i> judgehosts</a>
<?php
        }
        $nerr = count($updates['internal_error']);
        if ($nerr > 0) {
            ?>
<a class="new" href="internal_errors.php" accesskey="e" id="menu_internal_error"><i class="fas fa-bolt"></i> internal error (<?php echo $nerr ?> new)</a>
<?php
        }
    } ?>
<?php if (checkrole('jury')) {
        ?>
<a href="teams" accesskey="t"><i class="fas fa-users"></i> teams</a>
<a href="users" accesskey="u"><i class="fas fa-user"></i> users</a>
<?php
    $nunread = count($updates['clarifications']);
        if ($nunread > 0) {
            ?>
<a class="new" href="clarifications" accesskey="c" id="menu_clarifications"><i class="fas fa-comments"></i> clarifications (<?php echo $nunread ?> new)</a>
<?php
        } else {
            ?>
<a href="clarifications" accesskey="c" id="menu_clarifications"><i class="fas fa-comments"></i> clarifications</a>
<?php
        } ?>
<a href="submissions" accesskey="s"><i class="fas fa-file-code"></i> submissions</a>
<?php
    $nrejudgings = count($updates['rejudgings']);
        if ($nrejudgings > 0) {
            ?>
<a class="new" href="rejudgings.php" accesskey="r" id="menu_rejudgings"><i class="fas fa-sync"></i> rejudgings (<?php echo $nrejudgings ?> active)</a>
<?php
        } else {
            ?>
<a href="rejudgings.php" accesskey="r" id="menu_rejudgings"><i class="fa fa-sync"></i> rejudgings</a>
<?php
        } ?>
<?php
    } /* checkrole('jury') */ ?>
<?php if (have_printing()) {
        ?>
<a href="print.php" accesskey="p"><i class="fas fa-file-alt"></i> print</a>
<?php
    } ?>
<?php if (checkrole('jury')) {
        ?>
<a href="scoreboard.php" accesskey="b"><i class="fas fa-list-ol"></i> scoreboard</a>
<?php
    } ?>
<?php
if (checkrole('team')) {
        echo "<a target=\"_top\" href=\"../team/\" accesskey=\"t\"><i class=\"fas fa-arrow-right\"></i> team</a>\n";
    }
?>
</div>

<div id="menutopright">
<?php

putClock();

$notify_flag  =  isset($_COOKIE["domjudge_notify"])  && (bool)$_COOKIE["domjudge_notify"];
$refresh_flag = !isset($_COOKIE["domjudge_refresh"]) || (bool)$_COOKIE["domjudge_refresh"];

echo "<div id=\"toggles\">\n";
if (isset($refresh)) {
    $text = $refresh_flag ? 'Disable' : 'Enable';
    echo '<input id="refresh-toggle" type="button" value="' . $text . ' refresh" />';
}

// Default hide this from view, only show when javascript and
// notifications are available:
echo '<div id="notify" style="display: none">' .
    addForm('toggle_notify.php', 'get') .
    addHidden('enable', ($notify_flag ? '0' : '1')) .
    addSubmit(
        ($notify_flag ? 'Dis' : 'En') . 'able notifications',
        'toggle_notify',
              'return toggleNotifications(' . ($notify_flag ? 'false' : 'true') . ')'
    ) .
    addEndForm() . "</div>";

?>

</div>
</div></nav>
