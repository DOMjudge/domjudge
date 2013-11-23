<?php
/**
 * Provide login functionality.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

if ( ! defined('NONINTERACTIVE') ) define('NONINTERACTIVE', false);

if ( @$_POST['cmd']=='login' ) do_login();
if ( !logged_in() ) show_loginpage();

header("Location: ./");
exit;
