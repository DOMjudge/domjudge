<?php

/**
 * Misc. functions
 *
 * $Id$
 */

function printtime($datetime) {
	if(!$datetime) return '';
	$date_time = explode(' ',$datetime);
	return $date_time[1];

}
