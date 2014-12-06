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

if ( !empty($_GET['cmd']) ):

	requireAdmin();

	$cmd = $_GET['cmd'];

	echo "<h2>$title</h2>\n\n";

	echo addForm('judgehost_restrictions.php');

	echo "<table>\n";

	$contests = $DB->q("KEYVALUETABLE SELECT cid, CONCAT('c', cid, ' - ', shortname) FROM contest ORDER BY cid");
	$problems = $DB->q("KEYVALUETABLE SELECT probid, CONCAT('p', probid, ' - ', name) FROM problem ORDER BY probid");
	$languages = $DB->q("KEYVALUETABLE SELECT langid, CONCAT(langid, ' - ', name) FROM language ORDER BY langid");

	if ( $cmd == 'edit' ) {
		$row = $DB->q('MAYBETUPLE SELECT * FROM judgehost_restriction WHERE restrictionid = %i', $id);
		if ( !$row ) error("Missing or invalid judgehost restriction id");

		$row['restrictions'] = json_decode($row['restrictions'], true);

		echo "<tr><td>ID:</td><td>" .
		     addHidden('keydata[0][restrictionid]', $row['restrictionid']) .
		     htmlspecialchars($row['restrictionid']) . "</td></tr>\n";
	}

	?>

	<tr><td><label for="data_0__restrictionname_">Name:</label></td>
		<td><?php echo addInput('data[0][restrictionname]', @$row['restrictionname'], 15, 255, 'required')?></td></tr>

	<tr><td colspan="2">
		<h3>Restrict to any of the following contests (leave empty to allow all)</h3>
	</td></tr>

	<?php
	if ( isset($row) && isset($row['restrictions']['contest']) ) {
		$start = count($row['restrictions']['contest']);

		foreach ( $row['restrictions']['contest'] as $j => $restriction ) {
			?><tr><td></td><td><?php echo addSelect("data[0][restrictions][contest][${j}]", array(null => "-- Remove restriction") + $contests, $restriction, true)?></td></tr><?php
		}
	} else {
		$start = 0;
	}
	?>

	<?php for ($j = $start, $i = 0; $i < 10; $i++, $j = $i + $start): ?>
	<tr><td></td><td><?php echo addSelect("data[0][restrictions][contest][${j}]", array(null => "-- Do not restrict") + $contests, null, true)?></td></tr>
	<?php endfor; ?>

	<tr><td colspan="2">
		<h3>Restrict to any of the following problems (leave empty to allow all)</h3>
	</td></tr>

	<?php
	if ( isset($row) && isset($row['restrictions']['problem']) ) {
		$start = count($row['restrictions']['problem']);

		foreach ( $row['restrictions']['problem'] as $j => $restriction ) {
			?><tr><td></td><td><?php echo addSelect("data[0][restrictions][problem][${j}]", array(null => "-- Remove restriction") + $problems, $restriction, true)?></td></tr><?php
		}
	} else {
		$start = 0;
	}
	?>

	<?php for ($j = $start, $i = 0; $i < 10; $i++, $j = $i + $start): ?>
	<tr><td></td><td><?php echo addSelect("data[0][restrictions][problem][${j}]", array(null => "-- Do not restrict") + $problems, null, true)?></td></tr>
	<?php endfor; ?>

	<tr><td colspan="2">
		<h3>Restrict to any of the following languages (leave empty to allow all)</h3>
	</td></tr>

	<?php
	if ( isset($row) && isset($row['restrictions']['language']) ) {
		$start = count($row['restrictions']['language']);

		foreach ( $row['restrictions']['language'] as $j => $restriction ) {
			?><tr><td></td><td><?php echo addSelect("data[0][restrictions][language][${j}]", array(null => "-- Remove restriction") + $languages, $restriction, true)?></td></tr><?php
		}
	} else {
		$start = 0;
	}
	?>

	<?php for ($j = $start, $i = 0; $i < 10; $i++, $j = $i + $start): ?>
	<tr><td></td><td><?php echo addSelect("data[0][restrictions][language][${j}]", array(null => "-- Do not restrict") + $languages, null, true)?></td></tr>
	<?php endfor; ?>

	</table>

	<?php
	echo addHidden('cmd', $cmd) .
	     addHidden('table','judgehost_restriction') .
	     addHidden('referrer', @$_GET['referrer']) .
	     addSubmit('Save') .
	     addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
	     addEndForm();

	require(LIBWWWDIR . '/footer.php');
	exit;

endif;

$data = $DB->q('TUPLE SELECT * FROM judgehost_restriction WHERE restrictionid = %i', $id);
if ( !$data ) error("Missing or invalid restriction id");

echo "<h1>Restriction: " . htmlspecialchars($data['restrictionname']) . "</h1>\n\n";

echo "<table>\n";
echo '<tr><td>ID:</td><td>' . htmlspecialchars($data['restrictionid']) . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' . htmlspecialchars($data['restrictionname']) . "</td></tr>\n";

$restrictions = json_decode($data['restrictions'], true);
$contests = $DB->q("KEYVALUETABLE SELECT cid, CONCAT('c', cid, ' - ', shortname) FROM contest ORDER BY cid");
$problems = $DB->q("KEYVALUETABLE SELECT probid, CONCAT('p', probid, ' - ', name) FROM problem ORDER BY probid");
$languages = $DB->q("KEYVALUETABLE SELECT langid, CONCAT(langid, ' - ', name) FROM language ORDER BY langid");

if ( empty($restrictions['contest']) && empty($restrictions['problem']) && empty($restrictions['language']) ) {
	echo "<tr><td></td><td><i>No restrictions</i></td>\n";
} else {
	if ( !empty($restrictions['contest']) ) {
		$first = true;
		foreach ( $restrictions['contest'] as $contest ) {
			echo "<tr><td>" . ($first ? 'Restrict to contests:' : '') . "</td>";
			$first = false;
			echo "<td>" . $contests[$contest] . "</td></tr>\n";
		}
	}

	if ( !empty($restrictions['problem']) ) {
		$first = true;
		foreach ( $restrictions['problem'] as $problem ) {
			echo "<tr><td>" . ($first ? 'Restrict to problems:' : '') . "</td>";
			$first = false;
			echo "<td>" . $problems[$problem] . "</td></tr>\n";
		}
	}

	if ( !empty($restrictions['language']) ) {
		$first = true;
		foreach ( $restrictions['language'] as $language ) {
			echo "<tr><td>" . ($first ? 'Restrict to languages:' : '') . "</td>";
			$first = false;
			echo "<td>" . $languages[$language] . "</td></tr>\n";
		}
	}
}

echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
	     editLink('judgehost_restriction', $data['restrictionid']) . "\n" .
	     delLink('judgehost_restriction','restrictionid',$data['restrictionid']) . "</p>\n\n";
}

echo "<h2>Judgehosts having restriction " . htmlspecialchars($data['restrictionname']) . "</h2>\n\n";

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
