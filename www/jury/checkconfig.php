<?php
/**
 * Do various sanity checks on the system regarding data constraints,
 * permissions and the like. At the moment this only checks the constraints
 * on the contest table.
 *
 * $Id$
 */

require('init.php');
$title = 'Config Checker';
require('../header.php');
require('menu.php');
?>

<h1>Config Checker</h1>

<h2>Contests</h2>

<p>Current contest: <?php 
$cid = getCurContest();
if($cid == null) {
	// we need a valid 'current contest' at any time to function correctly
	err('No current contest found! System will not function.');
} else {
	$cid = (int)$cid;
	echo "<b>c$cid</b>";
}
echo "</p><p>Checking contests...</p>\n\n";

// get all contests
$res = $DB->q('SELECT UNIX_TIMESTAMP(starttime) as start, UNIX_TIMESTAMP(endtime) as end,
	UNIX_TIMESTAMP(lastscoreupdate) as lastsu, cid FROM contest ORDER BY cid');

while($cdata = $res->next()) {

	$haserrors = FALSE;
	
	echo "<p><b>c".$cdata['cid']."</b>: ";

	// endtime is before starttime: impossible
	if($cdata['end'] < $cdata['start']) {
		$haserrors = TRUE;
		err('Contest ends before it even starts!');
	}

	// the last score update time is not between start & endtime
	if(isset($cdata['lastsu']) &&
		($cdata['lastsu'] > $cdata['end'] || $cdata['lastsu'] < $cdata['start'] ) ) {
		$haserrors = TRUE;
		err('Lastscoreupdate is out of start/endtime range!');
	}

	// a check whether this contest overlaps in time with any other, the
	// system can only deal with exactly ONE current contest at any time.
	$overlaps = $DB->q('COLUMN SELECT cid FROM contest WHERE
		( (%i >= UNIX_TIMESTAMP(starttime) AND %i <= UNIX_TIMESTAMP(endtime)) OR
		(%i >= UNIX_TIMESTAMP(endtime) AND %i <= UNIX_TIMESTAMP(endtime)) ) AND
		cid != %i ORDER BY cid',
		$cdata['start'], $cdata['start'], $cdata['end'], $cdata['end'], $cdata['cid']);
	
	if(count($overlaps) > 0) {
		$haserrors = TRUE;
		err('This contest overlaps with the following contest(s): c'.implode(',c', $overlaps));
	}

	if(!$haserrors) echo "OK";

	echo "</p>\n\n";
}


echo "<p>End of config checker.</p>\n\n";

// helper to output an error message.
function err ($string) {
	echo "<b><u>ERROR</u>: ".$string."</b><br />\n";
}

require('../footer.php');
