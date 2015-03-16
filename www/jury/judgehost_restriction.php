<?php
/**
 * View or add/edit a row in a judgehost_restriction
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');

$id = getRequestID();
$title = ucfirst((empty($_GET['cmd']) ? '' : htmlspecialchars($_GET['cmd']) . ' ') .
		 'judgehost restriction');

require(LIBWWWDIR . '/header.php');

// Get these lists here, as they are needed both on the edit and view pages.
$contests  = $DB->q("KEYVALUETABLE SELECT cid, CONCAT('c', cid, ' - ', shortname)
                     FROM contest ORDER BY cid");
$problems  = $DB->q("KEYVALUETABLE SELECT probid, CONCAT('p', probid, ' - ', name)
                     FROM problem ORDER BY probid");
$languages = $DB->q("KEYVALUETABLE SELECT langid, CONCAT(langid, ' - ', name)
                     FROM language ORDER BY langid");
$lists = array('contest' => $contests,
               'problem' => $problems,
               'language' => $languages);

if ( !empty($_GET['cmd']) ) {

	requireAdmin();

	$cmd = $_GET['cmd'];

	echo "<h2>$title</h2>\n\n";

	echo addForm('judgehost_restrictions.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		$row = $DB->q('MAYBETUPLE SELECT * FROM judgehost_restriction
		               WHERE restrictionid = %i', $id);
		if ( !$row ) error("Missing or invalid judgehost restriction id");

		$row['restrictions'] = json_decode($row['restrictions'], true);

		echo "<tr><td>ID:</td><td>" .
		     addHidden('keydata[0][restrictionid]', $row['restrictionid']) .
		     htmlspecialchars($row['restrictionid']) . "</td></tr>\n";
	}

	?>
<tr><td><label for="data_0__name_">Name:</label></td>
    <td><?php echo addInput('data[0][name]', @$row['name'], 15, 255, 'required')?></td></tr>
<?php

	foreach ( array('contest','problem','language') as $type ) {
		?>
<tr><td colspan="2">
<h3>Restrict to any of the following <?php echo $type; ?>s (leave empty to allow all)</h3>
</td></tr>
<?php
		if ( isset($row) && isset($row['restrictions'][$type]) ) {
			$start = count($row['restrictions'][$type]);

			foreach ( $row['restrictions'][$type] as $j => $restriction ) {
				echo '<tr><td></td><td>' .
				     addSelect("data[0][restrictions][$type][${j}]",
				               array(null => "-- Remove restriction") + $lists[$type],
				               $restriction, true) .
				     "</td></tr>\n";
			}
		} else {
			$start = 0;
		}

		for ($j = $start, $i = 0; $i < 10; $i++, $j = $i + $start) {
			echo '<tr><td></td><td>' .
			     addSelect("data[0][restrictions][$type][${j}]",
			               array(null => "-- Do not restrict") + $lists[$type],
				           null, true) .
			     "</td></tr>\n";
		}
	}

	$rejudge_own = !isset($row['restrictions']['rejudge_own']) ||
	  (bool)$row['restrictions']['rejudge_own'];

	echo '<tr><td>Rejudge on same judgehost:</td><td>' .
	    addRadioButton('data[0][restrictions][rejudge_own]', $rejudge_own, 1) .
	    '<label for="data_0__restrictions__rejudge_own_1">yes</label>' .
	    addRadioButton('data[0][restrictions][rejudge_own]', !$rejudge_own, 0) .
	    '<label for="data_0__restrictions__rejudge_own_0">no</label>' .
	    "</td></tr>\n";

	echo "</table>\n\n";

	echo addHidden('cmd', $cmd) .
	     addHidden('table','judgehost_restriction') .
	     addHidden('referrer', @$_GET['referrer']) .
	     addSubmit('Save') .
	     addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
	     addEndForm();

	require(LIBWWWDIR . '/footer.php');
	exit;
}

$data = $DB->q('TUPLE SELECT * FROM judgehost_restriction WHERE restrictionid = %i', $id);
if ( !$data ) error("Missing or invalid restriction id");

echo "<h1>Restriction: " . htmlspecialchars($data['name']) . "</h1>\n\n";

echo "<table>\n";
echo '<tr><td>ID:</td><td>' . htmlspecialchars($data['restrictionid']) . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' . htmlspecialchars($data['name']) . "</td></tr>\n";

$restrictions = json_decode($data['restrictions'], true);

foreach ( array('contest','problem','language') as $type ) {
	echo "<tr><td>Restrict to ${type}s:</td>";
	if ( empty($restrictions[$type]) ) {
		echo "<td class=\"nodata\">none</td></tr>\n";
	} else {
		$first = true;
		foreach ( $restrictions[$type] as $val ) {
			if ( !$first ) echo '<tr><td></td>';
			$first = false;
			echo "<td>" . $lists[$type][$val] . "</td></tr>\n";
		}
	}
}

echo '<tr><td>Rejudge by same judgehost:</td><td>' .
     printyn(!isset($restrictions['rejudge_own']) ||
             (bool)$restrictions['rejudge_own']) . "</td></tr>\n";

echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
	     editLink('judgehost_restriction', $data['restrictionid']) . "\n" .
	     delLink('judgehost_restriction','restrictionid',$data['restrictionid']) . "</p>\n\n";
}

echo "<h2>Judgehosts having restriction " . htmlspecialchars($data['name']) . "</h2>\n\n";

$judgehosts = $DB->q('SELECT hostname, active FROM judgehost WHERE restrictionid = %i', $id);
if ( $judgehosts->count() == 0 ) {
	echo "<p class=\"nodata\">no judgehosts</p>\n\n";
} else {
	echo "<table class=\"list\">\n<thead>\n" .
	     "<tr><th scope=\"col\">hostname</th><th scope=\"col\">active</th></tr>\n" .
	     "</thead>\n<tbody>\n";
	while ($judgehost = $judgehosts->next()) {
		$link = '<a href="judgehost.php?id=' . urlencode($judgehost['hostname']) . '">';
		echo "<tr".( $judgehost['active'] ? '': ' class="disabled"').
		     "><td>"
		     . $link . htmlspecialchars($judgehost['hostname']) .
		     "</a></td><td>" .
		     $link . printyn($judgehost['active']) .
		     "</a></td></tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

require(LIBWWWDIR . '/footer.php');
