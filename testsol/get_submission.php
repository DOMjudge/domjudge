#!/usr/bin/php4 -q
<?php
/**
 * Request a jet unjudged submission from the database, judge it, and pass
 * the results back in to the database.
 *
 * $Id$
 */
require ('../etc/config.php');
require ('../php/init.php');

// get my hostname
$myhost = trim(`hostname`);

// who am I, and am I active?
$row = $DB->q('TUPLE SELECT * FROM judger WHERE name = %s', $myhost);
if($row['active'] != 1) {
	error("$myhost I'm not active.");
}

// environment
$MYID = $row['judgerid'];

$ME = $myhost/$MYID;

logmsg ("$ME Judger started");

// ff seeden
list($usec,$sec)=explode(" ",microtime());
mt_srand($sec * $usec);

while (1) {

	$randomding = md5(uniqid(mt_rand(), true));

	// update exactly one submission with our random string...
	$numupd = $DB->q('RETURNAFFECTED UPDATE submission
		SET judger = %i, uniqueding = %s WHERE judger IS NULL LIMIT 1', $MYID, $randomding);

	// nothing updated -> no open submissions
	if($numupd == 0) {
		logmsg("$ME No submissions in queue");
		sleep(5);
		continue;
	}

	// get max.runtime, path to submission and other params
	$row = $DB->q('TUPLE SELECT CEILING(time_factor*timelimit) AS runtime,
		s.submitid, s.source, s.langid, testdata
		FROM submission s, problem p, language l
		WHERE s.probid = p.probid AND s.langid = l.langid AND
		uniqueding = %s AND judger = %i', $randomding, $MYID);

	logmsg("$ME Starting judging of $row[submitid]...");

	// update the judging table with our ID and the starttime
	$judgingid = $DB->q('RETURNID INSERT INTO judging (submitid,starttime,judger)
		VALUES (%i,NOW(),%i)',
		$row['submitid'], $MYID);

	// create tempdir for tempfiles.
	$tempdir = system("mktemp -d -p ".SYSTEN_ROOT."/$myhost", $retval);
	if($retval != 0) {
		error("$ME Could not create tempdir ".SYSTEM_ROOT."/$myhost");
	}

	// do the actual compile-run-test
	system("./test_solution.sh ".
			OUTPUT_ROOT."/submit/$row[source] $row[langid] ".
			SYSTEM_ROOT."/$row[testdata]/testdata.in ".
			SYSTEM_ROOT."/$row[testdata]/testdata.out $row[runtime] $tempdir",
		$retval);

	// what does the exitcode mean?
	if(!isset($EXITCODES[$retval])) {
		error("$ME $row[submitid] Unknown exitcode from test_solution.sh: $retval");
	}
	$result = $EXITCODES[$retval];

	// pop the result back into the judging table
	$DB->q('UPDATE judging
		SET endtime = NOW(), result = %s, output_compile = %s, output_run = %s, output_diff = %s
		WHERE judgingid = %i AND judgeid = %i',
		$result,
		get_content($tempdir.'/compile.out'),
		get_content($tempdir.'/program.out'),
		get_content($tempdir.'/diff.out'),
		$judgingid, $MYID);

	// done!
	logmsg("$ME Juding $judgingid/$row[submitid] finished, result: $result");


}

// helperfunction to read 50,000 bytes from a file
function get_content($filename) {
	global $ME;
	
	if(!file_exists($filename)) return '';
	$fh = fopen($filename,'r');
	if(!$fh) {
		error("$ME Could not open $filename for reading");
	}
	return fread($fh, 50000);
}


