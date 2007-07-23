<?php
/**
 * Supply information for AJAX RPC calls (update the number
 * of new clarifications in the menu line).
 *
 * $Id$
 */
require('init.php');

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Wed, 10 Feb 1971 05:00:00 GMT");
header("Content-type: text/plain");

$cid = getCurContest();
echo (int) $DB->q('VALUE SELECT COUNT(*) FROM team_unread
                   LEFT JOIN clarification ON(mesgid=clarid)
                   WHERE type="clarification" AND teamid = %s
                   AND clarification.cid = %i', $login, $cid);

