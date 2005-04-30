<?php

function get_forums() {
	global $bbdb;
	return $bbdb->get_results("SELECT * FROM $bbdb->forums ORDER BY forum_order");
}

function get_forum( $id ) {
	global $bbdb;
	return $bbdb->get_row("SELECT * FROM $bbdb->forums WHERE forum_id = $id");
}

function get_topic( $id ) {
	global $topic_cache, $bbdb;
	$id = (int) $id;
	if ( !isset( $topic_cache[$id] ) )
		$topic_cache[$id] = $bbdb->get_row("SELECT * FROM $bbdb->topics WHERE topic_id = $id AND topic_status = 0");
	return $topic_cache[$id];
}

function get_thread( $topic, $page = 0, $reverse = 0 ) {
	global $bbdb;

	$limit = bb_get_option('page_topics');
	if ( $page )
		$limit = ($limit * $page) . ", $limit";
	$order = ($reverse) ? 'DESC' : 'ASC';

	return $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE topic_id = $topic AND post_status = 0 ORDER BY post_time $order LIMIT $limit");
}

function get_thread_post_ids ( $topic ) {
	global $bbdb;
	return $bbdb->get_col("SELECT post_id FROM $bbdb->posts WHERE topic_id = $topic AND post_status = 0 ORDER BY post_time");
}

function get_post( $post_id ) {
	global $bbdb;
	$post_id = (int) $post_id;
	return $bbdb->get_row("SELECT * FROM $bbdb->posts WHERE post_id = $post_id");
}

function get_latest_topics( $forum = 0, $page = 0 ) {
	global $bbdb, $bb;
	$where = $limit = '';
	if ( $forum )
		$where = "AND forum_id = $forum";
	$limit = bb_get_option('page_topics');
	if ( $page )
		$limit = ($limit * $page) . ", $limit";
	return $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_status = 0 $where ORDER BY topic_time DESC LIMIT $limit");
}

function get_sticky_topics( $forum = 0, $page = 0 ) {
	global $bbdb, $bb;
	$where = '';
	if ( $forum )
		$where = "AND forum_id = $forum";
	return $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_status = 0 AND topic_sticky = '1' $where ORDER BY topic_time DESC");
}

function get_latest_posts( $num ) {
	global $bbdb;
	$num = (int) $num;
	return $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE post_status = 0 ORDER BY post_time DESC LIMIT $num");
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
		if ( isset( $new_function_list ) )
			$wp_filter[$tag]["$priority"] = $new_function_list;
	}
	//die(var_dump($wp_filter));
	return true;
}

// The *_action functions are just aliases for the *_filter functions, they take special strings instead of generic content

function bb_do_action($tag, $string) {
	bb_apply_filters($tag, $string, false);
	return $string;
}

function bb_add_action($tag, $function_to_add, $priority = 10) {
	bb_add_filter($tag, $function_to_add, $priority);
}

function remove_action($tag, $function_to_remove, $priority = 10) {
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
	endswitch;
}

function option( $option ) {
	echo bb_get_option( $option ) ;
}

