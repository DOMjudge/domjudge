<?php

/**
 * Generate passwords for all users.
 *
 * $Id$
 */

require('init.php');
$title = 'Generate Passwords';
include('../header.php');
require('../forms.php');
requireAdmin();
?>

<h1>Generate passwords</h1>

<p>Generate new password for:</p>

<?php
$teams = $DB->q('KEYVALUETABLE SELECT login, name FROM team
                 ORDER BY categoryid ASC, name ASC');

echo addForm('genpasswds.php') .
	"<p>\n" .
	addSubmit('a specific team:', 'doteam') .
	addSelect('forteam', $teams, null, true) .
	"<br /></p>\n<p>" .
	addSubmit('all teams without a password', 'doallnull') .
	"<br /></p>\n<p>" .
	addSubmit('absolutely all teams', 'doall') .
	"<br /></p>\n" .
	addEndForm();

if ( isset($_POST['forteam']) ) {
	ob_implicit_flush();

	if ( isset($_POST['doteam']) ) {
		$teams = array($_POST['forteam']);
	} else {
		$teams = $DB->q('COLUMN SELECT login FROM team ' .
		                (isset($_POST['doallnull'])?'WHERE passwd IS NULL':'') .
		                ' ORDER BY login');
	}

	srand( (double) microtime()*1000000);

	echo "<hr />\n\n<pre>";
	foreach($teams as $team) {
		$pass = genrandpasswd();
		$DB->q('UPDATE team SET passwd = %s WHERE login = %s', md5($pass), $team);
		echo "Login:     " . htmlspecialchars($team) . "\n";
		echo "Password:  $pass\n\n";
	}
	echo "</pre>\n";

	echo "<hr />\n\n<p>Done.</p>\n\n";
}

include('../footer.php');

function genrandpasswd()
{
	$chars = array( 'a', 'A', 'b', 'B', 'c', 'C', 'd', 'D', 'e', 'E', 'f', 'F', 'g', 'G', 'h', 'H', 'i', 'j', 'J',  'k', 'K', 'L', 'm', 'M', 'n', 'N', 'p', 'P', 'q', 'Q', 'r', 'R', 's', 'S', 't', 'T',  'u', 'U', 'v', 'V', 'w', 'W', 'x', 'X', 'y', 'Y', 'z', 'Z', '2', '3', '4', '5', '6', '7', '8', '9');
	
	$max_chars = count($chars) - 1;
	
	$rand_str = '';
	for($i = 0; $i < 10; ++$i) {
		$rand_str = ( $i == 0 ) ? $chars[rand(0, $max_chars)] : $rand_str . $chars[rand(0, $max_chars)];
	}

	return $rand_str;
}
