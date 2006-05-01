<?php
require_once('../config.php');
set_time_limit(600);

$bb_queries = "CREATE TABLE $bbdb->forums (
  forum_id int(10) NOT NULL auto_increment,
  forum_name varchar(150)  NOT NULL default '',
  forum_desc text  NOT NULL,
  forum_order int(10) NOT NULL default '0',
  topics bigint(20) NOT NULL default '0',
  posts bigint(20) NOT NULL default '0',
  PRIMARY KEY  (forum_id)
);
CREATE TABLE $bbdb->posts (
  post_id bigint(20) NOT NULL auto_increment,
  forum_id int(10) NOT NULL default '1',
  topic_id bigint(20) NOT NULL default '1',
  poster_id int(10) NOT NULL default '0',
  post_text text NOT NULL,
  post_time datetime NOT NULL default '0000-00-00 00:00:00',
  poster_ip varchar(15) NOT NULL default '',
  post_status tinyint(1) NOT NULL default '0',
  post_position bigint(20) NOT NULL default '0',
  PRIMARY KEY  (post_id),
  KEY topic_id (topic_id),
  KEY poster_id (poster_id),
  FULLTEXT KEY post_text (post_text)
);
CREATE TABLE $bbdb->topics (
  topic_id bigint(20) NOT NULL auto_increment,
  topic_title varchar(100) NOT NULL default '',
  topic_poster bigint(20) NOT NULL default '0',
  topic_poster_name varchar(40) NOT NULL default 'Anonymous',
  topic_last_poster bigint(20) NOT NULL default '0',
  topic_last_poster_name varchar(40) NOT NULL default '',
  topic_start_time datetime NOT NULL default '0000-00-00 00:00:00',
  topic_time datetime NOT NULL default '0000-00-00 00:00:00',
  forum_id int(10) NOT NULL default '1',
  topic_status tinyint(1) NOT NULL default '0',
  topic_resolved varchar(15) NOT NULL default 'no',
  topic_open tinyint(1) NOT NULL default '1',
  topic_last_post_id bigint(20) NOT NULL default '1',
  topic_sticky tinyint(1) NOT NULL default '0',
  topic_posts bigint(20) NOT NULL default '0',
  tag_count bigint(20) NOT NULL default '0',
  PRIMARY KEY  (topic_id),
  KEY forum_id (forum_id)
);
CREATE TABLE $bbdb->topicmeta (
  meta_id bigint(20) NOT NULL auto_increment,
  topic_id bigint(20) NOT NULL default '0',
  meta_key varchar(255) default NULL,
  meta_value longtext,
  PRIMARY KEY  (meta_id),
  KEY user_id (topic_id),
  KEY meta_key (meta_key)
);
CREATE TABLE $bbdb->users (
  ID bigint(20) unsigned NOT NULL auto_increment,
  user_login varchar(60) NOT NULL default '',
  user_pass varchar(64) NOT NULL default '',
  user_nicename varchar(50) NOT NULL default '',
  user_email varchar(100) NOT NULL default '',
  user_url varchar(100) NOT NULL default '',
  user_registered datetime NOT NULL default '0000-00-00 00:00:00',
  user_status int(11) NOT NULL default '0',
  display_name varchar(250) NOT NULL default '',
  PRIMARY KEY  (ID),
  UNIQUE KEY user_login (user_login)
);
CREATE TABLE $bbdb->usermeta (
  umeta_id bigint(20) NOT NULL auto_increment,
  user_id bigint(20) NOT NULL default '0',
  meta_key varchar(255) default NULL,
  meta_value longtext,
  PRIMARY KEY  (umeta_id),
  KEY user_id (user_id),
  KEY meta_key (meta_key)
);
CREATE TABLE $bbdb->tags (
  tag_id bigint(20) unsigned NOT NULL auto_increment,
  tag varchar(30) NOT NULL default '',
  raw_tag varchar(50) NOT NULL default '',
  tag_count bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (tag_id)
);
CREATE TABLE $bbdb->tagged (
  tag_id bigint(20) unsigned NOT NULL default '0',
  user_id bigint(20) unsigned NOT NULL default '0',
  topic_id bigint(20) unsigned NOT NULL default '0',
  tagged_on datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (tag_id,user_id,topic_id),
  KEY tag_id_index (tag_id),
  KEY user_id_index (user_id),
  KEY topic_id_index (topic_id)
);
";

function dbDelta($queries, $execute = true) {
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
			$for_update[$matches[1]] = 'Created table '.$matches[1];
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
	if($tables = $bbdb->get_col('SHOW TABLES;')) {
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
		foreach($allqueries as $query) {
			//echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">".print_r($query, true)."</pre>\n";
			$bbdb->query($query);
		}
	}

	return $for_update;
}

function make_db_current() {
	global $bb_queries;

	$alterations = dbDelta($bb_queries);
	echo "<ol>\n";
	foreach($alterations as $alteration) {
		echo "<li>$alteration</li>\n";
		flush();
		}
	echo "</ol>\n";
}

make_db_current();

?>
