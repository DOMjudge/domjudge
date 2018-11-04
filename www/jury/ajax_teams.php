<?php declare(strict_types=1);

require('init.php');
header('content-type: application/json');

$teams = $DB->q("TABLE SELECT teamid AS id, name,
                 CONCAT(name, ' (t', teamid, ')') AS search FROM team
                 WHERE (name COLLATE " . DJ_MYSQL_COLLATION . " LIKE %c OR
                 CONCAT('t', teamid) LIKE %c)" .
                (isset($_GET['enabled']) ? " AND enabled = 1" : ""),
                $_GET['q'], $_GET['q']);

if ($_GET['select2'] ?? false) {
    $teams = array_map(function (array $team) {
        return [
            'id' => $team['id'],
            'text' => $team['search'],
        ];
    }, $teams);
    echo dj_json_encode(['results' => $teams]);
} else {
    echo dj_json_encode($teams);
}
