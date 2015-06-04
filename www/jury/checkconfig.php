<?php
/**
 * Do various sanity checks on the system regarding data constraints,
 * permissions and the like. At the moment this only contains some basic
 * checks but this can be extended in the future.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Config Checker';
require(LIBWWWDIR . '/header.php');

requireAdmin();

$time_start = microtime(TRUE);

?>

<h1>Config Checker</h1>

<?php

/** Print the output of phpinfo(), which may be useful to check which settings
 *  PHP is actually using. */
if ( $_SERVER['QUERY_STRING'] == 'phpinfo' ) {
	$ret = "<p><a href=\"./checkconfig.php\">return to config checker</a></p>\n\n";
	echo $ret;
	echo "<h2>PHP Information</h2>\n\n";
	phpinfo();
	echo $ret;
	require(LIBWWWDIR . '/footer.php');
	exit;
}

if ( !file_exists(LIBDIR . '/relations.php') ) {
	error("'".LIBDIR . "/relations.php' is missing, regenerate with 'make dist'.");
}

require_once(LIBDIR . '/relations.php');
require_once(LIBWWWDIR . '/checkers.jury.php');


$RESULTS = array();

function result($section, $item, $result, $details, $details_html = '') {
	global $RESULTS;

	$RESULTS[] = array(
		'section' => $section,
		'item' => $item,
		'result' => $result,
		'details' => $details,
		'details_html' => $details_html,
		'flushed' => false);
}

$lastsection = false; $resultno = 0;

function flushresults() {
	global $RESULTS, $lastsection, $resultno;

	foreach($RESULTS as &$row) {

		if ( $row['flushed'] ) continue;
		$row['flushed'] = TRUE;

		if ( empty($row['details']) && empty($row['details_html']) ) {
			$row['details'] = 'No issues found.';
		}

		if ( $row['section'] != $lastsection ) {
			echo "<tr><th colspan=\"2\">" .
			    htmlspecialchars(ucfirst($row['section'])) .
			    "</th></tr>\n";
			$lastsection = $row['section'];
		}

		echo "<tr class=\"result " . htmlspecialchars($row['result']) .
		    "\"><td class=\"resulticon\"><img src=\"../images/s_";
		switch($row['result']) {
		case 'O': echo "okay"; break;
		case 'W': echo "warn"; break;
		case 'E': echo "error"; break;
		case 'R': echo "refint"; break;
		default: error("Unknown config checker result: ".$row['result']);
		}
		echo ".png\" alt=\"" . $row['result'] . "\" class=\"picto\" /></td><td>" .
		    htmlspecialchars($row['item']) ." " .
		    "<a href=\"javascript:collapse($resultno)\"><img src=\"../images/b_help.png\" " .
		    "alt=\"?\" title=\"show details\" class=\"smallpicto helpicon\" /></a>\n" .
		    "<div class=\"details\" id=\"detail$resultno\">" .
		    nl2br(htmlspecialchars(trim($row['details']))."\n") . $row['details_html'] .
		    "</div></td></tr>\n";

		++$resultno;
	}

	flush();
}

echo "<table class=\"configcheck\">\n";

// SOFTWARE

if( !function_exists('version_compare') || version_compare( '5.3.3',PHP_VERSION,'>=') ) {
	result('software', 'PHP version', 'E',
		'You have PHP ' . PHP_VERSION . ', but need at least 5.3.3.',
		'See <a href="?phpinfo">phpinfo</a> for details.');
} else {
	result('software', 'PHP version', 'O',
		'You have PHP ' . PHP_VERSION . '.',
		'See <a href="?phpinfo">phpinfo</a> for details.');
}

if ( (bool) ini_get('register_globals') &&
     strtolower(ini_get('register_globals'))!='off' ) {
	result('software', 'PHP register_globals', 'W',
	       'PHP register_globals is on. This obsolete feature should be disabled');
} else {
	result('software', 'PHP register_globals', 'O', 'PHP register_globals off');
}

if ( function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()==1 ) {
	result('software', 'PHP magic quotes', 'E',
	       'PHP magic quotes enabled. This will result in overquoted ' .
	       'entries in the database.');
} else {
	result('software', 'PHP magic quotes', 'O', 'PHP magic quotes disabled.');
}

if ( !function_exists('gd_info') ) {
	result('software', 'PHP GD library', 'W',
	       'The PHP GD library is not available. Test case images cannot be uploaded.');
} else {
	result('software', 'PHP GD library', 'O',
	       'The PHP GD library is available to handle test case images.');
}

