<?php
/**
 * View the restrictions that can be assigned to judgehosts
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

// Forward to edit.php, after setting the JSON data correctly
// TODO set json data
@$cmd = @$_POST['cmd'];
if ( $cmd == 'add' || $cmd == 'edit' ) {
	if ( isset($_POST['data'][0]['restrictions']) ) {
		// key = restriction type, value = true if and only if integer
		$restrictions = array(
			'contest' => true,
			'problem' => true,
			'language' => false
		);
		foreach ( $restrictions as $restriction_name => $is_int ) {
			$restriction = array();
			foreach ( $_POST['data'][0]['restrictions'][$restriction_name] as $restriction_value ) {
				if ( $restriction_value !== '' ) {
					$restriction[] = $is_int ? intval($restriction_value) : $restriction_value;
				}
			}
			$_POST['data'][0]['restrictions'][$restriction_name] = $restriction;
		}
	}
	$_POST['data'][0]['restrictions'] = json_encode($_POST['data'][0]['restrictions']);
	require_once('edit.php');
	exit;
}

require('init.php');
$title = 'Judgehost restrictions';

require(LIBWWWDIR . '/header.php');

echo "<h1>Judgehost Restrictions</h1>\n\n";

$res = $DB->q('SELECT judgehost_restriction.*, COUNT(hostname) AS numjudgehosts
               FROM judgehost_restriction LEFT JOIN judgehost USING (restrictionid)
               GROUP BY judgehost_restriction.restrictionid ORDER BY restrictionid');

if( $res->count() == 0 ) {
	echo "<p class=\"nodata\">No judgehost restrictions defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\">ID</th>" .
	     "<th scope=\"col\">name</th><th scope=\"col\">#contests</th>\n" .
	     "<th scope=\"col\">#problems</th><th scope=\"col\">#languages</th>\n" .
	     "<th scope=\"col\">#linked judgehosts</th>\n" .
	     "</thead>\n<tbody>\n";

	while($row = $res->next()) {
		$restrictions = json_decode($row['restrictions'], true);
		$link = '<a href="judgehost_restriction.php?id=' . (int)$row['restrictionid'] . '">';
		echo '<tr><td>' . $link. (int)$row['restrictionid'] .
		     '</a></td><td>' . $link . htmlspecialchars($row['name']) .
		     '</a></td><td class="tdright">' . $link . count($restrictions['contest']) .
		     '</a></td><td class="tdright">' . $link . count($restrictions['problem']) .
		     '</a></td><td class="tdright">' . $link . count($restrictions['language']) .
		     '</a></td><td class="tdright">' . $link . (int)$row['numjudgehosts'] .
		     '</a></td>';
		if ( IS_ADMIN ) {
			echo "<td class=\"editdel\">" .
			     editLink('judgehost_restriction', $row['restrictionid']) . " " .
			     delLink('judgehost_restriction', 'restrictionid', $row['restrictionid']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('judgehost_restriction') . "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
