<?php

require('init.php');
header('content-type: application/json');

if (isset($_GET['fromcontest'])) {
    $problems = $DB->q("TABLE SELECT problem.probid AS id, problem.name,
                        CONCAT(problem.name, ' (', contestproblem.shortname, ' - p', problem.probid, ')') AS search
                        FROM problem
                        INNER JOIN contestproblem USING(probid)
                        WHERE (problem.name LIKE %c OR CONCAT('p', problem.probid) LIKE %c OR
                               contestproblem.shortname LIKE %c)
                        AND contestproblem.cid = %i",
                       $_GET['q'], $_GET['q'], $_GET['q'], $_GET['fromcontest']);
} else {
    $problems = $DB->q("TABLE SELECT probid AS id, name,
                        CONCAT(name, ' (p', probid, ')') AS search FROM problem
                        WHERE (name LIKE %c OR CONCAT('p', probid) LIKE %c)",
                       $_GET['q'], $_GET['q']);
}

echo dj_json_encode($problems);
