<?php
/**
 * Manage passwords for all users.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Generate Passwords';
include('../header.php');
require('../forms.php');
requireAdmin();
?>

<h1>Manage team passwords</h1>

<p>Generate new password for:</p>

<?php
$teams = $DB->q('KEYVALUETABLE SELECT login, name FROM team
                 ORDER BY categoryid ASC, name ASC');
$teams = array_merge(array(''=>'(select one)'),$teams);

echo addForm('genpasswds.php') .
	"<p>\nSet password for team " .
	addSelect('forteam', $teams, @$_GET['forteam'], true) .
	" to " .
	addInput('setpass', '', 10, 255) .
	" (leave empty for random) " .
	addSubmit('go', 'doteam') .
	"</p>\n<p>" .
	"Generate a random password for:</p>\n<p>\n" .
	addSubmit('all teams without a password', 'doallnull') .
	"<br /></p>\n<p>" .
	addSubmit('absolutely all teams', 'doall') .
	"<br /></p>\n" .
	addEndForm();

if ( isset($_POST['forteam']) ) {
	// output each password once we're done
	ob_implicit_flush();

	if ( isset($_POST['doteam']) ) {
		// one team only
		if ( empty($_POST['forteam']) ) {
			error("Please select a team to set this password for.");
		}
		$teams = $DB->q('TABLE SELECT login,name FROM team ' .
				'WHERE login = %s', $_POST['forteam']);
		if ( !empty($_POST['setpass']) ) {
			$setpass = $_POST['setpass'];
		}
	} else {
		// all teams, or optionaly only those with null password
		$teams = $DB->q('TABLE SELECT login,name FROM team ' .
		                (isset($_POST['doallnull'])?'WHERE passwd IS NULL':'') .
		                ' ORDER BY login');
	}

	echo "<hr />\n\n<pre>";
	foreach($teams as $team) {
		// generate a new password, only if it wasn't set in the interface
		if ( !isset($setpass) ) {
			$pass = genrandpasswd();
		} else {
			$pass = $setpass;
		}
		// update the team table with a password
		$DB->q('UPDATE team SET passwd = %s WHERE login = %s', md5($pass), $team['login']);
		echo "Team:      " . htmlspecialchars($team['name']) . "\n" .
		     "Login:     " . htmlspecialchars($team['login']) . "\n" .
		     "Password:  $pass\n\n\n\n";
	}
	echo "</pre>\n";

	echo "<hr />\n\n<p>Done.</p>\n\n";
}

include('../footer.php');

/**
 * Generate a random password of length 6 with lowercase alphanumeric
 * characters, except o, 0, l and 1 since these can be confusing.
 */
function genrandpasswd()
{
	$chars = array('a','b','c','d','e','f','g','h','i','j','k','m','n','p','q','r',
	               's','t','u','v','w','x','y','z','2','3','4','5','6','7','8','9');
	
	$max_chars = count($chars) - 1;
	
	$rand_str = '';
	for($i = 0; $i < 6; ++$i) {
		$rand_str .= $chars[mt_rand(0, $max_chars)];
	}

	return $rand_str;
}
