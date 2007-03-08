<?php 
// $Id$

/******************************************************************************
* lib.database.php version 1.2.0
******************************************************************************/

/******************************************************************************
*    Licence                                                                  *
*******************************************************************************

Copyright (C) 2001-2006 Jeroen van Wolffelaar <jeroen@php.net>, et al.

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

/******************************************************************************
*    Initialisation                                                           *
******************************************************************************/

if (!@define('INCLUDED_LIB_DATABASE',true)) return;

//define('DEBUG'    , 0 );

define('DB_EQ'    , '='        );
define('DB_NEQ'   , '!='       );
define('DB_LIKE'  , 'like'     );
define('DB_CONT'  , 'cont'     );
define('DB_NLIKE' , 'not like' );
//define('DB_NCONT' , 'ncont'    );
define('DB_REGEX' , 'regex'    );
define('DB_NREGEX', 'not regex');

// Special sentinel for set, because it should always use '=', and never 'is'
// (e.g. with null)
define('DB_SET', 'DB_SET');

/******************************************************************************
*    Helperfunctions
******************************************************************************/

function db_vw($kolom,$waarde,$mode=NULL)
{
	if (!$mode) $mode = DB_EQ; // not in the header because you can also pass
				// NULL explicitly
	
	if ($waarde === NULL) {
		switch ($mode) {
			case DB_EQ: $mode = 'is'; break;
			case DB_NEQ: $mode = 'is not'; break;
		}
	}

	// if set, then escape
	if ($mode === DB_SET) {
		$mode='=';
		$waarde = db__val2sql($waarde);
	// like
	} elseif ($mode === DB_CONT) {
		$waarde = db__val2sql((string)$waarde);
		$waarde = '"%'.substr($waarde,1, -1).'%"';
		$mode = 'LIKE';
	// this is also a condition
	} elseif ( is_array($waarde) ) {
		$mode = 'in';
		$waarde = array_map('db__val2sql', $waarde);
		$waarde = '('. implode(',', $waarde) . ')';
	// else just escape, because is a condition
	} else {
		$waarde = db__val2sql($waarde);
	}

	return "$kolom $mode $waarde";
}

function db_and()
{
	return db__andor(func_get_args(),'AND','1');
}

function db_or() 
{
	return db__andor(func_get_args(),'OR','00');
}

/******************************************************************************
*    Internal functions                                                       *
******************************************************************************/
// syntax:
// [1] db_and( [string cond [, string cond [, string cond [...] ] ] ] )
// [2] db_and( array conditions )
// [3] db_and( string column, array values [, string compare_mode])
// mode: one of DB_EQ (default), DB_NEQ , DB_LIKE

function db__andor($params,$op,$default)
{
	// check for case [3]:
	if (is_array(@$params[1])) {
		@list ($col,$values,$mode) = $params;
		if (!$values) return $default;
		return implode(" $op ", array_map(
			create_function('$value',
			"return db_vw('$col',\$value,'$mode');"),$values));
	}
	
	// let args be array-of-arguments, depending on [1] or [2]
	$args = is_array(@$params[0]) ? $params[0] : $params;
	return $args ? '('.implode(") $op (",$args).')' : $default;
}


/******************************************************************************
*    (internal) Connection handling                                           *
******************************************************************************/

// connects to a db-server if not yet connected
function db__connect($database,$host,$user,$pass,$persist=TRUE)
{
	$con = $persist ? 'mysql_pconnect' : 'mysql_connect';
	
	$db__connection = $con($host,$user,$pass)
		or user_error("Could not connect to database server ".
			"(host=$host,user=$user,password=".ereg_replace('.','*',$pass).")",
			E_USER_ERROR);
	mysql_select_db($database,$db__connection)
			or error("Could not select database '$database': ".
				mysql_error($db__connection),
				E_USER_ERROR);
	return $db__connection;
}


