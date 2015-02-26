<?php
/**
 * Code to import and export tsv formats as specified by the ICPC
 * Contest Control System Standard.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');
$title = "TSV Import";

requireAdmin();

$fmt = @$_REQUEST['fmt'];
$act = @$_REQUEST['act'];

if ( $act == 'im' ) {
	require(LIBWWWDIR . '/header.php');
	tsv_import($fmt);
	require(LIBWWWDIR . '/footer.php');
} elseif ( $act == 'ex' ) {
	tsv_export($fmt);
} else {
	error ("Unknown action.");
}

/** Import functions **/
function tsv_import($fmt)
{

	echo "<p>Importing $fmt.</p>\n\n";

	// generic for each tsv format
	checkFileUpload( $_FILES['tsv']['error'] );
	// read entire file into an array
	$content = file($_FILES['tsv']['tmp_name']);
	// the first line of the tsv is always the format with a version number.
	// currently we hardcode version 1 because there are no others
	$version = rtrim(array_shift($content));
	// Two variants are in use: one where the first token is a static string
	// "File_Version" and the second where it's the type, e.g. "groups".
	if ( !preg_match("/^(File_Version|$fmt)\t1$/", $version) ) {
		error ("Unknown format or version: $version");
	}

	// select each format and call appropriate functions.
	// the prepare function parses the tsv, checks if the data looks sane,
	// and delivers it in the format for the setter function. The latter
	// updates the database (so only after all lines have first been
	// read and checked).
	switch ($fmt) {
		case 'groups':
			$data = tsv_groups_prepare($content);
			$c = tsv_groups_set($data);
			break;
		case 'teams':
			$data = tsv_teams_prepare($content);
			$c = tsv_teams_set($data);
			break;
		case 'accounts':
			$data = tsv_accounts_prepare($content);
			$c = tsv_accounts_set($data);
			break;
		default: error("Unknown format");
	}

	echo "<p>$c rows imported</p>";

}


function tsv_groups_prepare($content)
{
	$data = array();
	$l = 1;
	foreach($content as $line) {
		$l++;
		$line = explode("\t", trim($line));
		if ( ! is_numeric($line[0]) ) error ("Invalid id format on line $l");
		$data[] = array (
			'categoryid' => @$line[0],
			'name' =>  @$line[1]);
	}

	return $data;
}


function tsv_groups_set($data)
{
	global $DB;
	$c = 0;
	foreach ($data as $row) {
		$DB->q("REPLACE INTO team_category SET %S", $row);
		auditlog('team_category', $row['categoryid'], 'replaced', 'imported from tsv');
		$c++;
	}
	return $c;
}

function tsv_teams_prepare($content)
{
	$data = array();
	$l = 1;
	foreach($content as $line) {
		$l++;
		$line = explode("\t", trim($line));

		// teams.tsv contains data pertaining both to affiliations and teams.
		// hence return data for both tables.

		// we may do more integrity/format checking of the data here.
		$data[] = array (
			'team' => array (
				'teamid' => @$line[0],
				'externalid' => @$line[1],
				'categoryid' => @$line[2],
				'name' => @$line[3]),
			'team_affiliation' => array (
				'shortname' => @$line[5],
				'name' => @$line[4],
				'country' => @$line[6]) );
	}

	return $data;
}


function tsv_teams_set($data)
{
	global $DB;
	$c = 0;
	foreach ($data as $row) {
		// it is legitimate that a team has no affiliation. Do not add it then.
		if ( !empty($row['team_affiliation']['shortname']) ) {
			$DB->q("REPLACE INTO team_affiliation SET %S", $row['team_affiliation']);
			$affilid = $DB->q("VALUE SELECT affilid FROM team_affiliation WHERE shortname = %s LIMIT 1", $row['team_affiliation']['shortname']);
			auditlog('team_affiliation', $affilid, 'replaced', 'imported from tsv');
			$row['team']['affilid'] = $affilid;
		}
		$DB->q("REPLACE INTO team SET %S", $row['team']);
		auditlog('team', $row['team']['teamid'], 'replaced', 'imported from tsv');
		$c++;
	}
	return $c;
}


