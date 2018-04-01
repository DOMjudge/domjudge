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
require(LIBWWWDIR . '/clarification.php');
require(LIBDIR . '/lib.impexp.php');

requireAdmin();

$categories = getClarCategories();
$clarifications = $DB->q('SELECT c.*, cp.shortname
            FROM clarification c
            LEFT JOIN problem p USING(probid)
            LEFT JOIN contestproblem cp USING (probid, cid)
            WHERE c.cid = %i ORDER BY category, probid, submittime, clarid', $cdata['cid']);

// Note: we're using affiliation names here for the WFs!
$team_names = $DB->q('KEYVALUETABLE SELECT t.teamid, a.name
                      FROM team t
                      LEFT JOIN team_affiliation a USING (affilid)');

$grouped = [];

Symfony\Component\VarDumper\VarDumper::setHandler(null);

while ($clarification = $clarifications->next()) {
	if (isset($categories[$clarification['category']])) {
		$category = $categories[$clarification['category']];
	} elseif (isset($clarification['probid'])) {
		$category = 'Problem ' . $clarification['shortname'];
	} else {
		$category = 'General';
	}

	if (!isset($grouped[$category])) {
		$grouped[$category] = [];
	}

	if (isset($clarification['respid'])) {
		$grouped[$category][$clarification['respid']]['reply'] = $clarification;
	} else {
		$grouped[$category][$clarification['clarid']] = $clarification;
	}
}

$title = sprintf('Clarifications for %s', $cdata['name']);
require(LIBWWWDIR . '/impexp_header.php');
?>
<?php foreach ($grouped as $category => $clarifications): ?>
	<h2><?= $category ?></h2>
	<table class="table">
		<thead>
		<tr>
			<th scope="col">Contest time</th>
			<th scope="col">From</th>
			<th scope="col">To</th>
			<th scope="col">Contents</th>
			<th scope="col">Answered?</th>
			<th scope="col">Reply</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($clarifications as $clarification): ?>
			<?php
			if (!empty($clarification['sender'])) {
				$from = specialchars($team_names[$clarification['sender']]);
			} else {
				$from = 'Jury' . ' (' . specialchars($clarification['jury_member']) . ')';
			}
			if ($clarification['recipient'] && empty($clarification['sender'])) {
				$to = specialchars($team_names[$clarification['recipient']]);
			} else {
				$to = ($clarification['sender']) ? 'Jury' : 'All';
			}
			?>
			<tr>
				<td><?= printtime($clarification['submittime'], NULL, $clarification['cid']) ?></td>
				<td><?= $from ?></td>
				<td><?= $to ?></td>
				<td>
					<pre><?= specialchars(wrap_unquoted($clarification['body'], 80)) ?></pre>
				</td>
				<td><?= $clarification['answered'] ? 'Yes' : 'No' ?></td>
				<td>
					<?php if (isset($clarification['reply'])): ?>
						<pre><?= specialchars(wrap_unquoted($clarification['reply']['body'], 80)) ?></pre>
					<?php else: ?>
						-
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endforeach; ?>
<?php
require(LIBWWWDIR . '/impexp_footer.php');
