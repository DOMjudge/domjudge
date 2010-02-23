<?php
/**
 * View the problems
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Problems';

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/forms.php');

echo "<h1>Problems</h1>\n\n";

$res = $DB->q('SELECT * FROM problem NATURAL JOIN contest ORDER BY problem.cid, probid');

if( $res->count() == 0 ) {
	echo "<p><em>No problems defined</em></p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
		"<tr><th scope=\"col\">ID</th><th scope=\"col\">name</th>" .
		"<th scope=\"col\">contest</th><th scope=\"col\">allow<br />submit</th>" .
		"<th scope=\"col\">allow<br />judge</th>" .
		"<th scope=\"col\">timelimit</th>" .
		"<th class=\"sorttable_nosort\" scope=\"col\">colour</th></tr>" .
		"</thead>\n<tbody>\n";

	$lastcid = -1;

	while($row = $res->next()) {
		$classes = array();
		if ( $row['cid'] != $cid ) $classes[] = 'disabled';
		if ( $row['cid'] != $lastcid ) {
			if ( $lastcid != -1 ) $classes[] = 'contestswitch';
			$lastcid = $row['cid'];
		}
		echo "<tr class=\"" . implode(' ',$classes) .
		    "\"><td class=\"probid\"><a href=\"problem.php?id=" . 
				htmlspecialchars($row['probid'])."\">".
				htmlspecialchars($row['probid'])."</a>".
			"</td><td><a href=\"problem.php?id=".htmlspecialchars($row['probid'])."\">".
			htmlspecialchars($row['name'])."</a>".
			"</td><td title=\"".htmlspecialchars($row['contestname'])."\">c".
			htmlspecialchars($row['cid']).
			"</td><td align=\"center\">".printyn($row['allow_submit']).
			"</td><td align=\"center\">".printyn($row['allow_judge']).
			"</td><td>".(int)$row['timelimit'].
			"</td>".
			( !empty($row['color'])
			? '<td title="' . htmlspecialchars($row['color']) .
		      '"><img style="background-color: ' . htmlspecialchars($row['color']) .
		      ';" alt="problem colour ' . htmlspecialchars($row['color']) .
		      '" src="../images/circle.png" />'
			: '<td>' );
			if ( IS_ADMIN ) {
				echo "</td><td class=\"editdel\">" .
					editLink('problem', $row['probid']) . " " . 
					delLink('problem','probid',$row['probid']);
			}
			echo "</td></tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('problem');
	if ( class_exists("ZipArchive") ) {
		echo "\n" . addForm('problem.php', 'post', null, 'multipart/form-data') .
	 		addHidden('id', @$data['probid']) .
	 		'Problem archive:' .
	 		addFileField('problem_archive') . 
	 		addSubmit('Upload', 'upload') . 
	 		addEndForm() . "\n";
	}
       	echo "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
