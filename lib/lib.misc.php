<?php
/**
 * Miscellaneous helper functions
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/** Constant to define MySQL datetime format in strftime() function notation. */
define('MYSQL_DATETIME_FORMAT', '%Y-%m-%d %H:%M:%S');

/** Perl regex class of allowed characters in identifier strings. */
define('IDENTIFIER_CHARS', '[a-zA-Z0-9_-]');

/** Perl regex of allowed filenames. */
define('FILENAME_REGEX', '/^[a-zA-Z0-9][a-zA-Z0-9+_\.-]*$/');

/**
 * helperfunction to read all contents from a file.
 * If $sizelimit is true (default), then only limit this to
 * the first 50,000 bytes and attach a note saying so.
 */
function getFileContents($filename, $sizelimit = true) {

	if ( ! file_exists($filename) ) {
		return '';
	}
	if ( ! is_readable($filename) ) {
		error("Could not open $filename for reading: not readable");
	}

	if ( $sizelimit && filesize($filename) > 50000 ) {
		return file_get_contents($filename, FALSE, NULL, -1, 50000)
			. "\n[output truncated after 50,000 B]\n";
	}

	return file_get_contents($filename);
}

/**
 * Will return either the current contest id, or
 * the most recently finished one.
 * When fulldata is true, returns the total row as an array
 * instead of just the ID.
 */
