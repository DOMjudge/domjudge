<?php

/* $Id$ */

/**
 * helperfunction to read 50,000 bytes from a file
 */
function getFileContents($filename) {

	if ( ! file_exists($filename) ) return '';
	$fh = fopen($filename,'r');
	if ( ! $fh ) error("Could not open $filename for reading");

	$note = (filesize($filename) > 50000 ? "\n[output truncated after 50,000 B]\n" : '');
	
	return fread($fh, 50000) . $note;
}

/**
 * Will return either the current contest id, or
 * the most recently finished one.
 * When fulldata is true, returns the total row as an array
 * instead of just the ID.
 */
function getCurContest($fulldata = FALSE) {

	global $DB;
	$now = $DB->q('SELECT * FROM contest
	               WHERE starttime <= NOW() AND endtime >= NOW()');
	
	if ( $now->count() == 1 ) {
		$row = $now->next();
		$retval = ( $fulldata ? $row : $row['cid'] );
	}
	if ( $now->count() == 0 ) {
		$prev = $DB->q('SELECT * FROM contest
		                WHERE endtime <= NOW() ORDER BY endtime DESC LIMIT 1');
		if ( $prev->count() == 0 ) return FALSE;
		$row = $prev->next();
		$retval = ( $fulldata ? $row : $row['cid'] );
	}
	if ( $now->count() > 1 ) {
		error("Contests table contains overlapping contests");
	}
	
	return $retval;
}

/**
 * Add another key/value to a url
 */
function addUrl($url, $keyvalue, $encode = TRUE)
{
	if ( empty($keyvalue) ) return $url;
	$separator = (strrpos($url, '?')===False) ? '?' : ( $encode ? '&amp;' : '&');
	return $url . $separator . $keyvalue;
}

/**
 * Scoreboard calculation
 *
 * This is here because it needs to be called by the submit_db script
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
	                  (c.lastscoreupdate IS NOT NULL && submittime >= c.lastscoreupdate) AS afterfreeze
	                  FROM judging
	                  LEFT JOIN submission s USING(submitid)
	                  LEFT OUTER JOIN contest c ON(c.cid=s.cid)
	                  WHERE teamid = %s AND probid = %s AND valid = 1 AND
	                  result IS NOT NULL AND s.cid = %i ORDER BY submittime',
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

	// insert or update the values in the jury scores table
	$DB->q('REPLACE INTO scoreboard_jury
	        (cid, teamid, probid, submissions, totaltime, penalty, is_correct, balloon)
	        VALUES (%i,%s,%s,%i,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_p, $time_p, $penalty_p, $correct_p, $balloon);

	// insert or update the values in the public/team scores table
	$DB->q('REPLACE INTO scoreboard_public
	        (cid, teamid, probid, submissions, totaltime, penalty, is_correct, balloon)
	        VALUES (%i,%s,%s,%i,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_j, $time_j, $penalty_j, $correct_j, $balloon);

	return;
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
		$value = rand();

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

	if ( ! ereg('^([0-9A-F]{4}:){7}[0-9A-F]{4}$',$addr) ) return NULL;

	return $addr;
}

/**
 * Compares two IP addresses for equivalence
 */
function compareipaddr($ip1, $ip2)
{
	$ip1 = expandipaddr($ip1);
	$ip2 = expandipaddr($ip2);
	if ( empty($ip1) || empty($ip2) ) return FALSE;

	// Replace IPv4 IPv6-mapped by IPv4-compatible address
	if ( ereg('^0000:0000:0000:0000:0000:FFFF:',$ip1) ) {
		$ip1 = '0000:0000:0000:0000:0000:0000:'.substr($ip1,30);
	}
	if ( ereg('^0000:0000:0000:0000:0000:FFFF:',$ip2) ) {
		$ip2 = '0000:0000:0000:0000:0000:0000:'.substr($ip2,30);
	}

	// Replace IPv4 loopback by IPv6 loopback
	if ( $ip1=='0000:0000:0000:0000:0000:0000:7F00:0001' ) {
		$ip1 = '0000:0000:0000:0000:0000:0000:0000:0001';
	}
	if ( $ip2=='0000:0000:0000:0000:0000:0000:7F00:0001' ) {
		$ip2 = '0000:0000:0000:0000:0000:0000:0000:0001';
	}
	
	return $ip1==$ip2;
}
