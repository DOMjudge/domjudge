<?php

/**
 * Functions to create syntax highlighted views of source code,
 * supporting different external formatters.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Given sourcecode $source and filename extension (programming language)
 * $ext, output HTML-formatted syntax highlighted code, using one of the
 * known highlighters if available.
 */
function highlight ($source, $ext)
{
	// keep result in case we want to highlight more than once
	static $lib;
	if ( !isset($lib) ) {
		$lib = highlighter_init();
	}
	return call_user_func('highlight_'.$lib, $source, $ext);
}

/**
 * Probe to find if any of the highlighters we know about is installed.
 * Include the relevant file and return the name of the highlighter.
 * If none found, return 'native'.
 *
 * We do not currently check if we can actually initialize the class
 * for the given language extension. If different highlighters support
 * different sets of languages, we could even select based on that. But
 * for now that seems overkill.
 *
 * Can only be called once per request.
 *
 * To add support for new highlighting software, update this function
 * with a list of paths to search for it, and define a corresponding
 * 'highlight_<identifier>' function below that does the actual
 * work.
 */
function highlighter_init ()
{
	// this adds some checks to GeSHi that try to make it
	// marginally more secure when including files. Doesn't hurt.
	define ('GESHI_SECURITY_PARANOID', true);

	$PATHS = array();
	$PATHS['geshi'] = array (
		'geshi/geshi.php',
		'/usr/share/php-geshi/geshi.php' );
	$PATHS['texthighlighter'] = array (
		'Text/Highlighter.php' );

	foreach ( $PATHS as $lib => $libpaths ) {
		foreach ( $libpaths as $path ) {
			if ( @include ($path) ) {
				return $lib;
			}
		}
	}

	return 'native';
}

/**
 * Output syntax highlighted HTML formatted source code using
 * the GeSHi syntax highlighter.
 */
function highlight_geshi ($source, $ext)
{
	switch (strtolower($ext)) {
	case 'hs':  $lang = 'haskell'; break;
	case 'pas': $lang = 'pascal';  break;
	case 'pl':  $lang = 'perl';    break;
	case 'py':  $lang = 'python';  break;
	case 'sh':  $lang = 'bash';    break;
	default:
		$lang = $ext;
	}

	$geshi = new Geshi ($source, $lang);
	$geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
	// TODO: make output use more CSS and less <font>
	if ( $geshi->error()===FALSE ) {
		return $geshi->parse_code();
	}
	
	return highlight_native($source, $ext);
}

/**
 * Output syntax highlighted HTML formatted source code using
 * the PEAR Text Highlighter class.
 */
function highlight_texthighlighter ($source, $ext)
{
	switch (strtolower($ext)) {
	case 'c':    $lang = 'cpp'; break;
	case 'bash': $lang = 'sh';  break;
	default:
		$lang = $ext;
	}

	require('Text/Highlighter/Renderer/Html.php');
	$renderer = new Text_Highlighter_Renderer_Html(
		array("numbers" => HL_NUMBERS_TABLE, "tabsize" => 4));
	$hl =& Text_Highlighter::factory($lang);

	if ( !PEAR::isError($hl) ) {
		$hl->setRenderer($renderer);
		return $hl->highlight($source);
	}

	return highlight_native($source, $ext);
}

/**
 * Simple way to display source code when no highlighters are available.
 * Adds line numbers and sets monospaced font.
 */
function highlight_native ($source, $ext)
{
	$sourcelines = explode("\n", $source);
	$ret = '<pre class="output_text">';
	$i = 1;
	$lnlen = strlen(count($sourcelines));
	foreach ($sourcelines as $line ) {
		$ret .= "<span class=\"lineno\">" . str_pad($i, $lnlen, ' ', STR_PAD_LEFT) .
			"</span>  " . htmlspecialchars($line) . "\n";
		$i++;
	}
	return $ret . "</pre>\n\n";
}
