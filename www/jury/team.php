<?php
/**
 * View team details
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();
$title = ucfirst((empty($_GET['cmd']) ? '' : htmlspecialchars($_GET['cmd']) . ' ') .
                 'team' . ($id ? ' t'.htmlspecialchars(@$id) : ''));

if ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.$pagename.'?id='.urlencode($id).
		(isset($_GET['restrict'])?'&restrict='.urlencode($_GET['restrict']):'');
}

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

if ( !empty($cmd) ):

	requireAdmin();

	echo "<h2>$title</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		$row = $DB->q('MAYBETUPLE SELECT * FROM team WHERE teamid = %i', $id);
		if ( !$row ) error("Missing or invalid team id");

		echo "<tr><td>ID:</td><td>" .
			addHidden('keydata[0][teamid]', $row['teamid']) .
			"t" . htmlspecialchars($row['teamid']) . "</td></tr>\n";
	}

?>
<tr><td><label for="data_0__name_">Team name:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 35, 255, 'required')?></td></tr>
<tr><td><label for="data_0__categoryid_">Category:</label></td>
<td><?php
$cmap = $DB->q("KEYVALUETABLE SELECT categoryid,name FROM team_category ORDER BY categoryid");
echo addSelect('data[0][categoryid]', $cmap, @$row['categoryid'], true);
?>
</td></tr>
<tr><td><label for="data_0__members_">Members:</label></td>
<td><?php echo addTextArea('data[0][members]', @$row['members'], 40, 3)?></td></tr>
<tr><td><label for="data_0__affilid_">Affiliation:</label></td>
<td><?php
$amap = $DB->q("KEYVALUETABLE SELECT affilid,name FROM team_affiliation ORDER BY name");
$amap[''] = 'none';
echo addSelect('data[0][affilid]', $amap, @$row['affilid'], true);
?>
</td></tr>
<tr><td><label for="data_0__room_">Location:</label></td>
<td><?php echo addInput('data[0][room]', @$row['room'], 10, 15)?></td></tr>
<tr><td><label for="data_0__comments_">Comments:</label></td>
<td><?php echo addTextArea('data[0][comments]', @$row['comments'])?></td></tr>

<!-- contest selection -->
<tr><td>Contests:</td>
	<td><?php
		$contests = $DB->q("TABLE SELECT contest.cid,contestname,max(gewis_contestteam.teamid=%s) AS incontest
				FROM contest
				LEFT JOIN gewis_contestteam ON gewis_contestteam.cid = contest.cid
				GROUP BY contest.cid", @$row['teamid']);
		$i=0;
		foreach ($contests as $contest) {
			echo "<label>";
			echo addCheckbox("data[0][mapping][items][$i]", $contest['incontest']==1, $contest['cid']);
			echo $contest['contestname'] . " (c${contest['cid']})</label><br/>";
			$i++;
		}
		?>
	</td></tr>

<tr><td>Enabled:</td>
<td><?php echo addRadioButton('data[0][enabled]', (!isset($row['']) || $row['enabled']), 1)?> <label for="data_0__enabled_1">yes</label>
<?php echo addRadioButton('data[0][enabled]', (isset($row['enabled']) && !$row['enabled']), 0)?> <label for="data_0__enabled_0">no</label></td></tr>
</table>

<?php
echo addHidden('data[0][mapping][fk][0]', 'teamid') .
     addHidden('data[0][mapping][fk][1]', 'cid') .
     addHidden('data[0][mapping][table]', 'gewis_contestteam');
echo addHidden('cmd', $cmd) .
     addHidden('table','team') .
     addHidden('referrer', @$_GET['referrer'] . ( $cmd == 'edit'?(strstr(@$_GET['referrer'],'?') === FALSE?'?edited=1':'&edited=1'):'')) .
     addSubmit('Save') .
     addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
     addEndForm();

require(LIBWWWDIR . '/footer.php');
exit;

endif;

/* optional restriction of submissions list to specific problem, language, etc. */
$restrictions = array();
if ( isset($_GET['restrict']) ) {
	list($key, $value) = explode(":",$_GET['restrict'],2);
	$restrictions[$key] = $value;
}

