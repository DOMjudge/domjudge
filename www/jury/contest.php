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
		$row = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i',
			$_GET['id']);
		echo addHidden('keydata[0][cid]', $row['cid']) .
			'c' . htmlspecialchars($row['cid']) .
			"</td></tr>\n";
	}

?>

<tr><td><label for="data_0__contestname_">Contest name:</label></td>
<td><?php echo addInput('data[0][contestname]', @$row['contestname'], 40, 255)?></td></tr>
<tr><td><label for="data_0__activatetime_string_">Activate time:</label></td>
<td><?php echo addInput('data[0][activatetime_string]', @$row['activatetime_string'], 20, 19)?> (yyyy-mm-dd hh:mm:ss <i>or</i> -hh:mm)</td></tr>

<tr><td><label for="data_0__starttime_">Start time:</label></td>
<td><?php echo addInput('data[0][starttime]', @$row['starttime'], 20, 19)?> (yyyy-mm-dd hh:mm:ss)</td></tr>

<tr><td><label for="data_0__freezetime_string_">Scoreboard freeze time:</label></td>
<td><?php echo addInput('data[0][freezetime_string]', @$row['freezetime_string'], 20, 19)?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__endtime_string_">End time:</label></td>
<td><?php echo addInput('data[0][endtime_string]', @$row['endtime_string'], 20, 19)?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__unfreezetime_string_">Scoreboard unfreeze time:</label></td>
<td><?php echo addInput('data[0][unfreezetime_string]', @$row['unfreezetime_string'], 20, 19)?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

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
if ( !empty($data['finalizetime']) ) {
	echo "<p><em>This contest is final.</em></p>\n\n";
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

if ( !empty($data['finalizetime']) ) {
	echo "<h3>Finalized</h3>\n\n";
	echo "<table>\n" .
	     "<tr><td>Finalized at:</td><td>" . htmlspecialchars($data['finalizetime']) . "</td></tr>\n" .
             "<tr><td>B:</td><td>" . htmlspecialchars($data['b']) . "</td></tr>\n" .
	     "</table>\n<p>Comment:</p>\n<pre class=\"output_text\">" . htmlspecialchars($data['finalizecomment']) . "</pre>\n";

	echo "<p><a href=\"finalize.php?id=" . (int)$data['cid'] . "\">update finalization</a></p>\n\n";
} else {
	echo "<p><a href=\"finalize.php?id=" . (int)$data['cid'] . "\">finalize this contest</a></p>\n\n";
}

echo "<h3>Removed intervals</h3>\n\n";

$removals = $DB->q('TABLE SELECT * FROM removed_interval
                    WHERE cid = %i ORDER BY starttime', $id);

if ( count($removals)==0 && ! IS_ADMIN ) {
	echo "<p class=\"nodata\">None.</p>\n\n";
} else {
	if ( IS_ADMIN ) {
		echo addForm('removed_intervals.php') .
		    addHidden('cmd', 'add') . addHidden('cid', $id);
	}
	echo "<table>\n";
	echo "<tr><th>from</th><td></td><th>to</th><td></td><th>duration</th></tr>\n";
	foreach ( $removals as $row ) {
		echo "<tr><td title=\"" . htmlspecialchars($row['starttime']) . "\">" .
		     printtime($row['starttime']) . "</td><td>&rarr;</td>" .
		     "<td title=\"" . htmlspecialchars($row['endtime']) . "\">" .
		     printtime($row['endtime']) . "</td><td></td><td>( " .
		     printtimediff(strtotime($row['starttime']), strtotime($row['endtime'])) . " )</td>" .
		     "<td><a href=\"removed_intervals.php?cmd=delete&amp;cid=$id&amp;intervalid=" .
		     (int)$row['intervalid'] . "\" onclick=\"return confirm('Really delete interval?');\">" .
		     "<img src=\"../images/delete.png\" alt=\"delete\" class=\"picto\" /></a></td>" .
		     "</tr>\n";
	}
	if ( IS_ADMIN ) {
		echo "<tr><td>" . addInput('starttime', null, 16, 50) . "</td><td>&rarr;</td>" .
		         "<td>" . addInput('endtime',   null, 16, 50) . "</td><td></td>" .
		         "<td>" . addSubmit('Add') . "</td><td>(yyyy-mm-dd hh:mm:ss)</td></tr>\n";
	}
	echo "</table>\n\n";
	if ( IS_ADMIN ) echo addEndForm();
}

require(LIBWWWDIR . '/footer.php');
