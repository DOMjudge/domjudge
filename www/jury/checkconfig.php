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

requireAdmin();

require_once(SYSTEM_ROOT . '/lib/relations.php');

ob_implicit_flush();

/** helper to output an error message */
function err ($string) {
	echo "<b><u>ERROR</u>: ".htmlspecialchars($string)."</b><br />\n";
}
/** helper to output a warning message */
function warn ($string) {
	echo "<b><u>WARNING</u>: ".htmlspecialchars($string)."</b><br />\n";
}

?>

<h1>Config Checker</h1>

<h2>Software</h2>

<?php

echo "<p>You are using DOMjudge version " . htmlspecialchars(DOMJUDGE_VERSION) . "<br />\n" .
"PHP version " . htmlspecialchars(PHP_VERSION) . " ";

// are we using the right php version?
if( !function_exists('version_compare') || version_compare( '4.3.2',PHP_VERSION,'>=') ) {
	err('You need at least PHP version 4.3.2.');
} else {
	echo "OK";
}
echo "</p>\n\n";
?>

<h2>Authentication</h2>

<p>Checking authentication...
<?php
if ( !isset( $_SERVER['REMOTE_USER'] ) ) {
	warn("You are not using HTTP Authentication for the Jury interface.\n" .
		"Are you sure that the jury interface is adequately protected?\n");
} else {
	echo "OK, logged in as user <em>" . htmlspecialchars($_SERVER['REMOTE_USER']) .
		"</em>.\n";
}
?>
</p>

<h2>Websubmit</h2>