function bb_add_query_arg() {
	$ret = '';
	if( is_array( func_get_arg(0) ) ) {
		$uri = @func_get_arg(1);
	} else {
		if ( @func_num_args() < 3 ) {
			$uri = $_SERVER['REQUEST_URI'];
		} else {
			$uri = @func_get_arg(2);
		}
	}

	if ( strstr($uri, '?') ) {
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
	return trim($ret, '?');
}

function bb_remove_query_arg($key, $query) {
	bb_add_query_arg($key, '', $query);
}

function post_author_cache($posts) {
	global $bbdb, $user_cache;
	foreach ($posts as $post) :
		if ( 0 != $post->poster_id )
			$ids[] = $post->poster_id;
	endforeach;
	if ( isset($ids) ) {
		$ids = join(',', $ids);
		$users = $bbdb->get_results("SELECT * FROM $bbdb->users WHERE user_id IN ($ids)");
		foreach ($users as $user) :
			$user_cache[$user->user_id] = $user;
		endforeach;
	}
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

function bb_current_user() {
	global $bbdb, $user_cache, $bb;
	if ( !isset($_COOKIE[ $bb->usercookie ]) )
		return false;
	if ( !isset($_COOKIE[ $bb->passcookie ]) )
		return false;
	$user = user_sanitize( $_COOKIE[ $bb->usercookie ] );
	$pass = user_sanitize( $_COOKIE[ $bb->passcookie ] );
	$current_user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE username = '$user' AND MD5( user_password ) = '$pass'");
	$user_cache[$current_user->user_id] = $current_user;
	return $current_user;
}

function bb_get_user( $id ) {
	global $bbdb, $user_cache;
	$id = (int) $id;
	if ( isset( $user_cache[$id] ) ) {
		return $user_cache[$id];
	} else {
		$user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_id = $id;");
		$user_cache[$id] = $user;
		return $user;
	}
}

function bb_check_login($user, $pass) {
	global $bbdb;
	$user = user_sanitize( $user );
	$pass = user_sanitize( md5( $pass ) );
	return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE username = '$user' AND user_password = '$pass'");
}

function bb_user_exists( $user ) {
	global $bbdb;
	$user = user_sanitize( $user );
	return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE username = '$user'");
}

function bb_new_topic( $title, $forum, $tags = '' ) {
	global $bbdb, $current_user;
	$title = bb_apply_filters('pre_topic_title', $title);
	$forum = (int) $forum;
	$now   = bb_current_time('mysql');

	if ( $forum && $title ) {
		$bbdb->query("INSERT INTO $bbdb->topics 
		(topic_title, topic_poster, topic_poster_name, topic_last_poster, topic_last_poster_name, topic_time, forum_id)
		VALUES
		('$title', $current_user->user_id, '$current_user->username', $current_user->user_id, '$current_user->username', '$now', $forum)");
		$topic_id = $bbdb->insert_id;
		if ( !empty( $tags ) )
			add_topic_tags( $topic_id, $tags );
		$bbdb->query("UPDATE $bbdb->forums SET topics = topics + 1 WHERE forum_id = $forum");
		bb_do_action('bb_new_topic', $topic_id);
		return $topic_id;
	} else {
		return false;
	}
}

function bb_update_topic( $title, $topic_id ) {
	global $bbdb;
	$title = bb_apply_filters('pre_topic_title', $title);
	$topic_id = (int) $topic_id;
	$forum_id = (int) $forum_id;

	if ( $topic_id && $title ) {
		$bbdb->query("UPDATE $bbdb->topics SET topic_title = '$title' WHERE topic_id = $topic_id");
		bb_do_action('bb_update_topic', $topic_id);
		return $topic_id;
	} else {
		return false;
	}
}

function bb_new_post( $topic_id, $post ) {
	global $bbdb, $current_user;
	$post  = bb_apply_filters('pre_post', $post);
	$tid   = (int) $topic_id;
	$now   = bb_current_time('mysql');
	$uid   = $current_user->user_id;
	$uname = $current_user->username;
	$ip    = addslashes( $_SERVER['REMOTE_ADDR'] );

	$topic = $bbdb->get_row("SELECT * FROM $bbdb->topics WHERE topic_id = $tid");

	if ( $post && $topic ) {
		$bbdb->query("INSERT INTO $bbdb->posts 
		(topic_id, poster_id, post_text, post_time, poster_ip)
		VALUES
		('$tid',   '$uid',    '$post',   '$now',    '$ip'    )");
		$post_id = $bbdb->insert_id;
		$bbdb->query("UPDATE $bbdb->forums SET posts = posts + 1 WHERE forum_id = $topic->forum_id");
		$bbdb->query("UPDATE $bbdb->topics SET topic_time = '$now', topic_last_poster = $uid, topic_last_poster_name = '$uname',
		topic_last_post_id = $post_id, topic_posts = topic_posts + 1 WHERE topic_id = $tid");
		bb_do_action('bb_new_post', $post_id);
		return $post_id;
	} else {
		return false;
	}
}

function bb_delete_post( $post_id ) {
	global $bbdb;
	$post_id = (int) $post_id;
	$post    = get_post ( $post_id );
	$topic   = get_topic( $post->topic_id );

	if ( $post ) {
		$bbdb->query("UPDATE $bbdb->posts SET post_status = 1 WHERE post_id = $post_id");
		$bbdb->query("UPDATE $bbdb->forums SET posts = posts - 1 WHERE forum_id = $topic->forum_id");
		$posts = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = $post->topic_id AND post_status = 0");
		$bbdb->query("UPDATE $bbdb->topics SET topic_posts = '$posts' WHERE topic_id = $post->topic_id");

		if ( 0 == $posts ) {
			$bbdb->query("UPDATE $bbdb->topics SET topic_status = 1 WHERE topic_id = $post->topic_id");
			$bbdb->query("DELETE FROM $bbdb->tagged WHERE topic_id = $post->topic_id");
		} else {
			$old_post = $bbdb->get_row("SELECT post_id, poster_id, post_time FROM $bbdb->posts WHERE topic_id = $post->topic_id AND post_status = 0 ORDER BY post_time DESC LIMIT 1");
			$old_name = $bbdb->get_var("SELECT username FROM $bbdb->users WHERE user_id = $old_post->poster_id");
			$bbdb->query("UPDATE $bbdb->topics SET topic_time = '$old_post->post_time', topic_last_poster = $old_post->poster_id, topic_last_poster_name = '$old_name', topic_last_post_id = $old_post->post_id WHERE topic_id = $post->topic_id");
		}

		bb_do_action('bb_delete_post', $post_id);
		return $post_id;
	} else {
		return false;
	}
}

function bb_close_topic ( $topic_id ) {
	global $bbdb;
	bb_do_action('close_topic', $topic_id);
	return $bbdb->query("UPDATE $bbdb->topics SET topic_open = '0' WHERE topic_id = $topic_id");
}

function bb_open_topic ( $topic_id ) {
	global $bbdb;
	bb_do_action('opentopic', $topic_id);
	return $bbdb->query("UPDATE $bbdb->topics SET topic_open = '1' WHERE topic_id = $topic_id");
}

function bb_stick_topic ( $topic_id ) {
	global $bbdb;
	bb_do_action('stick_topic', $topic_id);
	return $bbdb->query("UPDATE $bbdb->topics SET topic_sticky = '1' WHERE topic_id = $topic_id");
}

function bb_unstick_topic ( $topic_id ) {
	global $bbdb;
	bb_do_action('unstick_topic', $topic_id);
	return $bbdb->query("UPDATE $bbdb->topics SET topic_sticky = '0' WHERE topic_id = $topic_id");
}

function bb_update_post( $post, $post_id ) {
	global $bbdb, $current_user;
	$post  = bb_apply_filters('pre_post', $post);
	$post_id   = (int) $post_id;

	if ( $post_id && $post ) {
		$bbdb->query("UPDATE $bbdb->posts SET post_text = '$post' WHERE post_id = $post_id");
		bb_do_action('bb_update_post', $post_id);
		return $post_id;
	} else {
		return false;
	}
}

function get_post_link( $id ) {
	global $bbdb, $topic, $post;
	$id = (int) $id;
	if ( isset( $post->topic_id ) )
		$topic_id = $post->topic_id;
	else
		$topic_id = $bbdb->get_var("SELECT topic_id FROM $bbdb->posts WHERE post_id = $id");
	if ( !$topic_id )
		return false;
	$topic = get_topic($topic_id); 

	return get_topic_link() . "#post-$id";
}

function post_link() {
	global $post;
	echo get_post_link( $post->post_id );
}

function can_edit( $user_id, $admin_id = 0) {
	global $bbdb, $current_user;
	if ( !$admin_id )
		$admin_id = (int) $current_user->user_id;
	$admin = bb_get_user( $admin_id );
	$user  = bb_get_user( $user_id  );

	if ( $admin_id == $user_id )
		return true;

	if ( $user->user_type < $admin->user_type && $admin->user_type != 0 )
		return true;
	else
		return false;
}

function can_delete( $user_id, $admin_id = 0) {
	global $bbdb, $current_user;
	if ( !$admin_id )
		$admin_id = $current_user->user_id;
	$admin = bb_get_user( $admin_id );
	$user  = bb_get_user( $user_id  );

	if ( $user->user_type < $admin->user_type && $admin->user_type != 0 )
		return true;
	else
		return false;
}

function can_edit_post( $post_id, $user_id = 0 ) {
	global $bbdb, $current_user;
	if ( !$user_id )
		$user_id = $current_user->user_id;
	$user = bb_get_user( $user_id );
	$post = get_post( $post_id );
	$post_author = bb_get_user ( $post->poster_id );

	if ( $user->user_type > $post_author->user_type )
		return true;
	
	if ( ! topic_is_open( $post->topic_id ) )
		return false;

	$post_time  = strtotime( $post->post_time );
	$curr_time  = time();
	$time_limit = bb_get_option('edit_lock') * 60;
	if ( ($curr_time - $post_time) > $time_limit )
		return false;
	else
		return true;
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
	if ( 1 == $topic->topic_sticky )
		return true;
	else
		return false;
}

function bb_is_first( $post_id ) { // First post in thread
	global $bbdb;

	$post = $bbdb->get_row("SELECT * FROM $bbdb->posts WHERE post_id = $post_id");
	$first_post = $bbdb->get_var("SELECT post_id FROM $bbdb->posts WHERE topic_id = $post->topic_id ORDER BY post_id ASC LIMIT 1");

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
	$url = explode('/',$_SERVER['PATH_INFO']);
	return $url[$level];
}

function nocache_headers() {
	header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache, must-revalidate');
	header('Pragma: no-cache');
}

function add_topic_tag( $topic_id, $tag ) {
	global $bbdb, $current_user;
	if ( !$tag_id = create_tag( $tag ))
		return false;
	$now    = bb_current_time('mysql');
	if ( $bbdb->get_var("SELECT tag_id FROM $bbdb->tagged WHERE tag_id = '$tag_id' AND user_id = '$current_user->user_id' AND topic_id='$topic_id'") )
		return true;
	$bbdb->query("INSERT INTO $bbdb->tagged 
	( tag_id, user_id, topic_id, tagged_on )
	VALUES
	( '$tag_id', '$current_user->user_id', '$topic_id', '$now')");
	$bbdb->query("UPDATE $bbdb->tags SET tag_count = tag_count + 1 WHERE tag_id = '$tag_id'");
	return true;
}

function add_topic_tags( $topic_id, $tags ) {
	global $bbdb, $current_user;

	$tags = trim( $tags );
	$words = preg_split("/[\s,]+/", $tags);

	if ( !is_array( $words ) )
		return false;

	foreach ( $words as $tag ) :
		if ( !$tag_id = create_tag( $tag ))
			continue;
		$now = bb_current_time('mysql');
		if ( $bbdb->get_var("SELECT tag_id FROM $bbdb->tagged WHERE tag_id = '$tag_id' AND user_id = '$current_user->user_id' AND topic_id='$topic_id'") )
			continue;
		$bbdb->query("INSERT INTO $bbdb->tagged 
		( tag_id, user_id, topic_id, tagged_on )
		VALUES
		( '$tag_id', '$current_user->user_id', '$topic_id', '$now')");
		$bbdb->query("UPDATE $bbdb->tags SET tag_count = tag_count + 1");
	endforeach;
	return true;
}

function create_tag( $tag ) {
	global $bbdb;
	$raw_tag = $tag;
	$tag     = trim         ( $tag );
	$tag     = strtolower   ( $tag );
	$tag     = preg_replace ( '/\s/', '', $tag );
	$tag     = user_sanitize( $tag );
	if ( empty( $tag ) )
		return false;
	if ( $exists = $bbdb->get_var("SELECT tag_id FROM $bbdb->tags WHERE tag = '$tag'") )
		return $exists;

	$bbdb->query("INSERT INTO $bbdb->tags ( tag, raw_tag ) VALUES ( '$tag', '$raw_tag' )");
	return $bbdb->insert_id;
}

function get_tag_id( $tag ) {
	global $bbdb;
	$tag     = strtolower   ( $tag );
	$tag     = preg_replace ( '/\s/', '', $tag );
	$tag     = user_sanitize( $tag );

	return $bbdb->get_var("SELECT tag_id FROM $bbdb->tags WHERE tag = '$tag'");
}

function get_tag( $id ) {
	global $bbdb;
	$id = (int) $id;
	return $bbdb->get_row("SELECT * FROM $bbdb->tags WHERE tag_id = '$id'");
}

function get_tag_by_name( $tag ) {
	global $bbdb;
	$tag     = strtolower   ( $tag );
	$tag     = preg_replace ( '/\s/', '', $tag );
	$tag     = user_sanitize( $tag );

	return $bbdb->get_row("SELECT * FROM $bbdb->tags WHERE tag = '$tag'");
}

function get_topic_tags ( $topic_id ) {
	global $topic_tag_cache, $bbdb;
	
	if ( isset ($topic_tag_cache[$topic_id] ) )
		return $topic_tag_cache[$topic_id];

	$topic_tag_cache[$topic_id] = $bbdb->get_results("SELECT * FROM $bbdb->tagged JOIN $bbdb->tags ON ($bbdb->tags.tag_id = $bbdb->tagged.tag_id) WHERE topic_id = '$topic_id'");
	
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

function bb_find_filename( $text ) { 
	$text = preg_replace('|.*?/([a-z]+\.php)/?.*|', '$1', $text);
	return $text;
}

function get_top_tags( $recent = true, $limit = 40 ) {
	global $bbdb;
	$tags = $bbdb->get_results("SELECT * FROM $bbdb->tags ORDER BY tag_count DESC LIMIT $limit");
	return $tags;
}

?>