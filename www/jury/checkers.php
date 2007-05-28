<?php
/**
 * Functions that will check a given row of a given table
 * for problems, and if necessary, normalise it.
 *
 * $Id$
 */

function check_problem($data)
{
	if ( ! is_numeric($data['timelimit']) || $data['timelimit'] < 0 ||
			(int)$data['timelimit'] != $data['timelimit'] ) {
		error("Timelimit is not a valid positive integer!");
	}
	return $data;
}

function check_language($data)
{
	if ( ! is_numeric($data['time_factor']) || $data['time_factor'] < 0 ) {
		error("Timelimit is not a valid positive factor!");
	}
	if ( strpos($data['extension'], '.') !== FALSE ) {
		error("Do not include the dot (.) in the extension!");
	}
	return $data;
}

function check_contest($data)
{
	// FIXME: checkers for valid date/time formats and valid > > relationships	

	if ( empty($data['lastscoreupdate']) ) unset($data['lastscoreupdate']);
	if ( empty($data['unfreezetime']) ) unset($data['unfreezetime']);
	return $data;
}
