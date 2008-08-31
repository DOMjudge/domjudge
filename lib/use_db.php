<?php
/* $Id$ */

require('lib.database.php');

function setup_database_connection($privlevel)
{

	if (!defined('DBPRIVLEVEL')) {
		user_error("Privilege level required",
			E_USER_ERROR);
		exit();
	}

	$credentials = file( WWWETC_PATH . '/database-credentials.csv' );

	global $DB;

	if ($DB) {
		user_error("There already is a database-connection",
			E_USER_ERROR);
		exit();
	}

	for ($credentials as $credential) {
		list ($priv, $host, $db, $user, $pass) = explode(':', trim($credential)));
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

