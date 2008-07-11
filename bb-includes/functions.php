<?php
/* INIT */

function bb_global_sanitize( $array, $trim = true ) {
	foreach ($array as $k => $v) {
		if ( is_array($v) ) {
			$array[$k] = bb_global_sanitize($v);
		} else {
			if ( !get_magic_quotes_gpc() )
				$array[$k] = addslashes($v);
			if ( $trim )
				$array[$k] = trim($array[$k]);
		}
	}
	return $array;
}

function bb_is_installed() { // Maybe grab all the forums and cache them
	global $bbdb;
	$bbdb->suppress_errors();
	$forums = (array) get_forums();
	$bbdb->suppress_errors(false);
	if ( !$forums )
		return false;

	return true;
}

function bb_set_custom_user_tables() {
	global $bb;
	
	// Check for older style custom user table
	// TODO: Completely remove old constants on version 1.0
	if ( !isset($bb->custom_tables['users']) ) { // Don't stomp new setting style
		if ( !$bb->custom_user_table = bb_get_option('custom_user_table') ) // Maybe get from database or old config setting
			if ( defined('CUSTOM_USER_TABLE') ) // Maybe user has set old constant
				$bb->custom_user_table = CUSTOM_USER_TABLE;
		if ( $bb->custom_user_table ) {
			if ( !isset($bb->custom_tables) )
				$bb->custom_tables = array();
			$bb->custom_tables['users'] = $bb->custom_user_table;
		}
	}

	// Check for older style custom user meta table
	// TODO: Completely remove old constants on version 1.0
	if ( !isset($bb->custom_tables['usermeta']) ) { // Don't stomp new setting style
		if ( !$bb->custom_user_meta_table = bb_get_option('custom_user_meta_table') ) // Maybe get from database or old config setting
			if ( defined('CUSTOM_USER_META_TABLE') ) // Maybe user has set old constant
				$bb->custom_user_meta_table = CUSTOM_USER_META_TABLE;
		if ( $bb->custom_user_meta_table ) {
			if ( !isset($bb->custom_tables) )
				$bb->custom_tables = array();
			$bb->custom_tables['usermeta'] = $bb->custom_user_meta_table;
		}
	}

	// Check for older style wp_table_prefix
	// TODO: Completely remove old constants on version 1.0
	if ( $bb->wp_table_prefix = bb_get_option('wp_table_prefix') ) { // User has set old constant
		if ( !isset($bb->custom_tables) ) {
			$bb->custom_tables = array(
				'users'    => $bb->wp_table_prefix . 'users',
				'usermeta' => $bb->wp_table_prefix . 'usermeta'
			);
		} else {
			if ( !isset($bb->custom_tables['users']) ) // Don't stomp new setting style
				$bb->custom_tables['users'] = $bb->wp_table_prefix . 'users';
			if ( !isset($bb->custom_tables['usermeta']) )
				$bb->custom_tables['usermeta'] = $bb->wp_table_prefix . 'usermeta';
		}
	}

	// Check for older style user database
	// TODO: Completely remove old constants on version 1.0
	if ( !isset($bb->custom_databases) )
		$bb->custom_databases = array();
	if ( !isset($bb->custom_databases['user']) ) {
		if ( !$bb->user_bbdb_name = bb_get_option('user_bbdb_name') )
			if ( defined('USER_BBDB_NAME') ) // User has set old constant
				$bb->user_bbdb_name = USER_BBDB_NAME;
		if ( $bb->user_bbdb_name )
			$bb->custom_databases['user']['name'] = $bb->user_bbdb_name;

		if ( !$bb->user_bbdb_user = bb_get_option('user_bbdb_user') )
			if ( defined('USER_BBDB_USER') ) // User has set old constant
				$bb->user_bbdb_user = USER_BBDB_USER;
		if ( $bb->user_bbdb_user )
			$bb->custom_databases['user']['user'] = $bb->user_bbdb_user;

		if ( !$bb->user_bbdb_password = bb_get_option('user_bbdb_password') )
			if ( defined('USER_BBDB_PASSWORD') ) // User has set old constant
				$bb->user_bbdb_password = USER_BBDB_PASSWORD;
		if ( $bb->user_bbdb_password )
			$bb->custom_databases['user']['password'] = $bb->user_bbdb_password;

		if ( !$bb->user_bbdb_host = bb_get_option('user_bbdb_host') )
			if ( defined('USER_BBDB_HOST') ) // User has set old constant
				$bb->user_bbdb_host = USER_BBDB_HOST;
		if ( $bb->user_bbdb_host )
			$bb->custom_databases['user']['host'] = $bb->user_bbdb_host;

		if ( !$bb->user_bbdb_charset = bb_get_option('user_bbdb_charset') )
			if ( defined('USER_BBDB_CHARSET') ) // User has set old constant
				$bb->user_bbdb_charset = USER_BBDB_CHARSET;
		if ( $bb->user_bbdb_charset )
			$bb->custom_databases['user']['charset'] = $bb->user_bbdb_charset;

		if ( !$bb->user_bbdb_collate = bb_get_option('user_bbdb_collate') )
			if ( defined('USER_BBDB_COLLATE') ) // User has set old constant
				$bb->user_bbdb_collate = USER_BBDB_COLLATE;
		if ( $bb->user_bbdb_collate )
			$bb->custom_databases['user']['collate'] = $bb->user_bbdb_collate;

		if ( isset($bb->custom_tables['users']) )
			$bb->custom_tables['users'] = array('user', $bb->custom_tables['users']);
		if ( isset($bb->custom_tables['usermeta']) )
			$bb->custom_tables['usermeta'] = array('user', $bb->custom_tables['usermeta']);
	}
}

/* Forums */

function bb_get_forums_hierarchical( $root = 0, $depth = 0, $leaves = false, $_recursed = false ) {
	static $_leaves = false;
	$root = (int) $root;

	if ( false === $_leaves )
		$_leaves = $leaves ? $leaves : get_forums();

	if ( !$_leaves )
		return false;

	$branch = array();

	reset($_leaves);

	while ( list($l, $leaf) = each($_leaves) ) {
		if ( $root == $leaf->forum_parent ) {
			$new_root = (int) $leaf->forum_id;
			unset($_leaves[$l]);
			$branch[$new_root] = 1 == $depth ? true : bb_get_forums_hierarchical( $new_root, $depth - 1, false, true );
			reset($_leaves);
		}
	}

	if ( !$_recursed ) {
		foreach ( $_leaves as $leaf ) // Attach orphans to root
			$branch[$leaf->forum_id] = true;
		$_leaves = false;
		return ( empty($branch) ? false : $branch );
	}

	return $branch ? $branch : true;
}

function _bb_get_cached_data( $keys, $group, $callback ) {
	$return = array();
	foreach ( $keys as $key ) {
		// should use wp_cache_get_multi if available
		if ( false === $value = wp_cache_get( $key, $group ) )
			if ( !$value = call_user_func( $group, $key ) )
				continue;
		$return[$key] = $value;
	}
	return $return;
}

// 'where' arg provided for backward compatibility only
function get_forums( $args = null ) {
	global $bbdb;

	if ( is_numeric($args) ) {
		$args = array( 'child_of' => $args, 'hierarchical' => 1, 'depth' => 0 );
	} elseif ( is_callable($args) ) {
		$args = array( 'callback' => $args );
		if ( 1 < func_num_args() )
			$args['callback_args'] = func_get_arg(1);
	}

	$defaults = array( 'callback' => false, 'callback_args' => false, 'child_of' => 0, 'hierarchical' => 0, 'depth' => 0, 'cut_branch' => 0, 'where' => '' );
	$args = wp_parse_args( $args, $defaults );

	extract($args, EXTR_SKIP);
	$child_of = (int) $child_of;
	$hierarchical = 'false' === $hierarchical ? false : (bool) $hierarchical;
	$depth = (int) $depth;

	$where = apply_filters( 'get_forums_where', $where );
	$key = md5( serialize( $where ) ); // The keys that change the SQL query
	if ( false !== $forum_ids = wp_cache_get( $key, 'bb_forums' ) ) {
		$forums = _bb_get_cached_data( $forum_ids, 'bb_forum', 'get_forum' );
	} else {
		$forum_ids = array();
		$forums = array();
		foreach ( $_forums = (array) $bbdb->get_results("SELECT * FROM $bbdb->forums $where ORDER BY forum_order") as $f ) {
			$f = bb_append_meta( $f, 'forum' );
			$forums[(int) $f->forum_id] = $f;
			$forum_ids[] = (int) $f->forum_id;
			wp_cache_add( $f->forum_id, $f, 'bb_forum' );
			wp_cache_add( $f->forum_slug, $f->forum_id, 'bb_forum_slug' );
		}
		wp_cache_set( $key, $forum_ids, 'bb_forums' );
	}

	$forums = (array) apply_filters( 'get_forums', $forums );

	if ( $child_of || $hierarchical || $depth ) {
		$_forums = bb_get_forums_hierarchical( $child_of, $depth, $forums, true );

		if ( !is_array( $_forums ) )
			return false;

		$_forums = (array) bb_flatten_array( $_forums, $cut_branch );

		foreach ( array_keys($_forums) as $_id )
			$_forums[$_id] = $forums[$_id];

		$forums = $_forums;
	}

	if ( !is_callable($callback) )
		return $forums;

	if ( !is_array($callback_args) )
		$callback_args = array();

	foreach ( array_keys($forums) as $f ) :
		$_callback_args = $callback_args;
		array_push( $_callback_args, $forums[$f]->forum_id );
		if ( false == call_user_func_array( $callback, $_callback_args ) ) // $forum_id will be last arg;
			unset($forums[$f]);
	endforeach;
	return $forums;
}

function get_forum( $id ) {
	global $bbdb;

	if ( !is_numeric($id) ) {
		list($slug, $sql) = bb_get_sql_from_slug( 'forum', $id );
		$id = wp_cache_get( $slug, 'bb_forum_slug' );
	}

	// not else
	if ( is_numeric($id) ) {
		$id = (int) $id;
		$sql = "forum_id = $id";
	}

	if ( 0 === $id || !$sql )
		return false;

	// $where is NOT bbdb:prepared
	if ( $where = apply_filters( 'get_forum_where', '' ) ) {
		$forum = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->forums WHERE forum_id = %d", $id ) . " $where" );
		return bb_append_meta( $forum, 'forum' );
	}

	if ( is_numeric($id) && false !== $forum = wp_cache_get( $id, 'bb_forum' ) )
		return $forum;

	$forum = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->forums WHERE $sql", $id ) );
	$forum = bb_append_meta( $forum, 'forum' );
	wp_cache_set( $forum->forum_id, $forum, 'bb_forum' );
	wp_cache_add( $forum->forum_slug, $forum, 'bb_forum_slug' );

	return $forum;
}

/* Topics */

function get_topic( $id, $cache = true ) {
	global $bbdb;

	if ( !is_numeric($id) ) {
		list($slug, $sql) = bb_get_sql_from_slug( 'topic', $id );
		$id = wp_cache_get( $slug, 'bb_topic_slug' );
	}

	// not else
	if ( is_numeric($id) ) {
		$id = (int) $id;
		$sql = "topic_id = $id";
	}

	if ( 0 === $id || !$sql )
		return false;

	// &= not =&
	$cache &= 'AND topic_status = 0' == $where = apply_filters( 'get_topic_where', 'AND topic_status = 0' );

	if ( ( $cache || !$where ) && is_numeric($id) && false !== $topic = wp_cache_get( $id, 'bb_topic' ) )
		return $topic;

	// $where is NOT bbdb:prepared
	$topic = $bbdb->get_row( "SELECT * FROM $bbdb->topics WHERE $sql $where" );
	$topic = bb_append_meta( $topic, 'topic' );

	if ( $cache ) {
		wp_cache_set( $topic->topic_id, $topic, 'bb_topic' );
		wp_cache_add( $topic->topic_slug, $topic_id, 'bb_topic_slug' );
	}

	return $topic;
}

function get_latest_topics( $args = null ) {
	$defaults = array( 'forum' => false, 'page' => 1, 'exclude' => false, 'number' => false );
	if ( is_numeric( $args ) )
		$args = array( 'forum' => $args );
	else
		$args = wp_parse_args( $args ); // Make sure it's an array
	if ( 1 < func_num_args() )
		$args['page'] = func_get_arg(1);
	if ( 2 < func_num_args() )
		$args['exclude'] = func_get_arg(2);

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( $exclude ) {
		$exclude = '-' . str_replace(',', '-,', $exclude);
		$exclude = str_replace('--', '-', $exclude);
		if ( $forum )
			$forum = (string) $forum . ",$exclude";
		else
			$forum = $exclude;
	}

	$q = array('forum_id' => $forum, 'page' => $page, 'per_page' => $number);

	if ( is_front() )
		$q['sticky'] = '-2';
	elseif ( is_forum() || is_view() )
		$q['sticky'] = 0;

	// Last param makes filters back compat
	$query = new BB_Query( 'topic', $q, 'get_latest_topics' );
	return $query->results;
}

function get_sticky_topics( $forum = false, $display = 1 ) {
	if ( 1 != $display ) // Why is this even here?
		return false;

	$q = array(
		'forum_id' => $forum,
		'sticky' => is_front() ? 'super' : 'sticky'
	);

	$query = new BB_Query( 'topic', $q, 'get_sticky_topics' );
	return $query->results;
}

function get_recent_user_threads( $user_id ) {
	global $page;
	$q = array( 'page' => $page, 'topic_author' => $user_id, 'order_by' => 't.topic_start_time');

	$query = new BB_Query( 'topic', $q, 'get_recent_user_threads' );
	return $query->results;
}

