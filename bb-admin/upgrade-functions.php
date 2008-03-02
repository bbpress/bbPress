<?php

function bb_install() {
	require_once( BBPATH . 'bb-admin/upgrade-schema.php');
	$alterations = bb_dbDelta($bb_queries);
	bb_update_db_version();
	return $alterations;
}

function bb_upgrade_all() {
	if ( !ini_get('safe_mode') )
		set_time_limit(600);
	$bb_upgrade = array();
	$bb_upgrade[] = bb_upgrade_160(); // Break blocked users
	$bb_upgrade[] = bb_upgrade_170(); // Escaping in usermeta
	$bb_upgrade[] = bb_upgrade_180(); // Delete users for real
	$bb_upgrade[] = bb_upgrade_190(); // Move topic_resolved to topicmeta
	$bb_upgrade[] = bb_upgrade_200(); // Indices
	$bb_upgrade[] = bb_upgrade_210(); // Convert text slugs to varchar slugs
	$bb_upgrade[] = bb_upgrade_220(); // remove bb_tagged primary key, add new column and primary key
	require_once( BBPATH . 'bb-admin/upgrade-schema.php');
	$bb_upgrade = array_merge($bb_upgrade, bb_dbDelta($bb_queries));
	$bb_upgrade[] = bb_upgrade_1000(); // Make forum and topic slugs
	$bb_upgrade[] = bb_upgrade_1010(); // Make sure all forums have a valid parent
	$bb_upgrade[] = bb_upgrade_1020(); // Add a user_nicename to existing users
	$bb_upgrade[] = bb_upgrade_1030(); // Move admin_email option to from_email
	$bb_upgrade[] = bb_upgrade_1040(); // Activate Akismet and bozo plugins on upgrade only
	bb_update_db_version();
	return $bb_upgrade; 
}

