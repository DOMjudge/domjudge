<?php declare(strict_types=1);

// This file fetches credentials on the fly from files in etc.
// These settings can later be overridden by Symfony files
// (in order of precedence): .env.local.php .env.local .env.

use Symfony\Component\Dotenv\Dotenv;

function get_db_url(): string
{
    // Allow .env.local to override the DATABASE_URL since it can contain the
    // proper serverVersion, which is needed for automatically creating migrations.
    $localEnvFile = WEBAPPDIR . '/.env.local';
    if (file_exists($localEnvFile)) {
        $dotenv = (new Dotenv())->usePutenv(false);
        $localEnvData = $dotenv->parse(file_get_contents($localEnvFile));
        if (isset($localEnvData['DATABASE_URL'])) {
            return $localEnvData['DATABASE_URL'];
        }
    }

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
        list($_, $host, $db, $user, $pass, $port) = array_pad(explode(':', trim($line)), 6, null);
        break;
    }

    return sprintf('mysql://%s:%s@%s:%d/%s?serverVersion=5.7.0', $user, $pass, $host, $port ?? 3306, $db);
}

function get_app_secret(): string
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