$row = $DB->q('MAYBETUPLE SELECT t.*, a.country, c.name AS catname, a.shortname AS affshortname, a.name AS affname
               FROM team t
               LEFT JOIN team_category c USING (categoryid)
               LEFT JOIN team_affiliation a ON (t.affilid = a.affilid)
               WHERE teamid = %i', $id);

if ( !$row ) error("Invalid team identifier");

if ( isset($_GET['edited']) ) {

	echo addForm('refresh_cache.php') .
	     msgbox (
		     "Warning: Refresh scoreboard cache",
		     "If the membership of a team in a contest was changed, it may be necessary to recalculate any cached scoreboards.<br /><br />" .
		     addSubmit('recalculate caches now', 'refresh')
	     ) .
	     addEndForm();

}

$users = $DB->q('TABLE SELECT userid,username FROM user WHERE teamid = %i', $id);

$affillogo   = "../images/affiliations/" . urlencode($row['affilid']) . ".png";
$countryflag = "../images/countries/"    . urlencode($row['country']) . ".png";
$teamimage   = "../images/teams/"        . urlencode($row['teamid'])  . ".jpg";

echo "<h1>Team ".htmlspecialchars($row['name'])."</h1>\n\n";

if ( $row['enabled'] != 1 ) {
	echo "<p><em>Team is disabled</em></p>\n\n";
}

?>

<div class="col1"><table>
<tr><td>ID:        </td><td>t<?php echo htmlspecialchars($row['teamid'])?></td></tr>
<tr><td>Name:      </td><td><?php echo htmlspecialchars($row['name'])?></td></tr>
<tr><td>Host:</td><td><?php echo
	(@$row['hostname'] ? printhost($row['hostname'], TRUE):'') ?></td></tr>
<?php if (!empty($row['room'])): ?>
<tr><td>Location:</td><td><?php echo htmlspecialchars($row['room'])?></td></tr>
<?php endif; ?>
<tr><td>User:</td><td><?php
if ( count($users) ) {
	foreach($users as $user) {
		echo "<a href=\"user.php?id=" . urlencode($user['userid']) . "\">" . htmlspecialchars($user['username']) . "</a> ";
	}
} else {
	echo "<a href=\"user.php?cmd=add&amp;forteam=" . urlencode($row['teamid']) . "\"><small>(add)</small></a>";
}
?></td></tr>
</table></div>

<div class="col2"><table>
<?php

echo '<tr><td>Category:</td><td><a href="team_category.php?id=' .
	urlencode($row['categoryid']) . '">' .
	htmlspecialchars($row['catname']) . "</a></td></tr>\n";

if ( !empty($row['affilid']) ) {
	echo '<tr><td>Affiliation:</td><td>';
	if ( is_readable($affillogo) ) {
		echo '<img src="' . $affillogo . '" alt="' .
			htmlspecialchars($row['affshortname']) . '" /> ';
	}
	echo '<a href="team_affiliation.php?id=' . urlencode($row['affilid']) . '">' .
		htmlspecialchars($row['affname']) . "</a></td></tr>\n";
}
if ( !empty($row['country']) ) {
	echo '<tr><td>Country:</td><td>';
	if ( is_readable($countryflag) ) {
		echo '<img src="' . $countryflag . '" alt="' .
			htmlspecialchars($row['country']) . '" /> ';
	}
	echo htmlspecialchars($row['country']) . "</td></tr>\n";
}
if ( !empty($row['members']) ) {
	echo '<tr><td>Members:   </td><td>' .
		nl2br(htmlspecialchars($row['members'])) . "</td></tr>\n";
}
if ( !empty($row['comments']) ) {
	echo '<tr><td>Comments:</td><td>' .
		nl2br(htmlspecialchars($row['comments'])) . "</td></tr>\n";
}
echo "</table></div>\n";

if ( IS_ADMIN ) {
	echo "<p class=\"nomorecol\">" .
		editLink('team', $id). "\n" .
		delLink('team','teamid',$id) .
		"</p>\n\n";
}

echo rejudgeForm('team', $id) . "\n\n";

echo "<h3>Contests</h3>\n\n";

$res = $DB->q('TABLE SELECT contest.*
	       FROM contest
	       INNER JOIN gewis_contestteam USING (cid)
	       WHERE gewis_contestteam.teamid = %i
	       ORDER BY starttime DESC', $id);

if( count($res) == 0 ) {
	echo "<p class=\"nodata\">No contests defined</p>\n\n";
} else {
	$times = array ('activate','start','freeze','end','unfreeze');
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\" class=\"sorttable_numeric\">CID</th>";
	foreach($times as $time) echo "<th scope=\"col\">$time</th>";
	echo "<th scope=\"col\">name</th></tr>\n</thead>\n<tbody>\n";

	$iseven = false;
	foreach($res as $row) {

		$link = '<a href="contest.php?id=' . urlencode($row['cid']) . '">';

		echo '<tr class="' .
		     ( $iseven ? 'roweven': 'rowodd' ) .
		     (!$row['enabled']    ? ' disabled' :'') . '">' .
		     "<td class=\"tdright\">" . $link .
		     "c" . (int)$row['cid'] . "</a></td>\n";
		foreach ($times as $time) {
			echo "<td title=\"".printtime(@$row[$time. 'time'],'%Y-%m-%d %H:%M') . "\">" .
			     $link . ( isset($row[$time.'time']) ?
					printtime($row[$time.'time']) : '-' ) . "</a></td>\n";
		}
		echo "<td>" . $link . htmlspecialchars($row['contestname']) . "</a></td>\n";
		$iseven = ! $iseven;

		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

echo "<h3>Score</h3>\n\n";

putTeamRow($cdata,array($id));

echo '<h3>Submissions';
if ( isset($key) ) {
	$keystr = "";
	switch ( $key ) {
	case 'probid':    $keystr = "problem";   break;
	case 'langid':    $keystr = "language";  break;
	case 'judgehost': $keystr = "judgehost"; break;
	default:          error("Restriction on $key not allowed.");
	}
	echo ' for ' . htmlspecialchars($keystr) . ': ' . htmlspecialchars($value);
}
echo "</h3>\n\n";

$restrictions['teamid'] = $id;
putSubmissions($cdatas, $restrictions, 0, null, true);

require(LIBWWWDIR . '/footer.php');
