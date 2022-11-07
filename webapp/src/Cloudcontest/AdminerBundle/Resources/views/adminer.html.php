<?php
function adminer_object() {
    class AdminerSoftware extends Adminer {
      function name() {
        return "DOMjudge DB Admin";
      }
      function database() {
        return 'domjudge';
      }
      function databases($flush = true) {
        return ['domjudge'];
      }
      function credentials() {
        // Load credentials from /etc/domjudge/dbpasswords.secret
        $dbsecretsfile = '/etc/domjudge/dbpasswords.secret';
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
      function login($login, $password) {
        return true;
      }
      function tableName($tableStatus) {
        return h($tableStatus['Name']);
      }
      function permanentLogin($create = false) {
        return 'domjudge4cloudcontest';
      }
      function loginForm() {
        echo "<input type='hidden' value='server' name='auth[driver]'/>";
        echo "<input type='hidden' value='domjudge' name='auth[db]'/>";
        echo "<p><input type='submit' value='" . lang('Click to Login') . "'>\n";
      }

    }
    return new AdminerSoftware;
}

// include original Adminer or Adminer Editor
require __DIR__ . "/adminer-4.8.1-mysql.php";
