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
	require ('../etc/config.php');

	define ('SCRIPT_ID', 'check_config');
	define ('LOGFILE', LOGDIR.'/check_config.log');

	require ('../lib/init.php');
	
	logmsg(LOG_NOTICE, "started [DOMjudge/" . DOMJUDGE_VERSION . "]");

	// check some dirs for existence and read/writablility
	$dirstocheck = array (
		'SYSTEM_ROOT' => 'r',
		'OUTPUT_ROOT' => 'rw',
		'INPUT_ROOT' => 'r',
		'LIBDIR' => 'r',
		'INCOMINGDIR' => 'rw',
		'SUBMITDIR' => 'rw',
		'JUDGEDIR' => 'rw',
		'LOGDIR' => 'rw'
#		CHROOT_PREFIX => ' ???
		);

	foreach ($dirstocheck as $dir => $ops) {
		$realdir = constant($dir);
		if( ! file_exists ($realdir) )	{ logmsg(LOG_WARNING, "$dir [$realdir] does not exist!" ); continue; }
		if( ! is_dir($realdir) )		{ logmsg(LOG_WARNING, "$dir [$realdir] is not a directory!" ); continue; }
		if( strstr($ops,'r') &&
			! is_readable ($realdir) )	{ logmsg(LOG_WARNING, "$dir [$realdir] is not readable!" ); continue; }
		if( strstr($ops,'w') &&
			! is_writable ($realdir) )	{ logmsg(LOG_WARNING, "$dir [$realdir] is not writable!" ); continue; }
	}

	// does our runuser even exist?
	// In PHP 4.1.2 this crashes when the user doesn't exist, but the warning is output anyway.
	if ( ! @posix_getpwnam( RUNUSER ) ) {
		logmsg(LOG_WARNING, "RUNUSER [" . RUNUSER ."] does not exist!");
	}


	// add more tests here
		

	logmsg(LOG_NOTICE, "end");

	exit;
