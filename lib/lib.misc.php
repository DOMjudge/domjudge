<?php

/* $Id$ */

/**
 * helperfunction to read 50,000 bytes from a file
 */
function getFileContents($filename) {

	if ( ! file_exists($filename) ) return '';
	$fh = fopen($filename,'r');
	if ( ! $fh ) error("Could not open $filename for reading");

	$note = (filesize($filename) > 50000 ? "\n[output truncated after 50.000 B]\n" : '');
	
	return fread($fh, 50000) . $note;
}

/**
 * Will return either the current contest, or else the upcoming one
 */
function getCurContest() {

	global $DB;
	$now = $DB->q('SELECT cid FROM contest
	               WHERE starttime <= NOW() AND endtime >= NOW()');
	
	if ( $now->count() == 1 ) {
		$row = $now->next();
		$curcontest = $row['cid'];
	}
	if ( $now->count() == 0 ) {
		$prev = $DB->q('SELECT cid FROM contest
				WHERE endtime <= NOW()
				ORDER BY endtime DESC
				LIMIT 1');	
		$row = $prev->next();
		$curcontest = $row['cid'];
	}
	if ( $now->count() > 1 ) {
		error("Contests table contains overlapping contests");
	}
	
	return $curcontest;
}

/**
 * Add another key/value to a url
 */
function addUrl($url, $keyvalue)
{
	return $url.(strrpos($url, '?')===False ? '?' : '&' ).$keyvalue;
}
