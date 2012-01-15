<?php
/**
 * View team details
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

require('init.php');

$id = @$_REQUEST['id'];
$title = 'Team '.htmlspecialchars(@$id);

if ( ! preg_match('/^' . IDENTIFIER_CHARS . '*$/', $id) ) error("Invalid team id");

if ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.$pagename.'?id='.urlencode($id).
		(isset($_GET['restrict'])?'&restrict='.urlencode($_GET['restrict']):'');
}

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

if ( IS_ADMIN && !empty($cmd) ):

	echo "<h2>" . htmlspecialchars(ucfirst($cmd)) . " team</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Login:</td><td class=\"teamid\">";
		$row = $DB->q('TUPLE SELECT * FROM team WHERE login = %s',
			$_GET['id']);
		echo addHidden('keydata[0][login]', $row['login']);
		echo htmlspecialchars($row['login']);
	} else {
		echo "<tr><td><label for=\"data_0__login_\">Login:</label></td><td class=\"teamid\">";
		echo addInput('data[0][login]', null, 8, 15);
	}
	echo "</td></tr>\n";

?>
<tr><td><label for="data_0__name_">Team name:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 35, 255)?></td></tr>
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
<tr><td><label for="data_0__authtoken_">Auth token:</label></td>
<td><?php echo addInput('data[0][authtoken]', @$row['authtoken'], 35, 255)?></td></tr>
<tr><td><label for="data_0__room_">Location:</label></td>
<td><?php echo addInput('data[0][room]', @$row['room'], 10, 15)?></td></tr>
<tr><td><label for="data_0__comments_">Comments:</label></td>
<td><?php echo addTextArea('data[0][comments]', @$row['comments'])?></td></tr>
<tr><td><label for="data_0__enabled_">Enabled:</label></td>
<td><?php echo addRadioButton('data[0][enabled]', (!isset($row['']) || $row['enabled']), 1)?> <label for="data_0__enabled_1">yes</label>
<?php echo addRadioButton('data[0][enabled]', (isset($row['enabled']) && !$row['enabled']), 0)?> <label for="data_0__enabled_0">no</label></td></tr>
</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','team') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel') .
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

$row = $DB->q('MAYBETUPLE SELECT t.*, a.country, c.name AS catname, a.name AS affname
               FROM team t
               LEFT JOIN team_category c USING (categoryid)
               LEFT JOIN team_affiliation a ON (t.affilid = a.affilid)
               WHERE login = %s', $id);

if ( ! $row ) error("Missing or invalid team id");

$affillogo   = "../images/affiliations/" . urlencode($row['affilid']) . ".png";
$countryflag = "../images/countries/"    . urlencode($row['country']) . ".png";
$teamimage   = "../images/teams/"        . urlencode($row['login'])   . ".jpg";

echo "<h1>Team ".htmlspecialchars($row['name'])."</h1>\n\n";

if ( $row['enabled'] != 1 ) {
	echo "<p><em>Team is disabled</em></p>\n\n";
}

?>

<div class="col1"><table>
<tr><td scope="row">Login:     </td><td class="teamid"><?php echo $row['login']?></td></tr>
<tr><td scope="row">Name:      </td><td><?php echo htmlspecialchars($row['name'])?></td></tr>
<tr><td scope="row">Host:</td><td><?php echo
	(@$row['hostname'] ? printhost($row['hostname'], TRUE):'') ?></td></tr>
<?php if (!empty($row['room'])): ?>
<tr><td scope="row">Location:</td><td><?php echo htmlspecialchars($row['room'])?></td></tr>
<?php endif; ?>
</table></div>

<div class="col2"><table>
<?php

echo '<tr><td scope="row">Category:</td><td><a href="team_category.php?id=' .
	urlencode($row['categoryid']) . '">' .
	htmlspecialchars($row['catname']) . "</a></td></tr>\n";

if ( !empty($row['affilid']) ) {
	echo '<tr><td scope="row">Affiliation:</td><td>';
	if ( is_readable($affillogo) ) {
		echo '<img src="' . $affillogo . '" alt="' .
			htmlspecialchars($row['affilid']) . '" /> ';
	} else {
		echo htmlspecialchars($row['affilid']) . ' - ';
	}
	echo '<a href="team_affiliation.php?id=' . urlencode($row['affilid']) . '">' .
		htmlspecialchars($row['affname']) . "</a></td></tr>\n";
}
if ( !empty($row['country']) ) {
	echo '<tr><td scope="row">Country:</td><td>';
	if ( is_readable($countryflag) ) {
		echo '<img src="' . $countryflag . '" alt="' .
			htmlspecialchars($row['country']) . '" /> ';
	}
	echo htmlspecialchars($row['country']) . "</td></tr>\n";
}
if ( !empty($row['members']) ) {
	echo '<tr><td scope="row">Members:   </td><td>' .
		nl2br(htmlspecialchars($row['members'])) . "</td></tr>\n";
}
if ( !empty($row['comments']) ) {
	echo '<tr><td scope="row">Comments:</td><td>' .
		nl2br(htmlspecialchars($row['comments'])) . "</td></tr>\n";
}
echo "</table></div>\n";

if ( IS_ADMIN ) {
	echo "<p class=\"nomorecol\">" .
		editLink('team', $id). "\n" .
		delLink('team','login',$id) .
		"</p>\n\n";
}

echo rejudgeForm('team', $id) . "\n\n";

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
putSubmissions($cdata, $restrictions);

require(LIBWWWDIR . '/footer.php');
