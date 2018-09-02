<?php declare(strict_types=1);

require('lib.database.php');

function setup_database_connection()
{
    $credfile = ETCDIR . '/dbpasswords.secret';
    $credentials = @file($credfile);
    if (!$credentials) {
        user_error(
            "Cannot read database credentials file " . $credfile,
            E_USER_ERROR
        );
        exit();
    }

    global $DB;

    if ($DB) {
        user_error(
            "There already is a database-connection",
            E_USER_ERROR
        );
        exit();
    }

    foreach ($credentials as $credential) {
        if ($credential{0} == '#') {
            continue;
        }
        list($priv, $host, $db, $user, $pass) =
            explode(':', trim($credential));
        if ($priv=='team' || $priv=='public') {
            user_error("Found obsolete database privilege:user '$priv:$user'", E_USER_WARNING);
            continue;
        }

        $DB = new db($db, $host, $user, $pass, null, DJ_MYSQL_CONNECT_FLAGS);
        break;
    }

    if (!$DB) {
        user_error("Failed to create database connection", E_USER_ERROR);
        exit();
    }
}
