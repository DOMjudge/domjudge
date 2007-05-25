<?php
/**
 * View of one contest.
 *
 * $Id$
 */

$id = (int)$_GET['id'];

require('init.php');
$title = "Contest: " .htmlspecialchars(@$id);

if ( ! $id ) error("Missing or invalid contest id");

require('../header.php');

$data = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $id);

echo "<h1>Contest: ".htmlentities($data['contestname'])."</h1>\n\n";

if ( getCurContest() == $data['cid'] ) {
	echo "<p><em>This is the current contest.</em></p>\n\n";
}

echo "<table>\n";
echo '<tr><td>CID:</td><td>c' . (int)$data['cid'] . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' . htmlentities($data['contestname']) . "</td></tr>\n";
echo '<tr><td>Starttime:</td><td>' . htmlspecialchars($data['starttime']) . "</td></tr>\n";
echo '<tr><td>Last scoreboard update:</td><td>' . (empty($data['lastscoreupdate']) ? "-" : htmlspecialchars(@$data['lastscoreupdate'])) . "</td></tr>\n";
echo '<tr><td>Endtime:</td><td>' . htmlspecialchars($data['endtime']) . "</td></tr>\n";
echo '<tr><td>Scoreboard unfreeze:</td><td>' . (empty($data['unfreezetime']) ? "-" : htmlspecialchars(@$data['unfreezetime'])) . "</td></tr>\n";
echo "</table>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" . delLink('contest','cid',$data['cid']) ."</p>\n\n";
}

require('../footer.php');
