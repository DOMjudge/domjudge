<?php
/* $Id$ */

require('lib.database.php');

function setup_database_connection($privlevel)
{

	$credentials = @file(ETCDIR . '/database-credentials.csv');
	if (!$credentials) {
		user_error("Cannot find database-credentials file in " . ETCDIR,
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
}

