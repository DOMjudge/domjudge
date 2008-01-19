#!/usr/bin/php -q
<?php
/**
 * Program to rebuild the DB submissions table from submitted sources
 * on the filesystem. Can be useful in case of a database crash.
 *
 * Does not do any checking on valid parameters (team, problem, etc.)
 * and inserts all validly-named source files for all contests. The
 * file modification times are used as submittime.
 * 
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require('../etc/config.php');

define('SCRIPT_ID', 'restore_sources2db');
define('LOGFILE', LOGDIR.'/auxiliary.log');

require(SYSTEM_ROOT . '/lib/init.php');

$sourcesdir = SUBMITDIR;

logmsg(LOG_NOTICE, "started, sources dir = '$sourcesdir'");

if ( ($filelist = scandir($sourcesdir))===FALSE ) {
	error("cannot read directory '$sourcesdir'");
}

$submissions = array();

foreach ( $filelist as $src ) {

	$f = $sourcesdir . '/' . $src;

	if ( !(is_file($f) && is_readable($f)) ) {
		logmsg(LOG_DEBUG, "skipping '$src': not a readable file");
		continue;
	}

	// Reconstruct submission data from filename and mtime
	$fdata = explode('.',$src);
	if ( count($fdata)!=5 ) {
		logmsg(LOG_DEBUG, "skipping '$src': does not match pattern");
		continue;
	}
	list($cid, $login, $probid, $foo, $langid) = $fdata;
	$cid = substr($cid,1);

	$submittime = date('Y-m-d H:i:s',filemtime($f));

	// Store in array for later sorting
	$submissions[] = array('file'       => $f,
	                       'cid'        => $cid,
	                       'login'      => $login,
	                       'probid'     => $probid,
	                       'langid'     => $langid,
	                       'submittime' => $submittime);
}

// sort submissions on datetime
function cmpsubmittime($a, $b) {
	return strcmp($a['submittime'], $b['submittime']);
}
usort($submissions, 'cmpsubmittime');

foreach ( $submissions as $s ) {
	
	$cid    = $s['cid'];
	$login  = $s['login'];
	$probid = $s['probid'];
	$langid = $s['langid'];
	$src    = basename($s['file']);
	
	// Insert submissions into the database
	$id = $DB->q('RETURNID INSERT INTO submission
	              (cid,teamid,probid,langid,submittime,sourcefile,sourcecode)
	              VALUES (%i, %s, %s, %s, %s, %s, %s)',
	             $cid, $login, $probid, $langid, $s['submittime'], $src,
	             getFileContents($s['file'], false));

	// Log to event table
	$DB->q('INSERT INTO event (cid, teamid, langid, probid, submitid, description)
	        VALUES(%i, %s, %s, %s, %i, "problem submitted")',
	       $cid, $login, $langid, $probid, $id);

	logmsg(LOG_DEBUG, "inserted $login/$probid/$langid, file $src, id s$id/c$cid");
}

logmsg(LOG_NOTICE, "finished");

exit;
