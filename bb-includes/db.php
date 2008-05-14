<?php
define('OBJECT', 'OBJECT', true);
define('ARRAY_A', 'ARRAY_A', false);
define('ARRAY_N', 'ARRAY_N', false);

if ( !defined('SAVEQUERIES') )
	define('SAVEQUERIES', false);

class bbdb_base {
	var $show_errors = 1;
	var $num_queries = 0;
	var $retries = 0;
	var $last_query;
	var $col_info;
	var $queries;

	var $prefix;

	// Our tables
	var $tables = array(
		'forums',
		'posts',
		'tagged',
		'tags',
		'terms',
		'term_relationships',
		'term_taxonomy',
		'topics',
		'topicmeta',
		'users',
		'usermeta'
	);
	var $forums;
	var $posts;
	var $tagged;
	var $tags;
	var $terms;
	var $term_relationships;
	var $term_taxonomy;
	var $topics;
	var $topicmeta;
	var $users;
	var $usermeta;

	var $_force_dbhname = false;

	var $charset;
	var $collate;

	var $user_charset;

	// ==================================================================
	//	DB Constructor - connects to the server and selects a database

	function bbdb($dbuser, $dbpassword, $dbname, $dbhost) {
		return $this->__construct($dbuser, $dbpassword, $dbname, $dbhost);
	}

	function __construct($dbuser, $dbpassword, $dbname, $dbhost) {
		if ( defined('BBDB_CHARSET') )
			$this->charset = BBDB_CHARSET;
		if ( defined('BBDB_COLLATE') )
			$this->collate = BBDB_COLLATE;
		
		$this->db_connect();
		return true;

	}

	function __destruct() {
		return true;
	}

	function set_prefix($prefix, $tables = FALSE) {
		
		// Check that the prefix is valid
		if ( preg_match('|[^a-z0-9_]|i', $prefix) )
			return new WP_Error('invalid_db_prefix', 'Invalid database prefix (' . $prefix . ')'); // No gettext here
		
		if ( !$tables ) {
			// Set the old prefix to return
			$old_prefix = $this->prefix;
			// Set the general prefix
			$this->prefix = $prefix;
			// Process all table names
			$_tables = $this->tables;
		} elseif ( $prefix ) {
			$old_prefix = '';
			// Just process the specified table names
			$_tables = $tables;
		} else {
			return FALSE;
		}
		
		// Add the prefix to the stored table names
		foreach ( $_tables as $table )
			$this->$table = $prefix . $table;
		
		// Return the old prefix
		return $old_prefix;
	}

	function db_connect( $query = 'SELECT' ) {
		return false;
	}

	function get_table_from_query ( $q ) {
		If( substr( $q, -1 ) == ';' )
			$q = substr( $q, 0, -1 );
		if ( preg_match('/^\s*SELECT.*?\s+FROM\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*UPDATE IGNORE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*UPDATE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*INSERT INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*REPLACE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*INSERT IGNORE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*REPLACE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DELETE\s+FROM\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*(?:TRUNCATE|RENAME|OPTIMIZE|LOCK|UNLOCK)\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^SHOW TABLE STATUS (LIKE|FROM) \'?`?(\w+)\'?`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^SHOW INDEX FROM `?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*SHOW CREATE TABLE `?(\w+?)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^SHOW CREATE TABLE (wp_[a-z0-9_]+)/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*CREATE\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DROP\s+TABLE\s+IF\s+EXISTS\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DROP\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DESCRIBE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*ALTER\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*SELECT.*?\s+FOUND_ROWS\(\)/is', $q) )
			return $this->last_table;

		return '';
	}

