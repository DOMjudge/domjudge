<?php

/**
 * Error handling functions
 *
 * $Id$
 */

function error($string) {
	print SCRIPT_ID.": $string\n";
	exit(1);
}