function bb_insert_topic( $args = null ) {
	global $bbdb;

	if ( !$args = wp_parse_args( $args ) )
		return false;

	$fields = array_keys( $args );

	if ( isset($args['topic_id']) && false !== $args['topic_id'] ) {
		$update = true;
		if ( !$topic_id = (int) get_topic_id( $args['topic_id'] ) )
			return false;
		// Get from db, not cache.  Good idea?  Prevents trying to update meta_key names in the topic table (get_topic() returns appended topic obj)
		$topic = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->topics WHERE topic_id = %d", $topic_id ) );
		$defaults = get_object_vars( $topic );

		// Only update the args we passed
		$fields = array_intersect( $fields, array_keys($defaults) );
		if ( in_array( 'topic_poster', $fields ) )
			$fields[] = 'topic_poster_name';
		if ( in_array( 'topic_last_poster', $fields ) )
			$fields[] = 'topic_last_poster_name';
	} else {
		$update = false;

		$now = bb_current_time('mysql');
		$current_user_id = bb_get_current_user_info( 'id' );

		$defaults = array(
			'topic_id' => false, // accepts ids or slugs
			'topic_title' => '',
			'topic_slug' => '',
			'topic_poster' => $current_user_id, // accepts ids or names
			'topic_poster_name' => '', // useless
			'topic_last_poster' => $current_user_id,
			'topic_last_poster_name' => '', // useless
			'topic_start_time' => $now,
			'topic_time' => $now,
			'topic_open' => 1,
			'forum_id' => 0 // accepts ids or slugs
		);

		// Insert all args
		$fields = array_keys($defaults);
	}

	$defaults['tags'] = false; // accepts array or comma delimited string
	extract( wp_parse_args( $args, $defaults ) );
	unset($defaults['topic_id'], $defaults['tags']);

	if ( !$forum = get_forum( $forum_id ) )
		return false;
	$forum_id = (int) $forum->forum_id;

	if ( !$user = bb_get_user( $topic_poster ) )
		return false;
	$topic_poster = $user->ID;
	$topic_poster_name = $user->user_login;

	if ( !$last_user = bb_get_user( $topic_last_poster ) )
		return false;
	$topic_last_poster = $last_user->ID;
	$topic_last_poster_name = $last_user->user_login;

	if ( in_array( 'topic_title', $fields ) ) {
		$topic_title = apply_filters( 'pre_topic_title', $topic_title, $topic_id );
		if ( strlen($topic_title) < 1 )
			return false;
	}

	if ( in_array( 'topic_slug', $fields ) ) {
		$slug_sql = $update ?
			"SELECT topic_slug FROM $bbdb->topics WHERE topic_slug = %s AND topic_id != %d" :
			"SELECT topic_slug FROM $bbdb->topics WHERE topic_slug = %s";

		$topic_slug = $_topic_slug = bb_slug_sanitize( $topic_slug ? $topic_slug : $topic_title );
		if ( strlen( $_topic_slug ) < 1 )
			$topic_slug = $_topic_slug = '0';

		while ( is_numeric($topic_slug) || $existing_slug = $bbdb->get_var( $bbdb->prepare( $slug_sql, $topic_slug, $topic_id ) ) )
			$topic_slug = bb_slug_increment( $_topic_slug, $existing_slug );
	}

	if ( $update ) {
		$bbdb->update( $bbdb->topics, compact( $fields ), compact( 'topic_id' ) );
		wp_cache_delete( $topic_id, 'bb_topic' );
		if ( in_array( 'topic_slug', $fields ) )
			wp_cache_delete( $topic->topic_slug, 'bb_topic_slug' );
		do_action( 'bb_update_topic', $topic_id );
	} else {
		$bbdb->insert( $bbdb->topics, compact( $fields ) );
		$topic_id = $bbdb->insert_id;
		$bbdb->query( $bbdb->prepare( "UPDATE $bbdb->forums SET topics = topics + 1 WHERE forum_id = %d", $forum_id ) );
		wp_cache_delete( $forum_id, 'bb_forum' );
		wp_cache_flush( 'bb_forums' );
		do_action( 'bb_new_topic', $topic_id );
	}

	if ( !empty( $tags ) )
		bb_add_topic_tags( $topic_id, $tags );

	do_action( 'bb_insert_topic', $topic_id, $args, compact( array_keys($args) ) ); // topic_id, what was passed, what was used

	return $topic_id;
}

// Deprecated: expects $title to be pre-escaped
function bb_new_topic( $title, $forum, $tags = '' ) {
	$title = stripslashes( $title );
	$tags  = stripslashes( $tags );
	$forum = (int) $forum;
	return bb_insert_topic( array( 'topic_title' => $title, 'forum_id' => $forum, 'tags' => $tags ) );
}

// Deprecated: expects $title to be pre-escaped
function bb_update_topic( $title, $topic_id ) {
	$title = stripslashes( $title );
	return bb_insert_topic( array( 'topic_title' => $title, 'topic_id' => $topic_id ) );
}

function bb_delete_topic( $topic_id, $new_status = 0 ) {
	global $bbdb;
	$topic_id = (int) $topic_id;
	add_filter( 'get_topic_where', 'no_where' );
	if ( $topic = get_topic( $topic_id ) ) {
		$new_status = (int) $new_status;
		$old_status = (int) $topic->topic_status;
		if ( $new_status == $old_status )
			return;

		if ( 0 != $old_status && 0 == $new_status )
			add_filter('get_thread_where', 'no_where');
		$poster_ids = array();
		foreach ( get_thread( $topic_id, array( 'per_page' => -1, 'order' => 'DESC' ) ) as $post ) {
			_bb_delete_post( $post->post_id, $new_status );
			$poster_ids[] = $post->poster_id;
		}

		foreach ( array_unique( $poster_ids ) as $id )
			if ( $user = bb_get_user( $id ) )
				bb_update_usermeta( $user->ID, $bbdb->prefix . 'topics_replied', ( $old_status ? $user->topics_replied + 1 : $user->topics_replied - 1 ) );

		if ( $ids = $bbdb->get_col( "SELECT user_id, meta_value FROM $bbdb->usermeta WHERE meta_key = 'favorites' and FIND_IN_SET('$topic_id', meta_value) > 0" ) )
			foreach ( $ids as $id )
				bb_remove_user_favorite( $id, $topic_id );

		if ( $new_status ) {
			bb_remove_topic_tags( $topic_id );
			$bbdb->update( $bbdb->topics, array( 'topic_status' => $new_status, 'tag_count' => 0 ), compact( 'topic_id' ) );
			$bbdb->query( $bbdb->prepare(
				"UPDATE $bbdb->forums SET topics = topics - 1, posts = posts - %d WHERE forum_id = %d", $topic->topic->posts, $topic->forum_id
			) );
		} else {
			$bbdb->update( $bbdb->topics, array( 'topic_status' => $new_status ), compact( 'topic_id' ) );
			$topic_posts = (int) $bbdb->get_var( $bbdb->prepare(
				"SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = %d AND post_status = 0", $topic_id
			) );
			$all_posts = (int) $bbdb->get_var( $bbdb->prepare(
				"SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = %d", $topic_id
			) );
			bb_update_topicmeta( $topic_id, 'deleted_posts', $all_posts - $topic_posts );
			$bbdb->query( $bbdb->prepare(
				"UPDATE $bbdb->forums SET topics = topics + 1, posts = posts + %d WHERE forum_id = %d", $topic_posts, $topic->forum_id
			) );
			$bbdb->update( $bbdb->topics, compact( 'topic_posts' ), compact( 'topic_id' ) );
			bb_topic_set_last_post( $topic_id );
			update_post_positions( $topic_id );
		}
			
		do_action( 'bb_delete_topic', $topic_id, $new_status, $old_status );
		wp_cache_delete( $topic_id, 'bb_topic' );
		wp_cache_delete( $topic->topic_slug, 'bb_topic_slug' );
		wp_cache_delete( $topic_id, 'bb_thread' );
		return $topic_id;
	} else {
		return false;
	}
}

function bb_move_topic( $topic_id, $forum_id ) {
	global $bbdb;
	$topic = get_topic( $topic_id );
	$forum = get_forum( $forum_id );
	$topic_id = (int) $topic->topic_id;
	$forum_id = (int) $forum->forum_id;

	if ( $topic && $forum && $topic->forum_id != $forum_id ) {
		$bbdb->update( $bbdb->posts, compact( 'forum_id' ), compact( 'topic_id' ) );
		$bbdb->update( $bbdb->topics, compact( 'forum_id' ), compact( 'topic_id' ) );
		$bbdb->query( $bbdb->prepare(
			"UPDATE $bbdb->forums SET topics = topics + 1, posts = posts + %d WHERE forum_id = %d", $topic->topic_posts, $forum_id
		) );
		$bbdb->query( $bbdb->prepare( 
			"UPDATE $bbdb->forums SET topics = topics - 1, posts = posts - %d WHERE forum_id = %d", $topic->topic_posts, $topic->forum_id
		) );
		wp_cache_delete( $topic_id, 'bb_topic' );
		wp_cache_delete( $forum_id, 'bb_forum' );
		wp_cache_flush( 'bb_forums' );
		return $forum_id;
	}
	return false;
}

function bb_topic_set_last_post( $topic_id ) {
	global $bbdb;
	$topic_id = (int) $topic_id;
	$old_post = $bbdb->get_row( $bbdb->prepare(
		"SELECT post_id, poster_id, post_time FROM $bbdb->posts WHERE topic_id = %d AND post_status = 0 ORDER BY post_time DESC LIMIT 1", $topic_id
	) );
	$old_poster = bb_get_user( $old_post->poster_id );
	return $bbdb->update( $bbdb->topics, array( 'topic_time' => $old_post->post_time, 'topic_last_poster' => $old_post->poster_id, 'topic_last_poster_name' => $old_poster->login_name, 'topic_last_post_id' => $old_post->post_id ), compact( 'topic_id' ) );
}	

function bb_close_topic( $topic_id ) {
	global $bbdb;
	$topic_id = (int) $topic_id;
	wp_cache_delete( $topic_id, 'bb_topic' );
	$r = $bbdb->update( $bbdb->topics, array( 'topic_open' => 0 ), compact( 'topic_id' ) );
	do_action('close_topic', $topic_id, $r);
	return $r;
}

function bb_open_topic( $topic_id ) {
	global $bbdb;
	$topic_id = (int) $topic_id;
	wp_cache_delete( $topic_id, 'bb_topic' );
	$r = $bbdb->update( $bbdb->topics, array( 'topic_open' => 1 ), compact( 'topic_id' ) );
	do_action('open_topic', $topic_id, $r);
	return $r;
}

function bb_stick_topic( $topic_id, $super = 0 ) {
	global $bbdb;
	$topic_id = (int) $topic_id;
	$stick = 1 + abs((int) $super);
	wp_cache_delete( $topic_id, 'bb_topic' );
	$r = $bbdb->update( $bbdb->topics, array( 'topic_sticky' => $stick ), compact( 'topic_id' ) );
	do_action('stick_topic', $topic_id, $r);
}

function bb_unstick_topic( $topic_id ) {
	global $bbdb;
	$topic_id = (int) $topic_id;
	wp_cache_delete( $topic_id, 'bb_topic' );
	$r = $bbdb->update( $bbdb->topics, array( 'topic_sticky' => 0 ), compact( 'topic_id' ) );
	do_action('unstick_topic', $topic_id, $r);
	return $r;
}

function topic_is_open( $topic_id = 0 ) {
	$topic = get_topic( get_topic_id( $topic_id ) );
	return 1 == $topic->topic_open;
}

function topic_is_sticky( $topic_id = 0 ) {
	$topic = get_topic( get_topic_id( $topic_id ) );
	return '0' !== $topic->topic_sticky;
}

/* Thread */ // Thread, topic?  Guh-wah?  TODO: consistency in nomenclature

function get_thread( $topic_id, $args = null ) {
	$defaults = array( 'page' => 1, 'order' => 'ASC' );
	if ( is_numeric( $args ) )
		$args = array( 'page' => $args );
	if ( @func_get_arg(2) )
		$defaults['order'] = 'DESC';

	$args = wp_parse_args( $args, $defaults );
	$args['topic_id'] = $topic_id;

	$query = new BB_Query( 'post', $args, 'get_thread' );
	return $query->results;
}

// deprecated
function get_thread_post_ids( $topic_id ) {
	$return = array( 'post' => array(), 'poster' => array() );
	foreach ( get_thread( $topic_id, array( 'per_page' => -1 ) ) as $post ) {
		$return['post'][] = $post->post_id;
		$return['poster'][] = $post->poster_id;
	}
	return $return;
}

/* Posts */

function bb_get_post( $post_id ) {
	global $bbdb;
	$post_id = (int) $post_id;
	if ( false === $post = wp_cache_get( $post_id, 'bb_post' ) ) {
		$post = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->posts WHERE post_id = %d", $post_id ) );
		$post = bb_append_meta( $post, 'post' );
		wp_cache_set( $post_id, $post, 'bb_post' );
	}
	return $post;
}

// NOT bbdb::prepared
function bb_is_first( $post_id ) { // First post in thread
	global $bbdb;
	if ( !$bb_post = bb_get_post( $post_id ) )
		return false;
	$post_id = (int) $bb_post->post_id;
	$topic_id = (int) $bb_post->topic_id;

	$where = apply_filters('bb_is_first_where', 'AND post_status = 0');
	$first_post = (int) $bbdb->get_var("SELECT post_id FROM $bbdb->posts WHERE topic_id = $topic_id $where ORDER BY post_id ASC LIMIT 1");

	return $post_id == $first_post;
}

// Globalizes the result.
function bb_get_first_post( $_topic = false, $author_cache = true ) {
	global $topic, $bb_first_post_cache, $bb_post;
	if ( !$_topic )
		$topic_id = (int) $topic->topic_id;
	else if ( is_object($_topic) )
		$topic_id = (int) $_topic->topic_id;
	else if ( is_numeric($_topic) )
		$topic_id = (int) $_topic;

	if ( !$topic_id )
		return false;

	if ( isset($bb_first_post_cache[$topic_id]) ) {
		$post = bb_get_post( $bb_first_post_cache[$topic_id] );
	} else {
		$first_posts = bb_cache_first_posts( array($topic_id), $author_cache );
		if ( isset($first_posts[$topic_id]) )
			$post = $first_posts[$topic_id];
	}

	if ( $post ) {
		$bb_post = $post;
		return $bb_post;
	}

	return false;
}

// Ignore the return value.  Cache first posts with this function and use bb_get_first_post to grab each.
// NOT bbdb::prepared
function bb_cache_first_posts( $_topics = false, $author_cache = true ) {
	global $topics, $bb_first_post_cache, $bbdb;
	if ( !$_topics )
		$_topics =& $topics;
	if ( !is_array($_topics) )
		return false;

	$topic_ids = array();
	foreach ( $_topics as $topic )
		if ( is_object($topic) )
			$topic_ids[] = (int) $topic->topic_id;
		else if ( is_numeric($topic) )
			$topic_ids[] = (int) $topic;

	$_topic_ids = join(',', $topic_ids);

	$posts = (array) bb_cache_posts( "SELECT * FROM $bbdb->posts WHERE topic_id IN ($_topic_ids) AND post_position = 1 AND post_status = 0" );

	$first_posts = array();
	foreach ( $posts as $post ) {
		$bb_first_post_cache[(int) $post->topic_id] = (int) $post->post_id;
		$first_posts[(int) $post->topic_id] = $post;
	}

	if ( $author_cache )
		post_author_cache( $posts );

	return $first_posts;
}

function bb_cache_posts( $query ) {
	global $bbdb;
	if ( $posts = (array) $bbdb->get_results( $query ) )
		foreach( $posts as $bb_post )
			wp_cache_add( $bb_post->post_id, $bb_post, 'bb_post' );
	return $posts;
}

// Globalizes the result
function bb_get_last_post( $_topic = false, $author_cache = true ) {
	global $topic, $bb_post;
	if ( !$_topic )
		$topic_id = (int) $topic->topic_id;
	else if ( is_object($_topic) )
		$topic_id = (int) $_topic->topic_id;
	else if ( is_numeric($_topic) )
		$topic_id = (int) $_topic;

	if ( !$topic_id )
		return false;

	$_topic = get_topic( $topic_id );

	if ( $post = bb_get_post( $_topic->topic_last_post_id ) ) {
		if ( $author_cache )
			post_author_cache( array($post) );
		$bb_post = $post;
	}

	return $post;
}

