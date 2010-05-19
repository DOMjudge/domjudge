<?php
/**
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
require_once('../../etc/config.php');

require_once(SYSTEM_ROOT . '/lib/lib.error.php');
require_once(SYSTEM_ROOT . '/lib/lib.misc.php');
require_once(SYSTEM_ROOT . '/lib/use_db_team.php');

$login = @$_GET['login'];
$tempfile = @$_GET['key'];

// very basic sanity checks on input
if ( empty($login) || empty($tempfile) ||
	preg_match('/[^-A-Za-z0-9_.]/', $login) ||
	preg_match('/[^-A-Za-z0-9_.]/', $tempfile) ||
	strlen($login) > 30 ||
	strlen($tempfile) > 30 ) {
	echo "Invalid input supplied";
	die();
}

$ip = $_SERVER['REMOTE_ADDR'];

// check whether this request makes sense.
$row = $DB->q('MAYBETUPLE SELECT login FROM team WHERE authtoken = %s', $ip);
if ( $row ) {
	if ( $row['login'] == $login ) {
		echo "This team is already registered on this address";
		exit(0);
	} else {
		echo "Error: already a team registered on this address";
		die();
	}
}

$row = $DB->q('MAYBETUPLE SELECT authtoken FROM team WHERE login = %s', $login);
if ( $row ) {
	if ( !empty($row['authtoken']) ) {
		echo "Error: team already registered on a different address";
		die();
	}
} else {
	echo "Error: no team found with this login";
	die();
}

// everything seems ok.

# mark any previous still queued attempts as invalid to prevent flooding the
# daemon with duplicated work
$DB->q('UPDATE team_identify SET state = "invalid", timestamp = NOW()
	WHERE login = %s AND state = "new"', $login);
$DB->q('INSERT INTO team_identify (login, ipaddress, state, timestamp)
	VALUES (%s, %s, "new", NOW()', $login, $ip);

// done
echo "Request queued";