/******************************************************************************
*    (internal) Type conversion                                               *
******************************************************************************/
// transform a php variable into one that can be put directly into a query
function db__val2sql($val, $mode='.')
{
	if (isset($GLOBALS['MODE'])) {
		$mode = $GLOBALS['MODE'];
	}
	if (!isset($val)) return 'null';
	switch ($mode) {
		case 'f': return (float)$val;
		case 'i': return (int)$val;
		case 's': return '"'.mysql_escape_string($val).'"';
		case 'c': return '"%'.mysql_escape_string($val).'%"';
		case 'l': return $val;
		case '.': break;
		default: 
			user_error("Unknown mode: $mode", E_USER_ERROR);
	}

	switch (gettype($val))
	{
		case 'boolean':
			return (int) $val;
		case 'integer':
		case 'double':
			return $val;
		case 'string':
			return '"'.mysql_escape_string($val).'"';
		case 'array':
		case 'object':
			return '"'.mysql_escape_string(serialize($val)).'"';
		case 'resource':
			user_error('Cannot store a resource in database', E_USER_ERROR);
			/* break missing intentionally */
	}
	user_error('Case failed in lib.database', E_USER_ERROR);
}

function db__sql2val($val)
{
	$t = @unserialize($val);
	return $t !== false ? $t : $val;
}

/**
 * usage:
 * - $wat is a string, "<table>", with $db being the database
 * - $db is a db_result
 *
 * $result[]:
 *   [0]["table"]  table name
 *   [0]["name"]   field name
 *   [0]["type"]   field type
 *   [0]["len"]    field length
 *   [0]["flags"]  field flags
 */
function db__metadata(&$db,$table=null)
{
    $count = 0;
    $id    = 0;
    $res   = array();

	if($table)
	{
		$id=@mysql_list_fields($db->database,$table);
	}
	else
	{
		$id=$db->_result;
	}
 
	$count = @mysql_num_fields($id);

    // made this IF due to performance (one if is faster than $count if's)
	for ($i=0; $i<$count; $i++) {
		$res[$i]["table"] = @mysql_field_table ($id, $i);
		$res[$i]["name"]  = @mysql_field_name  ($id, $i);
		$res[$i]["type"]  = @mysql_field_type  ($id, $i);
		$res[$i]["len"]   = @mysql_field_len   ($id, $i);
		$res[$i]["flags"] = @mysql_field_flags ($id, $i);
	}

	// free the result only if we were called on a table
	if ($table) @mysql_free_result($id);
	return $res;
}
			
/**
 * To be used with or without constructor. Without constructor, a simple
 * extend is possible:
 *
 * class fake_db extends db
 * {
 * 		function my_db()
 * 		{
 * 			$this->db('dilithium','localhost','nobody','<password>',TRUE);
 *			// for faking another db
 *			$this->setprefix('fake');
 *		}
 * }
 * This uses the real database 'dilithium' to fake the database 'fake'.
 * In 'dilithium', the tables from 'fake' have the prefix 'fake_'.
 * So if you want to fake the table 'myfake' from 'fake':
 * 		$fake_db->insert('mytable',array('name'=>'me, myself and I'));
 * then this will be mapped to the following query on 'dilithium':
 * 		INSERT fake_mytable SET name='me, myself and I';
 */
class db
{
	var $host;
	var $database;
	var $user;
	var $password;
	var $persist;

	var $_connection=FALSE;
	var $_prefix = '';
	var $_cached_metadata;

	function db($database,$host,$user,$password,$persist=TRUE)
	{
		$this->database=$database;
		$this->host=$host;
		$this->user=$user;
		$this->password=$password;
		$this->persist=$persist;

	}

	function setprefix($prefix)
	{
		$this->_prefix=$prefix;
	}

	function metadata($table)
	{
		if(!@$this->_cached_metadata[$table])
			$this->_cached_metadata[$table]=db__metadata($this,$table);
		return $this->_cached_metadata[$table];
	}

