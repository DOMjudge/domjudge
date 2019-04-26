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

        switch ($type = $row['type']) {
        case 'bool':
        case 'int':
            if (!is_int($val)) {
                error("invalid type '$type' for config variable '$key'");
            }
            break;
        case 'string':
            if (!is_string($val)) {
                error("invalid type '$type' for config variable '$key'");
            }
            break;
        case 'array_val':
        case 'array_keyval':
            if (!is_array($val)) {
                error("invalid type '$type' for config variable '$key'");
            }
            break;
        default:
            error("unknown type '$type' for config variable '$key'");
        }

        $LIBDBCONFIG[$key] = array('value' => $val,
                                   'type' => $row['type'],
                                   'public' => $row['public'],
                                   'category' => $row['category'],
                                   'desc' => $row['description']);
    }
}

/**
 * Query configuration variable, with optional default value in case
 * the variable does not exist and boolean to indicate if cached
 * values can be used.
 *
 * When $name is null, then all variables will be returned.
 *
 * Set $onlyifpublic to true to only return a value when this is
 * a variable marked 'public'.
 */
function dbconfig_get(string $name, $default = null, bool $cacheok = true, bool $onlyifpublic = false)
{
    global $LIBDBCONFIG;

    if ((!isset($LIBDBCONFIG)) || (!$cacheok)) {
        dbconfig_init();
    }

    if (is_null($name)) {
        $ret = array();
        foreach ($LIBDBCONFIG as $name => $config) {
            if ( !$onlyifpublic || $config['public'] ) {
                $ret[$name] = $config['value'];
            }
        }
        return $ret;
    }

    if (isset($LIBDBCONFIG[$name]) && (!$onlyifpublic || $LIBDBCONFIG[$name]['public'])) {
        return $LIBDBCONFIG[$name]['value'];
    }

    if ($default===null) {
        error("Configuration variable '$name' not found.");
    }
    return $default;
}
