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
// FIXED
//   Use one fixed team user that is automatically logged in. This
//   can be useful e.g. for a demo or testing environment. Define
//   FIXED_TEAM to the team user to be used, e.g.:
//   define('FIXED_TEAM', 'domjudge');
define('AUTH_METHOD', 'PHP_SESSIONS');

// Strict checking of team's IP addresses (when using the IPADDRESS
// authentication method).
// The commandline submitdaemon can optionally check for correct source
// IP of teams (additionally to the security of "callback" via scp, see
// the admin manual appendix on the submitdaemon).
// The 'false' setting allows automatic updating of IP addresses during
// submission of teams that have their address unset. Otherwise these
// addresses have to be configured beforehand.
define('STRICTIPCHECK', false);

// List of LDAP servers (space separated) to query when using the LDAP
// authentication method. Secondly, DN to search in, where '&' will be
// replaced by the authtoken as set in the team's DOMjudge database entry.
define('LDAP_SERVERS', 'ldaps://ldap1.example.com/ ldaps://ldap2.example.com/');
define('LDAP_DNQUERY', 'CN=&,OU=users,DC=example,DC=com');

// Specify here which of the users in htpasswd-jury should have admin 
// rights on top of their jury rights
$DOMJUDGE_ADMINS = array('domjudge_jury', 'admin');

// Set this to a notification command, which receives the notification
// text on stdin. Examples below for notification by mail or prints.
//define('BALLOON_CMD', 'mail -s Balloon_notification domjudge@localhost');
//define('BALLOON_CMD', 'lpr');
define('BALLOON_CMD', '');

// After what delay of a judgehost not checking in should its status
// start displaying as warning or critical.
define('JUDGEHOST_WARNING', 30);
define('JUDGEHOST_CRITICAL', 120);
