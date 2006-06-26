
<div id="menutop">
<a href="<?=addUrl('index.php', $popupTag)?>">submissions</a>
<a href="<?=addUrl('clarifications.php', $popupTag)?>">clarifications</a>
<a href="<?=addUrl('scoreboard.php', $popupTag)?>">scoreboard</a>
<?php if ( ENABLEWEBSUBMIT ): ?>
<a href="<?=addUrl('websubmit.php', $popupTag)?>">submit</a>
<?php endif; ?>
</div>

<?php putClock();
// $Id$
?>

