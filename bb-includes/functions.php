<?php

function get_forums() {
	global $bb_cache;
	return apply_filters('get_forums',$bb_cache->get_forums());
}

function get_forum( $id ) {
	global $bb_cache;
	return $bb_cache->get_forum( $id );
}

function get_topic( $id, $cache = true ) {
	global $bb_cache, $bb_topic_cache;
	$id = (int) $id;
	if ( isset( $bb_topic_cache[$id] ) && $cache )
		return $bb_topic_cache[$id];
	else	return $bb_cache->get_topic($id, $cache);
}

function get_thread( $topic_id, $page = 1, $reverse = 0 ) {
	global $bb_cache;
	return $bb_cache->get_thread( $topic_id, $page, $reverse );
}

function get_thread_post_ids( $topic_id ) {
	global $bbdb, $thread_ids_cache;
	if ( !isset( $thread_ids_cache[$topic_id] ) ) {
		$where = apply_filters('get_thread_post_ids_where', 'AND post_status = 0');
		$thread_ids_cache[$topic_id]['post'] = (array) $bbdb->get_col("SELECT post_id, poster_id FROM $bbdb->posts WHERE topic_id = $topic_id $where ORDER BY post_time");
		$thread_ids_cache[$topic_id]['poster'] = (array) $bbdb->get_col('', 1);
	}
	return $thread_ids_cache[$topic_id];
}

function bb_get_post( $post_id ) {
	global $bb_post_cache, $bbdb;
	$post_id = (int) $post_id;
	if ( !isset( $bb_post_cache[$post_id] ) )
		$bb_post_cache[$post_id] = $bbdb->get_row("SELECT * FROM $bbdb->posts WHERE post_id = $post_id");
	return $bb_post_cache[$post_id];
}

function get_latest_topics( $forum = 0, $page = 1, $exclude = '') {
	global $bbdb, $bb_last_countable_query;
	$forum = (int) $forum;
	$page = (int) $page;
	$where = 'WHERE topic_status = 0';
	if ( $forum )
		$where .= " AND forum_id = $forum ";
	if ( !empty( $exclude ) )
		$where .= " AND forum_id NOT IN ('$exclude') ";
	if ( is_front() )
		$where .= " AND topic_sticky <> 2 ";
	elseif ( is_forum() || is_view() )
		$where .= " AND topic_sticky = 0 ";
	$limit = bb_get_option('page_topics');
	$where = apply_filters('get_latest_topics_where', $where);
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$bb_last_countable_query = "SELECT * FROM $bbdb->topics $where ORDER BY topic_time DESC LIMIT $limit";
	if ( $topics = $bbdb->get_results($bb_last_countable_query) )
		return bb_append_meta( $topics, 'topic' );
	else
		return false;
}

