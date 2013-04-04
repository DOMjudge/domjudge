<?php
// $Id$

/******************************************************************************
* lib.database.php version 1.4.1
******************************************************************************/

/******************************************************************************
*    Licence                                                                  *
*******************************************************************************

Copyright (C) 2001-2010 Jeroen van Wolffelaar <jeroen@php.net>, et al.

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

/**
 * To be used with or without constructor. Without constructor, a simple
 * extend is possible:
 *
 * class fake_db extends db
 * {
 *		function my_db()
 *		{
 *			$this->db('dilithium','localhost','nobody','<password>',TRUE);
 *			// for faking another db
 *			$this->setprefix('fake');
 *		}
 * }
 * This uses the real database 'dilithium' to fake the database 'fake'.
 * In 'dilithium', the tables from 'fake' have the prefix 'fake_'.
 * So if you want to fake the table 'myfake' from 'fake':
 *		$fake_db->insert('mytable',array('name'=>'me, myself and I'));
 * then this will be mapped to the following query on 'dilithium':
 *		INSERT fake_mytable SET name='me, myself and I';
 */
class db
{
	private $host;
	private $database;
	private $user;
	private $password;
	private $persist;

	private $_connection=FALSE;
	private $_prefix = '';
	private $_cached_metadata;

	function __construct($database, $host, $user, $password, $persist=TRUE)
	{
		$this->database = $database;
		$this->host     = $host;
		$this->user     = $user;
		$this->password = $password;
		$this->persist  = $persist;

		$this->_connection = FALSE;
		$this->_prefix = '';
		$this->_cached_metadata = array();
	}

	public function setprefix($prefix)
	{
		$this->_prefix = $prefix;
	}

	public function metadata($table)
	{
		if(!isset($this->_cached_metadata[$table])) {
			$res  = mysql_list_fields($this->database, $table);
			$this->_cached_metadata[$table] = db::metadataData($res);
			mysql_free_result($res);
		}
		return $this->_cached_metadata[$table];
	}

	// Helper method, is also used by db_result->metadata()
	public static function metadataData($res)
	{
		$count = mysql_num_fields($res);
		$data  = array();
		for ($i=0; $i<$count; $i++) {
			$data[$i]["table"] = mysql_field_table($res, $i);
			$data[$i]["name"]  = mysql_field_name ($res, $i);
			$data[$i]["type"]  = mysql_field_type ($res, $i);
			$data[$i]["len"]   = mysql_field_len  ($res, $i);
			$data[$i]["flags"] = mysql_field_flags($res, $i);
		}
		return $data;
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
		- keyvaluetable: select two columns, and this returns a map from the first
		  field (key) to the second (exactly one value)
	*/
	public function q() // queryf
	{
		$this->connect();

		$argv = func_get_args();
		$format = trim(array_shift($argv));
		list($key) = preg_split('/\s+/', $format, 2);
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
			case 'set':
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
			case 'keyvaluetable':
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
				throw new InvalidArgumentException(
				    "SQL command/lib keyword '$key' unknown!");
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
				throw new BadMethodCallException("Not enough arguments");
			}
			$val = array_shift($argv);
			switch ($part{0}) {
				case 'A':
					if (!is_array($val) || !$val) {
						throw new InvalidArgumentException(
							"%A in \$DATABASE->q() has to correspond to a "
							. "non-empty array, it's" . " now a '$val' (Query:"
							. "'$key $query')!");
					}
					$GLOBALS['MODE'] = $part{1};
					$query .= implode( ', '
					                 , array_map( array($this, 'val2sql')
					                            , $val));
					unset($GLOBALS['MODE']);
					$query .= substr($part,2);
					break;
				case 'S':
					$parts = array();
					foreach ( $val as $field => $value ) {
						$parts[] = '`'.$field.'` = '.$this->val2sql($value);
					}
					$query .= implode(', ', $parts);
					unset($parts);
					$query .= substr($part,1);
					break;
				case 's':
				case 'c':
				case 'i':
				case 'f':
				case 'l':
				case '.':
					$query .= $this->val2sql($val, $part{0});
					$query .= substr($part,1);
					break;
				case '_': // eat one argument
					$query .= substr($part,1);
					break;
				default:
					throw new InvalidArgumentException(
					    "Unknown %-code: " . $part{0});
			}

		}

