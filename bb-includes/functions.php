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
	global $bbdb;
	return $bbdb->get_row("SELECT * FROM $bbdb->topics WHERE topic_id = $id");
}

function get_thread ( $topic, $page = 0 ) {
	global $bbdb, $bb;

	$limit = get_option('page_topics');
	if ( $page )
		$limit = ($limit * $page) . ", $limit";
	return $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE topic_id = $topic ORDER BY post_time ASC LIMIT $limit");
}

function get_latest_topics( $forum = 0, $page = 0 ) {
	global $bbdb, $bb;
	if ( $forum )
		$where = "WHERE forum_id = $forum";
	$limit = get_option('page_topics');
	if ( $page )
		$limit = ($limit * $page) . ", $limit";
	return $bbdb->get_results("SELECT * FROM $bbdb->topics $where ORDER BY topic_time DESC LIMIT $limit");
}

function apply_filters($tag, $string, $filter = true) {
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

function add_filter($tag, $function_to_add, $priority = 10) {
	global $wp_filter;
	// So the format is wp_filter['tag']['array of priorities']['array of functions']
	if (!@in_array($function_to_add, $wp_filter[$tag]["$priority"])) {
		$wp_filter[$tag]["$priority"][] = $function_to_add;
	}
	return true;
}

function remove_filter($tag, $function_to_remove, $priority = 10) {
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

function do_action($tag, $string) {
	apply_filters($tag, $string, false);
	return $string;
}

function add_action($tag, $function_to_add, $priority = 10) {
	add_filter($tag, $function_to_add, $priority);
}

function remove_action($tag, $function_to_remove, $priority = 10) {
	remove_filter($tag, $function_to_remove, $priority);
}

function timer_stop($display = 0, $precision = 3) { //if called like timer_stop(1), will echo $timetotal
	global $bb_timestart, $timeend;
	$mtime = explode(' ', microtime());
	$timeend = $mtime[1] + $mtime[0];
	$timetotal = $timeend - $bb_timestart;
	if ($display)
		echo number_format($timetotal,$precision);
	return $timetotal;
}

function since($stamp) {
	$post = $stamp;                                                                         /* get a timestamp */
	$now = time();                                                                  /* get the current timestamp */
	$diff = ($now - $post);
	if ($diff <= 3600) {                                                                    /* is it less than an hour? */
	$mins = round($diff / 60);
	$since = "$mins mins";
	} else if (($diff <= 86400) && ($diff > 3600)) {                /* is it less than a day? */
	$hours = round($diff / 3600);
	if ($hours <= 1) {                                                              /* is it under two hours? */
	$since = "1 hour";
	} else {
	$since = "$hours hours";
	}
	} else if ($diff >= 86400) {                                                    /* is it more than a day? */
	$days = round($diff / 86400);
	if ($days <= 1) {
	$since = "1 day";
	} else {
	$since = "$days days";
	}
	}
	
	// $since = "Posted ".$since." ago";
	return $since;
}

function get_option( $option ) {
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
	echo get_option( $option ) ;
}

function add_query_arg() {
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

function remove_query_arg($key, $query) {
	add_query_arg($key, '', $query);
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

function current_time($type) {
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
	$title = apply_filters('pre_topic_title', $title);
	$forum = (int) $forum;
	$now   = current_time('mysql');

	if ( $forum && $title ) {
		$bbdb->query("INSERT INTO $bbdb->topics 
		(topic_title, topic_poster, topic_poster_name, topic_last_poster, topic_last_poster_name, topic_time, forum_id)
		VALUES
		('$title', $current_user->user_id, '$current_user->username', $current_user->user_id, '$current_user->username', '$now', $forum)");
		$topic_id = $bbdb->insert_id;
		$bbdb->query("UPDATE $bbdb->forums SET topics = topics + 1 WHERE forum_id = $forum");
		do_action('bb_new_topic', $topic_id);
		return $topic_id;
	} else {
		return false;
	}
}

function bb_new_post( $topic_id, $post ) {
	global $bbdb, $current_user;
	$post  = apply_filters('pre_post', $post);
	$tid   = (int) $topic_id;
	$now   = current_time('mysql');
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
		$bbdb->query("UPDATE $bbdb->topics SET topic_last_poster = $uid, topic_last_poster_name = '$uname',
		topic_last_post_id = $post_id, topic_posts = topic_posts + 1 WHERE topic_id = $tid");
		do_action('bb_new_post', $post_id);
		return $post_id;
	} else {
		return false;
	}
}

function get_post_link( $id ) {
	global $bbdb, $topic;
	$id = (int) $id;
	$topic_id = $bbdb->get_var("SELECT topic_id FROM $bbdb->posts WHERE post_id = $id");
	if ( !$topic_id )
		return false;
	$topic = $bbdb->get_row("SELECT * FROM $bbdb->topics WHERE topic_id = $topic_id"); 

	return get_topic_link() . "#post-$id";
}

function can_edit( $user_id, $admin_id = 0) {
	global $bbdb, $current_user;
	if ( !$admin_id )
		$admin_id = $current_user->user_id;
	$admin = bb_get_user( $admin_id );
	$user  = bb_get_user( $user_id  );

	if ( $admin_id === $user_id )
		return true;

	if ( $user->user_type < $admin->user_type )
		return true;
	else
		return false;
}

?>