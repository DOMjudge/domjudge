<?php
/**
 * Show source code from the database.
 *
 * $Id$
 */

require('init.php');
$title = 'Show Source';
require('../header.php');

$id = (int)$_GET['id'];

$source = $DB->q('TUPLE SELECT * FROM submission
                  WHERE submitid = %i',$id);

$oldsource = $DB->q('MAYBETUPLE SELECT * FROM submission
                     WHERE teamid = %s AND probid = %s AND langid = %s AND
                     submittime < %s ORDER BY submittime DESC LIMIT 1',
                    $source['teamid'],$source['probid'],$source['langid'],
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
	
	$oldid = (int)$oldsource['submitid'];

	$oldfile = SUBMITDIR.'/'.$oldsource['sourcefile'];
	$newfile = SUBMITDIR.'/'.$source['sourcefile'];
	
	if ( function_exists('xdiff_string_diff') ) {
		
		$difftext = xdiff_string_diff($oldsource['sourcecode'],
									  $source['sourcecode'],2);
		
	} elseif ( is_readable($oldfile) && is_readable($newfile) ) {

		$difftext = `diff -bBt -U 2 $oldfile $newfile 2>&1`;

	} else {
		
		if ( ! ($oldfile = tempnam("/tmp","source-old-s$oldid-")) ||
			 ! ($newfile = tempnam("/tmp","source-new-s$id-"   )) ||
			 ! ($oldhandle = fopen($oldfile,'w')) ||
			 ! ($newhandle = fopen($newfile,'w')) ||
			 ! fwrite($oldhandle,$oldsource['sourcecode']) ||
			 ! fwrite($newhandle,   $source['sourcecode']) ||
			 ! fclose($oldhandle) ||
			 ! fclose($newhandle) ) {
			
			$difftext = "DOMjudge: error generating temporary files for diff.";
			
		} else {

			$difftext = `diff -bBt -U 2 $oldfile $newfile 2>&1`;

		}

		unlink($oldfile);
		unlink($newfile);
	}
	
	echo '<h2 class="filename"><a name="diff"></a>Diff to submission ' .
		"<a href=\"submission.php?id=$oldid\">s$oldid</a> source: " .
		"<a href=\"show_source.php?id=$oldid\">" .
		htmlspecialchars($oldsource['sourcefile']) . "</a></h2>\n\n";

	echo '<pre class="output_text">' .
		htmlspecialchars($difftext) . "</pre>\n\n";
}

require('../footer.php');
