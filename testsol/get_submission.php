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

// Seed the random generator
list($usec,$sec)=explode(" ",microtime());
mt_srand($sec * $usec);

// Retrieve hostname and check database for judger entry
$myhost = trim(`hostname`);

$row = $DB->q('TUPLE SELECT * FROM judger WHERE name = %s', $myhost);

$myid = $row['judgerid'];
$me = "$myhost/$myid";

logmsg ("$me Judge started");

// Constantly check database for unjudged submissions
while ( 1 ) {

	// Check that this judge is active, else wait and check again later
	$row = $DB->q('TUPLE SELECT * FROM judger WHERE name = %s', $myhost);
	if($row['active'] != 1) {
		logmsg("$me Not active, waiting");
		sleep(15);
		continue;
	}

	// Generate (unique) random string to mark submission to be judged
	$mark = $me.microtime().md5(uniqid(mt_rand(), true));

	// update exactly one submission with our random string
	$numupd = $DB->q('RETURNAFFECTED UPDATE submission
		SET judger = %i, judgemark = %s WHERE judger IS NULL LIMIT 1', $myid, $mark);

	// nothing updated -> no open submissions
	if($numupd == 0) {
		logmsg("$me No submissions in queue");
		sleep(5);
		continue;
	}

	// get max.runtime, path to submission and other params
	$row = $DB->q('TUPLE SELECT CEILING(time_factor*timelimit) AS runtime,
		s.submitid, s.source, s.langid, testdata
		FROM submission s, problem p, language l
		WHERE s.probid = p.probid AND s.langid = l.langid AND
		judgemark = %s AND judger = %i', $mark, $myid);

	logmsg("$me Starting judging of $row[submitid]...");

	// update the judging table with our ID and the starttime
	$judgingid = $DB->q('RETURNID INSERT INTO judging (submitid,starttime,judger)
		VALUES (%i,NOW(),%i)',
		$row['submitid'], $myid);

	// create tempdir for tempfiles
	$tempdirpath = JUDGEDIR."/$myhost/";
	$tempdir = system("mktemp -d -p $tempdirpath $judgingid.XXXX", $retval);
	if($retval != 0) {
		error("$me Could not create tempdir $tempdirpath/$judgingid.XXXX");
	}

	// do the actual compile-run-test
	system("./test_solution.sh ".
			SUBMITDIR."/$row[source] $row[langid] ".
			INPUT_ROOT."/$row[testdata]/testdata.in ".
			INPUT_ROOT."/$row[testdata]/testdata.out $row[runtime] $tempdir",
		$retval);

	// what does the exitcode mean?
	if(!isset($EXITCODES[$retval])) {
		error("$me $row[submitid] Unknown exitcode from test_solution.sh: $retval");
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
		$judgingid, $myid);

	// done!
	logmsg("$me Judging $judgingid/$row[submitid] finished, result: $result");

}

// helperfunction to read 50,000 bytes from a file
function get_content($filename) {
	global $me;
	
	if(!file_exists($filename)) return '';
	$fh = fopen($filename,'r');
	if(!$fh) {
		error("$me Could not open $filename for reading");
	}
	return fread($fh, 50000);
}


