<?php

/**
 * Error handling functions
 *
 * $Id$
 */

define('STDERR', fopen('php://stderr', 'w'));

function error($string) {
	fwrite(STDERR, SCRIPT_ID.": $string\n");
	exit(1);
}

