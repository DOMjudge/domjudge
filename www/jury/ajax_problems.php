<?php

require('init.php');
header('content-type: application/json');

$problems = $DB->q("TABLE SELECT probid AS id, name,
                    CONCAT(name, ' (p', probid, ')') AS search FROM problem
                    WHERE (name LIKE %c OR CONCAT('p', probid) LIKE %c)",
                   $_GET['q'], $_GET['q']);

echo json_encode($problems);
