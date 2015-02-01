<?php
/**
 * View of one contest.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();
$title = ucfirst((empty($_GET['cmd']) ? '' : htmlspecialchars($_GET['cmd']) . ' ') .
                 'contest' . ($id ? ' c'.htmlspecialchars(@$id) : ''));

$jscolor=true;

require(LIBWWWDIR . '/header.php');

if ( !empty($_GET['cmd']) ):

	requireAdmin();

	$cmd = $_GET['cmd'];

	echo "<h2>$title</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		$row = $DB->q('MAYBETUPLE SELECT * FROM contest WHERE cid = %s', $id);
		if ( !$row ) error("Missing or invalid contest id");

		echo "<tr><td>Contest ID:</td><td>" .
			addHidden('keydata[0][cid]', $row['cid']) .
			'c' . htmlspecialchars($row['cid']) .
			"</td></tr>\n";
	}

$pattern_datetime  = "\d\d\d\d\-\d\d\-\d\d\ \d\d:\d\d:\d\d";
$pattern_offset    = "\d?\d:\d\d";
$pattern_dateorneg = "($pattern_datetime|\-$pattern_offset)";
$pattern_dateorpos = "($pattern_datetime|\+$pattern_offset)";
?>

<tr><td><label for="data_0__shortname_">Short name:</label></td>
<td><?php echo addInput('data[0][shortname]', @$row['shortname'], 40, 10, 'required')?></td></tr>
<tr><td><label for="data_0__contestname_">Contest name:</label></td>
<td><?php echo addInput('data[0][contestname]', @$row['contestname'], 40, 255, 'required')?></td></tr>
<tr><td><label for="data_0__activatetime_string_">Activate time:</label></td>
<td><?php echo addInput('data[0][activatetime_string]', @$row['activatetime_string'], 20, 19, 'required pattern="' . $pattern_dateorneg . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> -hh:mm)</td></tr>

<tr><td><label for="data_0__starttime_string_">Start time:</label></td>
<td><?php echo addInput('data[0][starttime_string]', @$row['starttime_string'], 20, 19, 'required pattern="' . $pattern_datetime . '"')?> (yyyy-mm-dd hh:mm:ss)</td></tr>

<tr><td><label for="data_0__freezetime_string_">Scoreboard freeze time:</label></td>
<td><?php echo addInput('data[0][freezetime_string]', @$row['freezetime_string'], 20, 19, 'pattern="' . $pattern_dateorpos . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__endtime_string_">End time:</label></td>
<td><?php echo addInput('data[0][endtime_string]', @$row['endtime_string'], 20, 19, 'required pattern="' . $pattern_dateorpos . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__unfreezetime_string_">Scoreboard unfreeze time:</label></td>
<td><?php echo addInput('data[0][unfreezetime_string]', @$row['unfreezetime_string'], 20, 19, 'pattern="' . $pattern_dateorpos . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td><label for="data_0__deactivatetime_string_">Deactivate time:</label></td>
<td><?php echo addInput('data[0][deactivatetime_string]', @$row['deactivatetime_string'], 20, 19, 'required pattern="' . $pattern_dateorpos . '"')?> (yyyy-mm-dd hh:mm:ss <i>or</i> +hh:mm)</td></tr>

<tr><td>Process balloons:</td><td>
<?php echo addRadioButton('data[0][process_balloons]', (!isset($row['process_balloons']) ||  $row['process_balloons']), 1)?> <label for="data_0__process_balloons_1">yes</label>
<?php echo addRadioButton('data[0][process_balloons]', ( isset($row['process_balloons']) && !$row['process_balloons']), 0)?> <label for="data_0__process_balloons_0">no</label></td></tr>

<tr><td>Public:</td><td>
<?php echo addRadioButton('data[0][public]', (!isset($row['public']) ||  $row['public']), 1)?> <label for="data_0__public_1">yes</label>
<?php echo addRadioButton('data[0][public]', ( isset($row['public']) && !$row['public']), 0)?> <label for="data_0__public_0">no</label></td></tr>

<tr><td>Enabled:</td><td>
<?php echo addRadioButton('data[0][enabled]', (!isset($row['enabled']) ||  $row['enabled']), 1)?> <label for="data_0__enabled_1">yes</label>
<?php echo addRadioButton('data[0][enabled]', ( isset($row['enabled']) && !$row['enabled']), 0)?> <label for="data_0__enabled_0">no</label></td></tr>

</table>

<h3>Problems</h3>
<table>
	<thead>
	<tr>
		<th>ID</th>
		<th>name</th>
		<th>short name</th>
		<th>allow submit</th>
		<th>allow judge</th>
		<th>color
		<a target="_blank" href="http://www.w3schools.com/cssref/css_colornames.asp">
		<img src="../images/b_help.png" class="smallpicto" alt="?"></a></th>
	</tr>
	</thead>
	<tbody>
	<?php
	$current_problems = $DB->q("TABLE SELECT * FROM contestproblem INNER JOIN problem
				    USING (probid) WHERE cid = %i ORDER BY shortname", $id);
	$i = 0;
	$used_problems = array();
	foreach ($current_problems as $current_problem) {
		$used_problems[] = $current_problem['probid'];
		echo "<tr>\n";
		echo "<td>" . addHidden("data[0][mapping][0][items][$i]", $current_problem['probid']) .
		     "p" . $current_problem['probid'] . "</td>\n";
		echo "<td>" . $current_problem['name'] . "</td>\n";
		echo "<td>" .
		     addInput("data[0][mapping][0][extra][$i][shortname]", $current_problem['shortname'], 8,
			      10) . "</td>\n";
		echo "<td>";
		echo addRadioButton("data[0][mapping][0][extra][$i][allow_submit]",
				(!isset($current_problem['allow_submit']) || $current_problem['allow_submit']), 1) .
		     "<label for='data_0__mapping__0__extra__{$i}__allow_submit_1'>yes</label>";
		echo addRadioButton("data[0][mapping][0][extra][$i][allow_submit]",
				(isset($current_problem['allow_submit']) && !$current_problem['allow_submit']), 0) .
		     "<label for='data_0__mapping__0__extra__{$i}__allow_submit_0'>no</label>";
		echo "</td>\n";
		echo "<td>";
		echo addRadioButton("data[0][mapping][0][extra][$i][allow_judge]",
				(!isset($current_problem['allow_judge']) || $current_problem['allow_judge']), 1) .
		     "<label for='data_0__mapping__9__extra__{$i}__allow_judge_1'>yes</label>";
		echo addRadioButton("data[0][mapping][0][extra][$i][allow_judge]",
				(isset($current_problem['allow_judge']) && !$current_problem['allow_judge']), 0) .
		     "<label for='data_0__mapping__0__extra__{$i}__allow_judge_0'>no</label>";
		echo "</td>\n";
		echo "<td>" .
		     addInput("data[0][mapping][0][extra][$i][color]", $current_problem['color'], 15, 25,
		     'class="color {required:false,adjust:false,hash:true,caps:false}"') .
		     "</td>\n";
		echo "</tr>\n";
		$i++;
	}

	$unused_problems = $DB->q("KEYVALUETABLE SELECT probid, CONCAT('p', probid, ' - ', name)
	                           FROM problem " .
	                           (empty($used_problems) ? '%_' : 'WHERE probid NOT IN (%Ai)') .
	                           " ORDER BY probid", 
	                          $used_problems);
	$values = array('' => '-- Select problem --');
	foreach ($unused_problems as $probid => $text) {
		$values[$probid] = $text;
	}

	if ( !empty($unused_problems) ) {
		for ( $j = 0; $j < 12; $j++ ) {
			echo "<tr>\n";
			echo "<td colspan=\"2\">" .
			     addSelect("data[0][mapping][0][items][$i]", $values, null, true) . "</td>\n";
			echo "<td>" .
			     addInput("data[0][mapping][0][extra][$i][shortname]", null,
				      8, 10) . "</td>\n";
			echo "<td>";
			echo addRadioButton("data[0][mapping][0][extra][$i][allow_submit]", true, 1) .
			     "<label for='data_0__mapping__0__extra__{$i}__allow_submit_1'>yes</label>";
			echo addRadioButton("data[0][mapping][0][extra][$i][allow_submit]", false, 0) .
			     "<label for='data_0__mapping__0__extra__{$i}__allow_submit_0'>no</label>";
			echo "</td>\n";
			echo "<td>";
			echo addRadioButton("data[0][mapping][0][extra][$i][allow_judge]", true, 1) .
			     "<label for='data_0__mapping__0__extra__{$i}__allow_judge_1'>yes</label>";
			echo addRadioButton("data[0][mapping][0][extra][$i][allow_judge]", false, 0) .
			     "<label for='data_0__mapping__0__extra__{$i}__allow_judge_0'>no</label>";
			echo "</td>\n";
			echo "<td>" . addInput("data[0][mapping][0][extra][$i][color]", null, 15, 25,
			      'class="color {required:false,adjust:false,hash:true,caps:false}"') . "</td>";
			echo "</tr>\n";
			$i++;
		}
	}
	?>
	</tbody>
</table>

<?php
echo addHidden('data[0][mapping][0][fk][0]', 'cid') .
     addHidden('data[0][mapping][0][fk][1]', 'probid') .
     addHidden('data[0][mapping][0][table]', 'contestproblem');
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

if ( in_array($data['cid'], $cids) ) {
	echo "<p><em>This is an active contest.</em></p>\n\n";
}
if ( !$data['enabled'] ) {
	echo "<p><em>This contest is disabled.</em></p>\n\n";
}

$numteams = $DB->q("VALUE SELECT COUNT(*) AS teamcount
		    FROM contestteam
		    WHERE cid = %i", $id);
$numprobs = $DB->q("VALUE SELECT COUNT(*) AS problemcount
		    FROM contestproblem
		    WHERE cid = %i", $id);


echo "<table>\n";
echo '<tr><td>CID:</td><td>c' .
	(int)$data['cid'] . "</td></tr>\n";
echo '<tr><td>Short name:</td><td>' .
     htmlspecialchars($data['shortname']) .
     "</td></tr>\n";
echo '<tr><td>Name:</td><td>' .
	htmlspecialchars($data['contestname']) .
	"</td></tr>\n";
echo '<tr><td>Activate time:</td><td>' .
	htmlspecialchars(@$data['activatetime_string']) .
	"</td></tr>\n";
echo '<tr><td>Start time:</td><td>' .
	htmlspecialchars($data['starttime_string']) .
	"</td></tr>\n";
echo '<tr><td>Scoreboard freeze:</td><td>' .
	(empty($data['freezetime_string']) ? "-" : htmlspecialchars(@$data['freezetime_string'])) .
	"</td></tr>\n";
echo '<tr><td>End time:</td><td>' .
	htmlspecialchars($data['endtime_string']) .
	"</td></tr>\n";
echo '<tr><td>Dectivate time:</td><td>' .
     htmlspecialchars(@$data['deactivatetime_string']) .
     "</td></tr>\n";
echo '<tr><td>Process balloons:</td><td>' .
     ($data['process_balloons'] ? 'yes' : 'no') .
     "</td></tr>\n";
echo '<tr><td>Public:</td><td>' .
     ($data['public'] ? 'yes' : 'no') .
     "</td></tr>\n";
echo '<tr><td>Teams:</td><td>';
if ( $data['public'] ) {
	echo "<em>all teams</em>";
} else {
	if ( $numteams == 0 ) {
		echo '<em>no teams</em>';
	} else {
		echo (int)$numteams;
	}
}
echo '</td></tr>';
echo '<tr><td>Problems:</td><td>';
if ( $numprobs==0 ) {
	echo '<em>no problems</em>';
} else {
	echo (int)$numprobs;
}
echo '</td></tr>';
echo '<tr><td>Scoreboard unfreeze:</td><td>' .
	(empty($data['unfreezetime_string']) ? "-" : htmlspecialchars(@$data['unfreezetime_string'])) .
	"</td></tr>\n";
echo "</table>\n\n";

if ( IS_ADMIN ) {
	if ( in_array($data['cid'], $cids) ) {
		echo "<p>". rejudgeForm('contest', $data['cid']) . "</p>\n\n";
	}
	echo "<p>" .
		editLink('contest',$data['cid']) . "\n" .
		delLink('contest','cid',$data['cid']) ."</p>\n\n";
}

echo "<h3>Problems</h3>\n\n";

$res = $DB->q('TABLE SELECT *
		       FROM problem
		       INNER JOIN contestproblem USING (probid)
		       WHERE cid = %i
		       ORDER BY shortname', $id);

if ( count($res) == 0 ) {
	echo "<p class=\"nodata\">No problems added yet</p>\n\n";
}
else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\" class=\"sorttable_numeric\">probid</th>";
	echo "<th scope=\"col\">name</th>";
	echo "<th scope=\"col\">shortname</th>";
	echo "<th scope=\"col\">allow<br />submit</th>";
	echo "<th scope=\"col\">allow<br />judge</th>";
	echo "<th class=\"sorttable_nosort\" scope=\"col\">colour</th>\n";
	echo "</tr>\n</thead>\n<tbody>\n";

	$iseven = false;
	foreach ( $res as $row ) {

		$link = '<a href="problem.php?id=' . urlencode($row['probid']) . '">';

		echo '<tr class="' .
		     ($iseven ? 'roweven' : 'rowodd') . '">' .
		     "<td class=\"tdright\">" . $link .
		     "p" . (int)$row['probid'] . "</a></td>\n";
		echo "<td>" . $link . htmlspecialchars($row['name']) . "</a></td>\n";
		echo "<td>" . $link . htmlspecialchars($row['shortname']) . "</a></td>\n";
		echo "<td class=\"tdcenter\">" . $link . printyn($row['allow_submit']) . "</a></td>\n";
		echo "<td class=\"tdcenter\">" . $link . printyn($row['allow_judge']) . "</a></td>\n";
		echo ( !empty($row['color'])
			? '<td title="' . htmlspecialchars($row['color']) .
			  '">' . $link . '<div class="circle" style="background-color: ' .
			  htmlspecialchars($row['color']) .
			  ';"></div></a></td>'
			: '<td>'. $link . '&nbsp;</a></td>' );
		if ( IS_ADMIN ) echo "<td>" . delLinkMultiple('contestproblem',array('cid','probid'),array($id, $row['probid']), 'contest.php?id='.$id) ."</td>";

		$iseven = !$iseven;

		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

require(LIBWWWDIR . '/footer.php');
