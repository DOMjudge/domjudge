<?php
/**
 * Request a yet unjudged submission from the domserver, judge it, and pass
 * the results back to the domserver.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require(ETCDIR . '/judgehost-config.php');
$credfile = ETCDIR . '/restapi.secret';
$credentials = @file($credfile);
if (!$credentials) {
	user_error("Cannot read REST API credentials file " . $credfile,
		E_USER_ERROR);
	exit();
}
foreach ($credentials as $credential) {
	if ( $credential{0} == '#' ) continue;
	list ($resturl, $restuser, $restpass) = preg_split("/\s+/", trim($credential));
	break;
}
if ( !(isset($resturl) && isset($restuser) && isset($restpass)) ) {
	// FIXME: do check API access here
	user_error("Cannot access REST API.", E_USER_ERROR);
	exit();
}

function request($url, $verb = 'GET', $data = '') {
	global $resturl, $restuser, $restpass;

	$url = $resturl . "/" . $url;
	if ( $verb == 'GET' ) {
		$url .= '?' . $data;
	}

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_USERAGENT, "DOMjudge/" . DOMJUDGE_VERSION);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, $restuser . ":" . $restpass);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	if ( $verb == 'POST' ) {
		curl_setopt($ch, CURLOPT_POST, TRUE);
		if ( is_array($data) ) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
		}
	} else if ( $verb == 'PUT' || $verb == 'DELETE' ) {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
	}
	if ( $verb == 'POST' || $verb == 'PUT' ) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}

	$response = curl_exec($ch);
	if ( !$response ) {
		error("Error while executing curl with url " . $url . ": " . curl_error($ch));
	}
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ( $status < 200 || $status >= 300 ) {
		error("Error while executing curl with url " . $url . ": http status code: " . $status . ", response: " . $response);
	}

	curl_close($ch);
	return $response;
}

function dbconfig_get_rest($name) {
	$res = request('config', 'GET', 'name=' . urlencode($name));
	$res = dj_json_decode($res);
	return $res[$name];
}

/**
 * Decode a json encoded string and handle errors.
 */
function dj_json_decode($str) {
	$res = json_decode($str, TRUE);
	if ( $res === NULL ) {
		error("Error retrieving API data. API gave us: " . $str);
	}
	return $res;
}

/**
 * Encode file contents for POST-ing to REST API.
 * Returns contents of $file as encoded string.
 */
function rest_encode_file($file) {
	return urlencode(base64_encode(getFileContents($file)));
}

$waittime = 5;

define ('SCRIPT_ID', 'judgedaemon');
define ('PIDFILE', RUNDIR.'/judgedaemon.pid');

function usage()
{
	echo "Usage: " . SCRIPT_ID . " [OPTION]...\n" .
	    "Start the judgedaemon.\n\n" .
	    "  -d       daemonize after startup\n" .
	    "  -n <id>  daemon number\n" .
	    "  -v       set verbosity to LEVEL (syslog levels)\n" .
	    "  -h       display this help and exit\n" .
	    "  -V       output version information and exit\n\n";
	exit;
}

$options = getopt("dv:n:hV");
// With PHP version >= 5.3 we can also use long options.
// FIXME: getopt doesn't return FALSE on parse failure as documented!
if ( $options===FALSE ) {
	echo "Error: parsing options failed.\n";
	usage();
}
if ( isset($options['d']) ) $options['daemon']  = $options['d'];
if ( isset($options['v']) ) $options['verbose'] = $options['v'];
if ( isset($options['n']) ) $options['daemonid'] = $options['n'];

if ( isset($options['V']) ) version();
if ( isset($options['h']) ) usage();

$myhost = trim(`hostname | cut -d . -f 1`);
if ( isset($options['daemonid']) ) {
	if ( !defined('USE_CGROUPS') || !USE_CGROUPS ) {
		echo "Option `-n' is only supported when compiled with cgroup support.\n";
		exit(1);
	}
	if ( preg_match('/^\d+$/', $options['daemonid'] ) ) {
		$myhost = $myhost . "-" . $options['daemonid'];
	} else {
		echo "Invalid value for daemonid, must be positive integer.\n";
		exit(1);
	}
}

