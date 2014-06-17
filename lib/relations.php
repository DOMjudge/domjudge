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
$KEYS['configuration'] = array('configid');
$KEYS['contest'] = array('cid');
$KEYS['event'] = array('eventid');
$KEYS['executable'] = array('execid');
$KEYS['judgehost'] = array('hostname');
$KEYS['judging'] = array('judgingid');
$KEYS['judging_run'] = array('runid');
$KEYS['language'] = array('langid');
$KEYS['problem'] = array('probid');
$KEYS['rankcache_jury'] = array('cid','teamid');
$KEYS['rankcache_public'] = array('cid','teamid');
$KEYS['role'] = array('roleid');
$KEYS['scorecache_jury'] = array('cid','teamid','probid');
$KEYS['scorecache_public'] = array('cid','teamid','probid');
$KEYS['submission'] = array('submitid');
$KEYS['submission_file'] = array('submitfileid');
$KEYS['team'] = array('teamid');
$KEYS['team_affiliation'] = array('affilid');
$KEYS['team_category'] = array('categoryid');
$KEYS['team_unread'] = array('teamid','mesgid');
$KEYS['testcase'] = array('testcaseid');
$KEYS['user'] = array('userid');
$KEYS['userrole'] = array('userid', 'roleid');

/** For each table, list all attributes that reference foreign keys
 *  and specify the source of that key. Appended to the
 *  foreign key is '&<ACTION>' where ACTION can be any of the
 *  following referential actions on delete of the foreign row:
 *  CASCADE:  also delete the source row
 *  SETNULL:  set source key to NULL
 *  RESTRICT: disallow delete of foreign row
 *  NOCONSTRAINT: no constraint is specified, even though the field
 *                references a foreign key.
 */
$RELATIONS = array();

$RELATIONS['auditlog'] = array();

$RELATIONS['balloon'] = array (
	'submitid' => 'submission.submitid&CASCADE',
);

$RELATIONS['clarification'] = array (
	'cid' => 'contest.cid&CASCADE',
	'respid' => 'clarification.clarid&SETNULL',
	'sender' => 'team.teamid&NOCONSTRAINT',
	'recipient' => 'team.teamid&NOCONSTRAINT',
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
	'teamid' => 'team.teamid&NOCONSTRAINT',
);

$RELATIONS['executable'] = array();

$RELATIONS['judgehost'] = array();

$RELATIONS['judging'] = array (
	'cid' => 'contest.cid',
	'submitid' => 'submission.submitid&CASCADE',
	'judgehost' => 'judgehost.hostname&SETNULL',
);

$RELATIONS['judging_run'] = array (
	'testcaseid' => 'testcase.testcaseid&RESTRICT',
	'judgingid' => 'judging.judgingid&CASCADE',
);

$RELATIONS['language'] = array();

$RELATIONS['problem'] = array (
	'cid' => 'contest.cid&CASCADE',
);

$RELATIONS['rankcache_jury'] =
$RELATIONS['rankcache_public'] = array (
	'cid' => 'contest.cid&CASCADE',
	'teamid' => 'team.teamid&NOCONSTRAINT'
);

$RELATIONS['role'] = array();

$RELATIONS['scorecache_jury'] =
$RELATIONS['scorecache_public'] = array (
	'cid' => 'contest.cid&NOCONSTRAINT',
	'teamid' => 'team.teamid&NOCONSTRAINT',
	'probid' => 'problem.probid&NOCONSTRAINT',
);

$RELATIONS['submission'] = array (
	'cid' => 'contest.cid&CASCADE',
	'teamid' => 'team.teamid&CASCADE',
	'probid' => 'problem.probid&CASCADE',
	'langid' => 'language.langid&CASCADE',
	'judgehost' => 'judgehost.hostname&SETNULL',
	'origsubmitid' => 'submission.submitid&SETNULL',
);

$RELATIONS['submission_file'] = array (
	'submitid' => 'submission.submitid&CASCADE',
);

$RELATIONS['team'] = array (
	'categoryid' => 'team_category.categoryid&CASCADE',
	'affilid' => 'team_affiliation.affilid&SETNULL',
);

$RELATIONS['team_affiliation'] = array();

$RELATIONS['team_category'] = array();

$RELATIONS['team_unread'] = array(
	'teamid' => 'team.teamid&CASCADE',
	'mesgid' => 'clarification.clarid&CASCADE',
);

$RELATIONS['testcase'] = array(
	'probid' => 'problem.probid&CASCADE',
);

$RELATIONS['user'] = array(
	'teamid' => 'team.teamid&SETNULL',
);
$RELATIONS['userrole'] = array(
	'userid' => 'user.userid&CASCADE',
	'roleid' => 'role.roleid&CASCADE',
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
