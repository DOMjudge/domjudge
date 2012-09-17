<?php
/**
 * View/download a problem text
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

putProblemText(@$_REQUEST['id']);
