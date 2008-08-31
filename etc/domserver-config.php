<?

// Default config

// Set DEBUG as a bitmask of the following settings.
// Of course never to be used on live systems!
//
// Display PHP notice level warnings
define('DEBUG_PHP_NOTICE', 1);
//
// Display timings for loading webpages
define('DEBUG_TIMINGS', 2);
//
// Display SQL queries on webpages
define('DEBUG_SQL', 4);

define('DEBUG', 1);
define('WEBBASEURI', '/domjudge/');

$DOMJUDGE_ADMINS = array();

// Specify here which of the users in htpasswd-jury should have admin 
// rights on top of their jury rights
// $DOMJUDGE_ADMINS[] = 'admin';

