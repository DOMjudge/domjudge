#!/usr/bin/php4 -q
<?php
/**
 * Request a jet unjudged submission from the database, judge it, and pass
 * the results back in to the database.
 *
 * $Id$
 */

require ('../etc/config.php');

$myhost = trim(`hostname`);

define ('SCRIPT_ID', 'judgedaemon');
define ('LOGFILE', LOGDIR.'/judge.'.$myhost.'.log');

require ('../php/init.php');

logmsg(LOG_NOTICE, "Judge started on $myhost");

// Seed the random generator
list($usec,$sec)=explode(" ",microtime());
mt_srand($sec * $usec);

// Retrieve hostname and check database for judger entry
$row = $DB->q('MAYBETUPLE SELECT * FROM judger WHERE name = %s', $myhost);
if ( ! $row ) {
	error("No database entry found for me ($myhost), exiting");
}
$myid = $row['judgerid'];

// Create directory where to test submissions
$tempdirpath = JUDGEDIR."/$myhost";
system("mkdir -p $tempdirpath", $retval);
if ( $retval != 0 ) error("Could not create $tempdirpath");

$waiting = FALSE;
$active = TRUE;

// Constantly check database for unjudged submissions
while ( TRUE ) {

	// Check that this judge is active, else wait and check again later
	$row = $DB->q('TUPLE SELECT * FROM judger WHERE name = %s', $myhost);
	if ( $row['active'] != 1 ) {
		if ( $active ) {
			logmsg(LOG_NOTICE, "Not active, waiting for activation...");
			$active = FALSE;
		}
		sleep(5);
		continue;
	}
	if ( ! $active ) {
		logmsg(LOG_INFO, "Activated, checking queue...");
		$active = TRUE;
		$waiting = FALSE;
	}

	// Generate (unique) random string to mark submission to be judged
	list($usec,$sec)=explode(" ",microtime());
	$mark = "$myhost/$myid".'@'.($sec+$usec).'#'.md5(uniqid(mt_rand(), true));

	// update exactly one submission with our random string
	$numupd = $DB->q('RETURNAFFECTED UPDATE submission
		SET judgerid = %i, judgemark = %s WHERE judgerid IS NULL LIMIT 1', $myid, $mark);

	// nothing updated -> no open submissions
	if ( $numupd == 0 ) {
		if ( ! $waiting ) {
			logmsg(LOG_INFO, "No submissions in queue, waiting...");
			$waiting = TRUE;
		}
		sleep(5);
		continue;
	}
	$waiting = FALSE;

	// get max.runtime, path to submission and other params
	$row = $DB->q('TUPLE SELECT CEILING(time_factor*timelimit) AS runtime,
		s.submitid, s.source, s.langid, testdata
		FROM submission s, problem p, language l
		WHERE s.probid = p.probid AND s.langid = l.langid AND
		judgemark = %s AND judgerid = %i', $mark, $myid);

	logmsg(LOG_NOTICE, "Judging submission $row[submitid]...");

	// update the judging table with our ID and the starttime
	$judgingid = $DB->q('RETURNID INSERT INTO judging (submitid,starttime,judgerid)
		VALUES (%i,NOW(),%i)',
		$row['submitid'], $myid);

	// create tempdir for tempfiles
	$tempdir = "$tempdirpath/$judgingid";
	system("mkdir -p $tempdir", $retval);
	if ( $retval != 0 ) error("Could not create $tempdir");

	// do the actual compile-run-test
	system("./test_solution.sh ".
			SUBMITDIR."/$row[source] $row[langid] ".
			INPUT_ROOT."/$row[testdata]/testdata.in ".
			INPUT_ROOT."/$row[testdata]/testdata.out $row[runtime] $tempdir",
		$retval);

	// what does the exitcode mean?
	if( ! isset($EXITCODES[$retval]) ) {
		error("$row[submitid] Unknown exitcode from test_solution.sh: $retval");
	}
	$result = $EXITCODES[$retval];

	// pop the result back into the judging table
	$DB->q('UPDATE judging
		SET endtime = NOW(), result = %s, output_compile = %s, output_run = %s, output_diff = %s
		WHERE judgingid = %i AND judgerid = %i',
		$result,
		get_content($tempdir.'/compile.out'),
		get_content($tempdir.'/program.out'),
		get_content($tempdir.'/diff.out'),
		$judgingid, $myid);

	// done!
	logmsg(LOG_NOTICE, "Judging $row[submitid]/$judgingid finished, result: $result");

	// restart the judging loop
}

// helperfunction to read 50,000 bytes from a file
function get_content($filename) {

	if ( ! file_exists($filename) ) return '';
	$fh = fopen($filename,'r');
	if ( ! $fh ) {
		error("Could not open $filename for reading");
	}
	return fread($fh, 50000);
}


