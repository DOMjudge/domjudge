<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2) . '/webapp/vendor/autoload.php';
require dirname(__DIR__) . '/config/load_db_secrets.php';

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}
