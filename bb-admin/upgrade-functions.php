<?php

function bb_install() {
	require_once( BB_PATH . 'bb-admin/upgrade-schema.php');
	$alterations = bb_sql_delta($bb_queries);

	bb_update_db_version();

	return array_filter($alterations);
}

function bb_upgrade_all() {
	if ( !ini_get('safe_mode') )
		set_time_limit(600);

	$bb_upgrade = array();

	// Pre DB Delta
	$bb_upgrade['messages'][] = bb_upgrade_160(); // Break blocked users
	$bb_upgrade['messages'][] = bb_upgrade_170(); // Escaping in usermeta
	$bb_upgrade['messages'][] = bb_upgrade_180(); // Delete users for real
	$bb_upgrade['messages'][] = bb_upgrade_190(); // Move topic_resolved to topicmeta
	$bb_upgrade['messages'][] = bb_upgrade_200(); // Indices
	$bb_upgrade['messages'][] = bb_upgrade_210(); // Convert text slugs to varchar slugs
	$bb_upgrade['messages'][] = bb_upgrade_220(); // remove bb_tagged primary key, add new column and primary key

	require_once( BB_PATH . 'bb-admin/upgrade-schema.php');
	$delta = bb_sql_delta($bb_queries);
	$bb_upgrade['messages'] = array_merge($bb_upgrade['messages'], $delta['messages']);
	$bb_upgrade['errors'] = $delta['errors'];

	// Post DB Delta
	$bb_upgrade['messages'][] = bb_upgrade_1000(); // Make forum and topic slugs
	$bb_upgrade['messages'][] = bb_upgrade_1010(); // Make sure all forums have a valid parent
	$bb_upgrade['messages'][] = bb_upgrade_1020(); // Add a user_nicename to existing users
	$bb_upgrade['messages'][] = bb_upgrade_1030(); // Move admin_email option to from_email
	$bb_upgrade['messages'][] = bb_upgrade_1040(); // Activate Akismet and bozo plugins and convert active plugins to new convention on upgrade only
	$bb_upgrade['messages'][] = bb_upgrade_1050(); // Update active theme if present
	$bb_upgrade['messages'][] = bb_upgrade_1070(); // trim whitespace from raw_tag
	$bb_upgrade['messages'][] = bb_upgrade_1080(); // Convert tags to taxonomy
	$bb_upgrade['messages'][] = bb_upgrade_1090(); // Add display names
	$bb_upgrade['messages'][] = bb_upgrade_1100(); // Replace forum_stickies index with stickies (#876)

	bb_update_db_version();

	$bb_upgrade['messages'] = array_filter($bb_upgrade['messages']);
	$bb_upgrade['errors'] = array_filter($bb_upgrade['errors']);

	return $bb_upgrade;
}


/**
 * Builds a column definition as used in CREATE TABLE statements from
 * an array such as those returned by DESCRIBE `foo` statements
 */
function bb_sql_get_column_definition($column_data) {
	if (!is_array($column_data)) {
		return $column_data;
	}
	
	if ($column_data['Null'] == 'NO') {
		$null = 'NOT NULL';
	}
	
	$default = '';
	
	// Defaults aren't allowed at all on certain column types
	if (!in_array(
		strtolower($column_data['Type']),
		array('tinytext', 'text', 'mediumtext', 'longtext', 'blob', 'mediumblob', 'longblob')
	)) {
		if ($column_data['Null'] == 'YES' && $column_data['Default'] === null) {
			$default = 'default NULL';
		} elseif (preg_match('@^\d+$@', $column_data['Default'])) {
			$default = 'default ' . $column_data['Default'];
		} elseif (is_string($column_data['Default']) || is_float($column_data['Default'])) {
			$default = 'default \'' . $column_data['Default'] . '\'';
		}
	}
	
	$column_definition = '`' . $column_data['Field'] . '` ' . $column_data['Type'] . ' ' . $null . ' ' . $column_data['Extra'] . ' ' . $default;
	return preg_replace('@\s+@', ' ', trim($column_definition));
}

/**
 * Builds an index definition as used in CREATE TABLE statements from
 * an array similar to those returned by SHOW INDEX FROM `foo` statements
 */
