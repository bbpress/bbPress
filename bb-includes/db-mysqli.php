<?php
define( 'BB_MYSQLI', true );

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
		
		if ( defined('USER_BBDB_NAME') && ( $table == $this->users || $table == $this->usermeta ) ) { // global user tables
			$dbhname =          'dbh_user'; // This is connection identifier
			$server->database = constant('USER_BBDB_NAME');
			$server->user =     constant('USER_BBDB_USER');
			$server->pass =     constant('USER_BBDB_PASSWORD');
			$server->host =     constant('USER_BBDB_HOST');
			$server->charset =  $this->user_charset;
		} else { // just us
			$dbhname =          'dbh_local'; // This is connection identifier
			$server->database = defined('BBDB_NAME')     ? constant('BBDB_NAME')     : false;
			$server->user =     defined('BBDB_USER')     ? constant('BBDB_USER')     : false;
			$server->pass =     defined('BBDB_PASSWORD') ? constant('BBDB_PASSWORD') : false;
			$server->host =     defined('BBDB_HOST')     ? constant('BBDB_HOST')     : false;
			$server->charset =  $this->charset;
		}
		
		// Set the port if it is specified in the host
		if (strpos($server->host, ':') === false) {
			$server->port = null;
		} else {
			list($server->host, $server->port) = explode(':', $server->host);
		}
		
		$current_connection = "$dbhname";
		
		if ( isset( $this->$dbhname ) ) // We're already connected!
			return $this->$dbhname;
		
		$this->timer_start();
		
		$this->$dbhname = @mysqli_connect( $server->host, $server->user, $server->pass, null, $server->port );
		
		if (!$this->$dbhname)
			return false;
		
		if ( isset($server->charset) && !empty($server->charset) && $this->has_cap( 'collation', $this->$dbhname ) )
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
		if (!@mysqli_select_db($dbh, $db)) {
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
		if (!$str) $str = mysqli_error( $this->db_connect( $this->last_query ) ); // Will this work?
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

		// Perform the query via std mysqli_query function..
		if (SAVEQUERIES)
			$this->timer_start();

		unset( $dbh );
		$dbh = $this->db_connect( $query );

		$this->result = @mysqli_query($dbh, $query);
		++$this->num_queries;

		if (SAVEQUERIES)
			$this->queries[] = array( $query . ' server:' . $current_connection, $this->timer_stop() );

		// If there is an error then take note of it..
		if( $dbh ) {
			if ( mysqli_error( $dbh ) ) {
				return $this->print_error( mysqli_error( $dbh ) );
			}
		}

		if ( preg_match("/^\\s*(insert|delete|update|replace) /i",$query) ) {
			$this->rows_affected = mysqli_affected_rows( $dbh );
			// Take note of the insert_id
			if ( preg_match("/^\\s*(insert|replace) /i",$query) ) {
				$this->insert_id = mysqli_insert_id($dbh);	
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$i = 0;
			while ($i < @mysqli_num_fields($this->result)) {
				$this->col_info[$i] = @mysqli_fetch_field($this->result);
				$i++;
			}
			$num_rows = 0;
			while ( $row = @mysqli_fetch_object($this->result) ) {
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			@mysqli_free_result($this->result);

			// Log number of rows the query returned
			$this->num_rows = $num_rows;
			
			// Return number of rows selected
			$return_val = $this->num_rows;
		}

		return $return_val;
	}

	// table name or mysqli object
	function db_version( $dbh = false ) {
		if ( !$dbh )
			$dbh = $this->forums;

		if ( !is_object( $dbh ) )
			$dbh = $this->db_connect( "DESCRIBE $dbh" );

		if ( $dbh )
			return mysqli_get_server_info( $dbh );
		return false;
	}
}

if ( !isset($bbdb) )
	$bbdb = new bbdb(BBDB_USER, BBDB_PASSWORD, BBDB_NAME, BBDB_HOST);
?>
