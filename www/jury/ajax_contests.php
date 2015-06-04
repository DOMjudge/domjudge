<?php

require('init.php');
header('content-type: application/json');

$contests = $DB->q("TABLE SELECT cid AS id, name, shortname,
                    CONCAT(name, ' (', shortname, ' - c', cid, ')') AS search
                    FROM contest
                    WHERE (name LIKE %c OR shortname LIKE %c OR
                    CONCAT('c', cid) LIKE %c)" .
                   ( isset($_GET['public']) ? "AND public = %i" : "%_" ),
                   $_GET['q'], $_GET['q'], $_GET['q'], @$_GET['public']);

echo json_encode($contests);
