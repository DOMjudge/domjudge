<?php

/**
 * Produce a total score.
 *
 * $Id$
 */
 
require('init.php');
$title="Scoreboard";
require('../header.php');

$contdata = $DB->q('TUPLE SELECT * FROM contest ORDER BY starttime DESC LIMIT 1');
$cid = $contdata['cid'];
?>

<h1>Scoreboard <?=htmlentities($contdata['contestname'])?></h1>

<table border="1">
<?php
$teams = $DB->q('TABLE SELECT login,name,category
	FROM team');
$probs = $DB->q('TABLE SELECT probid,name
	FROM problem WHERE allow_submit = 1 ORDER BY probid');

echo "<tr><th>TEAM</th>";
echo "<th>#corr</th><th>time</th>\n";
foreach($probs as $pr) {
	echo "<th title=\"".htmlentities($pr['name'])."\">".htmlentities($pr['probid'])."</th>";
}
echo "</tr>\n";

$THEMATRIX = $SCORES = $TEAMNAMES = array();

foreach($teams as $team) {

	// to lookup the team name at the end
	$TEAMNAMES[$team['login']]=$team['name'];

	// reset vars
	$grand_total_correct = 0;
	$grand_total_time = 0;
	
	foreach($probs as $pr) {

		$result = $DB->q('SELECT result, 
				(UNIX_TIMESTAMP(submittime)-UNIX_TIMESTAMP(c.starttime))/60 as timediff
			FROM judging LEFT JOIN submission s USING(submitid)
				LEFT OUTER JOIN contest c ON(c.cid=s.cid)
			WHERE team = %s AND probid = %s AND valid = 1 AND result IS NOT NULL AND s.cid = %i
			ORDER BY submittime',
			$team['login'], $pr['probid'], $cid);

		// reset vars
		$total_submitted = $penalty = $total_time = 0;
		$correct = FALSE;

		// for each submission
		while($row = $result->next()) {
			$total_submitted++;

			// if correct, don't look at any more submissions after this one
			if($row['result'] == 'correct') {

				$correct = TRUE;
				$total_time = round((int)@$row['timediff']);
				
				break;
			}

			$penalty += 20;
		}

		if(!$correct) {
			$penalty = 0;
		} else {
			$grand_total_correct++;
			$grand_total_time += ($total_time + $penalty);
		}

		// THEMATRIX contains the scores for each problem.
		$THEMATRIX[$team['login']][$pr['probid']] = array (
			'correct' => $correct,
			'submitted' => $total_submitted,
			'time' => $total_time,
			'penalty' => $penalty );

	}

	// SCORES contains a grand total for each time; this is our sorting criterion
	$SCORES[$team['login']]['num_correct'] = $grand_total_correct;
	$SCORES[$team['login']]['total_time'] = $grand_total_time;

}

// sort the array using our custom comparison function
uasort($SCORES, 'cmp');

// print the whole thing
foreach($SCORES as $team => $totals) {

	echo "<tr><td>".htmlentities($TEAMNAMES[$team]).
		"<br /><span class=\"teamid\">".htmlspecialchars($team)."</span></td><td>"
		.$totals['num_correct']."</td><td>".$totals['total_time']."</td>";
	foreach($THEMATRIX[$team] as $prob => $pdata) {
		echo "<td class=\"";
		if( $pdata['correct'] ) { 
			echo 'correct';
		} elseif ( $pdata['submitted'] > 0 ) {
			echo 'incorrect';
		} else {
			echo 'neutral';
		}
		echo "\">" . $pdata['submitted']."/".$pdata['time'] . 
			"' + ".$pdata['penalty'] ."'</td>";
	}
	echo "</tr>\n";

}

echo "</table>\n\n";


// last modified date
echo "<div id=\"lastmod\">Last Update: ".date('r')."</div>\n\n";

require('../footer.php');

// FIXME HACK ALERT: Bug #962 'fix'
die;

// comparison function
function cmp ($b, $a) {
	if($a['num_correct'] > $b['num_correct']) {
		return 1;
	} elseif($a['num_correct'] == $b['num_correct']) {
		if($a['total_time'] > $b['total_time']) {
			return -1;
		} elseif ($a['total_time'] == $b['total_time']) {
			return 0;
		} else {
			return 1;
		}
	} else {
		return -1;
	}
}
