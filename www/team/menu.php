<?php

/* (new) clarification info */
$unread_clarifications = (int) $DB->q('VALUE SELECT COUNT(*) FROM team_unread
		LEFT JOIN clarification ON(mesgid=clarid)
		WHERE type="clarification" AND teamid = %s
		AND cid = %i', $login, $cid);

echo "<div id=\"menutop\">\n";

echo "<a target=\"_top\" href=\"index.php\" accesskey=\"s\">submissions</a>\n";

if ( $unread_clarifications > 0 ) {
	echo '<a target="_top" class="new" href="clarifications.php" ' .
		'accesskey="c" id="menu_clarifications">clarifications (' .
		$unread_clarifications . " new)</a>\n";
} else {
	echo '<a target="_top" href="clarifications.php" ' .
		"accesskey=\"c\" id=\"menu_clarifications\">clarifications</a>\n";
}

echo "<a target=\"_top\" href=\"scoreboard.php\" accesskey=\"b\">scoreboard</a>\n";

if ( ENABLE_WEBSUBMIT_SERVER ) {
	echo "<a target=\"_top\" href=\"websubmit.php\" accesskey=\"u\">submit</a>\n";
}

echo "\n</div>\n\n";

putClock();