if ( extension_loaded('suhosin') ) {
	result('software', 'suhosin', 'E',
	       'PHP suhosin extension loaded. This may result in dropping POST arguments, e.g. output_run.');
} else {
	result('software', 'suhosin', 'O', 'PHP suhosin extension disabled.');
}

$max_file_check = max(100,dbconfig_get('sourcefiles_limit', 100));
result('software', 'PHP max_file_uploads',
       (int) ini_get('max_file_uploads') < $max_file_check ? 'W':'O',
       'PHP max_file_uploads is set to ' .
       (int) ini_get('max_file_uploads') . '. This should be set higher ' .
       'than the maximum number of test cases per problem and the ' .
       'configuration setting \'sourcefiles_limit\'.');


$sizes = array();
$postmaxvars = array('post_max_size', 'memory_limit', 'upload_max_filesize');
foreach($postmaxvars as $var) {
	/* skip 0 or empty values, and -1 which means 'unlimited' */
	if( $size = phpini_to_bytes(ini_get($var)) ) {
		if ( $size != '-1' ) {
			$sizes[$var] = $size;
		}
	}
}

$resulttext = 'PHP POST/upload filesize is limited to ' . printsize(min($sizes)) .
	"\n\nThis limit needs to be larger than the testcases you want to upload and than the amount of program output you expect the judgedaemons to post back to DOMjudge. We recommend at least 50 MB.\n\nNote that you need to ensure that all of the following php.ini parameters are at minimum the desired size:\n";
foreach($postmaxvars as $var) {
	$resulttext .= "$var (now set to " .
		(isset($sizes[$var]) ? printsize($sizes[$var]) : "unlimited") .
		")\n";
}

result('software', 'PHP POST/upload filesize',
       min($sizes) < 52428800 ? 'W':'O', '', $resulttext);

if ( class_exists("ZipArchive") ) {
	result('software', 'Problem up/download via zip bundles',
	       'O', 'PHP ZipArchive class available for importing and exporting problem data.');
} else {
	result('software', 'Problem up/download via zip bundles',
	       'W', 'Optionally, enable the PHP zip extension ' .
	       'to be able to import or export problem data via zip bundles.');
}

