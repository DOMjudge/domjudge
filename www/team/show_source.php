<?php
/**
 * Show source code from the database.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = (int)$_GET['id'];

$source = $DB->q('MAYBETUPLE SELECT * FROM submission
                  WHERE submitid = %i',$id);
if ( empty($source) ) error ("Submission $id not found");

if (!$source['valid'] ) {
	error("invalid submission");
}
$solved = $DB->q('VALUE SELECT COUNT(*) FROM judging WHERE submitid=%i AND result=%s AND valid=1', $id, 'correct');
if (!$solved) {
	error("invalid request"); // this submission was not solved
}
$solved = $DB->q('VALUE SELECT is_correct FROM scoreboard_public WHERE probid=%s AND teamid=%s', $source['probid'], $login);
if (!$solved) {
	error("invalid request"); // you did not solve the same problem
}
$category = $DB->q('VALUE SELECT categoryid FROM team, submission WHERE submitid=%i AND login = teamid', $id);
if ($category != 1) {
	error("invalid request"); // avoid reading DOMjudge's submissions
}

// FIXME: displays only first sourcefile
$sourcecode = $DB->q('VALUE SELECT sourcecode FROM submission_file WHERE submitid=%i LIMIT 1', $id);
$teamname = $DB->q('VALUE SELECT name FROM team WHERE login=%s', $source['teamid']);
$probname = $DB->q('VALUE SELECT name FROM problem WHERE probid=%s', $source['probid']);
$runtime = $DB->q('VALUE SELECT MAX(runtime) FROM judging_run WHERE judgingid IN (SELECT judgingid FROM judging WHERE submitid=%i AND valid=1)', $id);
$totaltime = $DB->q('VALUE SELECT SUM(runtime) FROM judging_run WHERE judgingid IN (SELECT judgingid FROM judging WHERE submitid=%i AND valid=1)', $id);
$sourcecode =
	"/*\n" .
	" * FAU Online Judge Submission\n" . 
	" * Author:         " . $teamname . "\n" . 
	" * Problem:        " . $probname . "\n" .
	" * Max. Runtime:   " . sprintf("%3.3lf", $runtime) . "s\n" . 
	" * Total. Runtime: " . sprintf("%3.3lf", $totaltime) . "s\n" . 
	" */\n\n"
	. $sourcecode;

header("Content-Type: text/plain; name=\"$sourcefile\"; charset=" . DJ_CHARACTER_SET);
header("Content-Disposition: inline; filename=\"$sourcefile\"");
header("Content-Length: " . strlen($sourcecode));

echo $sourcecode;
exit;