function bb_dbDelta($queries, $execute = true) {
	global $bbdb;
	
	// Seperate individual queries into an array
	if( !is_array($queries) ) {
		$queries = explode( ';', $queries );
		if('' == $queries[count($queries) - 1]) array_pop($queries);
	}
	
	$cqueries = array(); // Creation Queries
	$iqueries = array(); // Insertion Queries
	$for_update = array();
	
	// Create a tablename index for an array ($cqueries) of queries
	foreach($queries as $qry) {
		if(preg_match("|CREATE TABLE ([^ ]*)|", $qry, $matches)) {
			$cqueries[strtolower($matches[1])] = $qry;
			$for_update[strtolower($matches[1])] = 'Create table '.$matches[1];
		}
		else if(preg_match("|CREATE DATABASE ([^ ]*)|", $qry, $matches)) {
			array_unshift($cqueries, $qry);
		}
		else if(preg_match("|INSERT INTO ([^ ]*)|", $qry, $matches)) {
			$iqueries[] = $qry;
		}
		else if(preg_match("|UPDATE ([^ ]*)|", $qry, $matches)) {
			$iqueries[] = $qry;
		}
		else {
			// Unrecognized query type
		}
	}	

	// Check to see which tables and fields exist
	if($tables = (array) $bbdb->get_col('SHOW TABLES;')) {
		// For every table in the database
		foreach($tables as $table) {
			// If a table query exists for the database table...
			if( array_key_exists(strtolower($table), $cqueries) ) {
				// Clear the field and index arrays
				unset($cfields);
				unset($indices);
				// Get all of the field names in the query from between the parens
				preg_match("|\((.*)\)|ms", $cqueries[strtolower($table)], $match2);
				$qryline = trim($match2[1]);

				// Separate field lines into an array
				$flds = explode("\n", $qryline);

				//echo "<hr/><pre>\n".print_r(strtolower($table), true).":\n".print_r($cqueries, true)."</pre><hr/>";
				
				// For every field line specified in the query
				foreach($flds as $fld) {
					// Extract the field name
					preg_match("|^([^ ]*)|", trim($fld), $fvals);
					$fieldname = $fvals[1];
					
					// Verify the found field name
					$validfield = true;
					switch(strtolower($fieldname))
					{
					case '':
					case 'primary':
					case 'index':
					case 'fulltext':
					case 'unique':
					case 'key':
						$validfield = false;
						$indices[] = trim(trim($fld), ", \n");
						break;
					}
					$fld = trim($fld);
					
					// If it's a valid field, add it to the field array
					if($validfield) {
						$cfields[strtolower($fieldname)] = trim($fld, ", \n");
					}
				}
				
				// Fetch the table column structure from the database
				$tablefields = $bbdb->get_results("DESCRIBE {$table};");
								
				// For every field in the table
				foreach($tablefields as $tablefield) {				
					// If the table field exists in the field array...
					if(array_key_exists(strtolower($tablefield->Field), $cfields)) {
						// Get the field type from the query
						preg_match("|".$tablefield->Field." ([^ ]*( unsigned)?)|i", $cfields[strtolower($tablefield->Field)], $matches);
						$fieldtype = $matches[1];

						// Is actual field type different from the field type in query?
						if($tablefield->Type != $fieldtype) {
							// Add a query to change the column type
							$cqueries[] = "ALTER TABLE {$table} CHANGE COLUMN {$tablefield->Field} " . $cfields[strtolower($tablefield->Field)];
							$for_update[$table.'.'.$tablefield->Field] = "Changed type of {$table}.{$tablefield->Field} from {$tablefield->Type} to {$fieldtype}";
						}
						
						// Get the default value from the array
							//echo "{$cfields[strtolower($tablefield->Field)]}<br>";
						if(preg_match("| DEFAULT '(.*)'|i", $cfields[strtolower($tablefield->Field)], $matches)) {
							$default_value = $matches[1];
							if($tablefield->Default != $default_value)
							{
								// Add a query to change the column's default value
								$cqueries[] = "ALTER TABLE {$table} ALTER COLUMN {$tablefield->Field} SET DEFAULT '{$default_value}'";
								$for_update[$table.'.'.$tablefield->Field] = "Changed default value of {$table}.{$tablefield->Field} from {$tablefield->Default} to {$default_value}";
							}
						}

						// Remove the field from the array (so it's not added)
						unset($cfields[strtolower($tablefield->Field)]);
					}
					else {
						// This field exists in the table, but not in the creation queries?
					}
				}

				// For every remaining field specified for the table
				foreach($cfields as $fieldname => $fielddef) {
					// Push a query line into $cqueries that adds the field to that table
					$cqueries[] = "ALTER TABLE {$table} ADD COLUMN $fielddef";
					$for_update[$table.'.'.$fieldname] = 'Added column '.$table.'.'.$fieldname;
				}
				
				// Index stuff goes here
				// Fetch the table index structure from the database
				$tableindices = $bbdb->get_results("SHOW INDEX FROM {$table};");
				
				if($tableindices) {
					// Clear the index array
					unset($index_ary);

					// For every index in the table
					foreach($tableindices as $tableindex) {
						// Add the index to the index data array
						$keyname = $tableindex->Key_name;
						$index_ary[$keyname]['columns'][] = array('fieldname' => $tableindex->Column_name, 'subpart' => $tableindex->Sub_part);
						$index_ary[$keyname]['unique'] = ($tableindex->Non_unique == 0)?true:false;
						$index_ary[$keyname]['type'] = ('BTREE' == $tableindex->Index_type)?false:$tableindex->Index_type;
						if(!$index_ary[$keyname]['type']) {
							$index_ary[$keyname]['type'] = (strpos($tableindex->Comment, 'FULLTEXT') === false)?false:'FULLTEXT';
						}
					}

					// For each actual index in the index array
					foreach($index_ary as $index_name => $index_data) {
						// Build a create string to compare to the query
						$index_string = '';
						if($index_name == 'PRIMARY') {
							$index_string .= 'PRIMARY ';
						}
						else if($index_data['unique']) {
							$index_string .= 'UNIQUE ';
						}
						if($index_data['type']) {
							$index_string .= $index_data['type'] . ' ';
						}
						$index_string .= 'KEY ';
						if($index_name != 'PRIMARY') {
							$index_string .= $index_name;
						}
						$index_columns = '';
						// For each column in the index
						foreach($index_data['columns'] as $column_data) {
							if($index_columns != '') $index_columns .= ',';
							// Add the field to the column list string
							$index_columns .= $column_data['fieldname'];
							if($column_data['subpart'] != '') {
								$index_columns .= '('.$column_data['subpart'].')';
							}
						}
						// Add the column list to the index create string 
						$index_string .= ' ('.$index_columns.')';

						if(!(($aindex = array_search($index_string, $indices)) === false)) {
							unset($indices[$aindex]);
							//echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br/>Found index:".$index_string."</pre>\n";
						}
						//else echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br/><b>Did not find index:</b>".$index_string."<br/>".print_r($indices, true)."</pre>\n";
					}
				}

				// For every remaining index specified for the table
				foreach($indices as $index) {
					// Push a query line into $cqueries that adds the index to that table
					$cqueries[] = "ALTER TABLE {$table} ADD $index";
					$for_update[$table.'.'.$fieldname] = 'Added index '.$table.' '.$index;
				}

				// Remove the original table creation query from processing
				unset($cqueries[strtolower($table)]);
				unset($for_update[strtolower($table)]);
			} else {
				// This table exists in the database, but not in the creation queries?
			}
		}
	}

	$allqueries = array_merge($cqueries, $iqueries);
	if($execute) {
		foreach($allqueries as $query_index => $query) {
			//echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">".print_r($query, true)."</pre>\n";
			$result = $bbdb->query($query);
			if ( is_array($result) ) {
				// There was an error and $bbdb->show_errors = 2
				$for_update[$query_index] = array(
					'original' => array(
						'message' => $for_update[$query_index],
						'query'   => $query
					),
					'error' => array(
						'message' => $result['error_str'],
						'query'   => $result['query']
					)
				);
			}
		}
	}

	return $for_update;
}

