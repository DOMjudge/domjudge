<?php

/* $Id$ */

// helperfunction to read 50,000 bytes from a file
function get_content($filename) {

	if ( ! file_exists($filename) ) return '';
	$fh = fopen($filename,'r');
	if ( ! $fh ) {
		error("Could not open $filename for reading");
	}
	return fread($fh, 50000);
}

