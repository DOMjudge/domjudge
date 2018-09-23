<?php
/**
 * Miscellaneous wrappers for PHP functions, included from lib.misc.php.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Wrapper around PHP setcookie function to automatically set some
 * DOMjudge specific defaults and check the return value.
 * - cookies are defined in a common path for all web interfaces
 */
function dj_setcookie(
    $name,
    $value = null,
    $expire = 0,
                      $path = null,
    $domain = null,
    $secure = false,
    $httponly = false
) {
    if (!isset($path)) {
        // KLUDGE: We want to find the DOMjudge base path, but this
        // information is not directly available as configuration, so
        // we extract it from the executed PHP script.
        $path = preg_replace(
            '/\/(api|jury|public|team)\/?$/',
            '/',
			     dirname($_SERVER['REQUEST_URI'])
        );
    }

    $ret = setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);

    if ($ret!==true) {
        warning("Cookie '$name' not set properly.");
    }

    logmsg(LOG_DEBUG, "Cookie set: $name=$value, expire=$expire, path=$path");

    return $ret;
}

/**
 * Decode a JSON string and handle errors.
 */
function dj_json_decode($str)
{
    $res = json_decode($str, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error("Error decoding JSON data '$str': ".json_last_error_msg());
    }
    return $res;
}

/**
 * Encode data to JSON and handle errors.
 */
function dj_json_encode($data)
{
    $res = json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error("Error encoding data to JSON: ".json_last_error_msg());
    }
    return $res;
}

/**
 * Helperfunction to read all contents from a file.
 * If $maxsize is set to a nonnegative integer, then limit data read
 * to this many bytes, and when the truncating the file, attach a note
 * saying so.
 */
function dj_file_get_contents($filename, $maxsize = -1)
{
    if (! file_exists($filename)) {
        error("File does not exist: $filename");
    }
    if (! is_readable($filename)) {
        error("File is not readable: $filename");
    }

    if ($maxsize >= 0 && filesize($filename) > $maxsize) {
        $res = file_get_contents($filename, false, null, -1, $maxsize);
        if ($res===false) {
            error("Error reading from file: $filename");
        }
        $res .= "\n[output storage truncated after $maxsize B]\n";
    } else {
        $res = file_get_contents($filename);
        if ($res===false) {
            error("Error reading from file: $filename");
        }
    }

    return $res;
}

/**
 * Wrapper around PHP's htmlspecialchars() to set desired options globally:
 *
 * - ENT_QUOTES: Also convert single quotes, in case string is contained
 *   in a single quoted context.
 * - ENT_HTML5: Display those single quotes as the HTML5 entity &apos;.
 * - ENT_SUBSTITUTE: Replace any invalid Unicode characters with the
 *   Unicode replacement character.
 *
 * Additionally, set the character set explicitly to the DOMjudge global
 * character set.
 */
function specialchars($string)
{
    return htmlspecialchars(
        $string,
        ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
        DJ_CHARACTER_SET
    );
}

/**
 * Wrappers around the PHP password hashing functions. These check if
 * it is still an old hash of the form md5($user.'#'.$password), and
 * hash using our default settings. Note that dj_password_verify() has
 * an extra parameter $user relative to the PHP native function.
 */
function dj_password_verify($password, $hash, $user = null)
{
    // First check for old-style MD5 hashes:
    if (!empty($user) && strlen($hash)>0 && $hash[0]!=='$') {
        $userhash = md5($user."#".$password);
        return hash_equals($hash, $userhash);
    }
    return password_verify($password, $hash);
}

function dj_password_needs_rehash($hash)
{
    // First check for an old-style MD5 hash:
    if (strlen($hash)>0 && $hash[0]!=='$') {
        // Check that the SQL structure has been upgraded so that the
        // new, longer hashes actually fit.
        global $DB;
        $res = $DB->q('MAYBETUPLE SHOW COLUMNS FROM user WHERE Field = %s', 'password');
        $matches = array();
        if (!isset($res['Type']) ||
            !preg_match('/^varchar\(([0-9]+)\)$/i', $res['Type'], $matches)) {
            error("Could not parse DB table 'user' information");
        }
        $len = (int)$matches[1];
        if ($len<255) {
            error("DB column 'user.password' has length $len; " .
                  "upgrade your DB structure to support storing new, longer hashes");
        }
        return true;
    }
    return password_needs_rehash(
        $hash,
        PASSWORD_DEFAULT,
                                 array('cost' => PASSWORD_HASH_COST)
    );
}

function dj_password_hash($password)
{
    return password_hash(
        $password,
        PASSWORD_DEFAULT,
                         array('cost' => PASSWORD_HASH_COST)
    );
}