// No return value. Cache last posts with this function and use bb_get_last_post to grab each.
// NOT bbdb::prepared
function bb_cache_last_posts( $_topics = false, $author_cache = true ) {
	global $topics, $bbdb;
	if ( !$_topics )
		$_topics =& $topics;
	if ( !is_array($_topics) )
		return false;

	$last_post_ids = array();
	$topic_ids = array();
	foreach ( $_topics as $topic )
		if ( is_object($topic) )
			$last_post_ids[] = (int) $topic->topic_last_post_id;
		else if ( is_numeric($topic) && false !== $cached_topic = wp_cache_get( $topic, 'bb_topic' ) )
			$last_post_ids[] = (int) $cached_topic->topic_last_post_id;
		else if ( is_numeric($topic) )
			$topic_ids[] = (int) $topic;

	if ( !empty($last_post_ids) ) {
		$_last_post_ids = join(',', $last_post_ids);
		$posts = (array) bb_cache_posts( "SELECT * FROM $bbdb->posts WHERE post_id IN ($_last_post_ids) AND post_status = 0" );
		if ( $author_cache )
			post_author_cache( $posts );
	}

	if ( !empty($topic_ids) ) {	
		$_topic_ids = join(',', $topic_ids);
		$posts = (array) bb_cache_posts( "SELECT p.* FROM $bbdb->topics AS t LEFT JOIN $bbdb->posts AS p ON ( t.topic_last_post_id = p.post_id ) WHERE t.topic_id IN ($_topic_ids) AND p.post_status = 0" );
		if ( $author_cache )
			post_author_cache( $posts );
	}
}

// NOT bbdb::prepared
function bb_cache_post_topics( $posts ) {
	global $bbdb;

	if ( !$posts )
		return;

	$topic_ids = array();
	foreach ( $posts as $post )
		if ( false === wp_cache_get( $post->topic_id, 'bb_topic' ) )
			$topic_ids[] = (int) $post->topic_id;

	if ( !$topic_ids )
		return;

	$topic_ids = join(',', $topic_ids);

	if ( $topics = $bbdb->get_results( "SELECT * FROM $bbdb->topics WHERE topic_id IN($topic_ids)" ) )
		bb_append_meta( $topics, 'topic' );
}

function get_latest_posts( $limit = 0, $page = 1 ) {
	$limit = (int) $limit;
	$post_query = new BB_Query( 'post', array( 'page' => $page, 'per_page' => $limit ), 'get_latest_posts' );
	return $post_query->results;
}

function get_latest_forum_posts( $forum_id, $limit = 0, $page = 1 ) {
	$forum_id = (int) $forum_id;
	$limit    = (int) $limit;
	$post_query = new BB_Query( 'post', array( 'forum_id' => $forum_id, 'page' => $page, 'per_page' => $limit ), 'get_latest_forum_posts' );
	return $post_query->results;
}

function bb_insert_post( $args = null ) {
	global $bbdb, $bb_current_user;

	if ( !$args = wp_parse_args( $args ) )
		return false;

	$fields = array_keys( $args );

	if ( isset($args['post_id']) && false !== $args['post_id'] ) {
		$update = true;
		if ( !$post_id = (int) get_post_id( $args['post_id'] ) )
			return false;
		// Get from db, not cache.  Good idea?
		$post = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->posts WHERE post_id = %d", $post_id ) );
		$defaults = get_object_vars( $post );

		// Only update the args we passed
		$fields = array_intersect( $fields, array_keys($defaults) );
		if ( in_array( 'topic_id', $fields ) )
			$fields[] = 'forum_id';

		// No need to run filters if these aren't changing
		// bb_new_post() and bb_update_post() will always run filters
		$run_filters = (bool) array_intersect( array( 'post_status', 'post_text' ), $fields );
	} else {
		$update = false;
		$now = bb_current_time( 'mysql' );
		$current_user_id = bb_get_current_user_info( 'id' );
		$ip_address = $_SERVER['REMOTE_ADDR'];

		$defaults = array(
			'post_id' => false,
			'topic_id' => 0,
			'post_text' => '',
			'post_time' => $now,
			'poster_id' => $current_user_id, // accepts ids or names
			'poster_ip' => $ip_address,
			'post_status' => 0, // use bb_delete_post() instead
			'post_position' => false
		);

		// Insert all args
		$fields = array_keys($defaults);
		$fields[] = 'forum_id';

		$run_filters = true;
	}

	$defaults['throttle'] = true;
	extract( wp_parse_args( $args, $defaults ) );

	if ( !$topic = get_topic( $topic_id ) )
		return false;

	if ( !$user = bb_get_user( $poster_id ) )
		return false;

	$topic_id = (int) $topic->topic_id;
	$forum_id = (int) $topic->forum_id;

	if ( $run_filters && !$post_text = apply_filters('pre_post', $post_text, $post_id, $topic_id) )
		return false;

	if ( $update ) // Don't change post_status with this function.  Use bb_delete_post().
		$post_status = $post->post_status;

	if ( $run_filters )
		$post_status = (int) apply_filters('pre_post_status', $post_status, $post_id, $topic_id);

	if ( false === $post_position )
		$post_position = $topic_posts = intval( ( 0 == $post_status ) ? $topic->topic_posts + 1 : $topic->topic_posts );

	unset($defaults['post_id'], $defaults['throttle']);

	if ( $update ) {
		$bbdb->update( $bbdb->posts, compact( $fields ), compact( 'post_id' ) );
	} else {
		$bbdb->insert( $bbdb->posts, compact( $fields ) );
		$post_id = $topic_last_post_id = (int) $bbdb->insert_id;

		if ( 0 == $post_status ) {
			$topic_time = $post_time;
			$topic_last_poster = $poster_id;
			$topic_last_poster_name = $user->user_login;

			$bbdb->query( $bbdb->prepare( "UPDATE $bbdb->forums SET posts = posts + 1 WHERE forum_id = %d", $topic->forum_id ) );
			$bbdb->update(
				$bbdb->topics,
				compact( 'topic_time', 'topic_last_poster', 'topic_last_poster_name', 'topic_last_post_id', 'topic_posts' ),
				compact ( 'topic_id' )
			);

			$query = new BB_Query( 'post', array( 'post_author_id' => $poster_id, 'topic_id' => $topic_id, 'post_id' => "-$post_id" ) );
			if ( !$query->results )
				bb_update_usermeta( $poster_id, $bbdb->prefix . 'topics_replied', $user->topics_replied + 1 );
		} else {
			bb_update_topicmeta( $topic->topic_id, 'deleted_posts', isset($topic->deleted_posts) ? $topic->deleted_posts + 1 : 1 );
		}
	}
	
	if ( $throttle && !bb_current_user_can( 'throttle' ) )
		bb_update_usermeta( $poster_id, 'last_posted', time() );

	wp_cache_delete( $topic_id, 'bb_topic' );
	wp_cache_delete( $topic_id, 'bb_thread' );
	wp_cache_delete( $forum_id, 'bb_forum' );
	wp_cache_flush( 'bb_forums' );

	if ( $update ) // fire actions after cache is flushed
		do_action( 'bb_update_post', $post_id );
	else
		do_action( 'bb_new_post', $post_id );

	do_action( 'bb_insert_post', $post_id, $args, compact( array_keys($args) ) ); // post_id, what was passed, what was used

	return $post_id;
}

// Deprecated: expects $post_text to be pre-escaped
function bb_new_post( $topic_id, $post_text ) {
	$post_text = stripslashes( $post_text );
	return bb_insert_post( compact( 'topic_id', 'post_text' ) );
}

// Deprecated: expects $post_text to be pre-escaped
function bb_update_post( $post_text, $post_id, $topic_id ) {
	$post_text = stripslashes( $post_text );
	return bb_insert_post( compact( 'post_text', 'post_id', 'topic_id' ) );
}

function update_post_positions( $topic_id ) {
	global $bbdb;
	$topic_id = (int) $topic_id;
	$posts = get_thread( $topic_id, array( 'per_page' => '-1' ) );
	if ( $posts ) {
		foreach ( $posts as $i => $post )
			$bbdb->query( $bbdb->prepare( "UPDATE $bbdb->posts SET post_position = %d WHERE post_id = %d", $i + 1, $post->post_id ) );
		wp_cache_delete( $topic_id, 'bb_thread' );
		return true;
	} else {
		return false;
	}
}

function bb_delete_post( $post_id, $new_status = 0 ) {
	global $bbdb, $topic, $bb_post;
	$post_id = (int) $post_id;
	$bb_post    = bb_get_post ( $post_id );
	$new_status = (int) $new_status;
	$old_status = (int) $bb_post->post_status;
	add_filter( 'get_topic_where', 'no_where' );
	$topic   = get_topic( $bb_post->topic_id );
	$topic_id = (int) $topic->topic_id;

	if ( $bb_post ) {
		$uid = (int) $bb_post->poster_id;
		if ( $new_status == $old_status )
			return;
		_bb_delete_post( $post_id, $new_status );
		if ( 0 == $old_status ) {
			bb_update_topicmeta( $topic_id, 'deleted_posts', $topic->deleted_posts + 1 );
			$bbdb->query( $bbdb->prepare( "UPDATE $bbdb->forums SET posts = posts - 1 WHERE forum_id = %d", $topic->forum_id ) );
		} else if ( 0 == $new_status ) {
			bb_update_topicmeta( $topic_id, 'deleted_posts', $topic->deleted_posts - 1 );
			$bbdb->query( $bbdb->prepare( "UPDATE $bbdb->forums SET posts = posts + 1 WHERE forum_id = %d", $topic->forum_id ) );
		}
		$posts = (int) $bbdb->get_var( $bbdb->prepare( "SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = %d AND post_status = 0", $topic_id ) );
		$bbdb->update( $bbdb->topics, array( 'topic_posts' => $posts ), compact( 'topic_id' ) );

		if ( 0 == $posts ) {
			if ( 0 == $topic->topic_status || 1 == $new_status )
				bb_delete_topic( $topic_id, $new_status );
		} else {
			if ( 0 != $topic->topic_status ) {
				$bbdb->update( $bbdb->topics, array( 'topic_status' => 0 ), compact( 'topic_id' ) );
				$bbdb->query( $bbdb->prepare( "UPDATE $bbdb->forums SET topics = topics + 1 WHERE forum_id = %d", $topic->forum_id ) );
			}
			bb_topic_set_last_post( $topic_id );
			update_post_positions( $topic_id );
		}
		$user = bb_get_user( $uid );

		$user_posts = new BB_Query( 'post', array( 'post_author_id' => $user->ID, 'topic_id' => $topic_id ) );
		if ( $new_status && !$user_posts->results )
			bb_update_usermeta( $user->ID, $bbdb->prefix . 'topics_replied', $user->topics_replied - 1 );
		wp_cache_delete( $topic_id, 'bb_topic' );
		wp_cache_delete( $topic_id, 'bb_thread' );
		wp_cache_flush( 'bb_forums' );
		do_action( 'bb_delete_post', $post_id, $new_status, $old_status );
		return $post_id;
	} else {
		return false;
	}
}

function _bb_delete_post( $post_id, $post_status ) {
	global $bbdb;
	$post_id = (int) $post_id;
	$post_status = (int) $post_status;
	$bbdb->update( $bbdb->posts, compact( 'post_status' ), compact( 'post_id' ) );
}

function topics_replied_on_undelete_post( $post_id ) {
	global $bbdb;
	$bb_post = bb_get_post( $post_id );
	$topic = get_topic( $bb_post->topic_id );

	$user_posts = new BB_Query( 'post', array( 'post_author_id' => $bb_post->poster_id, 'topic_id' => $topic->topic_id ) );

	if ( 1 == count($user_posts) && $user = bb_get_user( $bb_post->poster_id ) )
		bb_update_usermeta( $user->ID, $bbdb->prefix . 'topics_replied', $user->topics_replied + 1 );
}

function post_author_cache($posts) {
	if ( !$posts )
		return;

	$ids = array();
	foreach ($posts as $bb_post)
		if ( 0 != $bb_post->poster_id && false === wp_cache_get( $bb_post->poster_id, 'users' ) ) // Don't cache what we already have
			$ids[] = $bb_post->poster_id;

	if ( $ids )
		bb_cache_users(array_unique($ids), false); // false since we've already checked for soft cached data.
}

// These two filters are lame.  It'd be nice if we could do this in the query parameters
function get_recent_user_replies_fields( $fields ) {
	return $fields . ', MAX(post_time) as post_time';
}

function get_recent_user_replies_group_by() {
	return 'p.topic_id';
}

function get_recent_user_replies( $user_id ) {
	global $bbdb;
	$user_id = (int) $user_id;

	$post_query = new BB_Query( 'post', array( 'post_author_id' => $user_id, 'order_by' => 'post_time' ), 'get_recent_user_replies' );

	return $post_query->results;
}

/* Tags */

/**
 * bb_add_topic_tag() - Adds a single tag to a topic.
 *
 * @param int $topic_id
 * @param string $tag The (unsanitized) full name of the tag to be added
 * @return int|bool The TT_ID of the new bb_topic_tag or false on failure
 */
function bb_add_topic_tag( $topic_id, $tag ) {
	$tt_ids = bb_add_topic_tags( $topic_id, $tag );
	if ( is_array( $tt_ids ) )
		return $tt_ids[0];
	return false;
}

/**
 * bb_add_topic_tag() - Adds a multiple tags to a topic.
 *
 * @param int $topic_id
 * @param array|string $tags The (unsanitized) full names of the tag to be added.  CSV or array.
 * @return array|bool The TT_IDs of the new bb_topic_tags or false on failure
 */
function bb_add_topic_tags( $topic_id, $tags ) {
	global $wp_taxonomy_object;
	$topic_id = (int) $topic_id;
	if ( !$topic = get_topic( $topic_id ) )
		return false;
	if ( !bb_current_user_can( 'add_tag_to', $topic_id ) )
		return false;

	$user_id = bb_get_current_user_info( 'id' );

	if ( !is_array( $tags ) )
		$tags = explode(',', (string) $tags);

	$tt_ids = $wp_taxonomy_object->set_object_terms( $topic->topic_id, $tags, 'bb_topic_tag', array( 'append' => true, 'user_id' => $user_id ) );

	if ( is_array($tt_ids) ) {
		foreach ( $tt_ids as $tt_id )
			do_action('bb_tag_added', $tt_id, $user_id, $topic_id);
		return $tt_ids;
	}
	return false;
}

/**
 * bb_create_tag() - Creates a single bb_topic_tag.
 *
 * @param string $tag The (unsanitized) full name of the tag to be created
 * @return int|bool The TT_ID of the new bb_topic_tags or false on failure
 */
