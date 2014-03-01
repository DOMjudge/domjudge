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
	$version = array_shift($content);
	if ( trim($version) != "File_Version\t1" ) {
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
				'login' => @$line[0],
				'externalid' => @$line[1],
				'categoryid' => @$line[2],
				'name' => @$line[3],
				'affilid' => @$line[4]),
			'team_affiliation' => array (
				'affilid' => @$line[4],
				'name' => @$line[5],
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
		if ( !empty($row['team_affiliation']['affilid']) ) {
			$DB->q("REPLACE INTO team_affiliation SET %S", $row['team_affiliation']);
			auditlog('team_affiliation', $row['team_affiliation']['affilid'], 'replaced', 'imported from tsv');
		}
		$DB->q("REPLACE INTO team SET %S", $row['team']);
		auditlog('team', $row['team']['login'], 'replaced', 'imported from tsv');
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
	//	case 'results':    $data = tsv_results_get();    $version = 1; break;
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
	// login is our team number
	// we use affilid as the short name
	global $DB;
	return $DB->q('TABLE SELECT login, externalid, categoryid, t.name, a.name as affilname, a.affilid, a.country
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
	foreach ($sb['scores'] as $login => $srow) {
		$maxtime = -1;
		foreach($sb['matrix'][$login] as $prob) {
			$drow[] = $prob['num_submissions'];
			$drow[] = $prob['is_correct'] ? $prob['time'] : -1;
			$maxtime = max($maxtime, $prob['time']);
		}
		$data[] = array_merge (
			array($sb['teams'][$login]['affilname'], $sb['teams'][$login]['externalid'],
				$srow['rank'], $srow['num_correct'], $srow['total_time'], $maxtime),
			$drow
			);
	}

	return $data;
}
