<?php
/**
 * Miscellaneous helper functions
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */


/** Constant to define MySQL datetime format in PHP date() function notation. */
define('MYSQL_DATETIME_FORMAT', 'Y-m-d H:i:s');


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
		// When our lowest supported PHP version >= 5.1.0, we can just use
		// file_get_contents() with the maxlen parameter.
		$fh = fopen($filename,'r');
		if ( ! $fh ) error("Could not open $filename for reading");
		$ret = fread($fh, 50000) . "\n[output truncated after 50,000 B]\n";
		fclose($fh);
		return $ret;
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
	               WHERE activatetime <= NOW() ORDER BY activatetime DESC LIMIT 1');

	if ($now == NULL)
		return FALSE;
	else
		return ( $fulldata ? $now : $now['cid'] );
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

	$result = $DB->q('SELECT result, verified, 
	                  (UNIX_TIMESTAMP(submittime)-UNIX_TIMESTAMP(c.starttime))/60 AS timediff,
	                  (c.freezetime IS NOT NULL && submittime >= c.freezetime) AS afterfreeze
	                  FROM judging j
	                  LEFT JOIN submission s USING(submitid)
	                  LEFT OUTER JOIN contest c ON(c.cid=s.cid)
	                  WHERE teamid = %s AND probid = %s AND j.valid = 1 AND
	                  result IS NOT NULL AND s.cid = %i AND s.valid = 1
					  ORDER BY submittime',
	                 $team, $prob, $cid);

	$balloon = $DB->q('MAYBEVALUE SELECT balloon FROM scoreboard_jury
                       WHERE cid = %i AND teamid = %s AND probid = %s',
	                  $cid, $team, $prob);
	
	if ( ! $balloon ) $balloon = 0;
	
	// reset vars
	$submitted_j = $penalty_j = $time_j = $correct_j = 0;
	$submitted_p = $penalty_p = $time_p = $correct_p = 0;

	// for each submission
	while( $row = $result->next() ) {

		if ( VERIFICATION_REQUIRED && ! $row['verified'] ) continue;
		
		$submitted_j++;
		if ( ! $row['afterfreeze'] ) $submitted_p++;

		// if correct, don't look at any more submissions after this one
		if ( $row['result'] == 'correct' ) {

			$correct_j = 1;
			$time_j = round((int)@$row['timediff']);
			if ( ! $row['afterfreeze'] ) {
				$correct_p = 1;
				$time_p = round((int)@$row['timediff']);
			}
			// if correct, we don't add penalty time for any later submissions
			break;
		}

		// extra penalty minutes for each submission
		// (will only be counted if this problem is correctly solved)
		$penalty_j += PENALTY_TIME;
		if ( ! $row['afterfreeze'] ) $penalty_p += PENALTY_TIME;
		
	}

	// calculate penalty time: only when correct add it to the total
	if ( $correct_j == 0 ) $penalty_j = 0;
	if ( $correct_p == 0 ) $penalty_p = 0;

	// insert or update the values in the public/team scores table
	$DB->q('REPLACE INTO scoreboard_public
	        (cid, teamid, probid, submissions, totaltime, penalty, is_correct)
	        VALUES (%i,%s,%s,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_p, $time_p, $penalty_p, $correct_p);

	// insert or update the values in the jury scores table
	$DB->q('REPLACE INTO scoreboard_jury
	        (cid, teamid, probid, submissions, totaltime, penalty, is_correct, balloon)
	        VALUES (%i,%s,%s,%i,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_j, $time_j, $penalty_j, $correct_j, $balloon);

	return;
}

/**
 * Simulate MySQL NOW() function to create insert queries that do not
 * change when replicated later.
 */
function now()
{
	return date(MYSQL_DATETIME_FORMAT);
}

/**
 * Returns >0, =0, <0 when $time1 >, =, < $time2 respectively.
 * This function currently uses string-based compare on the MySQL
 * format (see above), but is abstracted here for possible changes,
 * e.g. a C-like implementation with a numeric representation.
 */
function difftime($time1, $time2)
{
	return strcmp($time1, $time2);
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
 * This function takes a temporary file of a submission,
 * validates it and puts it into the database. Additionally it
 * moves it to a backup storage.
 */
function submit_solution($team, $ip, $prob, $langext, $file)
{
	if( empty($team)    ) error("No value for Team.");
	if( empty($ip)      ) error("No value for IP.");
	if( empty($prob)    ) error("No value for Problem.");
	if( empty($langext) ) error("No value for Language.");
	if( empty($file)    ) error("No value for Filename.");

	global $cdata,$cid, $DB;

	// If no contest has started yet, refuse submissions.
	$now = now();
	
	if( difftime($cdata['starttime'], $now) > 0 ) {
		error("The contest is closed, no submissions accepted. [c$cid]");
	}

	// Check 2: valid parameters?
	if( ! $lang = $DB->q('MAYBEVALUE SELECT langid FROM language WHERE
						  extension = %s AND allow_submit = 1', $langext) ) {
		error("Language '$langext' not found in database or not submittable.");
	}
	if( ! $teamrow = $DB->q('MAYBETUPLE SELECT * FROM team WHERE login = %s',$team) ) {
		error("Team '$team' not found in database.");
	}
	$team = $teamrow['login'];
	if( ! compareipaddr($teamrow['ipaddress'],$ip) ) {
		if ( $teamrow['ipaddress'] == NULL && ! STRICTIPCHECK ) {
			$hostname = gethostbyaddr($ip);
			if ( $hostname == $ip ) $hostname = NULL;
			$DB->q('UPDATE team SET ipaddress = %s, hostname = %s WHERE login = %s',$ip,$hostname,$team);
			logmsg (LOG_NOTICE, "Registered team '$team' at address '$ip'.");
		} else {
			error("Team '$team' not registered at this IP address.");
		}
	}
	if( ! $probid = $DB->q('MAYBEVALUE SELECT probid FROM problem WHERE probid = %s
							AND cid = %i AND allow_submit = "1"', $prob, $cid) ) {
		error("Problem '$prob' not found in database or not submittable [c$cid].");
	}
	if( ! is_readable($file) ) {
		error("File '$file' not found (or not readable).");
	}
	if( filesize($file) > SOURCESIZE*1024 ) {
		error("Submission file is larger than ".SOURCESIZE." kB."); 
	}

	logmsg (LOG_INFO, "input verified");

	// Insert submission into the database
	$id = $DB->q('RETURNID INSERT INTO submission
				  (cid, teamid, probid, langid, submittime, sourcecode)
				  VALUES (%i, %s, %s, %s, %s, %s)',
				 $cid, $team, $probid, $lang, $now,
				 getFileContents($file, false));

	// Log to event table
	$DB->q('INSERT INTO event (eventtime, cid, teamid, langid, probid, submitid, description)
			VALUES(%s, %i, %s, %s, %s, %i, "problem submitted")',
		   now(), $cid, $team, $lang, $prob, $id);

	$tofile = getSourceFilename($cid,$id,$team,$prob,$langext);
	$topath = SUBMITDIR . "/$tofile";

	if ( is_writable( SUBMITDIR ) ) {
		// Copy the submission to SUBMITDIR for safe-keeping
		if ( ! @copy($file, $topath) ) {
			warning("Could not copy '" . $file . "' to '" . $topath . "'");
		}
	} else {
		logmsg(LOG_DEBUG, "SUBMITDIR not writable, skipping");
	}

	if( difftime($cdata['endtime'], $now) <= 0 ) {
		warning("The contest is closed, submission stored but not processed. [c$cid]");
	}

	return $id;
}

/**
 * Compute the filename of a given submission.
 */
function getSourceFilename($cid,$sid,$team,$prob,$langext)
{
	return "c$cid.s$sid.$team.$prob.$langext";
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
 * Links helper.
 */
function make_link($name, $url, $condition = TRUE, $raw = FALSE)
{
	$result = $name;

	if (!$raw)
		$result = htmlspecialchars($result);

	if ($condition && ($url != NULL)) {
		$result = '<a href="' . htmlspecialchars($url) . '">' . $result . '</a>';
	}

	return $result;
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
 * The XML tree is assumed to be named '$xmldoc'.
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
