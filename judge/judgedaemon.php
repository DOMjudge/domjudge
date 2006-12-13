#!/usr/bin/php -q
<?php

/**
 * Request a yet unjudged submission from the database, judge it, and pass
 * the results back in to the database.
 *
 * $Id$
 */

require ('../etc/config.php');

$waittime = 5;

$myhost = trim(`hostname --short`);

define ('SCRIPT_ID', 'judgedaemon');
define ('LOGFILE', LOGDIR.'/judge.'.$myhost.'.log');

require (SYSTEM_ROOT . '/lib/init.php');

$verbose = LOG_INFO;

$cid = getCurContest();

logmsg(LOG_NOTICE, "Judge started on $myhost [DOMjudge/".DOMJUDGE_VERSION."]");

// Seed the random generator
list($usec, $sec) = explode( " ", microtime() );
mt_srand( $sec * $usec );

// Retrieve hostname and check database for judgehost entry
$row = $DB->q('MAYBETUPLE SELECT * FROM judgehost WHERE hostname = %s', $myhost);
if ( ! $row ) {
	error("No database entry found for me ($myhost), exiting");
}

// Create directory where to test submissions
$tempdirpath = JUDGEDIR . "/$myhost";
system("mkdir -p $tempdirpath", $retval);
if ( $retval != 0 ) error("Could not create $tempdirpath");

$waiting = FALSE;
$active = TRUE;

// Constantly check database for unjudged submissions
while ( TRUE ) {

	// Check that this judge is active, else wait and check again later
	$row = $DB->q('TUPLE SELECT * FROM judgehost WHERE hostname = %s', $myhost);
	if ( $row['active'] != 1 ) {
		if ( $active ) {
			logmsg(LOG_NOTICE, "Not active, waiting for activation...");
			$active = FALSE;
		}
		sleep($waittime);
		continue;
	}
	if ( ! $active ) {
		logmsg(LOG_INFO, "Activated, checking queue...");
		$active = TRUE;
		$waiting = FALSE;
	}

	$contdata = getCurContest(TRUE);
	$newcid = $contdata['cid'];
	$oldcid = $cid;
	if ( $oldcid !== $newcid ) {
		logmsg(LOG_NOTICE, "Contest has changed from " .
		       (isset($oldcid) ? "c$oldcid" : "none" ) . " to " .
		       (isset($newcid) ? "c$newcid" : "none" ) );
		$cid = $newcid;
	}
	
	// we have to check for the judgability of problems/languages this way,
	// because we use an UPDATE below where joining is not possible.
	$probs = $DB->q('COLUMN SELECT probid FROM problem WHERE allow_judge = 1');
	if( count($probs) == 0 ) {
		logmsg(LOG_NOTICE, "No judgable problems, waiting...");
		sleep($waittime);
		continue;
	}
	$judgable_prob = array_unique(array_values($probs));
	$langs = $DB->q('COLUMN SELECT langid FROM language WHERE allow_judge = 1');
	if( count($langs) == 0 ) {
		logmsg(LOG_NOTICE, "No judgable languages, waiting...");
		sleep($waittime);
		continue;
	}
	$judgable_lang = array_unique(array_values($langs));

	// Generate (unique) random string to mark submission to be judged
	list($usec, $sec) = explode(" ", microtime());
	$mark = $myhost.'@'.($sec+$usec).'#'.md5( uniqid( mt_rand(), true ) );

	// update exactly one submission with our random string
	$numupd = $DB->q('RETURNAFFECTED UPDATE submission
		SET judgehost = %s, judgemark = %s WHERE judgehost IS NULL
		AND cid = %i AND langid IN (%As) AND probid IN (%As)
		AND submittime <= %s LIMIT 1',
		$myhost, $mark, $cid, $judgable_lang, $judgable_prob, $contdata['endtime']);

	// nothing updated -> no open submissions
	if ( $numupd == 0 ) {
		if ( ! $waiting ) {
			logmsg(LOG_INFO, "No submissions in queue, waiting...");
			$waiting = TRUE;
		}
		sleep($waittime);
		continue;
	}

	$waiting = FALSE;

	// get max.runtime, path to submission and other params
	$row = $DB->q('TUPLE SELECT CEILING(time_factor*timelimit) AS runtime,
		s.submitid, s.sourcefile, s.langid, s.team, s.probid,
		p.testdata, p.special_run, p.special_compare
		FROM submission s, problem p, language l
		WHERE s.probid = p.probid AND s.langid = l.langid AND
		judgemark = %s AND judgehost = %s', $mark, $myhost);

	logmsg(LOG_NOTICE, "Judging submission s$row[submitid] ".
	       "($row[team]/$row[probid]/$row[langid])...");

	// update the judging table with our ID and the starttime
	$judgingid = $DB->q('RETURNID INSERT INTO judging (submitid,cid,starttime,judgehost)
	                     VALUES (%i,%i,NOW(),%s)', $row['submitid'], $cid, $myhost);

	// create tempdir for tempfiles
	$tempdir = "$tempdirpath/c$cid-s$row[submitid]-j$judgingid";
	system("mkdir -p $tempdir", $retval);
	if ( $retval != 0 ) error("Could not create $tempdir");

	// do the actual compile-run-test
	system("./test_solution.sh " .
			SUBMITDIR."/$row[sourcefile] $row[langid] " .
			INPUT_ROOT."/$row[testdata]/testdata.in " .
			INPUT_ROOT."/$row[testdata]/testdata.out " .
		   "$row[runtime] $tempdir " .
		   "'$row[special_run]' '$row[special_compare]'",
		$retval);

	// what does the exitcode mean?
	if( ! isset($EXITCODES[$retval]) ) {
		system(BEEP_CMD." ".BEEP_ERROR." &");
		error("s$row[submitid] Unknown exitcode from test_solution.sh: $retval");
	}
	$result = $EXITCODES[$retval];

	// Start a transaction. This will provide extra safety if the table type
	// supports it.
	$DB->q('START TRANSACTION');
	// pop the result back into the judging table
	$DB->q('UPDATE judging SET endtime = NOW(), result = %s,
	        output_compile = %s, output_run = %s, output_diff = %s, output_error = %s
	        WHERE judgingid = %i AND judgehost = %s',
	       $result,
	       getFileContents( $tempdir . '/compile.out' ),
	       getFileContents( $tempdir . '/program.out' ),
	       getFileContents( $tempdir . '/compare.out' ),
	       getFileContents( $tempdir . '/error.out' ),
	       $judgingid, $myhost);

	// recalculate the scoreboard cell (team,problem) after this judging
	calcScoreRow($cid, $row['team'], $row['probid']);

	$DB->q('COMMIT');
	
	// done!
	logmsg(LOG_NOTICE, "Judging s$row[submitid]/j$judgingid finished, result: $result");
	if ( $result == 'correct' ) {
		system(BEEP_CMD." ".BEEP_ACCEPT." &");
	} else {
		system(BEEP_CMD." ".BEEP_REJECT." &");
	}

	// restart the judging loop
}
