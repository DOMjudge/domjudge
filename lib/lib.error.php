<?php

/** error handling

  $Id$
**/



function error($string) {
	print "$string\n";
	exit(1);
}

function log_mysql_error($string) {
	print $string;
	exit(1);
}