define ('LOGFILE', LOGDIR.'/judge.'.$myhost.'.log');
require(LIBDIR . '/lib.error.php');
require(LIBDIR . '/lib.misc.php');

$verbose = LOG_INFO;
if ( isset($options['verbose']) ) {
	if ( preg_match('/^\d+$/', $options['verbose'] ) ) {
		$verbose = $options['verbose'];
	} else {
		echo "Invalid value for verbose, must be positive integer\n";
		exit(1);
	}
}

if ( DEBUG & DEBUG_JUDGE ) {
	$verbose = LOG_DEBUG;
	putenv('DEBUG=1');
}

$runuser = RUNUSER;
if ( isset($options['daemonid']) ) $runuser .= '-' . $options['daemonid'];

// Set static environment variables for passing path configuration
// to called programs:
putenv('DJ_BINDIR='      . BINDIR);
putenv('DJ_ETCDIR='      . ETCDIR);
putenv('DJ_JUDGEDIR='    . JUDGEDIR);
putenv('DJ_LIBDIR='      . LIBDIR);
putenv('DJ_LIBJUDGEDIR=' . LIBJUDGEDIR);
putenv('DJ_LOGDIR='      . LOGDIR);
putenv('RUNUSER='        . $runuser);

foreach ( $EXITCODES as $code => $name ) {
	$var = 'E_' . strtoupper(str_replace('-','_',$name));
	putenv($var . '=' . $code);
}

// Pass SYSLOG variable via environment for compare program
if ( defined('SYSLOG') && SYSLOG ) putenv('DJ_SYSLOG=' . SYSLOG);

system("pgrep -u $runuser", $retval);
if ($retval == 0) {
	error("Still some processes by $runuser found, aborting");
}
if ($retval != 1) {
	error("Error while checking processes for user $runuser");
}

logmsg(LOG_NOTICE, "Judge started on $myhost [DOMjudge/".DOMJUDGE_VERSION."]");

// Tick use required between PHP 4.3.0 and 5.3.0 for handling signals,
// must be declared globally.
if ( version_compare(PHP_VERSION, '5.3', '<' ) ) {
	declare(ticks = 1);
}
initsignals();

if ( isset($options['daemon']) ) daemonize(PIDFILE);

// Warn when chroot has been disabled. This has security implications.
if ( ! USE_CHROOT ) {
	logmsg(LOG_WARNING, "Chroot disabled. This reduces judgehost security.");
}

// Create directory where to test submissions
$workdirpath = JUDGEDIR . "/$myhost";
system("mkdir -p $workdirpath/testcase", $retval);
if ( $retval != 0 ) error("Could not create $workdirpath");
chmod("$workdirpath/testcase", 0700);

// Auto-register judgehost via REST
// If there are any unfinished judgings in the queue in my name,
// they will not be finished. Give them back.
$unfinished = request('judgehosts', 'POST', 'hostname=' . urlencode($myhost));
$unfinished = dj_json_decode($unfinished);
foreach ( $unfinished as $jud ) {
	$workdir = "$workdirpath/c$jud[cid]-s$jud[submitid]-j$jud[judgingid]";
	@chmod($workdir, 0700);
	logmsg(LOG_WARNING, "Found unfinished judging j" . $jud['judgingid'] . " in my name; given back");
}

$waiting = FALSE;

// Constantly check API for unjudged submissions
while ( TRUE ) {

	// Check whether we have received an exit signal
	if ( function_exists('pcntl_signal_dispatch') ) pcntl_signal_dispatch();
	if ( $exitsignalled ) {
		logmsg(LOG_NOTICE, "Received signal, exiting.");
		exit;
	}

	$judging = request('judgings', 'POST', 'judgehost=' . urlencode($myhost));
	$row = dj_json_decode($judging);

	// nothing returned -> no open submissions for us
	if ( empty($row) ) {
		if ( ! $waiting ) {
			logmsg(LOG_INFO, "No submissions in queue, waiting...");
			$waiting = TRUE;
		}
		sleep($waittime);
		continue;
	}

	// we have gotten a submission for judging
	$waiting = FALSE;

	logmsg(LOG_NOTICE, "Judging submission s$row[submitid] ".
	       "($row[teamid]/$row[probid]/$row[langid]), id j$row[judgingid]...");

	judge($row);

	// restart the judging loop
}

