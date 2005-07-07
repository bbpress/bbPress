<?php
require('../bb-config.php');
header ('content-type: text/plain');
set_time_limit(600);
// Uncomment to use. Best to run one at a time FROM TOP TO BOTTOM (BEGINNING TO END)

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
	foreach($posts as $post) {
		echo '.'; flush();
		$post_text = addslashes(deslash($post->post_text));
		$post_text = bb_apply_filters('pre_post', $post_text);
		$bbdb->query("UPDATE $bbdb->posts SET post_text = '$post_text' WHERE post_id = '$post->post_id'");
	}
}

unset($posts);
echo "Done with preformatting posts...";
*/

/*
$topics = $bbdb->get_results("SELECT topic_id, topic_title FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$topic_title = bb_specialchars(addslashes(deslash($topic->topic_title)));
		$bbdb->query("UPDATE $bbdb->topics SET topic_title = '$topic_title' WHERE topic_id = '$topic->topic_id'");
		echo '.';
	}
}
echo "Done with preformatting topics!";
flush();
*/

/* Add _topics.topic_start_time column
$bbdb->query("ALTER TABLE $bbdb->topics ADD topic_start_time DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER topic_last_poster_name");
echo "Done with adding topic_start_time column\n";
flush();
*/

/* Populate _topics.topic_start_time: June 3rd, 2005
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

/* Add _topics.topic_resolved column
$bbdb->query("ALTER TABLE $bbdb->topics ADD topic_resolved VARCHAR(15) DEFAULT 'no' NOT NULL AFTER topic_status");
echo "Done with adding topic_resolved column\n";
flush();
*/

// Make user table column names parallel WP's
/*
upgrade_100();
*/

// Move user meta info into usermeta and drop from users.  Will generate some index key errors from running upgrade-schema.php
/*
require_once('upgrade-schema.php');
upgrade_110();
*/

//Put user_registered back in users.
/*
require_once('upgrade-schema.php');
upgrade_110();
upgrade_120();
*/

//alter user table column names
function upgrade_100() {
	global $bbdb, $table_prefix;
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
	global $bbdb, $table_prefix;
	$users = $bbdb->get_results("SELECT * FROM $bbdb->users");
	$old_user_fields = array( 'type', 'icq', 'occ', 'from', 'interest', 'viewemail', 'sorttopics', 'newpwdkey', 'newpasswd', 'title' );
	foreach ( $users as $user ) :
		foreach ( $old_user_fields as $field )
			if ( isset( $user->{'user_' . $field} ) && $user->{'user_' . $field} !== '' )
				if ( 'type' == $field )
					update_usermeta( $user->ID, 'user_type', $user->user_type );
				else
					update_usermeta( $user->ID, $field, $user->{'user_' . $field} );
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
