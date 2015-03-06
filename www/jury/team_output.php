<?php
/**
 * Download team outputs
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$runid  = (int)$_GET['runid'];
$cid    = (int)$_GET['cid'];

$row = $DB->q('MAYBETUPLE SELECT OCTET_LENGTH(r.output_run) as size, t.rank,
                                 cp.shortname, p.probid, s.teamid
               FROM judging_run r
               LEFT JOIN testcase t USING (testcaseid)
               LEFT JOIN judging j USING (judgingid)
               LEFT JOIN problem p USING (probid)
               LEFT JOIN contestproblem cp ON (cp.probid=t.probid AND cp.cid=%i)
               LEFT JOIN submission s USING (submitid)
               WHERE runid=%i', $cid, $runid);

// sanity check before we start to output headers
if ( $row===NULL || !is_numeric($row['size']) ) error("Problem while fetching team output");

$filename = 'p' . $row['probid'] . '.t' . $row['rank'] . '.' .
            $row['shortname'] . '.run' . $runid . '.team' . $row['teamid'] . '.out';

header("Content-Type: application/octet-stream; name=\"$filename\"");
header("Content-Disposition: inline; filename=\"$filename\"");
header("Content-Length: $row[size]");

// This may not be good enough for large testsets, but streaming them
// directly from the database query result seems overkill to implement.
echo $DB->q('VALUE SELECT output_run FROM judging_run
             WHERE runid=%i', $runid);

exit(0);
