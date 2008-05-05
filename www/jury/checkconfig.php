<?php
/**
 * Do various sanity checks on the system regarding data constraints,
 * permissions and the like. At the moment this only contains some basic
 * checks but this can be extended in the future.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Config Checker';
require('../header.php');

requireAdmin();

?>
<script type="text/javascript" language="JavaScript">
<!--
function collapse(x){
  var oTemp=document.getElementById("detail"+x) ;
  if (oTemp.style.display=="block") {
    oTemp.style.display="none";
  } else {
    oTemp.style.display="block";
  }
}
// -->
</script>

<h1>Config Checker</h1>

<?php

/** Print the output of phpinfo(), which may be useful to check which settings
 *  PHP is actually using. */
 // FIXME
if ( $_SERVER['QUERY_STRING'] == 'phpinfo' ) {
	$ret = "<p><a href=\"./checkconfig.php\">return to config checker</a></p>\n\n";
	echo $ret;
	echo "<h2>PHP Information</h2>\n\n";
	phpinfo();
	echo $ret;
	require('../footer.php');
	exit;
}

require_once(SYSTEM_ROOT . '/lib/relations.php');
require_once('checkers.php');


$RESULTS = array();

function result($section, $item, $result, $details, $details_html = '') {
	global $RESULTS;

	$RESULTS[] = array('section' => $section,
		'item' => $item,
		'result' => $result,
		'details' => $details,
		'details_html' => $details_html);
}



// SOFTWARE

if( !function_exists('version_compare') || version_compare( '4.3.2',PHP_VERSION,'>=') ) {
	result('software', 'PHP version', 'E', 
		'You have PHP ' . PHP_VERSION . ', but need at least 4.3.2.',
		'See <a href="?phpinfo">phpinfo</a> for details.');
} else {
	result('software', 'PHP version', 'O', 
		'You have PHP ' . PHP_VERSION . '.',
		'See <a href="?phpinfo">phpinfo</a> for details.');
}

if ( include_highlighter() ) {
	result('software', 'PHP Highlighter class',
		'O', 'Optional PHP PEAR Text_Highlighter class is available.');
} else {
	result('software', 'PHP Highlighter class',
		'W', 'Optionally, install the PHP PEAR Text_Highlighter class '.
		'for better source syntax highlighting.',
		'<a href="http://pear.php.net/package/Text_Highlighter/">more information</a>');
}

$mysqldatares = $DB->q('SHOW variables WHERE
	Variable_name="max_connections" OR Variable_name = "version"');
while($row = $mysqldatares->next()) {
	$mysqldata[$row['Variable_name']] = $row['Value'];
}

result('software', 'MySQL version', 
	version_compare('4.1', $mysqldata['version'], '>=') ? 'E':'O',
	'Connected to ' . mysql_get_host_info().",\n".
	'MySQL server version ' .
	htmlspecialchars($mysqldata['version']) .
	'. Minimum required is 4.1.');

result('software', 'MySQL maximum connections',
	$mysqldata['max_connections'] < 300 ? 'W':'O',
	'MySQL\'s max_connections is set to ' .
	(int)$mysqldata['max_connections'] . '. In our experience ' .
	'you need at least 300, but better 1000 connections to ' .
	'prevent connection refusal during the contest.');


// SECURITY


if ( !isset( $_SERVER['REMOTE_USER'] ) ) {
	result('security', 'Protected Jury interface', 'W',
		"You are not using HTTP Authentication for the Jury interface. " .
		"Are you sure that the jury interface is adequately protected?\n");
} else {
	result('security', 'Protected Jury interface', 'O',
		'Logged in as user ' .
		htmlspecialchars($_SERVER['REMOTE_USER']) .
		".");
}

// CONTESTS

if($cid == null) {
	result('contests', 'Active contest', 'E',
		'No current contest found. System will not function.');
} else {
	result('contests', 'Active contest', 'O',
		'Current contest: c'.(int)$cid);
}

// get all contests
$res = $DB->q('SELECT * FROM contest ORDER BY cid');

$detail = '';
$has_errors = FALSE;
while($cdata = $res->next()) {

	$detail .=  "c".(int)$cdata['cid'].": ";

	$CHECKER_ERRORS = array();
	check_contest($cdata, array('cid' => $cdata['cid']));
	if ( count ( $CHECKER_ERRORS ) > 0 ) {
		$detail .= $chk_err;
		$has_errors = TRUE;
	} else {
		$detail .= "OK";
	}

	$detail .= "\n";
}

result('contests', 'Contests integrity',
	$has_errors ? 'E' : 'O',
	$detail);

// PROBLEMS

$res = $DB->q('SELECT * FROM problem ORDER BY probid');

$details = '';
if($res->count() > 0) {
	while($row = $res->next()) {
		$CHECKER_ERRORS = array();
		check_problem($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				$details .= $row['probid'].': ' . $chk_err."\n";
			}
		}
	}
}