/**
 ** bb_maybe_add_column()
 ** Add column to db table if it doesn't exist.
 ** Returns:  true if already exists or on successful completion
 **           false on error
 */
function bb_maybe_add_column( $table_name, $column_name, $create_ddl ) {
	global $bbdb, $debug;
	foreach ($bbdb->get_col("DESC $table_name", 0) as $column ) {
		if ($debug) echo("checking $column == $column_name<br />");
		if ($column == $column_name) {
			return true;
		}
	}
	// didn't find it try to create it.
	$q = $bbdb->query($create_ddl);
	// we cannot directly tell that whether this succeeded!
	foreach ($bbdb->get_col("DESC $table_name", 0) as $column ) {
		if ($column == $column_name) {
			return true;
		}
	}
	return false;
}

function bb_make_db_current() {
	global $bb_queries;

	$alterations = bb_dbDelta($bb_queries);
	echo "<ol>\n";
	foreach($alterations as $alteration) {
		echo "<li>$alteration</li>\n";
		flush();
		}
	echo "</ol>\n";
}

function bb_upgrade_process_all_slugs() {
	global $bbdb;
	// Forums

	$forums = (array) $bbdb->get_results("SELECT forum_id, forum_name FROM $bbdb->forums ORDER BY forum_order ASC" );

	$slugs = array();
	foreach ( $forums as $forum ) :
		$slug = bb_slug_sanitize( $forum->forum_name );
		$slugs[$slug][] = $forum->forum_id;
	endforeach;

	foreach ( $slugs as $slug => $forum_ids ) :
		foreach ( $forum_ids as $count => $forum_id ) :
			$_slug = $slug;
			$count = - $count; // madness
			if ( is_numeric($slug) || $count )
				$_slug = bb_slug_increment( $slug, $count );
			$bbdb->query("UPDATE $bbdb->forums SET forum_slug = '$_slug' WHERE forum_id = '$forum_id';");
		endforeach;
	endforeach;
	unset($forums, $forum, $slugs, $slug, $_slug, $forum_ids, $forum_id, $count);

	// Topics

	$topics = (array) $bbdb->get_results("SELECT topic_id, topic_title FROM $bbdb->topics ORDER BY topic_start_time ASC" );

	$slugs = array();
	foreach ( $topics as $topic) :
		$slug = bb_slug_sanitize( $topic->topic_title );
		$slugs[$slug][] = $topic->topic_id;
	endforeach;

	foreach ( $slugs as $slug => $topic_ids ) :
		foreach ( $topic_ids as $count => $topic_id ) :
			$_slug = $slug;
			$count = - $count;
			if ( is_numeric($slug) || $count )
				$_slug = bb_slug_increment( $slug, $count );
			$bbdb->query("UPDATE $bbdb->topics SET topic_slug = '$_slug' WHERE topic_id = '$topic_id';");
		endforeach;
	endforeach;
	unset($topics, $topic, $slugs, $slug, $_slug, $topic_ids, $topic_id, $count);
}

