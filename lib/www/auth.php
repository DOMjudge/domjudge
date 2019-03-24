<?php declare(strict_types=1);
/**
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

require_once(LIBVENDORDIR . '/autoload.php');

// TODO: still used in some import scripts. Refactor them and remove this

// The IP need not be set when included from
// import-{REST,XML}feed scripts, so suppress empty value.
$ip = @$_SERVER['REMOTE_ADDR'];

$teamid = null;
$username = null;
$teamdata = null;
$userdata = null;

// Check if current user has given role, or has superset of this role's
// privileges
function checkrole(string $rolename, bool $check_superset = true) : bool
{
    global $G_SYMFONY, $apiFromInternal;
    if (isset($apiFromInternal) && $apiFromInternal === true) {
        return true;
    }
    return $G_SYMFONY->checkrole($rolename, $check_superset);
}
