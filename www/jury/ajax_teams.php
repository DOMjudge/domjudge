<?php

require('init.php');
header('content-type: application/json');

$teams = $DB->q("TABLE SELECT teamid AS id, name,
                 CONCAT(name, ' (t', teamid, ')') AS search FROM team
                 WHERE (name COLLATE utf8_general_ci LIKE %c OR
                 CONCAT('t', teamid) LIKE %c)", $_GET['q'], $_GET['q']);

echo json_encode($teams);