function bb_create_tag( $tag ) {
	global $wp_taxonomy_object;

	if ( list($term_id, $tt_id) = $wp_taxonomy_object->is_term( $tag, 'bb_topic_tag' ) )
		return $tt_id;

	list($term_id, $tt_id) = $wp_taxonomy_object->insert_term( $tag, 'bb_topic_tag' );

	if ( is_wp_error($term_id) || is_wp_error($tt_id) || !$tt_id )
		return false;

	return $tt_id;
}

/**
 * bb_remove_topic_tag() - Removes a single bb_topic_tag by a user from a topic.
 *
 * @param int $tt_id The TT_ID of the bb_topic_tag to be removed
 * @param int $user_id
 * @param int $topic_id
 * @return array|false The TT_IDs of the users bb_topic_tags on that topic or false on failure
 */
function bb_remove_topic_tag( $tt_id, $user_id, $topic_id ) {
	global $wp_taxonomy_object;
	$tt_id   = (int) $tt_id;
	$user_id  = (int) $user_id;
	$topic_id = (int) $topic_id;
	if ( !$topic = get_topic( $topic_id ) )
		return false;
	if ( !bb_current_user_can( 'edit_tag_by_on', $user_id, $topic_id ) )
		return false;

	do_action('bb_pre_tag_removed', $tt_id, $user_id, $topic_id);
	$current_tag_ids = $wp_taxonomy_object->get_object_terms( $topic_id, 'bb_topic_tag', array( 'user_id' => $user_id, 'fields' => 'tt_ids' ) );
	if ( !is_array($current_tag_ids) )
		return false;

	$current_tag_ids = array_map( 'int_val', $current_tag_ids );

	if ( false === $pos = array_search( $current_tag_ids, $tt_id ) )
		return false;

	unset($current_tag_ids[$pos]);

	$return = $wp_taxonomy_object->set_object_terms( $topic_id, 'bb_topic_tag', array_values($current_tag_ids), array( 'user_id' => $user_id ) );
	if ( is_wp_error( $return ) )
		return false;
	return $return;
}

/**
 * bb_remove_topic_tag() - Removes all bb_topic_tags from a topic.
 *
 * @param int $topic_id
 * @return bool
 */
function bb_remove_topic_tags( $topic_id ) {
	global $wp_taxonomy_object;
	$topic_id = (int) $topic_id;
	if ( !$topic_id || !get_topic( $topic_id ) )
		return false;

	do_action( 'bb_pre_remove_topic_tags', $topic_id );

	$wp_taxonomy_object->delete_object_term_relationships( $topic_id, 'bb_topic_tag' );
	return true;
}

/**
 * bb_destroy_tag() - Completely removes a bb_topic_tag.
 *
 * @param int $tt_id The TT_ID of the tag to destroy
 * @return bool
 */
function bb_destroy_tag( $tt_id, $recount_topics = true ) {
	global $wp_taxonomy_object;

	$tt_id = (int) $tt_id;

	if ( !$tag = bb_get_tag( $tt_id ) )
		return false;

	$return = $wp_taxonomy_object->delete_term( $tag->term_id, 'bb_topic_tag' );

	if ( is_wp_error($return) )
		return false;

	return $return;
}

/**
 * bb_get_tag_id() - Returns the id of the specified or global tag.
 *
 * @param mixed $id The TT_ID, tag name of the desired tag, or 0 for the global tag
 * @return int 
 */
function bb_get_tag_id( $id = 0 ) {
	global $tag;
	if ( $id ) {
		$_tag = bb_get_tag( $id );
	} else {
		$_tag =& $tag;
	}
	return (int) $_tag->tag_id;
}

/**
 * bb_get_tag() - Returns the specified tag.  If $user_id and $topic_id are passed, will check to see if that tag exists on that topic by that user.
 *
 * @param mixed $id The TT_ID or tag name of the desired tag
 * @param int $user_id (optional)
 * @param int $topic_id (optional)
 * @return object Term object (back-compat)
 */
function bb_get_tag( $id, $user_id = 0, $topic_id = 0 ) {
	global $wp_taxonomy_object;
	$user_id  = (int) $user_id;
	$topic_id = (int) $topic_id;

	$term = false;
	if ( is_numeric( $id ) ) {
		$tt_id = (int) $id;
	} else {
		if ( !$term = $wp_taxonomy_object->get_term_by( 'slug', $id, 'bb_topic_tag' ) )
			return false;
		$tt_id = (int) $term->term_taxonomy_id;
	}

	if ( $user_id && $topic_id ) {
		$tt_ids = $wp_taxonomy_object->get_object_terms( $topic_id, 'bb_topic_tag', array( 'user_id' => $user_id, 'fields' => 'tt_ids' ) );
		if ( !in_array( $tt_id, $tt_ids ) )
			return false;
	}

	if ( !$term )
		$term = $wp_taxonomy_object->get_term_by( 'tt_id', $tt_id, 'bb_topic_tag' );

	_bb_make_tag_compat( $term );

	return $term;
}

/**
 * bb_get_topic_tags() - Returns all of the bb_topic_tags associated with the specified topic.
 *
 * @param int $topic_id
 * @param mixed $args
 * @return array|false Term objects (back-compat), false on failure
 */
function bb_get_topic_tags( $topic_id = 0, $args = null ) {
	global $wp_taxonomy_object;

	if ( !$topic = get_topic( get_topic_id( $topic_id ) ) )
		return false;

	$topic_id = (int) $topic->topic_id;
	
	$terms = $wp_taxonomy_object->get_object_terms( (int) $topic->topic_id, 'bb_topic_tag', $args );
	if ( is_wp_error( $terms ) )
		return false;

	for ( $i = 0; isset($terms[$i]); $i++ )
		_bb_make_tag_compat( $terms[$i] );

	return $terms;
}

function bb_get_user_tags( $topic_id, $user_id ) {
	$tags = bb_get_topic_tags( $topic_id );
	if ( !is_array( $tags ) )
		return;
	$user_tags = array();

	foreach ( $tags as $tag ) :
		if ( $tag->user_id == $user_id )
			$user_tags[] = $tag;
	endforeach;
	return $user_tags;
}

function bb_get_other_tags( $topic_id, $user_id ) {
	$tags = bb_get_topic_tags( $topic_id );
	if ( !is_array( $tags ) )
		return;
	$other_tags = array();

	foreach ( $tags as $tag ) :
		if ( $tag->user_id != $user_id )
			$other_tags[] = $tag;
	endforeach;
	return $other_tags;
}

function bb_get_public_tags( $topic_id ) {
	$tags = bb_get_topic_tags( $topic_id );
	if ( !is_array( $tags ) )
		return;
	$used_tags   = array();
	$public_tags = array();

	foreach ( $tags as $tag ) :
		if ( !in_array($tag->tag_id, $used_tags) ) :
			$public_tags[] = $tag;
			$used_tags[]   = $tag->tag_id;
		endif;
	endforeach;
	return $public_tags;
}

function bb_get_tagged_topic_ids( $tag_id ) {
	global $wp_taxonomy_object, $tagged_topic_count;
	
	if ( $topic_ids = (array) $wp_taxonomy_object->get_objects_in_term( $tag_id, 'bb_topic_tag', array( 'field' => 'tt_id' ) ) ) {
		$tagged_topic_count = count($topic_ids);
		return apply_filters('get_tagged_topic_ids', $topic_ids);
	} else {
		$tagged_topic_count = 0;
		return false;
	}
}

function get_tagged_topics( $tag_id, $page = 1 ) {
	$query = new BB_Query( 'topic', array('tag_id' => $tag_id), 'get_tagged_topics' );
	return $query->results;
}

function get_tagged_topic_posts( $tag_id, $page = 1 ) {
	$post_query = new BB_Query( 'post', array( 'tag_id' => $tag_id, 'page' => $page ), 'get_tagged_topic_posts' );
	return $post_query->results;
}

/**
 * bb_get_top_tags() - Returns most popular tags.
 *
 * @param mixed $args
 * @return array|false Term objects (back-compat), false on failure
 */
function bb_get_top_tags( $args = null ) {
	global $wp_taxonomy_object;

	$args = wp_parse_args( $args, array( 'number' => 40 ) );
	$args['order'] = 'DESC';
	$args['orderby'] = 'count';

	$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', $args );
	if ( is_wp_error( $terms ) )
		return false;

	for ( $i = 0; isset($terms[$i]); $i++ )
		_bb_make_tag_compat( $terms[$i] );

	return $terms;
}

function _bb_make_tag_compat( &$tag ) {
	if ( is_object($tag) && isset($tag->term_id) ) {
		$tag->tag_id    =& $tag->term_taxonomy_id;
		$tag->tag       =& $tag->slug;
		$tag->raw_tag   =& $tag->name;
		$tag->tag_count =& $tag->count;
	} elseif ( is_array($tag) && isset($tag['term_id']) ) {
		$tag->tag_id    =& $tag['term_taxonomy_id'];
		$tag->tag       =& $tag['slug'];
		$tag->raw_tag   =& $tag['name'];
		$tag->tag_count =& $tag['count'];
	}
}

/* Users */

function bb_block_current_user() {
	global $bbdb;
	if ( $id = bb_get_current_user_info( 'id' ) )
		bb_update_usermeta( $id, $bbdb->prefix . 'been_blocked', 1 ); // Just for logging.
	bb_die(__("You've been blocked.  If you think a mistake has been made, contact this site's administrator."));
}

function bb_get_user( $user_id ) {
	global $wp_users_object;
	$user = $wp_users_object->get_user( $user_id );
	if ( is_wp_error($user) )
		return false;
	return $user;
}

function bb_cache_users( $ids ) {
	global $wp_users_object;
	$wp_users_object->get_user( $ids );
}

function bb_get_user_by_nicename( $nicename ) {
	global $wp_users_object;
	$user = $wp_users_object->get_user( $nicename, array( 'by' => 'nicename' ) );
	if ( is_wp_error($user) )
		return false;
	return $user;
}

function bb_delete_user( $user_id, $reassign = 0 ) {
	global $wp_users_object;

	if ( !$user = bb_get_user( $user_id ) )
		return false;

	if ( $reassign ) {
		if ( !$new_user = bb_get_user( $reassign ) )
			return false;
		$bbdb->update( $bbdb->posts, array( 'poster_id' => $new_user->ID ), array( 'poster_id' => $user->ID ) );
		$bbdb->update( $bbdb->term_relationships, array( 'user_id' => $new_user->ID ), array( 'user_id' => $user->ID ) );
		$bbdb->update( $bbdb->topics, array( 'topic_poster' => $new_user->ID, 'topic_poster_name' => $new_user->user_login), array( 'topic_poster' => $user->ID ) );
		$bbdb->update( $bbdb->topics, array( 'topic_last_poster' => $new_user->ID, 'topic_last_poster_name' => $new_user->user_login ), array( 'topic_last_poster' => $user->ID ) );
		bb_update_topics_replied( $new_user->ID );
	}

	do_action( 'bb_delete_user', $user->ID, $reassign );

	$wp_users_object->delete_user( $user->ID );

	return true;
}

function bb_update_topics_replied( $user_id ) {
	global $bbdb;

	$user_id = (int) $user_id;

	if ( !$user = bb_get_user( $user_id ) )
		return false;

	$topics_replied = (int) $bbdb->get_var( $bbdb->prepare( "SELECT COUNT(DISTINCT topic_id) FROM $bbdb->posts WHERE post_status = '0' AND poster_id = %d", $user_id ) );
	return bb_update_usermeta( $user_id, $bbdb->prefix . 'topics_replied', $topics_replied );
}

function update_user_status( $user_id, $user_status = 0 ) {
	global $wp_users_object;
	$user = bb_get_user( $user_id );
	$user_status = (int) $user_status;
	$wp_users_object->update_user( $user->ID, compact( 'user_status' ) );
}

function bb_trusted_roles() {
	return apply_filters( 'bb_trusted_roles', array('moderator', 'administrator', 'keymaster') );
}

function bb_is_trusted_user( $user ) { // ID, user_login, BB_User, DB user obj
	if ( is_numeric($user) || is_string($user) )
		$user = new WP_User( $user );
	elseif ( is_object($user) && is_a($user, 'WP_User') ); // Intentional
	elseif ( is_object($user) && isset($user->ID) && isset($user->user_login) ) // Make sure it's actually a user object
		$user = new WP_User( $user->ID );
	else
		return;

	if ( !$user->ID )
		return;

	return apply_filters( 'bb_is_trusted_user', (bool) array_intersect(bb_trusted_roles(), $user->roles), $user->ID );
}

function bb_apply_wp_role_map_to_user( $user ) {
	if ( is_numeric($user) || is_string($user) ) {
		$user_id = (integer) $user;
	} elseif ( is_object($user) ) {
		$user_id = $user->ID;
	} else {
		return;
	}
	
	if ($wp_roles_map = bb_get_option('wp_roles_map')) {
		
		global $bbdb;
		
		$bb_roles_map = array_flip($wp_roles_map);
		
		$wp_userlevel_map = array(
			'administrator' => 10,
			'editor' => 7,
			'author' => 2,
			'contributor' => 1,
			'subscriber' => 0
		);
		
		$bb_roles = bb_get_usermeta($user_id, $bbdb->prefix . 'capabilities');
		
		$wp_table_prefix = bb_get_option('wp_table_prefix');
		
		$wp_roles = bb_get_usermeta($user_id, $wp_table_prefix . 'capabilities');
		
		if (!$bb_roles && is_array($wp_roles)) {
			$bb_roles_new = array();
			
			foreach ($wp_roles as $wp_role => $wp_role_value) {
				if ($wp_roles_map[$wp_role] && $wp_role_value) {
					$bb_roles_new[$wp_roles_map[$wp_role]] = true;
				}
			}
			
			if (count($bb_roles_new)) {
				bb_update_usermeta( $user_id, $bbdb->prefix . 'capabilities', $bb_roles_new );
			}
			
		} elseif (!$wp_roles && is_array($bb_roles)) {
			$wp_roles_new = array();
			
			foreach ($bb_roles as $bb_role => $bb_role_value) {
				if ($bb_roles_map[$bb_role] && $bb_role_value) {
					$wp_roles_new[$bb_roles_map[$bb_role]] = true;
					$wp_userlevels_new[] = $wp_userlevel_map[$bb_roles_map[$bb_role]];
				}
			}
			
			if (count($wp_roles_new)) {
				bb_update_usermeta( $user_id, $wp_table_prefix . 'capabilities', $wp_roles_new );
				bb_update_usermeta( $user_id, $wp_table_prefix . 'user_level', max($wp_userlevels_new) );
			}
			
		}
		
	}
}

