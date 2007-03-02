<?php

define('OBJECT', 'OBJECT', true);
define('ARRAY_A', 'ARRAY_A', false);
define('ARRAY_N', 'ARRAY_N', false);

if (!defined('SAVEQUERIES'))
	define('SAVEQUERIES', false);

class bbdb {

	var $show_errors = true;
	var $num_queries = 0;
	var $retries = 0;
	var $last_query;
	var $col_info;
	var $queries;

	// Our tables
	var $forums;
	var $posts;
	var $topics;
	var $users;

	// ==================================================================
	//	DB Constructor - connects to the server and selects a database

	function bbdb($dbuser, $dbpassword, $dbname, $dbhost) {

		$this->db_connect();
		return true;

	}

	function db_connect( $query = 'SELECT' ) {
		global $current_connection;

		if ( empty( $query ) || $query == 'SELECT' )
			return false;

		$table = $this->get_table_from_query( $query );

		if ( defined('USER_BBDB_NAME') && ( $table == $this->users || $table == $this->usermeta ) ) { // global user tables
			$dbhname = 'dbh_user'; // This is connection identifier
			$server->database = constant('USER_BBDB_NAME');
			$server->user = constant('USER_BBDB_USER');
			$server->pass = constant('USER_BBDB_PASSWORD');
			$server->host = constant('USER_BBDB_HOST');
		} else { // just us
			$dbhname = 'dbh_local'; // This is connection identifier
			$server->database = constant('BBDB_NAME');
			$server->user = constant('BBDB_USER');
			$server->pass = constant('BBDB_PASSWORD');
			$server->host = constant('BBDB_HOST');
		}
		
		$current_connection = "$dbhname";

		if ( isset( $this->$dbhname ) ) // We're already connected!
			return $this->$dbhname;

		$this->timer_start();
		
		$this->$dbhname = @mysql_connect( $server->host, $server->user, $server->pass, true );	
		$this->select( $server->database, $this->$dbhname );

		$current_connection .= ' connect: ' . number_format( ( $this->timer_stop() * 1000 ), 2) . 'ms';

		return $this->$dbhname;	
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
		if ( preg_match('/^\s*INSERT IGNORE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*REPLACE INTO\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*DELETE\s+FROM\s+`?(\w+)`?\s*/is', $q, $maybe) )
			return $maybe[1];
		if ( preg_match('/^\s*OPTIMIZE\s+TABLE\s+`?(\w+)`?\s*/is', $q, $maybe) )
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

		return false;
	}

	// ==================================================================
	//	Select a DB (if another one needs to be selected)

	function select($db, &$dbh) {
		if (!@mysql_select_db($db, $dbh)) {
//			$this->handle_error_connecting( $dbh, array( "db" => $db ) );
			die('Cannot select DB.');
		}
	}

	// ====================================================================
	//	Format a string correctly for safe insert under all PHP conditions
	
	function escape($str) {
		return addslashes($str);				
	}

	function escape_deep( $array ) {
		return is_array($array) ? array_map(array(&$this, 'escape_deep'), $array) : $this->escape( $array );
	}

	// ==================================================================
	//	Print SQL/DB error.

	function print_error($str = '') {
		global $EZSQL_ERROR;
		if (!$str) $str = mysql_error();
		$EZSQL_ERROR[] = 
		array ('query' => $this->last_query, 'error_str' => $str);

		// Is error output turned on or not..
		if ( $this->show_errors ) {
			// If there is an error then take note of it
			print "<div id='error'>
			<p class='bbdberror'><strong>bbPress database error:</strong> [$str]<br />
			<code>$this->last_query</code></p>
			</div>";
		} else {
			return false;	
		}
	}

	// ==================================================================
	//	Turn error handling on or off..

	function show_errors() {
		$this->show_errors = true;
	}
	
	function hide_errors() {
		$this->show_errors = false;
	}

	// ==================================================================
	//	Kill cached query results

	function flush() {
		$this->last_result = null;
		$this->col_info = null;
		$this->last_query = null;
	}

	// ==================================================================
	//	Basic Query	- see docs for more detail

	function query($query) {
		global $current_connection;
		// initialise return
		$return_val = 0;
		$this->flush();

		// Log how the function was called
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug..
		$this->last_query = $query;

		// Perform the query via std mysql_query function..
		if (SAVEQUERIES)
			$this->timer_start();

		unset( $dbh );
		$dbh = $this->db_connect( $query );

		$this->result = @mysql_query($query, $dbh);
		++$this->num_queries;

		if (SAVEQUERIES)
			$this->queries[] = array( $query . ' server:' . $current_connection, $this->timer_stop() );

		// If there is an error then take note of it..
		if( $dbh ) {
			if ( mysql_error( $dbh ) ) {
				$this->print_error( mysql_error( $dbh ));
				return false;
			}
		}

		if ( preg_match("/^\\s*(insert|delete|update|replace) /i",$query) ) {
			$this->rows_affected = mysql_affected_rows();
			// Take note of the insert_id
			if ( preg_match("/^\\s*(insert|replace) /i",$query) ) {
				$this->insert_id = mysql_insert_id($dbh);	
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$i = 0;
			while ($i < @mysql_num_fields($this->result)) {
				$this->col_info[$i] = @mysql_fetch_field($this->result);
				$i++;
			}
			$num_rows = 0;
			while ( $row = @mysql_fetch_object($this->result) ) {
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			@mysql_free_result($this->result);

			// Log number of rows the query returned
			$this->num_rows = $num_rows;
			
			// Return number of rows selected
			$return_val = $this->num_rows;
		}

		return $return_val;
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
			$this->print_error(" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N");
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
		<title>bbPress &rsaquo; Error</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
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
}

if ( !isset($bbdb) )
	$bbdb = new bbdb(BBDB_USER, BBDB_PASSWORD, BBDB_NAME, BBDB_HOST);
?>