result('problems, languages, teams', 'Problems integrity',
	$details == '' ? 'O':'E',
	$details);

// LANGUAGES

$res = $DB->q('SELECT * FROM language ORDER BY langid');

$details = '';
if($res->count() > 0) {
	while($row = $res->next()) {
		$CHECKER_ERRORS = array();
		check_language($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				$details .= $row['langid'].': ' . $chk_err ."\n";
			}
		}
	}
}

result('problems, languages, teams',
	'Languages integrity',
	$details == '' ? 'O': 'E',
	$details);



$res = $DB->q('SELECT * FROM team ORDER BY login');

$details = '';
if($res->count() > 0) {
	while($row = $res->next()) {
		$CHECKER_ERRORS = array();
		check_team($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				$details .= $row['login'].': ' . $chk_err . "\n";
			}
		}
	}
}

result('problems, languages, teams', 'Team integrity',
	$details == '' ? 'O': 'E', $details);

$details = '';
if ( SHOW_AFFILIATIONS ) {
	$res = $DB->q('SELECT affilid FROM team_affiliation ORDER BY affilid');

	while ( $row = $res->next() ) {
		$affillogo = '../images/affiliations/' .
			urlencode($row['affilid']) . '.png';
		if ( ! file_exists ( $affillogo ) ) {
			$details .= "Affiliation " . $row['affilid'] .
				" does not have a logo (looking for $affillogo).\n";
		} elseif ( ! is_readable ( $affillogo ) ) {
			$details .= "Affiliation " . $row['affilid'] .
				" has a logo, but it's not readable ($affillogo).\n";
		}
	}
	
	$res = $DB->q('SELECT DISTINCT country FROM team_affiliation ORDER BY country');
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
		($details == '') ? 'O' : 'E', $details);

} else {
	result('problems, languages, teams', 'Team affiliation icons',
		'O', 'Affiliation icons disabled in config.');
}


// SUBMISSIONS, JUDINGS

// check for non-existent problem references
$res = $DB->q('SELECT s.submitid, s.probid, s.cid FROM submission s
               LEFT OUTER JOIN problem p USING (probid) WHERE s.cid != p.cid');

$details = '';
if($res->count() > 0) {
	while($row = $res->next()) {
		$details .= 'Submission s' .  $row['submitid'] . ' is for problem "' .
			$row['probid'] .
			'" while this problem is not found (in c'. $row['cid'] . ")\n";
	}
}

$res = $DB->q('SELECT * FROM submission ORDER BY submitid');

if($res->count() > 0) {
	while($row = $res->next()) {
		$CHECKER_ERRORS = array();
		check_submission($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				$details .= $row['submitid'].': ' . $chk_err ."\n";
			}
		}
	}
}