	function is_write_query( $q ) {
		If( substr( $q, -1 ) == ';' )
			$q = substr( $q, 0, -1 );
		if ( preg_match('/^\s*SELECT.*?\s+FROM\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return false;
		if ( preg_match('/^\s*SHOW DATABASES\s*/is', $q, $maybe) )
			return false;
		if ( preg_match('/^\s*UPDATE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*INSERT INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*REPLACE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*INSERT IGNORE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*DELETE\s+FROM\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*OPTIMIZE\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^SHOW TABLE STATUS (LIKE|FROM) \'?`?(\w+)\'?`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*CREATE\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*SHOW CREATE TABLE `?(\w+?)`?.*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*DROP\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*DROP\s+TABLE\s+IF\s+EXISTS\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return true;
		if ( preg_match('/^\s*ALTER\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DESCRIBE\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*SHOW\s+INDEX\s+FROM\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*SELECT.*?\s+FOUND_ROWS\(\)/is', $q) )
			return false;
		if ( preg_match('/^\s*RENAME\s+TABLE\s+/i', $q) )
			return true;
		if ( preg_match('/^\s*TRUNCATE\s|TABLE\s+/i', $q) )
			return true;
		error_log( date( "Y-m-d H:i:s" ) . " is_write: " . $q ."\n\n", 3, "/tmp/db-missed.txt" );
		return true;
	}

	// ==================================================================
	//	Select a DB (if another one needs to be selected)

	function select($db, &$dbh) {
		return false;
	}

	// ====================================================================
	//	Format a string correctly for safe insert under all PHP conditions
	
	function escape($str) {
		return addslashes($str);
	}

	function escape_deep( $array ) {
		return is_array($array) ? array_map(array(&$this, 'escape_deep'), $array) : $this->escape( $array );
	}

	/**
	 * Escapes content by reference for insertion into the database, for security
	 * @param string $s
	 */
	function escape_by_ref(&$s) {
		$s = $this->escape($s);
	}

	/**
	 * Prepares a SQL query for safe use, using sprintf() syntax
	 */
	function prepare($args=NULL) {
		if ( NULL === $args )
			return;
		$args = func_get_args();
		$query = array_shift($args);
		$query = str_replace("'%s'", '%s', $query); // in case someone mistakenly already singlequoted it
		$query = str_replace('"%s"', '%s', $query); // doublequote unquoting
		$query = str_replace('%s', "'%s'", $query); // quote the strings
		array_walk($args, array(&$this, 'escape_by_ref'));
		return @vsprintf($query, $args);
	}

	// ==================================================================
	//	Print SQL/DB error.

	function print_error($str = '') {
		return false;
	}

	// ==================================================================
	//	Toggle error handling..
	
	// Cause errors to be returned in print_error
	function return_errors() {
		$this->show_errors = 2;
	}
	
	// Cause errors to be echoed in print_error
	function show_errors() {
		$this->show_errors = 1;
	}
	
	// Cause errors to be surpressed in print_error
	function hide_errors() {
		$this->show_errors = 0;
	}

	// ==================================================================
	//	Kill cached query results

	function flush() {
		$this->last_result = null;
		$this->col_info = null;
		$this->last_query = null;
	}

	/**
	 * Insert an array of data into a table
	 * @param string $table WARNING: not sanitized!
	 * @param array $data should not already be SQL-escaped
	 * @return mixed results of $this->query()
	 */
	function insert($table, $data) {
		$data = $this->escape_deep($data);
		$fields = array_keys($data);
		return $this->query("INSERT INTO $table (`" . implode('`,`',$fields) . "`) VALUES ('".implode("','",$data)."')");
	}

	/**
	 * Update a row in the table with an array of data
	 * @param string $table WARNING: not sanitized!
	 * @param array $data should not already be SQL-escaped
	 * @param array $where a named array of WHERE column => value relationships.  Multiple member pairs will be joined with ANDs.  WARNING: the column names are not currently sanitized!
	 * @return mixed results of $this->query()
	 */
	function update($table, $data, $where){
		$data = $this->escape_deep($data);
		$bits = $wheres = array();
		foreach ( array_keys($data) as $k )
			$bits[] = "`$k` = '$data[$k]'";

		if ( is_array( $where ) )
			foreach ( $where as $c => $v )
				$wheres[] = "$c = '" . $this->escape( $v ) . "'";
		else
			return false;
		return $this->query( "UPDATE $table SET " . implode( ', ', $bits ) . ' WHERE ' . implode( ' AND ', $wheres ) . ' LIMIT 1' );
	}

	// ==================================================================
	//	Basic Query	- see docs for more detail

	function query($query) {
		return false;
	}

	// ==================================================================
	//	Get one variable from the DB - see docs for more detail

	function get_var($query=null, $x = 0, $y = 0) {
		$this->func_call = "\$db->get_var(\"$query\",$x,$y)";
		if ( $query )
			$this->query($query);

		// Extract var out of cached results based x,y vals
		if ( $this->last_result[$y] ) {
			$values = array_values(get_object_vars($this->last_result[$y]));
		}

		// If there is a value return it else return null
		return (isset($values[$x]) && $values[$x]!=='') ? $values[$x] : null;
	}

	// ==================================================================
	//	Get one row from the DB - see docs for more detail

	function get_row($query = null, $output = OBJECT, $y = 0) {
		$this->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if ( $query )
			$this->query($query);

		if ( $output == OBJECT ) {
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		} elseif ( $output == ARRAY_A ) {
			return $this->last_result[$y] ? get_object_vars($this->last_result[$y]) : null;
		} elseif ( $output == ARRAY_N ) {
			return $this->last_result[$y] ? array_values(get_object_vars($this->last_result[$y])) : null;
		} else {
			return $this->print_error(" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N");
		}
	}

	// ==================================================================
	//	Function to get 1 column from the cached result set based in X index
	// se docs for usage and info

	function get_col($query = null , $x = 0) {
		if ( $query )
			$this->query($query);

		// Extract the column values
		for ( $i=0; $i < count($this->last_result); $i++ ) {
			$new_array[$i] = $this->get_var(null, $x, $i);
		}
		return $new_array;
	}

	// ==================================================================
	// Return the the query as a result set - see docs for more details

	function get_results($query = null, $output = OBJECT) {
		$this->func_call = "\$db->get_results(\"$query\", $output)";

		if ( $query )
			$this->query($query);

		// Send back array of objects. Each row is an object
		if ( $output == OBJECT ) {
			return $this->last_result;
		} elseif ( $output == ARRAY_A || $output == ARRAY_N ) {
			if ( $this->last_result ) {
				$i = 0;
				foreach( $this->last_result as $row ) {
					$new_array[$i] = (array) $row;
					if ( $output == ARRAY_N ) {
						$new_array[$i] = array_values($new_array[$i]);
					}
					$i++;
				}
				return $new_array;
			} else {
				return null;
			}
		}
	}


	// ==================================================================
	// Function to get column meta data info pertaining to the last query
	// see docs for more info and usage

	function get_col_info($info_type = 'name', $col_offset = -1) {
		if ( $this->col_info ) {
			if ( $col_offset == -1 ) {
				$i = 0;
				foreach($this->col_info as $col ) {
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			} else {
				return $this->col_info[$col_offset]->{$info_type};
			}
		}
	}

	function timer_start() {
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$this->time_start = $mtime[1] + $mtime[0];
		return true;
	}
	
	function timer_stop($precision = 3) {
		$mtime = microtime();
		$mtime = explode(' ', $mtime);
		$time_end = $mtime[1] + $mtime[0];
		$time_total = $time_end - $this->time_start;
		return $time_total;
	}

	function bail($message) { // Just wraps errors in a nice header and footer
	if ( !$this->show_errors )
		return false;
	echo <<<HEAD
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>bbPress &rsaquo; Error</title>
		<style media="screen" type="text/css">
		<!--
		html {
			background: #eee;
		}
		body {
			background: #fff;
			color: #000;
			font-family: Georgia, "Times New Roman", Times, serif;
			margin-left: 25%;
			margin-right: 25%;
			padding: .2em 2em;
		}
		
		h1 {
			color: #006;
			font-size: 18px;
			font-weight: lighter;
		}
		
		h2 {
			font-size: 16px;
		}
		
		p, li, dt {
			line-height: 140%;
			padding-bottom: 2px;
		}
	
		ul, ol {
			padding: 5px 5px 5px 20px;
		}
		#logo {
			margin-bottom: 2em;
		}
		-->
		</style>
	</head>
	<body>
	<h1 id="logo"><img alt="bbPress" src="http://bbpress.org/bbpress.png" /></h1>
HEAD;
	echo $message;
	echo "</body></html>";
	die();
	}

	/**
	 * Checks wether of not the database version is high enough to support the features WordPress uses
	 * @global $wp_version
	 */
	function check_database_version() {
		$bb_version = function_exists( 'bb_get_option' ) ? bb_get_option( 'bb_version' ) : '';
		// Make sure the server has MySQL 4.0
		$mysql_version = $this->db_version();
		if ( version_compare($mysql_version, '4.0.0', '<') )
			return new WP_Error('database_version',sprintf(__('<strong>ERROR</strong>: bbPress %s requires MySQL 4.0.0 or higher'), $bb_version));
	}

	function has_cap( $db_cap, $dbh_or_table = false ) {
		$version = $this->db_version( $dbh_or_table );

		$db_cap = strtolower( $db_cap );

		switch ( $db_cap ) :
		case 'group_concat' :
		case 'collation' :
			return version_compare($version, '4.1', '>=');
			break;
		endswitch;

		return false;
	}

	// table name or mysql resource 
	function db_version( $dbh = false ) {
		return false;
	}
}


// Default database extension is mysql
if (!defined('BBDB_EXTENSION')) {
	define('BBDB_EXTENSION', 'mysql');
}

// Choose a database extension
switch (BBDB_EXTENSION) {
	case 'mysqli':
		if (extension_loaded('mysqli')) {
			require(BB_PATH . BB_INC . 'db-mysqli.php');
		}
		break;
	case 'mysql':
		if (extension_loaded('mysql')) {
			require(BB_PATH . BB_INC . 'db-mysql.php');
		}
		break;
}

// Die if object was not created
if (!is_object($bbdb)) {
	die('Your PHP installation appears to be missing the specified MySQL extension (' . BBDB_EXTENSION . ') which is required for bbPress.');
}

?>
