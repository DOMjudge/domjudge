<?php declare(strict_types=1);

require_once("common-config.php");

// Cost for the password hashing function. Increase for more secure
// hashes and decrease for speed.
define('PASSWORD_HASH_COST', 10);

// Set this to a notification command, which receives the notification
// text on stdin. Examples below for notification by mail or prints.
//define('BALLOON_CMD', 'mail -s Balloon_notification domjudge@localhost');
//define('BALLOON_CMD', 'lpr');
define('BALLOON_CMD', '');

// Internal and output character set used, don't change (unless you
// know what you're doing).
define('DJ_CHARACTER_SET', 'utf-8');
define('DJ_CHARACTER_SET_MYSQL', 'utf8mb4');
// MySQL default collation setting associated to character set above.
// Note that the DB team.name field has binary collation to be able to
// distinguish/index on team names that differ in capitalization only.
define('DJ_MYSQL_COLLATION', 'utf8mb4_unicode_ci');
// MySQL connection flags.
define('DJ_MYSQL_CONNECT_FLAGS', null);
// To enable SSL/TLS encryption of MySQL connections, use the following.
// Not enabled by default because the server isn't configured to
// accept SSL by default. Not normally necessary if you run the DOMserver
// and database on the same machine.
// define('DJ_MYSQL_CONNECT_FLAGS', MYSQLI_CLIENT_SSL);

// Enable this to support removing time intervals from the contest.
// Although we use this feature at the ICPC World Finals, we strongly
// discourage using it, and we don't guarantee the code is completely
// bug-free. This code is rarely tested!
define('ALLOW_REMOVED_INTERVALS', false);

// Specify URL of the iCAT webinterface. Uncommenting this will enable
// the ICPC Analytics iCAT integration for jury members.
//define('ICAT_URL', 'http://icat.example.com/icat/');
