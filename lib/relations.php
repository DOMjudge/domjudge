<?php
/**
 * Document relations between DOMjudge tables for various use.
 */

/** For each table specify the set of attributes that together
 *  are considered the primary key / superkey. */
$KEYS = array();
$KEYS['auditlog'] = array('logid');
$KEYS['balloon'] = array('balloonid');
$KEYS['clarification'] = array('clarid');
$KEYS['contest'] = array('cid');
$KEYS['event'] = array('eventid');
$KEYS['judgehost'] = array('hostname');
$KEYS['judging'] = array('judgingid');
$KEYS['judging_run'] = array('runid');
$KEYS['language'] = array('langid');
$KEYS['problem'] = array('probid');
$KEYS['scoreboard_jury'] = array('cid','teamid','probid');
$KEYS['scoreboard_public'] = array('cid','teamid','probid');
$KEYS['submission'] = array('submitid');
$KEYS['submission_file'] = array('submitfileid');
$KEYS['team'] = array('login');
$KEYS['team_affiliation'] = array('affilid');
$KEYS['team_category'] = array('categoryid');
$KEYS['team_unread'] = array('teamid','mesgid','type');
$KEYS['testcase'] = array('testcaseid');


/** For each table, list all attributes that reference foreign keys
 *  and specify the source of that key. Optionally appended to the
 *  foreign key is '&<ACTION>' where ACTION can be any of the
 *  following referential actions on delete of the foreign row:
 *  CASCADE:  also delete the source row (default if not specified)
 *  SETNULL:  set source key to NULL
 *  RESTRICT: disallow delete of foreign row
 *  NOCONSTRAINT: no constraint is specified, even though the field
 *                references a foreign key.
 */
$RELATIONS = array();

$RELATIONS['auditlog'] = array();

$RELATIONS['clarification'] = array (
	'submitid' => 'submission.submitid',
);

$RELATIONS['clarification'] = array (
	'cid' => 'contest.cid',
	'respid' => 'clarification.clarid&SETNULL',
	'sender' => 'team.login&NOCONSTRAINT',
	'recipient' => 'team.login&NOCONSTRAINT',
	'probid' => 'problem.probid&SETNULL',
);

$RELATIONS['contest'] = array();

$RELATIONS['event'] = array (
	'cid' => 'contest.cid&NOCONSTRAINT',
	'clarid' => 'clarification.clarid&NOCONSTRAINT',
	'langid' => 'language.langid&NOCONSTRAINT',
	'probid' => 'problem.probid&NOCONSTRAINT',
	'submitid' => 'submission.submitid&NOCONSTRAINT',
	'judgingid' => 'judging.judgingid&NOCONSTRAINT',
	'teamid' => 'team.login&NOCONSTRAINT',
);

$RELATIONS['judgehost'] = array();

$RELATIONS['judging'] = array (
	'cid' => 'contest.cid',
	'submitid' => 'submission.submitid',
	'judgehost' => 'judgehost.hostname&SETNULL',
);

$RELATIONS['judging_run'] = array (
	'judgingid' => 'judging.judgingid',
	'testcaseid' => 'testcase.testcaseid&RESTRICT',
);

$RELATIONS['language'] = array();

$RELATIONS['problem'] = array (
	'cid' => 'contest.cid',
);

$RELATIONS['scoreboard_jury'] =
$RELATIONS['scoreboard_public'] = array (
	'cid' => 'contest.cid&NOCONSTRAINT',
	'teamid' => 'team.login&NOCONSTRAINT',
	'probid' => 'problem.probid&NOCONSTRAINT',
);

$RELATIONS['submission'] = array (
	'origsubmitid' => 'submission.submitid&SETNULL',
	'cid' => 'contest.cid',
	'teamid' => 'team.login',
	'probid' => 'problem.probid',
	'langid' => 'language.langid',
	'judgehost' => 'judgehost.hostname&SETNULL',
);

$RELATIONS['submission_file'] = array (
	'submitid' => 'submission.submitid',
);

$RELATIONS['team'] = array (
	'categoryid' => 'team_category.categoryid',
	'affilid' => 'team_affiliation.affilid&SETNULL',
);

$RELATIONS['team_affiliation'] = array();

$RELATIONS['team_category'] = array();

$RELATIONS['team_unread'] = array(
	'teamid' => 'team.login',
	'mesgid' => 'clarification.clarid',
);

$RELATIONS['testcase'] = array(
	'probid' => 'problem.probid',
);

/**
 * Check whether some primary key is referenced in any
 * table as a foreign key.
 *
 * Returns null or an array "table name => action" where matches are found.
 */
function fk_check ($keyfield, $value) {
	global $RELATIONS, $DB;

	$ret = array();
	foreach ( $RELATIONS as $table => $keys ) {
		foreach ( $keys as $key => $val ) {
			@list( $foreign, $action ) = explode('&', $val);
			if ( empty($action) ) $action = 'CASCADE';
			if ( $foreign == $keyfield ) {
				$c = $DB->q("VALUE SELECT count(*) FROM $table WHERE $key = %s",
					$value);
				if ( $c > 0 ) $ret[$table] = $action;
			}
		}
	}

	if ( count($ret) ) return $ret;
	return null;
}
