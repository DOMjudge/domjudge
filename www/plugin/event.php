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

$res = $DB->q('SELECT *	FROM event WHERE '
			. (isset($_REQUEST['fromid']) ? 'eventid >= %i ' : 'TRUE %_ ')
			. 'AND '
			. (isset($_REQUEST['toid']) ? 'eventid < %i ' : 'TRUE %_ ')
			.'ORDER BY eventid', $_REQUEST['fromid'], $_REQUEST['toid']);

$xmldoc = new DOMDocument('1.0', DJ_CHARACTER_SET);

$root   = XMLaddnode($xmldoc, 'root');
$events = XMLaddnode($root, 'events');

while ( $row = $res->next() ) {
	
	$event = XMLaddnode($events, 'event', NULL, array('id' => $row['eventid']));

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

		
		$elem = XMLaddnode($event, 'submission', NULL, array('id' => $row['submitid']));

		XMLaddnode($elem, 'team',     $data['teamname'], array('id' => $row['teamid']));
		XMLaddnode($elem, 'problem',  $data['probname'], array('id' => $row['probid']));
		XMLaddnode($elem, 'language', $data['langname'], array('id' => $row['langid']));
		break;
		
	case 'problem judged':
		$data = $DB->q('TUPLE SELECT s.submittime, j.result FROM judging j
		                LEFT JOIN submission s ON (s.submitid = j.submitid)
		                WHERE j.judgingid = %i', $row['judgingid']);

		if ( !IS_JURY && infreeze($data['submittime']) ) continue(2);

		XMLaddnode($event, 'judging', $data['result'], array('id' => $row['judgingid']));
		break;
			
	case 'clarification':
		$data = $DB->q('TUPLE SELECT * FROM clarification
		                WHERE clarid = %i', $row['clarid']);
		
		XMLaddnode($event, 'clarification', $data['body'], array('id' => $row['clarid']));
		break;
	}
}

if ( !$xmldoc->schemaValidate('events.xsd') ) error('XML file not valid.');

header('Content-Type: text/xml; charset=' . DJ_CHARACTER_SET);

$xmldoc->formatOutput = false;
echo $xmldoc->saveXML();