		if ($literal) {
			user_error("Internal error in q()", E_USER_ERROR);
		}
		if ($argv) {
			throw new BadMethodCallException("Not all arguments to q() are"
			    . " processed");
		}

		$res = $this->execute($query);

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
				throw new UnexpectedValueException("$this->database query error"
				    . " ($key $query): Query did not return any rows");
			}
			if ($res->count() > 1) {
				throw new UnexpectedValueException("$this->database query error"
				    . "($key $query): Query returned too many rows"
					. "(".$res->count().")");
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
		if ($key == 'keyvaluetable') {
			return $res->getkeyvaluetable();
		}
		if ($key == 'column') {
			return $res->getcolumn();
		}

		return $res;
	}

	private function execute($query)
	{
		$query = trim($query);

		// reselect DB, could have been changed by some bad php/mysql
		// implementation.
		mysql_select_db($this->database, $this->_connection);

		list($micros, $secs) = explode(' ',microtime());
		$res = @mysql_query($query,$this->_connection);
		list($micros2, $secs2) = explode(' ',microtime());
		$elapsed_ms = round(1000*(($secs2 - $secs) + ($micros2 - $micros)));

		if ( DEBUG & DEBUG_SQL ) {
			global $DEBUG_NUM_QUERIES;
			$DEBUG_NUM_QUERIES++;
			if ( isset($_SERVER['REMOTE_ADDR']) ) {
				printf("<p>SQL: $this->database: <tt>%s</tt> ({$elapsed_ms}ms)</p>\n",
				       htmlspecialchars($query));
			} else {
				printf("SQL: $this->database: %s ({$elapsed_ms}ms)\n",$query);
			}
		}

		if($res) return $res;

		if(DEBUG) {
			$backtrace = debug_backtrace();
			$callsite = ' file:' . $backtrace[1]['file'] . ', ' .
			            ' line:' . $backtrace[1]['line'] . ', ';
		} else {
			$callsite = '';
		}

		// switch error message depending on errornr.
		switch(mysql_errno($this->_connection)) {
			case 1062:	// duplicate key
			throw new UnexpectedValueException("Item with this key already"
			    . " exists.\n" . $callsite . mysql_error($this->_connection));
			case 1217:  // foreign key constraint
			throw new UnexpectedValueException("This operation would have"
			    . " brought the database in an inconsistent state,\n"
			    . $callsite . mysql_error($this->_connection));
			case 2006:	// MySQL server has gone away
			throw new RuntimeException("MySQL server has gone away");
			default:
			throw new RuntimeException("SQL error, " . $callsite
			    . "Error#" . mysql_errno($this->_connection) . ": "
			    . mysql_error($this->_connection) . ", query: '$query'");
		}
	}

	// connects to a db-server if not yet connected
	public function connect()
	{
		if($this->_connection) return;

		$con = $this->persist ? 'mysql_pconnect' : 'mysql_connect';
		if(!function_exists($con)) {
			throw new RuntimeException("PHP database module missing "
			    . "(no such function: '$con')");
		}

		$this->_connection = @$con($this->host, $this->user, $this->password);
		if(!$this->_connection) {
			throw new RuntimeException("Could not connect to database server "
			    . "(host=$this->host,user=$this->user,password="
			    . str_repeat('*', strlen($this->password)) . ")");
		}
		if(!mysql_select_db($this->database, $this->_connection)) {
			throw new RuntimeException("Could not select database '"
			    . $this->database . "': " . mysql_error($this->_connection));
		}
	}

	// reconnect to a db-server
	public function reconnect()
	{
		if(!$this->persist && $this->_connection)
			mysql_close($this->_connection);

		$this->_connection = NULL;
		$this->connect();
	}

	// transform a php variable into one that can be put directly into a query
	private function val2sql($val, $mode='.')
	{
		if (isset($GLOBALS['MODE'])) {
			$mode = $GLOBALS['MODE'];
		}
		if (!isset($val)) return 'null';
		switch ($mode)
		{
			case 'f': return (float)$val;
			case 'i': return (int)$val;
			case 's': return '"'.mysql_real_escape_string($val, $this->_connection).'"';
			case 'c': return '"%'.mysql_real_escape_string($val, $this->_connection).'%"';
			case 'l': return $val;
			case '.': break;
			default:
				throw new InvalidArgumentException("Unknown mode: $mode");
		}

		switch (gettype($val))
		{
			case 'boolean':
				return (int) $val;
			case 'integer':
			case 'double':
				return $val;
			case 'string':
				return '"'.mysql_real_escape_string($val, $this->_connection).'"';
			case 'array':
			case 'object':
				return '"'.mysql_real_escape_string(serialize($val), $this->_connection).'"';
			case 'resource':
				throw new InvalidArgumentException(
				    'Cannot store a resource in database');
		}
		user_error('Case failed in lib.database', E_USER_ERROR);
	}
}

