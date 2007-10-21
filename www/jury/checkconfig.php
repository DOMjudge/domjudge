<?php
/**
 * Do various sanity checks on the system regarding data constraints,
 * permissions and the like. At the moment this only contains some basic
 * checks but this can be extended in the future.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Config Checker';
require('../header.php');

requireAdmin();

echo "<h1>Config Checker</h1>\n\n";

/** Print the output of phpinfo(), which may be useful to check which settings
 *  PHP is actually using. */
if ( $_SERVER['QUERY_STRING'] == 'phpinfo' ) {
	$ret = "<p><a href=\"./checkconfig.php\">return to config checker</a></p>\n\n";
	echo $ret;
	echo "<h2>PHP Information</h2>\n\n";
	phpinfo();
	echo $ret;
	require('../footer.php');
	exit;
}

require_once(SYSTEM_ROOT . '/lib/relations.php');
require_once('checkers.php');

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
echo " <a href=\"?phpinfo\">(phpinfo)</a></p>\n\n";

$t_h = include_highlighter();
echo "<p>Optional PEAR Text_Highlighter class is ";
echo $t_h ? "available" : "not available.\n" .
	"Install it in PHP's include path to get better source syntax highlighting";
echo ".</p>\n\n";
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
		err("INCOMINGDIR '" . INCOMINGDIR . "' not writeable by webserver user");
	} else {
		echo "OK";
	}
} else {
	echo "Websubmit disabled in config.";
} ?>
</p>


<h2>Contests</h2>

<p>Current contest: <?php 

if($cid == null) {
	// we need a valid 'current contest' at any time to function correctly
	err('No current contest found. System will not function.');
} else {
	$cid = (int)$cid;
	echo "<b>c$cid</b>";
}
echo "</p><p>Checking contests...</p>\n\n";

// get all contests
$res = $DB->q('SELECT * FROM contest ORDER BY cid');

while($cdata = $res->next()) {

	echo "<p><b>c".(int)$cdata['cid']."</b>: ";

	$CHECKER_ERRORS = array();
	check_contest($cdata, array('cid' => $cdata['cid']));
	if ( count ( $CHECKER_ERRORS ) > 0 ) {
		foreach($CHECKER_ERRORS as $chk_err) {
			err($chk_err);
		}
	} else {
		echo "OK";
	}

	echo "</p>\n\n";
}

echo "<h2>Problems</h2>\n\n<p>Checking problems...<br />\n";

$res = $DB->q('SELECT * FROM problem ORDER BY probid');

if($res->count() > 0) {
	while($row = $res->next()) {
		$CHECKER_ERRORS = array();
		check_problem($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				err($row['probid'].': ' . $chk_err);
			}
		}
	}
}

echo "<h2>Languages</h2>\n\n<p>Checking languages...<br />\n";

$res = $DB->q('SELECT * FROM language ORDER BY langid');

if($res->count() > 0) {
	while($row = $res->next()) {
		$CHECKER_ERRORS = array();
		check_language($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				err($row['langid'].': ' . $chk_err);
			}
		}
	}
}


echo "<h2>Submissions</h2>\n\n<p>Checking submissions...<br />\n";

// check for non-existent problem references
$res = $DB->q('SELECT s.submitid, s.probid, s.cid FROM submission s
               LEFT OUTER JOIN problem p USING (probid) WHERE s.cid != p.cid');

if($res->count() > 0) {
	while($row = $res->next()) {
		err('Submission s' .  $row['submitid'] . ' is for problem "' .
			$row['probid'] .
			'" while this problem is not found (in c'. $row['cid'] . ')');
	}
}

$res = $DB->q('SELECT * FROM submission ORDER BY submitid');

if($res->count() > 0) {
	while($row = $res->next()) {
		$CHECKER_ERRORS = array();
		check_submission($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				err($row['submitid'].': ' . $chk_err);
			}
		}
	}
}

// check for submissions that have been marked by a judgehost but that
// have no judging-row
$res = $DB->q('SELECT s.submitid FROM submission s
               LEFT OUTER JOIN judging j USING (submitid)
               WHERE j.submitid IS NULL AND s.judgehost IS NOT NULL');

if($res->count() > 0) {
	while($row = $res->next()) {
		err('Submission s' . $row['submitid'] . ' has a judgehost but no entry in judgings');
	}
}


echo "</p>\n\n<h2>Judgings</h2>\n\n<p>Checking judgings...<br />\n";

// check for more than one valid judging for a submission
$res = $DB->q('SELECT submitid, SUM(valid) as numvalid
	FROM judging GROUP BY submitid HAVING numvalid > 1');
if ( $res->count() > 0 ) {
	while($row = $res->next()) {
		err('Submission s' . $row['submitid'] . ' has more than one valid judging (' .
			$row['numvalid'] . ')');
	}
}

// check for start/endtime problems and contestids
$res = $DB->q('SELECT s.submitid AS s_submitid, j.submitid AS j_submitid,
               judgingid, starttime, endtime, submittime, s.cid AS s_cid, j.cid AS j_cid
               FROM judging j LEFT OUTER JOIN submission s USING (submitid)
               WHERE (j.cid != s.cid) OR (s.submitid IS NULL) OR
               (j.endtime IS NOT NULL AND j.endtime < j.starttime) OR
               (j.starttime < s.submittime)');

if($res->count() > 0) {
	while($row = $res->next()) {
		$err = 'Judging j' . $row['judgingid'] . '/s' . $row['j_submitid'] . ' ';
		$CHECKER_ERRORS = array();
		if(!isset($row['s_submitid'])) {
			$CHECKER_ERRORS[] = 'has no corresponding submitid (in c'.$row['j_cid'] .')';
		}
		if($row['s_cid'] != NULL && $row['s_cid'] != $row['j_cid']) {
			$CHEKCER_ERRORS[] = 'Judging j' .$row['judgingid'] .
			    ' is from a different contest (c' . $row['j_cid'] .
			    ') than its submission s' . $row['j_submitid'] .
			    ' (c' . $row['s_cid'] . ')';
		}
		check_judging($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				err($err.': ' . $chk_err);
			}
		}
	}
}

echo "</p>\n\n<h2>Teams</h2>\n\n<p>Checking teams...<br />\n";

$res = $DB->q('SELECT * FROM team ORDER BY login');

if($res->count() > 0) {
	while($row = $res->next()) {
		$CHECKER_ERRORS = array();
		check_team($row);
		if ( count ( $CHECKER_ERRORS ) > 0 ) {
			foreach($CHECKER_ERRORS as $chk_err) {
				err($row['login'].': ' . $chk_err);
			}
		}
	}
}

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
						$row[$foreign_key] . "\" (not found in $target)");
				}
			}
		}
	}
}

echo "</p>\n\n";

echo "<p>End of config checker.</p>\n\n";

require('../footer.php');
