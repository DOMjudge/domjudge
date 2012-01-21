<?php
/**
 * (Dis|En)able verification
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

requireAdmin();

dbconfig_set('disable_verify', !dbconfig_get('disable_verify'));
auditlog('submission', null, (dbconfig_get('disable_verify')?'dis':'en') . 'abled verification');

/* redirect back to index page */
header('Location: index.php');
