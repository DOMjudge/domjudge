<?php

require('lib.database.php');

function setup_database_connection($privlevel)
{
	$credfile = ETCDIR . '/dbpasswords.secret';
	$credentials = @file($credfile);
	if (!$credentials) {
		user_error("Cannot read database credentials file " . $credfile,
			E_USER_ERROR);
		exit();
	}

	global $DB;

	if ($DB) {
		user_error("There already is a database-connection",
			E_USER_ERROR);
		exit();
	}

	foreach ($credentials as $credential) {
		if ( $credential{0} == '#' ) continue;
		list ($priv, $host, $db, $user, $pass) =
			explode(':', trim($credential));
		if ($priv != $privlevel) continue;

		$DB = new db ($db, $host, $user, $pass);
		break;
	}

	if (!$DB) {
		user_error(sprintf("Privilege level '%s' not supported",
			$privlevel), E_USER_ERROR);
		exit();
	}

	$DB->q('SET NAMES %s', DJ_CHARACTER_SET_MYSQL);
}

