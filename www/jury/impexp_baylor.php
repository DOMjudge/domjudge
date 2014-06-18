<?php
/**
 * Code to import teams and upload standings from and to
 * https://icpc.baylor.edu/.
 * 
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

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

$title = 'Import teams from icpc.baylor.edu';

$token = @$_REQUEST['token'];
$contest = @$_REQUEST['contest'];

require(LIBWWWDIR . '/header.php');

echo "<h1>$title</h1>\n";

if ( empty($token) || empty($contest) ) {
	error("Unknown access token or contest.");
	exit;
}

$ch = curl_init("https://icpc.baylor.edu/ws/clics/$contest");
curl_setopt($ch, CURLOPT_USERAGENT, "DOMJudge4.0");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$token:");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json"));

$response = curl_exec($ch);
if ( $response === FALSE ) {
	error("Error while retrieving data from icpc.baylor.edu: " . curl_error($ch));
}
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ( $status == 401 ) {
	error("Access forbidden, is your token valid?");
}
if ( $status < 200 || $status >= 300 ) {
	error("Unknown error while retrieving data from icpc.baylor.edu, status code: $status");
}
curl_close($ch);

$json = json_decode($response, TRUE);
if ( $json === NULL ) {
	error("Error retrieving API data. API gave us: " . $response);
}

$new_affils = array();
$new_teams = array();
$updated_teams = array();
foreach ( $json['icpcExport']['contest']['group'] as $group ) {
	foreach ( $group['team'] as $team ) {
		// Note: affiliations are not updated and not deleted even if all teams are canceled
		$affilid = $DB->q('MAYBEVALUE SELECT affilid FROM team_affiliation WHERE name=%s', $team['institutionName']);
		if ( empty($affilid) ) {
			$affilid = $DB->q('RETURNID INSERT INTO team_affiliation (name, shortname, country) VALUES (%s, %s, %s)',
				$team['institutionName'], $team['institutionShortName'], $team['country']);
			$new_affils[] = $team['institutionName'];
		}

		// collect team members
		$members = "";
		$members_json = $team['teamMember'];
		// FIXME: if there's only team member, it's not encapsulated in an array :-/
		if ( isset($members_json['@team']) ) {
			$members_json  = array($members_json);
		}
		foreach ( $members_json as $member ) {
			$members .= $member['firstName'] . " " . $member['lastName'] . "\n";
		}

		// Note: teams are not deleted but disabled depending on their status
		$id = $DB->q('MAYBEVALUE SELECT teamid FROM team WHERE externalid=%i', $team['reservationId']);
		$enabled = $team['status'] === 'ACCEPTED';
		if ( empty($id) ) {
			$id = $DB->q('RETURNID INSERT INTO team (name, categoryid, affilid, enabled, members, comments, externalid) VALUES (%s, %i, %i, %i, %s, %s, %i)',
				$team['teamName'], 2, $affilid, $enabled, $members, "Status: " . $team['status'], $team['reservationId']);
			$new_teams[] = $team['teamName'];
		} else {
			$cnt = $DB->q('RETURNAFFECTED UPDATE team SET name=%s, categoryid=%i, affilid=%i, enabled=%i, members=%s, comments=%s WHERE teamid=%i',
				$team['teamName'], 2, $affilid, $enabled, $members, "Status: " . $team['status'], $id);
			if ( $cnt > 0 ) {
				$updated_teams[] = $team['teamName'];
			}
		}
	}
}

updated($new_affils, "team affiliation");
updated($new_teams, "team");
updated($updated_teams, "team", "updated");
