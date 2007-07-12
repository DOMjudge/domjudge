<?php

require('init.php');

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Wed, 10 Feb 1971 05:00:00 GMT");
header("Content-type: text/plain");

$res = $DB->q('KEYTABLE SELECT type AS ARRAYKEY, COUNT(*) AS count FROM team_unread
               WHERE teamid = %s GROUP BY type', $login);
if ( isset($res['clarification']) ) {
	echo (int)$res['clarification']['count'];
} else
	echo 0;
