<?php
/**
 * Code to import and export tsv formats as specified by the ICPC
 * Contest Control System Standard.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');
require(LIBDIR . '/lib.impexp.php');
$title = "TSV Import";

requireAdmin();

$fmt = @$_REQUEST['fmt'];
$act = @$_REQUEST['act'];

if ($act == 'im') {
    require(LIBWWWDIR . '/header.php');
    tsv_import($fmt);
    require(LIBWWWDIR . '/footer.php');
} elseif ($act == 'ex') {
    tsv_export($fmt);
} else {
    error("Unknown action.");
}
