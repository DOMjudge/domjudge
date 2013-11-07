<?php
/**
 * Download team outputs
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$probid = @$_REQUEST['probid'];

require('init.php');

$runid  = $_GET['runid'];

$row = $DB->q('MAYBETUPLE SELECT OCTET_LENGTH(output_run) as size, rank
               FROM judging_run
               LEFT JOIN testcase USING (testcaseid)
               WHERE runid=%i', $runid);

// sanity check before we start to output headers
if ( $row===NULL || !is_numeric($row['size']) ) error("Problem while fetching team output");

$filename = $probid . $row['rank'] . ".run" . $runid . ".team.out";

header("Content-Type: application/octet-stream; name=\"$filename\"");
header("Content-Disposition: inline; filename=\"$filename\"");
header("Content-Length: $row[size]");

// This may not be good enough for large testsets, but streaming them
// directly from the database query result seems overkill to implement.
echo $DB->q('VALUE SELECT output_run FROM judging_run
             WHERE runid=%i', $runid);

exit(0);