function bb_sql_get_index_definition($index_data) {
	if (!is_array($index_data)) {
		return $index_data;
	}
	
	if (!count($index_data)) {
		return $index_data;
	}
	
	$_name = '`' . $index_data[0]['Key_name'] . '`';
	
	if ($index_data[0]['Index_type'] == 'BTREE' && $index_data[0]['Key_name'] == 'PRIMARY') {
		$_type = 'PRIMARY KEY';
		$_name = '';
	} elseif ($index_data[0]['Index_type'] == 'BTREE' && !$index_data[0]['Non_unique']) {
		$_type = 'UNIQUE KEY';
	} elseif ($index_data[0]['Index_type'] == 'FULLTEXT') {
		$_type = 'FULLTEXT KEY';
	} else {
		$_type = 'KEY';
	}
	
	$_columns = array();
	foreach ($index_data as $_index) {
		if ($_index['Sub_part']) {
			$_columns[] = '`' . $_index['Column_name'] . '`(' . $_index['Sub_part'] . ')';
		} else {
			$_columns[] = '`' . $_index['Column_name'] . '`';
		}
	}
	$_columns = join(', ', $_columns);
	
	$index_definition = $_type . ' ' . $_name . ' (' . $_columns . ')';
	return preg_replace('@\s+@', ' ', $index_definition);
}

/**
 * Returns a table structure from a raw sql query of the form "CREATE TABLE foo" etc.
 * The resulting array contains the original query, the columns as would be returned by DESCRIBE `foo`
 * and the indices as would be returned by SHOW INDEX FROM `foo` on a real table
 */
function bb_sql_describe_table($query) {
	// Retrieve the table structure from the query
	if (!preg_match('@^CREATE\s+TABLE(\s+IF\s+NOT\s+EXISTS)?\s+`?([^\s|`]+)`?\s+\((.*)\)\s*([^\)|;]*)\s*;?@ims', $query, $_matches))
		return $query;
	
	$_if_not_exists = $_matches[1];
	
	// Tidy up the table name
	$_table_name = trim($_matches[2]);
	
	// Tidy up the table columns/indices
	$_columns_indices = trim($_matches[3], " \t\n\r\0\x0B,");
	// Split by commas not followed by a closing parenthesis ")", using fancy lookaheads
	$_columns_indices = preg_split('@,(?!(?:[^\(]+\)))@ms', $_columns_indices);
	$_columns_indices = array_map('trim', $_columns_indices);
	
	// Tidy the table attributes
	$_attributes = preg_replace('@\s+@', ' ', trim($_matches[4]));
	unset($_matches);
	
	// Initialise some temporary arrays
	$_columns = array();
	$_indices = array();
	
	// Loop over the columns/indices
	foreach ($_columns_indices as $_column_index) {
		if (preg_match('@^(PRIMARY\s+KEY|UNIQUE\s+(?:KEY|INDEX)|FULLTEXT\s+(?:KEY|INDEX)|KEY|INDEX)\s+(?:`?(\w+)`?\s+)*\((.+?)\)$@im', $_column_index, $_matches)) {
			// It's an index
			
			// Tidy the type
			$_index_type = strtoupper(preg_replace('@\s+@', ' ', trim($_matches[1])));
			$_index_type = str_replace('INDEX', 'KEY', $_index_type);
			// Set the index name
			$_index_name = ('PRIMARY KEY' == $_matches[1]) ? 'PRIMARY' : $_matches[2];
			// Split into columns
			$_index_columns = array_map('trim', explode(',', $_matches[3]));
			
			foreach ($_index_columns as $_index_columns_index => $_index_column) {
				preg_match('@`?(\w+)`?(?:\s*\(\s*(\d+)\s*\))?@i', $_index_column, $_matches_column);
				$_indices[$_index_name][] = array(
					'Table'        => $_table_name,
					'Non_unique'   => ('UNIQUE KEY' == $_index_type || 'PRIMARY' == $_index_name) ? '0' : '1',
					'Key_name'     => $_index_name,
					'Seq_in_index' => (string) ($_index_columns_index + 1),
					'Column_name'  => $_matches_column[1],
					'Sub_part'     => $_matches_column[2] ? $_matches_column[2] : null,
					'Index_type'   => ('FULLTEXT KEY' == $_index_type) ? 'FULLTEXT' : 'BTREE'
				);
			}
			unset($_index_type, $_index_name, $_index_columns, $_index_columns_index, $_index_column, $_matches_column);
			
		} elseif (preg_match("@^`?(\w+)`?\s+(?:(\w+)(?:\s*\(\s*(\d+)\s*\))?(?:\s+(unsigned)){0,1})(?:\s+(NOT\s+NULL))?(?:\s+(auto_increment))?(?:\s+(default)\s+(?:(NULL|'[^']*'|\d+)))?@im", $_column_index, $_matches)) {
			// It's a column
			
			// Tidy the NOT NULL
			$_matches[5] = strtoupper(preg_replace('@\s+@', ' ', trim($_matches[5])));
			
			$_columns[$_matches[1]] = array(
				'Field'   => $_matches[1],
				'Type'    => (is_numeric($_matches[3])) ? $_matches[2] . '(' . $_matches[3] . ')' . ((strtolower($_matches[4]) == 'unsigned') ? ' unsigned' : '') : $_matches[2],
				'Null'    => ('NOT NULL' == strtoupper($_matches[5])) ? 'NO' : 'YES',
				'Default' => ('default' == strtolower($_matches[7]) && 'NULL' !== strtoupper($_matches[8])) ? trim($_matches[8], "'") : null,
				'Extra'   => ('auto_increment' == strtolower($_matches[6])) ? 'auto_increment' : ''
			);
		}
	}
	unset($_matches, $_columns_indices, $_column_index);
	
	// Tidy up the original query
	$_tidy_query = 'CREATE TABLE';
	if ($_if_not_exists) {
		$_tidy_query .= ' IF NOT EXISTS';
	}
	$_tidy_query .= ' `' . $_table_name . '` (' . "\n";
	foreach ($_columns as $_column) {
		$_tidy_query .= "\t" . bb_sql_get_column_definition($_column) . ",\n";
	}
	unset($_column);
	foreach ($_indices as $_index) {
		$_tidy_query .= "\t" . bb_sql_get_index_definition($_index) . ",\n";
	}
	$_tidy_query = substr($_tidy_query, 0, -2) . "\n" . ') ' . $_attributes . ';';
	
	// Add to the query array using the table name as the index
	$description = array(
		'query_original' => $query,
		'query_tidy' => $_tidy_query,
		'columns' => $_columns,
		'indices' => $_indices
	);
	unset($_table_name, $_columns, $_indices, $_tidy_query);
	
	return $description;
}

