<?php

/**
 * Functions for profiling / timing the DOMjudge code.
 *
 * $Id$
 */

global $DEBUG_NUM_QUERIES;
$DEBUG_NUM_QUERIES = 0;

global $DEBUG_TIMER_START;
$DEBUG_TIMER_START = microtime();

function totaltime() {
	global $DEBUG_NUM_QUERIES,$DEBUG_TIMER_START;

	list($micros1, $secs1) = explode(' ',$DEBUG_TIMER_START);
	list($micros2, $secs2) = explode(' ',microtime());
	$elapsed_ms = round(1000*(($secs2 - $secs1) + ($micros2 - $micros1)));
	
	echo "Execution took: $elapsed_ms ms, queries: $DEBUG_NUM_QUERIES\n";
}
