<?php // lib.debug; Copyright (c) 2001 <www@A-Eskwadraat.nl>

/******************************************************************************
*    Info                                                                     *
*******************************************************************************

Debugging functions

/******************************************************************************
*    TODO                                                                     *
*******************************************************************************

?

/******************************************************************************
*    Licence                                                                  *
*******************************************************************************

[ Hopefully there is no copyright on copyright notices, otherwise I've got a 
  problem ;-)                                                                 ]

Copyright (C) 2001 A-Eskwadraat WebCie <www@A-Eskwadraat.nl>

Permission is hereby granted, free of charge, to any person obtaining a copy of
these scripts and associated documentation files (the ``Library''), to deal in
the Library without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Library, and to permit persons to whom the Library is furnished to
do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies (full or partial) of the Library.

Except as contained in this notice, the names of individuals credited with
contribution to this Library shall not be used in advertising or otherwise to
promote the sale, use or other dealings in this Library without prior written
authorization from the individuals in question.

Any modification of this Library that is publically distributed will be
identified with a different name and the version strings in any derived Library
will be changed so that no possibility of confusion between the derived package
and this Library will exist.

If you distribute a modified version of this Library, you are encouraged to
send the author(s) a copy. Or make it available to the author(s) through
ftp; let him know where it can be found. If the number of changes is small
e-mailing the diffs will do. When the author(s) asks for it (in any way) you
must make your changes, available to them.

The author(s) reserves the right to include any changes in the official version
of this Library. This is negotiable. You are not allowed to distribute a
modified version of this Library when you are not willing to make the source
code available to the author(s).

THE LIBRARY IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
AUTHOR OR ANY OTHER CONTRIBUTOR BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE LIBRARY OR THE USE OR OTHER DEALINGS IN THE
LIBRARY.

/******************************************************************************
*    Initialisation                                                           *
******************************************************************************/

if (!@define('INCLUDED_LIB_DEBUG',true)) return;

include_once('lib.debug.ini.php');

/******************************************************************************
*    Implementation                                                           *
******************************************************************************/

function debug_dump($var) 
{
	if (!DEBUG) return;

	ob_start();  // Buffer output
	foreach(func_get_args() as $var) {
		print_r($var);
		echo "\n";
	}
	$buffer = ob_get_contents(); // Grab the print_r output
	ob_end_clean();  // Silently discard the output & stop buffering
	print '<pre>';
	print htmlentities($buffer);
	print '</pre>';
}

function debug_sql_msg($text)
{
	global $SQLMSG;

	if(isset($SQLMSG) && $SQLMSG) debug_msg($text);
}

function debug_msg($text)
{
	if(DEBUG)
	{
		echo $text."<br>\n";
		flush();
		if (isset($_SERVER['REMOTE_ADDR'])) {
			// web-request
			error_log("$text\n",3,"/tmp/www-debug-msgs.".$_SERVER['SERVER_PORT']);
		} else {
			error_log("$text\n",3,"/tmp/www-debug-msgs.".$_ENV['USER']);
		}
	}
}

function verbose($msg,$level)
{
	global $verbose_file;
	if (VERBOSE >= $level)
		error_log("[$level]".microtime().":$msg\n",3,$verbose_file);
}