$mysqldata = array();
$mysqldatares = $DB->q('SHOW variables WHERE
                        Variable_name = "max_connections" OR
                        Variable_name = "max_allowed_packet" OR
                        Variable_name = "version"');
while($row = $mysqldatares->next()) {
	$mysqldata[$row['Variable_name']] = $row['Value'];
}

result('software', 'MySQL version',
	version_compare('4.1', $mysqldata['version'], '>=') ? 'E':'O',
	'Connected to MySQL server version ' . $mysqldata['version'] .
	'. Minimum required is 4.1.');

result('software', 'MySQL maximum connections',
	$mysqldata['max_connections'] < 300 ? 'W':'O',
	'MySQL\'s max_connections is set to ' .
	(int)$mysqldata['max_connections'] . '. In our experience ' .
	'you need at least 300, but better 1000 connections to ' .
	'prevent connection refusal during the contest.');

result('software', 'MySQL maximum packet size',
	$mysqldata['max_allowed_packet'] < 16*1024*1024 ? 'W':'O', '',
	'MySQL\'s max_allowed_packet is set to ' .
	printsize($mysqldata['max_allowed_packet']) . '. You may ' .
	'want to raise this to about twice the maximum test case size.');

flushresults();

// CONFIGURATION

if ( $DB->q('VALUE SELECT count(*) FROM user
             WHERE username = "admin" AND password=MD5("admin#admin")') != 0 ) {
	result('configuration', 'Default admin password', 'E',
	       'The "admin" user still has the default password. ' .
	       'You should change it immediately.');
} else {
	result('configuration', 'Default admin password', 'O',
	       'Password for "admin" has been changed from the default.');
}

foreach (array('compare', 'run') as $type) {
	if ( $DB->q('VALUE SELECT count(*) FROM executable WHERE execid = %s',
	            dbconfig_get('default_' . $type)) == 0 ) {
		result('configuration', 'Default ' . $type .' script', 'E',
		       'The default ' . $type . ' script "' .
		       dbconfig_get('default_' . $type) . '" does not exist.');
	}
}


if ( DEBUG == 0 ) {
	result('configuration', 'Debugging', 'O', 'Debugging disabled.');
} else {
	result('configuration', 'Debugging', 'W',
	       'Debug information enabled (level ' . DEBUG .").\n" .
	       'Should not be enabled on live systems.');
}

if ( !is_writable(TMPDIR) ) {
       result('configuration', 'TMPDIR writable', 'W',
              'TMPDIR (' . TMPDIR . ') is not writable by the webserver; ' .
              'Showing diffs and editing of submissions may not work.');
} else {
       result('configuration', 'TMPDIR writable', 'O',
              'TMPDIR (' . TMPDIR . ') can be used to store temporary ' .
	          'files for submission diffs and edits.');
}

flushresults();

// CONTESTS

if( empty($cids) ) {
	result('contests', 'Active contests', 'E',
	       'No currently active contests found. System will not function.');
} else {
	$cidstring = implode(', ', array_map(function($cid) { return 'c'.$cid;}, $cids));
	result('contests', 'Active contests', 'O',
	       'Currently active contests: ' . $cidstring);
}

// get all contests
$res = $DB->q('SELECT * FROM contest ORDER BY cid');

$detail = '';
$has_errors = FALSE;
while($cdata = $res->next()) {
	$cp = $DB->q('SELECT * FROM contestproblem WHERE cid = %i', $cdata['cid']);

	$detail .=  "c".(int)$cdata['cid'].": ";

	$CHECKER_ERRORS = array();
	check_contest($cdata, array('cid' => $cdata['cid']));
	while( $cpdata = $cp->next() ) {
		check_contestproblem($cpdata, array('cid' => $cpdata['cid'], 'probid' => $cpdata['probid']));
	}
	if ( count ( $CHECKER_ERRORS ) > 0 ) {
		foreach($CHECKER_ERRORS as $chk_err) {
			$detail .= $chk_err . "\n";
			$has_errors = TRUE;
		}
	} else {
		$detail .= "OK";
	}

	$detail .= "\n";
}

result('contests', 'Contests integrity',
	$has_errors ? 'E' : 'O',
	$detail);

flushresults();

// PROBLEMS

$res = $DB->q('SELECT probid, cid, shortname, timelimit, special_compare, special_run
               FROM problem INNER JOIN contestproblem USING (probid)
               ORDER BY probid');

$details = '';
while($row = $res->next()) {
	$CHECKER_ERRORS = array();
	check_problem($row);
	if ( count ( $CHECKER_ERRORS ) > 0 ) {
		foreach($CHECKER_ERRORS as $chk_err) {
			$details .= 'p'.$row['probid']." in contest c" . $row['cid'] .': ' . $chk_err."\n";
		}
	}
	if ( ! $DB->q("MAYBEVALUE SELECT count(testcaseid) FROM testcase
 	               WHERE input IS NOT NULL AND output IS NOT NULL AND
 	               probid = %i", $row['probid']) ) {
		$details .= 'p'.$row['probid']." in contest c" . $row['cid'] . ": missing in/output testcase.\n";
	}
}
foreach(array('input','output') as $inout) {
	$mismatch = $DB->q("SELECT probid, rank FROM testcase
	                    WHERE md5($inout) != md5sum_$inout");
	while($r = $mismatch->next()) {
		$details .= 'p'.$r['probid'] . ": testcase #" . $r['rank'] .
		    " MD5 sum mismatch between $inout and md5sum_$inout\n";
	}
}
$oversize = $DB->q("SELECT probid, rank, OCTET_LENGTH(output) AS size
                    FROM testcase WHERE OCTET_LENGTH(output) > %i",
                   dbconfig_get('output_limit')*1024);
while($r = $oversize->next()) {
	$details .= 'p'.$r['probid'] . ": testcase #" . $r['rank'] .
	    " output size (" . printsize($r['size']) . ") exceeds output_limit\n";
}

$has_errors = $details != '';
$probs = $DB->q("TABLE SELECT probid, cid FROM contestproblem WHERE color IS NULL");
foreach($probs as $probdata) {
       $details .= 'p'.$probdata['probid'] . " in contest c" . $probdata['cid'] . ": has no color\n";
}

result('problems, languages, teams', 'Problems integrity',
	$details == '' ? 'O':($has_errors?'E':'W'),
	$details);

flushresults();

// LANGUAGES

$res = $DB->q('SELECT * FROM language ORDER BY langid');

$details = ''; $langseverity = 'W';
while($row = $res->next()) {
	$CHECKER_ERRORS = array();
	check_language($row);
	if ( count ( $CHECKER_ERRORS ) > 0 ) {
		foreach($CHECKER_ERRORS as $chk_err) {
			$details .= $row['langid'].': ' . $chk_err;
			// if this language is set to 'submittable', it's an error
			if ( $row['allow_submit'] == 1 ) $langseverity = 'E';
			else $details .= ' (but is not submittable)';
			$details .= "\n";
		}
	}
}

result('problems, languages, teams',
	'Languages integrity',
	$details == '' ? 'O': $langseverity,
	$details);

$details = '';
if ( dbconfig_get('show_affiliations', 1) ) {
	$res = $DB->q('SELECT affilid FROM team_affiliation ORDER BY affilid');

	while ( $row = $res->next() ) {
		$CHECKER_ERRORS = array();
		check_affiliation($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				$details .= $row['affilid'].': ' . $chk_err . "\n";
			}
		}
	}

	$res = $DB->q('SELECT DISTINCT country FROM team_affiliation
	               WHERE country IS NOT NULL ORDER BY country');
	while ( $row = $res->next() ) {
		$cflag = '../images/countries/' .
			urlencode($row['country']) . '.png';
		if ( ! file_exists ( $cflag ) ) {
			$details .= "Country " . $row['country'] .
				" does not have a flag (looking for $cflag).\n";
		} elseif ( ! is_readable ( $cflag ) ) {
			$details .= "Country " . $row['country'] .
				" has a flag, but it's not readable ($cflag).\n";
		}
	}

	result('problems, languages, teams', 'Team affiliation icons',
	       ($details == '') ? 'O' : 'W', $details);

} else {
	result('problems, languages, teams', 'Team affiliation icons',
	       'O', 'Affiliation icons disabled in config.');
}

flushresults();

// SUBMISSIONS, JUDINGS

$submres = 'O';
$submnote = NULL;
if ( ! is_writable(SUBMITDIR) ) {
	$submres = 'W';
	$submnote = 'The webserver has no write access to SUBMITDIR (' .
	            htmlspecialchars(SUBMITDIR) . '), and thus will not ' .
	            'be able to make backup copies of submissions.';
}

result('submissions and judgings', 'Submissions', $submres, $submnote);

// check for non-existent problem references
$res = $DB->q('SELECT s.submitid, s.probid, s.cid FROM submission s
               LEFT JOIN contestproblem p USING (cid,probid)
               WHERE p.shortname IS NULL');

$details = '';
while($row = $res->next()) {
	$details .= 'Submission s' .  $row['submitid'] . ' is for problem p' .
		$row['probid'] .
		' while this problem is not found (in c'. $row['cid'] . ")\n";
}

$res = $DB->q('SELECT * FROM submission ORDER BY submitid');

while($row = $res->next()) {
	$CHECKER_ERRORS = array();
	check_submission($row);
	if ( count ( $CHECKER_ERRORS ) > 0 ) {
		foreach($CHECKER_ERRORS as $chk_err) {
			$details .= $row['submitid'].': ' . $chk_err ."\n";
		}
	}
}

// check for submissions that have no associated source file(s)
$res = $DB->q('SELECT s.submitid FROM submission s
               LEFT OUTER JOIN submission_file f USING (submitid)
               WHERE f.submitid IS NULL');

while($row = $res->next()) {
	$details .= 'Submission s' . $row['submitid'] .
	            " does not have any associated source files\n";
}

// check for submissions that have been marked by a judgehost but that
// have no judging-row
$res = $DB->q('SELECT s.submitid FROM submission s
               LEFT OUTER JOIN judging j USING (submitid)
               WHERE j.submitid IS NULL AND s.judgehost IS NOT NULL');

while($row = $res->next()) {
	$details .= 'Submission s' . $row['submitid'] .
	            " has a judgehost but no entry in judgings\n";
}

result('submissions and judgings', 'Submission integrity',
	($details == '' ? 'O':'E'), $details);


$details = '';
// check for more than one valid judging for a submission
$res = $DB->q('SELECT submitid, SUM(valid) as numvalid
	FROM judging GROUP BY submitid HAVING numvalid > 1');
while($row = $res->next()) {
	$details .= 'Submission s' . $row['submitid'] .
	            ' has more than one valid judging (' . $row['numvalid'] . ")\n";
}

// check for valid judgings that are already running too long
$res = $DB->q('SELECT judgingid, submitid, starttime
               FROM judging WHERE valid = 1 AND endtime IS NULL AND
               (UNIX_TIMESTAMP()-starttime) > 300');
while($row = $res->next()) {
	$details .= 'Judging s' . (int)$row['submitid'] . '/j' . (int)$row['judgingid'] .
	            " is running for longer than 5 minutes, probably the judgedaemon crashed\n";
}

// check for start/endtime problems and contestids
$res = $DB->q('SELECT s.submitid AS s_submitid, j.submitid AS j_submitid,
               judgingid, starttime, endtime, submittime, s.cid AS s_cid, j.cid AS j_cid
               FROM judging j LEFT OUTER JOIN submission s USING (submitid)
               WHERE (j.cid != s.cid) OR (s.submitid IS NULL) OR
               (j.endtime IS NOT NULL AND j.endtime < j.starttime) OR
               (j.starttime < s.submittime)');

while($row = $res->next()) {
	$err = 'Judging j' . $row['judgingid'] . '/s' . $row['j_submitid'] . '';
	$CHECKER_ERRORS = array();
	if(!isset($row['s_submitid'])) {
		$CHECKER_ERRORS[] = 'has no corresponding submitid (in c'.$row['j_cid'] .')';
	}
	if($row['s_cid'] != NULL && $row['s_cid'] != $row['j_cid']) {
		$CHEKCER_ERRORS[] = 'Judging j' .$row['judgingid'] .
		                    ' is from a different contest (c' . $row['j_cid'] .
		                    ') than its submission s' . $row['j_submitid'] .
		                    ' (c' . $row['s_cid'] . ')';
	}
	check_judging($row);
	if ( count ( $CHECKER_ERRORS ) > 0 ) {
		foreach($CHECKER_ERRORS as $chk_err) {
			$details .= $err.': ' . $chk_err ."\n";
		}
	}
}

result('submissions and judgings', 'Judging integrity',
       ($details == '' ? 'O':'E'), $details);

flushresults();

// REFERENTIAL INTEGRITY. Nothing should turn up here since
// we have defined foreign key relations between our tables.
if ( $_SERVER['QUERY_STRING'] == 'refint' ) {

	$details = '';
	foreach ( $RELATIONS as $table => $foreign_keys ) {
		if ( empty($foreign_keys) ) {
			continue;
		}
		$fields = implode(', ', array_keys($foreign_keys));
		$res = $DB->q('SELECT ' . $fields . ' FROM ' . $table .
		              ' ORDER BY ' . implode(',', $KEYS[$table]));
		while ( $row = $res->next() ) {
			foreach ( $foreign_keys as $foreign_key => $val ) {
				list( $target, $action ) = explode('&', $val);
				if ( empty($row[$foreign_key]) || $action=='NOCONSTRAINT' ) {
					continue;
				}
				$f = explode('.', $target);
				if ( $DB->q("VALUE SELECT count(*) FROM $f[0] WHERE $f[1] = %s",
				            $row[$foreign_key]) < 1 ) {
					$details .= "foreign key constraint fails for $table.$foreign_key = \"" .
					            $row[$foreign_key] . "\" (not found in $target)\n";
				}
			}
		}
	}

	// problems found are of level warning, because the severity may be different depending
	// on which table it is.
	result('referential integrity', 'Inter-table relationships',
	       ($details == '' ? 'O':'W'), $details);
} else {
	result('referential integrity', 'Inter-table relationships', 'R',
	       'Not checked.', '<a href="?refint">check now</a> (potentially slow operation)');
}

flushresults();

echo "</table>\n\n";

// collapse all details; they are not collapsed in the default
// style sheet to keep things working with JavaScript disabled.
echo "<script type=\"text/javascript\">
<!--
for (var i = 0; i < $resultno; i++) {
    collapse(i);
}
// -->
</script>\n\n";

$time_end = microtime(TRUE);

echo "<p>Config checker completed in ".round($time_end - $time_start,2)." seconds.</p>\n\n";

echo "<p>Legend:
<img src=\"../images/s_okay.png\"      alt=\"O\" class=\"picto\" /> OK
<img src=\"../images/s_warn.png\"      alt=\"W\" class=\"picto\" /> Warning
<img src=\"../images/s_error.png\"     alt=\"E\" class=\"picto\" /> Error
</p>\n";

require(LIBWWWDIR . '/footer.php');
