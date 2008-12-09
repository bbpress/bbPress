<?php

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

	if (bb_get_option('enable_pingback')) {
		bb_update_postmeta($post_id, 'pingback_queued', '');
		wp_schedule_single_event(time(), 'do_pingbacks');
	}

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
