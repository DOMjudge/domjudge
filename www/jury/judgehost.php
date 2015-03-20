<?php
/**
 * View judgehost details
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID(FALSE);
if ( empty($id) ) error("Missing judge hostname");

$refresh = '15;url=judgehost.php?id='.urlencode($id);

if ( isset($_REQUEST['cmd']) &&
	( $_REQUEST['cmd'] == 'activate' || $_REQUEST['cmd'] == 'deactivate' ) ) {

	requireAdmin();

	$DB->q('UPDATE judgehost SET active = %i WHERE hostname = %s',
	       ($_REQUEST['cmd'] == 'activate' ? 1 : 0), $id);
	auditlog('judgehost', $id, 'marked ' . ($_REQUEST['cmd']=='activate'?'active':'inactive'));

	// the request came from the overview page
	if ( isset($_GET['cmd']) ) {
		header("Location: judgehosts.php");
		exit;
	}
}

$row = $DB->q('TUPLE SELECT judgehost.*, r.name AS restrictionname
               FROM judgehost
               LEFT JOIN judgehost_restriction r USING (restrictionid)
               WHERE hostname = %s', $id);

$title = 'Judgehost '.htmlspecialchars($row['hostname']);

require(LIBWWWDIR . '/header.php');

echo "<h1>Judgehost ".printhost($row['hostname'])."</h1>\n\n";

?>

<table>
<tr><td>Name:  </td><td><?php echo printhost($row['hostname'], TRUE)?></td></tr>
<tr><td>Active:</td><td><?php echo printyn($row['active'])?></td></tr>
<tr><td>Restriction:</td><td>
	<?php if ( is_null($row['restrictionname']) ) {
		echo '<i>None</i>';
	} else {
		echo '<a href="judgehost_restriction.php?id=' . urlencode($row['restrictionid']) . '">' .
		     htmlspecialchars($row['restrictionname']) . '</a>';
	}
	?>
</td></tr>
<tr><td>Status:</td><td>
<?php
if ( empty($row['polltime']) ) {
	echo "Judgehost never checked in.";
} else {
	$reltime = floor(difftime(now(),$row['polltime']));
	if ( $reltime < dbconfig_get('judgehost_warning',30) ) {
		echo "OK";
	} else if ( $reltime < dbconfig_get('judgehost_critical',120) ) {
		echo "Warning";
	} else {
		echo "Critical";
	}
	echo ", time since judgehost last checked in: " . printtimediff($row['polltime']) . 's.';
}
?>
</td></tr>
</table>

<?php
if ( IS_ADMIN ) {
	$cmd = ($row['active'] == 1 ? 'deactivate' : 'activate');

	echo addForm($pagename) . "<p>\n" .
		addHidden('id',  $row['hostname']) .
		addHidden('cmd', $cmd) .
		addSubmit($cmd) . "</p>\n" .
		addEndForm();
}

echo rejudgeForm('judgehost', $row['hostname']);

if ( IS_ADMIN ) {
	echo "<p>" . delLink('judgehost','hostname',$row['hostname']) . "</p>\n\n";
}

echo "<h3>Judgings by " . printhost($row['hostname']) . "</h3>\n\n";

// get the judgings for a specific key and value pair
// select only specific fields to avoid retrieving large blobs
$cids = getCurContests(FALSE);
if ( !empty($cids) ) {
	$res = $DB->q('SELECT judgingid, submitid, starttime, endtime, judgehost,
	               result, verified, valid FROM judging
	               WHERE cid IN (%Ai) AND judgehost = %s
	               ORDER BY starttime DESC, judgingid DESC',
	              $cids, $row['hostname']);
}

if( empty($cids) || $res->count() == 0 ) {
	echo "<p class=\"nodata\">No judgings.</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\" class=\"sorttable_numeric\">ID</th><th " .
	     "scope=\"col\">started</th><th scope=\"col\">runtime</th><th " .
	     "scope=\"col\">result</th><th scope=\"col\">valid</th><th " .
	     "scope=\"col\">verified</th></tr>\n</thead>\n<tbody>\n";

	while( $jud = $res->next() ) {
		if ( empty($jud['endtime']) ) {
			if ( $jud['valid'] ) {
				$runtime = printtimediff($jud['starttime'], NULL);
			} else {
				$runtime = '[aborted]';
			}
		} else {
			$runtime = printtimediff($jud['starttime'], $jud['endtime']);
		}
		$link = ' href="submission.php?id=' . (int)$jud['submitid'] .
			'&amp;jid=' . (int)$jud['judgingid'] . '"';
		echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';
		echo "<td><a$link>j" . (int)$jud['judgingid'] . '</a></td>';
		echo "<td><a$link>" . printtime($jud['starttime']) . '</a></td>';
		echo "<td><a$link>" . $runtime . '</a></td>';
		echo "<td><a$link>" . printresult(@$jud['result'], $jud['valid']) . '</a></td>';
		echo "<td class=\"tdcenter\"><a$link>" . printyn($jud['valid']) . '</a></td>';
		echo "<td class=\"tdcenter\"><a$link>" . printyn($jud['verified']) . '</a></td>';
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}


require(LIBWWWDIR . '/footer.php');
