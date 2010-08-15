<?php
/**
 * Request a yet unjudged submission from the database, judge it, and pass
 * the results back in to the database.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require(ETCDIR . '/judgehost-config.php');

// Set environment variables for passing path configuration to called programs
putenv('DJ_BINDIR='      . BINDIR);
putenv('DJ_ETCDIR='      . ETCDIR);
putenv('DJ_JUDGEDIR='    . JUDGEDIR);
putenv('DJ_LIBDIR='      . LIBDIR);
putenv('DJ_LIBJUDGEDIR=' . LIBJUDGEDIR);
putenv('DJ_LOGDIR='      . LOGDIR);

// Set other configuration variables for called programs
putenv('RUNUSER='       . RUNUSER);
putenv('USE_CHROOT='    . (USE_CHROOT ? '1' : ''));
putenv('CHROOT_SCRIPT=' . CHROOT_SCRIPT);
putenv('COMPILETIME='   . COMPILETIME);
putenv('MEMLIMIT='      . MEMLIMIT);
putenv('FILELIMIT='     . FILELIMIT);
putenv('PROCLIMIT='     . PROCLIMIT);

foreach ( $EXITCODES as $code => $name ) {
	$var = 'E_' . strtoupper(str_replace('-','_',$name));
	putenv($var . '=' . $code);
}

$waittime = 5;

$myhost = trim(`hostname | cut -d . -f 1`);

define ('SCRIPT_ID', 'judgedaemon');
define ('LOGFILE', LOGDIR.'/judge.'.$myhost.'.log');
define ('PIDFILE', RUNDIR.'/judgedaemon.'.$myhost.'.pid');

require(LIBDIR . '/init.php');

function usage()
{
	echo "Usage: " . SCRIPT_ID . " [OPTION]...\n" .
	    "Start the judgedaemon.\n\n" .
	    "  -d, --daemon          daemonize after startup\n" .
	    "  -v, --verbose=LEVEL   set verbosity to LEVEL (syslog levels)\n" .
	    "      --help            display this help and exit\n" .
	    "      --version         output version information and exit\n\n";
	exit;
}

$options = getopt("dv:", array('daemon', 'verbose:', 'help', 'version'));
// FIXME: getopt doesn't return FALSE on parse failure as documented!
if ( $options===FALSE ) {
	echo "Error: parsing options failed.\n";
	usage();
}
if ( isset($options['d']) ) $options['daemon']  = $options['d'];
if ( isset($options['v']) ) $options['verbose'] = $options['v'];

if ( isset($options['version']) ) version();
if ( isset($options['help']) ) usage();

setup_database_connection('jury');

$verbose = LOG_INFO;
if ( isset($options['verbose']) ) $verbose = $options['verbose'];

if ( DEBUG & DEBUG_JUDGE ) {
	$verbose = LOG_DEBUG;
	putenv('DEBUG=1');
}

system("pgrep -u ".RUNUSER, $retval);
if ($retval == 0) {
	error("Still some processes by ".RUNUSER." found, aborting");
}
if ($retval != 1) {
	error("Error while checking processes for user " . RUNUSER);
}

logmsg(LOG_NOTICE, "Judge started on $myhost [DOMjudge/".DOMJUDGE_VERSION."]");

// Tick use required between PHP 4.3.0 and 5.3.0 for handling signals,
// must be declared globally.
if ( version_compare(PHP_VERSION, '5.3', '<' ) ) {
	declare(ticks = 1);
}
initsignals();

if ( isset($options['daemon']) ) daemonize(PIDFILE);

database_retry_connect($waittime);

$first = True;
while( !$exitsignalled )
{
	try {
		// Retrieve hostname and check database for judgehost entry
		$row = $DB->q('MAYBETUPLE SELECT * FROM judgehost WHERE hostname = %s'
		             , $myhost);
		if ( ! $row ) {
			if($first)
				logmsg(LOG_WARNING, "No database entry found for me ($myhost)");
			$first = False;
			sleep($waittime);
			continue;
		}
		$myhost = $row['hostname'];
		unset($first);
		break;
	}
	catch( Exception $e ) {
		$msg = "MySQL server has gone away";
		if( ! strncmp($e->getMessage(), $msg, strlen($msg)) ) {
			logmsg(LOG_WARNING, $msg);
			database_retry_connect();
			continue;
		}
		throw $e;
	}
}

// Create directory where to test submissions
$workdirpath = JUDGEDIR . "/$myhost";
system("mkdir -p $workdirpath/testcase", $retval);
if ( $retval != 0 ) error("Could not create $workdirpath");

$waiting = FALSE;
$active = TRUE;
$cid = null;

// Constantly check database for unjudged submissions
while ( TRUE ) {

	// Check whether we have received an exit signal
	if ( function_exists('pcntl_signal_dispatch') ) pcntl_signal_dispatch();
	if ( $exitsignalled ) {
		logmsg(LOG_NOTICE, "Received signal, exiting.");
		exit;
	}

	try {
		// Check that this judge is active, else wait and check again later
		$row = $DB->q('TUPLE SELECT * FROM judgehost WHERE hostname = %s'
		             , $myhost);
		$DB->q('UPDATE LOW_PRIORITY judgehost SET polltime = NOW()
		       WHERE hostname = %s', $myhost);
	}
	catch( Exception $e ) {
		$msg = "MySQL server has gone away";
		if( ! strncmp($e->getMessage(), $msg, strlen($msg)) ) {
			logmsg(LOG_WARNING, $msg);
			database_retry_connect();
			continue;
		}
		throw $e;
	}

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

	// First, use a select to see whether there are any judgeable
	// submissions. This query is query-cacheable, and doing a select
	// first prevents a write-lock on the submission table if nothing is
	// to be judged, and also prevents throwing away the query cache every
	// single time
	$numopen = $DB->q('VALUE SELECT COUNT(*) FROM submission
	                   WHERE judgehost IS NULL AND cid = %i AND langid IN (%As)
	                   AND probid IN (%As) AND submittime < %s AND valid = 1',
	                  $cid, $judgable_lang, $judgable_prob, $contdata['endtime']);

	$numupd = 0;
	if ($numopen) {
		// Generate (unique) random string to mark submission to be judged
		list($usec, $sec) = explode(" ", microtime());
		$mark = $myhost.'@'.($sec+$usec).'#'.uniqid( mt_rand(), true );

		// update exactly one submission with our random string
		// Note: this might still return 0 if another judgehost beat
		// us to it
		$numupd = $DB->q('RETURNAFFECTED UPDATE submission
				  SET judgehost = %s, judgemark = %s
				  WHERE judgehost IS NULL AND cid = %i AND langid IN (%As)
				  AND probid IN (%As) AND submittime < %s AND valid = 1
				  LIMIT 1',
				 $myhost, $mark, $cid, $judgable_lang, $judgable_prob,
				 $contdata['endtime']);
	}

	// nothing updated -> no open submissions
	if ( $numupd == 0 ) {
		if ( ! $waiting ) {
			logmsg(LOG_INFO, "No submissions in queue, waiting...");
			$waiting = TRUE;
		}
		sleep($waittime);
		continue;
	}

	// we have marked a submission for judging
	$waiting = FALSE;

	// get maximum runtime, source code and other parameters
	$row = $DB->q('TUPLE SELECT CEILING(time_factor*timelimit) AS maxruntime,
	               s.submitid, s.sourcecode, s.langid, s.teamid, s.probid,
	               p.special_run, p.special_compare, l.extension
	               FROM submission s, problem p, language l
	               WHERE s.probid = p.probid AND s.langid = l.langid AND
	               judgemark = %s AND judgehost = %s', $mark, $myhost);

	// update the judging table with our ID and the starttime
	$judgingid = $DB->q('RETURNID INSERT INTO judging (submitid,cid,starttime,judgehost)
	                     VALUES (%i,%i,%s,%s)', $row['submitid'], $cid, now(), $myhost);

	logmsg(LOG_NOTICE, "Judging submission s$row[submitid] ".
	       "($row[teamid]/$row[probid]/$row[langid]), id j$judgingid...");

	judge($mark, $row, $judgingid);

	// restart the judging loop
}

function judge($mark, $row, $judgingid)
{
	global $EXITCODES, $DB, $cid, $myhost, $workdirpath;

	// create workdir for judging
	$workdir = "$workdirpath/c$cid-s$row[submitid]-j$judgingid";

	logmsg(LOG_INFO, "Working directory: $workdir");

	// If a database gets reset without removing the judging
	// directories, we might hit an old directory: rename it.
	if ( file_exists($workdir) ) {
		$oldworkdir = $workdir . '-old-' . getmypid() . '-' . now();
		if ( !rename($workdir, $oldworkdir) ) {
			error("Could not rename stale working directory to '$oldworkdir'");
		}
		warning("Found stale working directory; renamed to '$oldworkdir'");
	}

	system("mkdir -p '$workdir/compile'", $retval);
	if ( $retval != 0 ) error("Could not create '$workdir/compile'");

	// Get the source code from the DB and store in a file
	$srcfile = "$workdir/compile/source.$row[extension]";
	if ( file_put_contents($srcfile, $row['sourcecode']) === FALSE ) {
		error("Could not create $srcfile");
	}
	unset($row['sourcecode']);

	// Compile the program.
	system(LIBJUDGEDIR . "/compile.sh $row[extension] $row[langid] " .
	       "'$workdir'", $retval);

	// what does the exitcode mean?
	if( ! isset($EXITCODES[$retval]) ) {
		alert('error');
		error("Unknown exitcode from compile.sh for s$row[submitid]: $retval");
	}

	// pop the compilation result back into the judging table
	$DB->q('UPDATE judging SET output_compile = %s
	        WHERE judgingid = %i AND judgehost = %s',
	       getFileContents( $workdir . '/compile.out' ), $judgingid, $myhost);

	// Only continue running testcases when compilation was successful.
	// FIXME(?): result is still returned as in EXITCODES.
	if ( ($result = $EXITCODES[$retval])=='correct' ) {

	logmsg(LOG_DEBUG, "Fetching testcases from database");
	$testcases = $DB->q("KEYTABLE SELECT rank AS ARRAYKEY,
 	                     testcaseid, md5sum_input, md5sum_output, probid, rank
	                     FROM testcase WHERE probid = %s ORDER BY rank", $row['probid']);
	if ( count($testcases)==0 ) {
		error("No testcase found for problem " . $row['probid']);
	}

	$runresults = array_fill_keys(array_keys($testcases), NULL);

	foreach ( $testcases as $tc ) {

	logmsg(LOG_DEBUG, "Running testcase $tc[rank]...");
	$testcasedir = $workdir . "/testcase" . sprintf('%03d', $tc['rank']);

	// Get both in- and output files, only if we didn't have them already.
	$tcfile = array();
	foreach(array('input','output') as $inout) {
		$tcfile[$inout] = "$workdirpath/testcase/testcase.$tc[probid].$tc[rank]." .
		    $tc['md5sum_'.$inout] . "." . substr($inout, 0, -3);

		if ( !file_exists($tcfile[$inout]) ) {
			$content = $DB->q("VALUE SELECT $inout FROM testcase
	 		                   WHERE testcaseid = %i", $tc['testcaseid']);
			if ( file_put_contents($tcfile[$inout] . ".new", $content) === FALSE ) {
				error("Could not create $tcfile[$inout].new");
			}
			unset($content);
			if ( md5_file("$tcfile[$inout].new") == $tc['md5sum_'.$inout]) {
				rename("$tcfile[$inout].new",$tcfile[$inout]);
			} else {
				error("File corrupted during download.");
			}
			logmsg(LOG_NOTICE, "Fetched new $inout testcase $tc[rank] for " .
			       "problem $row[probid]");
		}
		// sanity check (NOTE: performance impact is negligible with 5
		// testcases and total 3.3 MB of data)
		if ( md5_file($tcfile[$inout]) != $tc['md5sum_' . $inout] ) {
			error("File corrupted: md5sum mismatch: " . $tcfile[$inout]);
		}
	}

	// Copy program with all possible additional files to testcase
	// dir. Use hardlinks to preserve space with big executables.
	$programdir = $testcasedir . '/execdir';
	system("mkdir -p '$programdir'", $retval);
	if ( $retval!=0 ) error("Could not create directory '$programdir'");

	system("cp -dpRl '$workdir'/compile/* '$programdir'", $retval);
	if ( $retval!=0 ) error("Could not copy program to '$programdir'");

	// do the actual test-run
	system(LIBJUDGEDIR . "/testcase_run.sh $tcfile[input] $tcfile[output] " .
	       "$row[maxruntime] '$testcasedir' " .
	       "'$row[special_run]' '$row[special_compare]'", $retval);

	// what does the exitcode mean?
	if( ! isset($EXITCODES[$retval]) ) {
		alert('error');
		error("Unknown exitcode from testcase_run.sh for s$row[submitid], " .
		      "testcase $tc[rank]: $retval");
	}
	$runresults[$tc['rank']] = $EXITCODES[$retval];

	// Try to read runtime from file
	$runtime = NULL;
	if ( is_readable($testcasedir . '/program.time') ) {
		$runtime = getFileContents($testcasedir . '/program.time');
	}

	$DB->q('INSERT INTO judging_run (judgingid, testcaseid, runresult,
 	        runtime, output_run, output_diff, output_error)
	        VALUES (%i, %i, %s, %f, %s, %s, %s)',
	       $judgingid, $tc['testcaseid'], $runresults[$tc['rank']], $runtime,
	       getFileContents($testcasedir . '/program.out'),
	       getFileContents($testcasedir . '/compare.out'),
	       getFileContents($testcasedir . '/error.out'));
	logmsg(LOG_DEBUG, "Testcase $tc[rank] done, result: " . $runresults[$tc['rank']]);

	// Optimization: stop judging when the result is already known.
	// This should report a final result when all runresults are non-null!
	if ( ($result = getFinalResult($runresults))!==NULL ) break;

	} // end: for each testcase

	} // end: if compile result==0

	if ( $result==NULL ) error("No final result obtained");

	// Start a transaction. This will provide extra safety if the table type
	// supports it.
	$DB->q('START TRANSACTION');
	// pop the result back into the judging table
	$DB->q('UPDATE judging SET endtime = %s, result = %s
	        WHERE judgingid = %i AND judgehost = %s',
	       now(), $result, $judgingid, $myhost);

	// recalculate the scoreboard cell (team,problem) after this judging
	calcScoreRow($cid, $row['teamid'], $row['probid']);

	// log to event table if no verification required
	// (case of verification required is handled in www/jury/verify.php)
	if ( ! VERIFICATION_REQUIRED ) {
		$DB->q('INSERT INTO event (eventtime, cid, teamid, langid, probid,
		                           submitid, judgingid, description)
		        VALUES(%s, %i, %s, %s, %s, %i, %i, "problem judged")',
		       now(), $cid, $row['teamid'], $row['langid'], $row['probid'],
		       $row['submitid'], $judgingid);
	}

	$DB->q('COMMIT');

	// done!
	logmsg(LOG_NOTICE, "Judging s$row[submitid]/j$judgingid finished, result: $result");
	if ( $result == 'correct' ) {
		alert('accept');
	} else {
		alert('reject');
	}
}

function database_retry_connect()
{
	global $DB, $exitsignalled, $waittime;

	$first = True;
	while( !$exitsignalled )
	{
		try {
			$DB->reconnect();
			logmsg(LOG_INFO, "Connected to database");
			break;
		}
		catch( Exception $e ) {
			$msg = "Could not connect to database server";
			if( ! strncmp($e->getMessage(), $msg, strlen($msg)) ) {
				if($first) logmsg(LOG_WARNING, $msg);
				$first = False;
				sleep($waittime);
				continue;
			}
			throw $e;
		}
	}
}