function getCurContest($fulldata = FALSE) {

	global $DB;
	$now = $DB->q('MAYBETUPLE SELECT * FROM contest
	               WHERE enabled = 1 AND activatetime <= NOW()
	               ORDER BY activatetime DESC LIMIT 1');

	if ( $now == NULL ) return FALSE;

	if ( !$fulldata ) return $now['cid'];

	return $now;
}

/**
 * Returns whether the problem with probid is visible to teams and the
 * public. That is, it is in the active contest, which has started and
 * it is submittable.
 */
function problemVisible($probid)
{
	global $DB, $cdata;

	if ( empty($probid) ) return FALSE;
	if ( !$cdata || difftime(now(),$cdata['starttime']) < 0 ) return FALSE;

	return $DB->q('MAYBETUPLE SELECT probid FROM problem
	               WHERE cid = %i AND allow_submit = 1 AND probid = %s',
	              $cdata['cid'], $probid) !== NULL;
}

/**
 * Calculate contest time from wall-clock time.
 * Returns time since contest start in seconds.
 * This function is currently a stub around timediff, but introduced
 * to allow minimal changes wrt. the removed intervals required for
 * the ICPC specification.
 */
function calcContestTime($walltime)
{
	global $cdata;

	$contesttime = difftime($walltime, $cdata['starttime']);

	return $contesttime;
}

/**
 * Scoreboard calculation
 *
 * This is here because it needs to be called by the judgedaemon script
 * as well.
 *
 * Given a contestid, teamid and a problemid,
 * (re)calculate the values for one row in the scoreboard.
 *
 * Due to current transactions usage, this function MUST NOT contain
 * any START TRANSACTION or COMMIT statements.
 */
function calcScoreRow($cid, $team, $prob) {
	global $DB;

	// First acquire an advisory lock to prevent other calls to
	// calcScoreRow() from interfering with our update.
	$lockstr = "domjudge.$cid.$team.$prob";
	if ( $DB->q("VALUE SELECT GET_LOCK('$lockstr',3)") != 1 ) {
		error("calcScoreRow failed to obtain lock '$lockstr'");
	}

	// Note the clause 'submittime < c.endtime': this is used to
	// filter out TOO-LATE submissions from pending, but it also means
	// that these will not count as solved. Correct submissions with
	// submittime after contest end should never happen, unless one
	// resets the contest time after successful judging.
	$result = $DB->q('SELECT result, verified, submittime,
	                  (c.freezetime IS NOT NULL && submittime >= c.freezetime) AS afterfreeze
	                  FROM submission s
	                  LEFT JOIN judging j ON(s.submitid=j.submitid AND j.valid=1)
	                  LEFT OUTER JOIN contest c ON(c.cid=s.cid)
	                  WHERE teamid = %s AND probid = %s AND s.cid = %i AND s.valid = 1
	                  AND submittime < c.endtime
	                  ORDER BY submittime',
	                 $team, $prob, $cid);

	// reset vars
	$submitted_j = $pending_j = $time_j = $correct_j = 0;
	$submitted_p = $pending_p = $time_p = $correct_p = 0;

	// for each submission
	while( $row = $result->next() ) {

		// Contest submit time in minutes for scoring.
		$submittime = (int)floor(calcContestTime($row['submittime']) / 60);

		// Check if this submission has a publicly visible judging result:
		if ( (dbconfig_get('verification_required', 0) && ! $row['verified']) ||
		     empty($row['result']) ) {

			$pending_j++;
			$pending_p++;
			// Don't do any more counting for this submission.
			continue;
		}

		$submitted_j++;
		if ( $row['afterfreeze'] ) {
			// Show submissions after freeze as pending to the public
			// (if SHOW_PENDING is enabled):
			$pending_p++;
		} else {
			$submitted_p++;
		}

		// if correct, don't look at any more submissions after this one
		if ( $row['result'] == 'correct' ) {

			$correct_j = 1;
			$time_j = $submittime;
			if ( ! $row['afterfreeze'] ) {
				$correct_p = 1;
				$time_p = $submittime;
			}
			// stop counting after a first correct submission
			break;
		}
	}

	// insert or update the values in the public/team scores table
	$DB->q('REPLACE INTO scoreboard_public
	        (cid, teamid, probid, submissions, pending, totaltime, is_correct)
	        VALUES (%i,%s,%s,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_p, $pending_p, $time_p, $correct_p);

	// insert or update the values in the jury scores table
	$DB->q('REPLACE INTO scoreboard_jury
	        (cid, teamid, probid, submissions, pending, totaltime, is_correct)
	        VALUES (%i,%s,%s,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_j, $pending_j, $time_j, $correct_j);

	if ( $DB->q("VALUE SELECT RELEASE_LOCK('$lockstr')") != 1 ) {
		error("calcScoreRow failed to release lock '$lockstr'");
	}

	return;
}

/**
 * Determines final result for a judging given an ordered array of
 * testcase results. Testcase results can have value NULL if not run
 * yet. A return value of NULL means that a final result cannot be
 * determined yet; this may only occur when not all testcases have
 * been run yet.
 */
function getFinalResult($runresults)
{
	$results_prio  = dbconfig_get('results_prio');
	$lazy_eval     = dbconfig_get('lazy_eval_results', true);

	// Whether we have NULL results
	$havenull = FALSE;

	// This stores the current result and priority to be returned:
	$bestres  = NULL;
	$bestprio = -1;

	// Find first highest priority result:
	foreach ( $runresults as $tc => $res ) {
		if ( $res===NULL ) {
			$havenull = TRUE;
		} else {
			$prio = $results_prio[$res];
			if ( empty($prio) ) error("Unknown result '$res' found.");
			if ( $prio>$bestprio ) {
				$bestres  = $res;
				$bestprio = $prio;
			}
		}
	}

	// Not all results are in yet, and we don't do lazy evaluation:
	if ( $havenull && ! $lazy_eval ) return NULL;

	if ( $lazy_eval ) {
		// If we have NULL results, check whether the highest priority
		// result has maximal priority. Use a local copy of the
		// 'results_prio' array, keeping the original untouched.
		$tmp = $results_prio;
		rsort($tmp);
		$maxprio = reset($tmp);

		// No highest priority result found: no final answer yet.
		if ( $havenull && $bestprio<$maxprio ) return NULL;
	}

	return $bestres;
}

/**
 * Parse language extensions from LANG_EXTS to ext -> ID map
 */
function parseLangExts()
{
	global $langexts;

	$langexts = array();
	foreach ( explode(' ', LANG_EXTS) as $lang ) {
		$exts = explode(',', $lang);
		for ($i=1; $i<count($exts); $i++) $langexts[$exts[$i]] = $exts[1];
	}
}

/**
 * Get langid from extension (initialize global $langexts if necessary)
 */
function getLangID($ext)
{
	global $langexts;

	if ( empty($langexts) ) parseLangExts();

	return @$langexts[$ext];
}

/**
 * Simulate MySQL NOW() function to create insert queries that do not
 * change when replicated later.
 */
function now()
{
	return strftime(MYSQL_DATETIME_FORMAT);
}

/**
 * Returns >0, =0, <0 when $time1 >, =, < $time2 respectively.
 * This function converts the strings to integer seconds and returns
 * their difference. We don't use the default second argument 'now()'
 * for 'strtotime()' since it could (theoretically) change.
 */
function difftime($time1, $time2)
{
	return strtotime($time1, 0) - strtotime($time2, 0);
}

/**
 * Call alert plugin program to perform user configurable action on
 * important system events. See default alert script for more details.
 */
function alert($msgtype, $description = '')
{
	system(LIBDIR . "/alert '$msgtype' '$description' &");
}

/**
 * Create a unique file from a template string.
 *
 * Returns a full path to the filename or FALSE on failure.
 */
function mkstemps($template, $suffixlen)
{
	if ( $suffixlen<0 || strlen($template)<$suffixlen+6 ) return FALSE;

	if ( substr($template,-($suffixlen+6),6)!='XXXXXX' ) return FALSE;

	$letters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$TMP_MAX = 16384;

	umask(0133);

	for($try=0; $try<$TMP_MAX; $try++) {
		$value = mt_rand();

		$filename = $template;
		$pos = strlen($filename)-$suffixlen-6;

		for($i=0; $i<6; $i++) {
			$filename{$pos+$i} = $letters{$value % 62};
			$value /= 62;
		}

		$fd = @fopen($filename,"x");

		if ( $fd !== FALSE ) {
			fclose($fd);
			return $filename;
		}
	}

	// We couldn't create a non-existent filename from the template:
	return FALSE;
}

/**
 * Convert an IPv4 address to the hexadecimal last 2 quads of an IPv6
 * address or return NULL on error.
 */
function ip4toip6sub($addr)
{
	if ( ip2long($addr)==-1 ) return NULL;
	$addr = sprintf("%8X",ip2long($addr));
	$q1 = substr($addr,0,4);
	$q2 = substr($addr,4);
	return $q1.':'.$q2;
}

/**
 * Expand an IPv4/6 address to full IPv6 notation
 *
 * Returns the expanded address as a string of 8 uppercase 4-digit
 * hexadecimal quads or NULL on error. So ::ffff:127.0.0.1 is expanded
 * to '0000:0000:0000:0000:0000:FFFF:FF00:0001'.
 */
function expandipaddr($addr)
{
	// Check for an IPv4 address
	if ( ! strstr($addr,':') ) {
		$addr = ip4toip6sub($addr);
		if ( empty($addr) ) return NULL;
		return '0000:0000:0000:0000:0000:0000:'.$addr;
	}

	// Check for IPv4 notation in last part of addr and translate
	$ip4 = substr($addr,strrpos($addr,':')+1);
	if ( strstr($ip4,'.') ) {
		$ip4 = ip4toip6sub($ip4);
		if ( empty($ip4) ) return NULL;
		$addr = substr($addr,0,strrpos($addr,':')+1).$ip4;
	}

	// Check for IPv6 compressed form and expand
	if ( strstr($addr,'::') ) {
		list($pre, $post) = explode('::',$addr,2);

		// Check for single '::' separator
		if ( strstr($post,'::') ) return NULL;

		// Check and reject unspecified addresses
		if ( empty($pre) && empty($post) ) return NULL;

		// Count # quads in pre and post strings
		if ( empty($pre) ) {
			$npre = 0;
		} else {
			$npre = count(explode(':',$pre));
		}
		if ( empty($post) ) {
			$npost = 0;
		} else {
			$npost = count(explode(':',$post));
		}

		// Create mid part to replace compressed '::' with
		$mid = ':';
		for($i=0; $i<8-($npre+$npost); $i++) $mid .= '0:';

		if ( $npre==0  ) $mid = substr($mid,1);
		if ( $npost==0 ) $mid = substr($mid,0,strlen($mid)-1);

		$addr = str_replace('::',$mid,$addr);
	}

	// Expand all single quads to 4-digit length
	$quads = explode(':',$addr);
	if ( count($quads)!=8 ) return NULL;

	$addr = '';
	foreach($quads as $quad) {
		while ( strlen($quad)<4 ) $quad = '0'.$quad;
		$addr .= ':'.$quad;
	}
	$addr = strtoupper(substr($addr,1));

	if ( ! preg_match('/^([0-9A-F]{4}:){7}[0-9A-F]{4}$/',$addr) ) return NULL;

	return $addr;
}

/**
 * Compares two IP addresses for equivalence
 * Currently IPv6 equivalent address checks are disabled.
 */
function compareipaddr($ip1, $ip2)
{
/*
	$ip1 = expandipaddr($ip1);
	$ip2 = expandipaddr($ip2);
	if ( empty($ip1) || empty($ip2) ) return FALSE;

	// Replace IPv4 IPv6-mapped by IPv4-compatible address
	if ( substr($ip1, 0, 30) == '0000:0000:0000:0000:0000:FFFF:' ) {
		$ip1 = '0000:0000:0000:0000:0000:0000:'.substr($ip1,30);
	}
	if ( substr($ip2, 0, 30) == '0000:0000:0000:0000:0000:FFFF:' ) {
		$ip2 = '0000:0000:0000:0000:0000:0000:'.substr($ip2,30);
	}

	// Replace IPv4 loopback by IPv6 loopback
	if ( $ip1=='0000:0000:0000:0000:0000:0000:7F00:0001' ) {
		$ip1 = '0000:0000:0000:0000:0000:0000:0000:0001';
	}
	if ( $ip2=='0000:0000:0000:0000:0000:0000:7F00:0001' ) {
		$ip2 = '0000:0000:0000:0000:0000:0000:0000:0001';
	}
*/
	return $ip1==$ip2;
}

/**
 * Functions to support graceful shutdown of daemons upon receiving a signal
 */
function sig_handler($signal)
{
	global $exitsignalled;

	logmsg(LOG_DEBUG, "Signal $signal received");

	switch ( $signal ) {
	case SIGTERM:
	case SIGHUP:
	case SIGINT:
		$exitsignalled = TRUE;
	}
}

function initsignals()
{
	global $exitsignalled;

	$exitsignalled = FALSE;

	if ( ! function_exists('pcntl_signal') ) {
		logmsg(LOG_INFO, "Signal handling not available");
		return;
	}

	logmsg(LOG_DEBUG, "Installing signal handlers");

	// Install signal handler for TERMINATE, HANGUP and INTERRUPT
	// signals. The sleep() call will automatically return on
	// receiving a signal.
	pcntl_signal(SIGTERM,"sig_handler");
	pcntl_signal(SIGHUP, "sig_handler");
	pcntl_signal(SIGINT, "sig_handler");
}

/**
 * Forks and detaches the current process to run as a daemon. Similar
 * to the daemon() call present in Linux and *BSD.
 *
 * Argument pidfile is an optional filename to check for running
 * instances and write PID to.
 *
 * Either returns successfully or exits with an error.
 */
function daemonize($pidfile = NULL)
{
	switch ( $pid = pcntl_fork() ) {
	case -1: error("cannot fork daemon");
	case  0: break; // child process: do nothing here.
	default: exit;  // parent process: exit.
	}

	if ( ($pid = posix_getpid())===FALSE ) error("failed to obtain PID");

	// Check and write PID to file
	if ( !empty($pidfile) ) {
		if ( ($fd=@fopen($pidfile, 'x+'))===FALSE ) {
			error("cannot create pidfile '$pidfile'");
		}
		$str = "$pid\n";
		if ( @fwrite($fd, $str)!=strlen($str) ) {
			error(errno, "failed writing PID to file");
		}
		register_shutdown_function('unlink', $pidfile);
	}

	// Notify user with daemon PID before detaching from TTY.
	logmsg(LOG_NOTICE, "daemonizing with PID = $pid");

	// Close std{in,out,err} file descriptors
	if ( !fclose(STDIN ) || !($GLOBALS['STDIN']  = fopen('/dev/null', 'r')) ||
	     !fclose(STDOUT) || !($GLOBALS['STDOUT'] = fopen('/dev/null', 'w')) ||
	     !fclose(STDERR) || !($GLOBALS['STDERR'] = fopen('/dev/null', 'w')) ) {
		error("cannot reopen stdio files to /dev/null");
	}

	// Start own process group, detached from any tty
	if ( posix_setsid()<0 ) error("cannot set daemon process group");
}

/**
 * This function takes a (set of) temporary file(s) of a submission,
 * validates it and puts it into the database. Additionally it
 * moves it to a backup storage.
 */
function submit_solution($team, $prob, $lang, $files, $filenames, $origsubmitid = NULL)
{
	if( empty($team) ) error("No value for Team.");
	if( empty($prob) ) error("No value for Problem.");
	if( empty($lang) ) error("No value for Language.");

	if ( !is_array($files) || count($files)==0 ) error("No files specified.");
	if ( !is_array($filenames) || count($filenames)!=count($files) ) {
		error("Nonmatching (number of) filenames specified.");
	}

	if ( count($filenames)!=count(array_unique($filenames)) ) {
		error("Duplicate filenames detected.");
	}

	global $cdata,$cid, $DB;

	$sourcesize = dbconfig_get('sourcesize_limit');

	// If no contest has started yet, refuse submissions.
	$now = now();

	if( difftime($cdata['starttime'], $now) > 0 ) {
		error("The contest is closed, no submissions accepted. [c$cid]");
	}

	// Check 2: valid parameters?
	if( ! $langid = $DB->q('MAYBEVALUE SELECT langid FROM language WHERE
						  langid = %s AND allow_submit = 1', $lang) ) {
		error("Language '$lang' not found in database or not submittable.");
	}
	if( ! $login = $DB->q('MAYBEVALUE SELECT login FROM team WHERE login = %s',$team) ) {
		error("Team '$team' not found in database.");
	}
	$team = $login;
	if( ! $probid = $DB->q('MAYBEVALUE SELECT probid FROM problem WHERE probid = %s
							AND cid = %i AND allow_submit = "1"', $prob, $cid) ) {
		error("Problem '$prob' not found in database or not submittable [c$cid].");
	}

	// Reindex arrays numerically to allow simultaneously iterating
	// over both $files and $filenames.
	$files     = array_values($files);
	$filenames = array_values($filenames);

	$totalsize = 0;
	for($i=0; $i<count($files); $i++) {
		if ( ! is_readable($files[$i]) ) {
			error("File '".$files[$i]."' not found (or not readable).");
		}
		if ( ! preg_match(FILENAME_REGEX, $filenames[$i]) ) {
			error("Illegal filename '".$filenames[$i]."'.");
		}
		$totalsize += filesize($files[$i]);
	}
	if ( $totalsize > $sourcesize*1024 ) {
		error("Submission file(s) are larger than $sourcesize kB.");
	}

	logmsg (LOG_INFO, "input verified");

	// Insert submission into the database
	$id = $DB->q('RETURNID INSERT INTO submission
				  (cid, teamid, probid, langid, submittime, origsubmitid)
				  VALUES (%i, %s, %s, %s, %s, %i)',
	             $cid, $team, $probid, $langid, $now, $origsubmitid);

	for($rank=0; $rank<count($files); $rank++) {
		$DB->q('INSERT INTO submission_file
		        (submitid, filename, rank, sourcecode) VALUES (%i, %s, %i, %s)',
		       $id, $filenames[$rank], $rank, getFileContents($files[$rank], false));
	}

	// Recalculate scoreboard cache for pending submissions
	calcScoreRow($cid, $team, $probid);

	// Log to event table
	$DB->q('INSERT INTO event (eventtime, cid, teamid, langid, probid, submitid, description)
	        VALUES(%s, %i, %s, %s, %s, %i, "problem submitted")',
	       now(), $cid, $team, $langid, $probid, $id);

	if ( is_writable( SUBMITDIR ) ) {
		// Copy the submission to SUBMITDIR for safe-keeping
		for($rank=0; $rank<count($files); $rank++) {
			$fdata = array('cid' => $cid,
			               'submitid' => $id,
			               'teamid' => $team,
			               'probid' => $probid,
			               'langid' => $langid,
			               'rank' => $rank,
			               'filename' => $filenames[$rank]);
			$tofile = SUBMITDIR . '/' . getSourceFilename($fdata);
			if ( ! @copy($files[$rank], $tofile) ) {
				warning("Could not copy '" . $files[$rank] . "' to '" . $tofile . "'");
			}
		}
	} else {
		logmsg(LOG_DEBUG, "SUBMITDIR not writable, skipping");
	}

	if( difftime($cdata['endtime'], $now) <= 0 ) {
		logmsg(LOG_INFO, "The contest is closed, submission stored but not processed. [c$cid]");
	}

	return $id;
}

/**
 * Compute the filename of a given submission. $fdata must be an array
 * that contains the data from submission and submission_file.
 */
function getSourceFilename($fdata)
{
	return implode('.', array('c'.$fdata['cid'], 's'.$fdata['submitid'],
	                          $fdata['teamid'], $fdata['probid'], $fdata['langid'],
	                          $fdata['rank'], $fdata['filename']));
}

/**
 * Output generic version information and exit.
 */
function version()
{
	echo SCRIPT_ID . " -- part of DOMjudge version " . DOMJUDGE_VERSION . "\n" .
		"Written by the DOMjudge developers\n\n" .
		"DOMjudge comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n" .
		"are welcome to redistribute it under certain conditions.  See the GNU\n" .
		"General Public Licence for details.\n";
	exit(0);
}

/**
 * Word wrap only unquoted text.
 */
function wrap_unquoted($text, $width = 75, $quote = '>')
{
	$lines = explode("\n", $text);

	$result = '';
	$unquoted = '';

	foreach( $lines as $line ) {
		// Check for quoted lines
		if ( strspn($line,$quote)>0 ) {
			// First append unquoted text wrapped, then quoted line:
			$result .= wordwrap($unquoted,$width);
			$unquoted = '';
			$result .= $line . "\n";
		} else {
			$unquoted .= $line . "\n";
		}
	}

	$result .= wordwrap(rtrim($unquoted),$width);

	return $result;
}

/**
 * DOM XML tree helper functions (PHP 5).
 * The XML tree is assumed to be named '$xmldoc' and the XPath object '$xpath'.
 */

/**
 * Create node and add below $paren.
 * $value is an optional element value and $attrs an array whose
 * key,value pairs are added as node attributes. All strings are htmlspecialchars
 */
function XMLaddnode($paren, $name, $value = NULL, $attrs = NULL)
{
	global $xmldoc;

	if ( $value === NULL ) {
		$node = $xmldoc->createElement(htmlspecialchars($name));
	} else {
		$node = $xmldoc->createElement(htmlspecialchars($name), htmlspecialchars($value));
	}

	if ( count($attrs) > 0 ) {
		foreach( $attrs as $key => $value ) {
			$node->setAttribute(htmlspecialchars($key), htmlspecialchars($value));
		}
	}

	$paren->appendChild($node);
	return $node;
}

/**
 * Retrieve node by a path from root, or relative to paren if non-null.
 * Generates error if no or more than one nodes are found.
 */
function XMLgetnode($path, $paren = NULL)
{
	global $xpath;

	$nodelist = $xpath->query($path,$paren);

	if ( $nodelist->length!=1 ) error("Not exactly one XML node found");

	return $nodelist->item(0);
}

/**
 * Returns attribute value of a node, or null if attribute does not exist.
 */
function XMLgetattr($node, $attr)
{
	$attrnode = $node->attributes->getNamedItem($attr);

	if ( $attrnode===NULL ) return NULL;

	return $attrnode->nodeValue;
}

/**
 * Log an action to the auditlog table.
 */
function auditlog($datatype, $dataid, $action, $extrainfo = null, $username = null)
{
	global $cid, $login, $DB;

	if ( !empty($username) ) {
		$user = $username;
	} elseif ( IS_JURY ) {
		$user = getJuryMember();
	} else {
		$user = $login;
	}

	$DB->q('INSERT INTO auditlog
	        (logtime, cid, user, datatype, dataid, action, extrainfo)
	        VALUES(%s, %i, %s, %s, %s, %s, %s)',
	       now(), $cid, $user, $datatype, $dataid, $action, $extrainfo);
}
