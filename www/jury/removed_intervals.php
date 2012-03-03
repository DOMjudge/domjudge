<?php
/**
 * Edit and undo removed contest time intervals.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Removed intervals';

require(LIBWWWDIR . '/header.php');

echo "<h1>Removed intervals</h1>\n\n";

requireAdmin();

@$cmd = @$_REQUEST['cmd'];
$mycid = (int)$_GET['cid'];
if ( $cmd == 'add' || $cmd == 'edit' ) {
	echo addForm('edit.php');
	echo "\n<table>\n" .
		"<tr><th>From</th><th>To</th><td></td></tr>\n";
	if ( $cmd == 'add' ) {
			echo "<tr><td>" . addHidden("data[0][cid]", $mycid) .
			     addInput("data[0][starttime]", null, 20, 50) .
			     "</td><td>" .
			     addInput("data[0][endtime]", null, 20, 50) .
			     "</td><td>(yyyy-mm-dd hh:mm:ss)</td></tr>\n";
				"</td></tr>\n";
	} else {
		$res = $DB->q('SELECT * FROM removed_interval WHERE cid = %i ORDER BY starttime', $mycid);
		$i = 0;
		while ( $row = $res->next() ) {
			echo "<tr><td>" . addHidden("keydata[$i][intervalid]", $row['intervalid']) .
			     addHidden("data[$i][cid]", $mycid) .
			     addInput("data[$i][starttime]", $row['starttime'], 20, 50) .
			     "</td><td>" .
			     addInput("data[$i][endtime]", $row['endtime'], 20, 50) .
			     "</td><td>(yyyy-mm-dd hh:mm:ss)</td></tr>\n";
			++$i;
		}
	}
	echo "</table>\n\n<br /><br />\n";
	echo addHidden('cmd', $cmd) .
		addHidden('table','removed_interval') .
		addHidden('referrer','contest.php?id='.$mycid) .
		addSubmit('Save') .
		addEndForm();

} elseif ( $cmd == 'delete' ) {

	$res = $DB->q('DELETE FROM removed_interval WHERE intervalid = %i', $_GET['intervalid']);
	echo "<p>Removed removed interval.</p>\n\n";

} else {
	error("Unknown cmd.");
}


require(LIBWWWDIR . '/footer.php');
