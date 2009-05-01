<?php

require_once("common-config.php");

define('WEBBASEURI', '/domjudge/');

define('SHOW_AFFILIATIONS', true);

define('LANG_EXTS', 'C,c C++,cpp,cc,c++ Java,java Pascal,pas,p Haskell,hs,lhs Perl,pl Bash,sh');

define('ENABLE_CMDSUBMIT_SERVER', true);
define('ENABLE_WEBSUBMIT_SERVER', true);

// Specify here which of the users in htpasswd-jury should have admin 
// rights on top of their jury rights
$DOMJUDGE_ADMINS = array('domjudge_jury', 'admin');

