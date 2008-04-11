<?php
define( 'BB_MYSQLI', false );

class bbdb extends bbdb_base {
	// ==================================================================
	//	DB Constructor - connects to the server and selects a database

	function bbdb($dbuser, $dbpassword, $dbname, $dbhost) {
		return $this->__construct($dbuser, $dbpassword, $dbname, $dbhost);
	}

	function __construct($dbuser, $dbpassword, $dbname, $dbhost) {
		return parent::__construct($dbuser, $dbpassword, $dbname, $dbhost);
	}

	function __destruct() {
		return true;
	}

	function db_connect( $query = 'SELECT' ) {
		global $current_connection;
		
		if ( empty( $query ) || $query == 'SELECT' )
			return false;
		
		$table = $this->get_table_from_query( $query );
		
		$server = new StdClass();
		
		global $bb;
		
		if ( isset($bb->user_bbdb_name) && $bb->user_bbdb_name && ( $table == $this->users || $table == $this->usermeta ) ) { // global user tables
			$dbhname =          'dbh_user'; // This is connection identifier
			$server->database = $bb->user_bbdb_name;
			$server->user =     $bb->user_bbdb_user;
			$server->pass =     $bb->user_bbdb_password;
			$server->host =     $bb->user_bbdb_host;
			$server->charset =  $this->user_charset;
		} else { // just us
			$dbhname =          'dbh_local'; // This is connection identifier
			$server->database = defined('BBDB_NAME')     ? constant('BBDB_NAME')     : false;
			$server->user =     defined('BBDB_USER')     ? constant('BBDB_USER')     : false;
			$server->pass =     defined('BBDB_PASSWORD') ? constant('BBDB_PASSWORD') : false;
			$server->host =     defined('BBDB_HOST')     ? constant('BBDB_HOST')     : false;
			$server->charset =  $this->charset;
		}
		
		$current_connection = "$dbhname";
		
		if ( isset( $this->$dbhname ) ) // We're already connected!
			return $this->$dbhname;
		
		$this->timer_start();
		
		$this->$dbhname = @mysql_connect( $server->host, $server->user, $server->pass, true );
		
		if (!$this->$dbhname)
			return false;
		
		if ( !empty($server->charset) && $this->has_cap( 'collation', $this->$dbhname ) )
			$this->query("SET NAMES '$server->charset'");
		
		if ( !$this->select( $server->database, $this->$dbhname ) ) {
			unset($this->$dbhname);
			return false;
		}
		
		$current_connection .= ' connect: ' . number_format( ( $this->timer_stop() * 1000 ), 2) . 'ms';
		
		return $this->$dbhname;	
	}

	// ==================================================================
	//	Select a DB (if another one needs to be selected)

	function select($db, &$dbh) {
		if (!@mysql_select_db($db, $dbh)) {
//			$this->handle_error_connecting( $dbh, array( "db" => $db ) );
			//die('Cannot select DB.');
			return false;
		}
		return true;
	}

	// ==================================================================
	//	Print SQL/DB error.

	function print_error($str = '') {
		global $EZSQL_ERROR;
		if (!$str) $str = mysql_error();
		$EZSQL_ERROR[] = 
		array('query' => $this->last_query, 'error_str' => $str);
		
		// What to do with the error?
		switch ( $this->show_errors ) {
			case 0:
				// Surpress
				return false;
				break;
			
			case 1:
				// Print
				print "<div id='error'>
				<p class='bbdberror'><strong>bbPress database error:</strong> [$str]<br />
				<code>$this->last_query</code></p>
				</div>";
				return false;
				break;
			
			case 2:
				// Return
				return array('query' => $this->last_query, 'error_str' => $str);
				break;
		}
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
				return $this->print_error( mysql_error( $dbh ));
			}
		}

		if ( preg_match("/^\\s*(insert|delete|update|replace|set) /i",$query) ) {
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

	// table name or mysql resource 
	function db_version( $dbh = false ) {
		if ( !$dbh )
			$dbh = $this->forums;

		if ( !is_resource( $dbh ) )
			$dbh = $this->db_connect( "DESCRIBE $dbh" );

		if ( $dbh )
			return mysql_get_server_info( $dbh );
		return false;
	}
}

if ( !isset($bbdb) )
	$bbdb = new bbdb(BBDB_USER, BBDB_PASSWORD, BBDB_NAME, BBDB_HOST);
?>
