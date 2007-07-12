<?php

require('init.php');

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Wed, 10 Feb 1971 05:00:00 GMT");
header("Content-type: text/plain");

$cid = getCurContest();
$cnew = $DB->q('VALUE SELECT COUNT(*) FROM clarification
                WHERE sender IS NOT NULL AND cid = %i AND answered = 0
                ORDER BY submittime DESC', $cid);
echo $cnew;
