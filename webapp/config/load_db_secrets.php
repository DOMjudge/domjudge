<?php declare(strict_types=1);

// This file fetches credentials on the fly from files in etc.
// These settings can later be overridden by Symfony files
// (in order of precedence): .env.local.php .env.local .env.

function get_db_url()
{
    $dbsecretsfile = ETCDIR . '/dbpasswords.secret';
    $db_credentials = @file($dbsecretsfile);
    if (!$db_credentials) {
        # Make sure that this fails with a clear error in Symfony.
        return 'mysql://cannot_read_dbpasswords_secret:@localhost:3306/';
    }

    foreach ($db_credentials as $line) {
        if ($line[0] == '#') {
            continue;
        }
        list($dummy, $host, $db, $user, $pass) = explode(':', trim($line));
        break;
    }

    return sprintf('mysql://%s:%s@%s:3306/%s', $user, $pass, $host, $db);
}

function get_app_secret()
{
    $appsecretsfile = ETCDIR . '/symfony_app.secret';
    $contents = file_get_contents($appsecretsfile);
    if ($contents === false) {
        return '';
    }

    return trim($contents);
}

$env = [
    'APP_SECRET'   => get_app_secret(),
    'DATABASE_URL' => get_db_url(),
];

foreach ($env as $k => $v) {
    $_ENV[$k] = $_ENV[$k] ?? (isset($_SERVER[$k]) && 0 !== strpos($k, 'HTTP_') ? $_SERVER[$k] : $v);
}
