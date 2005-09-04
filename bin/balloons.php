#!/usr/bin/php -q
<?php

/**
 * Notify contest crew when there is a new, correct submission (for
 * which a balloon has to be handed out).
 *
 * $Id$
 */

require ('../etc/config.php');

define ('SCRIPT_ID', 'balloons');
define ('LOGFILE', LOGDIR.'/balloons.log');

require (SYSTEM_ROOT . '/lib/init.php');

$verbose = LOG_INFO;

$waittime = 5;

function notification_title($team, $problem) {
	return "Team ".$team['login']." solved problem ".$problem['probid'];
}

function notification_body($team, $problem) {
	return
		"Notification of a problem solved:\n".
		"\n".
		"Team:    ".$team['login'].": ".$team['name']."\n".
		"Problem: ".$problem['probid'].": ".$problem['name']."\n";
}

$cid = getCurContest();

logmsg(LOG_NOTICE, "Balloon notifications started [DOMjudge/".DOMJUDGE_VERSION."]");

// Constantly check database for new correct submissions
while ( TRUE ) {

	$newcid = getCurContest();
	$oldcid = $cid;
	if ( $oldcid !== $newcid ) {
		logmsg(LOG_NOTICE, "Contest has changed from " .
		       (isset($oldcid) ? "c$oldcid" : "none" ) . " to " .
		       (isset($newcid) ? "c$newcid" : "none" ) );
		$cid = $newcid;
	}
	
	do {
		$res = $DB->q('SELECT * FROM scoreboard_public
		               WHERE cid = %i AND is_correct = 1 AND balloon = 0',
					  $cid);

		while ( $row = $res->next() ) {
			
			$team = $DB->q('TUPLE SELECT * FROM team    WHERE login  = %s',
			               $row['team']);
			$prob = $DB->q('TUPLE SELECT * FROM problem WHERE probid = %s',
			               $row['problem']);

			$title = notification_title($team,$prob);
			$body  = notification_body ($team,$prob);
			
			if ( defined('BALLOON_EMAIL') && BALLOON_EMAIL ) {
				
				logmsg(LOG_INFO,"Mailing notification: team '".
					   $row['team']."', problem '".$row['team']."'.");
				
				$handle = popen("mail -s '".$title."' ".BALLOON_EMAIL, 'w');
				if ( ! $handle ) error("Could not mail notification");
				
				fwrite($handle,$body);
				if ( ($exitcode = pclose($handle))!=0 ) {
					warning("Mail command exited with exitcode $exitcode");
				}
			}
			
			if ( defined('BALLOON_PRINT') && BALLOON_PRINT ) {

				logmsg(LOG_INFO,"Printing notification: team '".
					   $row['team']."', problem '".$row['team']."'.");
				
				$handle = popen(BALLOON_PRINT, 'w');
				if ( ! $handle ) error("Could not print notification");
				
				fwrite($handle,$body);
				if ( ($exitcode = pclose($handle))!=0 ) {
					warning("Print command exited with exitcode $exitcode");
				}
			}
			
			$DB->q('UPDATE scoreboard_public SET balloon=1
			        WHERE cid = %i AND team = %s AND problem = %s',
				   $row['cid'], $row['team'], $row['problem']);
		}
		
	} while ( $res->count()!=0 );

	sleep($waittime);
}
