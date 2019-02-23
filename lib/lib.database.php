<?php declare(strict_types=1);
/**
 * Database abstraction functions.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 *
 * Originally based on: lib.database.php 1.4.1, Copyright (C) 2001-2010
 * Jeroen van Wolffelaar <jeroen@php.net>, et al.; licensed under the
 * GNU GPL version 2 or higher.
 */

if (!@define('INCLUDED_LIB_DATABASE', true)) {
    return;
}

class db
{
    private $host;
    private $database;
    private $user;
    private $password;
    private $persist;
    private $flags;

    private $_connection=false;

    public function __construct($database, $host, $user, $password, $persist=true, $flags = null)
    {
        $this->database = $database;
        $this->host     = $host;
        $this->user     = $user;
        $this->password = $password;
        $this->persist  = $persist;
        $this->flags    = $flags;

        $this->_connection = false;
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
        %S:  array of key => ., becomes key=., comma separated
        %SS: array of key => ., becomes key=., "AND" separated

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
                $format = substr($format, strlen($key)+1);
                // no break
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
                $key = substr($key, 5, 5);
                // ATTENTION: the substr below will use the new key as its
                // keylength, that's why we have to take the length of
                // VALUE/TUPLE from the format. Luckily BOTH are 5 long.
                $format = substr($format, 5);
                // no break
            case 'column':
            case 'table':
            case 'keytable':
            case 'keyvaluetable':
            case 'tuple':
            case 'value':
                $format = substr($format, strlen($key)+1);
                // no break
            case 'select':
            case 'describe':
            case 'show':
                $type = 'select';
                break;
            // transactions
            case 'start': // start transaction. Do not support BEGIN, it's deprecated
            case 'commit':
            case 'rollback':
                $type = 'transaction';
                break;
            default:
                throw new InvalidArgumentException(
                    "SQL command/lib keyword '$key' unknown!"
                );
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
                        $backtrace = debug_backtrace();
                        if (DEBUG) {
                            $callsite = 'in file: ' . $backtrace[0]['file'] . ', ' .
                                    ' line: ' . $backtrace[0]['line'] . ', ';
                        } else {
                            $callsite = '';
                        }
                        throw new InvalidArgumentException(
                            "%A in \$DATABASE->q() has to correspond to an "
                            . "non-empty array, it is" . " now a '$val' (Query:"
                            . "'$key $query')! $callsite"
                        );
                    }
                    $GLOBALS['MODE'] = $part{1};
                    $query .= implode(', ', array_map(array($this, 'val2sql'), $val));
                    unset($GLOBALS['MODE']);
                    $query .= substr($part, 2);
                    break;
                case 'S':
                    $parts = array();
                    foreach ($val as $field => $value) {
                        $parts[] = '`'.$field.'` = '.$this->val2sql($value);
                    }
                    $separator = ', ';
                    $skip = 1;
                    if (strlen($part) > 1 && $part{1} == 'S') {
                        $separator = ' AND ';
                        $skip = 2;
                    }
                    $query .= implode($separator, $parts);
                    unset($parts);
                    $query .= substr($part, $skip);
                    break;
                case 's':
                case 'c':
                case 'i':
                case 'f':
                case 'l':
                case '.':
                    $query .= $this->val2sql($val, $part{0});
                    $query .= substr($part, 1);
                    break;
                case '_': // eat one argument
                    $query .= substr($part, 1);
                    break;
                default:
                    throw new InvalidArgumentException(
                        "Unknown %-code: " . $part{0}
                    );
            }
        }

        if ($literal) {
            user_error("Internal error in q()", E_USER_ERROR);
        }
        if ($argv) {
            if (DEBUG) {
                $backtrace = debug_backtrace();
                $callsite = ' in file: ' . $backtrace[0]['file'] . ', ' .
                        ' line: ' . $backtrace[0]['line'] . ', ';
            } else {
                $callsite = '';
            }
            throw new BadMethodCallException("Not all arguments to q() are"
                . " processed.\n$callsite");
        }

        $res = $this->execute($query);

        // nothing left to do if transaction statement...
        if ($type == 'transaction') {
            return null;
        }

        if ($type == 'update') {
            if ($key == 'returnid') {
                return mysqli_insert_id($this->_connection);
            }
            if ($key == 'returnaffected') {
                return mysqli_affected_rows($this->_connection);
            }
            return;
        }

        $res = new db_result($res);

        if ($key == 'tuple' || $key == 'value') {
            if ($res->count() < 1) {
                if ($maybe) {
                    return null;
                }
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

    private function execute(string $query)
    {
        $query = trim($query);

        list($micros, $secs) = explode(' ', microtime());
        $res = @mysqli_query($this->_connection, $query);
        list($micros2, $secs2) = explode(' ', microtime());
        $elapsed_ms = round(1000*(($secs2 - $secs) + ($micros2 - $micros)));

        if (DEBUG & DEBUG_SQL) {
            global $DEBUG_NUM_QUERIES;
            $DEBUG_NUM_QUERIES++;
            if (isset($_SERVER['REMOTE_ADDR'])) {
                printf(
                    "<p>SQL: $this->database: <kbd>%s</kbd> ({$elapsed_ms}ms)</p>\n",
                       specialchars($query)
                );
            } else {
                printf("SQL: $this->database: %s ({$elapsed_ms}ms)\n", $query);
            }
        }

        if ($res) {
            return $res;
        }

        if (DEBUG) {
            $backtrace = debug_backtrace();
            $callsite = ' file: ' . $backtrace[2]['file'] . ', ' .
                        ' line: ' . $backtrace[2]['line'] . ', ';
        } else {
            $callsite = '';
        }

        // switch error message depending on errornr.
        switch (mysqli_errno($this->_connection)) {
            case 1062: // duplicate key
                throw new UnexpectedValueException("Item with this key already"
                    . " exists.\n" . $callsite . mysqli_error($this->_connection));
            case 1217:  // foreign key constraint
                throw new UnexpectedValueException("This operation would have"
                    . " brought the database in an inconsistent state,\n"
                    . $callsite . mysqli_error($this->_connection));
            case 2006: // MySQL server has gone away
                throw new RuntimeException("MySQL server has gone away");
            case 1153:
                $query_len = round(strlen($query)/(1024*1024));
                throw new RuntimeException('MySQL error 1153: ' .
                    'Got a packet bigger than the configured "max_allowed_packet" ' .
                    '(current query was ~' . $query_len . 'MB).');
            default:
                throw new RuntimeException("SQL error, " . $callsite
                    . "Error#" . mysqli_errno($this->_connection) . ": "
                    . mysqli_error($this->_connection) . ", query: '$query'");
        }
    }

    // connects to a db-server if not yet connected
    public function connect()
    {
        if ($this->_connection) {
            return;
        }

        $pers = ($this->persist ? "p:" : "");
        if (!function_exists('mysqli_real_connect')) {
            throw new RuntimeException("PHP database module missing "
                . "(no such function: 'mysqli_real_connect')");
        }

        $this->_connection = mysqli_init();
        @mysqli_real_connect($this->_connection, $pers.$this->host, $this->user, $this->password, $this->database, 0, '', $this->flags ?? 0);

        if (mysqli_connect_error() || !$this->_connection) {
            throw new RuntimeException("Could not connect to database server "
                . "(host=$this->host,user=$this->user,password="
                . str_repeat('*', strlen($this->password)) . ",db=$this->database). "
                . "Error " . mysqli_connect_errno() . ": "
                . mysqli_connect_error());
        }
        mysqli_set_charset($this->_connection, DJ_CHARACTER_SET_MYSQL);
    }

    // reconnect to a db-server
    public function reconnect()
    {
        if (!$this->persist && $this->_connection) {
            mysqli_close($this->_connection);
        }

        $this->_connection = null;
        $this->connect();
    }

    // transform a php variable into one that can be put directly into a query
    private function val2sql($val, string $mode='.')
    {
        if (isset($GLOBALS['MODE'])) {
            $mode = $GLOBALS['MODE'];
        }
        if (!isset($val)) {
            return 'null';
        }
        switch ($mode) {
            case 'f': return (float)$val;
            case 'i': return (int)$val;
            case 's': return '"'.mysqli_real_escape_string($this->_connection, (string)$val).'"';
            case 'c': return '"%'.mysqli_real_escape_string($this->_connection, $val).'%"';
            case 'l': return $val;
            case '.': break;
            default:
                throw new InvalidArgumentException("Unknown mode: $mode");
        }

        switch (gettype($val)) {
            case 'boolean':
                return (int) $val;
            case 'integer':
            case 'double':
                return $val;
            case 'string':
                return '"'.mysqli_real_escape_string($this->_connection, $val).'"';
            default:
                throw new InvalidArgumentException(
                    'Cannot store type ' . gettype($val) .' in database'
                );
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
    private $_fields;

    public function __construct($res)
    {
        $this->_result = $res;
        $this->_count  = mysqli_num_rows($res);
        $this->_fields = mysqli_num_fields($res);

        $this->_nextused = false;
    }

    public function free()
    {
        mysqli_free_result($this->_result);
        $this->_result = null;
    }

    // return an assoc array that is a result row
    public function next()
    {
        // we've nexted over this result too many times already.
        if (!isset($this->_result)) {
            throw new BadMethodCallException(
                'Result does not contain a valid resource.'
            );
        }
        $this->_tuple = mysqli_fetch_assoc($this->_result);
        $this->_nextused = true;
        if (is_null($this->_tuple)) {
            $this->free();
            return false;
        }
        return $this->_tuple;
    }

    public function getcolumn($field=null)
    {
        if ($this->_nextused) {
            throw new BadMethodCallException('getcolumn does not work if'
                . ' you\'ve already next()ed over the result!');
        }
        $col = array();
        while ($this->next()) {
            $col[]=$field?$this->_tuple[$field]:current($this->_tuple);
        }
        return $col;
    }

    // returns a 2-dim array containing the result
    public function gettable()
    {
        if ($this->_nextused) {
            throw new BadMethodCallException('gettable does not work if'
                . ' you\'ve already next()ed over the result!');
        }
        $table = array();
        while ($this->next()) {
            $table[] = $this->_tuple;
        }
        return $table;
    }

    // returns a 2-dim array containing the result, with a column as key
    // (separate function for performance reasons)
    public function getkeytable($key)
    {
        if ($this->_nextused) {
            throw new BadMethodCallException('getkeytable does not work if'
                . ' you\'ve already next()ed over the result!');
        }
        $table = array();
        while ($this->next()) {
            $table[$this->_tuple[$key]] = $this->_tuple;
        }
        return $table;
    }

    // returns an associative array containing the result, with the first
    // column as the key and the second column as the value
    public function getkeyvaluetable()
    {
        if ($this->_nextused) {
            throw new BadMethodCallException('getkeyvaluetable does not work if'
                . ' you\'ve already next()ed over the result!');
        }

        if ($this->_fields!=2) {
            throw new BadMethodCallException('getkeyvaluetable only works on a'
                . 'table with exactly 2 columns!');
        }

        $table = array();
        while ($this->next()) {
            $key = array_shift($this->_tuple);
            $value = array_shift($this->_tuple);
            $table[$key] = $value;
        }
        return $table;
    }

    public function count()
    {
        return $this->_count;
    }
}
