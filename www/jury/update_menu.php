<?php
/**
 * Supply information for AJAX RPC calls (update the number
 * of new clarifications and judgehost problems in the menu line).
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Wed, 10 Feb 1971 05:00:00 GMT");
header("Content-type: text/plain");

echo json_encode(array($nunread_clars,$nhosts_down));
