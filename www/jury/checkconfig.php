<?php
/**
 * Do various sanity checks on the system regarding data constraints,
 * permissions and the like. At the moment this only contains some basic
 * checks but this can be extended in the future.
 *
 * $Id$
 */

require('init.php');
$title = 'Config Checker';
require('../header.php');
require('menu.php');
?>

<h1>Config Checker</h1>

<h2>Software</h2>

<?php
$phpver = PHP_VERSION;

echo "<p>You are using DOMjudge version " . DOMJUDGE_VERSION . "<br />\n" .
"PHP version " . $phpver;

// are we using the right php version?
if($phpver{0} < 4 || ($phpver{0} == 4 && $phpver{2} == 0 ) ) {
	err('You need at least PHP version 4.1.0.');
} else {
	echo " OK";
}
echo "</p>\n\n";
?>


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

echo "<h2>Submissions</h2>\n\n<p>Checking submissions...<br />\n";

$res = $DB->q('SELECT s.submitid,s.probid,s.cid FROM submission s LEFT OUTER JOIN problem p
	USING(probid)
	WHERE s.cid != p.cid');

if($res->count() > 0) {
	while($row = $res->next()) {
		err('Submission s' .  $row['submitid'] . ' is for problem "' .
			$row['probid'] . '" while this problem is not found (in c'.$row['cid'].')!');
	}
}


echo "</p>\n\n<h2>Judgings</h2>\n\n<p>Checking judgings...<br />\n";

$res = $DB->q('SELECT s.submitid as s_submitid, j.submitid as j_submitid,
	judgingid, starttime, endtime, submittime, s.cid AS s_cid, j.cid AS j_cid
	FROM judging j LEFT OUTER JOIN submission s USING(submitid)
	WHERE  (j.cid != s.cid) OR
		(j.endtime IS NOT NULL AND j.endtime < j.starttime) OR (j.starttime < s.submittime) OR
		(s.submitid IS NULL) ');

if($res->count() > 0) {
	while($row = $res->next()) {
		$err = 'Judging j' . $row['judgingid'] . '/s' . $row['j_submitid'] . ' ';
		if(isset($row['endtime']) && $row['endtime'] < $row['starttime']) {
			err($err.'ended before it started!');
		}
		if($row['starttime'] < $row['submittime']) {
			err($err.'started before it was submitted!');
		}
		if(!isset($row['s_submitid'])) {
			err($err .'has no corresponding submitid (in c'.$row['j_cid'] .')!');
		}
		if($row['s_cid'] != NULL && $row['s_cid'] != $row['j_cid']) {
			err('Judging j'.$row['judgingid'].' is from a different contest (c' . $row['j_cid'] .
				') than its submission s'.$row['j_submitid'] . ' (c' . $row['s_cid'] . ')!');
		}
	}
}

echo "</p>\n\n";

echo "<p>End of config checker.</p>\n\n";

// helper to output an error message.
function err ($string) {
	echo "<b><u>ERROR</u>: ".$string."</b><br />\n";
}

require('../footer.php');
