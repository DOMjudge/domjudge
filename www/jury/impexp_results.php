<?php
/**
 * Code to import and export HTML formats as specified by the ICPC
 * Contest Control System Standard.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');
require(LIBDIR . '/lib.impexp.php');

requireAdmin();

$categs = $DB->q('COLUMN SELECT categoryid FROM team_category WHERE visible = 1');
$sdata = genScoreBoard($cdata, true, array('categoryid' => $categs));
$teams = $sdata['teams'];

// Note: we're using affiliation names here for the WFs!
$team_names = $DB->q('KEYVALUETABLE SELECT t.externalid, a.name
                      FROM team t
                      LEFT JOIN team_affiliation a USING (affilid)
                      WHERE t.externalid IS NOT NULL');

$awarded = [];
$ranked = [];
$honorable = [];
$region_winners = [];

foreach (tsv_results_get() as $row) {
	$team = $team_names[$row[0]];

	if ($row[6] !== '') {
		$region_winners[] = [
			'group' => $row[6],
			'team' => $team,
		];
	}

	$row = [
		'team' => $team,
		'rank' => $row[1],
		'award' => $row[2],
		'solved' => $row[3],
		'total_time' => $row[4],
		'max_time' => $row[5],
	];
	if ($row['rank'] === '') {
		$honorable[] = $row;
	} elseif ($row['award'] === 'Ranked') {
		$ranked[] = $row;
	} else {
		$awarded[] = $row;
	}
}

usort($region_winners, function ($a, $b) {
	return $a['group'] <=> $b['group'];
});

$probs = $sdata['problems'];
$matrix = $sdata['matrix'];
$summary = $sdata['summary'];
$first_to_solve = [];
foreach ($probs as $probData) {
	$first_to_solve[$probData['probid']] = [
		'problem' => $probData['shortname'],
		'problem_name' => $probData['name'],
		'team' => null,
		'time' => null,
	];
	foreach ($teams as $teamData) {
		if (!in_array($teamData['categoryid'], $categs)) {
			continue;
		}
		if ($matrix[$teamData['teamid']][$probData['probid']]['is_correct'] && first_solved($matrix[$teamData['teamid']][$probData['probid']]['time'],
				@$summary['problems'][$probData['probid']]['best_time_sort'][$teamData['sortorder']])) {
			$first_to_solve[$probData['probid']] = [
				'problem' => $probData['shortname'],
				'problem_name' => $probData['name'],
				'team' => $team_names[$teamData['externalid']],
				'time' => scoretime($matrix[$teamData['teamid']][$probData['probid']]['time']),
			];
		}
	}
}

usort($first_to_solve, function ($a, $b) {
	return $a['problem'] <=> $b['problem'];
});

$title = sprintf('Results for %s', $cdata['name']);
require(LIBWWWDIR . '/impexp_header.php');
?>
<h2>Awards</h2>
<table class="table">
	<thead>
	<tr>
		<th scope="col">Place</th>
		<th scope="col">Team</th>
		<th scope="col">Award</th>
		<th scope="col">Solved problems</th>
		<th scope="col">Total time</th>
		<th scope="col">Time of last submission</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($awarded as $row): ?>
		<tr>
			<th scope="row"><?= $row['rank'] ?></th>
			<th scope="row"><?= $row['team'] ?></th>
			<td><?= $row['award'] ?></td>
			<td><?= $row['solved'] ?></td>
			<td><?= $row['total_time'] ?></td>
			<td><?= $row['max_time'] ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<h2>Other ranked teams</h2>
<table class="table">
	<thead>
	<tr>
		<th scope="col">Rank</th>
		<th scope="col">Team</th>
		<th scope="col">Solved problems</th>
		<th scope="col">Total time</th>
		<th scope="col">Time of last submission</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($ranked as $row): ?>
		<tr>
			<th scope="row"><?= $row['rank'] ?></th>
			<th scope="row"><?= $row['team'] ?></th>
			<td><?= $row['solved'] ?></td>
			<td><?= $row['total_time'] ?></td>
			<td><?= $row['max_time'] ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<h2>Honorable mentions</h2>
<table class="table">
	<tbody>
	<tr>
		<?php foreach ($honorable as $idx => $row): ?>
			<td><?= $row['team'] ?></td>
			<?php if ($idx % 2 === 1 && $row !== end($honorable)): ?><tr></tr><?php endif; ?>
		<?php endforeach; ?>
	</tr>
	</tbody>
</table>

<h2>Region winners</h2>
<table class="table">
	<thead>
	<tr>
		<th scope="col">Region</th>
		<th scope="col">Team</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($region_winners as $row): ?>
		<tr>
			<th scope="row"><?= $row['group'] ?></th>
			<td><?= $row['team'] ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<h2>First to solve</h2>
<table class="table">
	<thead>
	<tr>
		<th scope="col">Problem</th>
		<th scope="col">Team</th>
		<th scope="col">Time</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($first_to_solve as $row): ?>
		<tr>
			<th scope="row"><?= $row['problem'] ?>: <?= $row['problem_name'] ?></th>
			<td>
				<?php if ($row['team'] !== null): ?>
					<?= $row['team'] ?>
				<?php else: ?>
					<i>Not solved</i>
				<?php endif ?>
			</td>
			<td>
				<?php if ($row['time'] !== null): ?>
					<?= $row['time'] ?>
				<?php else: ?>
					<i>-</i>
				<?php endif ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php
require(LIBWWWDIR . '/impexp_footer.php');