function tsv_accounts_prepare($content)
{
	global $DB;
	$data = array();
	$l = 1;
	$juryroleid = $DB->q('VALUE SELECT roleid FROM role WHERE role = %s', 'jury');
	$adminroleid = $DB->q('VALUE SELECT roleid FROM role WHERE role = %s', 'admin');
	foreach($content as $line) {
		$l++;
		$line = explode("\t", trim($line));

		if ($line[0] != 'admin' && $line[0] != 'judge') {
			error('unknown role id in line ' . $l . ': ' . $line[0]);
		}
		$line[0] = ($line == 'admin' ? $adminroleid : $juryroleid);

		// accounts.tsv contains data pertaining both to users and userroles.
		// hence return data for both tables.

		// we may do more integrity/format checking of the data here.
		$data[] = array (
			'user' => array (
				'name' => $line[2],
				'username' => $line[3],
				'password' => md5($line[3].'#'.$line[4])),
			'userrole' => array (
				'userid' => -1, // need to get appropriate userid later
				'roleid' => $line[0])
			);
	}

	return $data;
}


function tsv_accounts_set($data)
{
	global $DB;
	$c = 0;
	foreach ($data as $row) {
		$DB->q("REPLACE INTO user SET %S", $row['user']);
		$userid = $DB->q("VALUE SELECT userid FROM user WHERE username = %s", $row['user']['username']);
		auditlog('user', $userid, 'replaced', 'imported from tsv');
		$row['userrole']['userid'] = $userid;
		$DB->q("REPLACE INTO userrole SET %S", $row['userrole']);
		auditlog('userrole', $userid, 'replaced', 'imported from tsv');
		$c++;
	}
	return $c;
}


/** Export functions **/
function tsv_export($fmt)
{
	// export files in tsv format. Call approprate output generation function
	// for each supported format.
	switch ( $fmt ) {
		case 'groups':     $data = tsv_groups_get();     $version = 1; break;
		case 'teams':      $data = tsv_teams_get();      $version = 1; break;
		case 'scoreboard': $data = tsv_scoreboard_get(); $version = 1; break;
		case 'results':    $data = tsv_results_get();    $version = 1; break;
	//	case 'userdata':   $data = tsv_userdata_get();   $version = 1; break;
	//	case 'accounts':   $data = tsv_accounts_get();   $version = 1; break;
		default: error('Specified format not (yet) supported.');
	}

	header("Content-Type: text/plain; name=\"" . $fmt . ".tsv\"; charset=" . DJ_CHARACTER_SET);
	header("Content-Disposition: attachment; filename=\"" . $fmt . ".tsv\"");

	// first a line with the format and version number
	echo "$fmt\t$version\n";
	// output the rows, filtering out any tab characters in the data
	foreach($data as $row) {
		echo implode("\t", str_replace("\t"," ",$row)) . "\n";
	}
}

function tsv_groups_get()
{
	// groups are categories.
	// we only select visible groups as the others are considered 'internal'
	global $DB;
	return $DB->q('TABLE SELECT categoryid, name FROM team_category WHERE visible = 1');
}

