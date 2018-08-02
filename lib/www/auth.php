<?php
/**
 * This file provides all functionality for authenticating teams. The
 * authentication method used is configured with the AUTH_METHOD
 * variable. When a team is succesfully authenticated, $username is set
 * to the team ID and $teamdata contains the corresponding row from
 * the database. $ip is set to the remote IP address used.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once(LIBVENDORDIR . '/autoload.php');

// In ICPC-live branch the IP need not be set when included from
// import-{REST,XML}feed scripts, so suppress empty value.
$ip = @$_SERVER['REMOTE_ADDR'];

session_name('domjudge_session');

session_set_cookie_params(
    0,
    preg_replace(
    '/\/(api|jury|public|team)\/?$/',
    '/',
                                          dirname($_SERVER['PHP_SELF'])
),
                          null,
    false,
    true
);

$teamid = null;
$username = null;
$teamdata = null;
$userdata = null;

// Check if current user has given role, or has superset of this role's
// privileges
function checkrole($rolename, $check_superset = true)
{
    global $G_SYMFONY, $apiFromInternal;
    if (isset($apiFromInternal) && $apiFromInternal === true) {
        return true;
    }
    return $G_SYMFONY->checkrole($rolename, $check_superset);
}

// Returns whether the connected user is logged in, sets $username, $teamdata
function logged_in()
{
    global $DB, $ip, $username, $teamid, $teamdata, $userdata;
    if (!empty($username) && !empty($userdata) && !empty($teamdata)) {
        return true;
    }
    if (isset($_SESSION['username'])) {
        $userdata = $DB->q('MAYBETUPLE SELECT * FROM user
                            WHERE username = %s AND enabled = 1', $_SESSION['username']);
    }

    if (!empty($userdata)) {
        $username = $userdata['username'];
        $teamdata = $DB->q('MAYBETUPLE SELECT * FROM team
                            WHERE teamid = %i AND enabled = 1', $userdata['teamid']);

        // Pull the list of roles that a user has
        $userdata['roles'] = get_user_roles($userdata['userid']);
    }
    if (!empty($teamdata)) {
        $teamid = $teamdata['teamid'];
        // Is this the first visit? Record that in the team table.
        if (empty($teamdata['teampage_first_visited'])) {
            $hostname = empty($ip) ? 'PHPUNIT' : gethostbyaddr($ip);
            $DB->q('UPDATE team SET teampage_first_visited = %s, hostname = %s
                    WHERE teamid = %i',
                   now(), $hostname, $teamid);
        }
    }

    return $username!==null;
}

// Returns whether the active authentication method has logout functionality.
function have_logout()
{
    // symfony always has logout
    return true;
}

function get_user_roles($userid)
{
    global $DB;
    return $DB->q('COLUMN SELECT role.role FROM userrole
                   LEFT JOIN role USING (roleid)
                   WHERE userrole.userid = %s', $userid);
}
