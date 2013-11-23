<nav><div id="menutop">
<a href="index.php" accesskey="h">home</a>
<?php
if ( have_problemtexts() ) {
	echo "<a href=\"problem.php\" accesskey=\"p\">problems</a>\n";
}
logged_in(); // fill userdata
if ( checkrole('team') ) {
	echo "<a target=\"_top\" href=\"../team/\" accesskey=\"t\">→team</a>\n";
}
if ( checkrole('jury') || checkrole('balloon') ) {
	echo "<a target=\"_top\" href=\"../jury/\" accesskey=\"j\">→jury</a>\n";
}
if ( !logged_in() ) {
	echo "<a href=\"login.php\" accesskey=\"l\">login</a>\n";
}
?>
</div></nav>
