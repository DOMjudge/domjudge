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

if ( !empty($_GET['cmd']) ):

	requireAdmin();

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

$pattern_datetime  = "\d\d\d\d\-\d\d\-\d\d\ \d\d:\d\d:\d\d";
$pattern_offset    = "\d?\d:\d\d";
$pattern_dateorneg = "($pattern_datetime|\-$pattern_offset)";
$pattern_dateorpos = "($pattern_datetime|\+$pattern_offset)";
?>

<tr><td><label for="data_0__contestname_">Contest name:</label></td>
<td><?php echo addInput('data[0][contestname]', @$row['contestname'], 40, 255, 'required')?></td></tr>
<tr><td><label for="data_0__activatetime_string_">Activate time:</label></td>
<td><?php echo addInput('data[0][activatetime_string]', @$row['activatetime_string'], 20, 19, 'required pattern="' . $pattern_dateorneg . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> -hh:mm)</td></tr>

<tr><td><label for="data_0__starttime_">Start time:</label></td>
<td><?php echo addInput('data[0][starttime]', strftime('%Y-%m-%d %H:%M:%S',@$row['starttime']), 20, 19, 'required pattern="' . $pattern_datetime . '"')?> (yyyy-mm-dd hh:mm:ss)</td></tr>

<tr><td><label for="data_0__freezetime_string_">Scoreboard freeze time:</label></td>
<td><?php echo addInput('data[0][freezetime_string]', @$row['freezetime_string'], 20, 19, 'pattern="' . $pattern_dateorpos . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__endtime_string_">End time:</label></td>
<td><?php echo addInput('data[0][endtime_string]', @$row['endtime_string'], 20, 19, 'required pattern="' . $pattern_dateorpos . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__unfreezetime_string_">Scoreboard unfreeze time:</label></td>
<td><?php echo addInput('data[0][unfreezetime_string]', @$row['unfreezetime_string'], 20, 19, 'pattern="' . $pattern_dateorpos . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td>Enabled:</td><td>
<?php echo addRadioButton('data[0][enabled]', (!isset($row['enabled']) ||  $row['enabled']), 1)?> <label for="data_0__enabled_1">yes</label>
<?php echo addRadioButton('data[0][enabled]', ( isset($row['enabled']) && !$row['enabled']), 0)?> <label for="data_0__enabled_0">no</label></td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','contest') .
	addHidden('referrer', @$_GET['referrer'] . ( $cmd == 'edit'?(strstr(@$_GET['referrer'],'?') === FALSE?'?edited=1':'&edited=1'):'')) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel', null, true, 'formnovalidate' . (isset($_GET['referrer']) ? ' formaction="' . htmlspecialchars($_GET['referrer']) . '"':'')) .
	addEndForm();

require(LIBWWWDIR . '/footer.php');
exit;

endif;

if ( ! $id ) error("Missing or invalid contest id");

if ( isset($_GET['edited']) ) {

	echo addForm('refresh_cache.php') .
            msgbox (
                "Warning: Refresh scoreboard cache",
		"If the contest start time was changed, it may be necessary to recalculate any cached scoreboards.<br /><br />" .
		addSubmit('recalculate caches now', 'refresh')
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
echo '<tr><td>CID:</td><td>c' .
	(int)$data['cid'] . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' .
	htmlspecialchars($data['contestname']) .
	"</td></tr>\n";
echo '<tr><td>Activate time:</td><td>' .
	htmlspecialchars(@$data['activatetime_string']) .
	"</td></tr>\n";
echo '<tr><td>Start time:</td><td>' .
	htmlspecialchars($data['starttime']) .
	"</td></tr>\n";
echo '<tr><td>Scoreboard freeze:</td><td>' .
	(empty($data['freezetime_string']) ? "-" : htmlspecialchars(@$data['freezetime_string'])) .
	"</td></tr>\n";
echo '<tr><td>End time:</td><td>' .
	htmlspecialchars($data['endtime_string']) .
	"</td></tr>\n";
echo '<tr><td>Scoreboard unfreeze:</td><td>' .
	(empty($data['unfreezetime_string']) ? "-" : htmlspecialchars(@$data['unfreezetime_string'])) .
	"</td></tr>\n";
echo "</table>\n\n";

if ( IS_ADMIN ) {
	if ( $cid == $data['cid'] ) {
		echo "<p>". rejudgeForm('contest', $data['cid']) . "</p>\n\n";
	}
	echo "<p>" .
		editLink('contest',$data['cid']) . "\n" .
		delLink('contest','cid',$data['cid']) ."</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
