<?php
/**
 * View of one contest.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$id = (int)@$_GET['id'];

require('init.php');
$title = "Contest";

require(LIBWWWDIR . '/header.php');

if ( IS_ADMIN && !empty($_GET['cmd']) ):
	$cmd = $_GET['cmd'];

	echo "<h2>" . htmlspecialchars(ucfirst($cmd)) . " contest</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Contest ID:</td><td>";
		$row = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %s',
			$_GET['id']);
		echo addHidden('keydata[0][cid]', $row['cid']) .
			'c' . htmlspecialchars($row['cid']) .
			"</td></tr>\n";
	}

?>

<tr><td><label for="data_0__contestname_">Contest name:</label></td>
<td><?php echo addInput('data[0][contestname]', @$row['contestname'], 40, 255)?></td></tr>
<tr><td><label for="data_0__activatetime_">Activate time:</label></td>
<td><?php echo addInput('data[0][activatetime]', @$row['activatetime_string'], 20, 19)?> (yyyy-mm-dd hh:mm:ss <i>or</i> -hh:mm)</td></tr>

<tr><td><label for="data_0__starttime_">Start time:</label></td>
<td><?php echo addInput('data[0][starttime]', @$row['starttime'], 20, 19)?> (yyyy-mm-dd hh:mm:ss)</td></tr>

<tr><td><label for="data_0__freezetime_">Scoreboard freeze time:</label></td>
<td><?php echo addInput('data[0][freezetime]', @$row['freezetime_string'], 20, 19)?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__endtime_">End time:</label></td>
<td><?php echo addInput('data[0][endtime]', @$row['endtime_string'], 20, 19)?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__unfreezetime_">Scoreboard unfreeze time:</label></td>
<td><?php echo addInput('data[0][unfreezetime]', @$row['unfreezetime_string'], 20, 19)?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td>Enabled:</td><td>
<?php echo addRadioButton('data[0][enabled]', (!isset($row['enabled']) ||  $row['enabled']), 1)?> <label for="data_0__enabled_1">yes</label>
<?php echo addRadioButton('data[0][enabled]', ( isset($row['enabled']) && !$row['enabled']), 0)?> <label for="data_0__enabled_0">no</label></td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','contest') .
	addHidden('referrer', @$_GET['referrer'] . ( $cmd == 'edit'?(strstr(@$_GET['referrer'],'?') === FALSE?'?edited=1':'&edited=1'):'')) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel') .
	addEndForm();

require(LIBWWWDIR . '/footer.php');
exit;

endif;

if ( ! $id ) error("Missing or invalid contest id");

if ( isset($_GET['edited']) ) {

	echo addForm('refresh_cache.php', 'get') .
            msgbox (
                "Warning: Refresh scoreboard cache",
		"If the contest start time was changed, it may be necessary to recalculate any cached scoreboards.<br /><br />" .
		addSubmit('recalculate caches now') 
		) .
		addEndForm();

}


$data = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $id);

echo "<h1>Contest: ".htmlspecialchars($data['contestname'])."</h1>\n\n";

if ( $cid == $data['cid'] ) {
	echo "<p><em>This is the active contest.</em></p>\n\n";
}
if ( !$data['enabled'] ) {
	echo "<p><em>This contest is disabled.</em></p>\n\n";
}

echo "<table>\n";
echo '<tr><td scope="row">CID:</td><td>c' .
	(int)$data['cid'] . "</td></tr>\n";
echo '<tr><td scope="row">Name:</td><td>' .
	htmlspecialchars($data['contestname']) .
	"</td></tr>\n";
echo '<tr><td scope="row">Activate time:</td><td>' .
	htmlspecialchars(@$data['activatetime_string']) .
	"</td></tr>\n";
echo '<tr><td scope="row">Start time:</td><td>' .
	htmlspecialchars($data['starttime']) .
	"</td></tr>\n";
echo '<tr><td scope="row">Scoreboard freeze:</td><td>' .
	(empty($data['freezetime_string']) ? "-" : htmlspecialchars(@$data['freezetime_string'])) .
	"</td></tr>\n";
echo '<tr><td scope="row">End time:</td><td>' .
	htmlspecialchars($data['endtime_string']) .
	"</td></tr>\n";
echo '<tr><td scope="row">Scoreboard unfreeze:</td><td>' .
	(empty($data['unfreezetime_string']) ? "-" : htmlspecialchars(@$data['unfreezetime_string'])) .
	"</td></tr>\n";
echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		editLink('contest',$data['cid']) . "\n" .
		delLink('contest','cid',$data['cid']) ."</p>\n\n";
}


echo "<h3>Removed intervals</h3>\n\n";

$removals = $DB->q('TABLE SELECT * FROM removed_interval WHERE cid = %i ORDER BY starttime', $id);

if ( count($removals) == 0 ) {
	echo "<p class=\"nodata\">None.</p>\n\n";
} else {
	echo "<table>\n";
	echo "<tr><th>from</th><td></td><th>to</th><td></td><th>duration</th></tr>\n";
	foreach ( $removals as $row ) {
		echo "<tr><td title=\"" . htmlspecialchars($row['starttime']) . "\">" .
		     printtime($row['starttime']) . "</td><td>&rarr;</td>" . 
		     "<td title=\"" . htmlspecialchars($row['endtime']) . "\">" .
		     printtime($row['endtime']) . "</td><td></td><td>( " .
		     printtimediff(strtotime($row['starttime']), strtotime($row['endtime'])) . " )</td>" .
		     "<td><a href=\"removed_intervals.php?intervalid=" . (int)$row['intervalid'] .
		     "&amp;cmd=delete\" onclick=\"return confirm('Really delete interval?');\">" .
		     "<img src=\"../images/delete.png\" alt=\"delete\" \"delete removed interval\" " .
		     "class=\"picto\" /></a></td>" .
		     "</tr>\n";
	}
	echo "</table>\n\n";

	if ( IS_ADMIN ) {
		echo "<p>" .
		     "<a href=\"removed_intervals.php?cid=" . (int)$id . "&amp;cmd=add\">" .
		     "<img src=\"../images/add.png\" alt=\"add\" title=\"add new removed interval\" " .
		     "class=\"picto\" /></a> " .
		     "<a href=\"removed_intervals.php?cid=" . (int)$id . "&amp;cmd=edit\">" .
		     "<img src=\"../images/edit-multi.png\" alt=\"edit\" title=\"edit removed intervals\" " .
		     "class=\"picto\" /></a>";
	}

}

require(LIBWWWDIR . '/footer.php');
