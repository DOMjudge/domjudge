<?php

require('init.php');

/* (new) clarification info */
$res = $DB->q('KEYTABLE SELECT type AS ARRAYKEY, COUNT(*) AS count FROM team_unread
               WHERE teamid = %s GROUP BY type', $login);

echo "<div id=\"menutop\">\n";

// 'unread submission' does not work yet (and the AJAX code does not support it)
if ( isset($res['submission']) ) {
	echo '<a target="_top" class="new" href="index.php" accesskey="s">' .
		'submissions (' .
		(int)$res['submission']['count'] . " new)</a>\n";
} else {
	echo "<a target=\"_top\" href=\"index.php\" accesskey=\"s\">submissions</a>\n";
}

if ( isset($res['clarification']) ) {
	echo '<a target="_top" class="new" href="clarifications.php" ' .
		'accesskey="c" id="menu_clarifications">clarifications (' .
		(int)$res['clarification']['count'] . " new)</a>\n";
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
