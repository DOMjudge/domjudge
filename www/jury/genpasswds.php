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
	addSelect('forteam', $teams, @$_GET['forteam'], true) .
	"<br /></p>\n<p>" .
	addSubmit('all teams without a password', 'doallnull') .
	"<br /></p>\n<p>" .
	addSubmit('absolutely all teams', 'doall') .
	"<br /></p>\n" .
	addEndForm();

if ( isset($_POST['forteam']) ) {
	ob_implicit_flush();

	if ( isset($_POST['doteam']) ) {
		$teams = $DB->q('TABLE SELECT login,name FROM team ' .
				'WHERE login = %s', $_POST['forteam']);
	} else {
		$teams = $DB->q('TABLE SELECT login,name FROM team ' .
		                (isset($_POST['doallnull'])?'WHERE passwd IS NULL':'') .
		                ' ORDER BY login');
	}

	srand( (double) microtime()*1000000);

	echo "<hr />\n\n<pre>";
	foreach($teams as $team) {
		$pass = genrandpasswd();
		$DB->q('UPDATE team SET passwd = %s WHERE login = %s', md5($pass), $team['login']);
		echo "Team:      " . htmlspecialchars($team['name']) . "\n";
		echo "Login:     " . htmlspecialchars($team['login']) . "\n";
		echo "Password:  $pass\n\n\n\n";
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
		$rand_str .= $chars[rand(0, $max_chars)];
	}

	return $rand_str;
}
