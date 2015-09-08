<?php
/**
 * View the users
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Users';

$users = $DB->q('SELECT u.*, GROUP_CONCAT(r.role) AS roles
                 FROM user u
                 LEFT JOIN userrole map ON u.userid = map.userid
                 LEFT JOIN role r ON (r.roleid = map.roleid)
                 GROUP BY u.userid ORDER BY username');

require(LIBWWWDIR . '/header.php');

echo "<h1>Users</h1>\n\n";

if( $users->count() == 0 ) {
	echo "<p class=\"nodata\">No users defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	    "<tr><th scope=\"col\">username</th><th scope=\"col\">name</th>" .
	    "<th scope=\"col\">email</th><th scope=\"col\">roles</th>" .
	    "<th scope=\"col\">team</th>" .
	    "<th class=\"thleft\" scope=\"col\">status</th><th></th>" .
	    "</tr>\n</thead>\n<tbody>\n";

	while( $row = $users->next() ) {

		$status = 0;
		if ( isset($row['last_login']) ) $status = 1;

		$link = '<a href="user.php?id='.urlencode($row['userid']) . '">';
		echo "<tr class=\"" . ($row['enabled'] == 1 ? '' : 'sub_ignore') .  "\">".
		    "<td class=\"username\">" . $link .
		        htmlspecialchars($row['username'])."</a></td>".
		    "<td>" . $link .
		        htmlspecialchars($row['name'])."</a></td>".
		    "<td>" . $link .
		        htmlspecialchars($row['email'])."</a></td>".
		    "<td>" . $link .
		        htmlspecialchars($row['roles'])."</a></td>".
		    "<td>" . (isset($row['teamid']) ? $link . "t" .
		        htmlspecialchars($row['teamid']). "</a>" : '') . "</td>";
		echo "<td sorttable_customkey=\"" . $status . "\" class=\"";
		if ($status == 1) {
			echo 'team-ok" title="logged in: ' . printtime($row['last_login']) . '"';
		} else {
			echo 'team-nocon" title="no connections made"';
		}
		echo ">$link" . CIRCLE_SYM . "</a></td>";
		if ( IS_ADMIN ) {
			echo "<td class=\"editdel\">" .
			    editLink('user', $row['userid']) . " " .
			    delLink('user','userid',$row['userid']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" .addLink('user') . "</p>\n";
}

require(LIBWWWDIR . '/footer.php');
