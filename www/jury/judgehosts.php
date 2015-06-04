<?php
/**
 * View the judgehosts
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Judgehosts';

if ( !isset($_REQUEST['cmd']) ) {
	$refresh = '15;url=judgehosts.php';
}

require(LIBWWWDIR . '/header.php');

echo "<h1>Judgehosts</h1>\n\n";

@$cmd = @$_REQUEST['cmd'];
if ( isset($_POST['cmd-activate']) || isset($_POST['cmd-deactivate']) ) {

	requireAdmin();

	$DB->q('UPDATE judgehost SET active = %i',
	       (isset($_POST['cmd-activate']) ? 1:0));
	auditlog('judgehost', null, 'marked all ' . (isset($_POST['cmd-activate'])?'active':'inactive'));
}
if ( $cmd == 'add' || $cmd == 'edit' ) {

	requireAdmin();

	$restrictions = $DB->q('KEYVALUETABLE SELECT restrictionid, name
	                        FROM judgehost_restriction ORDER BY restrictionid');
	$restrictions = array(null => '-- No restrictions --') + $restrictions;

	echo addForm('edit.php');
?>
<script type="text/template" id="judgehost_template">
<tr>
	<td>
		<?php echo addInput("data[{id}][hostname]", null, 20, 50, 'pattern="[A-Za-z0-9._-]+"'); ?>
	</td>
	<td>
		<?php echo addSelect("data[{id}][active]", array(1=>'yes',0=>'no'), '1', true); ?>
	</td>
	<td>
		<?php echo addSelect("data[{id}][restrictionid]", $restrictions, null, true); ?>
	</td>
</tr>
</script>
<?php
	echo "\n<table id=\"judgehosts\">\n" .
		"<tr><th>Hostname</th><th>Active</th><th>Restrictions</th></tr>\n";
	if ( $cmd == 'add' ) {
		// Nothing, added by javascript in addAddRowButton
	} else {
		$res = $DB->q('SELECT * FROM judgehost ORDER BY hostname');
		$i = 0;
		while ( $row = $res->next() ) {
			echo "<tr><td>" .
				addHidden("keydata[$i][hostname]", $row['hostname']) .
				printhost($row['hostname']) .
				"</td><td>" .
				addSelect("data[$i][active]",
					array(1=>'yes',0=>'no'), $row['active'], true) .
				"</td><td>" .
				addSelect("data[$i][restrictionid]", $restrictions, $row['restrictionid'], true) .
				"</td></tr>\n";
			++$i;
		}
	}
	echo "</table>\n\n<br /><br />\n";
	echo addHidden('cmd', $cmd) .
		( $cmd == 'add' ? addHidden('skipwhenempty', 'hostname') : '' ) .
		addHidden('table','judgehost') .
		( $cmd == 'add' ? addAddRowButton('judgehost_template', 'judgehosts') : '' ) .
		addSubmit('Save Judgehosts') .
		addEndForm();

	require(LIBWWWDIR . '/footer.php');
	exit;

}

$res = $DB->q('SELECT judgehost.*, judgehost_restriction.name
               FROM judgehost
               LEFT JOIN judgehost_restriction USING (restrictionid)
               ORDER BY hostname');

$now = now();
$query = 'KEYVALUETABLE SELECT judgehost,
          SUM(IF(endtime,endtime,%i) - GREATEST(%i,starttime))
          FROM judging
          WHERE endtime > %i OR (endtime IS NULL and valid = 1)
          GROUP BY judgehost';

$from = $now-2*60;
$work2min    = $DB->q($query, $now, $from, $from);

$from = $now-10*60;
$work10min   = $DB->q($query, $now, $from, $from);

$from = $cdata['starttime'];
$workcontest = $DB->q($query, $now, $from, $from);

$clen = difftime($now,$cdata['starttime']);

if( $res->count() == 0 ) {
	echo "<p class=\"nodata\">No judgehosts defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\">hostname</th>" .
		 "<th scope=\"col\">active</th>" .
		 "<th class=\"sorttable_nosort\">status</th>" .
	     "<th class=\"sorttable_nosort\">restriction</th>" .
		 "<th class=\"sorttable_nosort\">load</th></tr>\n" .
		 "</thead>\n<tbody>\n";
	while($row = $res->next()) {
		$link = '<a href="judgehost.php?id=' . urlencode($row['hostname']) . '">';
		echo "<tr".( $row['active'] ? '': ' class="disabled"').
			"><td>" . $link . printhost($row['hostname']) . '</a>' .
			"</td><td class=\"tdcenter\">" . $link . printyn($row['active']) .
			"</a></td>";
		echo "<td class=\"tdcenter ";
		if ( empty($row['polltime'] ) ) {
			echo "judgehost-nocon";
			echo "\" title =\"never checked in\">";
		} else {
			$reltime = floor(difftime($now,$row['polltime']));
			if ( $reltime < dbconfig_get('judgehost_warning',30) ) {
				echo "judgehost-ok";
			} else if ( $reltime < dbconfig_get('judgehost_critical',120) ) {
				echo "judgehost-warn";
			} else {
				echo "judgehost-crit";
			}
			echo "\" title =\"last checked in $reltime seconds ago\">";
		}
		echo $link . CIRCLE_SYM . "</a></td>";
		echo "<td>" . $link . (is_null($row['name']) ? '<i>none</i>' : $row['name']) . '</a></td>';
		echo "<td title=\"load during the last 2 and 10 minutes and the whole contest\">" .$link .
		    sprintf('%.2f&nbsp;%.2f&nbsp;%.2f',
		            @$work2min[   $row['hostname']] / (2*60),
		            @$work10min[  $row['hostname']] / (10*60),
		            @$workcontest[$row['hostname']] / $clen) . "</a></td>";
		if ( IS_ADMIN ) {
			if ( $row['active'] ) {
				$activepicto = "pause"; $activecmd = "deactivate";
			} else {
				$activepicto = "play"; $activecmd = "activate";
			}
			echo "<td><a href=\"judgehost.php?id=" . $row['hostname'] . "&amp;cmd=" .
			     $activecmd . "\"><img class=\"picto\" alt=\"" . $activecmd .
			     "\" title=\"" . $activecmd . " judgehost\" " .
			     "src=\"../images/" . $activepicto . ".png\" /></a></td>";
			echo "<td>" . delLink('judgehost','hostname',$row['hostname']) ."</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo addForm($pagename) .
		"<p>" .
		addSubmit('Start all judgehosts', 'cmd-activate') .
		addSubmit('Stop all judgehosts', 'cmd-deactivate') .
		"<br /><br />\n\n" .
		addLink('judgehosts', true) . "\n" .
		editLink('judgehosts', null, true) .
		"</p>\n" .
		addEndForm();

}

require(LIBWWWDIR . '/footer.php');
