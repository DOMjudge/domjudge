#!/usr/bin/php -q
<?php

/**
 * Request a yet unjudged submission from the database, judge it, and pass
 * the results back in to the database.
 *
 * $Id$
 */

require ('../etc/config.php');

$myhost = trim(`hostname`);

define ('SCRIPT_ID', 'judgedaemon');
define ('LOGFILE', LOGDIR.'/judge.'.trim(`hostname --short`).'.log');

require ('../lib/init.php');

$verbose = LOG_INFO;

logmsg(LOG_NOTICE, "Judge started on $myhost");

// Seed the random generator
list($usec,$sec)=explode(" ",microtime());
mt_srand($sec * $usec);

// Retrieve hostname and check database for judger entry
$row = $DB->q('MAYBETUPLE SELECT * FROM judger WHERE judgerid = %s', $myhost);
if ( ! $row ) {
	error("No database entry found for me ($myhost), exiting");
}

// Create directory where to test submissions
$tempdirpath = JUDGEDIR."/$myhost";
system("mkdir -p $tempdirpath", $retval);
if ( $retval != 0 ) error("Could not create $tempdirpath");

$waiting = FALSE;
$active = TRUE;

// Constantly check database for unjudged submissions
while ( TRUE ) {

	// Check that this judge is active, else wait and check again later
	$row = $DB->q('TUPLE SELECT * FROM judger WHERE judgerid = %s', $myhost);
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

	$cid = $DB->q('MAYBETUPLE SELECT cid FROM contest ORDER BY starttime DESC LIMIT 1');
	if( ! $cid ) {
		error("No contest found in database, aborting.");
	}
	// we have to check for the judgability of problems/languages this way,
	// because we use an UPDATE below where joining is not possible.
	$probs = $DB->q('COLUMN SELECT probid FROM problem WHERE allow_judge = 1');
	if( count($probs) == 0 ) {
			logmsg(LOG_NOTICE, "No judgable problems, waiting...");
			sleep(5); continue;
	}
	$judgable_prob = array_unique(array_values($probs));
	$langs = $DB->q('COLUMN SELECT langid FROM language WHERE allow_judge = 1');
	if( count($langs) == 0 ) {
			logmsg(LOG_NOTICE, "No judgable languages, waiting...");
			sleep(5); continue;
	}
	$judgable_lang = array_unique(array_values($langs));

	// Generate (unique) random string to mark submission to be judged
	list($usec,$sec)=explode(" ",microtime());
	$mark = $myhost.'@'.($sec+$usec).'#'.md5(uniqid(mt_rand(), true));

	// update exactly one submission with our random string
	$numupd = $DB->q('RETURNAFFECTED UPDATE submission
		SET judgerid = %s, judgemark = %s
		WHERE judgerid IS NULL AND cid = %i
			AND language IN (%As) AND problem IN (%As)
		LIMIT 1', $myhost, $mark, $cid, $judgeable_lang, $judgable_prob);

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
		s.submitid, s.sourcefile, s.langid, testdata
		FROM submission s, problem p, language l
		WHERE s.probid = p.probid AND s.langid = l.langid AND
		judgemark = %s AND judgerid = %s', $mark, $myhost);

	logmsg(LOG_NOTICE, "Judging submission s$row[submitid]...");

	// update the judging table with our ID and the starttime
	$judgingid = $DB->q('RETURNID INSERT INTO judging (submitid,cid,starttime,judgerid)
		VALUES (%i,%i,NOW(),%s)',
		$row['submitid'], $cid, $myhost);

	// create tempdir for tempfiles
	$tempdir = "$tempdirpath/c$cid/j$judgingid";
	system("mkdir -p $tempdir", $retval);
	if ( $retval != 0 ) error("Could not create $tempdir");

	// do the actual compile-run-test
	system("./test_solution.sh ".
			SUBMITDIR."/$row[sourcefile] $row[langid] ".
			INPUT_ROOT."/$row[testdata]/testdata.in ".
			INPUT_ROOT."/$row[testdata]/testdata.out $row[runtime] $tempdir",
		$retval);

	// what does the exitcode mean?
	if( ! isset($EXITCODES[$retval]) ) {
		system(SYSTEM_ROOT."/bin/beep ".BEEP_ERROR." &");
		error("$row[submitid] Unknown exitcode from test_solution.sh: $retval");
	}
	$result = $EXITCODES[$retval];

	// pop the result back into the judging table
	$DB->q('UPDATE judging
		SET endtime = NOW(), result = %s,
			output_compile = %s, output_run = %s, output_diff = %s, output_error = %s
		WHERE judgingid = %i AND judgerid = %s',
		$result,
		get_content($tempdir.'/compile.out'),
		get_content($tempdir.'/program.out'),
		get_content($tempdir.'/diff.out'),
		get_content($tempdir.'/error.out'),
		$judgingid, $myhost);

	// done!
	logmsg(LOG_NOTICE, "Judging s$row[submitid]/j$judgingid finished, result: $result");
	if ( $result == 'correct' ) {
		system(SYSTEM_ROOT."/bin/beep ".BEEP_ACCEPT." &");
	} else {
		system(SYSTEM_ROOT."/bin/beep ".BEEP_REJECT." &");
	}

	// restart the judging loop
}


