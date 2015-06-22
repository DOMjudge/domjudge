<?php
/**
 * Code to import teams and upload standings from and to
 * https://icpc.baylor.edu/.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

define('ICPCWSCLICS', 'https://icpc.baylor.edu/ws/clics/');
define('ICPCWSSTANDINGS', 'https://icpc.baylor.edu/ws/standings/');

function updated($array, $table, $type = 'created') {
	if ( count($array) == 0 ) {
		echo "<p class=\"nodata\">No " . $table . "s $type.</p>\n";
	} else {
		echo "$type " . count($array) . " " . $table . "(s):\n";
		echo "<ul>\n";
		foreach ( $array as $single ) {
			echo "<li>$single</li>\n";
		}
		echo "</ul>\n";
	}
	echo "<hr/>\n";
}

if ( isset($_REQUEST['fetch']) ) {
	$title = 'Import teams from icpc.baylor.edu';
} else {
	$title = 'Upload standings to icpc.baylor.edu';
}

$token = @$_REQUEST['token'];
$contest = @$_REQUEST['contest'];

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

echo "<h1>$title</h1>\n";

if ( empty($token) || empty($contest) ) {
	error("Unknown access token or contest.");
}
if ( !function_exists('curl_init') ) {
	error("PHP cURL extension required. Please install the php5-curl package.");
}

if ( isset($_REQUEST['fetch']) ) {
	$ch = curl_init(ICPCWSCLICS . $contest);
} else {
	$ch = curl_init(ICPCWSSTANDINGS . $contest);
}
curl_setopt($ch, CURLOPT_USERAGENT, "DOMjudge/" . DOMJUDGE_VERSION);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$token:");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json"));
if ( isset($_REQUEST['upload']) ) {
	if ( difftime($cdata['endtime'],now()) >= 0 ) {
		error("Contest did not end yet. Refusing to upload standings before contest end.");
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	$data = '<?xml version="1.0" encoding="UTF-8"?><icpc computeCitations="1" name="Upload_via_DOMjudge_' . date("c") . '">';
	$teams = $DB->q('SELECT teamid,externalid FROM team WHERE externalid IS NOT NULL AND enabled=1');
	while( $row = $teams->next() ) {
		$totals = $DB->q("MAYBETUPLE SELECT correct, totaltime
					FROM rankcache_jury
					WHERE cid = %i
					AND teamid = %i", $cid, $row['teamid']);
		if ( $totals === null ) {
			$totals['correct'] = $totals['totaltime'] = 0;
		}
		$rank = calcTeamRank($cdata, $row['teamid'], $totals, TRUE);
		$lastProblem = $DB->q('MAYBEVALUE SELECT MAX(totaltime) FROM scorecache_jury WHERE teamid=%i AND cid=%i', $row['teamid'], $cid);
		if ( $lastProblem === NULL ) {
			$lastProblem = 0;
		}
		$data .= '<Standing LastProblemTime="' . $lastProblem . '" ProblemsSolved="' .  $totals['correct'] . '" Rank="' . $rank .
			'" TeamID="' . $row['externalid'] . '" TotalTime="' .
			$totals['totaltime'] .
			'"/>';
	}
	$data .= '</icpc>';
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
}

$response = curl_exec($ch);
if ( $response === FALSE ) {
	error("Error while retrieving data from icpc.baylor.edu: " . curl_error($ch));
}
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ( $status == 401 ) {
	error("Access forbidden, is your token valid?");
}
if ( $status < 200 || $status >= 300 ) {
	error("Unknown error while retrieving data from icpc.baylor.edu, status code: $status, $response");
}
curl_close($ch);

if ( isset($_REQUEST['upload']) ) {
	echo "Uploaded standings to icpc.baylor.edu (Response was: $response).<br/>";
	echo "Do not forget to certify the standings there - it maybe necessary to logout/login there to see the standings.";
	exit;
}

$json = json_decode($response, TRUE);
if ( $json === NULL ) {
	error("Error retrieving API data. API gave us: " . $response);
}

$participants = $DB->q('VALUE SELECT categoryid FROM team_category WHERE name=%s', 'Participants');
$teamrole = $DB->q('VALUE SELECT roleid FROM role WHERE role=%s', 'team');

$new_affils = array();
$new_teams = array();
$updated_teams = array();
foreach ( $json['icpcExport']['contest']['groups']['group'] as $group ) {
	foreach ( $group['team'] as $team ) {
		// Note: affiliations are not updated and not deleted even if all teams are canceled
		$affilid = $DB->q('MAYBEVALUE SELECT affilid FROM team_affiliation
		                   WHERE name=%s', $team['institutionName']);
		if ( empty($affilid) ) {
			$affilid = $DB->q('RETURNID INSERT INTO team_affiliation (name, shortname, country) VALUES (%s, %s, %s)',
				$team['institutionName'], $team['institutionShortName'], $team['country']);
			$new_affils[] = $team['institutionName'];
		}

		// collect team members
		$members = "";
		$members_json = $team['teamMembers']['teamMember'];
		// FIXME: if there's only team member, it's not encapsulated in an array :-/
		if ( isset($members_json['@team']) ) {
			$members_json  = array($members_json);
		}
		foreach ( $members_json as $member ) {
			$members .= $member['firstName'] . " " . $member['lastName'] . "\n";
		}

		// Note: teams are not deleted but disabled depending on their status
		$id = $DB->q('MAYBEVALUE SELECT teamid FROM team
		              WHERE externalid=%i', $team['teamId']);
		$enabled = $team['status'] === 'ACCEPTED';
		if ( empty($id) ) {
			$id = $DB->q('RETURNID INSERT INTO team (name, categoryid, affilid, enabled, members, comments, externalid) VALUES (%s, %i, %i, %i, %s, %s, %i)',
				$team['teamName'], $participants, $affilid, $enabled, $members, "Status: " . $team['status'], $team['reservationId']);
			$username = sprintf("team%04d", $id);
			$userid = $DB->q('RETURNID INSERT INTO user (username, name, teamid) VALUES (%s,%s,%i)', $username, $team['teamName'], $id);
			$DB->q('INSERT INTO userrole (userid, roleid) VALUES (%i,%i)', $userid, $teamrole);
			$new_teams[] = $team['teamName'];
		} else {
			$username = sprintf("team%04d", $id);
			$cnt = $DB->q('RETURNAFFECTED UPDATE team SET name=%s, categoryid=%i, affilid=%i, enabled=%i, members=%s, comments=%s WHERE teamid=%i',
				$team['teamName'], $participants, $affilid, $enabled, $members, "Status: " . $team['status'], $id);
			$cnt += $DB->q('RETURNAFFECTED UPDATE user SET name=%s WHERE username=%s', $team['teamName'], $username);
			if ( $cnt > 0 ) {
				$updated_teams[] = $team['teamName'];
			}
		}
	}
}

updated($new_affils, "team affiliation");
updated($new_teams, "team");
updated($updated_teams, "team", "updated");
