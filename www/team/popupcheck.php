<?php
/**
 * Check if we have to popup a window to notify the team of a new
 * clarification.
 *
 * $Id$
 */

global $popup, $popupTag;

$popup = false;
$popupTag = 'stamp='.time();

// do not throw a popup if stamp is not set
if ( isset($_REQUEST['stamp']) ) {
	$res = $DB->q('SELECT * FROM clarification
		WHERE submittime >= FROM_UNIXTIME(%i)
		AND cid = %i AND ( recipient IS NULL OR recipient = %s )',
		$_REQUEST['stamp'], getCurContest(), $login);
	
	if ( $res->count() > 0 ) $popup = true;
}

