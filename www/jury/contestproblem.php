<?php
/**
 * View/edit contests of a problem
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$cid = (int)@$_REQUEST['cid'];

$contest = $DB->q('MAYBETUPLE SELECT cid, contestname
		  FROM contest WHERE cid = %i', $cid);

if ( ! $contest ) error("Missing or invalid contest id");

// We may need to re-update the problem data, so make it a function.
function get_contestproblem_data()
{
	global $DB, $data, $cid;

	$data = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, shortname, name
			FROM gewis_contestproblem
			INNER JOIN problem USING (probid)
			WHERE gewis_contestproblem.cid = %i ORDER BY probid', $cid);
}
get_contestproblem_data();

$title = 'Problems for contest c'.htmlspecialchars(@$cid);

require(LIBWWWDIR . '/header.php');

echo "<h1>" . $title ."</h1>\n\n";

$result = '';
if ( isset($_POST['cid']) && IS_ADMIN ) {
	if ( isset($_POST['probid']) ) {
		$DB->q("INSERT INTO gewis_contestproblem (cid, probid) VALUES (%i, %i)", $cid, $_POST['probid']);
		$contestname = $DB->q("VALUE SELECT contestname FROM contest WHERE cid = %i", $cid);
		$problem = $DB->q("VALUE SELECT shortname FROM problem WHERE probid = %i", $_POST['probid']);
		$result .= "<li>Added problem ${problem} (p${_POST['probid']})</li>\n";
		auditlog('contest', $cid, 'added problem', "problem t{$_POST['probid']}");
	}
}
if ( !empty($result) ) {
	echo "<ul>\n$result</ul>\n\n";

	// Reload testcase data after updates
	get_contestproblem_data();
}

echo "<p><a href=\"contest.php?id=" . urlencode($cid) . "\">back to contest c" .
	htmlspecialchars($cid) . "</a></p>\n\n";

if ( count($data)==0 ) {
	echo "<p class=\"nodata\">No problem(s) yet.</p>\n";
} else {
	?>
<table class="list">
<thead><tr>
<th scope="col">ID</th><th scope="col">shortname</th><th scope="col">name</th><th></th>
</tr></thead>
<tbody>
<?php
}

foreach( $data as $probid => $row ) {
	$link = '<a href="problem.php?id=' . urlencode($probid) . '">';
	echo "<tr>";
	echo "<td class=\"tid\">" . $link .
	    "t" . htmlspecialchars($probid) ."</a></td>" .
	    "<td class=\"probid\">" . $link . htmlspecialchars($row["shortname"]) . "</a></td>" .
	    "<td class=\"name\">" . $link . htmlspecialchars($row["name"]) . "</a></td>";
		if ( IS_ADMIN ) {
			echo "<td><a href=\"delete.php?table=gewis_contestproblem&amp;probid=$probid&amp;cid=$cid&amp;referrer=" .
			    urlencode('contestproblem.php?cid='.$cid) . "\">" .
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
	     addHidden('cid', $cid);

	$pmap = $DB->q("KEYVALUETABLE SELECT p.probid, p.shortname
		    FROM problem p
		    LEFT JOIN gewis_contestproblem g ON p.probid = g.probid AND g.cid = %i
		    WHERE g.cid IS NULL
		    ORDER BY probid", $cid);
	if (!empty($pmap)) {
		?>
		<h3>Add team</h3>
		<table>
			<tr>
				<td>Team:</td>
				<td>
					<?php
					foreach ($pmap as $probid => $pname) {
						$pmap[$probid] = "p$probid: $pname";
					}
					echo addSelect('probid', $pmap, null, true);
					?>
				</td>
			</tr>
		</table>
		<?php

		echo "<br />" . addSubmit('Add problem') . addEndForm();
	}
}

require(LIBWWWDIR . '/footer.php');
