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
//   Autheticate against one or more LDAP servers. Use PHP sessions
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

// List of auto-detected language extensions by the submit client.
//   Format: 'LANG,MAINEXT[,EXT]... [LANG...]' where:
//   - LANG is the language name displayed,
//   - MAINEXT is the extension corresponding to the langid in DOMjudge,
//   - EXT... are comma separated additional detected language extensions.
// This list only needs to be modified when additional languages are
// added and should be kept in sync with the list in submit-config.h.in.
define('LANG_EXTS', 'C,c C++,cpp,cc,c++ Java,java Pascal,pas,p Haskell,hs,lhs Perl,pl POSIX-shell,sh C#,csharp,cs AWK,awk Python,py Bash,bash');
