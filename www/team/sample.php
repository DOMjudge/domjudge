<?php
/**
 * View/edit testcases
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

$tcid = (int) @$_REQUEST['id'];
$in = (int) @$_REQUEST['in'];

require('init.php');

if ( !isset($tcid) ) {
	error("testcase ID not specified.");
}

$INOROUT = array('input','output');

// Download testcase
$fetch = ($in ? 'input' : 'output'); 

$testcase = $DB->q("SELECT probid, rank, OCTET_LENGTH($fetch) AS size FROM testcase WHERE testcaseid=%i", $tcid);
if ( $testcase->count() != 1 ) {
	error("Problem downloading sample data.");
}
$testcase = $testcase->next();
$filename = $testcase['probid'] . $testcase['rank'] . "." . substr($fetch,0,-3);

// sanity check before we start to output headers
if ( $testcase['size']===NULL || !is_numeric($testcase['size'])) error("Problem while fetching testcase");

header("Content-Type: text-plain; name=\"$filename\"");
header("Content-Disposition: inline; filename=\"$filename\"");
header("Content-Length: $size");

// This may not be good enough for large testsets, but streaming them
// directly from the database query result seems overkill to implement.
echo $DB->q("VALUE SELECT SQL_NO_CACHE $fetch FROM testcase
	     WHERE testcaseid=%i", $tcid);

exit(0);
