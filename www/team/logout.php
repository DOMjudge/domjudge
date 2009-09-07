<?php
/**
 * This page destroys the PHP session and the corresponding cookie.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Logout';

if ( isset($_COOKIE[session_name()]) ) {
    setcookie(session_name(), '', time()-42000, '/');
}
session_destroy();

require(LIBWWWDIR . '/header.php');

echo "<h1>Logged out</h1>\n\n<p>Successfully logged out.</p>\n\n";

require(LIBWWWDIR . '/footer.php');
