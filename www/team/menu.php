<?php

echo "<div id=\"menutop\">\n";

echo "<a target=\"_top\" href=\"index.php\" accesskey=\"s\">submissions</a>\n";

if ( $nunread_clars > 0 ) {
	echo '<a target="_top" class="new" href="clarifications.php" ' .
		'accesskey="c" id="menu_clarifications">clarifications (' .
		$nunread_clars . " new)</a>\n";
} else {
	echo '<a target="_top" href="clarifications.php" ' .
		"accesskey=\"c\" id=\"menu_clarifications\">clarifications</a>\n";
}

echo "<a target=\"_top\" href=\"scoreboard.php\" accesskey=\"b\">scoreboard</a>\n";

if ( ENABLE_WEBSUBMIT_SERVER ) {
	echo "<a target=\"_top\" href=\"websubmit.php\" accesskey=\"u\">submit</a>\n";
}

if ( have_logout() ) {
	echo "<a target=\"_top\" href=\"logout.php\" accesskey=\"l\">logout</a>\n";
}

echo "\n</div>\n\n";

putClock();
