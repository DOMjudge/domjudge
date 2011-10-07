<?php
/**
 * View current, past and future contests
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Contests';
require(LIBWWWDIR . '/header.php');

echo "<h1>Contests</h1>\n\n";

if ( isset($_POST['unfreeze']) ) {
	$docid = array_pop(array_keys($_POST['unfreeze']));
	if ( $docid != $cid ) {
		error("Can only unfreeze for current contest");
	}
	$now = now();
	$DB->q('UPDATE contest SET unfreezetime = %s, unfreezetime_string = %s
	        WHERE cid = %i', $now, $now, $docid);
}

if ( isset($_GET['edited']) ) {

	echo addForm('refresh_cache.php', 'get') .
            msgbox (
                "Warning: Refresh scoreboard cache",
		"After changing contest times, it may be necessary to recalculate the cached scoreboards.<br /><br />" .
		addSubmit('recalculate caches now') 
		) .
		addEndForm();

}

// Get data. Starttime seems most logical sort criterion.
$res = $DB->q('TABLE SELECT * FROM contest ORDER BY starttime DESC');

if( count($res) == 0 ) {
	echo "<p class=\"nodata\">No contests defined</p>\n\n";
} else {
	echo "<form action=\"contests.php\" method=\"post\">\n";
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\">CID</th><th scope=\"col\">active</th>" .
	     "<th scope=\"col\">starts</th><th scope=\"col\">ends</th>" .
	     "<th scope=\"col\">freeze<br />scores</th>" .
	     "<th scope=\"col\">unfreeze<br />scores</th>" .
	     "<th scope=\"col\">name</th></tr>\n</thead>\n<tbody>\n";

	$iseven = false;
	foreach($res as $row) {

		$link = '<a href="contest.php?id=' . urlencode($row['cid']) . '">';

		echo '<tr class="' .
			( $iseven ? 'roweven': 'rowodd' ) .
			(!$row['enabled']    ? ' disabled' :'') .
			($row['cid'] == $cid ? ' highlight':'') . '">' .
			"<td align=\"right\">" . $link .
			"c" . (int)$row['cid'] . "</a></td>\n" .
			"<td title=\"".htmlspecialchars(@$row['activatetime']) . "\">" .
				$link . printtime($row['activatetime']) . "</a></td>\n" .
			"<td title=\"" . htmlspecialchars($row['starttime']) . "\">" .
				$link . printtime($row['starttime'])."</a></td>\n".
			"<td title=\"".htmlspecialchars($row['endtime']) . "\">" .
				$link . printtime($row['endtime'])."</a></td>\n".
			"<td title=\"".htmlspecialchars(@$row['freezetime']) . "\">" .
				$link . ( isset($row['freezetime']) ?
			  printtime($row['freezetime']) : '-' ) . "</a></td>\n" .
			"<td title=\"".htmlspecialchars(@$row['unfreezetime']) . "\">" .
				$link . ( isset($row['unfreezetime']) ?
			  printtime($row['unfreezetime']) : '-' ) . "</a></td>\n" .
			"<td>" . $link . htmlspecialchars($row['contestname']) . "</a></td>\n";
		$iseven = ! $iseven;

		if ( IS_ADMIN ) {
			echo "<td class=\"editdel\">" .
				editLink('contest', $row['cid']) . " " .
				delLink('contest','cid',$row['cid']) . "</td>\n";
		}

		// display an unfreeze scoreboard button, only for the current
		// contest (unfreezing undisplayed scores makes no sense) and
		// only if the contest has already finished, and the scores have
		// not already been unfrozen.
		echo "<td>";
		if ( $row['cid'] == $cid && isset($row['freezetime']) ) {
			echo "<input type=\"submit\" name=\"unfreeze[" . $row['cid'] .
				"]\" value=\"unfreeze scoreboard now\"" ;
			$now = now();
			if ( difftime($row['endtime'],$now) > 0 ||
				(isset($row['unfreezetime']) && difftime($row['unfreezetime'], $now) <= 0)
				) {
				echo " disabled=\"disabled\"";
			}
			echo " />";
		}
		echo "</td>\n";
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n</form>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('contest') . "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
