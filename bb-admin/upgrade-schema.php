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
	if ( ! empty($bbdb->collate) )
		$user_charset_collate .= " COLLATE $bbdb->collate";
}

$bb_queries = array();

$bb_queries['forums'] = "CREATE TABLE $bbdb->forums (
  forum_id int(10) NOT NULL auto_increment,
  forum_name varchar(150)  NOT NULL default '',
  forum_slug varchar(255)  NOT NULL default '',
  forum_desc text  NOT NULL,
  forum_parent int(10) NOT NULL default '0',
  forum_order int(10) NOT NULL default '0',
  topics bigint(20) NOT NULL default '0',
  posts bigint(20) NOT NULL default '0',
  PRIMARY KEY  (forum_id),
  KEY forum_slug (forum_slug)
) $charset_collate;";

$bb_queries['meta'] = "CREATE TABLE $bbdb->meta (
  meta_id bigint(20) NOT NULL auto_increment,
  object_type varchar(16) NOT NULL default 'bb_option',
  object_id bigint(20) NOT NULL default '0',
  meta_key varchar(255) default NULL,
  meta_value longtext default NULL,
  PRIMARY KEY  (meta_id),
  KEY object_type__meta_key (object_type, meta_key),
  KEY object_type__object_id__meta_key (object_type, object_id, meta_key)
) $charset_collate;";

$bb_queries['posts'] = "CREATE TABLE $bbdb->posts (
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
) TYPE = MYISAM $charset_collate;";

$bb_queries['tagged'] = "CREATE TABLE $bbdb->tagged (
  tagged_id bigint(20) unsigned NOT NULL auto_increment,
  tag_id bigint(20) unsigned NOT NULL default '0',
  user_id bigint(20) unsigned NOT NULL default '0',
  topic_id bigint(20) unsigned NOT NULL default '0',
  tagged_on datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (tagged_id),
  UNIQUE KEY tag_user_topic (tag_id,user_id,topic_id),
  KEY user_id_index (user_id),
  KEY topic_id_index (topic_id)
) $charset_collate;";

$bb_queries['tags'] = "CREATE TABLE $bbdb->tags (
  tag_id bigint(20) unsigned NOT NULL auto_increment,
  tag varchar(200) NOT NULL default '',
  raw_tag varchar(50) NOT NULL default '',
  tag_count bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (tag_id),
  KEY name (tag)
) $charset_collate;";

$bb_queries['terms'] = "CREATE TABLE $bbdb->terms (
 term_id bigint(20) NOT NULL auto_increment,
 name varchar(55) NOT NULL default '',
 slug varchar(200) NOT NULL default '',
 term_group bigint(10) NOT NULL default 0,
 PRIMARY KEY  (term_id),
 UNIQUE KEY slug (slug)
) $charset_collate;";

$bb_queries['term_relationships'] = "CREATE TABLE $bbdb->term_relationships (
 object_id bigint(20) NOT NULL default 0,
 term_taxonomy_id bigint(20) NOT NULL default 0,
 user_id bigint(20) NOT NULL default 0,
 term_order int(11) NOT NULL default 0,
 PRIMARY KEY  (object_id,term_taxonomy_id),
 KEY term_taxonomy_id (term_taxonomy_id)
) $charset_collate;";

$bb_queries['term_taxonomy'] = "CREATE TABLE $bbdb->term_taxonomy (
 term_taxonomy_id bigint(20) NOT NULL auto_increment,
 term_id bigint(20) NOT NULL default 0,
 taxonomy varchar(32) NOT NULL default '',
 description longtext NOT NULL,
 parent bigint(20) NOT NULL default 0,
 count bigint(20) NOT NULL default 0,
 PRIMARY KEY  (term_taxonomy_id),
 UNIQUE KEY term_id_taxonomy (term_id,taxonomy)
) $charset_collate;";

$bb_queries['topics'] = "CREATE TABLE $bbdb->topics (
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
  KEY topic_slug (topic_slug),
  KEY forum_time (forum_id,topic_time),
  KEY user_start_time (topic_poster,topic_start_time),
  KEY forum_stickies (topic_status,forum_id,topic_sticky,topic_time)
) $charset_collate;";

$bb_queries['topicmeta'] = "CREATE TABLE $bbdb->topicmeta (
  meta_id bigint(20) NOT NULL auto_increment,
  topic_id bigint(20) NOT NULL default '0',
  meta_key varchar(255) default NULL,
  meta_value longtext,
  PRIMARY KEY  (meta_id),
  KEY topic_id (topic_id),
  KEY meta_key (meta_key)
) $charset_collate;";

$bb_queries['users'] = "CREATE TABLE $bbdb->users (
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
) $user_charset_collate;";

$bb_queries['usermeta'] = "CREATE TABLE $bbdb->usermeta (
  umeta_id bigint(20) NOT NULL auto_increment,
  user_id bigint(20) NOT NULL default '0',
  meta_key varchar(255) default NULL,
  meta_value longtext,
  PRIMARY KEY  (umeta_id),
  KEY user_id (user_id),
  KEY meta_key (meta_key)
) $user_charset_collate;";

$bb_queries = apply_filters( 'bb_schema', $bb_queries );

do_action( 'bb_schema_defined' );

?>
