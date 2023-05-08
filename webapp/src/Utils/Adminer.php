<?php declare(strict_types=1);

namespace App\Utils;

class Adminer extends \Adminer
{
    public function name(): string
    {
        return "DOMjudge DB Admin";
    }

    public function database(): string
    {
        return 'domjudge';
    }

    public function databases($flush = true): array
    {
        return ['domjudge'];
    }

    public function credentials(): array
    {
        // Load credentials from <etcDir>/dbpasswords.secret
        $dbsecretsfile = $GLOBALS['etcDir'] . '/dbpasswords.secret';
        $db_credentials = file($dbsecretsfile);
        foreach ($db_credentials as $line) {
            if ($line[0] == '#') {
                continue;
            }
            list($_, $host, $db, $user, $pass, $port) = array_pad(explode(':', trim($line)), 6, null);
            break;
        }

        return [$host, $user, $pass];
    }

    public function login($login, $password): bool
    {
        return true;
    }

    public function tableName($tableStatus): array|string
    {
        return h($tableStatus['Name']);
    }

    public function permanentLogin($create = false): string
    {
        return 'domjudge';
    }

    public function loginForm()
    {
        echo "<input type='hidden' value='server' name='auth[driver]'/>";
        echo "<input type='hidden' value='domjudge' name='auth[db]'/>";
        echo "<p><input type='submit' value='" . lang('Click to Login') . "'>\n";
    }
}
