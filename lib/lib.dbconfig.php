<?php declare(strict_types=1);
/**
 * Functions for handling database stored configuration.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Read configuration variables from DB configuration table and store
 * in global variable for later use.
 */
function dbconfig_init()
{
    global $LIBDBCONFIG, $DB;

    $LIBDBCONFIG = array();
    $res = $DB->q('SELECT * FROM configuration');

    while ($row = $res->next()) {
        $key = $row['name'];
        $val = dj_json_decode($row['value']);

        switch (json_last_error()) {
        case JSON_ERROR_NONE:
            break;
        case JSON_ERROR_DEPTH:
            error("JSON config '$key' decode: maximum stack depth exceeded");
            // no break
        case JSON_ERROR_STATE_MISMATCH:
            error("JSON config '$key' decode: underflow or the modes mismatch");
            // no break
        case JSON_ERROR_CTRL_CHAR:
            error("JSON config '$key' decode: unexpected control character found");
            // no break
        case JSON_ERROR_SYNTAX:
            error("JSON config '$key' decode: syntax error, malformed JSON");
            // no break
        case JSON_ERROR_UTF8:
            error("JSON config '$key' decode: malformed UTF-8 characters, possibly incorrectly encoded");
            // no break
        default:
            error("JSON config '$key' decode: unknown error");
        }

        // TODO: here used to be a check for type. We have removed this after
        // moving database configuration to a YAML file. This whole function
        // should be removed at some point anyway and we only use it in legacy
        // calls. Also we dont store the type and public values anymore, but
        // we don't need them in the legacy code.

        $LIBDBCONFIG[$key] = array('value' => $val);
    }
}

/**
 * Query configuration variable, with optional default value in case
 * the variable does not exist and boolean to indicate if cached
 * values can be used.
 *
 * When $name is null, then all variables will be returned.
 */
function dbconfig_get(string $name, $default = null, bool $cacheok = true)
{
    global $LIBDBCONFIG;

    if ((!isset($LIBDBCONFIG)) || (!$cacheok)) {
        dbconfig_init();
    }

    if (is_null($name)) {
        $ret = array();
        foreach ($LIBDBCONFIG as $name => $config) {
            $ret[$name] = $config['value'];
        }
        return $ret;
    }

    if (isset($LIBDBCONFIG[$name])) {
        return $LIBDBCONFIG[$name]['value'];
    }

    if ($default===null) {
        error("Configuration variable '$name' not found.");
    }
    return $default;
}
