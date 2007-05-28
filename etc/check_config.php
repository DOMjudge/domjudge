#!/usr/bin/php -q
<?php
/**
 * This script does a sanity check of a number of the config settings.
 * This helps verifying that config changes are correct and to quickly
 * spot any configuration errors.
 * It checks such things as permissions, dirs set up, tools needed etc.
 *
 * There's also an equivalent in the webinterface that checks the
 * database-integrity.
 *
 * $Id$
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

$nwarnings = 0;

function warn($msg) {
	$nwarnings++;
	logmsg(LOG_WARNING, $msg);
}

require ('../etc/config.php');

define ('SCRIPT_ID', 'check_config');
define ('LOGFILE', LOGDIR.'/check_config.log');

require (SYSTEM_ROOT . '/lib/init.php');

logmsg(LOG_NOTICE, "started [DOMjudge/" . DOMJUDGE_VERSION . "]");

logmsg(LOG_DEBUG, "checking dirs");
// check some dirs for existence and read/writablility
define ('LIBDIR', SYSTEM_ROOT . '/lib');
$dirstocheck = array (
	'SYSTEM_ROOT' => 'r',
	'OUTPUT_ROOT' => 'rw',
	'INPUT_ROOT' => 'r',
	'LIBDIR' => 'r',
	'INCOMINGDIR' => 'rw',
	'SUBMITDIR' => 'rw',
	'JUDGEDIR' => 'rw',
	'LOGDIR' => 'rw',
	'CHROOT_PREFIX' => 'r'
	);

foreach ( $dirstocheck as $dir => $ops ) {
	$realdir = constant($dir);
	if( ! file_exists($realdir) ) { warn("$dir [$realdir] does not exist!"); continue; }
	if( ! is_dir($realdir) )      { warn("$dir [$realdir] is not a directory!"); continue; }
	if( strstr($ops,'r') &&
	    ! is_readable($realdir) ) { warn("$dir [$realdir] is not readable!"); continue; }
	if( strstr($ops,'w') &&
	    ! is_writable($realdir) ) { warn("$dir [$realdir] is not writable!"); continue; }
}

logmsg(LOG_DEBUG, "checking users");

// does our runuser even exist?
// In PHP 4.1.2 this crashes when the user doesn't exist, but the
// warning is output anyway.
if ( ! @posix_getpwnam(RUNUSER) ) warn("RUNUSER [" . RUNUSER ."] does not exist!");

// check problems. 
logmsg(LOG_DEBUG, "checking problems");

global $DB;	
$probs = $DB->q('SELECT probid,testdata FROM problem ORDER BY cid,probid');

// check whether the problem input/output is readable by me.
$inout = array('in','out');
while ( $row = $probs->next() ) {
	foreach($inout as $i) {
		$testdata = INPUT_ROOT . '/' . $row['testdata'] . '/testdata.' . $i;
		if ( ! file_exists ( $testdata ) ) {
			warn("problem $row[probid] testdata.$i [$testdata] does not exist!");
		} elseif ( ! is_readable ( $testdata ) ) {
			warn("problem $row[probid] testdata.$i [$testdata] not readable!");
		}
	}
}

logmsg(LOG_NOTICE, 'end: ' . $nwarnings .' warnings');

exit($nwarnings);