function judge($row)
{
	global $EXITCODES, $myhost, $options, $workdirpath;

	// Set configuration variables for called programs
	putenv('USE_CHROOT='    . (USE_CHROOT ? '1' : ''));
	putenv('COMPILETIME='   . dbconfig_get_rest('compile_time'));
	putenv('MEMLIMIT='      . dbconfig_get_rest('memory_limit'));
	putenv('FILELIMIT='     . dbconfig_get_rest('filesize_limit'));
	putenv('PROCLIMIT='     . dbconfig_get_rest('process_limit'));

	$cpuset_opt = "";
	if ( isset($options['daemonid']) ) $cpuset_opt = "-n ${options['daemonid']}";

	// create workdir for judging
	$workdir = "$workdirpath/c$row[cid]-s$row[submitid]-j$row[judgingid]";

	logmsg(LOG_INFO, "Working directory: $workdir");

	// If a database gets reset without removing the judging
	// directories, we might hit an old directory: rename it.
	if ( file_exists($workdir) ) {
		$oldworkdir = $workdir . '-old-' . getmypid() . '-' . strftime('%Y-%m-%d_%H:%M');
		if ( !rename($workdir, $oldworkdir) ) {
			error("Could not rename stale working directory to '$oldworkdir'");
		}
		@chmod($oldworkdir, 0700);
		warning("Found stale working directory; renamed to '$oldworkdir'");
	}

	system("mkdir -p '$workdir/compile'", $retval);
	if ( $retval != 0 ) error("Could not create '$workdir/compile'");

	if ( !chdir($workdir) ) error("Could not chdir to '$workdir'");

	// Get the source code from the DB and store in local file(s)
	$sources = request('submission_files', 'GET', 'submitid=' . urlencode($row['submitid']));
	$sources = dj_json_decode($sources);
	$files = array();
	foreach ( $sources as $source ) {
		$srcfile = "$workdir/compile/$source[filename]";
		$files[] = "'$source[filename]'";
		if ( file_put_contents($srcfile, base64_decode($source['sourcecode'])) === FALSE ) {
			error("Could not create $srcfile");
		}
	}

	// Compile the program.
	system(LIBJUDGEDIR . "/compile.sh $cpuset_opt $row[langid] '$workdir' " .
	       implode(' ', $files), $retval);

	// what does the exitcode mean?
	if( ! isset($EXITCODES[$retval]) ) {
		alert('error');
		error("Unknown exitcode from compile.sh for s$row[submitid]: $retval");
	}
	$compile_success =  ($EXITCODES[$retval]!='compiler-error');

	// pop the compilation result back into the judging table
	request('judgings/' . urlencode($row['judgingid']), 'PUT',
		'judgehost=' . urlencode($myhost)
		. '&compile_success=' . $compile_success
		. '&output_compile=' . rest_encode_file($workdir . '/compile.out'));

	// compile error: our job here is done
	if ( ! $compile_success ) return;

	// Optionally create chroot environment
	if ( USE_CHROOT && CHROOT_SCRIPT ) {
		logmsg(LOG_INFO, "executing chroot script: '".CHROOT_SCRIPT." start'");
		system(LIBJUDGEDIR.'/'.CHROOT_SCRIPT.' start', $retval);
		if ( $retval!=0 ) error("chroot script exited with exitcode $retval");
	}

	// Make sure the workdir is accessible for the domjudge-run user.
	// Will be revoked again after this run finished.
	chmod ($workdir, 0755);

	$totalcases = 0;
	while ( TRUE ) {
		// get the next testcase
		$testcase = request('testcases', 'GET', 'judgingid=' . urlencode($row['judgingid']));
		$tc = dj_json_decode($testcase);

		// empty means: no more testcases for this judging.
		if ( empty($tc) ) break;

		$totalcases++;
		logmsg(LOG_DEBUG, "Running testcase $tc[rank]...");
		$testcasedir = $workdir . "/testcase" . sprintf('%03d', $tc['rank']);

		// Get both in- and output files, only if we didn't have them already.
		$tcfile = array();
		$fetched = array();
		foreach(array('input','output') as $inout) {
			$tcfile[$inout] = "$workdirpath/testcase/testcase.$tc[probid].$tc[rank]." .
			    $tc['md5sum_'.$inout] . "." . substr($inout, 0, -3);

			if ( !file_exists($tcfile[$inout]) ) {
				$content = request('testcase_files', 'GET', 'testcaseid='
						. urlencode($tc['testcaseid'])
						. '&' . $inout);
				$content = dj_json_decode($content);
				if ( file_put_contents($tcfile[$inout] . ".new", $content) === FALSE ) {
					error("Could not create $tcfile[$inout].new");
				}
				unset($content);
				if ( md5_file("$tcfile[$inout].new") == $tc['md5sum_'.$inout]) {
					rename("$tcfile[$inout].new",$tcfile[$inout]);
				} else {
					error("File corrupted during download.");
				}
				$fetched[] = $inout;
			}
			// sanity check (NOTE: performance impact is negligible with 5
			// testcases and total 3.3 MB of data)
			if ( md5_file($tcfile[$inout]) != $tc['md5sum_' . $inout] ) {
				error("File corrupted: md5sum mismatch: " . $tcfile[$inout]);
			}
		}
		// Only log downloading input and/or output testdata once.
		if ( count($fetched)>0 ) {
			logmsg(LOG_INFO, "Fetched new " . implode($fetched,',') .
			       " testcase $tc[rank] for problem $tc[probid]");
		}

		// Copy program with all possible additional files to testcase
		// dir. Use hardlinks to preserve space with big executables.
		$programdir = $testcasedir . '/execdir';
		system("mkdir -p '$programdir'", $retval);
		if ( $retval!=0 ) error("Could not create directory '$programdir'");

		system("cp -pPRl '$workdir'/compile/* '$programdir'", $retval);
		if ( $retval!=0 ) error("Could not copy program to '$programdir'");

		// do the actual test-run
		$hardtimelimit = $row['maxruntime'] +
		                 overshoot_time($row['maxruntime'],
		                                dbconfig_get_rest('timelimit_overshoot'));

		system(LIBJUDGEDIR . "/testcase_run.sh $cpuset_opt $tcfile[input] $tcfile[output] " .
		       "$row[maxruntime]:$hardtimelimit '$testcasedir' " .
		       "'$row[special_run]' '$row[special_compare]'", $retval);

		// what does the exitcode mean?
		if( ! isset($EXITCODES[$retval]) ) {
			alert('error');
			error("Unknown exitcode from testcase_run.sh for s$row[submitid], " .
			      "testcase $tc[rank]: $retval");
		}
		$result = $EXITCODES[$retval];

		// Try to read runtime from file
		$runtime = NULL;
		if ( is_readable($testcasedir . '/program.time') ) {
			$fdata = getFileContents($testcasedir . '/program.time');
			list($runtime) = sscanf($fdata,"%f");
		}

		request('judging_runs', 'POST', 'judgingid=' . urlencode($row['judgingid'])
			. '&testcaseid=' . urlencode($tc['testcaseid'])
			. '&runresult=' . urlencode($result)
			. '&runtime=' . urlencode($runtime)
			. '&judgehost=' . urlencode($myhost)
			. '&output_run='   . rest_encode_file($testcasedir . '/program.out')
			. '&output_diff='  . rest_encode_file($testcasedir . '/compare.out')
			. '&output_error=' . rest_encode_file($testcasedir . '/error.out'));
		logmsg(LOG_DEBUG, "Testcase $tc[rank] done, result: " . $result);

	} // end: for each testcase

	// revoke readablity for domjudge-run user to this workdir
	chmod($workdir, 0700);

	// Optionally destroy chroot environment
	if ( USE_CHROOT && CHROOT_SCRIPT ) {
		logmsg(LOG_INFO, "executing chroot script: '".CHROOT_SCRIPT." stop'");
		system(LIBJUDGEDIR.'/'.CHROOT_SCRIPT.' stop', $retval);
		if ( $retval!=0 ) error("chroot script exited with exitcode $retval");
	}

	// Sanity check: need to have had at least one testcase
	if ( $totalcases == 0 ) {
		logmsg(LOG_WARNING, "No testcases judged for s$row[submitid]/j$row[judgingid]!");
	}

	// done!
	logmsg(LOG_NOTICE, "Judging s$row[submitid]/j$row[judgingid] finished");
}
