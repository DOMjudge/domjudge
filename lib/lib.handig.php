<?php

function printtime($datetime) {
	$date_time = explode(' ',$datetime);
	return $date_time[1];

}

function logmsg($string) {
	echo '[' . date('M d H:i:s') . '] ' . $string . "\n";
}
