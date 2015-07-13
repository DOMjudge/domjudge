<?php
/**
 * View the details of a specific rejudging
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$viewtypes = array(0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => "diff", 4 => 'all');

$view = 1; // default view == unverified

if ( isset($_REQUEST['view']) ) {
	// did someone press any of the view buttons?
	foreach ($viewtypes as $i => $name) {
		if ( isset($_REQUEST['view'][$i]) ) $view = $i;
	}
}

require('init.php');
$id = getRequestID();

$refresh = '15;url=rejudging.php?id=' . urlencode($id) . '&' .
	urlencode('view[' . $view . ']') . '=' . urlencode($viewtypes[$view]) .
	(isset($_REQUEST['old_verdict']) ? '&old_verdict=' . urlencode($_REQUEST['old_verdict']) : '') .
	(isset($_REQUEST['new_verdict']) ? '&new_verdict=' . urlencode($_REQUEST['new_verdict']) : '');

$title = 'Rejudging r'.@$id;

require(LIBWWWDIR . '/header.php');

if ( ! $id ) error("Missing or invalid rejudging id");

$todo = $DB->q('VALUE SELECT COUNT(*) FROM submission
                WHERE rejudgingid=%i', $id);
$done = $DB->q('VALUE SELECT COUNT(*) FROM judging
                WHERE rejudgingid=%i AND endtime IS NOT NULL', $id);
$todo -= $done;

$rejdata = $DB->q('TUPLE SELECT * FROM rejudging
                   WHERE rejudgingid=%i', $id);

if ( ! $rejdata ) error ("Missing rejudging data");

if ( isset($_REQUEST['apply']) ) {
	if ( $todo > 0 ) {
		error("$todo unfinished judgings left, cannot apply rejudging.");
	} else if ( isset($rejdata['endtime']) ) {
		error("Rejudging already " . ( $rejdata['valid'] ? 'applied.' : 'canceled.'));
	}

	$res = $DB->q('SELECT submitid, cid, teamid, probid
	               FROM submission
	               WHERE rejudgingid=%i', $id);

	auditlog('rejudging', $id, 'applying rejudge', '(start)');

	$time_start = microtime(TRUE);

	// no output buffering... we want to see what's going on real-time
	echo "<br/><p>Applying rejudge may take some time, please be patient:</p>\n";
	ob_implicit_flush(true);
	ob_end_flush();

	echo "<p>\n";
	while ( $row = $res->next() ) {
		echo "s" . htmlspecialchars($row['submitid']) . ", ";
		$DB->q('START TRANSACTION');
		// first invalidate old judging, maybe different from prevjudgingid!
		$DB->q('UPDATE judging SET valid=0
		        WHERE submitid=%i', $row['submitid']);
		// then set judging to valid
		$DB->q('UPDATE judging SET valid=1
		        WHERE submitid=%i AND rejudgingid=%i', $row['submitid'], $id);
		// remove relation from submission to rejudge
		$DB->q('UPDATE submission SET rejudgingid=NULL
		        WHERE submitid=%i', $row['submitid']);
		// last update cache
		calcScoreRow($row['cid'], $row['teamid'], $row['probid']);
		$DB->q('COMMIT');
	}
	echo "\n</p>\n";

	$DB->q('UPDATE rejudging
	        SET endtime=%s, userid_finish=%i
	        WHERE rejudgingid=%i', now(), $userdata['userid'], $id);

	auditlog('rejudging', $id, 'applying rejudge', '(end)');

	$time_end = microtime(TRUE);

	echo "<p>Rejudging <a href=\"rejudging.php?id=" . urlencode($id) .
		"\">r$id</a> applied in ".round($time_end - $time_start,2)." seconds.</p>\n\n";

	require(LIBWWWDIR . '/footer.php');
	return;
} else if ( isset($_REQUEST['cancel']) ) {
	if ( isset($rejdata['endtime']) ) {
		error("Rejudging already " . ( $rejdata['valid'] ? 'applied.' : 'canceled.'));
	}
	auditlog('rejudging', $id, 'canceling rejudge', '(start)');

	$res = $DB->q('SELECT submitid, cid, teamid, probid
	               FROM submission
	               WHERE rejudgingid=%i', $id);
	while ( $row = $res->next() ) {
		// restore old judgehost association
		$valid_judgehost = $DB->q('VALUE SELECT judgehost FROM judging
		                           WHERE submitid=%i AND valid=1', $row['submitid']);
		$DB->q('UPDATE submission SET rejudgingid = NULL, judgehost=%s
		        WHERE rejudgingid = %i', $valid_judgehost, $id);
	}
	$DB->q('UPDATE rejudging
	        SET endtime=%s, userid_finish=%i, valid=0
	        WHERE rejudgingid=%i', now(), $userdata['userid'], $id);

	auditlog('rejudging', $id, 'canceled rejudge', '(end)');
	header('Location: rejudging.php?id='.urlencode($id));
}


$userdata = $DB->q('KEYVALUETABLE SELECT userid, name FROM user
                    WHERE userid=%i OR userid=%i',
                   $rejdata['userid_start'], @$rejdata['userid_finish']);

echo '<br/><h1 style="display:inline;">Rejudging r' . $id .
	( $rejdata['valid'] ? '' : ' (canceled)' ) . "</h1>\n\n";

echo "<table>\n";
echo "<tr><td>Reason:</td><td>";
if ( empty($rejdata['reason']) ) {
	echo '<span class="nodata">none</span>';
} else {
	echo htmlspecialchars($rejdata['reason']);
}
echo "</td></tr>\n";
foreach ( array('userid_start' => 'Issued by',
                'userid_finish' => ($rejdata['valid'] ? 'Accepted' : 'Canceled') . ' by')
          as $user => $msg ) {
	if ( isset($rejdata[$user]) ) {
		echo "<tr><td>$msg:</td><td>" .
			'<a href="user.php?id=' . urlencode($rejdata[$user]) . '">' .
			htmlspecialchars($userdata[$rejdata[$user]])  .
			"</a></td></tr>\n";
	}
}
foreach (array('starttime' => 'Start time', 'endtime' => 'Apply time') as $time => $msg) {
	echo "<tr><td>$msg:</td><td>";
	if ( isset($rejdata[$time]) ) {
		echo printtime($rejdata[$time]);
	} else {
		echo '<span class="nodata">-</span>';
	}
	echo "</td></tr>\n";
}
if ( $todo > 0 ) {
	echo "<tr><td>Queued:</td><td>$todo unfinished judgings</td>\n";
}
echo "</table>\n\n";

if ( !isset($rejdata['endtime']) ) {
	echo addForm($pagename . '?id=' . urlencode($id))
		. addSubmit('cancel rejudging', 'cancel')
		. addEndForm();

	if ( $todo == 0 ) {
		echo addForm($pagename . '?id=' . urlencode($id))
			. addSubmit('apply rejudging', 'apply')
			. addEndForm();
	}
}

$verdicts = array('compiler-error'     => 'CTE',
                  'memory-limit'       => 'MLE',
                  'output-limit'       => 'OLE',
                  'run-error'          => 'RTE',
                  'timelimit'          => 'TLE',
                  'wrong-answer'       => 'WA',
                  'presentation-error' => 'PE', /* dropped since 5.0 */
                  'no-output'          => 'NO',
                  'correct'            => 'AC');