// Reversibly break passwords of blocked users.
function bb_upgrade_160() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 535 )
		return;

	require_once('admin-functions.php');
	$blocked = bb_get_ids_by_role( 'blocked' );
	foreach ( $blocked as $b )
		bb_break_password( $b );
	return 'Done reversibly breaking passwords: ' . __FUNCTION__;
}

function bb_upgrade_170() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 536 )
		return;

	global $bbdb;
	foreach ( (array) $bbdb->get_results("SELECT * FROM $bbdb->usermeta WHERE meta_value LIKE '%&quot;%' OR meta_value LIKE '%&#039;%'") as $meta ) {
		$value = str_replace(array('&quot;', '&#039;'), array('"', "'"), $meta->meta_value);
		$value = stripslashes($value);
		bb_update_usermeta( $meta->user_id, $meta->meta_key, $value);
	}
	bb_update_option( 'bb_db_version', 536 );
	return 'Done updating usermeta: ' . __FUNCTION__;
}

function bb_upgrade_180() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 559 )
		return;

	global $bbdb;

	foreach ( (array) $bbdb->get_col("SELECT ID FROM $bbdb->users WHERE user_status = 1") as $user_id )
		bb_delete_user( $user_id );
	bb_update_option( 'bb_db_version', 559 );
	return 'Done clearing deleted users: ' . __FUNCTION__;
}

function bb_upgrade_190() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 630 )
		return;

	global $bbdb;

	$exists = false;
	foreach ( (array) $bbdb->get_col("DESC $bbdb->topics") as $col )
		if ( 'topic_resolved' == $col )
			$exists = true;
	if ( !$exists )
		return;

	$topics = (array) $bbdb->get_results("SELECT topic_id, topic_resolved FROM $bbdb->topics" );
	foreach ( $topics  as $topic )
		bb_update_topicmeta( $topic->topic_id, 'topic_resolved', $topic->topic_resolved );
	unset($topics,$topic);

	$bbdb->query("ALTER TABLE $bbdb->topics DROP topic_resolved");

	bb_update_option( 'bb_db_version', 630 );

	return 'Done converting topic_resolved: ' . __FUNCTION__;
}

function bb_upgrade_200() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 845 )
		return;

	global $bbdb;

	$bbdb->hide_errors();
	$bbdb->query( "DROP INDEX tag_id_index ON $bbdb->tagged" );
	$bbdb->query( "DROP INDEX user_id ON $bbdb->topicmeta" );
	$bbdb->query( "DROP INDEX forum_id ON $bbdb->topics" );
	$bbdb->query( "DROP INDEX topic_time ON $bbdb->topics" );
	$bbdb->query( "DROP INDEX topic_start_time ON $bbdb->topics" );
	$bbdb->query( "DROP INDEX tag_id_index ON $bbdb->tagged" );
	$bbdb->query( "DROP INDEX topic_id ON $bbdb->posts" );
	$bbdb->query( "DROP INDEX poster_id ON $bbdb->posts" );
	$bbdb->show_errors();

	bb_update_option( 'bb_db_version', 845 );

	return 'Done removing old indices: ' . __FUNCTION__;
}

// 210 converts text slugs to varchar(255) width slugs (upgrading from alpha version - fires before dbDelta)
// 1000 Gives new slugs (upgrading from previous release - fires after dbDelta)
function bb_upgrade_210() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 846 )
		return;

	global $bbdb;

	$bbdb->hide_errors();
	if ( !$ids = $bbdb->get_var("SELECT forum_slug FROM $bbdb->forums ORDER BY forum_order ASC LIMIT 1" ) )
		return; // Wait till after dbDelta
	$bbdb->show_errors();

	bb_upgrade_process_all_slugs();

	bb_update_option( 'bb_db_version', 846 );
	
	return 'Done adding slugs: ' . __FUNCTION__;
}

