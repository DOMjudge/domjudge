<?php declare(strict_types=1);

require('init.php');
header('content-type: application/json');

$languages = $DB->q("TABLE SELECT langid AS id, name,
                     CONCAT(name, ' (', langid, ')') AS search FROM language
                     WHERE (name LIKE %c OR langid LIKE %c)" .
                    (isset($_GET['enabled']) ? " AND allow_submit = 1" : ""),
                    $_GET['q'], $_GET['q']);

if ($_GET['select2'] ?? false) {
    $languages = array_map(function (array $language) {
        return [
            'id' => $language['id'],
            'text' => $language['search'],
        ];
    }, $languages);
    echo dj_json_encode(['results' => $languages]);
} else {
    echo dj_json_encode($languages);
}
