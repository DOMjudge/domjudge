#!/usr/bin/php -q
<?php
/**
 * Notify contest crew when there is a new, correct submission (for
 * which a balloon has to be handed out).
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require ('../etc/config.php');

define ('SCRIPT_ID', 'balloons');
define ('LOGFILE', LOGDIR.'/balloons.log');

require (SYSTEM_ROOT . '/lib/init.php');

$verbose = LOG_INFO;

$waittime = 5;

/**
 * Returns a text to be sent when notifying of a new balloon.
 */
function notification_text($team, $problem, $probs_solved, $probs_data) {
	global $cdata;
	
	$ret = 
		"Notification of a problem solved:\n".
		"\n".
		(empty($team['room']) ? "" : "Room:    ".$team['room']."\n" ) .
		"Team:    ".$team['login'].": ".$team['name']."\n".
		"Problem: ".$problem.": ".$probs_data[$problem]['name'].
		(empty($probs_data[$problem]['color']) ? "" : " (colour: ".$probs_data[$problem]['color'].")" ) . "\n\n" .
		"Current balloon status:\n";

	foreach($probs_solved as $probid) {
		$ret .= " - " . $probid .": " . $probs_data[$probid]['name'] .
			(empty($probs_data[$probid]['color']) ? "" : " (colour: ".$probs_data[$probid]['color'].")" )."\n";
	}

	if ( isset($cdata['lastscoreupdate']) &&
	     time() > strtotime($cdata['lastscoreupdate']) ) {
		$ret .= "\nWARNING: scoreboard is frozen!\n";
	}

	return $ret;
}

$cid = getCurContest();
$cdata = getCurContest(TRUE);

$infreeze = FALSE;

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
		$cdata = getCurContest(TRUE);
	}

	if ( isset($cdata['lastscoreupdate']) && ! $infreeze &&
	     time() > strtotime($cdata['lastscoreupdate']) ) {
		$infreeze = TRUE;
		logmsg(LOG_NOTICE, "Scoreboard is frozen since " . $cdata['lastscoreupdate']);
	}
	
	do {
		$res = $DB->q('SELECT s.*,t.name as teamname,t.room
		               FROM scoreboard_jury s
		               LEFT JOIN team t ON (t.login = s.teamid)
		               WHERE s.cid = %i AND s.is_correct = 1 AND s.balloon = 0',
		               $cid);

		while ( $row = $res->next() ) {
			$team = array ('name'   => $row['teamname'],
			               'room'   => $row['room'],
			               'login'  => $row['teamid']);

			logmsg(LOG_DEBUG,"New problem solved: ".$row['probid'].
				   " by team ".$row['teamid']);

			if ( defined('BALLOON_CMD') && BALLOON_CMD ) {
			
				$probs_solved = $DB->q('COLUMN SELECT probid FROM scoreboard_jury
				                        WHERE cid = %i AND teamid = %s AND is_correct = 1',
				                       $cid, $row['teamid']);
				$probs_data = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY,name,color
				                      FROM problem WHERE cid = %i', $cid);
				
				logmsg(LOG_INFO,"Sending notification: team '".
					   $row['teamid']."', problem '".$row['probid']."'.");
				
				logmsg(LOG_DEBUG,"Running command: '".BALLOON_CMD."'");
				
				$handle = popen(BALLOON_CMD, 'w');
				if ( ! $handle ) error("Could not run command '".BALLOON_CMD."'");
				
				fwrite($handle,notification_text($team,$row['probid'],$probs_solved, $probs_data));
				if ( ($exitcode = pclose($handle))!=0 ) {
					warning("Notification command exited with exitcode $exitcode");
				}
			}
			
			$DB->q('UPDATE scoreboard_jury SET balloon=1
			        WHERE cid = %i AND teamid = %s AND probid = %s',
				   $row['cid'], $row['teamid'], $row['probid']);
		}
		
	} while ( $res->count()!=0 );

	sleep($waittime);
}
