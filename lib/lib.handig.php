<?php

function printtime($datetime) {
	$date_time = explode(' ',$datetime);
	return $date_time[1];

}

function logmsg($string) {
	echo '[' . date('r') . '] ' . $string . "\n";
}
