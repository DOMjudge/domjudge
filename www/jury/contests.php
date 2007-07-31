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
require('../header.php');

echo "<h1>Contests</h1>\n\n";

$curcont = getCurContest();

if ( isset($_POST['unfreeze']) ) {
	$docid = array_pop(array_keys($_POST['unfreeze']));
	if ( $docid != $curcont['cid'] ) {
		error("Can only unfreeze for current contest");
	}
	$DB->q('UPDATE contest SET unfreezetime = %s WHERE cid = %i', now(), $docid);
}

$res = $DB->q('TABLE SELECT * FROM contest ORDER BY starttime DESC');

if( count($res) == 0 ) {
	echo "<p><em>No contests defined</em></p>\n\n";
} else {
	echo "<form action=\"contests.php\" method=\"post\">\n";
	echo "<table class=\"list\">\n<thead>\n" .
	     "<tr><th scope=\"col\">CID</th><th scope=\"col\">starts</th>" .
		 "<th scope=\"col\">ends</th><th scope=\"col\">freeze<br />scores</th>" .
		 "<th scope=\"col\">unfreeze<br />scores</th><th scope=\"col\">name</th>" .
	     "</tr>\n</thead>\n<tbody>\n";

	foreach($res as $row) {
		echo "<tr" .
			($row['cid'] == $curcont ? ' class="highlight"':'') . ">" .
			"<td align=\"right\"><a href=\"contest.php?id=" . urlencode($row['cid']) .
			"\">c" . (int)$row['cid'] . "</a></td>\n" .
			"<td title=\"" . htmlentities($row['starttime']) . "\">" .
				printtime($row['starttime'])."</td>\n".
			"<td title=\"".htmlentities($row['endtime']) . "\">" .
				printtime($row['endtime'])."</td>\n".
			"<td title=\"".htmlentities(@$row['lastscoreupdate']) . "\">" .
			( isset($row['lastscoreupdate']) ?
			  printtime($row['lastscoreupdate']) : '-' ) . "</td>\n" .
			"<td title=\"".htmlentities(@$row['unfreezetime']) . "\">" .
			( isset($row['unfreezetime']) ?
			  printtime($row['unfreezetime']) : '-' ) . "</td>\n" .
			"<td>" . htmlentities($row['contestname']) . "</td>\n";
		if ( IS_ADMIN ) {
			echo "<td>" . 
				editLink('contest', $row['cid']) . " " .
				delLink('contest','cid',$row['cid']) . "</td>\n";
		}

		// display an unfreeze scoreboard button, only for the current
		// contest (unfreezing undisplayed scores makes no sense) and
		// only if the contest has already finished, and the scores have
		// not already been unfrozen.
		echo "<td>";
		if ( $row['cid'] == $curcont && isset($row['lastscoreupdate']) ) {
			echo "<input type=\"submit\" name=\"unfreeze[" . $row['cid'] .
				"]\" value=\"unfreeze scoreboard now\"" ;
			if ( strtotime($row['endtime']) > time() ||
				(isset($row['unfreezetime']) && strtotime($row['unfreezetime']) <= time())
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

require('../footer.php');