/**
 * Splits grouped SQL statements into queries within a highly structured array
 * Only supports CREATE TABLE, INSERT and UPDATE
 */
function bb_sql_parse($sql) {
	// Only accept strings or arrays
	if (is_string($sql)) {
		// Just pop strings into an array to start with
		$queries = array($sql);
	} elseif (is_array($sql)) {
		// Flatten the array
		$queries = bb_flatten_array($sql, 0, false);
		// Remove empty nodes
		$queries = array_filter($queries);
	} else {
		return false;
	}
	
	// Clean up the queries
	$_clean_queries = array();
	foreach ($queries as $_query) {
		// Trim space and semi-colons
		$_query = trim($_query, "; \t\n\r\0\x0B");
		// If it exists and isn't a number
		if ($_query && !is_numeric($_query)) {
			// Is it more than one query?
			if (strpos(';', $_query) !== false) {
				// Explode by semi-colon
				foreach (explode(';', $_query) as $_part) {
					$_part = trim($_part);
					if ($_part && !is_numeric($_part)) {
						$_clean_queries[] = $_part . ';';
					}
				}
				unset($_part);
			} else {
				$_clean_queries[] = $_query . ';';
			}
		}
	}
	unset($_query);
	if (!count($_clean_queries)) {
		return false;
	}
	$queries = $_clean_queries;
	unset($_clean_queries);
	
	$_queries = array();
	foreach ($queries as $_query) {
		// Only process table creation, inserts and updates, capture the table/database name while we are at it
		if (!preg_match('@^(CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+INTO|UPDATE)\s+`?([^\s|`]+)`?@im', $_query, $_matches)) {
			continue;
		}
		
		// Tidy up the type so we can switch it
		$_type = strtoupper(preg_replace('@\s+@', ' ', trim($_matches[1])));
		$_table_name = trim($_matches[2]);
		unset($_matches);
		
		switch ($_type) {
			case 'CREATE TABLE':
			case 'CREATE TABLE IF NOT EXISTS':
				$_description = bb_sql_describe_table($_query);
				if (is_array($_description)) {
					$_queries['tables'][$_table_name] = $_description;
				}
				break;
			
			case 'INSERT INTO':
				// Just add the query as is for now
				$_queries['insert'][$_table_name][] = $_query;
				break;
			
			case 'UPDATE':
				// Just add the query as is for now
				$_queries['update'][$_table_name][] = $_query;
				break;
		}
		unset($_type, $_table_name);
	}
	unset($_query);
	
	if (!count($_queries)) {
		return false;
	}
	return $_queries;
}

