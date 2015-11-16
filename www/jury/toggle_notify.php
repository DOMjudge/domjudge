<?php
/**
 * Toggle sending of HTML5 notifications.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

if ( empty($_SERVER['HTTP_REFERER']) ) die("Missing referrer header.");

dj_setcookie('domjudge_notify', $_REQUEST['enable']);

header('Location: ' . $_SERVER['HTTP_REFERER']);