function bb_apply_wp_role_map_to_orphans() {
	if ( $wp_table_prefix = bb_get_option( 'wp_table_prefix' ) ) {
		
		$role_query = <<<EOQ
			SELECT
				ID
			FROM
				`%1\$s`
			LEFT JOIN `%2\$s` AS bbrole
				ON ID = bbrole.user_id
				AND bbrole.meta_key = '%3\$scapabilities'
			LEFT JOIN `%2\$s` AS wprole
				ON ID = wprole.user_id
				AND wprole.meta_key = '%4\$scapabilities'
			WHERE
				bbrole.meta_key IS NULL OR
				bbrole.meta_value IS NULL OR
				wprole.meta_key IS NULL OR
				wprole.meta_value IS NULL
			ORDER BY
				ID
EOQ;
		global $bbdb;
		
		$role_query = $bbdb->prepare($role_query, $bbdb->users, $bbdb->usermeta, $bbdb->prefix, $wp_table_prefix);
		
		if ( $user_ids = $bbdb->get_col($role_query) ) {
			foreach ( $user_ids as $user_id ) {
				bb_apply_wp_role_map_to_user( $user_id );
			}
		}
		
	}
}

/* Favorites */

function get_user_favorites( $user_id, $topics = false ) {
	$user = bb_get_user( $user_id );
	if ( $user->favorites ) {
		if ( $topics )
			$query = new BB_Query( 'topic', array('favorites' => $user_id, 'append_meta' => 0), 'get_user_favorites' );
		else
			$query = new BB_Query( 'post', array('favorites' => $user_id), 'get_user_favorites' );
		return $query->results;
	}
}

function is_user_favorite( $user_id = 0, $topic_id = 0 ) {
	if ( $user_id )
		$user = bb_get_user( $user_id );
	else
	 	global $user;
	if ( $topic_id )
		$topic = get_topic( $topic_id );
	else
		global $topic;
	if ( !$user || !$topic )
		return;

        return in_array($topic->topic_id, explode(',', $user->favorites));
}

function bb_add_user_favorite( $user_id, $topic_id ) {
	global $bbdb;
	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;
	$user = bb_get_user( $user_id );
	$topic = get_topic( $topic_id );
	if ( !$user || !$topic )
		return false;

	$fav = $user->favorites ? explode(',', $user->favorites) : array();
	if ( ! in_array( $topic_id, $fav ) ) {
		$fav[] = $topic_id;
		$fav = implode(',', $fav);
		bb_update_usermeta( $user->ID, $bbdb->prefix . 'favorites', $fav);
	}
	do_action('bb_add_user_favorite', $user_id, $topic_id);
	return true;
}

function bb_remove_user_favorite( $user_id, $topic_id ) {
	global $bbdb;
	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;
	$user = bb_get_user( $user_id );
	if ( !$user )
		return false;

	$fav = explode(',', $user->favorites);
	if ( is_int( $pos = array_search($topic_id, $fav) ) ) {
		array_splice($fav, $pos, 1);
		$fav = implode(',', $fav);
		bb_update_usermeta( $user->ID, $bbdb->prefix . 'favorites', $fav);
	}
	do_action('bb_remove_user_favorite', $user_id, $topic_id);
	return true;
}

/* Options/Meta */

function bb_option( $option ) {
	echo bb_get_option( $option ) ;
}

function bb_get_option( $option ) {
	global $bb;

	switch ( $option ) :
	case 'language':
		$r = str_replace('_', '-', get_locale());
		break;
	case 'text_direction':
		global $bb_locale;
		$r = $bb_locale->text_direction;
		break;
	case 'version' :
		return '1.0-dev'; // Don't filter
		break;
	case 'bb_db_version' :
		return '1528'; // Don't filter
		break;
	case 'html_type' :
		$r = 'text/html';
		break;
	case 'charset' :
		$r = 'UTF-8';
		break;
	case 'url' :
		$option = 'uri';
	case 'bb_table_prefix' :
	case 'table_prefix' :
		global $bbdb;
		return $bbdb->prefix; // Don't filter;
		break;
	default :
		if ( isset($bb->$option) ) {
			$r = $bb->$option;
			if ($option == 'mod_rewrite')
				if (is_bool($r))
					$r = (integer) $r;
			break;
		}
		
		$r = bb_get_option_from_db( $option );
		
		if (!$r) {
			switch ($option) {
				case 'mod_rewrite':
					$r = 0;
					break;
				case 'page_topics':
					$r = 30;
					break;
				case 'edit_lock':
					$r = 60;
					break;
				case 'gmt_offset':
					$r = 0;
					break;
				case 'uri_ssl':
					$r = preg_replace('|^http://|i', 'https://', bb_get_option('uri'));
			}
		}
		
		break;
	endswitch;
	return apply_filters( 'bb_get_option_' . $option, $r, $option);
}

function bb_get_option_from_db( $option ) {
	global $bbdb;
	$option = preg_replace('|[^a-z0-9_]|i', '', $option);

	if ( false === $r = wp_cache_get( $option, 'bb_option' ) ) {
		//if ( BB_INSTALLING ) $bbdb->return_errors();
		$row = $bbdb->get_row( $bbdb->prepare( "SELECT meta_value FROM $bbdb->meta WHERE object_type = 'bb_option' AND meta_key = %s", $option ) );
		//if ( BB_INSTALLING ) $bbdb->show_errors();

		if ( is_object($row) ) {
			$r = maybe_unserialize( $row->meta_value );
			wp_cache_set( $option, $r, 'bb_option' );
		} else {
			$r = null;
		}
	}
	return apply_filters( 'bb_get_option_from_db_' . $option, $r, $option );
}

function bb_form_option( $option ) {
	echo bb_get_form_option( $option );
}

function bb_get_form_option( $option ) {
	return attribute_escape( bb_get_option( $option ) );
}

function bb_cache_all_options() { // Don't use the return value; use the API.  Only returns options stored in DB.
	global $bbdb;
	$results = $bbdb->get_results( "SELECT meta_key, meta_value FROM $bbdb->meta WHERE object_type = 'bb_option'" );
	
	if ( $results )
		foreach ( $results as $options )
			wp_cache_set( $options->meta_key, maybe_unserialize($options->meta_value), 'bb_option' );
	
	$base_options = array(
		'bb_db_version' => 0,
		'name' => __('Please give me a name!'),
		'description' => '',
		'uri' => '',
		'uri_ssl' => '',
		'from_email' => '',
		'secret' => '',
		'page_topics' => '',
		'edit_lock' => '',
		'bb_active_theme' => '',
		'active_plugins' => '',
		'mod_rewrite' => '',
		'datetime_format' => '',
		'date_format' => '',
		'avatars_show' => '',
		'avatars_default' => '',
		'avatars_rating' => '',
		'wp_table_prefix' => '',
		'user_bbdb_name' => '',
		'user_bbdb_user' => '',
		'user_bbdb_password' => '',
		'user_bbdb_host' => '',
		'user_bbdb_charset' => '',
		'user_bbdb_collate' => '',
		'custom_user_table' => '',
		'custom_user_meta_table' => '',
		'wp_siteurl' => '',
		'wp_home' => '',
		'cookiedomain' => false,
		'usercookie' => '',
		'passcookie' => '',
		'authcookie' => '',
		'cookiepath' => '',
		'sitecookiepath' => '',
		'secure_auth_cookie' => '',
		'logged_in_cookie' => '',
		'admin_cookie_path' => '',
		'core_plugins_cookie_path' => '',
		'user_plugins_cookie_path' => '',
		'wp_admin_cookie_path' => '',
		'wp_plugins_cookie_path' => ''
	);
	
	foreach ( $base_options as $base_option => $base_option_default )
		if ( false === wp_cache_get( $base_option, 'bb_option' ) )
			wp_cache_set( $base_option, $base_option_default, 'bb_option' );
	
	return true;
}

// Can store anything but NULL.
function bb_update_option( $option, $value ) {
	return bb_update_meta( 0, $option, $value, 'option', true );
}

function bb_delete_option( $option, $value = '' ) {
	return bb_delete_meta( 0, $option, $value, 'option', true );
}

/**
 * BB_URI_CONTEXT_* - Bitwise definitions for bb_uri() and bb_get_uri() contexts
 *
 * @since 1.0-beta
 **/
define('BB_URI_CONTEXT_HEADER',               1);
define('BB_URI_CONTEXT_TEXT',                 2);
define('BB_URI_CONTEXT_A_HREF',               4);
define('BB_URI_CONTEXT_FORM_ACTION',          8);
define('BB_URI_CONTEXT_IMG_SRC',              16);
define('BB_URI_CONTEXT_LINK_STYLESHEET_HREF', 32);
define('BB_URI_CONTEXT_LINK_ALTERNATE_HREF',  64);
define('BB_URI_CONTEXT_SCRIPT_SRC',           128);
//define('BB_URI_CONTEXT_*',                    256);    // Reserved for future definitions
//define('BB_URI_CONTEXT_*',                    512);    // Reserved for future definitions
define('BB_URI_CONTEXT_BB_FEED',              1024);
define('BB_URI_CONTEXT_BB_USER_FORMS',        2048);
define('BB_URI_CONTEXT_BB_ADMIN',             4096);
//define('BB_URI_CONTEXT_*',                    8192);   // Reserved for future definitions
//define('BB_URI_CONTEXT_*',                    16384);  // Reserved for future definitions
//define('BB_URI_CONTEXT_*',                    32768);  // Reserved for future definitions
//define('BB_URI_CONTEXT_*',                    65536);  // Reserved for future definitions
//define('BB_URI_CONTEXT_*',                    131072); // Reserved for future definitions
//define('BB_URI_CONTEXT_*',                    262144); // Reserved for future definitions
define('BB_URI_CONTEXT_AKISMET',              524288);

/**
 * bb_uri() - echo a URI based on the URI setting
 *
 * @since 1.0-beta
 *
 * @param $resource string The directory, may include a querystring
 * @param $query mixed The query arguments as a querystring or an associative array
 * @param $context integer The context of the URI, use BB_URI_CONTEXT_*
 * @return void
 **/
function bb_uri($resource = null, $query = null, $context = BB_URI_CONTEXT_A_HREF) {
	echo apply_filters('bb_uri', bb_get_uri($resource, $query, $context), $resource, $query, $context);
}

/**
 * bb_get_uri() - return a URI based on the URI setting
 *
 * @since 1.0-beta
 *
 * @param $resource string The directory, may include a querystring
 * @param $query mixed The query arguments as a querystring or an associative array
 * @param $context integer The context of the URI, use BB_URI_CONTEXT_*
 * @return string The complete URI
 **/
function bb_get_uri($resource = null, $query = null, $context = BB_URI_CONTEXT_A_HREF) {
	// If there is a querystring in the resource then extract it
	if ($resource && strpos($resource, '?') !== false) {
		list($_resource, $_query) = explode('?', trim($resource));
		$resource = $_resource;
		$_query = wp_parse_args($_query);
	}
	
	// Make sure $_query is an array for array_merge()
	if (!$_query) {
		$_query = array();
	}
	
	// $query can be an array as well as a string
	if ($query) {
		if (is_string($query)) {
			$query = ltrim(trim($query), '?');
		}
		$query = wp_parse_args($query);
	}
	
	// Make sure $query is an array for array_merge()
	if (!$query) {
		$query = array();
	}
	
	// Merge the queries into a single array
	$query = array_merge($_query, $query);
	
	// Make sure context is an integer
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF;
	}
	
	// Get the base URI
	$uri = bb_get_option('uri');
	
	// Force https when required on user forms
	if (($context & BB_URI_CONTEXT_BB_USER_FORMS) && bb_force_ssl_user_forms()) {
		$uri = bb_get_option('uri_ssl');
	}
	
	// Force https when required in admin
	if (($context & BB_URI_CONTEXT_BB_ADMIN) && bb_force_ssl_admin()) {
		$uri = bb_get_option('uri_ssl');
	}
	
	// Add the directory
	$uri .= ltrim($resource, '/');
	
	// Add the query string to the URI
	$uri = add_query_arg($query, $uri);
	
	return apply_filters('bb_get_uri', $uri, $resource, $context);
}

/**
 * bb_force_ssl_user_forms() - Whether SSL should be forced when sensitive user data is being submitted.
 *
 * @since 1.0-beta
 *
 * @param string|bool $force Optional.
 * @return bool True if forced, false if not forced.
 **/
function bb_force_ssl_user_forms($force = '') {
	static $forced;
	
	if ( '' != $force ) {
		$old_forced = $forced;
		$forced = $force;
		return $old_forced;
	}
	
	return $forced;
}

/**
 * bb_force_ssl_admin() - Whether SSL should be forced when using the admin area.
 *
 * @since 1.0-beta
 *
 * @param string|bool $force Optional.
 * @return bool True if forced, false if not forced.
 **/
function bb_force_ssl_admin($force = '') {
	static $forced;
	
	if ( '' != $force ) {
		$old_forced = $forced;
		$forced = $force;
		return $old_forced;
	}
	
	return $forced;
}

/**
 * bb_ssl_redirect() - Forces redirection to an SSL page when required
 *
 * @since 1.0-beta
 *
 * @return void
 **/
function bb_ssl_redirect()
{
	do_action('bb_ssl_redirect');
	
	$page = bb_get_location();
	
	if (BB_IS_ADMIN && !bb_force_ssl_admin()) {
		return;
	}
	
	switch ($page) {
		case 'login-page':
		case 'register-page':
			if (!bb_force_ssl_user_forms()) {
				return;
			}
			break;
		
		case 'profile-page':
			$tab = isset($_GET['tab']) ? $_GET['tab'] : get_path(2);
			if ($tab == 'edit' && !bb_force_ssl_user_forms()) {
				return;
			}
			break;
		
		default:
			return;
			break;
	}
	
	if (bb_is_ssl()) {
		return;
	}
	
	if ( 0 === strpos($_SERVER['REQUEST_URI'], bb_get_option('uri')) ) {
		$uri = $_SERVER['REQUEST_URI'];
	} else {
		$uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}
	
	$uri = bb_get_option('uri_ssl') . substr($uri, strlen(bb_get_option('uri')));
	
	bb_safe_redirect($uri);
	
	return;
}

/**
 * bb_is_ssl() - Determine if SSL is used.
 *
 * @since 1.0-beta
 *
 * @return bool True if SSL, false if not used.
 */
function bb_is_ssl() {
	return ( 'on' == strtolower($_SERVER['HTTPS']) ) ? true : false;
}

