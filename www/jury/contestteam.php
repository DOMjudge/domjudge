<?php
/**
 * View/edit contests of a problem
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$cid = (int)@$_REQUEST['cid'];

$contest = $DB->q('MAYBETUPLE SELECT cid, contestname
		  FROM contest WHERE cid = %i', $cid);

if ( ! $contest ) error("Missing or invalid contest id");

// We may need to re-update the team data, so make it a function.
function get_contestteam_data()
{
	global $DB, $data, $cid;

	$data = $DB->q('KEYTABLE SELECT teamid AS ARRAYKEY, name
			FROM gewis_contestteam
			NATURAL JOIN team
			WHERE cid = %i ORDER BY teamid', $cid);
}
get_contestteam_data();

$title = 'Teams for contest c'.htmlspecialchars(@$cid);

require(LIBWWWDIR . '/header.php');

echo "<h1>" . $title ."</h1>\n\n";

$result = '';
if ( isset($_POST['cid']) && IS_ADMIN ) {
	if ( isset($_POST['teamid']) ) {
		$DB->q("INSERT INTO gewis_contestteam (cid, teamid) VALUES (%i, %i)", $cid, $_POST['teamid']);
		$contestname = $DB->q("VALUE SELECT contestname FROM contest WHERE cid = %i", $cid);
		$teamname = $DB->q("VALUE SELECT name FROM team WHERE teamid = %i", $_POST['teamid']);
		$result .= "<li>Added team ${teanmae} (t${_POST['teamid']})</li>\n";
		auditlog('contest', $cid, 'added team', "team t{$_POST['teamid']}");
	}
}
if ( !empty($result) ) {
	echo "<ul>\n$result</ul>\n\n";

	// Reload testcase data after updates
	get_contestteam_data();
}

echo "<p><a href=\"contest.php?id=" . urlencode($cid) . "\">back to contest c" .
	htmlspecialchars($cid) . "</a></p>\n\n";

if ( count($data)==0 ) {
	echo "<p class=\"nodata\">No team(s) yet.</p>\n";
} else {
	?>
<table class="list">
<thead><tr>
<th scope="col">ID</th><th scope="col">name</th><th></th>
</tr></thead>
<tbody>
<?php
}

foreach( $data as $teamid => $row ) {
	$link = '<a href="team.php?id=' . urlencode($teamid) . '">';
	echo "<tr>";
	echo "<td class=\"tid\">" . $link .
	    "t" . htmlspecialchars($teamid) ."</a></td>" .
	    "<td class=\"name\">" . $link . htmlspecialchars($row["name"]) . "</a></td>";
		if ( IS_ADMIN ) {
			echo "<td><a href=\"delete.php?table=gewis_contestteam&amp;teamid=$teamid&amp;cid=$cid&amp;referrer=" .
			    urlencode('contestteam.php?cid='.$cid) . "\">" .
			    "<img src=\"../images/delete.png\" alt=\"delete\"" .
			    " title=\"remove this team from this contest\" class=\"picto\" /></a></td>";
		} else {
			echo "<td></td>";
		}
	echo "</tr>\n";
}

if ( count($data)!=0 ) echo "</tbody>\n</table>\n";

if ( IS_ADMIN ) {
	echo addForm($pagename, 'post', null, 'multipart/form-data') .
	     addHidden('cid', $cid);

	$tmap = $DB->q("KEYVALUETABLE SELECT t.teamid, t.name
		    FROM team t
		    LEFT JOIN gewis_contestteam g ON t.teamid = g.teamid AND g.cid = %i
		    WHERE g.cid IS NULL
		    ORDER BY teamid", $cid);
	if (!empty($tmap)) {
		?>
		<h3>Add team</h3>
		<table>
			<tr>
				<td>Team:</td>
				<td>
					<?php
					foreach ($tmap as $teamid => $tname) {
						$tmap[$teamid] = "t$teamid: $tname";
					}
					echo addSelect('teamid', $tmap, null, true);
					?>
				</td>
			</tr>
		</table>
		<?php

		echo "<br />" . addSubmit('Add team') . addEndForm();
	}
}

require(LIBWWWDIR . '/footer.php');
