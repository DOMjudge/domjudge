<?php
/**
 * View the judgehosts
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Judgehosts';

$refresh = '15;url=judgehosts.php';

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

	echo addForm('edit.php');
	echo "\n<table>\n" .
		"<tr><th>Hostname</th><th>Active</th></tr>\n";
	if ( $cmd == 'add' ) {
		for ($i=0; $i<10; ++$i) {
			echo "<tr><td>" .
				addInput("data[$i][hostname]", null, 20, 50, 'pattern="[A-Za-z0-9._-]+"') .
				"</td><td>" .
				addSelect("data[$i][active]",
					array(1=>'yes',0=>'no'), '1', true) .
				"</td></tr>\n";
		}
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
				"</td></tr>\n";
			++$i;
		}
	}
	echo "</table>\n\n<br /><br />\n";
	echo addHidden('cmd', $cmd) .
		( $cmd == 'add' ? addHidden('skipwhenempty', 'hostname') : '' ) .
		addHidden('table','judgehost') .
		addSubmit('Save Judgehosts') .
		addEndForm();

	require(LIBWWWDIR . '/footer.php');
	exit;

}

$res = $DB->q('SELECT * FROM judgehost ORDER BY hostname');

// NOTE: these queries do not take into account the time spent on a
// current judging. It is tricky, however, to determine if a judging
// is currently running or has crashed, so we simply ignore this.

$now = now();
$work2min    = $DB->q('KEYVALUETABLE SELECT judgehost, SUM(endtime - GREATEST(%i,starttime))
                       FROM judging WHERE endtime > %i GROUP BY judgehost',
                      $now-2*60, $now-2*60);

$work10min   = $DB->q('KEYVALUETABLE SELECT judgehost, SUM(endtime - GREATEST(%i,starttime))
                       FROM judging WHERE endtime > %i GROUP BY judgehost',
                      $now-10*60, $now-10*60);

$workcontest = $DB->q('KEYVALUETABLE SELECT judgehost, SUM(endtime - GREATEST(%i,starttime))
                       FROM judging WHERE endtime > %i GROUP BY judgehost',
                      $cdata['starttime'], $cdata['starttime']);

$clen = difftime($now,$cdata['starttime']);

if( $res->count() == 0 ) {
	echo "<p class=\"nodata\">No judgehosts defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\">hostname</th>" .
		 "<th scope=\"col\">active</th>" .
		 "<th class=\"sorttable_nosort\">status</th>" .
		 "<th class=\"sorttable_nosort\">load</th>" .
		 "<th scope=\"col\" colspan=\"2\"></th></tr>\n" .
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
			if ( $reltime < JUDGEHOST_WARNING ) {
				echo "judgehost-ok";
			} else if ( $reltime < JUDGEHOST_CRITICAL ) {
				echo "judgehost-warn";
			} else {
				echo "judgehost-crit";
			}
			echo "\" title =\"last checked in $reltime seconds ago\">";
		}
		echo $link . CIRCLE_SYM . "</a></td>";
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
