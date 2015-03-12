<?php
/**
 * View the problems
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Problems';

require(LIBWWWDIR . '/header.php');

echo "<h1>Problems</h1>\n\n";

// Select all data
$res = $DB->q('SELECT p.probid,p.name,p.timelimit,p.memlimit,p.outputlimit,
               p.problemtext_type, COUNT(testcaseid) AS testcases
               FROM problem p
               LEFT JOIN testcase USING (probid)
               GROUP BY probid ORDER BY probid');

// Get number of contests per problem
$contestinfo = $DB->q("TABLE SELECT probid, cid
                       FROM contestproblem");
$contestproblems = array();
foreach ($contestinfo as $row) {
	if ( !isset($contestproblems[$row['probid']]) ) {
		$contestproblems[$row['probid']] = array();
	}
	$contestproblems[$row['probid']][] = $row['cid'];
}

if( $res->count() == 0 ) {
	echo "<p class=\"nodata\">No problems defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\">ID</th><th scope=\"col\">name</th>" .
	     "<th scope=\"col\" class=\"sorttable_numeric\"># contests</th>" .
	     "<th scope=\"col\">time<br />limit</th>" .
	     "<th scope=\"col\">memory<br />limit</th>" .
	     "<th scope=\"col\">output<br />limit</th>" .
	     "<th scope=\"col\">test<br />cases</th>" .
	     "<th scope=\"col\"></th>" .
	    ( IS_ADMIN ? "<th scope=\"col\"></th><th scope=\"col\"></th>" : '' ) .
	     "</tr></thead>\n<tbody>\n";

	$lastcid = -1;

	while($row = $res->next()) {
		$classes = array();
		if ( count(array_intersect($contestproblems[$row['probid']], $cids)) == 0 ) $classes[] = 'disabled';
		$link = '<a href="problem.php?id=' . urlencode($row['probid']) . '">';

		echo "<tr class=\"" . implode(' ',$classes) .
			"\"><td>" . $link . "p" .
				htmlspecialchars($row['probid'])."</a>".
			"</td><td>" . $link . htmlspecialchars($row['name'])."</a>".
			"</td><td>".
			$link . htmlspecialchars(count($contestproblems[$row['probid']])) . "</a>" .
			"</td><td>" . $link . (int)$row['timelimit'] . "</a>" .
			"</td><td>" . $link . (isset($row['memlimit']) ? (int)$row['memlimit'] : '-') . "</a>" .
			"</td><td>" . $link . (isset($row['outputlimit']) ? (int)$row['outputlimit'] : '-') . "</a>" .
			"</td><td><a href=\"testcase.php?probid=" . $row['probid'] .
			"\">" . $row['testcases'] . "</a></td>";
		if ( !empty($row['problemtext_type']) ) {
			echo '<td title="view problem description">' .
			     '<a href="problem.php?id=' . urlencode($row['probid']) .
			     '&amp;cmd=viewtext"><img src="../images/' . urlencode($row['problemtext_type']) .
			     '.png" alt="problem text" /></a></td>';
		} else {
			echo '<td></td>';
		}
		if ( IS_ADMIN ) {
			echo '<td title="export problem as zip-file">' .
			     exportLink($row['probid']) . '</td>' .
			     "<td class=\"editdel\">" .
			     editLink('problem', $row['probid']) . " " .
			     delLink('problem','probid',$row['probid']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('problem') . "</p>\n\n";
	if ( class_exists("ZipArchive") ) {
		$contests = $DB->q("KEYVALUETABLE SELECT cid, CONCAT('c', cid, ': ' , shortname, ' - ', contestname) FROM contest");
		$values = array(-1 => 'Do not link to a contest');
		foreach ($contests as $cid => $contest) {
			$values[$cid] = $contest;
		}
		echo "\n" . addForm('problem.php', 'post', null, 'multipart/form-data') .
		     'Contest: ' .
		     addSelect('contest', $values, -1, true) .
		     'Problem archive(s): ' .
		     addFileField('problem_archive[]', null, ' required multiple accept="application/zip"') .
		     addSubmit('Upload', 'upload') .
		     addEndForm() . "\n";
	}
}

require(LIBWWWDIR . '/footer.php');