// This is the only function that should add to user / topic
// NOT bbdb::prepared
function bb_append_meta( $object, $type ) {
	global $bbdb;
	switch ( $type ) :
	case 'user' :
		global $wp_users_object;
		return $wp_users_object->append_meta( $object );
		break;
	case 'forum' :
		$object_id_column = 'forum_id';
		$object_type = 'bb_forum';
		$slug = 'forum_slug';
		break;
	case 'topic' :
		$object_id_column = 'topic_id';
		$object_type = 'bb_topic';
		$slug = 'topic_slug';
		break;
	case 'post' :
		$object_id_column = 'post_id';
		$object_type = 'bb_post';
		$slug = 'post_slug';
		break;
	endswitch;
	if ( is_array($object) && $object ) :
		$trans = array();
		foreach ( array_keys($object) as $i )
			$trans[$object[$i]->$object_id_column] =& $object[$i];
		$ids = join(',', array_map('intval', array_keys($trans)));
		if ( $metas = $bbdb->get_results("SELECT object_id, meta_key, meta_value FROM $bbdb->meta WHERE object_id IN ($ids) /* bb_append_meta */") )
			usort( $metas, '_bb_append_meta_sort' );
			foreach ( $metas as $meta ) :
				$trans[$meta->object_id]->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
				if ( strpos($meta->meta_key, $bbdb->prefix) === 0 )
					$trans[$meta->object_id]->{substr($meta->meta_key, strlen($bbdb->prefix))} = maybe_unserialize( $meta->meta_value );
			endforeach;
		foreach ( array_keys($trans) as $i ) {
			wp_cache_add( $i, $trans[$i], $object_type );
			if ($slug)
				wp_cache_add( $trans[$i]->$slug, $i, 'bb_' . $slug );
		}
		return $object;
	elseif ( $object ) :
		if ( $metas = $bbdb->get_results( $bbdb->prepare( "SELECT meta_key, meta_value FROM $bbdb->meta WHERE object_type = '$object_type' AND object_id = %d /* bb_append_meta */", $object->$object_id_column ) ) )
			usort( $metas, '_bb_append_meta_sort' );
			foreach ( $metas as $meta ) :
				$object->{$meta->meta_key} = maybe_unserialize( $meta->meta_value );
				if ( strpos($meta->meta_key, $bbdb->prefix) === 0 )
					$object->{substr($meta->meta_key, strlen($bbdb->prefix))} = $object->{$meta->meta_key};
			endforeach;
		if ( $object->$object_id_column ) {
			wp_cache_set( $object->$object_id_column, $object, $object_type );
			if ($slug)
				wp_cache_add( $object->$slug, $object->$object_id_column, 'bb_' . $slug );
		}
		return $object;
	endif;
}

/** 
 * _bb_append_meta_sort() - sorts meta keys by length to ensure $appended_object->{$bbdb->prefix}key overwrites $appended_object->key as desired
 *
 * @internal
 */
function _bb_append_meta_sort( $a, $b ) {
	return strlen( $a->meta_key ) - strlen( $b->meta_key );
}

function bb_get_forummeta( $forum_id, $meta_key ) {
	if ( !$forum = bb_get_forum( $forum_id ) )
		return;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);
	if ( !isset($forum->$meta_key) )
		return;
	return $forum->$meta_key;
}

function bb_update_forummeta( $forum_id, $meta_key, $meta_value ) {
	return bb_update_meta( $forum_id, $meta_key, $meta_value, 'forum' );
}

function bb_delete_forummeta( $forum_id, $meta_key, $meta_value = '' ) {
	return bb_delete_meta( $forum_id, $meta_key, $meta_value, 'forum' );
}

function bb_get_usermeta( $user_id, $meta_key ) {
	if ( !$user = bb_get_user( $user_id ) )
		return;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);
	if ( !isset($user->$meta_key) )
		return;
	return $user->$meta_key;
}

function bb_update_usermeta( $user_id, $meta_key, $meta_value ) {
	return bb_update_meta( $user_id, $meta_key, $meta_value, 'user' );
}

function bb_delete_usermeta( $user_id, $meta_key, $meta_value = '' ) {
	return bb_delete_meta( $user_id, $meta_key, $meta_value, 'user' );
}

function bb_get_topicmeta( $topic_id, $meta_key ) {
	if ( !$topic = get_topic( $topic_id ) )
		return;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);
	if ( !isset($topic->$meta_key) )
		return;
	return $topic->$meta_key;
}

function bb_update_topicmeta( $topic_id, $meta_key, $meta_value ) {
	return bb_update_meta( $topic_id, $meta_key, $meta_value, 'topic' );
}

function bb_delete_topicmeta( $topic_id, $meta_key, $meta_value = '' ) {
	return bb_delete_meta( $topic_id, $meta_key, $meta_value, 'topic' );
}

function bb_get_postmeta( $post_id, $meta_key ) {
	if ( !$post = get_post( $post_id ) )
		return;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);
	if ( !isset($post->$meta_key) )
		return;
	return $post->$meta_key;
}

function bb_update_postmeta( $post_id, $meta_key, $meta_value ) {
	return bb_update_meta( $post_id, $meta_key, $meta_value, 'post' );
}

function bb_delete_postmeta( $post_id, $meta_key, $meta_value = '' ) {
	return bb_delete_meta( $post_id, $meta_key, $meta_value, 'post' );
}

// Internal use only.  Use API.
function bb_update_meta( $object_id = 0, $meta_key, $meta_value, $type, $global = false ) {
	global $bbdb;
	if ( !is_numeric( $object_id ) || empty($object_id) && !$global )
		return false;
	$object_id = (int) $object_id;
	switch ( $type ) {
		case 'option':
			$object_type = 'bb_option';
			break;
		case 'user' :
			global $wp_users_object;
			$id = $object_id;
			$return = $wp_users_object->update_meta( compact( 'id', 'meta_key', 'meta_value' ) );
			if ( is_wp_error($return) )
				return false;
			return $return;
			break;
		case 'forum' :
			$object_type = 'bb_forum';
			break;
		case 'topic' :
			$object_type = 'bb_topic';
			break;
		case 'post' :
			$object_type = 'bb_post';
			break;
		default :
			$object_type = $type;
			break;
	}

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);

	$meta_tuple = compact('object_type', 'object_id', 'meta_key', 'meta_value', 'type');
	$meta_tuple = apply_filters('bb_update_meta', $meta_tuple);
	extract($meta_tuple, EXTR_OVERWRITE);

	$meta_value = $_meta_value = maybe_serialize( $meta_value );
	$meta_value = maybe_unserialize( $meta_value );

	$cur = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->meta WHERE object_type = %s AND object_id = %d AND meta_key = %s", $object_type, $object_id, $meta_key ) );
	if ( !$cur ) {
		$bbdb->insert( $bbdb->meta, array( 'object_type' => $object_type, 'object_id' => $object_id, 'meta_key' => $meta_key, 'meta_value' => $_meta_value ) );
	} elseif ( $cur->meta_value != $meta_value ) {
		$bbdb->update( $bbdb->meta, array( 'meta_value' => $_meta_value), array( 'object_type' => $object_type, 'object_id' => $object_id, 'meta_key' => $meta_key ) );
	}

	wp_cache_delete( $object_id, $object_type );
	if ( !$cur )
		return true;
}

// Internal use only.  Use API.
function bb_delete_meta( $object_id = 0, $meta_key, $meta_value, $type, $global = false ) {
	global $bbdb;
	if ( !is_numeric( $object_id ) || empty($object_id) && !$global )
		return false;
	$object_id = (int) $object_id;
	switch ( $type ) {
		case 'option':
			$object_type = 'bb_option';
			break;
		case 'user' :
			global $wp_users_object;
			$id = $object_id;
			return $wp_users_object->update_meta( compact( 'id', 'meta_key', 'meta_value' ) );
			break;
		case 'forum' :
			$object_type = 'bb_forum';
			break;
		case 'topic' :
			$object_type = 'bb_topic';
			break;
		case 'post' :
			$object_type = 'bb_post';
			break;
		default :
			$object_type = $type;
			break;
	}

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);

	$meta_tuple = compact('object_type', 'object_id', 'meta_key', 'meta_value', 'type');
	$meta_tuple = apply_filters('bb_delete_meta', $meta_tuple);
	extract($meta_tuple, EXTR_OVERWRITE);

	$meta_value = maybe_serialize( $meta_value );

	$meta_sql = empty($meta_value) ? 
		$bbdb->prepare( "SELECT meta_id FROM $bbdb->meta WHERE object_type = %s AND object_id = %d AND meta_key = %s", $object_type, $object_id, $meta_key ) :
		$bbdb->prepare( "SELECT meta_id FROM $bbdb->meta WHERE object_type = %s AND object_id = %d AND meta_key = %s AND meta_value = %s", $object_type, $object_id, $meta_key, $meta_value );

	if ( !$meta_id = $bbdb->get_var( $meta_sql ) )
		return false;

	$bbdb->query( $bbdb->prepare( "DELETE FROM $bbdb->meta WHERE meta_id = %d", $meta_id ) );

	wp_cache_delete( $object_id, $object_type );
	return true;
}

/* Pagination */

function bb_get_uri_page() {
	if ( isset($_GET['page']) && is_numeric($_GET['page']) && 1 < (int) $_GET['page'] )
		return (int) $_GET['page'];

	if ( isset($_SERVER['PATH_INFO']) )
		$path = $_SERVER['PATH_INFO'];
	else
		if ( !$path = strtok($_SERVER['REQUEST_URI'], '?') )
			return 1;

	if ( $page = strstr($path, '/page/') ) {
		$page = (int) substr($page, 6);
		if ( 1 < $page )
			return $page;
	}
	return 1;
}

//expects $item = 1 to be the first, not 0
function get_page_number( $item, $per_page = 0 ) {
	if ( !$per_page )
		$per_page = bb_get_option('page_topics');
	return intval( ceil( $item / $per_page ) ); // page 1 is the first page
}

/* Time */

function bb_timer_stop($display = 0, $precision = 3) { //if called like bb_timer_stop(1), will echo $timetotal
	global $bb_timestart, $timeend;
	$mtime = explode(' ', microtime());
	$timeend = $mtime[1] + $mtime[0];
	$timetotal = $timeend - $bb_timestart;
	if ($display)
		echo bb_number_format_i18n($timetotal, $precision);
	return bb_number_format_i18n($timetotal, $precision);
}

// GMT -> so many minutes ago
function bb_since( $original, $do_more = 0 ) {
	$today = time();

	if ( !is_numeric($original) ) {
		if ( $today < $_original = bb_gmtstrtotime( str_replace(',', ' ', $original) ) ) // Looks like bb_since was called twice
			return $original;
		else
			$original = $_original;
	}
		
	// array of time period chunks
	$chunks = array(
		array(60 * 60 * 24 * 365 , __('year') , __('years')),
		array(60 * 60 * 24 * 30 , __('month') , __('months')),
		array(60 * 60 * 24 * 7, __('week') , __('weeks')),
		array(60 * 60 * 24 , __('day') , __('days')),
		array(60 * 60 , __('hour') , __('hours')),
		array(60 , __('minute') , __('minutes')),
		array(1 , __('second') , __('seconds')),
	);

	$since = $today - $original;

	for ($i = 0, $j = count($chunks); $i < $j; $i++) {
		$seconds = $chunks[$i][0];
		$name = $chunks[$i][1];
		$names = $chunks[$i][2];

		if ( 0 != $count = floor($since / $seconds) )
			break;
	}

	$print = sprintf(__('%1$d %2$s'), $count, $count == 1 ? $name : $names);

	if ( $do_more && $i + 1 < $j) {
		$seconds2 = $chunks[$i + 1][0];
		$name2 = $chunks[$i + 1][1];
		$names2 = $chunks[$i + 1][2];
		if ( 0 != $count2 = floor( ($since - $seconds * $count) / $seconds2) )
			$print .= sprintf(__(', %1$d %2$s'), $count2, ($count2 == 1) ? $name2 : $names2);
	}
	return $print;
}

function bb_current_time( $type = 'timestamp' ) {
	switch ($type) {
		case 'mysql':
			$d = gmdate('Y-m-d H:i:s');
			break;
		case 'timestamp':
			$d = time();
			break;
	}
	return $d;
}

// GMT -> Local
// in future versions this could eaily become a user option.
function bb_offset_time( $time, $args = '' ) {
	if ( 'since' == $args['format'] )
		return $time;
	if ( !is_numeric($time) ) {
		if ( -1 !== $_time = bb_gmtstrtotime( $time ) )
			return gmdate('Y-m-d H:i:s', $_time + bb_get_option( 'gmt_offset' ) * 3600);
		else
			return $time; // Perhaps should return -1 here
	} else {
		return $time + bb_get_option( 'gmt_offset' ) * 3600;
	}
}

/* Permalinking / URLs / Paths */

function get_path( $level = 1, $base = false, $request = false ) {
	if ( !$request )
		$request = $_SERVER['REQUEST_URI'];
	if ( is_string($request) )
		$request = parse_url($request);
	if ( !is_array($request) || !isset($request['path']) )
		return '';

	$path = rtrim($request['path'], '/');
	if ( !$base )
		$base = rtrim(bb_get_option('path'), '/');
	$path = preg_replace('|' . preg_quote($base, '|') . '/?|','',$path,1);
	if ( !$path )
		return '';
	if ( strpos($path, '/') === false )
		return '';

	$url = explode('/',$path);
	if ( !isset($url[$level]) )
		return '';

	return urldecode($url[$level]);
}

function bb_find_filename( $text ) {
	if ( preg_match('|.*?/([a-z\-]+\.php)/?.*|', $text, $matches) )
		return $matches[1];
	else {
		$path = bb_get_option( 'path' );
		$text = preg_replace("#^$path#", '', $text);
		$text = preg_replace('#/.+$#', '', $text);
		return $text . '.php';
	}
	return false;
}

function bb_send_headers() {
	if ( bb_is_user_logged_in() )
		nocache_headers();
	@header('Content-Type: ' . bb_get_option( 'html_type' ) . '; charset=' . bb_get_option( 'charset' ));
	do_action( 'bb_send_headers' );
}

