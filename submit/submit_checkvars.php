#!/usr/bin/php4 -q
<?php
// $Id $
	
require ('../etc/config.php');
require ('../php/init.php');

	$argv = $GLOBALS['argv'];
	
	$team = @$argv[1];
	$ip   = @$argv[2];
	$prob = @$argv[3];
	$lang = @$argv[4];
	$file = @$argv[5];

	// Check 0: called correctly?
	if(!$team)	error("No value for team.");
	if(!$ip)	error("No value for ip.");
	if(!$prob)	error("No value for prob.");
	if(!$lang)	error("No value for lang.");
	if(!$file)	error("No value for file.");
	
	// Check 1: valid parameters?
	if(!$DB->q('MAYBETUPLE SELECT * FROM language WHERE langid = %s',
		$lang) ){
		error("Language '$lang' not found in database");
	}
	if(!$row = $DB->q('MAYBETUPLE SELECT * FROM team WHERE login = %s',
		$team) ){
		error("Team '$team' not found in database");
	}
	if($row['ipadres'] != $ip) {
		error("Team '$team' not registered at this IP address.");
	}
	if(!$DB->q('MAYBETUPLE SELECT * FROM problem WHERE probid = %s
		AND allow_submit = "1"',
		$prob) ){
		error("Problem '$prob' not found in database or not submittable.");
	}

	logmsg ("checkvars: input verified");
	exit;