/**
 * Evaluates the difference between a given set of SQL queries and real database structure
 */
function bb_sql_delta($queries, $execute = true) {
	if (!$_queries = bb_sql_parse($queries)) {
		return 'No schema available';
	}
	
	global $bbdb;
	
	// Build an array of $bbdb registered tables and their database identifiers
	$_tables = $bbdb->tables;
	$bbdb_tables = array();
	foreach ($_tables as $_table_id => $_table_name) {
		if (is_array($_table_name) && isset($bbdb->db_servers['dbh_' . $_table_name[0]])) {
			$bbdb_tables[$bbdb->$_table_id] = 'dbh_' . $_table_name[0];
		} else {
			$bbdb_tables[$bbdb->$_table_id] = 'dbh_global';
		}
	}
	unset($_tables, $_table_id, $_table_name);
	
	$alterations = array();
	
	// Loop through table queries
	if (isset($_queries['tables'])) {
		foreach ($_queries['tables'] as $_new_table_name => $_new_table_data) {
			// See if the table is custom and registered in $bbdb under a custom database
			if (
				isset($bbdb_tables[$_new_table_name]) &&
				$bbdb_tables[$_new_table_name] != 'dbh_global' &&
				isset($bbdb->db_servers[$bbdb_tables[$_new_table_name]]['ds']))
			{
				// Force the database connection
				$_dbhname = $bbdb->db_servers[$bbdb_tables[$_new_table_name]]['ds'];
				$bbdb->_force_dbhname = $_dbhname;
			} else {
				$_dbhname = 'dbh_global';
			}
			
			// Fetch the existing table column structure from the database
			$bbdb->suppress_errors();
			if (!$_existing_table_columns = $bbdb->get_results('DESCRIBE `' . $_new_table_name . '`;', ARRAY_A)) {
				$bbdb->suppress_errors(false);
				// The table doesn't exist, add it and then continue to the next table
				$alterations[$_dbhname][$_new_table_name][] = array(
					'action' => 'create_table',
					'message' => __('Creating table'),
					'query' => $_new_table_data['query_tidy']
				);
				continue;
			}
			$bbdb->suppress_errors(false);
			
			// Add an index to the existing columns array
			$__existing_table_columns = array();
			foreach ($_existing_table_columns as $_existing_table_column) {
				// Remove 'Key' from returned column structure
				unset($_existing_table_column['Key']);
				$__existing_table_columns[$_existing_table_column['Field']] = $_existing_table_column;
			}
			$_existing_table_columns = $__existing_table_columns;
			unset($__existing_table_columns);
			
			// Loop over the columns in this table and look for differences
			foreach ($_new_table_data['columns'] as $_new_column_name => $_new_column_data) {
				if (!in_array($_new_column_data, $_existing_table_columns)) {
					// There is a difference
					if (!isset($_existing_table_columns[$_new_column_name])) {
						// The column doesn't exist, so add it
						$alterations[$_dbhname][$_new_table_name][] = array(
							'action' => 'add_column',
							'message' => __('Adding column:') . ' ' . $_new_column_name,
							'column' => $_new_column_name,
							'query' => 'ALTER TABLE `' . $_new_table_name . '` ADD COLUMN ' . bb_sql_get_column_definition($_new_column_data) . ';'
						);
						continue;
					}
					
					// Adjust defaults on columns that allow defaults
					if (
						$_new_column_data['Default'] !== $_existing_table_columns[$_new_column_name]['Default'] &&
						!in_array(
							strtolower($_new_column_data['Type']),
							array('tinytext', 'text', 'mediumtext', 'longtext', 'blob', 'mediumblob', 'longblob')
						)
					) {
						// Change the default value for the column
						$alterations[$_dbhname][$_new_table_name][] = array(
							'action' => 'set_default',
							'message' => __('Setting default on column:') . ' ' . $_new_column_name,
							'column' => $_new_column_name,
							'query' => 'ALTER TABLE `' . $_new_table_name . '` ALTER COLUMN `' . $_new_column_name . '` SET DEFAULT \'' . $_new_column_data['Default'] . '\';'
						);
						// Don't continue, overwrite this if the next conditional is met
					}
					
					if (
						$_new_column_data['Type'] !== $_existing_table_columns[$_new_column_name]['Type'] ||
						$_new_column_data['Null'] !== $_existing_table_columns[$_new_column_name]['Null'] ||
						$_new_column_data['Extra'] !== $_existing_table_columns[$_new_column_name]['Extra']
					) {
						// Change the structure for the column
						$alterations[$_dbhname][$_new_table_name][] = array(
							'action' => 'change_column',
							'message' => __('Changing column:') . ' ' . $_new_column_name,
							'column' => $_new_column_name,
							'query' => 'ALTER TABLE `' . $_new_table_name . '` CHANGE COLUMN `' . $_new_column_name . '` ' . bb_sql_get_column_definition($_new_column_data) . ';'
						);
					}
				}
			}
			unset($_existing_table_columns, $_new_column_name, $_new_column_data);
			
			// Fetch the table index structure from the database
			if (!$_existing_table_indices = $bbdb->get_results('SHOW INDEX FROM `' . $_new_table_name . '`;', ARRAY_A)) {
				continue;
			}
			
			// Add an index to the existing columns array and organise by index name
			$__existing_table_indices = array();
			foreach ($_existing_table_indices as $_existing_table_index) {
				// Remove unused parts from returned index structure
				unset(
					$_existing_table_index['Collation'],
					$_existing_table_index['Cardinality'],
					$_existing_table_index['Packed'],
					$_existing_table_index['Null'],
					$_existing_table_index['Comment']
				);
				$__existing_table_indices[$_existing_table_index['Key_name']][] = $_existing_table_index;
			}
			$_existing_table_indices = $__existing_table_indices;
			unset($__existing_table_indices);
			
			// Loop over the indices in this table and look for differences
			foreach ($_new_table_data['indices'] as $_new_index_name => $_new_index_data) {
				if (!in_array($_new_index_data, $_existing_table_indices)) {
					// There is a difference
					if (!isset($_existing_table_indices[$_new_index_name])) {
						// Ignore the 'user_login' index in the user table due to compatibility issues with WordPress
						if ($bbdb->users == $_new_table_name && $_new_index_name == 'user_login') {
							continue;
						}
						
						// The index doesn't exist, so add it
						$alterations[$_dbhname][$_new_table_name][] = array(
							'action' => 'add_index',
							'message' => __('Adding index:') . ' ' . $_new_index_name,
							'index' => $_new_index_name,
							'query' => 'ALTER TABLE `' . $_new_table_name . '` ADD ' . bb_sql_get_index_definition($_new_index_data) . ';'
						);
						continue;
					}
					
					if ($_new_index_data !== $_existing_table_indices[$_new_index_name]) {
						// Ignore the 'user_nicename' index in the user table due to compatibility issues with WordPress
						if ($bbdb->users == $_new_table_name && $_new_index_name == 'user_nicename') {
							continue;
						}
						
						// The index is incorrect, so drop it and add the new one
						if ($_new_index_name == 'PRIMARY') {
							$_drop_index_name = 'PRIMARY KEY';
						} else {
							$_drop_index_name = 'INDEX `' . $_new_index_name . '`';
						}
						$alterations[$_dbhname][$_new_table_name][] = array(
							'action' => 'drop_index',
							'message' => __('Dropping index:') . ' ' . $_new_index_name,
							'index' => $_new_index_name,
							'query' => 'ALTER TABLE `' . $_new_table_name . '` DROP ' . $_drop_index_name . ';'
						);
						unset($_drop_index_name);
						$alterations[$_dbhname][$_new_table_name][] = array(
							'action' => 'add_index',
							'message' => __('Adding index:') . ' ' . $_new_index_name,
							'index' => $_new_index_name,
							'query' => 'ALTER TABLE `' . $_new_table_name . '` ADD ' . bb_sql_get_index_definition($_new_index_data) . ';'
						);
					}
				}
			}
			unset($_new_index_name, $_new_index_data);
			
			// Go back to the default database connection
			$bbdb->_force_dbhname = false;
		}
		unset($_new_table_name, $_new_table_data, $_dbhname);
	}
	
	// Now deal with the sundry INSERT and UPDATE statements (if any)
	if (isset($_queries['insert']) && is_array($_queries['insert']) && count($_queries['insert'])) {
		foreach ($_queries['insert'] as $_table_name => $_inserts) {
			foreach ($_inserts as $_insert) {
				$alterations['dbh_global'][$_table_name][] = array(
					'action' => 'insert',
					'message' => __('Inserting data'),
					'query' => $_insert
				);
			}
			unset($_insert);
		}
		unset($_table_name, $_inserts);
	}
	if (isset($_queries['update']) && is_array($_queries['update']) && count($_queries['update'])) {
		foreach ($_queries['update'] as $_table_name => $_updates) {
			foreach ($_updates as $_update) {
				$alterations['dbh_global'][$_table_name][] = array(
					'action' => 'update',
					'message' => __('Updating data'),
					'query' => $_update
				);
			}
			unset($_update);
		}
		unset($_table_name, $_updates);
	}
	
	// Initialise an array to hold the output messages
	$messages = array();
	$errors = array();
	
	foreach ($alterations as $_dbhname => $_tables) {
		// Force the database connection (this was already checked to be valid in the previous loop)
		$bbdb->_force_dbhname = $_dbhname;
		
		// Note the database in the return messages
		$messages[] = '>>> ' . __('Modifying database:') . ' ' . $bbdb->db_servers[$_dbhname]['name'] . ' (' . $bbdb->db_servers[$_dbhname]['host'] . ')';
		
		foreach ($_tables as $_table_name => $_alterations) {
			// Note the table in the return messages
			$messages[] = '>>>>>> ' . __('Table:') . ' ' . $_table_name;
			
			foreach ($_alterations as $_alteration) {
				// If there is no query, then skip
				if (!$_alteration['query']) {
					continue;
				}
				
				// Note the action in the return messages
				$messages[] = '>>>>>>>>> ' . $_alteration['message'];
				
				if (!$execute) {
					$messages[] = '>>>>>>>>>>>> ' . __('Skipped');
					continue;
				}
				
				// Run the query
				$_result = $bbdb->query($_alteration['query']);
				$_result_error = $bbdb->get_error();

				if ( $_result_error ) {
					// There was an error
					$_result =& $_result_error;
					unset( $_result_error );
					$messages[] = '>>>>>>>>>>>> ' . __('SQL ERROR! See the error log for more detail');
					$errors[] = __('SQL ERROR!');
					$errors[] = '>>> ' . __('Database:') . ' ' . $bbdb->db_servers[$_dbhname]['name'] . ' (' . $bbdb->db_servers[$_dbhname]['host'] . ')';
					$errors[] = '>>>>>> ' . $_result->error_data['db_query']['query'];
					$errors[] = '>>>>>> ' . $_result->error_data['db_query']['error'];
				} else {
					$messages[] = '>>>>>>>>>>>> ' . __('Done');
				}
				unset($_result);
			}
			unset($_alteration);
		}
		unset($_table_name, $_alterations);
	}
	unset($_dbhname, $_tables);
	
	// Reset the database connection
	$bbdb->_force_dbhname = false;
	
	return array('messages' => $messages, 'errors' => $errors);
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
	if ( !$bbdb->get_var("SELECT forum_slug FROM $bbdb->forums ORDER BY forum_order ASC LIMIT 1" ) )
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

// Activate Akismet and bozo plugins and convert active plugins to new convention on upgrade only
function bb_upgrade_1040() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1230 )
		return;
	
	// Only do this when upgrading
	if ( defined( 'BB_UPGRADING' ) && BB_UPGRADING ) {
		$plugins = bb_get_option('active_plugins');
		if ( bb_get_option('akismet_key') && !in_array('core#akismet.php', $plugins) ) {
			$plugins[] = 'core#akismet.php';
		}
		if ( !in_array('core#bozo.php', $plugins) ) {
			$plugins[] = 'core#bozo.php';
		}
		
		$new_plugins = array();
		foreach ($plugins as $plugin) {
			if (substr($plugin, 0, 5) != 'core#') {
				if ($plugin != 'akismet.php' && $plugin != 'bozo.php') {
					$new_plugins[] = 'user#' . $plugin;
				}
			} else {
				$new_plugins[] = $plugin;
			}
		}
		
		bb_update_option( 'active_plugins', $new_plugins );
	}
	
	bb_update_option( 'bb_db_version', 1230 );
	
	return 'Done activating Akismet and Bozo plugins and converting active plugins to new convention on upgrade only: ' . __FUNCTION__;
}

