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
		$topic_cache[$id] = $bbdb->get_row("SELECT * FROM $bbdb->topics WHERE topic_id = $id");
	return $topic_cache[$id];
}

function get_thread( $topic, $page = 0, $reverse = 0 ) {
	global $bbdb;

	$limit = bb_get_option('page_topics');
	if ( $page )
		$limit = ($limit * $page) . ", $limit";
	$order = ($reverse) ? 'DESC' : 'ASC';

	return $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE topic_id = $topic ORDER BY post_time $order LIMIT $limit");
}

function get_post( $post_id ) {
	global $bbdb;
	$post_id = (int) $post_id;
	return $bbdb->get_row("SELECT * FROM $bbdb->posts WHERE post_id = $post_id");
}

function get_latest_topics( $forum = 0, $page = 0 ) {
	global $bbdb, $bb;
	if ( $forum )
		$where = "WHERE forum_id = $forum";
	$limit = bb_get_option('page_topics');
	if ( $page )
		$limit = ($limit * $page) . ", $limit";
	return $bbdb->get_results("SELECT * FROM $bbdb->topics $where ORDER BY topic_time DESC LIMIT $limit");
}

function get_latest_posts( $num ) {
	global $bbdb;
	$num = (int) $num;
	return $bbdb->get_results("SELECT * FROM $bbdb->posts ORDER BY post_time DESC LIMIT $num");
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

function add_action($tag, $function_to_add, $priority = 10) {
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
	$since = $today - $original;
	
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

function bb_current_time($type) {
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
	global $bbdb, $user_cache;
	if ( !isset($_COOKIE['bb_user_' . BBHASH]) )
		return false;
	if ( !isset($_COOKIE['bb_pass_' . BBHASH]) )
		return false;
	$user = user_sanitize( $_COOKIE['bb_user_' . BBHASH] );
	$pass = user_sanitize( $_COOKIE['bb_pass_' . BBHASH] );
	
	$current_user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE username = '$user' AND user_password = '$pass'");
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

function bb_new_topic( $title, $forum ) {
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
		$admin_id = $current_user->user_id;
	$admin = bb_get_user( $admin_id );
	$user  = bb_get_user( $user_id  );

	if ( $admin_id === $user_id )
		return true;

	if ( $user->user_type < $admin->user_type && $admin->user_type != 0 )
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

?>