// Inspired by and adapted from Yung-Lung Scott YANG's http://scott.yang.id.au/2005/05/permalink-redirect/ (GPL)
function bb_repermalink() {
	global $page;
	$location = bb_get_location();
	$uri = $_SERVER['REQUEST_URI'];
	if ( isset($_GET['id']) )
		$id = $_GET['id'];
	else
		$id = get_path();
	$_original_id = $id;

	do_action( 'pre_permalink', $id );

	$id = apply_filters( 'bb_repermalink', $id );

	switch ($location) {
		case 'forum-page':
			global $forum_id, $forum;
			$forum     = get_forum( $id );
			$forum_id  = $forum->forum_id;
			$permalink = get_forum_link( $forum->forum_id, $page );
			break;
		case 'topic-page':
			global $topic_id, $topic;
			$topic     = get_topic( $id );
			$topic_id  = $topic->topic_id;
			$permalink = get_topic_link( $topic->topic_id, $page );
			break;
		case 'profile-page': // This handles the admin side of the profile as well.
			global $user_id, $user, $profile_hooks, $self;
			if ( isset($_GET['id']) )
				$id = $_GET['id'];
			elseif ( isset($_GET['username']) )
				$id = $_GET['username'];
			else
				$id = get_path();
			$_original_id = $id;
			
			if ( !$id )
				$user = bb_get_current_user(); // Attempt to go to the current users profile
			elseif ( !is_numeric( $id ) && is_string( $id ) )
				$user = bb_get_user_by_nicename( $id ); // Get by the user_nicename
			else
				$user = bb_get_user( $id ); // Get by the ID

			if ( !$user || ( 1 == $user->user_status && !bb_current_user_can( 'moderate' ) ) )
				bb_die(__('User not found.'), '', 404);

			$user_id = $user->ID;
			global_profile_menu_structure();
			$valid = false;
			if ( $tab = isset($_GET['tab']) ? $_GET['tab'] : get_path(2) )
				foreach ( $profile_hooks as $valid_tab => $valid_file )
					if ( $tab == $valid_tab ) {
						$valid = true;
						$self = $valid_file;
					}
			if ( $valid ) :
				$permalink = get_profile_tab_link( $user->ID, $tab, $page );
			else :
				$permalink = get_user_profile_link( $user->ID, $page );
				unset($self, $tab);
			endif;
			break;
		case 'favorites-page':
			$permalink = get_favorites_link();
			break;
		case 'tag-page': // It's not an integer and tags.php pulls double duty.
			if ( isset($_GET['tag']) )
				$id = $_GET['tag'];
			else
				$id = get_path( 1, bb_get_option('tagpath') );
			$_original_id = $id;
			if ( !$id )
				$permalink = bb_get_tag_page_link();
			else {
				global $tag, $tag_name;
				$tag_name = $id;
				$tag = bb_get_tag_by_name( $tag_name );
				$permalink = bb_get_tag_link( 0, $page ); // 0 => grabs $tag from global.
			}
			break;
		case 'view-page': // Not an integer
			if ( isset($_GET['view']) )
				$id = $_GET['view'];
			else
				$id = get_path();
			$_original_id = $id;
			global $view;
			$view = $id;
			$permalink = get_view_link( $view, $page );
			break;
		default:
			return;
			break;
	}
	
	wp_parse_str($_SERVER['QUERY_STRING'], $args);
	$args = urlencode_deep($args);
	if ( $args ) {
		$permalink = add_query_arg($args, $permalink);
		if ( bb_get_option('mod_rewrite') ) {
			$pretty_args = array('id', 'page', 'tag', 'tab', 'username'); // these are already specified in the path
			if ( $location == 'view-page' )
				$pretty_args[] = 'view';
			foreach ( $pretty_args as $pretty_arg )
				$permalink = remove_query_arg( $pretty_arg, $permalink );
		}
	}

	$permalink = apply_filters( 'bb_repermalink_result', $permalink, $location );

	$domain = bb_get_option('domain');
	$domain = preg_replace('/^https?/', '', $domain);
	$check = preg_replace( '|^.*' . trim($domain, ' /' ) . '|', '', $permalink, 1 );

	if ( 1 === bb_get_option( 'debug' ) ) :
		echo "<table>\n<tr><td>". __('REQUEST_URI') .":</td><td>";
		var_dump($uri);
		echo "</td></tr>\n<tr><td>". __('should be') .":</td><td>";
		var_dump($check);
		echo "</td></tr>\n<tr><td>". __('full permalink') .":</td><td>";
		var_dump($permalink);
		echo "</td></tr>\n<tr><td>". __('PATH_INFO') .":</td><td>";
		var_dump($_SERVER['PATH_INFO']);
		echo "</td></tr>\n</table>";
	else :
		if ( $check != $uri && $check != str_replace(urlencode($_original_id), $_original_id, $uri) ) {
			wp_redirect( $permalink );
			exit;
		}
	endif;
	do_action( 'post_permalink', $permalink );
}

/* Profile/Admin */

function global_profile_menu_structure() {
	global $user_id, $profile_menu, $profile_hooks;
	// Menu item name
	// The capability required for own user to view the tab ('' to allow non logged in access)
	// The capability required for other users to view the tab ('' to allow non logged in access)
	// The URL of the item's file
	// Item name for URL (nontranslated)
	$profile_menu[0] = array(__('Edit'), 'edit_profile', 'edit_users', 'profile-edit.php', 'edit');
	$profile_menu[5] = array(__('Favorites'), 'edit_favorites', 'edit_others_favorites', 'favorites.php', 'favorites');

	// Create list of page plugin hook names the current user can access
	$profile_hooks = array();
	foreach ($profile_menu as $profile_tab)
		if ( can_access_tab( $profile_tab, bb_get_current_user_info( 'id' ), $user_id ) )
			$profile_hooks[bb_sanitize_with_dashes($profile_tab[4])] = $profile_tab[3];

	do_action('bb_profile_menu');
	ksort($profile_menu);
}

function add_profile_tab($tab_title, $users_cap, $others_cap, $file, $arg = false) {
	global $profile_menu, $profile_hooks, $user_id;

	$arg = $arg ? $arg : $tab_title;

	$profile_tab = array($tab_title, $users_cap, $others_cap, $file, $arg);
	$profile_menu[] = $profile_tab;
	if ( can_access_tab( $profile_tab, bb_get_current_user_info( 'id' ), $user_id ) )
		$profile_hooks[bb_sanitize_with_dashes($arg)] = $file;
}

function can_access_tab( $profile_tab, $viewer_id, $owner_id ) {
	global $bb_current_user;
	$viewer_id = (int) $viewer_id;
	$owner_id = (int) $owner_id;
	if ( $viewer_id == bb_get_current_user_info( 'id' ) )
		$viewer =& $bb_current_user;
	else
		$viewer = new WP_User( $viewer_id );
	if ( !$viewer )
		return '' === $profile_tab[2];

	if ( $owner_id == $viewer_id ) {
		if ( '' === $profile_tab[1] )
			return true;
		else
			return $viewer->has_cap($profile_tab[1]);
	} else {
		if ( '' === $profile_tab[2] )
			return true;
		else
			return $viewer->has_cap($profile_tab[2]);
	}
}

//meta_key => (required?, Label).  Don't use user_{anything} as the name of your meta_key.
function get_profile_info_keys() {
	return apply_filters( 'get_profile_info_keys', array(
		'user_email' => array(1, __('Email')),
		'user_url' => array(0, __('Website')),
		'from' => array(0, __('Location')),
		'occ' => array(0, __('Occupation')),
		'interest' => array(0, __('Interests')),
	) );
}

function get_profile_admin_keys() {
	global $bbdb;
	return apply_filters( 'get_profile_admin_keys', array(
		$bbdb->prefix . 'title' => array(0, __('Custom Title'))
	) );
}

function get_assignable_caps() {
	$caps = array();
	if ( $throttle_time = bb_get_option( 'throttle_time' ) )
		$caps['throttle'] = sprintf( __('Ignore the %d second post throttling limit'), $throttle_time );
	return apply_filters( 'get_assignable_caps', $caps );
}

/* Views */

function bb_get_views() {
	global $bb_views;

	$views = array();
	foreach ( (array) $bb_views as $view => $array )
		$views[$view] = $array['title'];

	return $views;
}

function bb_register_view( $view, $title, $query_args = '', $feed = TRUE ) {
	global $bb_views;

	$view  = bb_slug_sanitize( $view );
	$title = wp_specialchars( $title );

	if ( !$view || !$title )
		return false;

	$query_args = wp_parse_args( $query_args );

	if ( !$sticky_set = isset($query_args['sticky']) )
		$query_args['sticky'] = 'no';

	$bb_views[$view]['title']  = $title;
	$bb_views[$view]['query']  = $query_args;
	$bb_views[$view]['sticky'] = !$sticky_set; // No sticky set => split into stickies and not
	$bb_views[$view]['feed'] = $feed;
	return $bb_views[$view];
}

function bb_deregister_view( $view ) {
	global $bb_views;

	$view = bb_slug_sanitize( $view );
	if ( !isset($bb_views[$view]) )
		return false;

	unset($GLOBALS['bb_views'][$view]);
	return true;
}

function bb_view_query( $view, $new_args = '' ) {
	global $bb_views;

	$view = bb_slug_sanitize( $view );
	if ( !isset($bb_views[$view]) )
		return false;

	if ( $new_args ) {
		$new_args = wp_parse_args( $new_args );
		$query_args = array_merge( $bb_views[$view]['query'], $new_args );
	} else {
		$query_args = $bb_views[$view]['query'];
	}

	return new BB_Query( 'topic', $query_args, "bb_view_$view" );
}

function bb_get_view_query_args( $view ) {
	global $bb_views;

	$view = bb_slug_sanitize( $view );
	if ( !isset($bb_views[$view]) )
		return false;

	return $bb_views[$view]['query'];
}

/* Nonce */

function bb_nonce_url($actionurl, $action = -1) {
	return add_query_arg( '_wpnonce', bb_create_nonce( $action ), $actionurl );
}

function bb_nonce_field($action = -1, $name = "_wpnonce", $referer = true) {
	$name = attribute_escape($name);
	echo '<input type="hidden" name="' . $name . '" value="' . bb_create_nonce($action) . '" />';
	if ( $referer )
		wp_referer_field();
}

function bb_nonce_ays( $action ) {
	$title = __( 'bbPress Failure Notice' );
	$html .= "\t<div id='message' class='updated fade'>\n\t<p>" . wp_specialchars( bb_explain_nonce( $action ) ) . "</p>\n\t<p>";
	if ( wp_get_referer() )
		$html .= "<a href='" . remove_query_arg( 'updated', clean_url( wp_get_referer() ) ) . "'>" . __( 'Please try again.' ) . "</a>";
	$html .= "</p>\n\t</div>\n";
	$html .= "</body>\n</html>";
	bb_die( $html, $title );
}

function bb_install_header( $title = '', $header = false ) {
	if ( empty($title) )
		if ( function_exists('__') )
			$title = __('bbPress');
		else
			$title = 'bbPress';
		
		$uri = false;
		if ( function_exists('bb_get_uri') && !BB_INSTALLING ) {
			$uri = bb_get_uri();
			$uri_stylesheet = bb_get_uri('bb-admin/install.css', null, BB_URI_CONTEXT_LINK_STYLESHEET_HREF + BB_URI_CONTEXT_BB_INSTALLER);
			$uri_stylesheet_rtl = bb_get_uri('bb-admin/install-rtl.css', null, BB_URI_CONTEXT_LINK_STYLESHEET_HREF + BB_URI_CONTEXT_BB_INSTALLER);
			$uri_logo = bb_get_uri('bb-admin/images/install-logo.gif', null, BB_URI_CONTEXT_IMG_SRC + BB_URI_CONTEXT_BB_INSTALLER);
		}
		
		if (!$uri) {
			$uri = preg_replace('|(/bb-admin)?/[^/]+?$|', '/', $_SERVER['PHP_SELF']);
			$uri_stylesheet = $uri . 'bb-admin/install.css';
			$uri_stylesheet_rtl = $uri . 'bb-admin/install-rtl.css';
			$uri_logo = $uri . 'bb-admin/images/install-logo.gif';
		}
	
	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"<?php if ( function_exists( 'bb_language_attributes' ) ) bb_language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $title; ?></title>
	<meta name="robots" content="noindex, nofollow" />
	<link rel="stylesheet" href="<?php echo $uri_stylesheet; ?>" type="text/css" />
<?php
	if ( function_exists( 'bb_get_option' ) && 'rtl' == bb_get_option( 'text_direction' ) ) {
?>
	<link rel="stylesheet" href="<?php echo $uri_stylesheet_rtl; ?>" type="text/css" />
<?php
	}
?>
</head>
<body>
	<div id="container">
		<div class="logo">
			<img src="<?php echo $uri_logo; ?>" alt="bbPress Installation" />
		</div>
<?php
	if ( !empty($header) ) {
?>
		<h1>
			<?php echo $header; ?>
		</h1>
<?php
	}
}

function bb_install_footer() {
?>
	</div>
	<p id="footer">
		<?php _e('<a href="http://bbpress.org/">bbPress</a> - simple, fast, elegant'); ?>
	</p>
</body>
</html>
<?php
}

function bb_die( $message, $title = '', $header = 0 ) {
	global $bb_locale;
	
	if ( $header && !headers_sent() )
		status_header( $header );

	if ( empty($title) )
		$title = __('bbPress &rsaquo; Error');
	
	bb_install_header( $title );
?>
	<p><?php echo $message; ?></p>
<?php
	if ($uri = bb_get_uri()) {
?>
	<p class="last"><?php printf( __('Back to <a href="%s">%s</a>.'), $uri, bb_get_option( 'name' ) ); ?></p>
<?php
	}
	bb_install_footer();
	die();
}

function bb_explain_nonce($action) {
	if ( $action !== -1 && preg_match('/([a-z]+)-([a-z]+)(_(.+))?/', $action, $matches) ) {
		$verb = $matches[1];
		$noun = $matches[2];

		$trans = array();
		$trans['create']['post'] = array(__('Your attempt to submit this post has failed.'), false);
		$trans['edit']['post'] = array(__('Your attempt to edit this post has failed.'), false);
		$trans['delete']['post'] = array(__('Your attempt to delete this post has failed.'), false);

		$trans['create']['topic'] = array(__('Your attempt to create this topic has failed.'), false);
		$trans['resolve']['topic'] = array(__('Your attempt to change the resolution status of this topic has failed.'), false);
		$trans['delete']['topic'] = array(__('Your attempt to delete this topic has failed.'), false);
		$trans['close']['topic'] = array(__('Your attempt to change the status of this topic has failed.'), false);
		$trans['stick']['topic'] = array(__('Your attempt to change the sticky status of this topic has failed.'), false);
		$trans['move']['topic'] = array(__('Your attempt to move this topic has failed.'), false);

		$trans['add']['tag'] = array(__('Your attempt to add this tag to this topic has failed.'), false);
		$trans['rename']['tag'] = array(__('Your attempt to rename this tag has failed.'), false);
		$trans['merge']['tag'] = array(__('Your attempt to submit these tags has failed.'), false);
		$trans['destroy']['tag'] = array(__('Your attempt to destroy this tag has failed.'), false);
		$trans['remove']['tag'] = array(__('Your attempt to remove this tag from this topic has failed.'), false);

		$trans['toggle']['favorite'] = array(__('Your attempt to toggle your favorite status for this topic has failed.'), false);

		$trans['edit']['profile'] = array(__("Your attempt to edit this user's profile has failed."), false);

		$trans['add']['forum'] = array(__("Your attempt to add this forum has failed."), false);
		$trans['update']['forums'] = array(__("Your attempt to update your forums has failed."), false);
		$trans['delete']['forums'] = array(__("Your attempt to delete that forum has failed."), false);

		$trans['do']['counts'] = array(__("Your attempt to recount these items has failed."), false);

		$trans['switch']['theme'] = array(__("Your attempt to switch themes has failed."), false);

		if ( isset($trans[$verb][$noun]) ) {
			if ( !empty($trans[$verb][$noun][1]) ) {
				$lookup = $trans[$verb][$noun][1];
				$object = $matches[4];
				if ( 'use_id' != $lookup )
					$object = call_user_func($lookup, $object);
				return sprintf($trans[$verb][$noun][0], wp_specialchars( $object ));
			} else {
				return $trans[$verb][$noun][0];
			}
		}
	}

	return apply_filters( 'bb_explain_nonce_' . $verb . '-' . $noun, __('Your attempt to do this has failed.'), $matches[4] );
}

