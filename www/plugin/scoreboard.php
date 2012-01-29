<?php
/**
 * Output scoreboard in XML format.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

// Frozen scoreboard requested, even if after unfreeze time. Make sure
// we access the database as non-jury, before defining IS_JURY in init.
if ( isset($_REQUEST['frozen']) ) define('IS_JURY', FALSE);

require('init.php');

require(LIBWWWDIR . '/scoreboard.php');

$now = now();

$cstarted = difftime($now, $cdata['starttime'])>0;
$cended   = difftime($now, $cdata['endtime'])  >0;

// Frozen scoreboard requested:
if ( isset($_REQUEST['frozen']) ) unset($cdata['unfreezetime']);

// parse filter options
$filter = array();
foreach( array('affilid', 'country', 'categoryid') as $type ) {
	if ( !empty($_GET[$type]) ) $filter[$type] = $_GET[$type];
}

$tmp = @genScoreBoard($cdata, FALSE, $filter);
if ( ! empty($tmp) ) {
	$MATRIX  = $tmp['matrix'];
	$SCORES  = $tmp['scores'];
	$SUMMARY = $tmp['summary'];
}
unset($tmp);

// Get problems, languages, affiliations and categories for legend
$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, name, color FROM problem
                 WHERE cid = %i AND allow_submit = 1 ORDER BY probid', $cid);

$langs = $DB->q('KEYTABLE SELECT langid AS ARRAYKEY, name FROM language
                 WHERE allow_submit = 1 ORDER BY langid');

$affils = $DB->q('KEYTABLE SELECT affilid AS ARRAYKEY, name, country
                  FROM team_affiliation ORDER BY name');

$categs = $DB->q('KEYTABLE SELECT categoryid AS ARRAYKEY, name, color
                  FROM team_category WHERE visible = 1 ORDER BY name');

$xmldoc = new DOMDocument('1.0', DJ_CHARACTER_SET);

$root       = XMLaddnode($xmldoc, 'root');
$scoreboard = XMLaddnode($root, 'scoreboard');
$contest    = XMLaddnode($scoreboard, 'contest', $cdata['contestname'],
                         array('id'    => $cid,
                               'start' => $cdata['starttime'],
                               'end'   => $cdata['endtime'] ));

// Add scoreboard subnodes here, so they are also present when the
// contest has not started yet.
$rows        = XMLaddnode($scoreboard, 'rows');
$summary     = XMLaddnode($scoreboard, 'summary');
$problegend  = XMLaddnode($scoreboard, 'problem_legend');
$langlegend  = XMLaddnode($scoreboard, 'language_legend');
$affillegend = XMLaddnode($scoreboard, 'affiliation_legend');
$categlegend = XMLaddnode($scoreboard, 'category_legend');

if ( isset($cdata['freezetime']) ) {
	$contest->setAttribute('freeze', $cdata['freezetime']);
}

// Don't output anything if before start of contest
if ( ! empty($MATRIX) ) {

	foreach( $SCORES as $team => $totals ) {

		$row = XMLaddnode($rows, 'row', NULL, array('rank' => $totals['rank']));

		XMLaddnode($row, 'team', $totals['teamname'],
		           array('id' => $team, 'categoryid' => $totals['categoryid'],
		                 'affilid' => $totals['affilid'], 'country' => $totals['country']));

		XMLaddnode($row, 'num_solved', $totals['num_correct']);
		XMLaddnode($row, 'totaltime',  $totals['total_time']);

		$problems = XMLaddnode($row, 'problems');

		foreach( $MATRIX[$team] as $prob => $score ) {

			$elem = XMLaddnode($problems, 'problem', NULL,
			                   array('id' => $prob, 'correct' => ($score['is_correct']?'true':'false')));

			XMLaddnode($elem, 'num_submissions', $score['num_submissions']);

			if ( $score['is_correct'] ) {
				XMLaddnode($elem, 'time', $score['time']);
				XMLaddnode($elem, 'penalty', calcPenaltyTime($score['is_correct'],$score['num_submissions']));
			}
		}
	}

	// Add summary data
	XMLaddnode($summary, 'num_solved', $SUMMARY['num_correct']);

	// Summary per problem
	$problems = XMLaddnode($summary, 'problems');

	foreach( $SUMMARY['problems'] as $prob => $data ) {
		$elem = XMLaddnode($problems, 'problem', NULL, array('id' => $prob));

		XMLaddnode($elem, 'num_submissions', $data['num_submissions']);
		XMLaddnode($elem, 'num_solved', $data['num_correct']);
		XMLaddnode($elem, 'best_time', $data['best_time']);
	}

	// Add legends for problems, languages, affiliations and categories
	foreach( $probs as $prob => $data ) {
		XMLaddnode($problegend, 'problem', $data['name'],
		           array('id' => $prob, 'color' => $data['color']));
	}

	foreach( $langs as $lang => $data ) {
		XMLaddnode($langlegend, 'language', $data['name'],
		           array('id' => $lang));
	}

	foreach( $affils as $affil => $data ) {
		XMLaddnode($affillegend, 'affiliation', $data['name'],
		           array('id' => $affil, 'country' => $data['country']));
	}

	foreach( $categs as $categ => $data ) {
		XMLaddnode($categlegend, 'category', $data['name'],
		           array('id' => $categ, 'color' => $data['color']));
	}
}

if ( !$xmldoc->schemaValidate('scoreboard.xsd') ) error('XML file not valid.');

header('Content-Type: text/xml; charset=' . DJ_CHARACTER_SET);

$xmldoc->formatOutput = false;
echo $xmldoc->saveXML();
