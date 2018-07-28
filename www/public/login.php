<?php
/**
 * Provide login functionality.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

if (@$_POST['cmd']=='register') {
    do_register();
}
logged_in();

header("Location: ./");
exit;
