<?php // lib.lang 1.0; Copyright (c) 2001 <jeroen@php.net>

/******************************************************************************
*    Info                                                                     *
*******************************************************************************

Misc. functions that provide extra functionallity missing in the standard
functions. These functions are very close to the language.

/******************************************************************************
*    Licence                                                                  *
*******************************************************************************

[ Hopefully there is no copyright on copyright notices, otherwise I've got a 
  problem ;-)                                                                 ]

Copyright (C) 2001 Jeroen van Wolffelaar <jeroen@php.net>

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

if (!@define('INCLUDED_LIB_LANG',true)) return;

/******************************************************************************
*    Implementation                                                           *
******************************************************************************/

// geeft de union van de values van arrays. Volgorde enzo is NIET defined.
// maar op dit moment wordt de EERSTE behouden, en de rest van de volgorde
// behouden.
	
// als je maar 1 arg opgeeft, wordt aangenomen dat je de union wilt van
// de arrays die in die ene array zitten.

// keys worden NIET behouden
function array_union($arr1)
{
	$argv = (func_num_args() == 1) ? $arr1 : func_get_args();
	
	$ret = array();
	
	foreach ($argv as $arg)
	{
		foreach ($arg as $val)
		{
			if (!in_array($val,$ret))
					$ret[] = $val;
		}
	}
	
	return $ret;
}	

// returns a REAL copy of $value. This can 
// be used in workarounds for bug#6417
function cp($value)
{
	return unserialize(serialize($value));
}


// hetzelfde als define, maar:
// - bij error abort
// - toevoegen aan $lang_defined_constants
// - maakt er ook een globale variabele van
// 
function lang_define($name,$value)
{
	// vanaf versie 4.0.6: gebruik get_defined_constants()
	if (!define($name,$value))
	{
		user_error("Define of $name failed",E_USER_ERROR);
		// overbodig, maar voor de zekerheid:
		exit;
	}
	
	$GLOBALS['lang_defined_constants'][$name] = $value;
	$GLOBALS[$name]                           = $value;
}

// include this oneliner as the first line of each function you want to
// use your constants in

//	extract($GLOBALS['lang_defined_constants']);
// starting with 406:
//  extract(get_defined_constants());
