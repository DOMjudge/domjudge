<?php
/**
 * View current, past and future contests
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/checkers.jury.php');
$times = array ('activate','start','freeze','end','unfreeze');
$now = now();

if ( IS_ADMIN && isset($_POST['donow']) ) {
	$time = array_pop(array_keys($_POST['donow']));
	if ( !in_array($time, $times) ) error("Unknown value for timetype");
	// for activatetime  we don't have a current contest to use,
	// so we need to get it from the form data.
	$docid = $time == 'activate' ? array_pop(array_keys($_POST['donow'][$time])) : $cid;

	auditlog('contest', $docid, $time. ' now', $now);
	// starttime is special because it doesn't have relative time support
	if ( $time == 'start' ) {
		$cdata['starttime'] = $now;
		foreach(array('endtime','freezetime','unfreezetime','activatetime') as $f) {
			$cdata[$f] = check_relative_time($cdata[$f.'_string'], $cdata['starttime'], $f);
		}
		$DB->q('UPDATE contest SET starttime = %s, endtime = %s, freezetime = %s,
			unfreezetime = %s, activatetime = %s
		        WHERE cid = %i', $cdata['starttime'], $cdata['endtime'],
			$cdata['freezetime'], $cdata['unfreezetime'], $cdata['activatetime'],
			$docid);
		header ("Location: ./contests.php?edited=1");
	} else {
		$DB->q('UPDATE contest SET ' . $time . 'time = %s, ' . $time . 'time_string = %s
		        WHERE cid = %i', $now, $now, $docid);
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

echo "<form action=\"contests.php\" method=\"post\">\n";
echo "<fieldset><legend>Current contest: ";

if ( empty($cid) )  {
	echo "none</legend>\n\n";

	$row = $DB->q('MAYBETUPLE SELECT * FROM contest
	               WHERE activatetime > now() AND enabled = 1
                       ORDER BY activatetime LIMIT 1');

	if ( $row ) {
		echo "<p>No active contest. Upcoming:<br/> <em>" .
		     htmlspecialchars($row['contestname']) .
		     "</em>; active from " . $row['activatetime'] .
		     "<br /><br />\n";
		if ( IS_ADMIN ) echo "<input type=\"submit\" " .
		     "name=\"donow[activate][" . (int)$row['cid'] . 
		     "]\" value=\"activate now\" />\n";
		
	} else {
		echo "<p class=\"nodata\">No upcoming contest</p>\n";
	}

} else {
	$row = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $cid);
	echo htmlspecialchars($row['contestname'] . " (c$cid)") . "</legend>\n\n";

	$prevchecked = false;
	$hasstarted = difftime($row['starttime'], $now) <= 0;
	$hasended = difftime($row['endtime'], $now) <= 0;
	$hasfrozen = !empty($row['freezetime']) && difftime($row['freezetime'], $now) <= 0;
	$hasunfrozen = !empty($row['unfreezetime']) && difftime($row['unfreezetime'], $now) <= 0;

	echo "<table>\n";
	foreach ($times as $time) {
		$haspassed = difftime($row[$time.'time'], $now) <= 0;

		echo "<tr><td>";
		// display checkmark when done or ellipsis when next up
		if ( empty($row[$time.'time']) ) {
			// don't display anything before an empty row
		} elseif ( $haspassed ) {
			echo "<img src=\"../images/s_success.png\" alt=\"&#10003;\" class=\"picto\" />\n";
			$prevchecked = true;
		} elseif ($prevchecked) {
			echo "…";
			$prevchecked = false;
		}

		echo "</td><td>" .
		     ucfirst($time) . " time:</td><td>" .
		     htmlspecialchars($row[$time.'time']) . "</td><td>";

		// Show a button for setting the time to now(), only when that
		// makes sense. E.g. only for end contest when contest has started.
		// No button for 'activate', because when shown by definition always already active
		if ( IS_ADMIN && (
		 ( $time == 'start' && !$hasstarted ) ||
		 ( $time == 'end' && $hasstarted && !$hasended && (empty($row['freezetime']) || $hasfrozen) ) ||
		 ( $time == 'freeze' && $hasstarted && !$hasended && !$hasfrozen ) || 
		 ( $time == 'unfreeze' && $hasfrozen && !$hasunfrozen && $hasended ) ) ) {
			echo addSubmit("$time now", "donow[$time]");
		}

		echo "</td></tr>";

	}

	echo "</table>\n\n";

}

echo "</fieldset>\n</form>\n\n";


// Get data. Starttime seems most logical sort criterion.
$res = $DB->q('TABLE SELECT * FROM contest ORDER BY starttime DESC');

if( count($res) == 0 ) {
	echo "<p class=\"nodata\">No contests defined</p>\n\n";
} else {
	echo "<h3>All available contests</h3>\n\n";
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\" class=\"sorttable_numeric\">CID</th>";
	foreach($times as $time) echo "<th scope=\"col\">$time</th>";
	echo "<th scope=\"col\">name</th></tr>\n</thead>\n<tbody>\n";

	$iseven = false;
	foreach($res as $row) {

		$link = '<a href="contest.php?id=' . urlencode($row['cid']) . '">';

		echo '<tr class="' .
			( $iseven ? 'roweven': 'rowodd' ) .
			(!$row['enabled']    ? ' disabled' :'') .
			($row['cid'] == $cid ? ' highlight':'') . '">' .
			"<td align=\"right\">" . $link .
			"c" . (int)$row['cid'] . "</a></td>\n";
		foreach ($times as $time) {
			echo "<td title=\"".htmlspecialchars(@$row[$time. 'time']) . "\">" .
			      $link . ( isset($row[$time.'time']) ?
			      printtime($row[$time.'time']) : '-' ) . "</a></td>\n";
		}
		echo "<td>" . $link . htmlspecialchars($row['contestname']) . "</a></td>\n";
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
