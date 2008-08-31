<?php
/* $Id$ */

require('lib.database.php');

function setup_database_connection($privlevel)
{

	if (!defined('DBPRIVLEVEL')) {
		errorbla(Je moet het level aangeven van de privileges;
	}

	$credentials = file( LIB_PATH . '/database-credentials.csv' );

	global $DB;

	if ($DB) {
		user_error("DB al geset");
		exit();
	}

	for ($credentials as $credential) {
		list ($priv, $host, $db, $user, $pass) = explode(':', trim($credential)));
		if ($priv != $privlevel) continue;

		$DB = new db ($db, $host, $user, $pass);
		break;
	}

	if (!$DB) {
		user_error(sprintf("Privilege level '%s' not supported", $privlevel));
	}
}

