<?php
/**
 * Show source code from the database.
 *
 * $Id$
 */

require('init.php');
$title = 'Show Source';
require('../header.php');
require('menu.php');

$id = (int)$_GET['id'];

$source = $DB->q('TUPLE SELECT * FROM submission
                  WHERE submitid = %i',$id);

$oldsource = $DB->q('MAYBETUPLE SELECT * FROM submission
                     WHERE team = %s AND probid = %s AND langid = %s AND
                     submittime < %s ORDER BY submittime DESC LIMIT 1',
					$source['team'],$source['probid'],$source['langid'],
					$source['submittime']);

if ( $oldsource ) {
	echo '<p><a href="#diff">Goto diff to previous submission</a></p>';
}

echo '<h2 class="filename"><a name="source"></a>Submission ' .
	"<a href=\"submission.php?id=$id\">s$id</a> source: " .
	"<a href=\"show_source.php?id=$id\">" .
	htmlspecialchars($source['sourcefile']) . "</a></h2>\n\n";

echo '<pre class="output_text">' .
	htmlspecialchars($source['sourcecode']) . "</pre>\n\n";

if ( $oldsource ) {
	
	$oldid = $oldsource['submitid'];
	
	$oldfile = SUBMITDIR.'/'.$oldsource['sourcefile'];
	$newfile = SUBMITDIR.'/'.$source['sourcefile'];
	
	$difftext = `diff -bBt -u2 $oldfile $newfile 2>&1`;
	
	echo '<h2 class="filename"><a name="diff"></a>Diff to submission ' .
		"<a href=\"submission.php?id=$oldid\">s$oldid</a> source: " .
		"<a href=\"show_source.php?id=$oldid\">" .
		htmlspecialchars($oldsource['sourcefile']) . "</a></h2>\n\n";

	echo '<pre class="output_text">' .
		htmlspecialchars($difftext) . "</pre>\n\n";
}

require('../footer.php');
