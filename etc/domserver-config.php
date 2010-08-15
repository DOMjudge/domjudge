<?php

require_once("common-config.php");

// Show compile output in team webinterface.
// Note that this might give teams the possibility to gain information
// about the judging system; e.g. which functions are usable or
// possibly system information through compiler directives.
// 0 = Never
// 1 = Only on compilation error(s)
// 2 = Always
define('SHOW_COMPILE', 2);

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

// List of auto-detected language extensions by the submit client.
//   Format: 'LANG,MAINEXT[,EXT]... [LANG...]' where:
//   - LANG is the language name displayed,
//   - MAINEXT is the extension corresponding to the extension in DOMjudge,
//   - EXT... are comma separated additional detected language extensions.
define('LANG_EXTS', 'C,c C++,cpp,cc,c++ Java,java Pascal,pas,p Haskell,hs,lhs Perl,pl POSIX-shell,sh C#,cs AWK,awk Python,py Bash,bash');

// Specify here which of the users in htpasswd-jury should have admin 
// rights on top of their jury rights
$DOMJUDGE_ADMINS = array('domjudge_jury', 'admin');

// Penalty time in minutes per wrong submission (if finally solved).
define('PENALTY_TIME', 20);

// Internal and output character set used, don't change.
define('DJ_CHARACTER_SET', 'utf-8');

