<?php

function get_forums() {
	global $bb_cache;
	return $bb_cache->get_forums();
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

function get_thread_post_ids ( $topic_id ) {
	global $bbdb, $thread_ids_cache;
	if ( !isset( $thread_ids_cache[$topic_id] ) ) {
		$where = bb_apply_filters('get_thread_post_ids_where', 'AND post_status = 0');
		$thread_ids_cache[$topic_id]['post'] = $bbdb->get_col("SELECT post_id, poster_id FROM $bbdb->posts WHERE topic_id = $topic_id $where ORDER BY post_time");
		$thread_ids_cache[$topic_id]['poster'] = $bbdb->get_col('', 1);
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
	global $bbdb, $bb;
	$forum = (int) $forum;
	$page = (int) $page;
	$where = 'WHERE topic_status = 0';
	if ( $forum )
		$where .= " AND forum_id = $forum ";
	if ( !empty( $exclude ) )
		$where .= " AND forum_id NOT IN ('$exclude') ";
	if ( is_front() )
		$where .= " AND topic_sticky <> 2 ";
	elseif ( is_forum() )
		$where .= " AND topic_sticky = 0 ";
	$limit = bb_get_option('page_topics');
	$where = bb_apply_filters('get_latest_topics_where', $where);
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	if ( $topics = $bbdb->get_results("SELECT * FROM $bbdb->topics $where ORDER BY topic_time DESC LIMIT $limit") )
		return bb_append_meta( $topics, 'topic' );
	else	return false;
}

function get_sticky_topics( $forum = 0, $display = 1 ) {
	global $bbdb, $bb;
	if ( 1 != $display )
		return false;
	$forum = (int) $forum;
	if ( is_front() )
		$where = 'WHERE topic_sticky = 2  AND topic_status = 0';
	else	$where = 'WHERE topic_sticky <> 0 AND topic_status = 0';
	if ( $forum )
		$where .= " AND forum_id = $forum ";
	$where = bb_apply_filters('get_sticky_topics_where', $where);
	if ( $stickies = $bbdb->get_results("SELECT * FROM $bbdb->topics $where ORDER BY topic_time DESC") )
		return bb_append_meta( $stickies, 'topic' );	
	else	return false;
}

function no_replies( $where ) {
	return $where . ' AND topic_posts = 1 ';
}

function untagged( $where ) {
	return $where . ' AND tag_count = 0 ';
}

function unresolved( $where ) {
	return $where . " AND topic_resolved = 'no' ";
}

function deleted_topics( $where ) {
	return str_replace('topic_status = 0', 'topic_status = 1', $where);
}

function no_where( $where ) {
	return;
}

function get_latest_posts( $limit, $page = 1 ) {
	global $bbdb;
	$limit = (int) $limit;
	if ( !$limit )
		$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$where = bb_apply_filters('get_latest_posts_where', 'WHERE post_status = 0');
	return $bbdb->get_results("SELECT * FROM $bbdb->posts $where ORDER BY post_time DESC LIMIT $limit");
}

function get_user_favorites( $user_id, $list = false ) {
	global $bbdb;
	$user = bb_get_user( $user_id );
	if ( $user->favorites )
		if ( $list )
			return $bbdb->get_results("
				SELECT topic_id, topic_title, topic_time, topic_open, topic_posts FROM $bbdb->topics
				WHERE topic_status = 0 AND topic_id IN ($user->favorites) ORDER BY topic_time");
		else
			return $bbdb->get_results("
				SELECT * FROM $bbdb->posts WHERE post_status = 0 AND topic_id IN ($user->favorites)
				ORDER BY post_time DESC LIMIT 20");
}

function is_user_favorite( $user_id = 0, $topic_id = 0 ) {
	if ( $user_id )
		$user = bb_get_user( $user_id );
	else 	global $user;
	if ( $topic_id )
		$topic = get_topic( $topic_id );
	else	global $topic;
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
	bb_do_action('bb_add_user_favorite', serialize(array('user_id' => $user_id, 'topic_id' => $topic_id)));
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
	bb_do_action('bb_remove_user_favorite', serialize(array('user_id' => $user_id, 'topic_id' => $topic_id)));
	return true;
}

function get_recent_user_replies( $user_id ) {
	global $bbdb, $bb_post_cache, $page;
	$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$where = bb_apply_filters('get_recent_user_replies', 'AND post_status = 0');
	$posts = $bbdb->get_results("SELECT *, MAX(post_time) as post_time FROM $bbdb->posts WHERE poster_id = $user_id $where GROUP BY topic_id ORDER BY post_time DESC LIMIT $limit");
	if ( $posts ) :
		foreach ($posts as $bb_post) {
			$bb_post_cache[$bb_post->post_id] = $bb_post;
			$topics[] = $bb_post->topic_id;
		}
		$topic_ids = join(',', $topics);
		$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)");
		bb_append_meta( $topics, 'topic' );
		return $posts;
	else :
		return false;
	endif;
}

function get_recent_user_threads( $user_id ) {
	global $bbdb, $page;
	$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$where = bb_apply_filters('get_recent_user_threads_where', 'AND topic_status = 0');
	$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_poster = $user_id $where ORDER BY topic_start_time DESC LIMIT $limit");
	if ( $topics )
		$topic = bb_append_meta( $topics, 'topic' );
	return $topics;
}

//expects $item = 1 to be the first, not 0
function get_page_number( $item, $per_page = 0 ) {
	if ( !$per_page )
		$per_page = bb_get_option('page_topics');
	return intval( ceil( $item / $per_page ) ); // page 1 is the first page
}

function bb_apply_filters($tag, $string, $filter = true) {
	global $wp_filter;
	if (isset($wp_filter['all'])) {
		foreach ($wp_filter['all'] as $priority => $functions) {
			if (isset($wp_filter[$tag][$priority]))
				$wp_filter[$tag][$priority] = array_merge($wp_filter['all'][$priority], $wp_filter[$tag][$priority]);
			else
				$wp_filter[$tag][$priority] = array_merge($wp_filter['all'][$priority], array());
			$wp_filter[$tag][$priority] = array_unique($wp_filter[$tag][$priority]);
		}

	}

	if (isset($wp_filter[$tag])) {
		ksort($wp_filter[$tag]);
		foreach ($wp_filter[$tag] as $priority => $functions) {
			if (!is_null($functions)) {
				foreach($functions as $function) {
					if ($filter)
						$string = call_user_func($function, $string);
					else
						call_user_func($function, $string);
				}
			}
		}
	}
	return $string;
}

function bb_add_filter($tag, $function_to_add, $priority = 10) {
	global $wp_filter;
	// So the format is wp_filter['tag']['array of priorities']['array of functions']
	if (!@in_array($function_to_add, $wp_filter[$tag]["$priority"])) {
		$wp_filter[$tag]["$priority"][] = $function_to_add;
	}
	return true;
}

function bb_remove_filter($tag, $function_to_remove, $priority = 10) {
	global $wp_filter;
	if (@in_array($function_to_remove, $wp_filter[$tag]["$priority"])) {
		foreach ($wp_filter[$tag]["$priority"] as $function) {
			if ($function_to_remove != $function) {
				$new_function_list[] = $function;
			}
		}
		if ( isset($new_function_list) )
			$wp_filter[$tag]["$priority"] = $new_function_list;
		else	unset($wp_filter[$tag]["$priority"]);
	}
	return true;
}

// The *_action functions are just aliases for the *_filter functions, they take special strings instead of generic content

function bb_do_action($tag) {
	$string = ( 1 < func_num_args() ) ? func_get_arg(1) : '';
	bb_apply_filters($tag, $string, false);
	return $string;
}

function bb_add_action($tag, $function_to_add, $priority = 10) {
	bb_add_filter($tag, $function_to_add, $priority);
}

function bb_remove_action($tag, $function_to_remove, $priority = 10) {
	bb_remove_filter($tag, $function_to_remove, $priority);
}

function bb_timer_stop($display = 0, $precision = 3) { //if called like bb_timer_stop(1), will echo $timetotal
	global $bb_timestart, $timeend;
	$mtime = explode(' ', microtime());
	$timeend = $mtime[1] + $mtime[0];
	$timetotal = $timeend - $bb_timestart;
	if ($display)
		echo number_format($timetotal,$precision);
	return $timetotal;
}

function bb_since( $original, $do_more = 0 ) {
	// array of time period chunks
	$chunks = array(
		array(60 * 60 * 24 * 365 , 'year'),
		array(60 * 60 * 24 * 30 , 'month'),
		array(60 * 60 * 24 * 7, 'week'),
		array(60 * 60 * 24 , 'day'),
		array(60 * 60 , 'hour'),
		array(60 , 'minute'),
	);
	
	$today = time();
	$since = $today - bb_offset_time($original);
	
	for ($i = 0, $j = count($chunks); $i < $j; $i++) {
		$seconds = $chunks[$i][0];
		$name = $chunks[$i][1];
		
		if (($count = floor($since / $seconds)) != 0)
			break;
	}
	
	$print = ($count == 1) ? '1 '.$name : "$count {$name}s";
	
	if ($i + 1 < $j) {
		$seconds2 = $chunks[$i + 1][0];
		$name2 = $chunks[$i + 1][1];
		
		// add second item if it's greater than 0
		if ( (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) && $do_more )
			$print .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
	}
	return $print;
}

function bb_get_option( $option ) {
	global $bb;

	switch ( $option ) :
	case 'uri' :
		return $bb->domain . $bb->path;
		break;
	case 'name' :
		return $bb->name;
		break;
	case 'page_topics' : 
		return $bb->page_topics;
		break;
	case 'mod_rewrite' : 
		return $bb->mod_rewrite;
		break;
	case 'path' : 
		return $bb->path;
		break;
	case 'domain' :
		return $bb->domain;
		break;
	case 'admin_email' : 
		return $bb->admin_email;
		break;
	case 'edit_lock' :
		return $bb->edit_lock;
		break;
	case 'version' :
		return 'Version e<sup>i&#960;</sup>+1... and a half&#8212;&#945;';
		break; 
	endswitch;
}

function option( $option ) {
	echo bb_get_option( $option ) ;
}

function bb_add_query_arg() {
	$ret = '';
	if( is_array( func_get_arg(0) ) )
		$uri = @func_get_arg(1);
	else
		$uri = @func_get_arg(2);
	if ( false === $uri )
		$uri = $_SERVER['REQUEST_URI'];

	if ( $frag = strstr($uri, '#') )
		$uri = substr($uri, 0, -strlen($frag));

	if ( false !== strpos($uri, '?') ) {
		$parts = explode('?', $uri, 2);
		if (1 == count($parts)) {
			$base = '?';
			$query = $parts[0];
		} else {
			$base = $parts[0] . '?';
			$query = $parts[1];
		}
	} else {
		$base = $uri . '?';
		$query = '';
	}
	parse_str($query, $qs);
	if (is_array(func_get_arg(0))) {
		$kayvees = func_get_arg(0);
		$qs = array_merge($qs, $kayvees);
	} else {
		$qs[func_get_arg(0)] = func_get_arg(1);
	}

	foreach($qs as $k => $v) {
		if($v != '') {
			if($ret != '') $ret .= '&';
			$ret .= "$k=$v";
		}
	}
	$ret = $base . $ret;   
	return trim($ret, '?') . ($frag ? $frag : '');
}

function bb_remove_query_arg($key, $query) {
	return bb_add_query_arg($key, '', $query);
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
	foreach ($posts as $bb_post)
		if ( 0 != $bb_post->poster_id )
			if ( !isset($bb_user_cache[$bb_post->poster_id]) ) // Don't cache what we already have
				$ids[] = $bb_post->poster_id;
	if ( isset($ids) )
		bb_cache_users(array_unique($ids), false); // false since we've already checked for soft cached data.
}

function bb_current_time( $type = 'timestamp' ) {
	global $bb;
	switch ($type) {
		case 'mysql':
			$d = gmdate('Y-m-d H:i:s');
			break;
		case 'timestamp':
			$d = time() - $bb->gmt_offset * 3600; //make this GMT
			break;
	}
	return $d;
}

//This is only used at initialization.  Use global $bb_current_user to grab user info.
function bb_current_user() {
	if ( defined( 'BB_INSTALLING' ) )
		return false;

	global $bbdb, $bb, $bb_cache, $bb_user_cache;
	if ( !isset($_COOKIE[ $bb->usercookie ]) )
		return false;
	if ( !isset($_COOKIE[ $bb->passcookie ]) )
		return false;
	$user = user_sanitize( $_COOKIE[ $bb->usercookie ] );
	$pass = user_sanitize( $_COOKIE[ $bb->passcookie ] );
	if ( $bb_current_user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND MD5( user_pass ) = '$pass' AND user_status % 2 = 0") ) {
		$bb_current_user = $bb_cache->append_current_user_meta( $bb_current_user );
		return new BB_User($bb_current_user->ID);
	} else 	$bb_user_cache[$bb_current_user->ID] = false;
	return false;
}

function bb_get_user( $user_id, $cache = true ) {
	global $bb_cache, $bb_user_cache;
	if ( !is_numeric( $user_id ) )
		die('bb_get_user needs a numeric ID');
	$user_id = (int) $user_id;
	if ( isset( $bb_user_cache[$user_id] ) && $cache )
		return $bb_user_cache[$user_id];
	else
		return  $bb_cache->get_user( $user_id, $cache );
}

function bb_cache_users( $ids, $soft_cache = true ) {
	global $bb_cache;
	if ( $soft_cache )
		foreach( $ids as $i => $d )
			if ( isset($bb_user_cache[$d]) )
				unset($ids[i]); // Don't cache what we already have
	if ( 0 < count($ids) )
		$bb_cache->cache_users( $ids );
}

function bb_get_user_by_name( $name ) {
	global $bbdb;
	$name    = user_sanitize( $name );
	$user_id =  $bbdb->get_var("SELECT ID FROM $bbdb->users WHERE user_login = '$name'");
	return bb_get_user( $user_id );
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
				$trans[$meta->$field]->{$meta->meta_key} = cast_meta_value( $meta->meta_value );
				if ( strpos($meta->meta_key, $bb_table_prefix) === 0 )
					$trans[$meta->$field]->{substr($meta->meta_key, strlen($bb_table_prefix))} = cast_meta_value( $meta->meta_value );
			endforeach;
		foreach ( array_keys($trans) as $i )
			$cache[$i] = $trans[$i];
		return $object;
	elseif ( $object ) :
		if ( $metas = $bbdb->get_results("SELECT meta_key, meta_value FROM $table WHERE $field = '{$object->$id}'") )
			foreach ( $metas as $meta ) :
				$object->{$meta->meta_key} = cast_meta_value( $meta->meta_value );
				if ( strpos($meta->meta_key, $bb_table_prefix) === 0 )
					$object->{substr($meta->meta_key, strlen($bb_table_prefix))} = cast_meta_value( $meta->meta_value );
			endforeach;
		$cache[$object->$id] = $object;
		return $object;
	endif;
}

function cast_meta_value( $value ) {
	$value = stripslashes($value);
	@ $r = unserialize($value);
	if ( false === $r )
		$r = $value;
	return $r;
}

function bb_check_login($user, $pass) {
	global $bbdb;
	$user = user_sanitize( $user );
	$pass = user_sanitize( md5( $pass ) );
	return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND user_pass = '$pass'");
}

function bb_user_exists( $user ) {
	global $bbdb;
	$user = user_sanitize( $user );
	return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user'");
}

// delete_user
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

function bb_update_topicmeta( $topic_id, $meta_key, $meta_value ) {
	return bb_update_meta( $topic_id, $meta_key, $meta_value, 'topic' );
}

function bb_update_meta( $type_id, $meta_key, $meta_value, $type ) {
	global $bbdb, $bb_cache, $bb_table_prefix;
	if ( !is_numeric( $type_id ) )
		return false;
	switch ( $type ) :
	case 'user' :
		$table = $bbdb->usermeta;
		$field = 'user_id';
		break;
	case 'topic' :
		$table = $bbdb->topicmeta;
		$field = 'topic_id';
		break;
	endswitch;

	$meta_key = preg_replace('|[^a-z0-9_]|i', '', $meta_key);
	if ( 'user' == $type && 'capabilities' == $meta_key )
		$meta_key = $bb_table_prefix . 'capabilities';

	$meta_tuple = compact('type_id', 'meta_key', 'meta_value', 'type');
	$meta_tuple = bb_apply_filters('bb_update_meta', $meta_tuple);
	extract($meta_tuple, EXTR_OVERWRITE);

	if ( is_array($meta_value) || is_object($meta_value) )
		$meta_value = serialize($meta_value);
	$meta_value = $bbdb->escape( $meta_value );

	$cur = $bbdb->get_row("SELECT * FROM $table WHERE $field = '$type_id' AND meta_key = '$meta_key'");
	if ( !$cur ) {
		$bbdb->query("INSERT INTO $table ( $field, meta_key, meta_value )
		VALUES
		( '$type_id', '$meta_key', '$meta_value' )");
		$bb_cache->flush_one( $type, $type_id );
		return true;
	}
	if ( $cur->meta_value != $meta_value ) {
		$bbdb->query("UPDATE $table SET meta_value = '$meta_value' WHERE $field = '$type_id' AND meta_key = '$meta_key'");
		$bb_cache->flush_one( $type, $type_id );
	}
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

function bb_new_topic( $title, $forum, $tags = '' ) {
	global $bbdb, $bb_cache, $bb_current_user;
	$title = bb_apply_filters('pre_topic_title', $title);
	$forum = (int) $forum;
	$now   = bb_current_time('mysql');

	if ( $forum && $title ) {
		$bbdb->query("INSERT INTO $bbdb->topics 
		(topic_title, topic_poster, topic_poster_name, topic_last_poster, topic_last_poster_name, topic_start_time, topic_time, forum_id)
		VALUES
		('$title', $bb_current_user->ID, '$bb_current_user->data->user_login', $bb_current_user->ID, '$bb_current_user->data->user_login', '$now', '$now', $forum)");
		$topic_id = $bbdb->insert_id;
		if ( !empty( $tags ) )
			add_topic_tags( $topic_id, $tags );
		$bbdb->query("UPDATE $bbdb->forums SET topics = topics + 1 WHERE forum_id = $forum");
		$bb_cache->flush_many( 'forum', $forum_id );
		bb_do_action('bb_new_topic', $topic_id);
		return $topic_id;
	} else {
		return false;
	}
}

function bb_update_topic( $title, $topic_id ) {
	global $bbdb, $bb_cache;
	$title = bb_apply_filters('pre_topic_title', $title);
	$topic_id = (int) $topic_id;

	if ( $topic_id && $title ) {
		$bbdb->query("UPDATE $bbdb->topics SET topic_title = '$title' WHERE topic_id = $topic_id");
		$bb_cache->flush_one( 'topic', $topic_id );
		bb_do_action('bb_update_topic', $topic_id);
		return $topic_id;
	} else {
		return false;
	}
}

function bb_delete_topic( $topic_id ) {
	global $bb_cache;
	$topic_id = (int) $topic_id;
	if ( $topic = get_topic( $topic_id ) ) {
		$post_ids = get_thread_post_ids( $topic_id );
		$post_ids['post'] = array_reverse($post_ids['post']);
		foreach ( $post_ids['post'] as $post_id )
			bb_delete_post( $post_id );
		if ( $topic->topic_status ) {
			global $bb_table_prefix;
			$ids = array_unique($post_ids['poster']);
			foreach ( $ids as $id )
				if ( $user = bb_get_user( $id ) )
					bb_update_usermeta( $user->ID, $bb_table_prefix . 'topics_replied', $user->topics_replied + 1 );
			bb_do_action( 'bb_undelete_topic', $topic_id );
		}
		$bb_cache->flush_one( 'topic', $topic_id );
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
	$bb_post  = bb_apply_filters('pre_post', $bb_post);
	$tid   = (int) $topic_id;
	$now   = bb_current_time('mysql');
	$uid   = $bb_current_user->ID;
	$uname = $bb_current_user->data->user_login;
	$ip    = addslashes( $_SERVER['REMOTE_ADDR'] );

	$topic = get_topic( $tid );

	if ( $bb_post && $topic ) {
		$bbdb->query("INSERT INTO $bbdb->posts 
		(topic_id, poster_id, post_text, post_time, poster_ip, post_position)
		VALUES
		('$tid',   '$uid',    '$bb_post',   '$now',    '$ip',     $topic->topic_posts + 1)");
		$post_id = $bbdb->insert_id;
		$bbdb->query("UPDATE $bbdb->forums SET posts = posts + 1 WHERE forum_id = $topic->forum_id");
		$bbdb->query("UPDATE $bbdb->topics SET topic_time = '$now', topic_last_poster = $uid, topic_last_poster_name = '$uname',
		topic_last_post_id = $post_id, topic_posts = topic_posts + 1 WHERE topic_id = $tid");
		if ( isset($thread_ids_cache[$tid]) ) {
			$thread_ids_cache[$tid]['post'][] = $post_id;
			$thread_ids_cache[$tid]['poster'][] = $uid;
		}
		$post_ids = get_thread_post_ids( $tid );
		if ( !in_array($uid, array_slice($post_ids['poster'], 0, -1)) )
			bb_update_usermeta( $uid, $bb_table_prefix . 'topics_replied', $bb_current_user->data->topics_replied + 1 );
		if ( !bb_current_user_can('throttle') )
			bb_update_usermeta( $uid, 'last_posted', time() );
		$bb_cache->flush_one( 'topic', $tid );
		$bb_cache->flush_many( 'thread', $tid );
		$bb_cache->flush_many( 'forum', $forum_id );
		bb_do_action('bb_new_post', $post_id);
		return $post_id;
	} else {
		return false;
	}
}

function bb_delete_post( $post_id ) {
	global $bbdb, $bb_cache, $bb_table_prefix, $thread_ids_cache;
	$post_id = (int) $post_id;
	$bb_post    = bb_get_post ( $post_id );
	$topic   = get_topic( $bb_post->topic_id );

	if ( $bb_post ) {
		$new_status = ( $bb_post->post_status + 1 ) % 2;
		$sign = ( $new_status ) ? '-' : '+';
		$bbdb->query("UPDATE $bbdb->posts SET post_status = $new_status WHERE post_id = $post_id");
		bb_update_topicmeta( $topic->topic_id, 'deleted_posts', $topic->deleted_posts + ( $new_status ? 1 : -1 ) );
		$bbdb->query("UPDATE $bbdb->forums SET posts = posts $sign 1 WHERE forum_id = $topic->forum_id");
		$posts = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = $bb_post->topic_id AND post_status = 0");
		$bbdb->query("UPDATE $bbdb->topics SET topic_posts = '$posts' WHERE topic_id = $bb_post->topic_id");

		if ( 0 == $posts ) {
			$bbdb->query("UPDATE $bbdb->topics SET topic_status = 1 WHERE topic_id = $bb_post->topic_id");
			if ( $tags = $bbdb->get_col("SELECT tag_id FROM $bbdb->tagged WHERE topic_id = $bb_post->topic_id") ) {
				$tags = join(',', $tags);
				$bbdb->query("UPDATE $bbdb->tags SET tag_count = tag_count - 1 WHERE tag_id IN ($tags)");
			}
			$bbdb->query("DELETE FROM $bbdb->tagged WHERE topic_id = $bb_post->topic_id");
			$bbdb->query("UPDATE $bbdb->forums SET topics = topics - 1 WHERE forum_id = $topic->forum_id");
			bb_do_action('bb_delete_topic', $bb_post->topic_id);
		} else {
			$old_post = $bbdb->get_row("SELECT post_id, poster_id, post_time FROM $bbdb->posts WHERE topic_id = $bb_post->topic_id AND post_status = 0 ORDER BY post_time DESC LIMIT 1");
			$old_name = $bbdb->get_var("SELECT user_login FROM $bbdb->users WHERE ID = $old_post->poster_id");
			if ( $topic->topic_status ) {
				$bbdb->query("UPDATE $bbdb->topics SET topic_status = 0, topic_time = '$old_post->post_time', topic_last_poster = $old_post->poster_id, topic_last_poster_name = '$old_name', topic_last_post_id = $old_post->post_id WHERE topic_id = $bb_post->topic_id");
				$bbdb->query("UPDATE $bbdb->forums SET topics = topics + 1 WHERE forum_id = $topic->forum_id");
			} else
				$bbdb->query("UPDATE $bbdb->topics SET topic_time = '$old_post->post_time', topic_last_poster = $old_post->poster_id, topic_last_poster_name = '$old_name', topic_last_post_id = $old_post->post_id WHERE topic_id = $bb_post->topic_id");
			if ( $topic->topic_posts != $bb_post->post_position )
				update_post_positions( $topic->topic_id );
		}
		//Only happens if we're deleting an entire topic
		if ( $new_status && isset($thread_ids_cache[$topic->topic_id]) ) {
			array_pop($thread_ids_cache[$topic->topic_id]['post']);
			array_pop($thread_ids_cache[$topic->topic_id]['poster']);
		}
		$post_ids = get_thread_post_ids( $bb_post->topic_id );
		$user = bb_get_user( $bb_post->poster_id );
		if ( $new_status && ( !is_array($post_ids['poster']) || !in_array($user->ID, $post_ids['poster']) ) )
			bb_update_usermeta( $user->ID, $bb_table_prefix . 'topics_replied', $user->topics_replied - 1 );
		$bb_cache->flush_one( 'topic', $bb_post->topic_id );
		$bb_cache->flush_many( 'thread', $bb_post->topic_id );
		$bb_cache->flush_many( 'forum', $forum_id );
		bb_do_action('bb_delete_post', $post_id);
		return $post_id;
	} else {
		return false;
	}
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

function bb_resolve_topic ( $topic_id, $resolved = 'yes' ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	if ( ! in_array($resolved, array('yes', 'no', 'mu')) )
		return false;
	bb_do_action('resolve_topic', $topic_id);
	$bb_cache->flush_one( 'topic', $topic_id );
	return $bbdb->query("UPDATE $bbdb->topics SET topic_resolved = '$resolved' WHERE topic_id = '$topic_id'");
}

function bb_close_topic ( $topic_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $toppic_id;
	bb_do_action('close_topic', $topic_id);
	$bb_cache->flush_one( 'topic', $topic_id );
	return $bbdb->query("UPDATE $bbdb->topics SET topic_open = '0' WHERE topic_id = $topic_id");
}

function bb_open_topic ( $topic_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	bb_do_action('opentopic', $topic_id);
	$bb_cache->flush_one( 'topic', $topic_id );
	return $bbdb->query("UPDATE $bbdb->topics SET topic_open = '1' WHERE topic_id = $topic_id");
}

function bb_stick_topic ( $topic_id, $super = 0 ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	$stick = 1 + abs((int) $super);
	bb_do_action('stick_topic', $topic_id);
	$bb_cache->flush_one( 'topic', $topic_id );
	return $bbdb->query("UPDATE $bbdb->topics SET topic_sticky = '$stick' WHERE topic_id = $topic_id");
}

function bb_unstick_topic ( $topic_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	bb_do_action('unstick_topic', $topic_id);
	$bb_cache->flush_one( 'topic', $topic_id );
	return $bbdb->query("UPDATE $bbdb->topics SET topic_sticky = '0' WHERE topic_id = $topic_id");
}

function bb_update_post( $bb_post, $post_id, $topic_id ) {
	global $bbdb, $bb_cache;
	$bb_post  = bb_apply_filters('pre_post', $bb_post);
	$post_id  = (int) $post_id;
	$topic_id = (int) $topic_id;

	if ( $post_id && $bb_post ) {
		$bbdb->query("UPDATE $bbdb->posts SET post_text = '$bb_post' WHERE post_id = $post_id");
		$bb_cache->flush_many( 'thread', $topic_id );
		bb_do_action('bb_update_post', $post_id);
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
	return bb_apply_filters( 'get_post_link', get_topic_link( $bb_post->topic_id, $page ) . "#post-$bb_post->post_id" );
}

function post_link( $post_id = 0 ) {
	echo bb_apply_filters( 'post_link', get_post_link( $post_id ) );
}

function update_post_positions( $topic_id ) {
	global $bbdb, $bb_cache;
	$topic_id = (int) $topic_id;
	$posts = get_thread_post_ids( $topic_id );
	if ( $posts ) {
		foreach ( $posts['post'] as $i => $post_id )
			$bbdb->query("UPDATE $bbdb->posts SET post_position = $i + 1 WHERE post_id = $post_id");
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
	$where = bb_apply_filters('bb_is_first_where', 'AND post_status = 0');
	$first_post = $bbdb->get_var("SELECT post_id FROM $bbdb->posts WHERE topic_id = $bb_post->topic_id $where ORDER BY post_id ASC LIMIT 1");

	if ( $post_id == $first_post )
		return true;
	else
		return false;
}

function bb_global_sanitize( $array ) {
	foreach ($array as $k => $v) {
		if ( is_array($v) ) {
			$array[$k] = bb_global_sanitize($v);
		} else {
			if ( get_magic_quotes_gpc() )
				$array[$k] = trim($v);
			else
				$array[$k] = addslashes( trim($v) );
		}
	}
	return $array;
}

// GMT -> Local
function bb_offset_time($time) {
	// in future versions this could eaily become a user option.
	global $bb;
	if ( !is_numeric($time) ) {
		if ( !(strtotime($time) === -1)) {
			$time = strtotime($time);
			return date('Y-m-d H:i:s', ($time + ($bb->gmt_offset * 3600)));
		} else {
			return $time;
		}
	} else {
		return ($time + ($bb->gmt_offset * 3600));
	}
}

function bb_cookie( $name, $value, $expires = 0 ) {
	global $bb;
	if ( !$expires )
		$expires = time() + 604800;
	if ( isset( $bb->cookiedomain ) )
		setcookie( $name, $value, $expires, $bb->cookiepath, $bb->cookiedomain );
	else
		setcookie( $name, $value, $expires, $bb->cookiepath );
}

function get_path( $level = 1 ) {
	if ( isset($_SERVER['PATH_INFO']) ) :
		$url = explode('/',$_SERVER['PATH_INFO']);
		return $url[$level];
	else:	return;
	endif;
}

//WPcommon
if ( !function_exists('nocache_headers') ) {
function nocache_headers() {
	header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
}
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
			bb_do_action('bb_already_tagged', serialize(array('tag_id' => $tag_id, 'user_id' => $bb_current_user->ID, 'topic_id' => $topic_id)));
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
	bb_do_action('bb_tag_added', serialize(array('tag_id' => $tag_id, 'user_id' => $bb_current_user->ID, 'topic_id' => $topic_id)));
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
	bb_do_action('bb_tag_created', $bbdb->insert_id);
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

	bb_do_action('bb_tag_renamed', $tag_id );

	if ( $bbdb->query("UPDATE $bbdb->tags SET tag = '$tag', raw_tag = '$raw_tag' WHERE tag_id = '$tag_id'") )
		return get_tag_by_name( $tag );
	return false;
}

function remove_topic_tag( $tag_id, $user_id, $topic_id ) {
	global $bbdb, $bb_cache, $bb_current_user;
	$tag_id = (int) $tag_id;
	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;
	$tagged = serialize( array('tag_id' => $tag_id, 'user_id' => $user_id, 'topic_id' => $topic_id) );
	if ( !$topic = get_topic( $topic_id ) )
		return false;
	if ( !bb_current_user_can( 'edit_tag_by_on', $user_id, $topic_id ) )
		return false;

	bb_do_action('bb_tag_removed', $tagged);

	$topics = array_flip($bbdb->get_col("SELECT topic_id, COUNT(*) FROM $bbdb->tagged WHERE tag_id = '$tag_id' GROUP BY topic_id"));
	$counts = $bbdb->get_col('', 1);
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

	$merged_tags = serialize( array( 'old_id' => $old_id, 'new_id' => $new_id ) );

	bb_do_action('bb_tag_merged', $merged_tags);

	$tagged_del = 0;
	if ( $old_topic_ids = $bbdb->get_col( "SELECT topic_id FROM $bbdb->tagged WHERE tag_id = '$old_id'" ) ) {
		$old_topic_ids = join(',', $old_topic_ids);
		$shared_topics_u = $bbdb->get_col( "SELECT user_id, topic_id FROM $bbdb->tagged WHERE tag_id = '$new_id' AND topic_id IN ($old_topic_ids)" );
		$shared_topics_i = $bbdb->get_col( '', 1 );
		foreach ( $shared_topics_i as $t => $i ) {
			$tagged_del += $bbdb->query( "DELETE FROM $bbdb->tagged WHERE tag_id = '$old_id' AND user_id = '{$shared_topics_u[$t]}' AND topic_id = '$i'" );
			$count = $bbdb->get_var( "SELECT COUNT(DISTINCT tag_id) FROM $bbdb->tagged WHERE topic_id = '$topic_id' GROUP BY topic_id" );
			$bbdb->query( "UPDATE $bbdb->tags SET tag_count = $count WHERE tag_id = '$new_id'" );
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

	bb_do_action('bb_tag_destroyed', $tag_id);

	if ( $tags = $bbdb->query("DELETE FROM $bbdb->tags WHERE tag_id = '$tag_id'") ) {
		if ( $topics = $bbdb->get_col("SELECT DISTINCT topic_id FROM $bbdb->tagged WHERE tag_id = '$tag_id'") ) {
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
	global $bbdb;
	$tag     = tag_sanitize( $tag );

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
	if ( $topic_ids = $bbdb->get_col("SELECT DISTINCT topic_id FROM $bbdb->tagged WHERE tag_id = '$tag_id' ORDER BY tagged_on DESC") ) {
		$tagged_topic_count = count($topic_ids);
		return bb_apply_filters('get_tagged_topic_ids', $topic_ids);
	} else {
		$tagged_topic_count = 0;
		return false;
	}
}

function get_tagged_topics( $tag_id, $page = 1 ) {
	global $bbdb;
	if ( !$topic_ids = get_tagged_topic_ids( $tag_id ) )
		return false;
	$topic_ids = join($topic_ids, ',');
	$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	if ( $topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids) AND topic_status = 0 ORDER BY topic_time DESC LIMIT $limit") )
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
	$text = preg_replace('|.*?/([a-z\-]+\.php)/?.*|', '$1', $text);
	return $text;
}

function get_top_tags( $recent = true, $limit = 40 ) {
	global $bbdb;
	$tags = $bbdb->get_results("SELECT * FROM $bbdb->tags ORDER BY tag_count DESC LIMIT $limit");
	return $tags;
}

// Inspired by and adapted from Yung-Lung Scott YANG's http://scott.yang.id.au/2005/05/permalink-redirect/ (GPL)
function bb_repermalink() {
	global $bb, $page;
	$uri = $_SERVER['REQUEST_URI'];
	if ( isset($_GET['id']) )
		$permalink = (int) $_GET['id'];
	else	$permalink = intval( get_path() );

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
		else	$permalink = get_path();
		if ( !$permalink )
			$permalink = get_tag_page_link();
		else {
			global $tag_name;
			$tag_name = $permalink;
			$permalink = get_tag_link( $permalink, $page );
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
		$permalink = bb_add_query_arg($args, $permalink);
			if ( bb_get_option('mod_rewrite') ) {
				$pretty_args = array('id', 'page', 'tag', 'tab'); // these are already specified in the path
				foreach( $pretty_args as $arg )
					$permalink = bb_remove_query_arg($arg, $permalink);
			}
	}

	$check = preg_replace( '|' . trim( bb_get_option('domain'), ' /' ) . '|', '', $permalink, 1 );

	if ( isset($bb->debug) && 1 === $bb->debug ) :
		echo "<table>\n<tr><td>REQUEST_URI:</td><td>";
		var_dump($uri);
		echo "</td></tr>\n<tr><td>should be:</td><td>";
		var_dump($check);
		echo "</td></tr>\n<tr><td>full permalink:</td><td>";
		var_dump($permalink);
		echo "</td></tr>\n<tr><td>PATH_INFO:</td><td>";
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
}

//WPcommon
if ( !function_exists('status_header') ) {
function status_header( $header ) {
	if ( 200 == $header ) {
		$text = 'OK';
	} elseif ( 301 == $header ) {
		$text = 'Moved Permanently';
	} elseif ( 302 == $header ) {
		$text = 'Moved Temporarily';
	} elseif ( 304 == $header ) {
		$text = 'Not Modified';
	} elseif ( 404 == $header ) {
		$text = 'Not Found';
	} elseif ( 410 == $header ) {
		$text = 'Gone';
	}
	if ( preg_match('/cgi/',php_sapi_name()) ) {
		@header("Status: $header $text");
	} else {
		if ( version_compare(phpversion(), '4.3.0', '>=') )
			@header($text, TRUE, $header);
		else
			@header("HTTP/1.x $header $text");
	}
}
}

// Placeholders
//WPcommon
if ( !function_exists('_e') ) {
function _e($e) {
	echo $e;
}
}

//WPcommon
if ( !function_exists('__') ) {
function __($e) {
	return $e;
}
}

// Profile/Admin
function global_profile_menu_structure() {
	global $bb_current_user, $user_id, $profile_menu, $profile_hooks;
	// Menu item name
	// The capability required for own user to view the tab ('' to allow non logged in access)
	// The capability required for other users to view the tab ('' to allow non logged in access)
	// The URL of the item's file
	$profile_menu[0] = array(__('Edit'), 'edit_profile', 'edit_users', 'profile-edit.php');
	$profile_menu[5] = array(__('Favorites'), 'edit_favorites', 'edit_others_favorites', 'favorites.php');

	// Create list of page plugin hook names the current user can access
	$profile_hooks = array();
	foreach ($profile_menu as $profile_tab)
		if ( can_access_tab( $profile_tab, $bb_current_user->ID, $user_id ) )
			$profile_hooks[$profile_tab[3]] = tag_sanitize($profile_tab[0]);

	bb_do_action('bb_profile_menu','');
	ksort($profile_menu);
}

function add_profile_tab($tab_title, $users_cap, $others_cap, $file) {
	global $profile_menu, $profile_hooks, $bb_current_user, $user_id;

	$profile_tab = array($tab_title, $users_cap, $others_cap, $file);
	$profile_menu[] = $profile_tab;
	if ( can_access_tab( $profile_tab, $bb_current_user->ID, $user_id ) )
		$profile_hooks[$file] = tag_sanitize($tab_title);
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
	return bb_apply_filters(
		'get_profile_info_keys',
		array('user_email' => array(1, __('Email')), 'user_url' => array(0, __('Website')), 'from' => array(0, __('Location')), 'occ' => array(0, __('Occupation')), 'interest' => array(0, __('Interests')))
	);
}

function get_profile_admin_keys() {
	global $bb_table_prefix;
	return bb_apply_filters(
		'get_profile_admin_keys',
		array($bb_table_prefix . 'title' => array(0, __('Custom Title')))
	);
}

function get_assignable_caps() {
	return bb_apply_filters(
		'get_assignable_caps',
		array('throttle' => __('Ignore the 30 second post throttling limit'))
	);
}

function get_views( $cache = true ) {
	global $bb_current_user, $views;
	if ( !isset($views) || !$cache )
		$views = array('no-replies' => __('Topics with no replies'), 'untagged' => __('Topics with no tags'), 'unresolved' => __('Unresolved topics'));
	return bb_apply_filters('bb_views', $views);
}
?>
