<?php
/**
 * Output events in XML format.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

if ( isset($_REQUEST['fromid']) ) {
	$fromid = (int) $_REQUEST['fromid'];
} else {
	$fromid = -1;
}

if ( isset($_REQUEST['toid']) ) {
	$toid = (int) $_REQUEST['toid'];
} else {
	$toid = 2147483647;
}

$now = now();

$cstarted = difftime($now, $cdata['starttime'])>0;
$cended   = difftime($now, $cdata['endtime'])  >0;

function infreeze($time) {
	if ( ( ! empty($cdata['freezetime']) &&
		   difftime($time, $cdata['freezetime'])>0 ) &&
		!( ! empty($cdata['unfreezetime']) &&
		   difftime($time, $cdata['unfreezetime'])<=0 ) ) return TRUE;
	return FALSE;
}

$res = $DB->q('SELECT * FROM event WHERE eventid >= %i AND eventid < %i
               ORDER BY eventid', $fromid, $toid);

$xml = new DOMDocument('1.0', DJ_CHARACTER_SET);

$root = $xml->createElement('root');
$xml->appendChild($root);

$events = $xml->createElement('events');
$root->appendChild($events);

while ( $row = $res->next() ) {
	
	$event = $xml->createElement('event');
	$event->setAttribute('id', $row['eventid']);

	switch ( $row['description'] ) {

	case 'problem submitted':
		if ( !IS_JURY && infreeze($row['eventtime']) ) continue(2);
		
		$data = $DB->q('TUPLE SELECT s.submittime, t.name AS teamname,
		                             p.name AS probname, l.name AS langname
		                FROM submission s
		                LEFT JOIN team     t ON (t.login    = s.teamid)
		                LEFT JOIN problem  p ON (p.probid   = s.probid)
		                LEFT JOIN language l ON (l.langid   = s.langid)
		                WHERE s.submitid = %i', $row['submitid']);

		$elem = $xml->createElement('submission');
		$elem->setAttribute('id', $row['submitid']);

		$subelem = $xml->createElement('team', htmlspecialchars($data['teamname']));
		$subelem->setAttribute('id', $row['teamid']);
		$elem->appendChild($subelem);
		
		$subelem = $xml->createElement('problem', htmlspecialchars($data['probname']));
		$subelem->setAttribute('id', $row['probid']);
		$elem->appendChild($subelem);
		
		$subelem = $xml->createElement('language', htmlspecialchars($data['langname']));
		$subelem->setAttribute('id', $row['langid']);
		$elem->appendChild($subelem);
		break;
		
	case 'problem judged':
		$data = $DB->q('TUPLE SELECT s.submittime, j.result FROM judging j
		                LEFT JOIN submission s ON (s.submitid = j.submitid)
		                WHERE j.judgingid = %i', $row['judgingid']);

		if ( !IS_JURY && infreeze($data['submittime']) ) continue(2);


		$elem = $xml->createElement('judging', htmlspecialchars($data['result']));
		$elem->setAttribute('id', $row['judgingid']);
		
		break;
			
	case 'clarification':
		$data = $DB->q('TUPLE SELECT * FROM clarification
		                WHERE clarid = %i', $row['clarid']);
		
		$elem = $xml->createElement('clarification', htmlspecialchars($data['body']));
		$elem->setAttribute('id', $row['clarid']);

		break;
	}
	
	$event->appendChild($elem);
	
	$events->appendChild($event);
}

header('Content-Type: text/xml; charset=' . DJ_CHARACTER_SET);

$xml->formatOutput = true;
echo $xml->saveXML();
