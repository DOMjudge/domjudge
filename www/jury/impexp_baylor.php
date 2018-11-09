<?php
/**
 * Code to import teams and upload standings from and to
 * https://icpc.baylor.edu/.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

define('ICPCWSCLICS', 'https://icpc.baylor.edu/cm5-contest-rest/rest/contest/export/CLICS/CONTEST/');
define('ICPCWSSTANDINGS', 'https://icpc.baylor.edu/ws/standings/');
define('ICPCWSTOKEN', 'https://icpc.baylor.edu/auth/realms/cm5/protocol/openid-connect/token');

function updated($array, $table, $type = 'created')
{
    if (count($array) == 0) {
        echo "<p class=\"nodata\">No " . $table . "s $type.</p>\n";
    } else {
        echo "$type " . count($array) . " " . $table . "(s):\n";
        echo "<ul>\n";
        foreach ($array as $single) {
            echo "<li>$single</li>\n";
        }
        echo "</ul>\n";
    }
    echo "<hr/>\n";
}

if (isset($_REQUEST['fetch'])) {
    $title = 'Import teams from icpc.baylor.edu';
} else {
    $title = 'Upload standings to icpc.baylor.edu';
}

$token = @$_REQUEST['token'];
$contest = @$_REQUEST['contest'];

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

echo "<h1>$title</h1>\n";

if (empty($token) || empty($contest)) {
    error("Unknown access token or contest.");
}
if (!function_exists('curl_init')) {
    error("PHP cURL extension required. Please install the php5-curl package.");
}

# request real token first
$ch = curl_init(ICPCWSTOKEN);
curl_setopt($ch, CURLOPT_USERAGENT, "DOMjudge/" . DOMJUDGE_VERSION);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json"));
$data = array(
    'client_id' => 'cm5-token',
    'username' => 'token:' . $token,
    'password' => '',
    'grant_type' => 'password'
);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$result = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($status >= 400 && $status < 500) {
    error("Access forbidden, is your token valid? " . htmlspecialchars($result));
}
if ($status < 200 || $status >= 300) {
    error("Unknown error while retrieving data from icpc.baylor.edu, status code: $status, $response");
}

$json = dj_json_decode($result);
$access_token = $json['access_token'];

if (isset($_REQUEST['fetch'])) {
    $ch = curl_init(ICPCWSCLICS . $contest);
} else {
    $ch = curl_init(ICPCWSSTANDINGS . $contest);
}

curl_setopt($ch, CURLOPT_USERAGENT, "DOMjudge/" . DOMJUDGE_VERSION);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER,
    array(
        "Authorization: bearer $access_token",
        "Accept: application/json",
    )
);

if (isset($_REQUEST['upload'])) {
    error("Sorry, standings upload is broken because of a format change.");
    if (difftime($cdata['endtime'], now()) >= 0) {
        error("Contest did not end yet. Refusing to upload standings before contest end.");
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    $data = '<?xml version="1.0" encoding="UTF-8"?><icpc computeCitations="1" name="Upload_via_DOMjudge_' . date("c") . '">';
    $teams = $DB->q('SELECT teamid, externalid FROM team
                     WHERE externalid IS NOT NULL AND enabled=1');
    while ($row = $teams->next()) {
        $totals = $DB->q('MAYBETUPLE SELECT points_restricted AS points,
                                            totaltime_restricted AS totaltime
                          FROM rankcache
                          WHERE cid = %i AND teamid = %i',
                         $cid,
            $row['teamid']
        );
        if ($totals === null) {
            $totals['points'] = $totals['totaltime'] = 0;
        }
        $rank = calcTeamRank($cdata, $row['teamid'], $totals, true);
        $lastProblem = $DB->q('MAYBEVALUE SELECT MAX(solvetime_restricted) FROM scorecache
                               WHERE teamid=%i AND cid=%i', $row['teamid'], $cid);
        if ($lastProblem === null) {
            $lastProblem = 0;
        } else {
            $lastProblem = scoretime($lastProblem);
        }

        //  If the scoreboard times have been kept in seconds,
        //  the values need to be converted to minutes before
        //  uploading to ICPC HQ.
        $totalTime = $totals['totaltime'];
        if (dbconfig_get('score_in_seconds', 0)) {
            $lastProblem = (int) floor($lastProblem / 60);
            $totalTime = (int) floor($totalTime / 60);
        }

        $data .= '<Standing LastProblemTime="' . $lastProblem . '" ProblemsSolved="' .  $totals['points'] . '" Rank="' . $rank .
            '" TeamID="' . $row['externalid'] . '" TotalTime="' .
            $totalTime .
            '"/>';
    }
    $data .= '</icpc>';
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
}

$response = curl_exec($ch);
if ($status >= 400 && $status < 500) {
    error("Access forbidden, is your token valid? Did you specify the correct contest ID? " . htmlspecialchars($result));
}
if ($status < 200 || $status >= 300) {
    error("Unknown error while retrieving data from icpc.baylor.edu, status code: $status, $response");
}

if ($response === false) {
    error("Error while retrieving data from icpc.baylor.edu: " . curl_error($ch));
}
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($status >= 400 && $status < 500) {
    error("Access forbidden, is your token valid? Did you specify the correct contest ID? " . htmlspecialchars($result));
}
if ($status < 200 || $status >= 300) {
    error("Unknown error while retrieving data from icpc.baylor.edu, status code: $status, $response");
}
curl_close($ch);

if (isset($_REQUEST['upload'])) {
    echo "Uploaded standings to icpc.baylor.edu (Response was: $response).<br/>";
    echo "Do not forget to certify the standings there - it maybe necessary to logout/login there to see the standings.";
    exit;
}

$json = dj_json_decode($response);
if ($json === null) {
    error("Error retrieving API data. API gave us: " . $response);
}

$participants = $DB->q('VALUE SELECT categoryid FROM team_category WHERE name=%s', 'Participants');
$teamrole = $DB->q('VALUE SELECT roleid FROM role WHERE role=%s', 'team');

$new_affils = array();
$new_teams = array();
$updated_teams = array();
foreach ($json['group'] as $group) {
    $siteName = $group['groupName'];
    foreach ($group['teams'] as $team) {
        $institutionName = $team['instName'];
        // Note: affiliations are not updated and not deleted even if all teams have canceled
        $affilid = $DB->q('MAYBEVALUE SELECT affilid FROM team_affiliation
                           WHERE name=%s', $institutionName);
        if (empty($affilid)) {
            $shortName = isset($team['instShortName']) ? $team['instShortName'] : $institutionName;
            $affilid = $DB->q('RETURNID INSERT INTO team_affiliation
                               (name, shortname, country) VALUES (%s, %s, %s)',
                              $institutionName,
                              $team['instShortName'],
                              $team['country']);
            $new_affils[] = $institutionName;
        }

        /*
         * FIXME: team members are behind a different API call and not important for now
        // collect team members
        $members_a = $mails_a = array();
        $members_json = $team['teamMembers']['teamMember'];
        foreach ($team['teamMember'] as $member) {
            // FIXME: include role (coach, contestant, other) here or somewhere else?
            $members_a[] = $member['firstName'] . " " . $member['lastName'];
            $mails_a[]   = $member['email'];
        }
        $members = implode("\n", $members_a);
        $mails = implode(",", $mails_a);
        */

        // Note: teams are not deleted but disabled depending on their status
        $id = $DB->q('MAYBEVALUE SELECT teamid FROM team
                      WHERE externalid=%i', $team['teamId']);
        $enabled = $team['status'] === 'ACCEPTED';
        if (empty($id)) {
            $id = $DB->q('RETURNID INSERT INTO team
                          (name, categoryid, affilid, enabled, members, comments, externalid, room)
                          VALUES (%s, %i, %i, %i, %s, %s, %i, %s)',
                         $team['teamName'], $participants, $affilid, $enabled, '',
                         "Status: " . $team['status'], $team['teamId'], $siteName);
            $username = sprintf("team%04d", $id);
            $userid = $DB->q('RETURNID INSERT INTO user (username, name, teamid, email)
                              VALUES (%s,%s,%i,%s)',
                             $username, $team['teamName'], $id, '');
            $DB->q('INSERT INTO userrole (userid, roleid) VALUES (%i,%i)', $userid, $teamrole);
            $new_teams[] = $team['teamName'];
        } else {
            $username = sprintf("team%04d", $id);
            $cnt = $DB->q('RETURNAFFECTED UPDATE team SET name=%s, categoryid=%i,
                           affilid=%i, enabled=%i, members=%s, comments=%s, room=%s
                           WHERE teamid=%i',
                          $team['teamName'], $participants, $affilid, $enabled, '',
                          "Status: " . $team['status'], $siteName, $id);
            $cnt += $DB->q('RETURNAFFECTED UPDATE user SET name=%s, email=%s
                            WHERE username=%s', $team['teamName'], '', $username);
            if ($cnt > 0) {
                $updated_teams[] = $team['teamName'];
            }
        }
    }
}

updated($new_affils, "team affiliation");
updated($new_teams, "team");
updated($updated_teams, "team", "updated");
