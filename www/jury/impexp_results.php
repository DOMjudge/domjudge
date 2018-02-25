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

$team_mapping = [];
foreach ($teams as $team) {
	$team_mapping[$team['externalid']] = $team['name'];
}
$ranked = [];
$unranked = [];
$group_winners = [];

foreach (tsv_results_get() as $row) {
	$team = $team_mapping[$row[0]];

	if ($row[6] !== '') {
		$group_winners[] = [
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
		$unranked[] = $row;
	} else {
		$ranked[] = $row;
	}
}

usort($group_winners, function($a, $b) {
	return $a['group'] <=> $b['group'];
});

$probs = $sdata['problems'];
$matrix = $sdata['matrix'];
$summary = $sdata['summary'];
$first_to_solve = [];
foreach ($probs as $probData) {
	foreach ($teams as $teamData) {
		if (!in_array($teamData['categoryid'], $categs)) {
			continue;
		}
		if ($matrix[$teamData['teamid']][$probData['probid']]['is_correct'] && first_solved($matrix[$teamData['teamid']][$probData['probid']]['time'],
				@$summary['problems'][$probData['probid']]['best_time_sort'][$teamData['sortorder']])) {
			$first_to_solve[] = [
				'problem' => $probData['shortname'],
				'problem_name' => $probData['name'],
				'team' => $teamData['name'],
				'time' => scoretime($matrix[$teamData['teamid']][$probData['probid']]['time']),
			];
		}
	}
}

usort($first_to_solve, function ($a, $b) {
	return $a['problem'] <=> $b['problem'];
});
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
<head>
	<!-- DOMjudge version <?php echo DOMJUDGE_VERSION ?> -->
	<meta charset="<?php echo DJ_CHARACTER_SET ?>"/>
	<title>Results for <?= $cdata['name'] ?></title>
	<style>

		/*!
		 * Styling based on:
		 * Bootstrap v4.0.0 (https://getbootstrap.com)
		 * Copyright 2011-2018 The Bootstrap Authors
		 * Copyright 2011-2018 Twitter, Inc.
		 * Licensed under MIT (https://github.com/twbs/bootstrap/blob/master/LICENSE)
		 */
		html {
			font-family: sans-serif;
			line-height: 1.15;
			-webkit-text-size-adjust: 100%;
			-ms-text-size-adjust: 100%;
			-ms-overflow-style: scrollbar;
			-webkit-tap-highlight-color: transparent;
		}

		body {
			margin: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
			font-size: 1rem;
			font-weight: 400;
			line-height: 1.5;
			color: #212529;
			text-align: left;
			background-color: #fff;
		}

		table {
			border-collapse: collapse;
		}

		.table {
			width: 100%;
			max-width: 100%;
			margin-bottom: 1rem;
			background-color: transparent;
		}

		.table th,
		.table td {
			padding: 0.75rem;
			vertical-align: top;
			border-top: 1px solid #dee2e6;
		}

		.table thead th {
			vertical-align: bottom;
			border-bottom: 2px solid #dee2e6;
		}

		.table tbody + tbody {
			border-top: 2px solid #dee2e6;
		}

		.table .table {
			background-color: #fff;
		}

		main {
			padding: 1rem 3rem;
		}

		h1, h2 {
			text-align: center;
		}

		h1 {
			font-size: 2em;
			padding-top: 3rem;
		}

		h2 {
			font-size: 1.5em;
			padding-top: 2rem;
		}
	</style>
</head>
<body>
<main role="main" class="">
	<h1>Results for <?= $cdata['name'] ?></h1>

	<h2>Ranked teams</h2>
	<table class="table">
		<thead>
		<tr>
			<th scope="col">Rank</th>
			<th scope="col">Team</th>
			<th scope="col">Award</th>
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
				<td><?= $row['award'] ?></td>
				<td><?= $row['solved'] ?></td>
				<td><?= $row['total_time'] ?></td>
				<td><?= $row['max_time'] ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2>Unranked teams</h2>
	<table class="table">
		<thead>
		<tr>
			<th scope="col">Team</th>
			<th scope="col">Award</th>
			<th scope="col">Solved problems</th>
			<th scope="col">Total time</th>
			<th scope="col">Time of last submission</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($unranked as $row): ?>
			<tr>
				<th scope="row"><?= $row['team'] ?></th>
				<td><?= $row['award'] ?></td>
				<td><?= $row['solved'] ?></td>
				<td><?= $row['total_time'] ?></td>
				<td><?= $row['max_time'] ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2>Group winners</h2>
	<table class="table">
		<thead>
		<tr>
			<th scope="col">Group</th>
			<th scope="col">Team</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($group_winners as $row): ?>
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
				<td><?= $row['team'] ?></td>
				<td><?= $row['time'] ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</main>
</body>
</html>
