<?php
/**
 * Check if we have to popup a window to notify the team of a new
 * clarification.
 *
 * $Id$
 */

global $popup, $popupTag;

$popup = '';
$popupTag = '';

if(!isset($_REQUEST['data'])) {
	$data = null;
} else {
	$data = $_REQUEST['data'];
}

// do not throw a popup if stamp is not set
$res = $DB->q('KEYTABLE SELECT type AS ARRAYKEY, COUNT(*) AS count FROM team_unread
               WHERE team = %s GROUP BY type', $login);

if(!empty($res))
{
	foreach($res as $k => $v)
	{
		if(!empty($popupTag)) {
			$popupTag .= "&";
		}
		$popupTag .= "data[$k]=".$v['count'];
		if((isset($data[$k]) && $data[$k] < $v['count']) || !isset($data[$k])) {
			if(!empty($popup)) {
				$popup .= "&";
			}
			$popup .= 'new[]='.$k;
		}
	}
}