$orig_verdicts = $DB->q('KEYVALUETABLE SELECT submitid, result
                         FROM judging
                         WHERE judgingid IN
                         ( SELECT prevjudgingid
                           FROM judging
                           WHERE rejudgingid=%i AND endtime IS NOT NULL
                         )', $id);
$new_verdicts = $DB->q('KEYVALUETABLE SELECT submitid, result
                        FROM judging
                        WHERE rejudgingid=%i AND endtime IS NOT NULL', $id);

$table = array();
$used  = array();

// pre-fill $table to get a consistent ordering
foreach ($verdicts as $verdict => $abbrev) {
	foreach ($verdicts as $verdict2 => $abbrev2) {
		$table[$verdict][$verdict2] = array();
	}
}

// add unknown verdicts
// - to the end of $table (rows *and* columns)
// - as their own abbreviation to $verdicts
function addVerdict($unknownVerdict, &$verdicts, &$table) {
	// add column to existing rows
	foreach ($verdicts as $verdict => $abbrev) {
		$table[$verdict][$unknownVerdict] = array();
	}
	// add verdict to known verdicts
	$verdicts[$unknownVerdict] = $unknownVerdict;
	// add row
	$table[$unknownVerdict] = array();
	foreach ($verdicts as $verdict => $abbrev) {
		$table[$unknownVerdict][$verdict] = array();
	}
}

// generates a list with links to submissions
foreach ($new_verdicts as $submitid => $new_verdict) {
	$orig_verdict = $orig_verdicts[$submitid];

	// add verdicts to data structures if they are unkown up to now
	foreach (array($new_verdict, $orig_verdict) as $verdict) {
		if ( !array_key_exists($verdict, $verdicts) ) {
			addVerdict($verdict, $verdicts, $table);
		}
	}

	// mark them as used, so we can filter out unused cols/rows later
	$used[$orig_verdict] = TRUE;
	$used[$new_verdict]  = TRUE;

	// append submitid to list of orig->new verdicts
	$table[$orig_verdict][$new_verdict][] = $submitid;
}

echo "<h2>Overview</h2>\n";
echo '<table class="rejudgetable">' . "\n";
echo "<tr><th title=\"old vs. new verdicts\">-\+</th>"; // first column are table headers as well
// write table header
foreach ($verdicts as $verdict => $abbrev) {
	if ( !isset($used[$verdict]) ) {
		// filter out unused cols
		continue;
	}
	echo "<th title=\"$verdict\">$abbrev</th>\n";
}
echo "</tr>";

foreach ($table as $orig_verdict => $changed_verdicts) {
	if ( !isset($used[$orig_verdict]) ) {
		// filter out unused rows
		continue;
	}

	$orig_verdict_abbrev = $verdicts[$orig_verdict];
	echo "<tr><th title=\"$orig_verdict\">$orig_verdict_abbrev</th>";
	foreach ($changed_verdicts as $new_verdict => $submitids) {
		if ( !isset($used[$new_verdict]) ) {
			// filter out unused cols
			continue;
		}
		$new_verdict_abbrev = $verdicts[$new_verdict];
		$cnt = sizeof($submitids);
		$link = '';
		if ( $orig_verdict == $new_verdict ) {
			$class = "identical";
		} else if ( $cnt == 0 ) {
			$class = "zero";
		} else {
			// this case is the interesting one
			$class = "changed";
			$link = '<a href="rejudging.php?id=' . urlencode($id) . '&old_verdict=' .
				urlencode($orig_verdict) . '&new_verdict=' . urlencode($new_verdict) . '">';
		}
		echo "<td class=\"$class\">$link$cnt" .  ( empty($link) ? '' : '</a>' ) . "</td>\n";
	}
	echo "</tr>\n";
}
echo "</table>\n";

echo "<h2>Details</h2>\n";

$restrictions = array('rejudgingid' => $id);
if ( $viewtypes[$view] == 'unverified' ) $restrictions['verified'] = 0;
if ( $viewtypes[$view] == 'unjudged' ) $restrictions['judged'] = 0;
if ( $viewtypes[$view] == 'diff' ) $restrictions['rejudgingdiff'] = 1;
if ( isset($_REQUEST['old_verdict']) && $_REQUEST['old_verdict'] != 'all' ) {
	$restrictions['old_result'] = $_REQUEST['old_verdict'];
}
if ( isset($_REQUEST['new_verdict']) && $_REQUEST['new_verdict'] != 'all' ) {
	$restrictions['result'] = $_REQUEST['new_verdict'];
}

echo addForm($pagename, 'get') . "<p>Show submissions:\n" .
	addHidden('id', $id);
for($i=0; $i<count($viewtypes); ++$i) {
	echo addSubmit($viewtypes[$i], 'view['.$i.']', null, ($view != $i));
}
$verdicts = array_keys($verdicts);
array_unshift($verdicts, 'all');
echo "<br/>old verdict: " . addSelect('old_verdict', $verdicts, ( isset($_REQUEST['old_verdict']) ? $_REQUEST['old_verdict'] : 'all' ));
echo ", new verdict: " . addSelect('new_verdict', $verdicts, ( isset($_REQUEST['new_verdict']) ? $_REQUEST['new_verdict'] : 'all' ));
echo addSubmit('filter');
echo "</p>\n" . addEndForm();

putSubmissions($cdatas, $restrictions);

require(LIBWWWDIR . '/footer.php');
