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

function notification_text($team, $problem) {
	return
		"Notification of a problem solved:\n".
		"\n".
		(empty($team['room']) ? "" : "Room:    $team[room]\n" ) .
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
		$res = $DB->q('SELECT s.*,t.name as teamname,t.room,p.name as probname
		               FROM scoreboard_public s
		               LEFT JOIN problem p USING(probid)
		               LEFT JOIN team t ON (t.login = s.team)
		               WHERE s.cid = %i AND s.is_correct = 1 AND s.balloon = 0',
					  $cid);

		while ( $row = $res->next() ) {
			$team = array ('name'   => $row['teamname'],
			               'room'   => $row['room'],
				       'login'  => $row['team']);
			$prob = array ('name'   => $row['probname'],
			               'probid' => $row['probid']);

			logmsg(LOG_DEBUG,"New problem solved: ".$row['probid'].
				   " by team ".$row['team']);
			
			if ( defined('BALLOON_CMD') && BALLOON_CMD ) {
				
				logmsg(LOG_INFO,"Sending notification: team '".
					   $row['team']."', problem '".$row['probid']."'.");
				
				logmsg(LOG_DEBUG,"Running command: '".BALLOON_CMD."'");
				
				$handle = popen(BALLOON_CMD, 'w');
				if ( ! $handle ) error("Could not run command '".BALLOON_CMD."'");
				
				fwrite($handle,notification_text($team,$prob));
				if ( ($exitcode = pclose($handle))!=0 ) {
					warning("Notification command exited with exitcode $exitcode");
				}
			}
			
			$DB->q('UPDATE scoreboard_public SET balloon=1
			        WHERE cid = %i AND team = %s AND probid = %s',
				   $row['cid'], $row['team'], $row['probid']);
		}
		
	} while ( $res->count()!=0 );

	sleep($waittime);
}
