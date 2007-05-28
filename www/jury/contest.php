<?php
/**
 * View of one contest.
 *
 * $Id$
 */

$id = (int)@$_GET['id'];

require('init.php');
$title = "Contest: " .htmlspecialchars(@$id);

require('../header.php');

if ( IS_ADMIN && !empty($_GET['cmd']) ):
	$cmd = $_GET['cmd'];

	require('../forms.php');
	
	echo "<h2>" . ucfirst($cmd) . " contest</h2>\n\n";

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
<td><?=addInput('data[0][contestname]', @$row['contestname'], 40, 255)?></td></tr>

<tr><td><label for="data_0__starttime_">Start time:</label></td>
<td><?=addInput('data[0][starttime]', @$row['starttime'], 20, 19)?> (yyyy-mm-dd hh:mm:ss)</td></tr>

<tr><td><label for="data_0__endtime_">End time:</label></td>
<td><?=addInput('data[0][endtime]', @$row['endtime'], 20, 19)?> (yyyy-mm-dd hh:mm:ss)</td></tr>

<tr><td><label for="data_0__lastscoreupdate_">Last score update:</label></td>
<td><?=addInput('data[0][lastscoreupdate]', @$row['lastscoreupdate'], 20, 19)?> (yyyy-mm-dd hh:mm:ss)</td></tr>

<tr><td><label for="data_0__unfreezetime_">Unfreeze time:</label></td>
<td><?=addInput('data[0][unfreezetime]', @$row['unfreezetime'], 20, 19)?> (yyyy-mm-dd hh:mm:ss)</td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','contest') .
	addSubmit('Save') .
	addEndForm();

require('../footer.php');
exit;

endif;

if ( ! $id ) error("Missing or invalid contest id");


$data = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $id);

echo "<h1>Contest: ".htmlentities($data['contestname'])."</h1>\n\n";

if ( getCurContest() == $data['cid'] ) {
	echo "<p><em>This is the current contest.</em></p>\n\n";
}

echo "<table>\n";
echo '<tr><td>CID:</td><td>c' . (int)$data['cid'] . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' . htmlentities($data['contestname']) . "</td></tr>\n";
echo '<tr><td>Starttime:</td><td>' . htmlspecialchars($data['starttime']) . "</td></tr>\n";
echo '<tr><td>Last scoreboard update:</td><td>' . (empty($data['lastscoreupdate']) ? "-" : htmlspecialchars(@$data['lastscoreupdate'])) . "</td></tr>\n";
echo '<tr><td>Endtime:</td><td>' . htmlspecialchars($data['endtime']) . "</td></tr>\n";
echo '<tr><td>Scoreboard unfreeze:</td><td>' . (empty($data['unfreezetime']) ? "-" : htmlspecialchars(@$data['unfreezetime'])) . "</td></tr>\n";
echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" . 
		editLink('contest',$data['cid']) . " " .
		delLink('contest','cid',$data['cid']) ."</p>\n\n";
}

require('../footer.php');
