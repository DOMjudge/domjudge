<?php

// Default config: should go into runtime configuration file.

define('DJ_CHARACTER_SET', 'utf-8');

define('VERIFICATION_REQUIRED', false);

define('WEBBASEURI', '/domjudge/');

define('SOURCESIZE', 256);

define('LANG_EXTS', 'C,c C++,cpp,cc,c++ Java,java Pascal,pas,p Haskell,hs,lhs Perl,pl Bash,sh');

define('DEBUG', 1);

// What to do with stuff below?

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

// Specify here which of the users in htpasswd-jury should have admin 
// rights on top of their jury rights
$DOMJUDGE_ADMINS = array('domjudge_jury', 'admin');

