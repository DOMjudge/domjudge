<?php
/**
 * Show source code from the database.
 *
 * $Id$
 */

require('init.php');

$id = (int)$_GET['id'];

$source = $DB->q('TUPLE SELECT * FROM submission
                  WHERE submitid = %i',$id);

$oldsource = $DB->q('MAYBETUPLE SELECT * FROM submission
                     WHERE teamid = %s AND probid = %s AND langid = %s AND
                     submittime < %s ORDER BY submittime DESC LIMIT 1',
                    $source['teamid'],$source['probid'],$source['langid'],
                    $source['submittime']);

// Use PEAR Text::Highlighter class if available
if ( include_highlighter() ) {
	switch (strtolower($source['langid'])) {
		case 'c':
		case 'cpp':
			$lang = 'cpp';
			break;
		case 'java';
		case 'perl':
		case 'ruby':
		case 'php':
		case 'python':
			$lang = $source['langid'];
	}
	if ( isset($lang) ) {
		include('Text/Highlighter/Renderer/Html.php');
		$renderer = new Text_Highlighter_Renderer_Html(array("numbers" => HL_NUMBERS_TABLE, "tabsize" => 4));
		$hl =& Text_Highlighter::factory(($source['langid'] == 'c'?'cpp':$source['langid']));
	}
	$sourcecss = true;
}

$title = 'Source: ' . htmlspecialchars($source['sourcefile']);
require('../header.php');

if ( $oldsource ) {
	echo "<p><a href=\"#diff\">Go to diff to previous submission</a></p>\n\n";
}

echo '<h2 class="filename"><a name="source"></a>Submission ' .
	"<a href=\"submission.php?id=$id\">s$id</a> source: " .
	"<a href=\"show_source.php?id=$id\">" .
	htmlspecialchars($source['sourcefile']) . "</a></h2>\n\n";

if ( strlen($source['sourcecode']) ) {
	// Someone submitted an empty file. Cope gracefully.
	echo "<p><em>empty file</em></p>\n\n";
} elseif ( isset($hl) ) {
	// We managed to set up the highligher
	$hl->setRenderer($renderer);
	echo $hl->highlight($source['sourcecode']);
} else {
	// else display it ourselves
	$sourcelines = explode("\n", $source['sourcecode']);
	echo '<pre class="output_text">';
	$i = 1;
	$lnlen = strlen(count($sourcelines));
	foreach ($sourcelines as $line ) {
		echo "<span class=\"lineno\">" . str_pad($i, $lnlen, ' ', STR_PAD_LEFT) .
			"</span>  " . htmlspecialchars($line) . "\n";
		$i++;
	}
	echo "</pre>\n\n";
}


// show diff to old source
if ( $oldsource ) {
	
	$oldfile = SUBMITDIR.'/'.$oldsource['sourcefile'];
	$newfile = SUBMITDIR.'/'.$source['sourcefile'];
	$oldid = (int)$oldsource['submitid'];

	// Try different ways of diffing, in order of preference.
	if ( function_exists('xdiff_string_diff') ) {
		// The PECL xdiff PHP-extension.
		
		$difftext = xdiff_string_diff($oldsource['sourcecode'],
									  $source['sourcecode'],2);
		
	} elseif ( is_readable($oldfile) && is_readable($newfile) ) {
		// A direct diff on the sources in the SUBMITDIR.

		$difftext = `diff -bBt -U 2 $oldfile $newfile 2>&1`;

	} else {
		// Write sources to tempfile and diff those.
		// This does not work with safe_mode, which is a known
		// limitation of tempnam. In safe_mode, this currently
		// creates temp files that we cannot access nor remove...

		if ( ! ($oldfile = tempnam("/tmp","source-old-s$oldid-")) ||
			 ! ($newfile = tempnam("/tmp","source-new-s$id-"   )) ||
			 ! ($oldhandle = fopen($oldfile,'w')) ||
			 ! ($newhandle = fopen($newfile,'w')) ||
			 fwrite($oldhandle,$oldsource['sourcecode']) === FALSE ||
			 fwrite($newhandle,   $source['sourcecode']) === FALSE ||
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
