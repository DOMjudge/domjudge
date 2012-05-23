<?php
/**
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');

requireAdmin();
$fmt = @$_GET['fmt'];

switch ( $fmt ) {
	case 'groups':     $data = tsv_groups();     $version = 1; break;
	case 'teams':      $data = tsv_teams();      $version = 1; break;
	case 'scoreboard': $data = tsv_scoreboard(); $version = 1; break;
//	case 'results':    $data = tsv_results();    $version = 1; break;
//	case 'userdata':   $data = tsv_userdata();   $version = 1; break;
//	case 'accounts':   $data = tsv_accounts();   $version = 1; break;
	default: error('Specified format not (yet) supported.');
}

header("Content-Type: text/plain; name=\"" . $fmt . ".tsv\"; charset=" . DJ_CHARACTER_SET);
header("Content-Disposition: attachment; filename=\"" . $fmt . ".tsv\"");

echo "$fmt\t$version\n";
foreach($data as $row) {
	echo implode("\t", str_replace("\t"," ",$row)) . "\n";
}

function tsv_groups()
{
	// groups are categories.
	// we only select visible groups as the others are considered 'internal'
	global $DB;
	return $DB->q('TABLE SELECT categoryid, name FROM team_category WHERE visible = 1');
}

function tsv_teams()
{
	// login is our team number
	// we use affilid as the short name
	global $DB;
	return $DB->q('TABLE SELECT login, externalid, categoryid, t.name, a.name, a.affilid, a.country
	               FROM team t LEFT JOIN team_affiliation a USING(affilid)
	               WHERE enabled = 1');

}

function tsv_scoreboard()
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
