<?php

/* $Id$ */

/**
 * helperfunction to read 50,000 bytes from a file
 */
function getFileContents($filename) {

	if ( ! file_exists($filename) ) return '';
	$fh = fopen($filename,'r');
	if ( ! $fh ) error("Could not open $filename for reading");
	return fread($fh, 50000);
}

/**
 * Will return either the current contest, or else the upcoming one
 */
function getCurContest() {
	static $curcontest;
	if(isset($curcontest)) return $curcontest;

	global $DB;
	return $curcontest = $DB->q('MAYBEVALUE SELECT cid FROM contest
	                             ORDER BY starttime DESC LIMIT 1');
}

