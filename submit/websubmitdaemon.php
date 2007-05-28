#!/usr/bin/php -q
<?php
/**
 * websubmitdaemon -- polling server for websubmit.
 *
 * Polls incoming dir. for websubmitted files and calls submit_db.php
 * to insert these into the database.
 *
 * $Id$
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require ('../etc/config.php');

define ('SCRIPT_ID', 'websubmitdaemon');
define ('LOGFILE', LOGDIR.'/submit.log');

require (SYSTEM_ROOT . '/lib/init.php');

if ( ! ENABLE_WEBSUBMIT_SERVER ) {
	error("Websubmit disabled in config!");
}

$waittime = 1;

$cid = getCurContest();

logmsg(LOG_NOTICE, "Websubmit server started [DOMjudge/".DOMJUDGE_VERSION."]");

// Regular expression that websumbmitted files must match:
// 'websubmit.<problem>.<team>.<IP(dash-separated)>.RANDOM.<ext>'
$file_pregex = '/^websubmit\.([[:alnum:]]+\.){2}([[:alnum:]:-]){7,29}\.' .
               '[[:alnum:]]{6}\.[a-zA-Z0-9+-]+$/';

// Constantly check incoming dir for new websubmissions
while ( TRUE ) {
	$newsubmission = FALSE;

	$contdata = getCurContest(TRUE);
	$newcid = $contdata['cid'];
	$oldcid = $cid;
	if ( $oldcid !== $newcid ) {
		logmsg(LOG_NOTICE, "Contest has changed from " .
		       (isset($oldcid) ? "c$oldcid" : "none" ) . " to " .
		       (isset($newcid) ? "c$newcid" : "none" ) );
		$cid = $newcid;
	}

	$dir = opendir(INCOMINGDIR);
	if ( $dir === FALSE ) {
		system(BEEP_CMD." ".BEEP_ERROR." &");
		error("Cannot read '" . INCOMINGDIR . "'");
	}

	while ( ($file = readdir($dir)) !== FALSE ) {

		$filefull = INCOMINGDIR . '/' . $file;
		
		if ( ! is_file($filefull) ) continue;

		if ( ! preg_match($file_pregex,$file) ) continue;

		$newsubmission = TRUE;
		
		$data = explode(".",$file);

		$problem = $data[1];
		$team    = $data[2];
		$ip      = str_replace("-",".",$data[3]);
		$langext = $data[5];

		logmsg(LOG_NOTICE,"found file '$file'");
		
		system("./submit_db.php $team $ip $problem $langext $file", $retval);

		if ( $retval!=0 ) {
			logmsg(LOG_WARNING,"error: submit_db returned exitcode $retval");
			system(BEEP_CMD." ".BEEP_WARNING." &");

			if ( ! rename($filefull,INCOMINGDIR . "/rejected-" . $file) ) {
				system(BEEP_CMD." ".BEEP_ERROR." &");
				error("could not rename '$file'");
			}
		} else {
			logmsg(LOG_INFO,"added submission to database");
			system(BEEP_CMD." ".BEEP_SUBMIT." &");
			
			if ( ! unlink($filefull) ) {
				system(BEEP_CMD." ".BEEP_ERROR." &");
				error("could not delete '$file'");
			}
		}
	}

	closedir($dir);

	if ( $newsubmission == FALSE ) sleep($waittime);
	
	// restart the loop
}

exit;
