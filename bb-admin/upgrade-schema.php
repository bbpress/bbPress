<?php
global $bb_queries, $bbdb;

$bb_queries = "CREATE TABLE $bbdb->forums (
  forum_id int(10) NOT NULL auto_increment,
  forum_name varchar(150)  NOT NULL default '',
  forum_slug text  NOT NULL default '',
  forum_desc text  NOT NULL,
  forum_parent int(10) NOT NULL default '0',
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
  KEY post_time (post_time),
  FULLTEXT KEY post_text (post_text)
) TYPE = MYISAM;
CREATE TABLE $bbdb->topics (
  topic_id bigint(20) NOT NULL auto_increment,
  topic_title varchar(100) NOT NULL default '',
  topic_slug text NOT NULL default '',
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
  KEY forum_id (forum_id),
  KEY topic_time (topic_time),
  KEY topic_start_time (topic_start_time)
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
  tag varchar(200) NOT NULL default '',
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

do_action( 'bb_schema_defined' );

?>
