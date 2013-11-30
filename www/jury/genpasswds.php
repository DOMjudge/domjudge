<?php
/**
 * Manage passwords for all users.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Generate Passwords';
require(LIBWWWDIR . '/header.php');
requireAdmin();
?>

<h1>Manage user passwords</h1>

<?php
$users = $DB->q('KEYVALUETABLE SELECT username, CONCAT(CONCAT(username, " - "),name) FROM user
                 ORDER BY username');

if ( empty($users) ) {
	echo "<p class=\"nodata\">No users defined.</p>\n\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}

$users = array(''=>'(select one)') + $users;

switch ( AUTH_METHOD ):

case 'IPADDRESS':
?>
<p>You are using IP-address based authentication. Note that resetting the
password of a user (or all users) implies instantly revoking any current
access that user may have to their userpage until they enter their newly
generated password.</p>
<?php
break;
case 'PHP_SESSIONS':
?>
<p>You are using PHP sessions based authentication. Generating a new password
for a user will not affect existing logged-in sessions.</p>
<?php
break;
default:
?>
<p>Unknown authentication scheme in use.</p>
<?php
endswitch;

echo addForm($pagename) .
	"<p>\nSet password for user " .
	addSelect('foruser', $users, @$_GET['foruser'], true) .
	" to " .
	addInput('setpass', '', 10, 255) .
	" (leave empty for random) " .
	addSubmit('go', 'douser') .
	"</p>\n<p>" .
	"Generate a random password for:</p>\n<p>\n" .
	addSubmit('all users without a password or IP-address', 'doallnull') .
	"<br /></p>\n<p>" .
	addSubmit('absolutely all users', 'doall') .
	"<br /></p>\n" .
	addEndForm();

if ( isset($_POST['foruser']) ) {
	// output each password once we're done
	ob_implicit_flush();

	if ( isset($_POST['douser']) ) {
		// one user only
		if ( empty($_POST['foruser']) ) {
			error("Please select a user to set this password for.");
		}
		$users = $DB->q('TABLE SELECT username,name FROM user ' .
				'WHERE username = %s', $_POST['foruser']);
		if ( !empty($_POST['setpass']) ) {
			$setpass = $_POST['setpass'];
		}
	} else {
		// all users, or optionaly only those with null password
		$users = $DB->q('TABLE SELECT username,name FROM user ' .
		                (isset($_POST['doallnull'])?'WHERE password IS NULL':'') .
		                ' ORDER BY username');
	}

	echo "<hr />\n\n<pre>";
	foreach($users as $user) {
		// generate a new password, only if it wasn't set in the interface
		if ( !isset($setpass) ) {
			$pass = genrandpasswd();
		} else {
			$pass = $setpass;
		}
		// update the user table with a password
		$DB->q('UPDATE user SET password = %s WHERE username = %s', md5($user['username'].'#'.$pass), $user['username']);
		auditlog('user', $user['username'], 'set password');
		echo "User:      " . htmlspecialchars($user['name']) . "\n" .
		     "Login:     " . htmlspecialchars($user['username']) . "\n" .
		     "Password:  $pass\n\n\n\n";
	}
	echo "</pre>\n";

	echo "<hr />\n\n<p>Done.</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');

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
