<?php
/**
 * Functions for profiling / timing the DOMjudge code.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

global $DEBUG_NUM_QUERIES;
$DEBUG_NUM_QUERIES = 0;

global $DEBUG_TIMER_START;
$DEBUG_TIMER_START = microtime();

/**
 * Displays the total time in milliseconds it took to execute the code
 * and the number of SQL queries done. The time is measured starting at
 * the moment lib.timer.php was included.
 */
function totaltime() {
	global $DEBUG_NUM_QUERIES,$DEBUG_TIMER_START;

	list($micros1, $secs1) = explode(' ',$DEBUG_TIMER_START);
	list($micros2, $secs2) = explode(' ',microtime());
	$elapsed_ms = round(1000*(($secs2 - $secs1) + ($micros2 - $micros1)));

	echo "Execution took: $elapsed_ms ms" .
		(DEBUG & DEBUG_SQL ? ", queries: $DEBUG_NUM_QUERIES\n" : "\n");
}
