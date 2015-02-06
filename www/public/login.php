<?php
/**
 * Provide login functionality.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

if ( @$_POST['cmd']=='login' ) do_login();
if ( @$_POST['cmd']=='register' ) do_register();
if ( !logged_in() ) show_loginpage();

header("Location: ./");
exit;
