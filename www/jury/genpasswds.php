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

switch ( AUTH_METHOD ):

case 'IPADDRESS':
?>
<p>You are using IP-address based authentication. Note that resetting the
password of a user implies instantly revoking any current
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

echo addForm($pagename);
?>
<p>Generate a random password for: <select name="action">
<option value="doallnull">all users with a team role without a password or IP-address</option>
<option value="doall">all users with a team role</option>
</select>
</p>

<?php
echo addSubmit('generate') . addEndForm();


if ( isset($_POST['action']) ) {
	// output each password once we're done
	ob_implicit_flush();

	// all users, or optionaly only those with null password
	$users = $DB->q('TABLE SELECT username,name FROM user WHERE teamid IS NOT NULL' .
	                ($_POST['action'] == 'doallnull'?' AND password IS NULL':'') .
	                ' ORDER BY username');

	echo "<hr />\n\n<pre>";
	foreach($users as $user) {
		$pass = genrandpasswd();

		// update the user table with a password
		$DB->q('UPDATE user SET password = %s WHERE username = %s', md5($user['username'].'#'.$pass), $user['username']);
		auditlog('user', $user['username'], 'set password');
		echo "Full name: " . htmlspecialchars($user['name']) . "\n" .
		     "Username:  " . htmlspecialchars($user['username']) . "\n" .
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
