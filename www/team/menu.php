<?php

echo "<div id=\"menutop\">\n";

echo "<a target=\"_top\" href=\"index.php#submit\">submit</a>\n";
echo "<a target=\"_top\" href=\"index.php#stats\" accesskey=\"o\">stats</a>\n";
echo "<a target=\"_top\" href=\"problems.php#\">problems</a>\n";
echo "<a target=\"_top\" href=\"ranking.php\">ranking</a>\n";
echo "<a target=\"_top\" href=\"index.php#clarifications\">clarifications</a>\n";
echo "<a target=\"_top\" href=\"index.php#clarreq\">clar. requests</a>\n";

if ( have_printing() ) {
	echo "<a target=\"_top\" href=\"print.php\" accesskey=\"p\">print</a>\n";
}
//echo "<a target=\"_top\" href=\"scoreboard.php\" accesskey=\"b\">scoreboard</a>\n";

if ( have_logout() ) {
	echo "<a target=\"_top\" href=\"logout.php\" accesskey=\"l\">logout</a>\n";
}

echo "</div>\n\n<div id=\"menutopright\">\n";

putClock();

echo "</div>\n\n";
