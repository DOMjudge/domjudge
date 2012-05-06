<?php

# config section
$feedURL = 'testfeed.xml';
$sleeptime = 1;
$directory = 'submissions';

$knownRuns = array();
$submittimes = array();

while (1) {
	$feedXML = file_get_contents($feedURL);
	$feedDOM = DOMDocument::loadXML($feedXML);
	$runs = $feedDOM->getElementsByTagName('run');

	foreach ($runs as $run) {
		if (val($run, 'judged') !== 'True') {
			$submittimes[val($run, 'id')] = val($run, 'timestamp');
			continue;
		}

		$id = val($run, 'id');
		if (in_array($id, $knownRuns)) {
			continue;
		}
		$knownRuns[] = $id;
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

		system("./import.php coolteam fltcmp c " . $submittimes[$id] . " $id " . val($run, 'result') . $files . " 1>2");

		$handle = opendir($ziptmp);
		while (false !== ($entry = readdir($handle))) {
			if ($entry != "." && $entry != "..") {
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

?>
