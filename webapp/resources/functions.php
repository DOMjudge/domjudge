<?php declare(strict_types=1);

use Adminer\Adminer;
use function Adminer\h;
use function Adminer\lang;

if (!function_exists('adminer_object')) {
    function adminer_object(): Adminer
    {
        // Use anonymous class to extend Adminer with our own settings.
        // We can't use a normal class since the base class only exists when
        // the adminer code is included in JuryMiscController.
        return new class extends Adminer {
            public function name(): string
            {
                return "DOMjudge DB Admin";
            }

            public function database(): string
            {
                return $this->getDatabaseCredentials()['db'];
            }

            /**
             * Note: the $flush parameter comes from the parent class.
             *
             * @return list<string>
             */
            public function databases(bool $flush = true): array
            {
                return [$this->getDatabaseCredentials()['db']];
            }

            /**
             * @return array{string, string, string}
             */
            public function credentials(): array
            {
                ['host' => $host, 'user' => $user, 'pass' => $pass] = $this->getDatabaseCredentials();

                return [$host, $user, $pass];
            }

            /**
             *  Note: the $login and $password parameters comes from the parent class.
             */
            public function login(string $login, string $password): bool
            {
                return true;
            }

            /**
             *  Note: the $create parameter comes from the parent class.
             */
            public function permanentLogin(bool $create = false): string
            {
                return 'domjudge';
            }

            public function loginForm(): void
            {
                ['db' => $db, 'user' => $user] = $this->getDatabaseCredentials();
                echo "<input type='hidden' value='server' name='auth[driver]'/>";
                echo "<input type='hidden' value='$user' name='auth[username]'/>";
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
        };
    }
}
