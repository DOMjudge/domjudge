<?php

require_once("common-config.php");

// Authentication scheme to be used for teams. The following methods
// are supported:
// IPADDRESS
//   Use the computer's IP address to authenticate a team. This
//   removes the hassle of logging in with user/pass, but requires
//   that each team has a unique, fixed IP address, that cannot be
//   spoofed, e.g. by logging in on another team's computer.
// PHP_SESSIONS
//   Use PHP sessions with user/password authentication. This allows
//   teams to login from different machines and might be useful for
//   online contests or programming courses.
// LDAP
//   Authenticate against one or more LDAP servers. Use PHP sessions
//   after successful authentication. This option may be useful to
//   integrate DOMjudge (e.g. as courseware) into a larger system.
// EXTERNAL
//   Use authentication information provided by Apache. This enables
//   use of any authentication module available for Apache, and will
//   get the username from the REMOTE_USER environment variable.
// FIXED
//   Use one fixed user that is automatically logged in. This
//   can be useful e.g. for a demo or testing environment. Define
//   FIXED_USER to the user to be used, e.g.:
//   define('FIXED_USER', 'domjudge');
define('AUTH_METHOD', 'PHP_SESSIONS');

// List of LDAP servers (space separated) to query when using the LDAP
// authentication method. Secondly, DN to search in, where '&' will be
// replaced by the authtoken as set in the team's DOMjudge database entry.
define('LDAP_SERVERS', 'ldaps://ldap1.example.com/ ldaps://ldap2.example.com/');
define('LDAP_DNQUERY', 'CN=&,OU=users,DC=example,DC=com');

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

// Specify URL to external CCS's submission page for linking external
// submission IDs. The external ID is appended to the URL below.
//define('EXT_CCS_URL', 'https://ccs.example.com/submissions/');