function tsv_teams_get()
{
	global $DB;
	return $DB->q('TABLE SELECT teamid, externalid, categoryid, t.name, a.name as affilname, a.shortname, a.country
	               FROM team t LEFT JOIN team_affiliation a USING(affilid)
	               WHERE enabled = 1');

}

function tsv_scoreboard_get()
{
	// we'll here assume that the requested file will be of the current contest,
	// as all our scoreboard interfaces do
	// 1 	Institution name 	University of Virginia 	string
	// 2	External ID 	24314 	integer
	// 3 	Position in contest 	1 	integer
	// 4 	Number of problems the team has solved 	4 	integer
	// 5 	Total Time 	534 	integer
	// 6 	Time of the last accepted submission 	233 	integer   -1 if none
	// 6 + 2i - 1 	Number of submissions for problem i 	2 	integer
	// 6 + 2i 	Time when problem i was solved 	233 	integer   -1 if not
	global $cdata;
	$sb = genScoreBoard($cdata, true);

	$data = array();
	foreach ($sb['scores'] as $teamid => $srow) {
		$maxtime = -1;
		$drow = array();
		foreach($sb['matrix'][$teamid] as $prob) {
			$drow[] = $prob['num_submissions'];
			$drow[] = $prob['is_correct'] ? $prob['time'] : -1;
			$maxtime = max($maxtime, $prob['time']);
		}
		$data[] = array_merge (
			array($sb['teams'][$teamid]['affilname'], @$sb['teams'][$teamid]['externalid'],
				$srow['rank'], $srow['num_correct'], $srow['total_time'], $maxtime),
			$drow
			);
	}

	return $data;
}

$extid_to_name = array();

// sort data array according to rank and name
function cmp_extid_name($a, $b) {
	global $extid_to_name;
	if ( $a[1] != $b[1] ) {
		// Honorable mention has no rank
		if ( $a[1] == "" ) {
			return 1;
		} else if ( $b[1] == "" ) {
			return -11;
		}
		return $a[1] - $b[1];
	}
	$name_a = $extid_to_name[$a[0]];
	$name_b = $extid_to_name[$b[0]];
	return strcmp($name_a, $name_b);
}

function tsv_results_get()
{
	// we'll here assume that the requested file will be of the current contest,
	// as all our scoreboard interfaces do
	// 1 	External ID 	24314 	integer
	// 2 	Rank in contest 	1 	integer
	// 3 	Award 	Gold Medal 	string
	// 4 	Number of problems the team has solved 	4 	integer
	// 5 	Total Time 	534 	integer
	// 6 	Time of the last submission 	233 	integer
	// 7 	Group Winner 	North American 	string
	global $cdata, $DB, $extid_to_name;

	$categs = $DB->q('COLUMN SELECT categoryid FROM team_category WHERE visible = 1');
	$sb = genScoreBoard($cdata, true, array('categoryid' => $categs));
	$extid_to_name = $DB->q('KEYVALUETABLE SELECT externalid, name FROM team ORDER BY externalid');

	$numteams = sizeof($sb['scores']);

	// determine number of problems solved by median team
	$cnt = 0;
	foreach ($sb['scores'] as $teamid => $srow) {
		$cnt++;
		$median = $srow['num_correct'];
		if ($cnt > $numteams/2) { // XXX: lower or upper median?
			break;
		}
	}

	$ranks = array();
	$group_winners = array();
	$data = array();
	foreach ($sb['scores'] as $teamid => $srow) {
		$maxtime = -1;
		foreach($sb['matrix'][$teamid] as $prob) {
			$maxtime = max($maxtime, $prob['time']);
		}

		$rank = $srow['rank'];
		$num_correct = $srow['num_correct'];
		if ( $rank <= 4 ) {
			$awardstring = "Gold Medal";
		} else if ( $rank <= 8 ) {
			$awardstring = "Silver Medal";
		} else if ( $rank <= 12 ) {
			$awardstring = "Bronze Medal";
		} else if ( $num_correct >= $median ) {
			// teams with equally solved number of problems get the same rank
			if ( !isset($ranks[$num_correct]) ) {
				$ranks[$num_correct] = $rank;
			}
			$rank = $ranks[$num_correct];
			$awardstring = "Ranked";
		} else {
			$awardstring = "Honorable";
			$rank = "";
		}

		$groupwinner = "";
		if ( !isset($group_winners[$srow['categoryid']]) ) {
			$group_winners[$srow['categoryid']] = true;
			$groupwinner = $DB->q('VALUE SELECT name FROM team_category WHERE categoryid = %i', $srow['categoryid']);
		}

		$data[] = array(@$sb['teams'][$teamid]['externalid'],
				$rank, $awardstring, $srow['num_correct'],
				$srow['total_time'], $maxtime, $groupwinner);
	}

	// sort by rank/name
	uasort($data, 'cmp_extid_name');

	return $data;
}
