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

session_set_cookie_params(0, preg_replace('/\/(api|jury|public|team)\/?$/', '/',
                                          dirname($_SERVER['PHP_SELF'])),
                          null, false, true);

$teamid = NULL;
$username = NULL;
$teamdata = NULL;
$userdata = NULL;

// Check if current user has given role, or has superset of this role's
// privileges
function checkrole($rolename, $check_superset = TRUE) {
    global $G_SYMFONY;
    return $G_SYMFONY->checkrole($rolename, $check_superset);
}

// Returns whether the connected user is logged in, sets $username, $teamdata
function logged_in()
{
	global $DB, $ip, $username, $teamid, $teamdata, $userdata;
	if ( !empty($username) && !empty($userdata) && !empty($teamdata) ) return TRUE;
	if ( isset($_SESSION['username']) ) {
		$userdata = $DB->q('MAYBETUPLE SELECT * FROM user
		                    WHERE username = %s AND enabled = 1', $_SESSION['username']);
	}

	if ( !empty($userdata) ) {
		$username = $userdata['username'];
		$teamdata = $DB->q('MAYBETUPLE SELECT * FROM team
		                    WHERE teamid = %i AND enabled = 1', $userdata['teamid']);

		// Pull the list of roles that a user has
		$userdata['roles'] = get_user_roles($userdata['userid']);
	}
	if ( !empty($teamdata) ) {
		$teamid = $teamdata['teamid'];
		// Is this the first visit? Record that in the team table.
		if ( empty($teamdata['teampage_first_visited']) ) {
			$hostname = gethostbyaddr($ip);
			$DB->q('UPDATE team SET teampage_first_visited = %s, hostname = %s
			        WHERE teamid = %i',
			       now(), $hostname, $teamid);
		}
	}

	return $username!==NULL;
}

// Returns whether the active authentication method has logout functionality.
function have_logout()
{
    // symfony always has logout
	return TRUE;
}

function do_register() {
	global $DB, $ip;
	if ( !dbconfig_get('allow_registration', false) ) {
		error("Self-Registration is disabled.");
	}
	if ( AUTH_METHOD != "PHP_SESSIONS" ) {
		error("You can only register if the site is using PHP Sessions for authentication.");
	}

	$login = trim($_POST['login']);
	$pass = trim($_POST['passwd']);
	$pass2 = trim($_POST['passwd2']);

	if ( $login == '' || $pass == '') {
		error("You must enter all fields");
	}

	if ( !ctype_alnum($login) ) {
		error("Username must consist of only alphanumeric characters.");
	}

	if ( $pass != $pass2 ) {
		error("Your passwords do not match. Please go back and try registering again.");
	}
	$user = $DB->q('MAYBETUPLE SELECT * FROM user WHERE username = %s', $login);
	if ( $user ) {
		error("That login is already taken.");
	}
	$team = $DB->q('MAYBETUPLE SELECT * FROM team WHERE name = %s', $login);
	if ( $team ) {
		error("That login is already taken.");
	}

	// Create the team object
	$team = array();
	$team['name'] = $login;
	$team['categoryid'] = 2; // Self-registered category id
	$team['enabled'] = 1;
	$team['comments'] = "Registered by $teamp on " . date('r');

	$teamid = $DB->q("RETURNID INSERT INTO team SET %S", $team);
	auditlog('team', $teamid, 'registered by ' . $ip);

	// Associate a user with the team we just made
	$user = array();
	$user['username'] = $login;
	$user['password'] = dj_password_hash($pass);
	$user['name'] = $login;
	$user['teamid'] = $teamid;
	$newid = $DB->q("RETURNID INSERT INTO user SET %S", $user);
	auditlog('user', $newid, 'registered by ' . $ip);

	$DB->q("INSERT INTO `userrole` (`userid`, `roleid`) VALUES ($newid, 3)");

	$title = 'Account Registered';
	$menu = false;

	require(LIBWWWDIR . '/header.php');
	echo "<h1>Account registered</h1>\n\n<p><a href=\"./\">Click here to login.</a></p>\n\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

function get_user_roles($userid)
{
	global $DB;
	return $DB->q('COLUMN SELECT role.role FROM userrole
	               LEFT JOIN role USING (roleid)
	               WHERE userrole.userid = %s', $userid);
}