<p>
<?php 
if ( ENABLE_WEBSUBMIT_SERVER ) {

	echo "Checking for writeable incoming dir... ";
	if ( ! is_writable(INCOMINGDIR) ) {
		err("INCOMINGDIR '" . INCOMINGDIR . "' not writeable by webserver user!");
	} else {
		echo "OK";
	}
} else {
	echo "Websubmit disabled in config.";
} ?>
</p>


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
$res = $DB->q('SELECT cid,
               UNIX_TIMESTAMP(starttime)       AS start,
               UNIX_TIMESTAMP(endtime)         AS end,
               UNIX_TIMESTAMP(lastscoreupdate) AS lastsu,
               UNIX_TIMESTAMP(unfreezetime)    AS unfreeze
               FROM contest ORDER BY cid');

while($cdata = $res->next()) {

	$haserrors = FALSE;
	
	echo "<p><b>c".(int)$cdata['cid']."</b>: ";

	
	// this must always hold (lastscoreupdate and unfreezetime are optional,
	// but when set must also be in this range):
	// starttime < lastscoreupdate < endtime < unfreezetime
	if($cdata['end'] < $cdata['start']) {
		$haserrors = TRUE;
		err('Contest ends before it even starts!');
	}
	if(isset($cdata['lastsu']) &&
		($cdata['lastsu'] > $cdata['end'] || $cdata['lastsu'] < $cdata['start'] ) ) {
		$haserrors = TRUE;
		err('Lastscoreupdate is out of start/endtime range!');
	}
	if ( isset($cdata['unfreeze']) ) {
		if ( !isset($cdata['lastsu']) ) {
			$haserrors = TRUE;
			err('Unfreezetime set but no freeze time. That makes no sense.');
		}
		if ( $cdata['unfreeze'] < $cdata['lastsu'] || $cdata['unfreeze'] < $cdata['start'] ||
			$cdata['unfreeze'] < $cdata['end'] ) {
			$haserrors = TRUE;
			err('Unfreezetime must be larger than any of start/end/freezetimes.');
		}
	}

	// a check whether this contest overlaps in time with any other, the
	// system can only deal with exactly ONE current contest at any time.
	$overlaps = $DB->q('COLUMN SELECT cid FROM contest WHERE
	                    ( (%i >= UNIX_TIMESTAMP(starttime) AND %i <= UNIX_TIMESTAMP(endtime)) OR
	                      (%i >= UNIX_TIMESTAMP(endtime)   AND %i <= UNIX_TIMESTAMP(endtime)) ) AND
	                    cid != %i ORDER BY cid',
	                   $cdata['start'], $cdata['start'], $cdata['end'],
	                   $cdata['end'], $cdata['cid']);
	
	if(count($overlaps) > 0) {
		$haserrors = TRUE;
		err('This contest overlaps with the following contest(s): c' . 
			htmlspecialchars(implode(',c', $overlaps)));
	}

	if(!$haserrors) echo "OK";

	echo "</p>\n\n";
}


echo "<h2>Submissions</h2>\n\n<p>Checking submissions...<br />\n";

// check for non-existent problem references
$res = $DB->q('SELECT s.submitid, s.probid, s.cid FROM submission s
               LEFT OUTER JOIN problem p USING (probid) WHERE s.cid != p.cid');

if($res->count() > 0) {
	while($row = $res->next()) {
		err('Submission s' .  (int)$row['submitid'] . ' is for problem "' .
			htmlspecialchars($row['probid']) .
			'" while this problem is not found (in c'.(int)$row['cid'].')!');
	}
}

// check for submissions that have been marked by a judgehost but that
// have no judging-row
$res = $DB->q('SELECT s.submitid FROM submission s
               LEFT OUTER JOIN judging j USING (submitid)
               WHERE j.submitid IS NULL AND s.judgehost IS NOT NULL');

if($res->count() > 0) {
	while($row = $res->next()) {
		err('Submission s' . (int)$row['submitid'] . ' has a judgehost but no entry in judgings!');
	}
}


echo "</p>\n\n<h2>Judgings</h2>\n\n<p>Checking judgings...<br />\n";

// check for start/endtime problems and contestids
$res = $DB->q('SELECT s.submitid AS s_submitid, j.submitid AS j_submitid,
               judgingid, starttime, endtime, submittime, s.cid AS s_cid, j.cid AS j_cid
               FROM judging j LEFT OUTER JOIN submission s USING (submitid)
               WHERE (j.cid != s.cid) OR (s.submitid IS NULL) OR
               (j.endtime IS NOT NULL AND j.endtime < j.starttime) OR
               (j.starttime < s.submittime)');

if($res->count() > 0) {
	while($row = $res->next()) {
		$err = 'Judging j' . (int)$row['judgingid'] . '/s' . (int)$row['j_submitid'] . ' ';
		if(isset($row['endtime']) && $row['endtime'] < $row['starttime']) {
			err($err.'ended before it started!');
		}
		if($row['starttime'] < $row['submittime']) {
			err($err.'started before it was submitted!');
		}
		if(!isset($row['s_submitid'])) {
			err($err .'has no corresponding submitid (in c'.(int)$row['j_cid'] .')!');
		}
		if($row['s_cid'] != NULL && $row['s_cid'] != $row['j_cid']) {
			err('Judging j' .(int)$row['judgingid'] .
			    ' is from a different contest (c' . (int)$row['j_cid'] .
			    ') than its submission s' . (int)$row['j_submitid'] .
			    ' (c' . (int)$row['s_cid'] . ')!');
		}
	}
}

echo "</p>\n\n<h2>Teams</h2>\n\n<p>Checking teams...<br />\n";

if ( SHOW_AFFILIATIONS ) {
	$res = $DB->q('SELECT affilid FROM team_affiliation ORDER BY affilid');

	while ( $row = $res->next() ) {
		$affillogo = '../images/affiliations/' .
			urlencode($row['affilid']) . '.png';
		if ( ! file_exists ( $affillogo ) ) {
			err ("Affiliation " . $row['affilid'] .
				" does not have a logo (looking for $affillogo).");
		} elseif ( ! is_readable ( $affillogo ) ) {
			err ("Affiliation " . $row['affilid'] .
				" has a logo, but it's not readable ($affillogo).");
		}
	}
	
	$res = $DB->q('SELECT DISTINCT country FROM team_affiliation ORDER BY country');
	while ( $row = $res->next() ) {
		$cflag = '../images/countries/' .
			urlencode($row['country']) . '.png';
		if ( ! file_exists ( $cflag ) ) {
			err ("Country " . $row['country'] .
				" does not have a flag (looking for $cflag).");
		} elseif ( ! is_readable ( $cflag ) ) {
			err ("Country " . $row['country'] .
				" has a flag, but it's not readable ($cflag).");
		}
	}

}
echo "</p>\n\n";



echo "<h2>Referential Integrity</h2>\n\n";

echo "<p>Checking integrity of inter-table relationships...";

foreach ( $RELATIONS as $table => $foreign_keys ) {
	$res = $DB->q('SELECT * FROM ' . $table . ' ORDER BY ' . implode(',', $KEYS[$table]));
	while ( $row = $res->next() ) {
		foreach ( $foreign_keys as $foreign_key => $target ) {
			if ( !empty($row[$foreign_key]) ) {
				$f = explode('.', $target);
				if ( $DB->q("VALUE SELECT count(*) FROM $f[0] WHERE $f[1] = %s",
						$row[$foreign_key]) < 1 ) {
					err ("foreign key constraint fails for $table.$foreign_key = \"" .
						htmlspecialchars($row[$foreign_key]) . "\" (not found in $target)");
				}
			}
		}
	}
}

echo "</p>\n\n";

echo "<p>End of config checker.</p>\n\n";

require('../footer.php');