// check for submissions that have been marked by a judgehost but that
// have no judging-row
$res = $DB->q('SELECT s.submitid FROM submission s
               LEFT OUTER JOIN judging j USING (submitid)
               WHERE j.submitid IS NULL AND s.judgehost IS NOT NULL');

if($res->count() > 0) {
	while($row = $res->next()) {
		$details .= 'Submission s' . $row['submitid'] . " has a judgehost but no entry in judgings\n";
	}
}

result('submissions and judgings', 'Submission integrity',
	($details == '' ? 'O':'E'), $details);


$details = '';
// check for more than one valid judging for a submission
$res = $DB->q('SELECT submitid, SUM(valid) as numvalid
	FROM judging GROUP BY submitid HAVING numvalid > 1');
if ( $res->count() > 0 ) {
	while($row = $res->next()) {
		$details .= 'Submission s' . $row['submitid'] . ' has more than one valid judging (' .
			$row['numvalid'] . ")\n";
	}
}

// check for unknown result strings
$res = $DB->q('SELECT judgingid, submitid, result
	FROM judging WHERE result IS NOT NULL AND result NOT IN (%As)',
	$EXITCODES);
if ( $res->count() > 0 ) {
	while($row = $res->next()) {
		$details .= 'Judging s' . (int)$row['submitid'] . '/j' . (int)$row['judgingid'] .
			' has an unknown result code "' .
			$row['result'] . "\"\n";
	}
}


// check for start/endtime problems and contestids
$res = $DB->q('SELECT s.submitid AS s_submitid, j.submitid AS j_submitid,
               judgingid, starttime, endtime, submittime, s.cid AS s_cid, j.cid AS j_cid
               FROM judging j LEFT OUTER JOIN submission s USING (submitid)
               WHERE (j.cid != s.cid) OR (s.submitid IS NULL) OR
               (j.endtime IS NOT NULL AND j.endtime < j.starttime) OR
               (j.starttime < s.submittime)');

if($res->count() > 0) {
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
}

result('submissions and judgings', 'Judging integrity',
	($details == '' ? 'O':'E'), $details);



// REFERENTIAL INTEGRITY

$details = '';
foreach ( $RELATIONS as $table => $foreign_keys ) {
	$res = $DB->q('SELECT * FROM ' . $table . ' ORDER BY ' . implode(',', $KEYS[$table]));
	while ( $row = $res->next() ) {
		foreach ( $foreign_keys as $foreign_key => $target ) {
			if ( !empty($row[$foreign_key]) ) {
				$f = explode('.', $target);
				if ( $DB->q("VALUE SELECT count(*) FROM $f[0] WHERE $f[1] = %s",
						$row[$foreign_key]) < 1 ) {
					$details .= "foreign key constraint fails for $table.$foreign_key = \"" .
						$row[$foreign_key] . "\" (not found in $target)\n";
				}
			}
		}
	}
}

// problems found are of level warning, because the severity may be different depending
// on which table it is.
result('referential integrity', 'Inter-table relationships',
	($details == '' ? 'O':'W'), $details);



// DISPLAY RESULTS

echo "<table class=\"configcheck\">\n";

$lastsection = false; $i = 0;

foreach($RESULTS as $row) {

	if ( empty($row['details']) ) $row['details'] = 'No issues found.';

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
		default: error("Unknown config checker result: ".$row['result']);
	}
	echo ".png\" alt=\"" . $row['result'] . "\" class=\"picto\" /></td><td>" .
		htmlspecialchars($row['item']) ." " .
		"<a href=\"javascript:collapse($i)\"><img src=\"../images/b_help.png\" " .
		"alt=\"?\" title=\"show details\"></a>\n" .
		"<div class=\"details\" id=\"detail$i\">" .
		nl2br(htmlspecialchars(trim($row['details']))."\n") . $row['details_html'] .
		"</div></td></tr>\n";

	++$i;
}

echo "</table>\n\n";

echo "<p>Config checker completed.</p>\n\n";

require('../footer.php');