function bb_upgrade_220() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1051 )
		return;

	global $bbdb;

	$bbdb->query( "ALTER TABLE $bbdb->tagged DROP PRIMARY KEY" );
	$bbdb->query( "ALTER TABLE $bbdb->tagged ADD tagged_id bigint(20) unsigned NOT NULL auto_increment PRIMARY KEY FIRST" );

	return "Done removing key from $bbdb->tagged: " . __FUNCTION__;
}

function bb_upgrade_1000() { // Give all topics and forums slugs
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 846 )
		return;

	bb_upgrade_process_all_slugs();

	bb_update_option( 'bb_db_version', 846 );
	
	return 'Done adding slugs: ' . __FUNCTION__;;
}

// Make sure all forums have a valid parent
function bb_upgrade_1010() {
	global $bbdb;
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 952 )
		return;

	$forums = (array) $bbdb->get_results( "SELECT forum_id, forum_parent FROM $bbdb->forums" );
	$forum_ids = (array) $bbdb->get_col( '', 0 );

	foreach ( $forums as $forum ) {
		if ( $forum->forum_parent && !in_array( $forum->forum_parent, $forum_ids ) )
			$bbdb->query( "UPDATE $bbdb->forums SET forum_parent = 0 WHERE forum_id = '$forum->forum_id'" );
	}

	bb_update_option( 'bb_db_version', 952 );
	
	return 'Done re-parenting orphaned forums: ' . __FUNCTION__;
}

// Add a nicename for existing users if they don't have one already
function bb_upgrade_1020() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 977 )
		return;
	
	global $bbdb;
	
	$users = $bbdb->get_results( "SELECT ID, user_login, user_nicename FROM $bbdb->users WHERE user_nicename IS NULL OR user_nicename = ''" );
	
	if ( $users ) {
		foreach ( $users as $user ) {
			$user_nicename = $_user_nicename = bb_user_nicename_sanitize( $user->user_login );
			while ( is_numeric($user_nicename) || $existing_user = bb_get_user_by_nicename( $user_nicename ) )
				$user_nicename = bb_slug_increment($_user_nicename, $existing_user->user_nicename, 50);
			
			$bbdb->query( "UPDATE $bbdb->users SET user_nicename = '$user_nicename' WHERE ID = $user->ID;" );
		}
	}
	
	bb_update_option( 'bb_db_version', 977 );
	
	return 'Done adding nice-names to existing users: ' . __FUNCTION__;
}

// Move admin_email option to from_email
function bb_upgrade_1030() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1058 )
		return;
	
	$admin_email = bb_get_option('admin_email');
	if ($admin_email) {
		bb_update_option('from_email', $admin_email);
	}
	bb_delete_option('admin_email');
	
	bb_update_option( 'bb_db_version', 1058 );
	
	return 'Done moving admin_email to from_email: ' . __FUNCTION__;
}

// Activate Akismet and bozo plugins on upgrade only
function bb_upgrade_1040() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1174 )
		return;
	
	// Only do this when upgrading
	if ( defined( 'BB_UPGRADING' ) && BB_UPGRADING ) {
		$plugins = bb_get_option('active_plugins');
		if ( bb_get_option('akismet_key') && !in_array('akismet.php', $plugins) ) {
			$plugins[] = 'akismet.php';
		}
		if ( !in_array('bozo.php', $plugins) ) {
			$plugins[] = 'bozo.php';
		}
		ksort($plugins);
		bb_update_option( 'active_plugins', $plugins );
	}
	
	bb_update_option( 'bb_db_version', 1174 );
	
	return 'Done activating Akismet and Bozo plugins on upgrade only: ' . __FUNCTION__;
}

function bb_deslash($content) {
    // Note: \\\ inside a regex denotes a single backslash.

    // Replace one or more backslashes followed by a single quote with
    // a single quote.
    $content = preg_replace("/\\\+'/", "'", $content);

    // Replace one or more backslashes followed by a double quote with
    // a double quote.
    $content = preg_replace('/\\\+"/', '"', $content);

    // Replace one or more backslashes with one backslash.
    $content = preg_replace("/\\\+/", "\\", $content);

    return $content;
}

function bb_update_db_version() {
	bb_update_option( 'bb_db_version', bb_get_option( 'bb_db_version' ) );
}
?>
