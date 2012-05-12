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
$problemmap  = array('1' => 'fltcmp', '2' => 'boolfind', '3' => 'hello',
	'4' => 'fltcmp', '5' => 'boolfind', '6' => 'hello');
// FIXME: why is the teammap necessary? why don't the IDs in the feed match?
$teammap     = array(
    '2139' => '804', 
    '2175' => '805',
    '2282' => '806',
    '2432' => '807',
    '2671' => '808',
    '2711' => '809',
    '2757' => '810',
    '2758' => '811',
    '2929' => '812',
    '3033' => '813',
    '3082' => '814',
    '3100' => '815',
    '3248' => '816'
    );
$resultmap = array(
	'CE' => 'compiler-error',
	'RTE' => 'run-error',
	'TLE' => 'timelimit',
	'WA' => 'wrong-answer',
	'AC' => 'correct',
	'SV' => 'security violation',
	'JE' => 'judging error',
	'DEL' => 'deleted'
);

$knownRuns = array();
$submittimes = array();

while (1) {
	$feedXML = file_get_contents($feedURL);
	$feedDOM = DOMDocument::loadXML($feedXML);
	$runs = $feedDOM->getElementsByTagName('run');

	foreach ( $runs as $run ) {
		if ( val($run, 'judged') !== 'True' ) {
			$submittimes[val($run, 'id')] = val($run, 'timestamp');
			continue;
		}

		$id = val($run, 'id');
		if ( $knownRuns[$id] >= val($run, 'timestamp') ) {
			continue;
		}
		$knownRuns[$id] = val($run, 'timestamp');
		print "$id\n";

		// wait for submission
		$zipfile = $directory . '/' . $id . '.zip';
		$ziptmp = $directory . '/ziptmp';
		while (1) {
			sleep(1);
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

		system("./import.php "
			. $teammap[val($run, 'team')] . " "
			. $problemmap[val($run, 'problem')] . " "
			. $languagemap[val($run, 'language')] . " "
			. $submittimes[$id] . " $id "
			. $resultmap[val($run, 'result')] . $files
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

	sleep($sleeptime);
}


function val($node, $tag) {
	return $node->getElementsByTagName($tag)->item(0)->nodeValue;
}

function stderr($msg) {
	file_put_contents('php://stderr', $msg);
}

?>
