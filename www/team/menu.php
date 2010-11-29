<?php

echo "<div id=\"menutop\">\n";

echo "<a target=\"_top\" href=\"index.php\" accesskey=\"o\">overview</a>\n";

echo "<a target=\"_top\" href=\"scoreboard.php\" accesskey=\"b\">scoreboard</a>\n";

if ( have_logout() ) {
	echo "<a target=\"_top\" href=\"logout.php\" accesskey=\"l\">logout</a>\n";
}

echo "\n</div>\n\n";

putClock();
