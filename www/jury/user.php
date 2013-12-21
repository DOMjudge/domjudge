<?php
/**
 * View user details
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = (int)@$_REQUEST['id'];
$title = $id ? 'User '.htmlspecialchars(@$id) : 'Add user';

if ( isset($_GET['cmd'] ) ) {
    $cmd = $_GET['cmd'];
} else {
    $refresh = '15;url='.$pagename.'?id='.urlencode($id).
        (isset($_GET['restrict'])?'&restrict='.urlencode($_GET['restrict']):'');
}

require(LIBWWWDIR . '/header.php');

if ( !empty($cmd) ):

    requireAdmin();

    echo "<h2>" . htmlspecialchars(ucfirst($cmd)) . " user</h2>\n\n";

    echo addForm('edit.php');

    echo "<table>\n";

    if ( $cmd == 'edit' ) {
        echo "<tr><td>Username:</td><td class=\"username\">";
        $row = $DB->q('TUPLE SELECT * FROM user WHERE userid = %s',
            $id);
        echo addHidden('keydata[0][userid]', $row['userid']);
        echo addHidden('keydata[0][username]', $row['username']);
        echo htmlspecialchars($row['username']);
    } else {
        echo "<tr><td><label for=\"data_0__login_\">Username:</label></td><td class=\"username\">";
        echo addInput('data[0][username]', null, 8, 15, 'pattern="' . IDENTIFIER_CHARS . '+" title="Alphanumerics only" required');
    }
    echo "</td></tr>\n";

?>
<tr><td><label for="data_0__name_">Full name:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 35, 255, 'required')?></td></tr>
<tr><td><label for="data_0__email_">Email:</label></td>
<td><?php echo addInputField('email', 'data[0][email]', @$row['email'], ' size="35" maxlength="255"')?></td></tr>

<tr><td><label for="data_0__password_">Password:</label></td><td><?php
if ( !empty($row['password']) ) {
	echo "<em>set</em>";
} else {
	echo "<em>not set</em>";
} ?> - to change: <?php echo addInputField('password', 'data[0][password]', "", ' size="19" maxlength="255"')?></td></tr>
<tr><td><label for="data_0__ip_address_">IP Address:</label></td>
<td><?php echo addInput('data[0][ip_address]', @$row['ip_address'], 35, 255)?></td></tr>

<tr><td><label for="data_0__enabled_">Enabled:</label></td>
<td><?php echo addRadioButton('data[0][enabled]', (!isset($row['']) || $row['enabled']), 1)?> <label for="data_0__enabled_1">yes</label>
<?php echo addRadioButton('data[0][enabled]', (isset($row['enabled']) && !$row['enabled']), 0)?> <label for="data_0__enabled_0">no</label></td></tr>

<!-- team selection -->
<tr><td><label for="data_0__affilid_">Team:</label></td>
<td><?php
$tmap = $DB->q("KEYVALUETABLE SELECT login,name FROM team ORDER BY name");
$tmap[''] = 'none';
echo addSelect('data[0][teamid]', $tmap, isset($row['teamid'])?$row['teamid']:@$_GET['forteam'], true);
?>
</td></tr>

<!-- role selection -->
<tr><td><label for="data_0__affilid_">Roles:</label></td>
<td><?php
$roles = $DB->q("TABLE SELECT role.roleid,role,description,max(userrole.userid=%s) AS hasrole ".
    "FROM role ".
    "LEFT JOIN userrole ON userrole.roleid = role.roleid ".
    "GROUP BY role.roleid", @$row['userid']);
$i=0;
foreach ($roles as $role) {
    echo "<label>";
    echo addCheckbox("data[0][mapping][items][$i]", $role['hasrole']==1, $role['roleid']);
    echo $role['description'] . "</label><br/>";
    $i++;
}
?>
</td></tr>

</table>
<?php
echo addHidden('data[0][mapping][fk][0]', 'userid') .
     addHidden('data[0][mapping][fk][1]', 'roleid') .
     addHidden('data[0][mapping][table]', 'userrole');
echo addHidden('cmd', $cmd) .
    addHidden('table','user') .
    addHidden('referrer', @$_GET['referrer']) .
    addSubmit('Save') .
    addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
    addEndForm();

require(LIBWWWDIR . '/footer.php');
exit;

endif;

$row = $DB->q('MAYBETUPLE SELECT u.*
               FROM user u
               WHERE u.userid = %s', $id);
$roles = $DB->q('SELECT role.* FROM userrole LEFT JOIN role ON userrole.roleid = role.roleid WHERE userrole.userid = %s', $id);

if ( ! $row ) error("Missing or invalid user id");

$userimage   = "../images/users/"        . urlencode($row['username'])   . ".jpg";

echo "<h1>User ".htmlspecialchars($row['name'])."</h1>\n\n";

if ( $row['enabled'] != 1 ) {
    echo "<p><em>User is disabled</em></p>\n\n";
}

?>

<div class="col1"><table>
<tr><td>Login:     </td><td class="teamid"><?php echo $row['username']?></td></tr>
<tr><td>Name:      </td><td><?php echo htmlspecialchars($row['name'])?></td></tr>
<tr><td>Email:      </td><td><?php
if ( !empty($row['email']) ) {
	echo "<a href=\"mailto:" . urlencode($row['email']) . "\">" .
	     htmlspecialchars($row['email']) . "</a>";
} else {
	echo "-";
}
?></td></tr>
<tr><td>Password:  </td><td><?php
if ( !empty($row['password']) ) {
	echo "set";
} else {
	echo "not set";
} ?></td></tr>
<tr><td>Roles:</td>
    <td><?php
    if ($roles->count() == 0) echo "No roles assigned";
    else {
        while( $role = $roles->next() ) {
            echo "${role['role']} - ${role['description']}<br>";
        }
    }
    ?></td></tr>
<tr><td>Team:</td><?php
if ( $row['teamid'] ) {
	echo "<td class=\"teamid\"><a href=\"team.php?id=" .
	     urlencode($row['teamid']) . "\">" .
	     htmlspecialchars($row['teamid']) . "</a></td>";
} else {
	echo "<td>-</td>";
} ?></tr>
<tr><td>Last login:</td><td><?php echo htmlspecialchars($row['last_login'])?></td></tr>
<tr><td>Last IP:   </td><td><?php echo
    (@$row['ip_address'] ? printhost($row['ip_address'], TRUE):'') ?></td></tr>
</table></div>

<?php
if ( IS_ADMIN ) {
    echo "<p class=\"nomorecol\">" .
        editLink('user', $id). "\n" .
        delLink('user','userid',$id) .
        "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
