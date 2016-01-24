<nav><div id="menutop">
<a href="index.php" accesskey="h"><span class="octicon octicon-home"></span> home</a>
<a href="problems.php" accesskey="p"><span class="octicon octicon-book"></span> problems</a>
<?php
logged_in(); // fill userdata

if ( checkrole('team') ) {
	echo "<a target=\"_top\" href=\"../team/\" accesskey=\"t\"><span class=\"octicon octicon-arrow-right\"></span> team</a>\n";
}
if ( checkrole('jury') || checkrole('balloon') ) {
	echo "<a target=\"_top\" href=\"../jury/\" accesskey=\"j\"><span class=\"octicon octicon-arrow-right\"></span> jury</a>\n";
}
if ( !logged_in() ) {
	echo "<a href=\"login.php\" accesskey=\"l\"><span class=\"octicon octicon-sign-in\"></span> login</a>\n";
}
?>
</div></nav>
