<?php
	
/**
 * Check if we have to popup a window to notify the team of a new
 * clarification.
 */

global $popup, $popupTag;

$popup = false;
$popupTag = 'stamp='.time();

// ald die niet geset is, niet gelijk een popup gooien
if ( isset($_REQUEST['stamp']) ) {
	$res = $DB->q('SELECT * FROM clar_response
	               WHERE submittime >= FROM_UNIXTIME(%i) AND cid = %i AND rcpt in (NULL, %s)',
	              $_REQUEST['stamp'], getCurContest(), $login );
	
	if ( $res->count() > 0 ) $popup = true;
}

