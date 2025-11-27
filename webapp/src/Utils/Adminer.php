<?php declare(strict_types=1);

namespace App\Utils;

use function Adminer\h;
use function Adminer\lang;

class Adminer extends \Adminer\Adminer
{
    public function name(): string
    {
        return "DOMjudge DB Admin";
    }

    public function database(): string
    {
        return $this->getDatabaseCredentials()['db'];
    }

    public function databases($flush = true): array
    {
        return [$this->getDatabaseCredentials()['db']];
    }

    public function credentials(): array
    {
        ['host' => $host, 'user' => $user, 'pass' => $pass] = $this->getDatabaseCredentials();

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
        $db = $this->getDatabaseCredentials()['db'];
        echo "<input type='hidden' value='server' name='auth[driver]'/>";
        echo "<input type='hidden' value='$db' name='auth[db]'/>";
        echo "<p><input type='submit' value='" . lang('Click to Login') . "'>\n";
    }

    /**
     * @return array{host: string, db: string, user: string, pass: string}
     */
    private function getDatabaseCredentials(): array
    {
        $host = $db = $user = $pass = null;

        // Load credentials from <etcDir>/dbpasswords.secret
        $dbsecretsfile = $GLOBALS['etcDir'] . '/dbpasswords.secret';
        $db_credentials = file($dbsecretsfile);
        foreach ($db_credentials as $line) {
            if ($line[0] == '#') {
                continue;
            }
            [$_, $host, $db, $user, $pass] = array_pad(explode(':', trim($line)), 6, null);
            break;
        }

        if ($host === null) {
            throw new \LogicException("Can't get DB credentials");
        }

        return ['host' => $host, 'db' => $db, 'user' => $user, 'pass' => $pass];
    }
}
