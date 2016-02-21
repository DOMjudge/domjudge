<?php

echo "<nav><div id=\"menutop\">\n";

echo "<a target=\"_top\" href=\"index.php\" accesskey=\"o\"><span class=\"octicon octicon-home\"></span> overview</a>\n";

echo "<a target=\"_top\" href=\"problems.php\" accesskey=\"t\"><span class=\"octicon octicon-book\"></span> problems</a>\n";

if ( have_printing() ) {
	echo "<a target=\"_top\" href=\"print.php\" accesskey=\"p\"><span class=\"octicon octicon-file-text\"></span> print</a>\n";
}
echo "<a target=\"_top\" href=\"scoreboard.php\" accesskey=\"b\"><span class=\"octicon octicon-list-ordered\"></span> scoreboard</a>\n";

if ( checkrole('jury') || checkrole('balloon') ) {
	echo "<a target=\"_top\" href=\"../jury/\" accesskey=\"j\"><span class=\"octicon octicon-arrow-right\"></span> jury</a>\n";
}

echo "</div>\n\n<div id=\"menutopright\">\n";

putClock();

echo "</div></nav>\n\n";
