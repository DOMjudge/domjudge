<?php
/**
 * View current, past and future contests
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/checkers.jury.php');
$times = array ('activate','start','freeze','end','unfreeze','deactivate');
$now = now();

if ( isset($_POST['donow']) ) {

	requireAdmin();

	$docid = $_POST['cid'];

	$time = key($_POST['donow']);
	if ( !in_array($time, $times) ) error("Unknown value for timetype");

	$now = floor($now);
	$nowstring = strftime('%Y-%m-%d %H:%M:%S',$now);
	auditlog('contest', $docid, $time. ' now', $nowstring);

	// starttime is special because other, relative times depend on it.
	if ( $time == 'start' ) {
		$docdata = $cdatas[$docid];
		$docdata['starttime'] = $now;
		$docdata['starttime_string'] = $nowstring;
		foreach(array('endtime','freezetime','unfreezetime','activatetime','deactivatetime') as $f) {
			$docdata[$f] = check_relative_time($docdata[$f.'_string'], $docdata['starttime'], $f);
		}
		$DB->q('UPDATE contest SET starttime = %s, starttime_string = %s,
		        endtime = %s, freezetime = %s, unfreezetime = %s,
		        activatetime = %s, deactivatetime = %s
		        WHERE cid = %i', $docdata['starttime'], $docdata['starttime_string'],
		       $docdata['endtime'], $docdata['freezetime'], $docdata['unfreezetime'],
		       $docdata['activatetime'], $docdata['deactivatetime'], $docid);
		header ("Location: ./contests.php?edited=1");
	} else {
		$DB->q('UPDATE contest SET ' . $time . 'time = %s, ' . $time . 'time_string = %s
		        WHERE cid = %i', $now, $nowstring, $docid);
		header ("Location: ./contests.php");
	}
	exit;
}

$title = 'Contests';
require(LIBWWWDIR . '/header.php');

echo "<h1>Contests</h1>\n\n";

if ( isset($_GET['edited']) ) {
	echo addForm('refresh_cache.php') .
            msgbox (
                "Warning: Refresh scoreboard cache",
		"After changing the contest start time, it may be necessary to recalculate any cached scoreboards.<br /><br />" .
		addSubmit('recalculate caches now', 'refresh')
		) .
		addEndForm();
}

// Display current contest data prominently

echo "<fieldset><legend>Current contests: ";

$curcids = getCurContests(FALSE);

if ( empty($curcids) )  {
	echo "none</legend>\n\n";

	$row = $DB->q('MAYBETUPLE SELECT * FROM contest
	               WHERE activatetime > UNIX_TIMESTAMP() AND enabled = 1
	               ORDER BY activatetime LIMIT 1');

	if ( $row ) {
		echo "<form action=\"contests.php\" method=\"post\">\n";
		echo addHidden('cid', $row['cid']);
		echo "<p>No active contest. Upcoming:<br/> <em>" .
		     htmlspecialchars($row['name']) .
		     ' (' . htmlspecialchars($row['shortname']) . ')' .
		     "</em>; active from " . printtime($row['activatetime'], '%a %d %b %Y %T %Z') .
		     "<br /><br />\n";
		if ( IS_ADMIN ) echo addSubmit("activate now", "donow[activate]");
		echo "</form>\n\n";
	} else {
		echo "<p class=\"nodata\">No upcoming contest</p>\n";
	}

} else {
	if ( empty($curcids) ) {
		$rows = array();
	} else {
		$rows = $DB->q('TABLE SELECT * FROM contest WHERE cid IN (%Ai)', $curcids);
	}
	echo "</legend>\n\n";

	foreach ($rows as $row) {
		$prevchecked = false;
		$hasstarted = difftime($row['starttime'], $now) <= 0;
		$hasended = difftime($row['endtime'], $now) <= 0;
		$hasfrozen = !empty($row['freezetime']) &&
			     difftime($row['freezetime'], $now) <= 0;
		$hasunfrozen = !empty($row['unfreezetime']) &&
			       difftime($row['unfreezetime'], $now) <= 0;

		$contestname = htmlspecialchars(sprintf('%s (%s - c%d)',
							$row['name'],
							$row['shortname'],
							$row['cid']));

		echo "<form action=\"contests.php\" method=\"post\">\n";
		echo addHidden('cid', $row['cid']);
		echo "<fieldset><legend>${contestname}</legend>\n";

		echo "<table>\n";
		foreach ($times as $time) {
			$haspassed = difftime($row[$time . 'time'], $now) <= 0;

			echo "<tr><td>";
			// display checkmark when done or ellipsis when next up
			if ( empty($row[$time . 'time']) ) {
				// don't display anything before an empty row
			} elseif ( $haspassed ) {
				echo "<img src=\"../images/s_success.png\" alt=\"&#10003;\" class=\"picto\" />\n";
				$prevchecked = true;
			} elseif ( $prevchecked ) {
				echo "…";
				$prevchecked = false;
			}

			echo "</td><td>" .
			     ucfirst($time) . " time:</td><td>" .
			     printtime($row[$time . 'time'], '%Y-%m-%d %H:%M (%Z)') .
			     "</td><td>";

			// Show a button for setting the time to now(), only when that
			// makes sense. E.g. only for end contest when contest has started.
			// No button for 'activate', because when shown by definition always already active
			if ( IS_ADMIN && (
					($time == 'start' && !$hasstarted) ||
					($time == 'end' && $hasstarted && !$hasended &&
					 (empty($row['freezetime']) || $hasfrozen)) ||
					($time == 'deactivate' && $hasended &&
					 (empty($row['unfreezetime']) || $hasunfrozen)) ||
					($time == 'freeze' && $hasstarted && !$hasended &&
					 !$hasfrozen) ||
					($time == 'unfreeze' && $hasfrozen && !$hasunfrozen &&
					 $hasended))
			) {
				echo addSubmit("$time now", "donow[$time]");
			}

			echo "</td></tr>";

		}

		echo "</table>\n";

		echo "</fieldset>\n</form>\n\n";
	}

}

echo "</fieldset>\n\n";


// Get data. Starttime seems most logical sort criterion.
$res = $DB->q('TABLE SELECT contest.*, COUNT(teamid) AS numteams
               FROM contest
               LEFT JOIN contestteam USING (cid)
               GROUP BY cid ORDER BY starttime DESC');
$numprobs = $DB->q('KEYVALUETABLE SELECT cid, COUNT(probid) FROM contest LEFT JOIN contestproblem USING(cid) GROUP BY cid');

if( count($res) == 0 ) {
	echo "<p class=\"nodata\">No contests defined</p>\n\n";
} else {
	echo "<h3>All available contests</h3>\n\n";
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\" class=\"sorttable_numeric\">CID</th>";
	echo "<th scope=\"col\">shortname</th>";
	foreach($times as $time) echo "<th scope=\"col\">$time</th>";
	echo "<th scope=\"col\">process<br />balloons?</th>";
	echo "<th scope=\"col\">public?</th>";
	echo "<th scope=\"col\" class=\"sorttable_numeric\"># teams</th>";
	echo "<th scope=\"col\" class=\"sorttable_numeric\"># problems</th>";
	echo "<th scope=\"col\">name</th>" .
	     ( IS_ADMIN ? "<th scope=\"col\"></th>" : '' ) .
	     "</tr>\n</thead>\n<tbody>\n";

	$iseven = false;
	foreach($res as $row) {

		$link = '<a href="contest.php?id=' . urlencode($row['cid']) . '">';

		echo '<tr class="' .
			( $iseven ? 'roweven': 'rowodd' ) .
			(!$row['enabled']    ? ' disabled' :'') .
			(in_array($row['cid'], $curcids) ? ' highlight':'') . '">' .
			"<td class=\"tdright\">" . $link .
			"c" . (int)$row['cid'] . "</a></td>\n";
		echo "<td>" . $link . htmlspecialchars($row['shortname']) . "</a></td>\n";
		foreach ($times as $time) {
			echo "<td title=\"".printtime(@$row[$time. 'time'],'%Y-%m-%d %H:%M') . "\">" .
			      $link . ( isset($row[$time.'time']) ?
			      printtime($row[$time.'time']) : '-' ) . "</a></td>\n";
		}
		echo "<td>" . $link . ($row['process_balloons'] ? 'yes' : 'no') . "</a></td>\n";
		echo "<td>" . $link . ($row['public'] ? 'yes' : 'no') . "</a></td>\n";
		echo "<td>" . $link . ($row['public'] ? '<em>all</em>' : $row['numteams']) . "</a></td>\n";
		echo "<td>" . $link . $numprobs[$row['cid']] . "</a></td>\n";
		echo "<td>" . $link . htmlspecialchars($row['name']) . "</a></td>\n";
		$iseven = ! $iseven;

		if ( IS_ADMIN ) {
			echo "<td class=\"editdel\">" .
				editLink('contest', $row['cid']) . " " .
				delLink('contest','cid',$row['cid']) . "</td>\n";
		}

		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('contest') . "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
