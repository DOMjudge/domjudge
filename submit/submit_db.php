#!/usr/bin/php4 -q
<?php
/**
 * $Id$
 */

	require('../etc/config.php');
	require('../php/init.php');

	$argv = $GLOBALS['argv'];
	
	$team = @$argv[1];
	$ip   = @$argv[2];
	$prob = @$argv[3];
	$lang = @$argv[4];
	$file = @$argv[5];

	// Check 0: called correctly?
	if(!$team)	error("No value for team.");
	if(!$ip)	error("No value for ip.");
	if(!$prob)	error("No value for problem.");
	if(!$lang)	error("No value for language.");
	if(!$file)	error("No value for file.");

	$id = $DB->q('RETURNID INSERT INTO submission 
		(team,probid,langid,submittime,source)
		VALUES (%s, %s, %s, NOW(), %s)',
		$team, $prob, $lang, $file);

	logmsg ("Submitted $team-$prob-$lang, filename: $file with id: $id");

	exit;
