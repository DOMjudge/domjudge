<?php
/**
 * Download team outputs
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

$probid = @$_REQUEST['probid'];

require('init.php');

$runid  = $_GET['runid'];

$size = $DB->q('MAYBEVALUE SELECT OCTET_LENGTH(output_run) FROM judging_run
                WHERE runid=%i', $runid);

// sanity check before we start to output headers
if ( $size===NULL || !is_numeric($size)) error("Problem while fetching team output");

$filename = $probid . $runid . ".team.out";

header("Content-Type: application/octet-stream; name=\"$filename\"");
header("Content-Disposition: inline; filename=\"$filename\"");
header("Content-Length: $size");

// This may not be good enough for large testsets, but streaming them
// directly from the database query result seems overkill to implement.
echo $DB->q('VALUE SELECT output_run FROM judging_run
                WHERE runid=%i', $runid);

exit(0);
