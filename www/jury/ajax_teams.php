<?php

require('init.php');
header('content-type: application/json');

$teams = $DB->q("TABLE SELECT teamid AS id, name,
                 CONCAT(name, ' (t', teamid, ')') AS search FROM team
                 WHERE (name COLLATE %s_general_ci LIKE %c OR
                 CONCAT('t', teamid) LIKE %c)", DJ_CHARACTER_SET_MYSQL, $_GET['q'], $_GET['q']);

echo json_encode($teams);
