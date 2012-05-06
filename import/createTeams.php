<?php

# config section
$feedURL = 'testfeed.xml';

$feedXML = file_get_contents($feedURL);
$feedDOM = DOMDocument::loadXML($feedXML);
$teams = $feedDOM->getElementsByTagName('team');

foreach ($teams as $team) {
	if (hasChild($team)) {
		echo "INSERT INTO team (login, name) VALUES ('" . val($team, 'id') . "', '" . val($team, 'name') . "');\n";
	}
}

function val($node, $tag) {
	return $node->getElementsByTagName($tag)->item(0)->nodeValue;
}

function hasChild($p) {
	if ($p->hasChildNodes()) {
		foreach ($p->childNodes as $c) {
			if ($c->nodeType == XML_ELEMENT_NODE) {
				return true;
			}
		}
	}
	return false;
}

?>