	/**
	 * Execute a query.
	 *
	 * syntax:
		%%: literal %
		%.: auto-detect
		%s: string with quotes and escaping
		%c: string with quotes and escaping, embraced by percent signs for
		    usage with LIKE
		%i: integer
		%f: floating point
		%l: literal (no quoting/escaping)
		%_: nothing, but do process one argument
		%A?: array of type ?, comma separated
		%S: array of key => ., becomes key=., comma separated

		query can be prepended with a keyword to change the returned data
		format:
		- returnid: for use with INSERT, returns the auto_increment value used
		  for that row.
		- returnaffected: return the number of modified rows by this query.
		- tuple, value: select exactly one row or one value
		- maybetuple, maybevalue: select zero or one rows or values
		- column: return a list of a single attribute
		- table: return complete result in one array
		- keytable: same as table but arraykey is the field called ARRAYKEY
	*/
	function q() // queryf
	{
		$argv = func_get_args();
		$format = trim(array_shift($argv));
		list($key) = explode(' ', $format, 2);
		$key = strtolower($key);
		$maybe = false;
		switch ($key) {

			// modifying commands; keywords first, then regular
			case 'returnid':
			case 'returnaffected':
				$format = substr($format,strlen($key)+1);
			case 'insert':
			case 'update':
			case 'replace':
			case 'delete':
				$type = 'update';
				break;

			// select commandos; keywords, then regular
			case 'maybetuple':
			case 'maybevalue':
				$maybe = true;
				$key = substr($key,5,5);
				// ATTENTION: the substr below will use the new key as its
				// keylength, that's why we have to take the length of
				// VALUE/TUPLE from the format. Luckily BOTH are 5 long.
				$format = substr($format,5);
			case 'column':
			case 'table':
			case 'keytable':
			case 'tuple':
			case 'value':
				$format = substr($format,strlen($key)+1);
			case 'select':
			case 'describe':
			case 'show':
				$type = 'select';
				break;
			// transactions
			case 'start':	// start transaction. Do not support BEGIN, it's deprecated
			case 'commit':
			case 'rollback':
				$type = 'transaction';
				break;
			default:
				user_error("SQL command/lib keyword '$key' unknown!", E_USER_ERROR);
		}

		$parts = explode('%', $format);
		$literal = false;
		foreach ($parts as $part) {
			if ($literal) {
				$literal = false;
				$query .= $part;
				continue;
			}
			if (!isset($query)) {
				// first part
				$query = $part;
				continue;
			}
			if (!$part) {
				// literal %%
				$query .= '%';
				$literal=true;
				continue;
			}
			if (!$argv) {
				user_error("Not enough arguments", E_USER_ERROR);
			}
			$val = array_shift($argv);
			switch ($part{0}) {
				case 'A':
					if (!is_array($val) || !$val) {
						user_error("%A in \$DATABASE->q() has to correspond to a "
							."non-empty array, it's now a "
							."'$val'!", E_USER_ERROR );
					}
					$GLOBALS['MODE'] = $part{1};
					$query .= implode(', ', array_map('db__val2sql', $val));
					unset($GLOBALS['MODE']);
					$query .= substr($part,2);
					break;
				case 'S':
					$query .= implode(', ', array_map(create_function(
						'$key,$value', 'return db_vw($key, $value,
						DB_SET);'),array_keys($val),$val));
					$query .= substr($part,1);
					break;
				case 's':
				case 'c':
				case 'i':
				case 'f':
				case 'l':
				case '.':
					$query .= db__val2sql($val, $part{0});
					$query .= substr($part,1);
					break;
				case '_': // eat one argument
					$query .= substr($part,1);
					break;
				default:
					user_error("Unkown %-code: ".$part{0}, E_USER_ERROR);
			}

		}

		if ($literal) {
			user_error("Internal error in q()", E_USER_ERROR);
		}
		if ($argv) {
			user_error("Not all arguments to q() are processed", E_USER_ERROR);
		}

		$res = $this->_execute($query);
		
		// nothing left to do if transaction statement...
		if ( $type == 'transaction' ) {
			return null;
		}

		if ($type == 'update') {
			if ($key == 'returnid') {
				return mysql_insert_id($this->_connection);
			}
			if ($key == 'returnaffected') {
				return mysql_affected_rows($this->_connection);
			}
			return;
		}
		
		$res = new db_result($res);

		if ($key == 'tuple' || $key == 'value') {
			if ($res->count() < 1) {
				if ($maybe) return NULL;
				user_error("$this->database query error ($key $query".
					"): Query did not return any rows", E_USER_ERROR);
			}
			if ($res->count() > 1) {
				user_error("$this->database query error ($key $query".
					"): Query returned too many rows (".$res->count().")",
					E_USER_ERROR);
			}
			$row = $res->next();
			if ($key == 'value') {
				return array_shift($row);
			}
			return $row;
		}

		if ($key == 'table') {
			return $res->gettable();
		}
		if ($key == 'keytable') {
			return $res->getkeytable('ARRAYKEY');
		}
		if ($key == 'column') {
			return $res->getcolumn();
		}

		return $res;
	}

	function _execute($query)
	{
		$query = trim($query);
		if(!$this->_connection)
		{
			$this->_connection=db__connect($this->database,$this->host,
										   $this->user,$this->password,$this->persist);
		}

		// reselect DB, could have been changed by some bad php/mysql
		// implementation.
		mysql_select_db($this->database,$this->_connection);

		list($micros, $secs) = explode(' ',microtime());
		$res = @mysql_query($query,$this->_connection);
		list($micros2, $secs2) = explode(' ',microtime());
		$elapsed_ms = round(1000*(($secs2 - $secs) + ($micros2 - $micros)));

		if ( DEBUG ) {
			global $DEBUG_NUM_QUERIES;
			printf("<p>SQL: $this->database: <tt>%s</tt> ({$elapsed_ms}ms)</p>",
				htmlspecialchars($query));
			$DEBUG_NUM_QUERIES++;
		}

		if (!$res)
		{
			// switch error message depending on errornr.
			switch(mysql_errno($this->_connection)) {
				case 1062:	// duplicate key
				user_error("Item with this key already exists.\n".
					mysql_error($this->_connection), E_USER_ERROR );
				case 1217:  // foreign key constraint
				user_error("This operation would have brought the database in an ".
					"inconsistent state.\n".
					mysql_error($this->_connection), E_USER_ERROR );
				default:
				user_error("SQL syntax-error ($query). Error#".
					mysql_errno($this->_connection).": ".
					mysql_error($this->_connection),
					E_USER_ERROR);
			}
		}

		return $res;
	}
}

class db_result
{
	var $_result = FALSE;
	var $_count = 0;
	var $_tuple;
	var $_nextused = FALSE;

	function db_result($res)
	{
		$this->_result=$res;
		$this->_count=mysql_num_rows($res);
	}

	function free()
	{
		return @mysql_free_result($this->_result);
	}

	// return an assoc array that is a result row
	function next()
	{
		// we've nexted over this result too many times already.
		if(!isset($this->_result)) {
			user_error('Result does not contain a valid resource.', E_USER_ERROR);
		}  
		$this->tuple = mysql_fetch_assoc($this->_result);
		$this->_nextused = TRUE;
		if ($this->tuple === FALSE)
		{
			// garbage collection
			$this->_result = null;
			return FALSE;
		}
		return $this->tuple = array_map('db__sql2val',$this->tuple);
	}

	function field($field)
	{
		$this->next();

		if($this->tuple===FALSE)
			return FALSE;
		return $this->tuple[$field];
	}

	function getcolumn($field=NULL)
	{
		if($this->_nextused) {
			user_error('Getcolumn does not work if you\'ve already next()ed over the result!',
				E_USER_ERROR);
		}
		$col = array();
		while($this->next())
		{
			$col[]=$field?$this->tuple[$field]:current($this->tuple);
		}
		return $col;
	}

	// returns a 2-dim array containing the result
	function gettable()
	{
		if($this->_nextused) {
			user_error('Gettable does not work if you\'ve already next()ed over the result!',
				E_USER_ERROR);
		}
		$tabel = array();
		while ($this->next())
		{
			$tabel[] = $this->tuple;
		}
		return $tabel;
	}

	// returns a 2-dim array containing the result, with a column as key
	// (separate function for performance reasons)
	function getkeytable($key)
	{
		if($this->_nextused) {
			user_error('Gettable does not work if you\'ve already next()ed over the result!',
				E_USER_ERROR);
		}
		$tabel = array();
		while ($this->next()) {
			$tabel[$this->tuple[$key]] = $this->tuple;
		}
		return $tabel;
	}

	function count()
	{
		return $this->_count;
	}

	function fieldname($i)
	{
		$data = $this->metadata();
		return $data[$i]['name'];
	}

	function numfields()
	{
		return count( $this->metadata() );
	}

	function seek($i)
	{
		return mysql_data_seek($this->_result, $i);
	}

	function metadata()
	{
		return db__metadata($this);
	}
}