/* DB Helpers */
function bb_count_last_query( $query = '' ) {
	global $bbdb, $bb_last_countable_query;

	if ( $query )
		$q = $query;
	elseif ( $bb_last_countable_query )
		$q = $bb_last_countable_query;
	else
		$q = $bbdb->last_query;

	if ( false === strpos($q, 'SELECT') )
		return false;

	if ( false !== strpos($q, 'SQL_CALC_FOUND_ROWS') )
		return (int) $bbdb->get_var( "SELECT FOUND_ROWS()" );

	$q = preg_replace(
		array('/SELECT.*?\s+FROM/', '/LIMIT [0-9]+(\s*,\s*[0-9]+)?/', '/ORDER BY\s+.*$/', '/DESC/', '/ASC/'),
		array('SELECT COUNT(*) FROM', ''),
		$q
	);

	if ( preg_match( '/GROUP BY\s+(\S+)/', $q, $matches ) )
		$q = str_replace( array( 'COUNT(*)', $matches[0] ), array( "COUNT(DISTINCT $matches[1])", '' ), $q );

	if ( !$query )
		$bb_last_countable_query = '';
	return (int) $bbdb->get_var($q);
}

function no_where( $where ) {
	return;
}

/* Plugins/Themes utility */

function bb_basename($file, $directories) {
	if (strpos($file, '#') !== false)
		return $file; // It's already a basename
	foreach ($directories as $type => $directory)
		if (strpos($file, $directory) !== false)
			break; // Keep the $file and $directory set and use them below, nifty huh?
	$file = str_replace('\\','/',$file);
	$file = preg_replace('|/+|','/', $file);
	$file = preg_replace('|^.*' . preg_quote($directory, '|') . '|', $type . '#', $file);
	return $file;
}

/* Plugins */

function bb_plugin_basename($file) {
	return bb_basename( $file, array('user' => BB_PLUGIN_DIR, 'core' => BB_CORE_PLUGIN_DIR) );
}

function bb_register_plugin_activation_hook($file, $function) {
	$file = bb_plugin_basename($file);
	add_action('bb_activate_plugin_' . $file, $function);
}

function bb_register_plugin_deactivation_hook($file, $function) {
	$file = bb_plugin_basename($file);
	add_action('bb_deactivate_plugin_' . $file, $function);
}

function bb_get_plugin_uri( $plugin = false ) {
	if ( !$plugin ) {
		$plugin_uri = BB_PLUGIN_URL;
	} else {
		$plugin_uri = str_replace(
			array('core#', 'user#'),
			array(BB_CORE_PLUGIN_URL, BB_PLUGIN_URL),
			$plugin
		);
		$plugin_uri = dirname($plugin_uri) . '/';
	}
	return apply_filters( 'bb_get_plugin_uri', $plugin_uri, $plugin );
}

/* Themes / Templates */

function bb_get_active_theme_directory() {
	return apply_filters( 'bb_get_active_theme_directory', bb_get_theme_directory() );
}

function bb_get_theme_directory($theme = false) {
	if (!$theme) {
		$theme = bb_get_option( 'bb_active_theme' );
	}
	if ( !$theme ) {
		$theme_directory = BB_DEFAULT_THEME_DIR;
	} else {
		$theme_directory = str_replace(
			array('core#', 'user#'),
			array(BB_CORE_THEME_DIR, BB_THEME_DIR),
			$theme
		) . '/';
	}
	return $theme_directory;
}

function bb_get_themes() {
	$r = array();
	$theme_roots = array(
		'core' => BB_CORE_THEME_DIR,
		'user' => BB_THEME_DIR
	);
	foreach ( $theme_roots as $theme_root_name => $theme_root )
		if ( $themes_dir = @dir($theme_root) )
			while( ( $theme_dir = $themes_dir->read() ) !== false )
				if ( is_dir($theme_root . $theme_dir) && is_readable($theme_root . $theme_dir) && '.' != $theme_dir{0} )
					$r[$theme_root_name . '#' . $theme_dir] = $theme_root_name . '#' . $theme_dir;
	ksort($r);
	return $r;
}

function bb_theme_basename($file) {
	$file = bb_basename( $file, array('user' => BB_THEME_DIR, 'core' => BB_CORE_THEME_DIR) );
	$file = preg_replace('|/+.*|', '', $file);
	return $file;
}

function bb_register_theme_activation_hook($file, $function) {
	$file = bb_theme_basename($file);
	add_action('bb_activate_theme_' . $file, $function);
}

function bb_register_theme_deactivation_hook($file, $function) {
	$file = bb_theme_basename($file);
	add_action('bb_deactivate_theme_' . $file, $function);
}

/* Search Functions */
// NOT bbdb::prepared
function bb_user_search( $args = '' ) {
	global $bbdb, $bb_last_countable_query;

	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'query' => $args );

	$defaults = array( 'query' => '', 'append_meta' => true, 'user_login' => true, 'display_name' => true, 'user_nicename' => false, 'user_url' => true, 'user_email' => false, 'user_meta' => false, 'users_per_page' => false, 'page' => false );

	extract(wp_parse_args( $args, $defaults ), EXTR_SKIP);

	$query = trim( $query );
	if ( $query && strlen( preg_replace('/[^a-z0-9]/i', '', $query) ) < 3 )
		return new WP_Error( 'invalid-query', __('Your search term was too short') );

	if ( !$page )
		$page = $GLOBALS['page'];

	$page = (int) $page;

	$query = $bbdb->escape( $query );

	$limit = 0 < (int) $users_per_page ? (int) $users_per_page : bb_get_option( 'page_topics' );
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";

	$likeit = preg_replace('/\s+/', '%', $query);

	$fields = array();

	foreach ( array('user_login', 'display_name', 'user_nicename', 'user_url', 'user_email') as $field )
		if ( $$field )
			$fields[] = $field;

	if ( $query && $user_meta ) :
		$sql = "SELECT user_id FROM $bbdb->usermeta WHERE meta_value LIKE ('%$likeit')";
		if ( empty($fields) )
			$sql .= " LIMIT $limit";
		$user_meta_ids = $bbdb->get_col($sql);
		if ( empty($fields) ) :
			bb_cache_users( $user_meta_ids );
			$users = array();
			foreach( $user_meta_ids as $user_id )
				$users[] = bb_get_user( $user_id );
			return $users;
		endif;
	endif;

	$sql = "SELECT * FROM $bbdb->users";

	$sql_terms = array();
	if ( $query )
		foreach ( $fields as $field )
			$sql_terms[] = "$field LIKE ('%$likeit%')";

	if ( $user_meta_ids )
		$sql_terms[] = "ID IN (". join(',', $user_meta_ids) . ")";

	if ( $query && empty($sql_terms) )
		return new WP_Error( 'invalid-query', __('Your query parameters are invalid') );

	$sql .= ( $sql_terms ? ' WHERE ' . implode(' OR ', $sql_terms) : '' ) . " LIMIT $limit";

	$bb_last_countable_query = $sql;

	if ( ( $users = $bbdb->get_results($sql) ) && $append_meta )
		return bb_append_meta( $users, 'user' );

	return $users ? $users : false;
}

function bb_tag_search( $args = '' ) {
	global $page, $wp_taxonomy_object;

	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'search' => $args );

	$defaults = array( 'search' => '', 'number' => false );

	$args = wp_parse_args( $args );
	if ( isset( $args['query'] ) )
		$args['search'] = $args['query'];
	if ( isset( $args['tags_per_page'] ) )
		$args['number'] = $args['tags_per_page'];
	unset($args['query'], $args['tags_per_page']);
	$args = wp_parse_args( $args, $defaults );

	extract( $args, EXTR_SKIP );

	$number = (int) $number;
	$search = trim( $search );
	if ( strlen( $search ) < 3 )
		return new WP_Error( 'invalid-query', __('Your search term was too short') );

	$number = 0 < $number ? $number : bb_get_option( 'page_topics' );
	if ( 1 < $page )
		$offset = ( intval($page) - 1 ) * $number;

	$args = array_merge( $args, compact( 'number', 'offset', 'search' ) );

	$terms = $wp_taxonomy_object->get_terms( 'bb_topic_tag', $args );
	if ( is_wp_error( $terms ) )
		return false;

	for ( $i = 0; isset($terms[$i]); $i++ )
		_bb_make_tag_compat( $terms[$i] );

	return $terms;
}

// TODO
function bb_related_tags( $_tag = false, $number = 40 ) {
	return array();

	global $bbdb, $tag;
	if ( is_numeric($_tag) )
		$_tag = bb_get_tag( $_tag );
	elseif ( is_string($_tag) )
		$_tag = bb_get_tag_by_name( $_tag );
	elseif ( false === $_tag )
		$_tag = $tag;

	if ( !$_tag )
		return false;

	$number = (int) $number;

	$sql = $bbdb->prepare(
		"SELECT tag.tag_id, tag.tag, tag.raw_tag, COUNT(DISTINCT t.topic_id) AS tag_count
	           FROM $bbdb->tagged AS t
	           JOIN $bbdb->tagged AS tt  ON (t.topic_id = tt.topic_id)
	           JOIN $bbdb->tags   AS tag ON (t.tag_id = tag.tag_id)
	        WHERE tt.tag_id = %d AND t.tag_id != %d GROUP BY t.tag_id ORDER BY tag_count DESC LIMIT %d",
		$_tag->tag_id, $_tag->tag_id, $number
	);

	foreach ( (array) $tags = $bbdb->get_results( $sql ) as $_tag ) {
		wp_cache_add( $tag->tag, $tag, 'bb_tag' );
		wp_cache_add( $tag->tag_id, $tag->tag, 'bb_tag_id' );
	}

	return $tags;
}

/* Slugs */

function bb_slug_increment( $slug, $existing_slug, $slug_length = 255 ) {
	if ( preg_match('/^.*-([0-9]+)$/', $existing_slug, $m) )
		$number = (int) $m[1] + 1;
	else
		$number = 1;

	$r = bb_encoded_utf8_cut( $slug, $slug_length - 1 - strlen($number) );
	return apply_filters( 'bb_slug_increment', "$r-$number", $slug, $existing_slug, $slug_length );
}

function bb_get_id_from_slug( $table, $slug, $slug_length = 255 ) {
	global $bbdb;
	$tablename = $table . 's';

	list($_slug, $sql) = bb_get_sql_from_slug( $table, $slug, $slug_length );

	if ( !$_slug || !$sql )
		return 0;

	return (int) $bbdb->get_var( "SELECT ${table}_id FROM {$bbdb->$tablename} WHERE $sql" );
}

function bb_get_sql_from_slug( $table, $slug, $slug_length = 255 ) {
	global $bbdb;

	// Look for new style equiv of old style slug
	$_slug = bb_slug_sanitize( (string) $slug );
	if ( strlen( $_slug ) < 1 )
		return '';

	if ( strlen($_slug) > $slug_length && preg_match('/^.*-([0-9]+)$/', $_slug, $m) ) {
		$_slug = bb_encoded_utf8_cut( $_slug, $slug_length - 1 - strlen($number) );
		$number = (int) $m[1];
		$_slug =  "$_slug-$number";
	}

	return array( $_slug, $bbdb->prepare( "${table}_slug = %s", $_slug ) );
}	

/* Utility */

function bb_flatten_array( $array, $cut_branch = 0, $keep_child_array_keys = true ) {
	if ( !is_array($array) )
		return $array;
	
	if ( empty($array) )
		return null;
	
	$temp = array();
	foreach ( $array as $k => $v ) {
		if ( $cut_branch && $k == $cut_branch )
			continue;
		if ( is_array($v) ) {
			if ( $keep_child_array_keys ) {
				$temp[$k] = true;
			}
			$temp += bb_flatten_array($v, $cut_branch, $keep_child_array_keys);
		} else {
			$temp[$k] = $v;
		}
	}
	return $temp;
}

function bb_get_common_parts($string1 = false, $string2 = false, $delimiter = '', $reverse = false) {
	if (!$string1 || !$string2) {
		return false;
	}
	
	if ($string1 === $string2) {
		return $string1;
	}
	
	$string1_parts = explode( $delimiter, (string) $string1 );
	$string2_parts = explode( $delimiter, (string) $string2 );
	
	if ($reverse) {
		$string1_parts = array_reverse( $string1_parts );
		$string2_parts = array_reverse( $string2_parts );
		ksort( $string1_parts );
		ksort( $string2_parts );
	}
	
	$common_parts = array();
	foreach ( $string1_parts as $index => $part ) {
		if ( $string2_parts[$index] == $part ) {
			$common_parts[] = $part;
		} else {
			break;
		}
	}
	
	if (!count($common_parts)) {
		return false;
	}
	
	if ($reverse) {
		$common_parts = array_reverse( $common_parts );
	}
	
	return join( $delimiter, $common_parts );
}

function bb_get_common_domains($domain1 = false, $domain2 = false) {
	if (!$domain1 || !$domain2) {
		return false;
	}
	
	$domain1 = strtolower( preg_replace( '@^https?://([^/]+).*$@i', '$1', $domain1 ) );
	$domain2 = strtolower( preg_replace( '@^https?://([^/]+).*$@i', '$1', $domain2 ) );
	
	return bb_get_common_parts( $domain1, $domain2, '.', true );
}

function bb_get_common_paths($path1 = false, $path2 = false) {
	if (!$path1 || !$path2) {
		return false;
	}
	
	$path1 = preg_replace('@^https?://[^/]+(.*)$@i', '$1', $path1);
	$path2 = preg_replace('@^https?://[^/]+(.*)$@i', '$1', $path2);
	
	if ($path1 === $path2) {
		return $path1;
	}
	
	$path1 = trim( $path1, '/' );
	$path2 = trim( $path2, '/' );
	
	$common_path = bb_get_common_parts( $path1, $path2, '/' );
	
	if ($common_path) {
		return '/' . $common_path . '/';
	} else {
		return '/';
	}
}

function bb_match_domains($domain1 = false, $domain2 = false) {
	if (!$domain1 || !$domain2) {
		return false;
	}
	
	$domain1 = strtolower( preg_replace( '@^https?://([^/]+).*$@i', '$1', $domain1 ) );
	$domain2 = strtolower( preg_replace( '@^https?://([^/]+).*$@i', '$1', $domain2 ) );
	
	if ( (string) $domain1 === (string) $domain2 ) {
		return true;
	}
	
	return false;
}

function bb_glob($pattern) {
	// On fail return an empty array so that loops don't explode
	
	if (!$pattern)
		return array();
	
	// May break if pattern contains forward slashes
	$directory = dirname( $pattern );
	
	if (!$directory)
		return array();
	
	if (!file_exists($directory))
		return array();
	
	if (!is_dir($directory))
		return array();
	
	if (!function_exists('glob'))
		return array();
	
	if (!is_callable('glob'))
		return array();
	
	$glob = glob($pattern);
	
	if (!is_array($glob))
		$glob = array();
	
	return $glob;
}

?>
