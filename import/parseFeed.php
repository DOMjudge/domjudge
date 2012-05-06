<?php

# config section
$feedURL = 'testfeed.xml';
$sleeptime = 1;

$knownRuns = array();

while (1) {
	$feedXML = file_get_contents($feedURL);
	$feedDOM = DOMDocument::loadXML($feedXML);
	$runs = $feedDOM->getElementsByTagName('run');

	foreach ($runs as $run) {
		if (val($run, 'judged') !== 'True') {
			continue;
		}

		$id = val($run, 'id');
		if (in_array($id, $knownRuns)) {
			continue;
		}
		$knownRuns[] = $id;
		print "$id\n";
	}

	sleep($sleeptime);
}


function val($node, $tag) {
	return $node->getElementsByTagName($tag)->item(0)->nodeValue;
}

?>