function get_sticky_topics( $forum = 0, $display = 1 ) {
	global $bbdb, $bb_last_countable_query;
	if ( 1 != $display )
		return false;
	$forum = (int) $forum;
	if ( is_front() )
		$where = 'WHERE topic_sticky = 2  AND topic_status = 0';
	else	$where = 'WHERE topic_sticky <> 0 AND topic_status = 0';
	if ( $forum )
		$where .= " AND forum_id = $forum ";
	$where = apply_filters('get_sticky_topics_where', $where);
	$bb_last_countable_query = "SELECT * FROM $bbdb->topics $where ORDER BY topic_time DESC";
	if ( $stickies = $bbdb->get_results($bb_last_countable_query) )
		return bb_append_meta( $stickies, 'topic' );	
	else	return false;
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
function bb_cache_first_posts( $_topics = false, $author_cache = true ) {
	global $topics, $bb_first_post_cache, $bb_cache, $bbdb;
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

	$posts = (array) $bb_cache->cache_posts( "SELECT * FROM $bbdb->posts WHERE topic_id IN ($_topic_ids) AND post_position = 1 AND post_status = 0" );

	$first_posts = array();
	foreach ( $posts as $post ) {
		$bb_first_post_cache[(int) $post->topic_id] = (int) $post->post_id;
		$first_posts[(int) $post->topic_id] = $post;
	}

	if ( $author_cache )
		post_author_cache( $posts );

	return $first_posts;
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
function bb_cache_last_posts( $_topics = false, $author_cache = true ) {
	global $topics, $bb_topic_cache, $bb_cache, $bbdb;
	if ( !$_topics )
		$_topics =& $topics;
	if ( !is_array($_topics) )
		return false;

	$last_post_ids = array();
	$topic_ids = array();
	foreach ( $_topics as $topic )
		if ( is_object($topic) )
			$last_post_ids[] = (int) $topic->topic_last_post_id;
		else if ( is_numeric($topic) && isset($bb_topic_cache[(int) $topic]) && $bb_topic_cache[(int) $topic] )
			$last_post_ids[] = (int) $bb_topic_cache[(int) $topic]->topic_last_post_id;
		else if ( is_numeric($topic) )
			$topic_ids[] = (int) $topic;

	if ( !empty($last_post_ids) ) {
		$_last_post_ids = join(',', $last_post_ids);
		$posts = (array) $bb_cache->cache_posts( "SELECT * FROM $bbdb->posts WHERE post_id IN ($_last_post_ids) AND post_status = 0" );
		if ( $author_cache )
			post_author_cache( $posts );
	}

	if ( !empty($topic_ids) ) {	
		$_topic_ids = join(',', $topic_ids);
		$posts = (array) $bb_cache->cache_posts( "SELECT p.* FROM $bbdb->topics AS t LEFT JOIN $bbdb->posts AS p ON ( t.topic_last_post_id = p.post_id ) WHERE t.topic_id IN ($_topic_ids) AND p.post_status = 0" );
		if ( $author_cache )
			post_author_cache( $posts );
	}
}

function no_replies( $where ) {
	return $where . ' AND topic_posts = 1 ';
}

function untagged( $where ) {
	return $where . ' AND tag_count = 0 ';
}

function deleted_topics( $where ) {
	return str_replace('topic_status = 0', 'topic_status = 1', $where);
}

function no_where( $where ) {
	return;
}

function bb_move_forum_topics( $from_forum_id, $to_forum_id ) {
	global $bb_cache, $bbdb;
	
	$from_forum_id = (int) $from_forum_id ;
	$to_forum_id = (int) $to_forum_id;
	
	add_filter('get_forum_where', 'no_where'); // Just in case
	
	$from_forum = get_forum( $from_forum_id );
	if ( !$to_forum = get_forum( $to_forum_id ) )
		return false;

	$bb_cache->flush_many( 'forum', $from_forum_id );
	$bb_cache->flush_many( 'forum', $to_forum_id );
	
	$posts = $to_forum->posts + ( $from_forum ? $from_forum->posts : 0 );
	$topics = $to_forum->topics + ( $from_forum ? $from_forum->topics : 0 );
	
	$bbdb->query("UPDATE $bbdb->forums SET topics = '$topics', posts = '$posts' WHERE forum_id = '$to_forum_id'");
	$bbdb->query("UPDATE $bbdb->forums SET topics = 0, posts = 0 WHERE forum_id = '$from_forum_id'");
	$bbdb->query("UPDATE $bbdb->posts SET forum_id = '$to_forum_id' WHERE forum_id = '$from_forum_id'");
	$topic_ids = $bbdb->get_col("SELECT topic_id FROM $bbdb->topics WHERE forum_id = '$from_forum_id'");
	$return = $bbdb->query("UPDATE $bbdb->topics SET forum_id = '$to_forum_id' WHERE forum_id = '$from_forum_id'");
	if ( $topic_ids )
		foreach ( $topic_ids as $topic_id ) {
			$bb_cache->flush_one( 'topic', $topic_id );
			$bb_cache->flush_many( 'thread', $topic_id );
		}
	$bb_cache->flush_one( 'forum', $to_forum_id );
	$bb_cache->flush_many( 'forum', $from_forum_id );
	return $return;
}

function get_latest_posts( $limit = 0, $page = 1 ) {
	global $bbdb;
	$limit = (int) $limit;
	if ( !$limit )
		$limit = bb_get_option( 'page_topics' );
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$where = apply_filters( 'get_latest_posts_where', 'WHERE post_status = 0' );
	return $bbdb->get_results("SELECT * FROM $bbdb->posts $where ORDER BY post_time DESC LIMIT $limit");
}

function get_latest_forum_posts( $forum_id, $limit = 0, $page = 1 ) {
	global $bbdb;
	$limit = (int) $limit;
	$forum_id = (int) $forum_id;
	if ( !$limit )
		$limit = bb_get_option( 'page_topics' );
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$where = apply_filters('get_latest_forum_posts_where', "WHERE forum_id = '$forum_id' AND post_status = 0");
	return $bbdb->get_results("SELECT * FROM $bbdb->posts $where ORDER BY post_time DESC LIMIT $limit");
}

function get_user_favorites( $user_id, $list = false ) {
	global $bbdb, $page;
	$user = bb_get_user( $user_id );
	if ( $user->favorites )
		if ( $list ) {
			$limit = bb_get_option( 'page_topics' );
			if ( 1 < $page )
				$limit = ($limit * ($page - 1)) . ", $limit";
			return $bbdb->get_results("
				SELECT * FROM $bbdb->topics WHERE topic_status = 0 AND topic_id IN ($user->favorites)
				ORDER BY topic_time DESC LIMIT $limit");
		} else
			return $bbdb->get_results("
				SELECT * FROM $bbdb->posts WHERE post_status = 0 AND topic_id IN ($user->favorites)
				ORDER BY post_time DESC LIMIT 20");
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
		return false;

	if ( in_array($topic->topic_id, explode(',', $user->favorites)) )
		return 1;
	else	return 0;
}

function bb_add_user_favorite( $user_id, $topic_id ) {
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
		bb_update_usermeta( $user->ID, $bb_table_prefix . 'favorites', $fav);
	}
	do_action('bb_add_user_favorite', $user_id, $topic_id);
	return true;
}

function bb_remove_user_favorite( $user_id, $topic_id ) {
	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;
	$user = bb_get_user( $user_id );
	if ( !$user )
		return false;

	$fav = explode(',', $user->favorites);
	if ( is_int( $pos = array_search($topic_id, $fav) ) ) {
		array_splice($fav, $pos, 1);
		$fav = implode(',', $fav);
		bb_update_usermeta( $user->ID, $bb_table_prefix . 'favorites', $fav);
	}
	do_action('bb_remove_user_favorite', $user_id, $topic_id);
	return true;
}

function get_recent_user_replies( $user_id ) {
	global $bbdb, $bb_post_cache, $page, $bb_last_countable_query;
	$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$where = apply_filters('get_recent_user_replies_where', 'AND post_status = 0');
	$posts = $bbdb->get_results("SELECT *, MAX(post_time) as post_time FROM $bbdb->posts WHERE poster_id = $user_id $where GROUP BY topic_id ORDER BY post_time DESC LIMIT $limit");
	if ( $posts ) :
		foreach ($posts as $bb_post) {
			$bb_post_cache[$bb_post->post_id] = $bb_post;
			$topics[] = $bb_post->topic_id;
		}
		$topic_ids = join(',', $topics);
		$bb_last_countable_query = "SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)";
		$topics = $bbdb->get_results($bb_last_countable_query);
		bb_append_meta( $topics, 'topic' );
		return $posts;
	else :
		return false;
	endif;
}

function get_recent_user_threads( $user_id ) {
	global $bbdb, $page, $bb_last_countable_query;
	$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$where = apply_filters('get_recent_user_threads_where', 'AND topic_status = 0');
	$bb_last_countable_query = "SELECT * FROM $bbdb->topics WHERE topic_poster = $user_id $where ORDER BY topic_start_time DESC LIMIT $limit";
	if ( $topics = $bbdb->get_results($bb_last_countable_query) )
		$topic = bb_append_meta( $topics, 'topic' );
	return $topics;
}

//expects $item = 1 to be the first, not 0
function get_page_number( $item, $per_page = 0 ) {
	if ( !$per_page )
		$per_page = bb_get_option('page_topics');
	return intval( ceil( $item / $per_page ) ); // page 1 is the first page
}

function bb_timer_stop($display = 0, $precision = 3) { //if called like bb_timer_stop(1), will echo $timetotal
	global $bb_timestart, $timeend;
	$mtime = explode(' ', microtime());
	$timeend = $mtime[1] + $mtime[0];
	$timetotal = $timeend - $bb_timestart;
	if ($display)
		echo number_format($timetotal, $precision);
	return number_format($timetotal, $precision);
}

function bb_since( $original, $do_more = 0 ) {
	if ( !is_numeric($original) )
		$original = strtotime($original);
	// array of time period chunks
	$chunks = array(
		array(60 * 60 * 24 * 365 , __('year') , __('years')),
		array(60 * 60 * 24 * 30 , __('month') , __('months')),
		array(60 * 60 * 24 * 7, __('week') , __('weeks')),
		array(60 * 60 * 24 , __('day') , __('days')),
		array(60 * 60 , __('hour') , __('hours')),
		array(60 , __('minute') , __('minutes')),
	);

	$today = time();
	$since = $today - bb_offset_time($original);

	for ($i = 0, $j = count($chunks); $i < $j; $i++) {
		$seconds = $chunks[$i][0];
		$name = $chunks[$i][1];
		$names = $chunks[$i][2];

		if (($count = floor($since / $seconds)) != 0)
			break;
	}

	$print = sprintf(__('%1$d %2$s'), $count, ($count == 1) ? $name : $names);

	if ($i + 1 < $j) {
		$seconds2 = $chunks[$i + 1][0];
		$name2 = $chunks[$i + 1][1];
		$names2 = $chunks[$i + 1][2];

		// add second item if it's greater than 0
		if ( (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) && $do_more )
			$print .= sprintf(__(', %1$d %2$s'), $count2, ($count2 == 1) ? $name2 : $names2);
	}
	return $print;
}

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
		return '0.8-alpha'; // Don't filter
		break;
	case 'html_type' :
		$r = 'text/html';
		break;
	case 'charset' :
		$r = 'UTF-8';
		break;
	case 'url' :
		$option = 'uri';
	default :
		if ( isset($bb->$option) ) {
			$r = $bb->$option;
			break;
		}

		$r = bb_get_option_from_db( $option );
		break;
	endswitch;
	return apply_filters( 'bb_get_option_' . $option, $r, $option);
}

function bb_get_option_from_db( $option ) {
	global $bbdb, $bb_topic_cache;
	$option = preg_replace('|[^a-z0-9_]|i', '', $option);

	if ( isset($bb_topic_cache[0]->$option) ) {
		$r = $bb_topic_cache[0]->$option;
		if ( is_wp_error( $r ) && 'bb_get_option' == $r->get_error_code() )
			$r = null; // see WP_Error below
	} else {
		$row = $bbdb->get_row("SELECT meta_value FROM $bbdb->topicmeta WHERE topic_id = 0 AND meta_key = '$option'");

		if ( is_object($row) ) {
			$bb_topic_cache[0]->$option = $r = bb_maybe_unserialize( $row->meta_value );
		} else {
			$r = null;
			$bb_topic_cache[0]->$option = new WP_Error( 'bb_get_option' ); // Used internally for caching.  See above.
		}
	}
	return apply_filters( 'bb_get_option_from_db_' . $option, $r, $option );
}

function bb_cache_all_options() { // Don't use the return value; use the API.  Only returns options stored in DB.
	return bb_append_meta( (object) array('topic_id' => 0), 'topic' );
}

// Can store anything but NULL.
function bb_update_option( $option, $value ) {
	return bb_update_meta( 0, $option, $value, 'topic', true );
}

function bb_delete_option( $option, $value = '' ) {
	return bb_delete_meta( 0, $option, $value, 'topic', true );
}

function bb_maybe_serialize( $data ) {
	if ( is_string($data) )
		$data = trim($data);
	elseif ( is_array($data) || is_object($data) || is_bool($data) )
		return serialize($data);
	if ( is_serialized( $data ) )
		return serialize($data);
	return $data;
}

function bb_maybe_unserialize( $data ) {
	if ( is_serialized( $data ) ) {
		if ( 'b:0;' === $data )
			return false;
		if ( false !== $_data = @unserialize($data) )
			return $_data;
	}
	return $data;
}

function bb_get_uri_page() {
	if ( isset($_GET['page']) && is_numeric($_GET['page']) && 1 < (int) $_GET['page'] )
		return (int) $_GET['page'];
	if ( isset($_SERVER['PATH_INFO']) )
		if ( $page = strstr($_SERVER['PATH_INFO'], '/page/') ):
			$page = (int) substr($page, 6);
			if ( 1 < $page )
				return $page;
		endif;
	return 1;
}

function post_author_cache($posts) {
	global $bb_user_cache;

	if ( !$posts )
		return;

	foreach ($posts as $bb_post)
		if ( 0 != $bb_post->poster_id )
			if ( !isset($bb_user_cache[$bb_post->poster_id]) ) // Don't cache what we already have
				$ids[] = $bb_post->poster_id;
	if ( isset($ids) )
		bb_cache_users(array_unique($ids), false); // false since we've already checked for soft cached data.
}

function bb_current_time( $type = 'timestamp' ) {
	switch ($type) {
		case 'mysql':
			$d = gmdate('Y-m-d H:i:s');
			break;
		case 'timestamp':
			$d = time() - bb_get_option( 'gmt_offset' ) * 3600; //make this GMT
			break;
	}
	return $d;
}

function bb_block_current_user() {
	global $bbdb, $bb_table_prefix, $bb_current_user;
	if ( $bb_current_user )
		bb_update_usermeta( $bb_current_user->ID, $bb_table_prefix . 'been_blocked', 1 ); // Just for logging.
	bb_die(__("You've been blocked.  If you think a mistake has been made, contact this site's administrator."));
}

//Temp
function bb_log_current_nocaps() {
	global $bbdb, $bb_table_prefix, $bb_current_user;
	bb_update_usermeta( $bb_current_user->ID, $bb_table_prefix . 'no_caps', 1 ); // Just for logging.
}

function bb_get_user( $user_id, $cache = true ) {
	global $bb_cache, $bb_user_cache;
	if ( !is_numeric( $user_id ) )
		die(__('bb_get_user needs a numeric ID'));
	$user_id = (int) $user_id;
	if ( isset( $bb_user_cache[$user_id] ) && $cache )
		return $bb_user_cache[$user_id];
	else
		return $bb_cache->get_user( $user_id, $cache );
}

function bb_cache_users( $ids, $soft_cache = true ) {
	global $bb_cache, $bb_user_cache;
	if ( $soft_cache )
		foreach( $ids as $i => $d )
			if ( isset($bb_user_cache[$d]) )
				unset($ids[i]); // Don't cache what we already have
	if ( 0 < count($ids) )
		$bb_cache->cache_users( $ids );
}

function bb_get_user_by_name( $name ) {
	global $bbdb;
	$name = user_sanitize( $name );
	if ( $user_id = $bbdb->get_var("SELECT ID FROM $bbdb->users WHERE user_login = '$name'") )
		return bb_get_user( $user_id );
	else
		return false;
}

// This is the only function that should add to $bb_(user||topic)_cache
function bb_append_meta( $object, $type ) {
	global $bbdb, $bb_table_prefix;
	switch ( $type ) :
	case 'user' :
		global $bb_user_cache;
		$cache =& $bb_user_cache;
		$table = $bbdb->usermeta;
		$field = 'user_id';
		$id = 'ID';
		break;
	case 'topic' :
		global $bb_topic_cache;
		$cache =& $bb_topic_cache;
		$table = $bbdb->topicmeta;
		$field = $id = 'topic_id';
		break;
	endswitch;
	if ( is_array($object) ) :
		foreach ( array_keys($object) as $i )
			$trans[$object[$i]->$id] =& $object[$i];
		$ids = join(',', array_keys($trans));
		if ( $metas = $bbdb->get_results("SELECT $field, meta_key, meta_value FROM $table WHERE $field IN ($ids)") )
			foreach ( $metas as $meta ) :
				$trans[$meta->$field]->{$meta->meta_key} = bb_maybe_unserialize( $meta->meta_value );
				if ( strpos($meta->meta_key, $bb_table_prefix) === 0 )
					$trans[$meta->$field]->{substr($meta->meta_key, strlen($bb_table_prefix))} = bb_maybe_unserialize( $meta->meta_value );
			endforeach;
		foreach ( array_keys($trans) as $i )
			$cache[$i] = $trans[$i];
		return $object;
	elseif ( $object ) :
		if ( $metas = $bbdb->get_results("SELECT meta_key, meta_value FROM $table WHERE $field = '{$object->$id}'") )
			foreach ( $metas as $meta ) :
				$object->{$meta->meta_key} = bb_maybe_unserialize( $meta->meta_value );
				if ( strpos($meta->meta_key, $bb_table_prefix) === 0 )
					$object->{substr($meta->meta_key, strlen($bb_table_prefix))} = bb_maybe_unserialize( $meta->meta_value );
			endforeach;
		$cache[$object->$id] = $object;
		return $object;
	endif;
}

function bb_user_exists( $user ) {
	global $bbdb;
	$user = user_sanitize( $user );
	return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user'");
}

function bb_delete_user( $user_id, $reassign = 0 ) {
	global $bbdb, $bb_cache;

	$user_id = (int) $user_id;
	$reassign = (int) $reassign;

	if ( !$user = bb_get_user( $user_id ) )
		return false;

	if ( $reassign ) {
		if ( !$new_user = bb_get_user( $reassign ) )
			return false;
		$bbdb->query("UPDATE $bbdb->posts SET poster_id = '$new_user->ID' WHERE poster_id = '$user->ID'");
		$bbdb->query("UPDATE $bbdb->tagged SET user_id = '$new_user->ID' WHERE user_id = '$user->ID'");
		$bbdb->query("UPDATE $bbdb->topics SET topic_poster = '$new_user->ID', topic_poster_name = '$new_user->user_login' WHERE topic_poster = '$user->ID'");
		$bbdb->query("UPDATE $bbdb->topics SET topic_last_poster = '$new_user->ID', topic_last_poster_name = '$new_user->user_login' WHERE topic_last_poster = '$user->ID'");
		bb_update_topics_replied( $new_user->ID );
		$bb_cache->flush_one( 'user', $new_user->ID );
	}

	do_action( 'bb_delete_user', $user_id, $reassign );

	$bbdb->query("DELETE FROM $bbdb->users WHERE ID = '$user->ID'");
	$bbdb->query("DELETE FROM $bbdb->usermeta WHERE user_id = '$user->ID'");
	$bb_cache->flush_one( 'user', $user->ID );

	return true;
}

function bb_update_topics_replied( $user_id ) {
	global $bbdb, $bb_table_prefix;

	$user_id = (int) $user_id;

	if ( !$user = bb_get_user( $user_id ) )
		return false;

	$topics_replied = $bbdb->get_var("SELECT COUNT(DISTINCT topic_id) FROM $bbdb->posts WHERE post_status = '0' AND poster_id = '$user_id'");
	return bb_update_usermeta( $user_id, $bb_table_prefix . 'topics_replied', $topics_replied );
}

function update_user_status( $user_id, $status = 0 ) {
	global $bbdb, $bb_cache, $bb_current_user;
	$user = bb_get_user( $user_id );
	$status = (int) $status;
	if ( $user->ID != $bb_current_user->ID && bb_current_user_can('edit_users') ) :
		$bbdb->query("UPDATE $bbdb->users SET user_status = $status WHERE ID = $user->ID");
		$bb_cache->flush_one( 'user', $user->ID );
	endif;
	return;
}

function bb_update_usermeta( $user_id, $meta_key, $meta_value ) {
	return bb_update_meta( $user_id, $meta_key, $meta_value, 'user' );
}

function bb_delete_usermeta( $user_id, $meta_key, $meta_value = '' ) {
	return bb_delete_meta( $user_id, $meta_key, $meta_value, 'user' );
}

function bb_update_topicmeta( $topic_id, $meta_key, $meta_value ) {
	return bb_update_meta( $topic_id, $meta_key, $meta_value, 'topic' );
}

function bb_delete_topicmeta( $topic_id, $meta_key, $meta_value = '' ) {
	return bb_delete_meta( $topic_id, $meta_key, $meta_value, 'topic' );
}

// Internal use only.  Use API.
function bb_update_meta( $type_id, $meta_key, $meta_value, $type, $global = false ) {
	global $bbdb, $bb_cache, $bb_table_prefix;
	if ( !is_numeric( $type_id ) || empty($type_id) && !$global )
		return false;
	$type_id = (int) $type_id;
	switch ( $type ) :
	case 'user' :
		global $bb_user_cache;
		$cache =& $bb_user_cache;
		$table = $bbdb->usermeta;
		$field = 'user_id';
		break;
	case 'topic' :
		global $bb_topic_cache;
		$cache =& $bb_topic_cache;
		$table = $bbdb->topicmeta;
		$field = 'topic_id';
		break;
	endswitch;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);
	if ( 'user' == $type && 'capabilities' == $meta_key )
		$meta_key = $bb_table_prefix . 'capabilities';

	$meta_tuple = compact('type_id', 'meta_key', 'meta_value', 'type');
	$meta_tuple = apply_filters('bb_update_meta', $meta_tuple);
	extract($meta_tuple, EXTR_OVERWRITE);

	$meta_value = bb_maybe_serialize( $meta_value );
	$_meta_value = $bbdb->escape( $meta_value );
	$meta_value = bb_maybe_unserialize( $meta_value );

	$cur = $bbdb->get_row("SELECT * FROM $table WHERE $field = '$type_id' AND meta_key = '$meta_key'");
	if ( !$cur ) {
		$bbdb->query("INSERT INTO $table ( $field, meta_key, meta_value )
		VALUES
		( '$type_id', '$meta_key', '$_meta_value' )");
	} elseif ( $cur->meta_value != $meta_value ) {
		$bbdb->query("UPDATE $table SET meta_value = '$_meta_value' WHERE $field = '$type_id' AND meta_key = '$meta_key'");
	}

	if ( isset($cache[$type_id]) ) {
		$cache[$type_id]->{$meta_key} = $meta_value;
		if ( 0 === strpos($meta_key, $bb_table_prefix) )
			$cache[$type_id]->{substr($meta_key, strlen($bb_table_prefix))} = $cache[$type_id]->{$meta_key};
	}

	$bb_cache->flush_one( $type, $type_id );
	if ( !$cur )
		return true;
}

// Internal use only.  Use API.
function bb_delete_meta( $type_id, $meta_key, $meta_value, $type, $global = false ) {
	global $bbdb, $bb_cache, $bb_table_prefix;
	if ( !is_numeric( $type_id ) || empty($type_id) && !$global )
		return false;
	$type_id = (int) $type_id;
	switch ( $type ) :
	case 'user' :
		global $bb_user_cache;
		$cache =& $bb_user_cache;
		$table = $bbdb->usermeta;
		$field = 'user_id';
		$meta_id_field = 'umeta_id';
		break;
	case 'topic' :
		global $bb_topic_cache;
		$cache =& $bb_topic_cache;
		$table = $bbdb->topicmeta;
		$field = 'topic_id';
		$meta_id_field = 'meta_id';
		break;
	endswitch;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);

	$meta_tuple = compact('type_id', 'meta_key', 'meta_value', 'type');
	$meta_tuple = apply_filters('bb_delete_meta', $meta_tuple);
	extract($meta_tuple, EXTR_OVERWRITE);

	$meta_value = bb_maybe_serialize( $meta_value );
	$meta_value = $bbdb->escape( $meta_value );

	if ( empty($meta_value) )
		$meta_id = $bbdb->get_var("SELECT $meta_id_field FROM $table WHERE $field = '$type_id' AND meta_key = '$meta_key'");
	else
		$meta_id = $bbdb->get_var("SELECT $meta_id_field FROM $table WHERE $field = '$type_id' AND meta_key = '$meta_key' AND meta_value = '$meta_value'");

	if ( !$meta_id )
		return false;

	if ( empty($meta_value) )
		$bbdb->query("DELETE FROM $table WHERE $field = '$type_id' AND meta_key = '$meta_key'");
	else
		$bbdb->query("DELETE FROM $table WHERE $meta_id_field = '$meta_id'");

	unset($cache[$type_id]->{$meta_key});
	if ( 0 === strpos($meta_key, $bb_table_prefix) )
		unset($cache[$type_id]->{substr($meta_key, strlen($bb_table_prefix))});

	$bb_cache->flush_one( $type, $type_id );
	return true;
}



function bb_new_forum( $name, $desc, $order = 0 ) {
	global $bbdb, $bb_cache, $bb_current_user;
	if ( !bb_current_user_can('manage_forums') )
		return false;
	if ( strlen($name) < 1 )
		return false;
	$bbdb->query("INSERT INTO $bbdb->forums (forum_name, forum_desc, forum_order) VALUES ('$name', '$desc', '$order')");
	$bb_cache->flush_one( 'forums' );
	return $bbdb->insert_id;
}

function bb_update_forum( $forum_id, $name, $desc, $order = 0 ) {
	global $bbdb, $bb_cache, $bb_current_user;
	if ( !bb_current_user_can('manage_forums') )
		return false;
	if ( !$forum_id = (int) $forum_id )
		return false;
	$order = (int) $order;
	if ( strlen($name) < 1 )
		return false;
	$bb_cache->flush_many( 'forum', $forum_id );
	return $bbdb->query("UPDATE $bbdb->forums SET forum_name = '$name', forum_desc = '$desc', forum_order = '$order' WHERE forum_id = $forum_id");
}

// When you delete a forum, you delete *everything*
function bb_delete_forum( $forum_id ) {
	global $bbdb, $bb_cache;
	if ( !bb_current_user_can( 'delete_forum', $forum_id ) )
		return false;
	if ( !$forum_id = (int) $forum_id )
		return false;

	if ( $topic_ids = $bbdb->get_col("SELECT topic_id FROM $bbdb->topics WHERE forum_id = '$forum_id'") ) {
		$_topic_ids = join(',', $topic_ids);
		$bbdb->query("DELETE FROM $bbdb->posts WHERE topic_id IN ($_topic_ids) AND topic_id != 0");
		$bbdb->query("DELETE FROM $bbdb->topicmeta WHERE topic_id IN ($_topic_ids) AND topic_id != 0");
		$bbdb->query("DELETE FROM $bbdb->topics WHERE forum_id = '$forum_id'");
	}
	
	$return = $bbdb->query("DELETE FROM $bbdb->forums WHERE forum_id = $forum_id");

	if ( $topic_ids )
		foreach ( $topic_ids as $topic_id ) {
			$bb_cache->flush_one( 'topic', $topic_id );
			$bb_cache->flush_many( 'thread', $topic_id );
		}

	$bb_cache->flush_many( 'forum', $forum_id );
	return $return;
}

function bb_new_topic( $title, $forum, $tags = '' ) {
	global $bbdb, $bb_cache, $bb_current_user;
	$title = apply_filters('pre_topic_title', $title, false);
	$forum = (int) $forum;
	$now   = bb_current_time('mysql');

	if ( $forum && $title ) {
		$bbdb->query("INSERT INTO $bbdb->topics 
		(topic_title, topic_poster, topic_poster_name, topic_last_poster, topic_last_poster_name, topic_start_time, topic_time, forum_id)
		VALUES
		('$title', $bb_current_user->ID, '{$bb_current_user->data->user_login}', $bb_current_user->ID, '{$bb_current_user->data->user_login}', '$now', '$now', $forum)");
		$topic_id = $bbdb->insert_id;
		if ( !empty( $tags ) )
			add_topic_tags( $topic_id, $tags );
		$bbdb->query("UPDATE $bbdb->forums SET topics = topics + 1 WHERE forum_id = $forum");
		$bb_cache->flush_many( 'forum', $forum_id );
		do_action('bb_new_topic', $topic_id);
		return $topic_id;
	} else {
		return false;
	}
}

function bb_update_topic( $title, $topic_id ) {
	global $bbdb, $bb_cache;
	$title = apply_filters('pre_topic_title', $title, $topic_id);
	$topic_id = (int) $topic_id;

	if ( $topic_id && $title ) {
		$bbdb->query("UPDATE $bbdb->topics SET topic_title = '$title' WHERE topic_id = $topic_id");
		$bb_cache->flush_one( 'topic', $topic_id );
		do_action('bb_update_topic', $topic_id);
		return $topic_id;
	} else {
		return false;
	}
}

function bb_delete_topic( $topic_id, $new_status = 0 ) {
	global $bbdb, $bb_cache, $bb_table_prefix;
	$topic_id = (int) $topic_id;
	add_filter( 'get_topic_where', 'no_where' );
	if ( $topic = get_topic( $topic_id ) ) {
		$new_status = (int) $new_status;
		$old_status = (int) $topic->topic_status;
		if ( $new_status == $old_status )
			return;
		$post_ids = get_thread_post_ids( $topic_id );
		$post_ids['post'] = array_reverse((array) $post_ids['post']);
		foreach ( $post_ids['post'] as $post_id )
			_bb_delete_post( $post_id, $new_status );
		$bbdb->query("UPDATE $bbdb->topics SET topic_status = '$new_status' WHERE topic_id = '$topic_id'");

		$ids = array_unique((array) $post_ids['poster']);
		foreach ( $ids as $id )
			if ( $user = bb_get_user( $id ) )
				bb_update_usermeta( $user->ID, $bb_table_prefix . 'topics_replied', ( $old_status ? $user->topics_replied + 1 : $user->topics_replied - 1 ) );
		if ( $new_status ) {
			if( $tags = (array) $bbdb->get_col("SELECT tag_id FROM $bbdb->tagged WHERE topic_id = '$topic_id'") ) {
				$tags = join(',', $tags);
				$bbdb->query("UPDATE $bbdb->tags SET tag_count = tag_count - 1 WHERE tag_id IN ($tags)");
			}
			$bbdb->query("DELETE FROM $bbdb->tagged WHERE topic_id = '$topic_id'");
			$bbdb->query("UPDATE $bbdb->forums SET topics = topics - 1, posts = posts - '$topic->topic_posts' WHERE forum_id = '$topic->forum_id'");
		} else {
			$topic_posts = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = '$topic_id' AND post_status = 0");
			$all_posts = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = '$topic_id'");
			bb_update_topicmeta( $topic_id, 'deleted_posts', $all_posts - $topic_posts );
			$bbdb->query("UPDATE $bbdb->forums SET topics = topics + 1, posts = posts + '$topic_posts' WHERE forum_id = '$topic->forum_id'");
			$bbdb->query("UPDATE $bbdb->topics SET topic_posts = '$topic_posts' WHERE topic_id = '$topic_id'");
			bb_topic_set_last_post( $topic_id );
			update_post_positions( $topic_id );
		}
			
		do_action( 'bb_delete_topic', $topic_id, $new_status, $old_status );
		$bb_cache->flush_one( 'topic', $topic_id );
		$bb_cache->flush_many( 'thread', $topic_id );
		return $topic_id;
	} else {
		return false;
	}
}

function bb_move_topic( $topic_id, $forum_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	$forum_id = (int) $forum_id;
	$topic = get_topic( $topic_id );
	if ( $topic && $topic->forum_id != $forum_id && get_forum( $forum_id ) ) {
		$bbdb->query("UPDATE $bbdb->posts SET forum_id = $forum_id WHERE topic_id = $topic_id");
		$bbdb->query("UPDATE $bbdb->topics SET forum_id = $forum_id WHERE topic_id = $topic_id");
		$bbdb->query("UPDATE $bbdb->forums SET topics = topics + 1, posts = posts + $topic->topic_posts WHERE forum_id = $forum_id");
		$bbdb->query("UPDATE $bbdb->forums SET topics = topics - 1, posts = posts - $topic->topic_posts WHERE forum_id = $topic->forum_id");
		$bb_cache->flush_one( 'topic', $topic_id );
		$bb_cache->flush_many( 'forum', $forum_id );
		return $forum_id;
	}
	return false;
}

function bb_new_post( $topic_id, $bb_post ) {
	global $bbdb, $bb_cache, $bb_table_prefix, $bb_current_user, $thread_ids_cache;
	$topic_id   = (int) $topic_id;
	$bb_post  = apply_filters('pre_post', $bb_post, false, $topic_id);
	$post_status = (int) apply_filters('pre_post_status', '0', false, $topic_id);
	$now   = bb_current_time('mysql');
	$uid   = $bb_current_user->ID;
	$uname = $bb_current_user->data->user_login;
	$ip    = addslashes( $_SERVER['REMOTE_ADDR'] );

	$topic = get_topic( $topic_id );
	$forum_id = $topic->forum_id;

	if ( $bb_post && $topic ) {
		$topic_posts = ( 0 == $post_status ) ? $topic->topic_posts + 1 : $topic->topic_posts;
		$bbdb->query("INSERT INTO $bbdb->posts 
		(forum_id, topic_id, poster_id, post_text, post_time, poster_ip, post_status, post_position)
		VALUES
		('$forum_id', '$topic_id', '$uid',  '$bb_post','$now',    '$ip',    '$post_status', $topic_posts)");
		$post_id = $bbdb->insert_id;
		if ( 0 == $post_status ) {
			$bbdb->query("UPDATE $bbdb->forums SET posts = posts + 1 WHERE forum_id = $topic->forum_id");
			$bbdb->query("UPDATE $bbdb->topics SET topic_time = '$now', topic_last_poster = '$uid', topic_last_poster_name = '$uname',
				topic_last_post_id = '$post_id', topic_posts = '$topic_posts' WHERE topic_id = '$topic_id'");
			if ( isset($thread_ids_cache[$topic_id]) ) {
				$thread_ids_cache[$topic_id]['post'][] = $post_id;
				$thread_ids_cache[$topic_id]['poster'][] = $uid;
			}
			$post_ids = get_thread_post_ids( $topic_id );
			if ( !in_array($uid, array_slice($post_ids['poster'], 0, -1)) )
				bb_update_usermeta( $uid, $bb_table_prefix . 'topics_replied', $bb_current_user->data->topics_replied + 1 );
		} else
			bb_update_topicmeta( $topic->topic_id, 'deleted_posts', isset($topic->deleted_posts) ? $topic->deleted_posts + 1 : 1 );
		if ( !bb_current_user_can('throttle') )
			bb_update_usermeta( $uid, 'last_posted', time() );
		$bb_cache->flush_one( 'topic', $topic_id );
		$bb_cache->flush_many( 'thread', $topic_id );
		$bb_cache->flush_many( 'forum', $forum_id );
		do_action('bb_new_post', $post_id);
		return $post_id;
	} else {
		return false;
	}
}

function bb_delete_post( $post_id, $new_status = 0 ) {
	global $bbdb, $bb_cache, $bb_table_prefix, $thread_ids_cache, $topic, $bb_post;
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
			$bbdb->query("UPDATE $bbdb->forums SET posts = posts - 1 WHERE forum_id = $topic->forum_id");
		} else if ( 0 == $new_status ) {
			bb_update_topicmeta( $topic_id, 'deleted_posts', $topic->deleted_posts - 1 );
			$bbdb->query("UPDATE $bbdb->forums SET posts = posts + 1 WHERE forum_id = $topic->forum_id");
		}
		$posts = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = $topic_id AND post_status = 0");
		$bbdb->query("UPDATE $bbdb->topics SET topic_posts = '$posts' WHERE topic_id = $topic_id");

		if ( isset($thread_ids_cache[$topic_id]) && false !== $pos = array_search($post_id, $thread_ids_cache[$topic_id]['post']) ) {
			array_splice($thread_ids_cache[$topic_id]['post'], $pos, 1);
			array_splice($thread_ids_cache[$topic_id]['poster'], $pos, 1);
		}
		$post_ids = get_thread_post_ids( $topic_id );

		if ( 0 == $posts ) {
			if ( 0 == $topic->topic_status || 1 == $new_status )
				bb_delete_topic( $topic_id, $new_status );
		} else {
			if ( 0 != $topic->topic_status ) {
				$bbdb->query("UPDATE $bbdb->topics SET topic_status = 0 WHERE topic_id = $topic_id");
				$bbdb->query("UPDATE $bbdb->forums SET topics = topics + 1 WHERE forum_id = $topic->forum_id");
			}
			bb_topic_set_last_post( $topic_id );
			update_post_positions( $topic_id );
		}
		$user = bb_get_user( $uid );
		if ( $new_status && ( !is_array($post_ids['poster']) || !in_array($user->ID, $post_ids['poster']) ) )
			bb_update_usermeta( $user->ID, $bb_table_prefix . 'topics_replied', $user->topics_replied - 1 );
		$bb_cache->flush_one( 'topic', $topic_id );
		$bb_cache->flush_many( 'thread', $topic_id );
		$bb_cache->flush_many( 'forum', $forum_id );
		do_action( 'bb_delete_post', $post_id, $new_status, $old_status );
		return $post_id;
	} else {
		return false;
	}
}

function _bb_delete_post( $post_id, $new_status ) {
	global $bbdb;
	$bbdb->query("UPDATE $bbdb->posts SET post_status = $new_status WHERE post_id = $post_id");
}

function bb_topic_set_last_post( $topic_id ) {
	global $bbdb;
	$old_post = $bbdb->get_row("SELECT post_id, poster_id, post_time FROM $bbdb->posts WHERE topic_id = $topic_id AND post_status = 0 ORDER BY post_time DESC LIMIT 1");
	$old_name = $bbdb->get_var("SELECT user_login FROM $bbdb->users WHERE ID = $old_post->poster_id");
	$bbdb->query("UPDATE $bbdb->topics SET topic_time = '$old_post->post_time', topic_last_poster = $old_post->poster_id, topic_last_poster_name = '$old_name', topic_last_post_id = $old_post->post_id WHERE topic_id = $topic_id");
}	

function topics_replied_on_undelete_post( $post_id ) {
	global $bb_table_prefix;
	$bb_post = bb_get_post( $post_id );
	$topic = get_topic( $bb_post->topic_id );
	$post_ids = get_thread_post_ids( $topic->topic_id );
	$times = array_count_values( $post_ids['poster'] );
	if ( 1 == $times[$bb_post->poster_id] )
		if ( $user = bb_get_user( $bb_post->poster_id ) )
			bb_update_usermeta( $user->ID, $bb_table_prefix . 'topics_replied', $user->topics_replied + 1 );
}

function bb_close_topic( $topic_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	$bb_cache->flush_one( 'topic', $topic_id );
	$r = $bbdb->query("UPDATE $bbdb->topics SET topic_open = '0' WHERE topic_id = $topic_id");
	do_action('close_topic', $topic_id, $r);
	return $r;
}

function bb_open_topic( $topic_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	$bb_cache->flush_one( 'topic', $topic_id );
	$r = $bbdb->query("UPDATE $bbdb->topics SET topic_open = '1' WHERE topic_id = $topic_id");
	do_action('open_topic', $topic_id, $r);
	return $r;
}

function bb_stick_topic( $topic_id, $super = 0 ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	$stick = 1 + abs((int) $super);
	$bb_cache->flush_one( 'topic', $topic_id );
	$r = $bbdb->query("UPDATE $bbdb->topics SET topic_sticky = '$stick' WHERE topic_id = $topic_id");
	do_action('stick_topic', $topic_id, $r);
}

function bb_unstick_topic( $topic_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	$bb_cache->flush_one( 'topic', $topic_id );
	$r = $bbdb->query("UPDATE $bbdb->topics SET topic_sticky = '0' WHERE topic_id = $topic_id");
	do_action('unstick_topic', $topic_id, $r);
	return $r;
}

function bb_update_post( $bb_post, $post_id, $topic_id ) {
	global $bbdb, $bb_cache;
	$post_id  = (int) $post_id;
	$topic_id = (int) $topic_id;
	$old_post = bb_get_post( $post_id );
	$bb_post  = apply_filters( 'pre_post', $bb_post, $post_id, $topic_id );
	$post_status = (int) apply_filters( 'pre_post_status', $old_post->post_status, $post_id, $topic_id );

	if ( $post_id && $bb_post ) {
		$bbdb->query("UPDATE $bbdb->posts SET post_text = '$bb_post', post_status = '$post_status' WHERE post_id = $post_id");
		$bb_cache->flush_many( 'thread', $topic_id );
		do_action('bb_update_post', $post_id);
		return $post_id;
	} else {
		return false;
	}
}

function get_post_link( $post_id ) {
	global $bb_post;
	$post_id = (int) $post_id;
	if ( $post_id )
		$bb_post = bb_get_post( $post_id );
	$page = get_page_number( $bb_post->post_position );
	return apply_filters( 'get_post_link', get_topic_link( $bb_post->topic_id, $page ) . "#post-$bb_post->post_id" );
}

function post_link( $post_id = 0 ) {
	echo apply_filters( 'post_link', get_post_link( $post_id ) );
}

function update_post_positions( $topic_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	$posts = get_thread_post_ids( $topic_id );
	if ( $posts ) {
		foreach ( $posts['post'] as $i => $post_id ) {
			$bbdb->query("UPDATE $bbdb->posts SET post_position = $i + 1 WHERE post_id = $post_id");
		}
		$bb_cache->flush_many( 'thread', $topic_id );
		return true;
	} else {
		return false;
	}
}

function topic_is_open ( $topic_id ) {
	$topic = get_topic( $topic_id );
	if ( 1 == $topic->topic_open )
		return true;
	else
		return false;
}

function topic_is_sticky ( $topic_id ) {
	$topic = get_topic( $topic_id );
	if ( '0' !== $topic->topic_sticky )
		return true;
	else
		return false;
}

function bb_is_first( $post_id ) { // First post in thread
	global $bbdb;
	$bb_post = bb_get_post( $post_id );
	$where = apply_filters('bb_is_first_where', 'AND post_status = 0');
	$first_post = $bbdb->get_var("SELECT post_id FROM $bbdb->posts WHERE topic_id = $bb_post->topic_id $where ORDER BY post_id ASC LIMIT 1");

	if ( $post_id == $first_post )
		return true;
	else
		return false;
}

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

// GMT -> Local
function bb_offset_time($time) {
	// in future versions this could eaily become a user option.
	if ( !is_numeric($time) ) {
		if ( !(strtotime($time) === -1)) {
			$time = strtotime($time);
			return date('Y-m-d H:i:s', $time + bb_get_option( 'gmt_offset' ) * 3600);
		} else {
			return $time;
		}
	} else {
		return $time + bb_get_option( 'gmt_offset' ) * 3600;
	}
}

function get_path( $level = 1 ) {
	$request = parse_url($_SERVER['REQUEST_URI']);
	$path = $request['path'];
	$bbpath = bb_get_option('path');
	$path = preg_replace("#$bbpath#",'',$path,1);
	$url = explode('/',$path);
	return $url[$level];
}

function add_topic_tag( $topic_id, $tag ) {
	global $bbdb, $bb_cache, $bb_current_user;
	$topic_id = (int) $topic_id;
	if ( !$topic = get_topic( $topic_id ) )
		return false;
	if ( !bb_current_user_can( 'add_tag_to', $topic_id ) )
		return false;
	if ( !$tag_id = create_tag( $tag ) )
		return false;

	$now    = bb_current_time('mysql');
	if ( $user_already = $bbdb->get_var("SELECT user_id FROM $bbdb->tagged WHERE tag_id = '$tag_id' AND topic_id='$topic_id'") )
		if ( $user_already == $bb_current_user->ID ) :
			do_action('bb_already_tagged', $tag_id, $bb_current_user->ID, $topic_id);
			return true;
		endif;
	$bbdb->query("INSERT INTO $bbdb->tagged 
	( tag_id, user_id, topic_id, tagged_on )
	VALUES
	( '$tag_id', '$bb_current_user->ID', '$topic_id', '$now')");
	if ( !$user_already ) {
		$bbdb->query("UPDATE $bbdb->tags SET tag_count = tag_count + 1 WHERE tag_id = '$tag_id'");
		$bbdb->query("UPDATE $bbdb->topics SET tag_count = tag_count + 1 WHERE topic_id = '$topic_id'");
		$bb_cache->flush_one( 'topic', $topic_id );
	}
	do_action('bb_tag_added', $tag_id, $bb_current_user->ID, $topic_id);
	return true;
}

function add_topic_tags( $topic_id, $tags ) {
	global $bbdb, $bb_current_user;

	$tags = trim( $tags );
	$words = preg_split("/[\s,]+/", $tags);

	if ( !is_array( $words ) )
		return false;

	foreach ( $words as $tag ) :
		add_topic_tag( $topic_id, $tag );
	endforeach;
	return true;
}

function create_tag( $tag ) {
	global $bbdb;
	$raw_tag = $tag;
	$tag     = tag_sanitize( $tag );
	if ( empty( $tag ) )
		return false;
	if ( $exists = $bbdb->get_var("SELECT tag_id FROM $bbdb->tags WHERE tag = '$tag'") )
		return $exists;

	$bbdb->query("INSERT INTO $bbdb->tags ( tag, raw_tag ) VALUES ( '$tag', '$raw_tag' )");
	do_action('bb_tag_created', $raw_tag, $bbdb->insert_id);
	return $bbdb->insert_id;
}

function rename_tag( $tag_id, $tag ) {
	global $bbdb, $bb_current_user;
	if ( !bb_current_user_can('manage_tags') )
		return false;
	$raw_tag = $tag;
	$tag     = tag_sanitize( $tag ); 

	if ( empty( $tag ) )
		return false;
	if ( $bbdb->get_var("SELECT tag_id FROM $bbdb->tags WHERE tag = '$tag' AND tag_id <> '$tag_id'") )
		return false;

	$old_tag = get_tag( $tag_id );

	if ( $bbdb->query("UPDATE $bbdb->tags SET tag = '$tag', raw_tag = '$raw_tag' WHERE tag_id = '$tag_id'") ) {
		do_action('bb_tag_renamed', $tag_id, $old_tag->raw_tag, $raw_tag );
		return get_tag( $tag_id );
	}
	return false;
}

function remove_topic_tag( $tag_id, $user_id, $topic_id ) {
	global $bbdb, $bb_cache, $bb_current_user;
	$tag_id = (int) $tag_id;
	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;
	if ( !$topic = get_topic( $topic_id ) )
		return false;
	if ( !bb_current_user_can( 'edit_tag_by_on', $user_id, $topic_id ) )
		return false;

	do_action('bb_pre_tag_removed', $tag_id, $user_id, $topic_id);

	$topics = array_flip((array) $bbdb->get_col("SELECT topic_id, COUNT(*) FROM $bbdb->tagged WHERE tag_id = '$tag_id' GROUP BY topic_id"));
	$counts = (array) $bbdb->get_col('', 1);
	if ( $tags = $bbdb->query("DELETE FROM $bbdb->tagged WHERE tag_id = '$tag_id' AND user_id = '$user_id' AND topic_id = '$topic_id'") ) :
		if ( 1 == $counts[$topics[$topic_id]] ) :
			$tagged = $bbdb->query("UPDATE $bbdb->tags SET tag_count = tag_count - 1 WHERE tag_id = '$tag_id'");
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = tag_count - 1 WHERE topic_id = '$topic_id'");
			$bb_cache->flush_one( 'topic', $topic_id );
			if ( 1 == count($counts) )
				$destroyed = destroy_tag( $tag_id );
		endif;
	endif;
	return array( 'tags' => $tags, 'tagged' => $tagged, 'destroyed' => $destroyed );
}

// merge $old_id into $new_id.  MySQL 4.0 can't do IN on tuples!
function merge_tags( $old_id, $new_id ) {
	global $bbdb, $bb_current_user;
	if ( !bb_current_user_can('manage_tags') )
		return false;
	if ( $old_id == $new_id )
		return false;

	do_action('bb_pre_merge_tags', $old_id, $new_id);

	$tagged_del = 0;
	if ( $old_topic_ids = (array) $bbdb->get_col( "SELECT topic_id FROM $bbdb->tagged WHERE tag_id = '$old_id'" ) ) {
		$old_topic_ids = join(',', $old_topic_ids);
		$shared_topics_u = (array) $bbdb->get_col( "SELECT user_id, topic_id FROM $bbdb->tagged WHERE tag_id = '$new_id' AND topic_id IN ($old_topic_ids)" );
		$shared_topics_i = (array) $bbdb->get_col( '', 1 );
		foreach ( $shared_topics_i as $t => $topic_id ) {
			$tagged_del += $bbdb->query( "DELETE FROM $bbdb->tagged WHERE tag_id = '$old_id' AND user_id = '{$shared_topics_u[$t]}' AND topic_id = '$topic_id'" );
			$count = $bbdb->get_var( "SELECT COUNT(DISTINCT tag_id) FROM $bbdb->tagged WHERE topic_id = '$topic_id' GROUP BY topic_id" );
			$bbdb->query( "UPDATE $bbdb->topics SET tag_count = $count WHERE topic_id = '$topic_id'" );
		}
	}

	if ( $diff_count = $bbdb->query( "UPDATE $bbdb->tagged SET tag_id = '$new_id' WHERE tag_id = '$old_id'" ) ) {
		$count = $bbdb->get_var( "SELECT COUNT(DISTINCT topic_id) FROM $bbdb->tagged WHERE tag_id = '$new_id' GROUP BY tag_id" );
		$bbdb->query( "UPDATE $bbdb->tags SET tag_count = $count WHERE tag_id = '$new_id'" );
	}

	// return values and destroy the old tag
	return array( 'destroyed' => destroy_tag( $old_id ), 'old_count' => $diff_count + $tagged_del, 'diff_count' => $diff_count );
}

function destroy_tag( $tag_id ) {
	global $bbdb, $bb_cache, $bb_current_user;
	if ( !bb_current_user_can('manage_tags') ) 
		return false;

	do_action('bb_pre_destroy_tag', $tag_id);

	if ( $tags = $bbdb->query("DELETE FROM $bbdb->tags WHERE tag_id = '$tag_id'") ) {
		if ( $topics = (array) $bbdb->get_col("SELECT DISTINCT topic_id FROM $bbdb->tagged WHERE tag_id = '$tag_id'") ) {
			$topics = join(',', $topics);
			$bbdb->query("UPDATE $bbdb->topics SET tag_count = tag_count - 1 WHERE topic_id IN ($topics)");
			$bb_cache->flush_one( 'topic', $topic_id );
		}	
		$tagged = $bbdb->query("DELETE FROM $bbdb->tagged WHERE tag_id = '$tag_id'");
	}
	return array( 'tags' => $tags, 'tagged' => $tagged );
}

function get_tag_id( $tag ) {
	global $bbdb;
	$tag     = tag_sanitize( $tag );

	return $bbdb->get_var("SELECT tag_id FROM $bbdb->tags WHERE tag = '$tag'");
}

function get_tag( $id ) {
	global $bbdb;
	$id = (int) $id;
	return $bbdb->get_row("SELECT * FROM $bbdb->tags WHERE tag_id = '$id'");
}

function get_tag_by_name( $tag ) {
	global $bbdb, $tag_cache;

	$tag = tag_sanitize( $tag );

	if ( isset($tag_cache[$tag]) )
		return $tag_cache[$tag];

	return $bbdb->get_row("SELECT * FROM $bbdb->tags WHERE tag = '$tag'");
}

function get_topic_tags ( $topic_id ) {
	global $topic_tag_cache, $bbdb;
	
	if ( isset ($topic_tag_cache[$topic_id] ) )
		return $topic_tag_cache[$topic_id];

	$topic_tag_cache[$topic_id] = $bbdb->get_results("SELECT * FROM $bbdb->tagged RIGHT JOIN $bbdb->tags ON ($bbdb->tags.tag_id = $bbdb->tagged.tag_id) WHERE topic_id = '$topic_id'");
	
	return $topic_tag_cache[$topic_id];
}

function get_user_tags ( $topic_id, $user_id ) {
	$tags = get_topic_tags ( $topic_id );
	if ( !is_array( $tags ) )
		return;
	$user_tags = array();

	foreach ( $tags as $tag ) :
		if ( $tag->user_id == $user_id )
			$user_tags[] = $tag;
	endforeach;
	return $user_tags;
}

function get_other_tags ( $topic_id, $user_id ) {
	$tags = get_topic_tags ( $topic_id );
	if ( !is_array( $tags ) )
		return;
	$other_tags = array();

	foreach ( $tags as $tag ) :
		if ( $tag->user_id != $user_id )
			$other_tags[] = $tag;
	endforeach;
	return $other_tags;
}

function get_public_tags ( $topic_id ) {
	$tags = get_topic_tags ( $topic_id );
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

function get_tagged_topic_ids( $tag_id ) {
	global $bbdb, $tagged_topic_count;
	$tag_id = (int) $tag_id;
	if ( $topic_ids = (array) $bbdb->get_col("SELECT DISTINCT topic_id FROM $bbdb->tagged WHERE tag_id = '$tag_id' ORDER BY tagged_on DESC") ) {
		$tagged_topic_count = count($topic_ids);
		return apply_filters('get_tagged_topic_ids', $topic_ids);
	} else {
		$tagged_topic_count = 0;
		return false;
	}
}

function get_tagged_topics( $tag_id, $page = 1 ) {
	global $bbdb, $bb_last_countable_query;
	if ( !$topic_ids = get_tagged_topic_ids( $tag_id ) )
		return false;
	$topic_ids = join($topic_ids, ',');
	$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$bb_last_countable_query = "SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids) AND topic_status = 0 ORDER BY topic_time DESC LIMIT $limit";
	if ( $topics = $bbdb->get_results($bb_last_countable_query) )
		return bb_append_meta( $topics, 'topic' );
	else	return false;
}

function get_tagged_topic_posts( $tag_id, $page = 1 ) {
	global $bbdb, $bb_post_cache;
	if ( !$topic_ids = get_tagged_topic_ids( $tag_id ) )
		return false;
	$topic_ids = join($topic_ids, ',');
	$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	if ( $posts = $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE topic_id IN ($topic_ids) AND post_status = 0 ORDER BY post_time DESC LIMIT $limit") ) {
		foreach ( $posts as $bb_post )
			$bb_post_cache[$bb_post->post_id] = $bb_post;
		return $posts;
	} else { return false; }
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

function get_top_tags( $recent = true, $limit = 40 ) {
	global $bbdb, $tag_cache;
	foreach ( (array) $tags = $bbdb->get_results("SELECT * FROM $bbdb->tags ORDER BY tag_count DESC LIMIT $limit") as $tag )
		$tag_cache[$tag->tag] = $tag;
	return $tags;
}


function bb_send_headers() {
	@header('Content-type: ' . bb_get_option( 'html_type' ) . '; charset=' . bb_get_option( 'charset' ));
	do_action( 'bb_send_headers' );
}

// Inspired by and adapted from Yung-Lung Scott YANG's http://scott.yang.id.au/2005/05/permalink-redirect/ (GPL)
function bb_repermalink() {
	global $page;
	$uri = $_SERVER['REQUEST_URI'];
	if ( isset($_GET['id']) )
		$permalink = (int) $_GET['id'];
	else
		$permalink = intval( get_path() );

	do_action( 'pre_permalink', $permalink );

	if ( is_forum() ) {
		global $forum_id;
		$forum_id = $permalink;
		$permalink = get_forum_link( $permalink, $page );
	} elseif ( is_topic() ) {
		global $topic_id;
		$topic_id = $permalink;
		$permalink = get_topic_link( $permalink, $page );
	} elseif ( is_bb_profile() ) { // This handles the admin side of the profile as well.
		global $user_id, $profile_hooks, $self;
		$user_id = $permalink;
		global_profile_menu_structure();
		$valid = false;
		if ( $tab = isset($_GET['tab']) ? $_GET['tab'] : get_path(2) )
			foreach ( $profile_hooks as $valid_file => $valid_tab )
				if ( $tab == $valid_tab ) {
					$valid = true;
					$self = $valid_file;
				}
		if ( $valid ) :
			$permalink = get_profile_tab_link( $permalink, $tab, $page );
		else :
			$permalink = get_user_profile_link( $permalink, $page );
			unset($self, $tab);
		endif;
	} elseif ( is_bb_favorites() ) {
		$permalink = get_favorites_link();
	} elseif ( is_tag() ) {  // It's not an integer and tags.php pulls double duty.
		if ( isset($_GET['tag']) )
			$permalink = $_GET['tag'];
		else
			$permalink = get_path();
		if ( !$permalink )
			$permalink = get_tag_page_link();
		else {
			global $tag, $tag_name;
			$tag_name = $permalink;
			$tag = get_tag_by_name( $tag_name );
			$permalink = get_tag_link( 0, $page ); // 0 => grabs $tag from global.
		}
	} elseif ( is_view() ) { // Not an integer
		if ( isset($_GET['view']) )
			$permalink = $_GET['view'];
		else	$permalink = get_path();
		global $view;
		$view = $permalink;
		$permalink = get_view_link( $permalink, $page );
	} else { return; }

	parse_str($_SERVER['QUERY_STRING'], $args);
	if ( $args ) {
		$permalink = add_query_arg($args, $permalink);
			if ( bb_get_option('mod_rewrite') ) {
				$pretty_args = array('id', 'page', 'tag', 'tab'); // these are already specified in the path
				if ( is_view() )
					$pretty_args[] = 'view';
				foreach ( $pretty_args as $pretty_arg )
					$permalink = remove_query_arg( $pretty_arg, $permalink );
			}
	}

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
		if ( $check != $uri ) {
			if ( version_compare(phpversion(), '4.3.0', '>=') ) {
				header("Location: $permalink", true, 301);
			} else {
				header("Location: $permalink");
				status_header( 301 );
			}
			exit;
		}
	endif;
	do_action( 'post_permalink', $permalink );
}

// Profile/Admin
function global_profile_menu_structure() {
	global $bb_current_user, $user_id, $profile_menu, $profile_hooks;
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
		if ( can_access_tab( $profile_tab, $bb_current_user->ID, $user_id ) )
			$profile_hooks[$profile_tab[3]] = tag_sanitize($profile_tab[4]);

	do_action('bb_profile_menu');
	ksort($profile_menu);
}

function add_profile_tab($tab_title, $users_cap, $others_cap, $file, $arg = false) {
	global $profile_menu, $profile_hooks, $bb_current_user, $user_id;

	$arg = $arg ? $arg : $tab_title;

	$profile_tab = array($tab_title, $users_cap, $others_cap, $file, $arg);
	$profile_menu[] = $profile_tab;
	if ( can_access_tab( $profile_tab, $bb_current_user->ID, $user_id ) )
		$profile_hooks[$file] = tag_sanitize($arg);
}

function can_access_tab( $profile_tab, $viewer_id, $owner_id ) {
	global $bb_current_user;
	$viewer_id = (int) $viewer_id;
	$owner_id = (int) $owner_id;
	if ( $viewer_id == $bb_current_user->ID )
		$viewer =& $bb_current_user;
	else
		$viewer = new BB_User( $viewer_id );
	if ( !$viewer )
		return false;

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
	return apply_filters(
		'get_profile_info_keys',
		array('user_email' => array(1, __('Email')), 'user_url' => array(0, __('Website')), 'from' => array(0, __('Location')), 'occ' => array(0, __('Occupation')), 'interest' => array(0, __('Interests')))
	);
}

function get_profile_admin_keys() {
	global $bb_table_prefix;
	return apply_filters(
		'get_profile_admin_keys',
		array($bb_table_prefix . 'title' => array(0, __('Custom Title')))
	);
}

function get_assignable_caps() {
	return apply_filters(
		'get_assignable_caps',
		array('throttle' => __('Ignore the 30 second post throttling limit'))
	);
}

function get_views( $cache = true ) {
	global $bb_current_user, $views;
	if ( isset($views) && $cache )
		return $views;
	
	$views = array(
		'no-replies' => __('Topics with no replies'),
		'untagged' => __('Topics with no tags')
	);
	
	$views = apply_filters('bb_views', $views);
	return $views;
}

function bb_nonce_url($actionurl, $action = -1) {
	return wp_specialchars(add_query_arg('_wpnonce', bb_create_nonce($action), $actionurl));
}

function bb_nonce_field($action = -1) {
	echo '<input type="hidden" name="_wpnonce" value="' . bb_create_nonce($action) . '" />';
	wp_referer_field();
}

function bb_nonce_ays($action) {
	$adminurl = bb_get_option( 'uri' ) . '/bb-admin';
	if ( wp_get_referer() )
		$adminurl = wp_get_referer();

	$title = __('bbPress Confirmation');
	// Remove extra layer of slashes.
	$_POST   = stripslashes_deep( $_POST );
	if ( $_POST ) {
		$q = http_build_query($_POST);
		$q = explode( ini_get('arg_separator.output'), $q);
		$url = remove_query_arg( '_wpnonce' );
		$html .= "\t<form method='post' action='$url'>\n";
		foreach ( (array) $q as $a ) {
			$v = substr(strstr($a, '='), 1);
			$k = substr($a, 0, -(strlen($v)+1));
			$html .= "\t\t<input type='hidden' name='" . wp_specialchars( urldecode($k), 1 ) . "' value='" . wp_specialchars( urldecode($v), 1 ) . "' />\n";
		}
		$html .= "\t\t<input type='hidden' name='_wpnonce' value='" . bb_create_nonce($action) . "' />\n";
		$html .= "\t\t<div id='message' class='confirm fade'>\n\t\t<p>" . bb_explain_nonce($action) . "</p>\n\t\t<p><a href='$adminurl'>" . __('No') . "</a> <input type='submit' value='" . __('Yes') . "' /></p>\n\t\t</div>\n\t</form>\n";
	} else {
		$html .= "\t<div id='message' class='confirm fade'>\n\t<p>" . bb_explain_nonce($action) . "</p>\n\t<p><a href='$adminurl'>" . __('No') . "</a> <a href='" . add_query_arg( '_wpnonce', bb_create_nonce($action), $_SERVER['REQUEST_URI'] ) . "'>" . __('Yes') . "</a></p>\n\t</div>\n";
	}
	$html .= "</body>\n</html>";
	bb_die($html, $title);
}

function bb_die($message, $title = '') {
	global $bb_locale;

	header('Content-Type: text/html; charset=utf-8');

	if ( empty($title) )
		$title = __('bbPress &rsaquo; Error');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<title><?php echo $title ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" href="<?php bb_option('uri'); ?>bb-admin/install.css" type="text/css" />
<?php if ( ('rtl' == $bb_locale->text_direction) ) : ?>
	<link rel="stylesheet" href="<?php bb_option('uri'); ?>bb-admin/install-rtl.css" type="text/css" />
<?php endif; ?>
</head>
<body>
	<h1 id="logo"><img alt="bbPress" src="<?php bb_option('uri'); ?>bb-images/bbpress.png" /></h1>
	<p><?php echo $message; ?></p>
</body>
</html>
<?php
	die();
}

function bb_explain_nonce($action) {
	if ( $action !== -1 && preg_match('/([a-z]+)-([a-z]+)(_(.+))?/', $action, $matches) ) {
		$verb = $matches[1];
		$noun = $matches[2];

		$trans = array();
		$trans['create']['post'] = array(__('Are you sure you want to submit this post?'), false);
		$trans['edit']['post'] = array(__('Are you sure you want to edit this post?'), false);
		$trans['delete']['post'] = array(__('Are you sure you want to delete this post?'), false);

		$trans['create']['topic'] = array(__('Are you sure you want to create this topic?'), false);
		$trans['resolve']['topic'] = array(__('Are you sure you want to change the resolution status of this topic?'), false);
		$trans['delete']['topic'] = array(__('Are you sure you want to delete this topic?'), false);
		$trans['close']['topic'] = array(__('Are you sure you want to change the status of this topic?'), false);
		$trans['stick']['topic'] = array(__('Are you sure you want to change the sticky status of this topic?'), false);
		$trans['move']['topic'] = array(__('Are you sure you want to move this topic?'), false);

		$trans['add']['tag'] = array(__('Are you sure you want to add this tag to this topic?'), false);
		$trans['rename']['tag'] = array(__('Are you sure you want to rename this tag?'), false);
		$trans['merge']['tag'] = array(__('Are you sure you want to submit these tags?'), false);
		$trans['destroy']['tag'] = array(__('Are you sure you want to destroy this tag?'), false);
		$trans['remove']['tag'] = array(__('Are you sure you want to remove this tag from this topic?'), false);

		$trans['toggle']['favorite'] = array(__('Are you sure you want to toggle your favorite status for this topic?'), false);

		$trans['edit']['profile'] = array(__("Are you sure you want to edit this user's profile?"), false);

		$trans['add']['forum'] = array(__("Are you sure you want to add this forum?"), false);
		$trans['update']['forums'] = array(__("Are you sure you want to update your forums?"), false);

		$trans['do']['counts'] = array(__("Are you sure you want to recount these items?"), false);

		if ( isset($trans[$verb][$noun]) ) {
			if ( !empty($trans[$verb][$noun][1]) ) {
				$lookup = $trans[$verb][$noun][1];
				$object = $matches[4];
				if ( 'use_id' != $lookup )
					$object = call_user_func($lookup, $object);
				return sprintf($trans[$verb][$noun][0], $object);
			} else {
				return $trans[$verb][$noun][0];
			}
		}
	}

	return apply_filters( 'bb_explain_nonce_' . $verb . '-' . $noun, __('Are you sure you want to do this?'), $matches[4] );
}

function bb_count_last_query() {
	global $bbdb, $bb_last_countable_query;
	if ( $bb_last_countable_query )
		$q = $bb_last_countable_query;
	else
		$q = $bbdb->last_query;

	if ( false === strpos($q, 'SELECT') )
		return false;

	$q = preg_replace(
		array('/SELECT.*?\s+FROM/', '/LIMIT [0-9]+(\s*,\s*[0-9]+)?/'),
		array('SELECT COUNT(*) FROM', ''),
		$q
	);
	$bb_last_countable_query = '';
	return $bbdb->get_var($q);
}

function bb_trusted_roles() {
	return apply_filters( 'bb_trusted_roles', array('moderator', 'administrator', 'keymaster') );
}

function bb_get_active_theme_folder() {
	$activetheme = bb_get_option( 'bb_active_theme' );
	if ( !$activetheme )
		$activetheme = BBPATH . 'bb-templates/kakumei';

	return apply_filters( 'bb_get_active_theme_folder', $activetheme );
}

function bb_get_themes() {
	$r = array();

	$theme_roots = array(BBPATH . 'bb-templates/', BBTHEMEDIR . '/');
	foreach ( $theme_roots as $theme_root )
		if ( $themes_dir = @dir($theme_root) )
			while( ( $theme_dir = $themes_dir->read() ) !== false )
				if ( is_dir($theme_root . $theme_dir) && is_readable($theme_root . $theme_dir) && '.' != $theme_dir{0} )
					$r[$theme_dir] = $theme_root . $theme_dir;

	ksort($r);
	return $r;
}

function bb_parse_args( $args, $defaults = '' ) {
	if ( is_array($args) )
		$r =& $args;
	else
		parse_str( $args, $r );

	if ( is_array($defaults) ) :
		extract($defaults);
		extract($r);
		return compact(array_keys($defaults)); // only those options defined in $defaults
	else :
		return $r;
	endif;
}	

/* Searh Functions */
function bb_user_search( $args = '' ) {
	global $bbdb, $bb_last_countable_query;

	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'query' => $args );

	$defaults = array( 'query' => '', 'append_meta' => true, 'user_login' => true, 'display_name' => true, 'user_nicename' => false, 'user_url' => true, 'user_email' => false, 'user_meta' => false, 'users_per_page' => false, 'page' => false );

	extract(bb_parse_args( $args, $defaults ));

	if ( $query && strlen( preg_replace('/[^a-z0-9]/i', '', $query) ) < 3 )
		return new WP_Error( 'invalid-query', __('Your search term was too short') );

	if ( !$page )
		$page = $GLOBALS['page'];

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
		$bb_last_countable_query = "SELECT user_id FROM $bbdb->usermeta WHERE meta_value LIKE ('%$likeit')";
		if ( empty($fields) )
			$bb_last_countable_query .= " LIMIT $limit";
		$user_meta_ids = $bbdb->get_col($bb_last_countable_query);
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

	$bb_last_countable_query = $sql .= ( $sql_terms ? ' WHERE ' . implode(' OR ', $sql_terms) : '' ) . " LIMIT $limit";

	if ( ( $users = $bbdb->get_results($sql) ) && $append_meta )
		return bb_append_meta( $users, 'user' );

	return $users ? $users : false;
}

function bb_tag_search( $args = '' ) {
	global $page, $bbdb, $bb_last_countable_query, $tag_cache;

	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'query' => $args );

	$defaults = array( 'query' => '', 'tags_per_page' => false );

	extract(bb_parse_args( $args, $defaults ));

	if ( strlen( preg_replace('/[^a-z0-9]/i', '', $query) ) < 3 )
		return new WP_Error( 'invalid-query', __('Your search term was too short') );

	$query = $bbdb->escape( $query );

	$limit = 0 < (int) $tags_per_page ? (int) $tags_per_page : bb_get_option( 'page_topics' );
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";

	$likeit = preg_replace('/\s+/', '%', $query);

	$bb_last_countable_query = "SELECT * FROM $bbdb->tags WHERE raw_tag LIKE ('%$likeit%') LIMIT $limit";

	foreach ( (array) $tags = $bbdb->get_results( $bb_last_countable_query ) as $tag )
		$tag_cache[$tag->tag] = $tag;

	return $tags ? $tags : false;
}

function bb_related_tags( $_tag = false, $number = 40 ) {
	global $bbdb, $tag_cache, $tag;;
	if ( is_numeric($_tag) )
		$_tag = get_tag( $_tag );
	elseif ( is_string($_tag) )
		$_tag = get_tag_by_name( $_tag );
	elseif ( false === $_tag )
		$_tag =& $tag;

	if ( !$_tag )
		return false;

	$sql = "SELECT tag.tag_id, tag.tag, tag.raw_tag, COUNT(DISTINCT t.topic_id) AS tag_count
	           FROM $bbdb->tagged AS t
	           JOIN $bbdb->tagged AS tt  ON (t.topic_id = tt.topic_id)
	           JOIN $bbdb->tags   AS tag ON (t.tag_id = tag.tag_id)
	        WHERE tt.tag_id = '$_tag->tag_id' AND t.tag_id != '$_tag->tag_id' GROUP BY t.tag_id ORDER BY tag_count DESC";

	foreach ( (array) $tags = $bbdb->get_results( $sql ) as $_tag )
		$tag_cache[$_tag->tag] = $_tag;

	return $tags;
}

?>
