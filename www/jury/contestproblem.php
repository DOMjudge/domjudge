<?php
/**
 * View/edit contests of a problem
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$probid = (int)@$_REQUEST['probid'];

$prob = $DB->q('MAYBETUPLE SELECT probid, shortname, name
		FROM problem WHERE probid = %i', $probid);

if ( ! $prob ) error("Missing or invalid problem id");

// We may need to re-update the testcase data, so make it a function.
function get_contestproblem_data()
{
	global $DB, $data, $probid;

	$data = $DB->q('KEYTABLE SELECT cid AS ARRAYKEY, contestname
			FROM gewis_contestproblem
			NATURAL JOIN contest
			WHERE probid = %i ORDER BY cid', $probid);
}
get_contestproblem_data();

$title = 'Testcases for problem p'.htmlspecialchars(@$probid);

require(LIBWWWDIR . '/header.php');

echo "<h1>" . $title ."</h1>\n\n";

$result = '';
if ( isset($_POST['probid']) && IS_ADMIN ) {
	if ( isset($_POST['cid']) ) {
		$DB->q("INSERT INTO gewis_contestproblem (probid, cid) VALUES (%i, %i)", $probid, $_POST['cid']);
		$contestname = $DB->q("VALUE SELECT contestname FROM contest WHERE cid = %i", $_POST['cid']);
		$result .= "<li>Added to contest ${contestname} (c${_POST['cid']})</li>\n";
		auditlog('problem', $probid, 'added contest', "contest c{$_POST['cid']}");
	}
}
if ( !empty($result) ) {
	echo "<ul>\n$result</ul>\n\n";

	// Reload testcase data after updates
	get_contestproblem_data();
}

echo "<p><a href=\"problem.php?id=" . urlencode($probid) . "\">back to problem p" .
	htmlspecialchars($probid) . "</a></p>\n\n";

if ( count($data)==0 ) {
	echo "<p class=\"nodata\">No contest(s) yet.</p>\n";
} else {
	?>
<table class="list">
<thead><tr>
<th scope="col">CID</th><th scope="col">name</th><th></th>
</tr></thead>
<tbody>
<?php
}

foreach( $data as $cid => $row ) {
	$link = '<a href="contest.php?id=' . urlencode($cid) . '">';
	echo "<tr>";
	echo "<td class=\"cid\">" . $link .
	    "c" . htmlspecialchars($cid) ."</a></td>" .
	    "<td class=\"name\">" . $link . htmlspecialchars($row["contestname"]) . "</a></td>";
		if ( IS_ADMIN ) {
			echo "<td><a href=\"delete.php?table=gewis_contestproblem&amp;cid=$cid&amp;probid=$probid&amp;referrer=" .
			    urlencode('contestproblem.php?probid='.$probid) . "\">" .
			    "<img src=\"../images/delete.png\" alt=\"delete\"" .
			    " title=\"remove this problem from this contest\" class=\"picto\" /></a></td>";
		} else {
			echo "<td></td>";
		}
	echo "</tr>\n";
}

if ( count($data)!=0 ) echo "</tbody>\n</table>\n";

if ( IS_ADMIN ) {
	echo addForm($pagename, 'post', null, 'multipart/form-data') .
	     addHidden('probid', $probid);
	echo "<script type=\"text/javascript\">\n";
	foreach ( $data as $rank => $row ) {
		echo "hideTcDescEdit($rank);\n";
	}
	echo "</script>\n\n";

?>
<h3>Add to contest</h3>

<table>
<tr><td>Contest: </td><td>
<?php
$cmap = $DB->q("KEYVALUETABLE SELECT cid,contestname FROM contest ORDER BY cid DESC");
foreach($cmap as $cid => $cname) {
	$cmap[$cid] = "c$cid: $cname";
}
echo addSelect('cid', $cmap, null, true);
?>
</td></tr>
</table>
<?php

	echo "<br />" . addSubmit('Add to contest') . addEndForm();
}

require(LIBWWWDIR . '/footer.php');
