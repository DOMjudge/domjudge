<?php

require('init.php');
header('content-type: application/json');

$teams = $DB->q("TABLE SELECT teamid AS id, name,
                 CONCAT(name, ' (t', teamid, ')') AS search FROM team
                 WHERE (name COLLATE " . DJ_MYSQL_COLLATION . " LIKE %c OR
                 CONCAT('t', teamid) LIKE %c)" .
                (isset($_GET['enabled']) ? " AND enabled = 1" : ""),
                $_GET['q'], $_GET['q']);

echo dj_json_encode($teams);
