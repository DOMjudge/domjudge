<?php
/**
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

requireAdmin();
$fmt = @$_GET['fmt'];

switch ( $fmt ) {
	case 'groups':     $data = tsv_groups();     $version = 1; break;
	case 'teams':      $data = tsv_teams();      $version = 1; break;
//	case 'scoreboard': $data = tsv_scoreboard(); $version = 1; break;
//	case 'results':    $data = tsv_results();    $version = 1; break;
//	case 'userdata':   $data = tsv_userdata();   $version = 1; break;
//	case 'accounts':   $data = tsv_accounts();   $version = 1; break;
	default: error('Specified format not (yet) supported.');
}

header("Content-Type: text/plain; name=\"" . $fmt . ".tsv\"; charset=" . DJ_CHARACTER_SET);
header("Content-Disposition: attachment; filename=\"" . $fmt . ".tsv\"");

echo "$fmt\t$version\n";
foreach($data as $row) {
	// fixme: are we sure that no field will contain a tab already?
	// should we strip them?
	echo implode("\t", $row) . "\n";
}

function tsv_groups()
{
	// groups are categories.
	// we only select visible groups as the others are considered 'internal'
	global $DB;
	return $DB->q('TABLE SELECT categoryid, name FROM team_category WHERE visible = 1');
}

function tsv_teams()
{
	// login is our team number
	// we use affilid as the short name
	global $DB;
	return $DB->q('TABLE SELECT login, externalid, categoryid, t.name, a.name, a.affilid, a.country
	               FROM team t LEFT JOIN team_affiliation a USING(affilid)
	               WHERE enabled = 1');

}

