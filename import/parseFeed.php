#!/usr/bin/env php
<?php
/**
 * Parses a CCS event feed, writes out the run IDs which should be exported 
 * from primary CCS and imported into DOMjudge as secondary/shadow CCS
 *
 * Called: ./parseFeed.php | ./export.pl
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

# config section
$feedURL = 'testfeed.xml';
$sleeptime = 1;
$directory = 'submissions';
$languagemap = array('Java' => 'java', 'C' => 'c', 'C++' => 'cpp');
$problemmap  = array(
'1' =>  'A',
'2' =>  'B',
'3' =>  'C',
'4' =>  'D',
'5' =>  'E',
'6' =>  'F',
'7' =>  'G',
'8' =>  'H',
'9' =>  'I',
'10' => 'J',
'11' => 'K',
'12' => 'L'
 );
$resultmap = array(
	'CE' => 'compiler-error',
	'RTE' => 'run-error',
	'TLE' => 'timelimit',
	'WA' => 'wrong-answer',
	'AC' => 'correct',
	'SV' => 'security violation',
	'JE' => 'judging error',
	'DEL' => 'deleted',
	'IF' => 'invalid function'
);

$knownRuns = array();
$submittimes = array();

$argv = $_SERVER['argv'];
$skipruns = @$argv[1];


while (1) {
	sleep($sleeptime);

	if (!(is_readable("testfeed.xml"))) {
		stderr(".\n");
		continue;
	}
	`mv $feedURL $feedURL.mine.xml`;
	$feedXML = file_get_contents($feedURL . '.mine.xml');
	$feedDOM = DOMDocument::loadXML($feedXML);
	if ( $feedDOM == NULL ) {
		continue;
	}
	$runs = $feedDOM->getElementsByTagName('run');

	foreach ( $runs as $run ) {
		if ( val($run, 'judged') !== 'True' ) {
			$submittimes[val($run, 'id')] = val($run, 'timestamp');
			continue;
		}
		if ( isset($skipruns) && val($run, 'id') < $skipruns ) {
			continue;
		}

		$id = val($run, 'id');
		if ( isset($knownRuns[$id]) && $knownRuns[$id] >= val($run, 'timestamp') ) {
			continue;
		}
		$knownRuns[$id] = val($run, 'timestamp');
		print "$id\n";

		// wait for submission
		$zipfile = $directory . '/' . $id . '.zip';
		$ziptmp = $directory . '/ziptmp';
		while (1) {
			`sleep 0.05s`;
			if (file_exists($zipfile)) {
				break;
			}
		}

		mkdir($ziptmp);
		system("unzip -j $zipfile -d $ziptmp > /dev/null");
		$handle = opendir($ziptmp);

		$files = "";
		while (false !== ($entry = readdir($handle))) {
			if ($entry != "." && $entry != "..") {
				$files .= " '" . $ziptmp . "/" . $entry . "'";
				$files .= " '" . $entry . "'";
			}
		}
		closedir($handle);

		stderr("./import.php "
			. val($run, 'team') . " "
			. $problemmap[val($run, 'problem')] . " "
			. $languagemap[val($run, 'language')] . " "
			. $submittimes[$id] . " $id "
			. "'" . $resultmap[val($run, 'result')] . "'"
			. $files
			. " 1>2\n");
		system("./import.php "
			. val($run, 'team') . " "
			. $problemmap[val($run, 'problem')] . " "
			. $languagemap[val($run, 'language')] . " "
			. $submittimes[$id] . " $id "
			. "'" . $resultmap[val($run, 'result')] . "'"
			. $files
			. " 1>2");

		$handle = opendir($ziptmp);
		while ( false !== ($entry = readdir($handle)) ) {
			if ( $entry != "." && $entry != ".." ) {
				unlink($ziptmp . "/" . $entry);
			}
		}
		closedir($handle);
		rmdir($ziptmp);
	}

}


function val($node, $tag) {
	return $node->getElementsByTagName($tag)->item(0)->nodeValue;
}

function stderr($msg) {
	file_put_contents('php://stderr', $msg);
}

?>