class db_result
{
	private $_result;
	private $_count;
	private $_tuple;
	private $_nextused;
	private $_cached_metadata;

	function __construct($res)
	{
		$this->_result = $res;
		$this->_count  = mysql_num_rows($res);
		$this->_fields = mysql_num_fields($res);

		$this->_nextused = FALSE;
		$this->_cached_metadata = FALSE;
	}

	public function free()
	{
		mysql_free_result($this->_result);
		$this->_result = null;
	}

	// return an assoc array that is a result row
	public function next()
	{
		// we've nexted over this result too many times already.
		if(!isset($this->_result)) {
			throw new BadMethodCallException(
			    'Result does not contain a valid resource.');
		}
		$this->tuple = mysql_fetch_assoc($this->_result);
		$this->_nextused = TRUE;
		if ($this->tuple === FALSE)
		{
			$this->free();
			return FALSE;
		}
		return $this->tuple = array_map( array('db_result', 'sql2val')
		                               , $this->tuple);
	}

	public function field($field)
	{
		$this->next();

		if($this->tuple===FALSE)
			return FALSE;
		return $this->tuple[$field];
	}

	public function getcolumn($field=NULL)
	{
		if($this->_nextused) {
			throw new BadMethodCallException('getcolumn does not work if'
			    . ' you\'ve already next()ed over the result!');
		}
		$col = array();
		while($this->next())
		{
			$col[]=$field?$this->tuple[$field]:current($this->tuple);
		}
		return $col;
	}

	// returns a 2-dim array containing the result
	public function gettable()
	{
		if($this->_nextused) {
			throw new BadMethodCallException('gettable does not work if'
			    . ' you\'ve already next()ed over the result!');
		}
		$table = array();
		while ($this->next())
		{
			$table[] = $this->tuple;
		}
		return $table;
	}

	// returns a 2-dim array containing the result, with a column as key
	// (separate function for performance reasons)
	public function getkeytable($key)
	{
		if($this->_nextused) {
			throw new BadMethodCallException('getkeytable does not work if'
			    . ' you\'ve already next()ed over the result!');
		}
		$table = array();
		while ($this->next()) {
			$table[$this->tuple[$key]] = $this->tuple;
		}
		return $table;
	}

	// returns an associative array containing the result, with the first
	// column as the key and the second column as the value
	public function getkeyvaluetable()
	{
		if($this->_nextused) {
			throw new BadMethodCallException('getkeyvaluetable does not work if'
			    . ' you\'ve already next()ed over the result!');
		}

		if($this->_fields!=2) {
			throw new BadMethodCallException('getkeyvaluetable only works on a'
			    . 'table with exactly 2 columns!');
		}

		$table = array();
		while ($this->next()) {
			$key = array_shift($this->tuple);
			$value = array_shift($this->tuple);
			$table[$key] = $value;
		}
		return $table;
	}

	public function count()
	{
		return $this->_count;
	}

	public function fieldname($i)
	{
		$data = $this->metadata();
		return $data[$i]['name'];
	}

	public function numfields()
	{
		return count( $this->metadata() );
	}

	public function seek($i)
	{
		return mysql_data_seek($this->_result, $i);
	}

	public function metadata()
	{
		if(!$this->_cached_metadata)
			$this->_cached_metadata = db::metadataData($this->_result);
		return $this->_cached_metadata;
	}

	// inverse of db->val2sql
	private static function sql2val($val)
	{
		$t = @unserialize($val);
		return $t !== false ? $t : $val;
	}
}

