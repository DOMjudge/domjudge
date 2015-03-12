<?php
/**
 * Manage passwords for all users.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Generate Passwords';
requireAdmin();

function genpw($users, $group, $format) {
	global $DB;
	$teamroleid = $DB->q('VALUE SELECT roleid FROM role WHERE role = %s', 'team');
	$juryroleid = $DB->q('VALUE SELECT roleid FROM role WHERE role = %s', 'jury');
	$adminroleid = $DB->q('VALUE SELECT roleid FROM role WHERE role = %s', 'admin');

	if ( $format == "page" ) {
		echo "\n\n<pre>";
	}
	foreach($users as $user) {
		// checks if user has a "higher" role
		// FIXME: integrate in users query
		if ( $group == 'team' ) {
			if ( $DB->q('VALUE SELECT COUNT(*) FROM userrole
			             WHERE userid = %i AND (roleid = %i OR roleid = %i)',
			            $user['userid'], $juryroleid, $adminroleid) > 0 ) {
				continue;
			}
		} else if ( $group == 'judge' ) {
			if ( $DB->q('VALUE SELECT COUNT(*) FROM userrole
			             WHERE userid = %i AND roleid = %i',
			            $user['userid'], $adminroleid) > 0 ) {
				continue;
			}
		}
		$pass = genrandpasswd();
		// update the user table with a password
		$DB->q('UPDATE user SET password = %s WHERE username = %s', md5($user['username'].'#'.$pass), $user['username']);
		auditlog('user', $user['username'], 'set password');
		$line = implode("\t",
			array($group, $group == 'team' ? $user['teamid'] : '',
				str_replace("\t", " ", $user['name']),
				str_replace("\t", " ", $user['username']),
				$pass)) . "\n";
		if ( $format == "page" ) {
			echo htmlspecialchars($line);
		} else {
			echo $line;
		}
	}
	if ( $format == "page" ) {
		echo "</pre><hr />\n\n<pre>";
	}
}

if ( isset($_POST['format']) ) {
	$format = $_POST['format'];

	if ( $format == "page" ) {
		require(LIBWWWDIR . '/header.php');
		echo "<h1>Generated passwords:</h1>\n";
		// output each password once we're done
		ob_implicit_flush();
	} else {
		$fmt = "userdata";
		$version = 1;
		header("Content-Type: text/plain; name=\"" . $fmt . ".tsv\"; charset=" . DJ_CHARACTER_SET);
		header("Content-Disposition: attachment; filename=\"" . $fmt . ".tsv\"");
		echo $tsv = "userdata\t1\n";
	}

	$teamroleid = $DB->q('VALUE SELECT roleid FROM role WHERE role = %s', 'team');
	$juryroleid = $DB->q('VALUE SELECT roleid FROM role WHERE role = %s', 'jury');
	$adminroleid = $DB->q('VALUE SELECT roleid FROM role WHERE role = %s', 'admin');

	foreach($_POST['group'] as $group) {
		switch ( $group ) {
			case 'team':
			case 'teamwithoutpw':
				$users = $DB->q('TABLE SELECT username,name,userid,teamid FROM user
				                 LEFT JOIN userrole USING (userid)
				                 WHERE teamid IS NOT NULL AND roleid = %i' .
				                ($group == 'teamwithoutpw' ? ' AND password IS NULL' : '') .
				                ' GROUP BY userid ORDER BY username', $teamroleid);
				genpw($users, 'team', $format);
				break;
			case 'judge':
			case 'admin':
				$users = $DB->q('TABLE SELECT username,name,userid FROM user
				                 LEFT JOIN userrole USING (userid)
				                 WHERE roleid = %i
				                 GROUP BY userid ORDER BY username',
				                ($group == 'judge' ? $juryroleid : $adminroleid));
				genpw($users, $group, $format);
				break;
			default: error('Unknown group: ' . $group);
		}
	}

	exit;
}

require(LIBWWWDIR . '/header.php');
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
case 'FIXED':
case 'LDAP':
case 'EXTERNAL':
?>
<p>You are using the '<?php echo AUTH_METHOD; ?>' authentication scheme.
This scheme does not support resetting passwords (within DOMjudge).</p>
<?php
require(LIBWWWDIR . '/footer.php');
exit;
break;
default:
?>
<p>Unknown authentication scheme in use.</p>
<?php
endswitch;

echo addForm($pagename);
?>
<p>Generate a random password for:<br/>
<input type="checkbox" name="group[]" value="team">all teams<br />
<input type="checkbox" name="group[]" value="teamwithoutpw">teams without password<br />
<input type="checkbox" name="group[]" value="judge">jury members<br />
<input type="checkbox" name="group[]" value="admin">admins<br />
</p>
<p>Output format:<br/>
<input type="radio" name="format" value="page" checked>on web page<br/>
<input type="radio" name="format" value="tsv">as userdata.tsv download<br/>
<?php
echo addSubmit('generate') . addEndForm();

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
