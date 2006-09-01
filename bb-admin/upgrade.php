<?php
if ( ini_get('safe_mode') )
	die("You're running in safe mode which does not allow this upgrade
	script to set a running time limit.  Depending on the size of your
	database and on which parts of the script you are running, the script
	can take quite some time to run (or it could take just a few seconds).
	To throw caution to the wind and run the script in safe mode anyway,
	remove the first two lines of code in this file.  Backups are always a
	good idea.");
require('../bb-load.php');
set_time_limit(600);

// Use the following only if you have a May, 2005 or earlier version of bbPress
// Uncomment them to use. Best to run one at a time FROM TOP TO BOTTOM (BEGINNING TO END)

/*
$topics = $bbdb->get_results("SELECT topic_id FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$poster = $bbdb->get_row("SELECT poster_id, poster_name FROM $bbdb->posts WHERE topic_id = $topic->topic_id ORDER BY post_time DESC LIMIT 1");
		echo '.';
		$bbdb->query("UPDATE $bbdb->topics SET topic_last_poster = '$poster->poster_id', topic_last_poster_name = '$poster->poster_name' WHERE topic_id = '$topic->topic_id'");
	}
}
unset($topics);
echo "Done with adding people...";
flush();
*/

/*
$posts = $bbdb->get_results("SELECT post_id, post_text FROM $bbdb->posts");
if ($posts) {
	foreach($posts as $bb_post) {
		echo '.'; flush();
		$post_text = addslashes(deslash($bb_post->post_text));
		$post_text = bb_apply_filters('pre_post', $post_text);
		$bbdb->query("UPDATE $bbdb->posts SET post_text = '$post_text' WHERE post_id = '$bb_post->post_id'");
	}
}

unset($posts);
echo "Done with preformatting posts...";
*/

/*
$topics = $bbdb->get_results("SELECT topic_id, topic_title FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$topic_title = wp_specialchars(addslashes(deslash($topic->topic_title)));
		$bbdb->query("UPDATE $bbdb->topics SET topic_title = '$topic_title' WHERE topic_id = '$topic->topic_id'");
		echo '.';
	}
}
echo "Done with preformatting topics!";
flush();
*/

/* Add _topics.topic_start_time column: June 4th, 2005
$bbdb->query("ALTER TABLE $bbdb->topics ADD topic_start_time DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER topic_last_poster_name");
echo "Done with adding topic_start_time column\n";
flush();
*/

/* Populate _topics.topic_start_time: June 4th, 2005
$topics = $bbdb->get_results("SELECT topic_id FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$start_time = $bbdb->get_var("SELECT post_time FROM $bbdb->posts RIGHT JOIN $bbdb->topics ON ( $bbdb->posts.topic_id = $bbdb->topics.topic_id )
 WHERE $bbdb->topics.topic_id = '$topic->topic_id' ORDER BY post_time ASC LIMIT 1");
		echo '.';
		$bbdb->query("UPDATE $bbdb->topics SET topic_start_time = '$start_time' WHERE topic_id = '$topic->topic_id'");
	}
}
unset($topics);
echo "Done with adding topic_start_time...\n";
flush();
*/

/* Add _topics.topic_resolved column: June 11th, 2005
$bbdb->query("ALTER TABLE $bbdb->topics ADD topic_resolved VARCHAR(15) DEFAULT 'no' NOT NULL AFTER topic_status");
echo "Done with adding topic_resolved column\n";
flush();
*/

// Make user table column names parallel WP's: July 2nd, 2005
/*
upgrade_100();
*/

// Move user meta info into usermeta and drop from users.  May generate some index key errors from running upgrade-schema.php: July 2nd, 2005
/*
require_once('upgrade-schema.php');
upgrade_110();
*/

//Put user_registered back in users: July 5th, 2005
/*
require_once('upgrade-schema.php');
upgrade_110();
upgrade_120();
*/

//Add posts.post_position.  Populate: July 14th, 2005
/*
require_once('upgrade-schema.php');
upgrade_130();
*/

//meta_value -> $bb_table_prefix . meta_value: July23rd, 2005
/*
upgrade_140();
*/

//Translate user_type to capabilities Aug 13th, 2005
/*
upgrade_150();
*/

//alter user table column names
function upgrade_100() {
	global $bbdb, $bb_table_prefix;
	$fields = $bbdb->get_col("SHOW COLUMNS FROM $bbdb->users");
	if ( in_array( 'user_id', $fields ) )
		$bbdb->query("ALTER TABLE `$bbdb->users` CHANGE `user_id` `ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT");
	if ( in_array( 'username', $fields ) )
		$bbdb->query("ALTER TABLE `$bbdb->users` CHANGE `username` `user_login` varchar(60) NOT NULL default ''");
	if ( in_array( 'user_password', $fields ) )
		$bbdb->query("ALTER TABLE `$bbdb->users` CHANGE `user_password` `user_pass` varchar(64) NOT NULL default ''");
	if ( in_array( 'user_email', $fields ) )
		$bbdb->query("ALTER TABLE `$bbdb->users` CHANGE `user_email` `user_email` varchar(100) NOT NULL default ''");
	if ( in_array( 'user_website', $fields ) )
		$bbdb->query("ALTER TABLE `$bbdb->users` CHANGE `user_website` `user_url` varchar(100) NOT NULL default ''");
	if ( in_array( 'user_regdate', $fields ) )
		$bbdb->query("ALTER TABLE `$bbdb->users` CHANGE `user_regdate` `user_registered` datetime NOT NULL default '0000-00-00 00:00:00'");
	if ( !in_array( 'user_status', $fields ) )
		$bbdb->query("ALTER TABLE `$bbdb->users` ADD `user_status` int(11) NOT NULL default '0'");
}

//users -> populate usermeta.  drop old users columns
function upgrade_110() {
	global $bbdb, $bb_table_prefix;
	$users = $bbdb->get_results("SELECT * FROM $bbdb->users");
	$old_user_fields = array( 'type', 'icq', 'occ', 'from', 'interest', 'viewemail', 'sorttopics', 'newpwdkey', 'newpasswd', 'title' );
	foreach ( $users as $user ) :
		foreach ( $old_user_fields as $field )
			if ( isset( $user->{'user_' . $field} ) && $user->{'user_' . $field} !== '' )
				if ( 'type' == $field )
					bb_update_usermeta( $user->ID, $bb_table_prefix . 'user_type', $user->user_type );
				else
					bb_update_usermeta( $user->ID, $field, $user->{'user_' . $field} );
	endforeach;

	$bbdb->hide_errors();
	foreach ( $old_user_fields as $old ) {
		$old = 'user_' . $old;
		
		$bbdb->query("ALTER TABLE $bbdb->users DROP $old");
	}
	$bbdb->show_errors();
}

//put registration date back in.  RERUN upgrade_100() and upgrade-schema!!!!!!
function upgrade_120() {
	global $bbdb, $bb;
	if ( $usermetas = $bbdb->get_results("SELECT * FROM $bbdb->usermeta where meta_key = 'regdate'") ) {
		foreach ( $usermetas as $usermeta ) {
			$reg_date = gmdate('Y-m-d H:i:s', $usermeta->meta_value + $bb->gmt_offset * 3600);
			$bbdb->query("UPDATE $bbdb->users SET user_registered = '$reg_date' WHERE ID = '$usermeta->user_id'");
		}

		$bbdb->query("DELETE FROM $bbdb->usermeta WHERE meta_key = 'regdate'");
	}
}

//populate posts.post_position
function upgrade_130() {
	global $bbdb;
	if ( $topics = $bbdb->get_col("SELECT topic_id FROM $bbdb->topics") )
		foreach ( $topics as $topic_id )
			update_post_positions( $topic_id );
}

//meta conversion
function upgrade_140() {
	global $bbdb, $bb_table_prefix;
	$newkey = $bb_table_prefix . 'user_type';
	$bbdb->query("UPDATE $bbdb->usermeta SET meta_key = '$newkey' WHERE meta_key = 'user_type'");
	$newkey = $bb_table_prefix . 'title';
	$bbdb->query("UPDATE $bbdb->usermeta SET meta_key = '$newkey' WHERE meta_key = 'title'");
	$newkey = $bb_table_prefix . 'favorites';
	$bbdb->query("UPDATE $bbdb->usermeta SET meta_key = '$newkey' WHERE meta_key = 'favorites'");
	$newkey = $bb_table_prefix . 'topics_replied';
	$bbdb->query("UPDATE $bbdb->usermeta SET meta_key = '$newkey' WHERE meta_key = 'topics_replied'");
}

//user_type -> capabilities
function upgrade_150() {
	global $bbdb, $bb_table_prefix;
	$old_key = $bb_table_prefix . 'user_type';
	$new_key = $bb_table_prefix . 'capabilities';
	$member = serialize(array('member' => true));
	$role['2'] = $role['1'] = serialize(array('moderator' => true));
	$role['4'] = $role['3'] = serialize(array('administrator' => true));
	$role['5'] = serialize(array('keymaster' => true));
	$inactive = serialize(array('inactive' => true));
	if ( $mods = $bbdb->get_col("SELECT user_id, meta_value FROM $bbdb->usermeta WHERE meta_key = '$old_key' AND meta_value > 0") ) :
		$mod_type = $bbdb->get_col('', 1);
		foreach ( $mods as $i => $u ) :
			if ( !$set = $bbdb->get_var("SELECT umeta_id FROM $bbdb->usermeta WHERE meta_key = '$new_key' AND user_id = $u") )
				$bbdb->query("INSERT INTO $bbdb->usermeta ( user_id, meta_key, meta_value ) VALUES ( $u, '$new_key', '{$role[$mod_type[$i]]}' )");
		endforeach;
		echo "Done translating from moderators' user_types to roles<br />\n";
	endif;
	if ( $user_ids = $bbdb->get_col("SELECT ID, user_status FROM $bbdb->users") ) :
		$user_stati = $bbdb->get_col('' , 1);
		foreach ( $user_ids as $i => $u ) :
			if ( !$set = $bbdb->get_var("SELECT umeta_id FROM $bbdb->usermeta WHERE meta_key = '$new_key' AND user_id = $u") ) :
				if ( $user_stati[$i] == 2 )
					$bbdb->query("INSERT INTO $bbdb->usermeta ( user_id, meta_key, meta_value ) VALUES ( $u, '$new_key', '$inactive' )");
				else
					$bbdb->query("INSERT INTO $bbdb->usermeta ( user_id, meta_key, meta_value ) VALUES ( $u, '$new_key', '$member' )");
			endif;
		endforeach;
		echo "Done translating all users' user_types to role<br />\n";
	endif;
	$bbdb->query("DELETE FROM $bbdb->usermeta WHERE meta_key = '$old_key'");
	echo "Done deleting user_type<br />\n";
}

function deslash($content) {
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

?>
