<?php
global $bb_queries, $bbdb;

$charset_collate = '';
$user_charset_collate = '';

if ( !defined( 'BB_MYSQLI' ) )
	die( __('Database class not loaded.') );

if ( $bbdb->has_cap( 'collation', $bbdb->forums ) ) {
	if ( ! empty($bbdb->charset) )
		$charset_collate = "DEFAULT CHARACTER SET $bbdb->charset";
	if ( ! empty($bbdb->collate) )
		$charset_collate .= " COLLATE $bbdb->collate";
}

if ( $bbdb->has_cap( 'collation', $bbdb->users ) ) {
	if ( ! empty($bbdb->user_charset) )
		$user_charset_collate = "DEFAULT CHARACTER SET $bbdb->user_charset";
	if ( ! empty($bbdb->user_collate) )
		$user_charset_collate .= " COLLATE $bbdb->user_collate";
}

$bb_queries = "CREATE TABLE $bbdb->forums (
  forum_id int(10) NOT NULL auto_increment,
  forum_name varchar(150)  NOT NULL default '',
  forum_slug varchar(255)  NOT NULL default '',
  forum_desc text  NOT NULL,
  forum_parent int(10) NOT NULL default '0',
  forum_order int(10) NOT NULL default '0',
  topics bigint(20) NOT NULL default '0',
  posts bigint(20) NOT NULL default '0',
  PRIMARY KEY  (forum_id)
) $charset_collate;
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
  KEY topic_time (topic_id,post_time),
  KEY poster_time (poster_id,post_time),
  KEY post_time (post_time),
  FULLTEXT KEY post_text (post_text)
) $charset_collate;
CREATE TABLE $bbdb->topics (
  topic_id bigint(20) NOT NULL auto_increment,
  topic_title varchar(100) NOT NULL default '',
  topic_slug varchar(255) NOT NULL default '',
  topic_poster bigint(20) NOT NULL default '0',
  topic_poster_name varchar(40) NOT NULL default 'Anonymous',
  topic_last_poster bigint(20) NOT NULL default '0',
  topic_last_poster_name varchar(40) NOT NULL default '',
  topic_start_time datetime NOT NULL default '0000-00-00 00:00:00',
  topic_time datetime NOT NULL default '0000-00-00 00:00:00',
  forum_id int(10) NOT NULL default '1',
  topic_status tinyint(1) NOT NULL default '0',
  topic_open tinyint(1) NOT NULL default '1',
  topic_last_post_id bigint(20) NOT NULL default '1',
  topic_sticky tinyint(1) NOT NULL default '0',
  topic_posts bigint(20) NOT NULL default '0',
  tag_count bigint(20) NOT NULL default '0',
  PRIMARY KEY  (topic_id),
  KEY forum_time (forum_id,topic_time),
  KEY user_start_time (topic_poster,topic_start_time)
) $charset_collate;
CREATE TABLE $bbdb->topicmeta (
  meta_id bigint(20) NOT NULL auto_increment,
  topic_id bigint(20) NOT NULL default '0',
  meta_key varchar(255) default NULL,
  meta_value longtext,
  PRIMARY KEY  (meta_id),
  KEY topic_id (topic_id),
  KEY meta_key (meta_key)
) $charset_collate;
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
  UNIQUE KEY user_login (user_login),
  UNIQUE KEY user_nicename (user_nicename)
) $user_charset_collate;
CREATE TABLE $bbdb->usermeta (
  umeta_id bigint(20) NOT NULL auto_increment,
  user_id bigint(20) NOT NULL default '0',
  meta_key varchar(255) default NULL,
  meta_value longtext,
  PRIMARY KEY  (umeta_id),
  KEY user_id (user_id),
  KEY meta_key (meta_key)
) $user_charset_collate;
CREATE TABLE $bbdb->tags (
  tag_id bigint(20) unsigned NOT NULL auto_increment,
  tag varchar(200) NOT NULL default '',
  raw_tag varchar(50) NOT NULL default '',
  tag_count bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (tag_id),
  KEY name (tag)
) $charset_collate;
CREATE TABLE $bbdb->tagged (
  tag_id bigint(20) unsigned NOT NULL default '0',
  user_id bigint(20) unsigned NOT NULL default '0',
  topic_id bigint(20) unsigned NOT NULL default '0',
  tagged_on datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (tag_id,user_id,topic_id),
  KEY user_id_index (user_id),
  KEY topic_id_index (topic_id)
) $charset_collate;
";

do_action( 'bb_schema_defined' );

?>
