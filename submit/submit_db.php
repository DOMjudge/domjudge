#!/usr/bin/php -q
<?php
/**
 * Called by commandline/web submitdaemon.
 * Given the details of a submission, check the parameters for validity
 * (is the contest open? is the problem valid? is this really the team?)
 * and if ok, copy the file from INCOMING to SUBMIT and add a database
 * entry.
 *
 * Called: submit_db.php <team> <ip> <problem> <langext> <filename>
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require ('../etc/config.php');

define ('SCRIPT_ID', 'submit_db');
define ('LOGFILE', LOGDIR.'/submit.log');

require (SYSTEM_ROOT . '/lib/init.php');

// Get commandline vars and case-normalize them
$argv = $_SERVER['argv'];

$team    = strtolower(@$argv[1]);
$ip      = @$argv[2];
$prob    = strtolower(@$argv[3]);
$langext = strtolower(@$argv[4]);
$file    = @$argv[5];

logmsg(LOG_DEBUG, "arguments: '$team' '$ip' '$prob' '$langext' '$file'");

$cdata = getCurContest(TRUE);
$cid = $cdata['cid'];

$sid = submit_solution($team, $ip, $prob, $langext, INCOMINGDIR."/$file");
	
logmsg(LOG_NOTICE, "submitted $team/$prob/$langext, id s$sid/c$cid");

exit;