// Update active theme if present
function bb_upgrade_1050() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1234 )
		return;
	
	// Only do this when upgrading
	if ( defined( 'BB_UPGRADING' ) && BB_UPGRADING ) {
		$theme = bb_get_option( 'bb_active_theme' );
		if ($theme) {
			$theme = str_replace(
				array(BB_CORE_THEME_DIR, BB_THEME_DIR),
				array('core#', 'user#'),
				$theme
			);
			$theme = trim($theme, '/');
			bb_update_option( 'bb_active_theme', $theme );
		}
	}
	
	bb_update_option( 'bb_db_version', 1234 );
	
	return 'Done updating active theme if present: ' . __FUNCTION__;
}

function bb_upgrade_1070() {
	global $bbdb;
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1467 )
		return;

	$bbdb->query( "UPDATE `$bbdb->tags` SET `raw_tag` = TRIM(`raw_tag`)" );

	bb_update_option( 'bb_db_version', 1467 );

	return 'Whitespace trimmed from raw_tag: ' . __FUNCTION__;
}

function bb_upgrade_1080() {
	global $bbdb, $wp_taxonomy_object;
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1526 )
		return;

	$offset = 0;
	while ( $tags = (array) $bbdb->get_results( "SELECT * FROM $bbdb->tags LIMIT $offset, 100" ) ) {
		if ( !ini_get('safe_mode') ) set_time_limit(600);
		$wp_taxonomy_object->defer_term_counting(true);
		for ( $i = 0; isset($tags[$i]); $i++ ) {
			$bbdb->insert( $bbdb->terms, array( 
				'name' => $tags[$i]->raw_tag,
				'slug' => $tags[$i]->tag
			) );
			$term_id = $bbdb->insert_id;
			$bbdb->insert( $bbdb->term_taxonomy, array(
				'term_id' => $term_id,
				'taxonomy' => 'bb_topic_tag',
				'description' => ''
			) );
			$term_taxonomy_id = $bbdb->insert_id;
			$topics = (array) $bbdb->get_results( $bbdb->prepare( "SELECT user_id, topic_id FROM $bbdb->tagged WHERE tag_id = %d", $tags[$i]->tag_id ) );
			for ( $j = 0; isset($topics[$j]); $j++ ) {
				$bbdb->insert( $bbdb->term_relationships, array(
					'object_id' => $topics[$j]->topic_id,
					'term_taxonomy_id' => $term_taxonomy_id,
					'user_id' => $topics[$j]->user_id
				) );
			}
			$wp_taxonomy_object->update_term_count( array( $term_taxonomy_id ), 'bb_topic_tag' );
		}
		$wp_taxonomy_object->defer_term_counting(false);
		$offset += 100;
	}

	bb_update_option( 'bb_db_version', 1526 );

	return 'Tags copied to taxonomy tables: ' . __FUNCTION__;
}

function bb_upgrade_1090() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1589 )
		return;

	global $bbdb;

	$users = (array) $bbdb->get_results( "SELECT `ID`, `user_login` FROM $bbdb->users WHERE `display_name` = '' OR `display_name` IS NULL;" );

	if ($users) {
		foreach ($users as $user) {
			$bbdb->query( "UPDATE $bbdb->users SET `display_name` = '" . $user->user_login . "' WHERE ID = " . $user->ID . ";" );
		}
		unset($user, $users);
	}

	bb_update_option( 'bb_db_version', 1589 );

	return 'Display names populated: ' . __FUNCTION__;
}

function bb_upgrade_1100() {
	if ( ( $dbv = bb_get_option_from_db( 'bb_db_version' ) ) && $dbv >= 1638 )
		return;

	global $bbdb;

	$bbdb->query( "DROP INDEX forum_stickies ON $bbdb->topics" );

	bb_update_option( 'bb_db_version', 1638 );

	return 'Index forum_stickies dropped: ' . __FUNCTION__;
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
