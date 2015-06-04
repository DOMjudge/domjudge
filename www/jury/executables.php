<?php
/**
 * View the executables
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Executables';

require(LIBWWWDIR . '/header.php');

echo "<h1>Executables</h1>\n\n";

// Select all data, sort problems from the current contest on top.
$res = $DB->q('SELECT execid, description, md5sum, type, OCTET_LENGTH(zipfile) AS size
               FROM executable ORDER BY execid');

if( $res->count() == 0 ) {
	echo "<p class=\"nodata\">No executables defined</p>\n\n";
} else {
	echo "<table class=\"list sortable\">\n<thead>\n" .
	     "<tr><th scope=\"col\">ID</th><th scope=\"col\">type</th>" .
	     "<th scope=\"col\">description</th>" .
	     "<th scope=\"col\">size</th><th scope=\"col\">md5</th>" .
	     "<th></th><th></th></tr></thead>\n<tbody>\n";

	$lastcid = -1;

	while($row = $res->next()) {
		$link = '<a href="executable.php?id=' . urlencode($row['execid']) . '">';

		echo "<tr><td class=\"execid\">" . $link .
				htmlspecialchars($row['execid'])."</a>".
			"</td><td>" . $link . htmlspecialchars($row['type'])."</a>".
			"</td><td>" . $link . htmlspecialchars(str_cut($row['description'],40))."</a>".
			"</td><td class=\"size\">" . $link .
				printsize($row['size'])."</a>".
			"</td><td class=\"md5\">" . $link .
				htmlspecialchars($row['md5sum'])."</a>".
			"</td>";
		if ( IS_ADMIN ) {
			echo '<td title="export executable as zip-file"><a href="executable.php?fetch&amp;id=' . urlencode($row['execid']) .
			     '"><img src="../images/b_save.png" alt="export" /></a></td>' .
			     "<td class=\"editdel\">" .
			     editLink('executable', $row['execid']) . " " .
			     delLink('executable','execid',$row['execid']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('executable') . "</p>\n\n";
	if ( class_exists("ZipArchive") ) {
		echo "\n" . addForm('executable.php', 'post', null, 'multipart/form-data') .
	 		'Executable archive(s): ' .
			addSelect('type', $executable_types) .
	 		addFileField('executable_archive[]', null, ' required multiple accept="application/zip"') .
	 		addSubmit('Upload', 'upload') .
	 		addEndForm() . "\n";
	}
}

require(LIBWWWDIR . '/footer.php');
