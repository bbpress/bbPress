<?php

function get_header() {
	global $bb;
	include( ABSPATH . '/bb-templates/header.php');
}

function get_footer() {
	global $bbdb;
	include( ABSPATH . '/bb-templates/footer.php');
}

function login_form() {
	global $current_user;
	if ($current_user) {
		echo "Welcome, $current_user->username! <a href='/user/$current_user->user_id'>View your profile &raquo;</a> 
		<small>(<a href='" . get_option('uri') . "bb-login.php?logout'>Logout</a>)</small>";
	} else {
		require( ABSPATH . '/bb-templates/login-form.php');
	}
}

function post_form() {
	global $current_user;
	if ($current_user) {
		require( ABSPATH . '/bb-templates/post-form.php');
	} else {
		echo "<p>You must login to post.";
		require( ABSPATH . '/bb-templates/login-form.php');
	}
}

function alt_class( $key ) {
	global $bb_alt;
	if ( !isset( $bb_alt[$key] ) ) $bb_alt[$key] = -1;
	++$bb_alt[$key];
	if ( $bb_alt[$key] % 2 ) echo ' class="alt"';
}

function is_front() {
	if ( '/index.php' == $_SERVER['PHP_SELF'] )
		return true;
	else
		return false;
}

function is_forum() {
	if ( '/forum.php' == $_SERVER['PHP_SELF'] )
		return true;
	else
		return false;
}

function is_topic() {
	if ( '/topic.php' == $_SERVER['PHP_SELF'] )
		return true;
	else
		return false;
}

function bb_title() {
	global $topic, $forum, $static_title;
	$title = '';
	if ( is_topic() )
		$title = get_topic_title(). ' &laquo; ';
	if ( is_forum() )
		$title = get_forum_name() . ' &laquo; ';
	if ( !empty($static_title) )
		$title = $static_title . ' &laquo; ';
	$title .= get_option('name');
	echo $title;
}

// FORUMS

function forum_link() {
	global $forum, $bb;
	if ( $bb->mod_rewrite )
		$link = $bb->path . $forum->nice_name;
	else
		$link = $bb->path . "forum.php?id=$forum->forum_id";

	echo apply_filters('forum_link', $link);
}

function forum_name() {
	echo apply_filters('forum_name', get_forum_name() );
}
function get_forum_id() {
	global $forum;
	return $forum->forum_id;
}
function forum_id() {
	echo apply_filters('forum_id', get_forum_id() );
}
function get_forum_name() {
	global $forum;
	return apply_filters('get_forum_name', $forum->forum_name);
}

function forum_description() {
	global $forum;
	echo apply_filters('forum_description', $forum->forum_desc);
}

function forum_topics() {
	global $forum;
	echo apply_filters('forum_topics', $forum->topics);
}

function forum_posts() {
	global $forum;
	echo apply_filters('forum_posts', $forum->posts);
}

function forum_pages() {
	global $forum, $page;
	if ( 0 == $forum->posts )
		$forum->posts = 1;
	$r = '';
	if ( get_option('mod_rewrite') ) {
		
	} else {
		if ( $page && ($page * get_option('page_topics')) < $forum->posts )
			$r .=  '<a class="prev" href="' . bb_specialchars( add_query_arg('page', $page - 1) ) . '">&laquo; Previous Page</a>';
		if ( get_option('page_topics') < $forum->posts )
			$r .=  ' <a class="next" href="' . bb_specialchars( add_query_arg('page', $page + 1) ) . '">Next Page &raquo;</a>';
	}
	echo apply_filters('forum_pages', $r);
}

// TOPICS
function get_topic_id() {
	global $topic;
	return $topic->topic_id;
}

function topic_id() {
	echo apply_filters('topic_id', get_topic_id() );
}

function topic_link() {
	echo apply_filters('topic_link', get_topic_link() );
}

function get_topic_link() {
	global $topic, $bb;

	if ( get_option('mod_rewrite') )
		$link = get_option('path') . $topic->topic_id;
	else
		$link = get_option('path') . "topic.php?id=$topic->topic_id";

	return apply_filters('get_topic_link', $link);
}

function topic_title() {
	global $topic;
	echo apply_filters('topic_title', get_topic_title() );
}

function get_topic_title() {
	global $topic;
	return $topic->topic_title;
}

function topic_posts() {
	global $topic;
	echo apply_filters('topic_posts', $topic->topic_posts);
}

function topic_last_poster() {
	global $topic;
	echo apply_filters('topic_last_poster', $topic->topic_last_poster_name);
}

function topic_time() {
	global $topic;
	echo apply_filters('topic_time', $topic->topic_time);
}

function topic_pages() {
	global $topic, $page;
	if ( 0 == $topic->topic_posts )
		$topic->topic_posts = 1;
	$r = '';
	if ( get_option('mod_rewrite') ) {
		
	} else {
		if ( $page && ($page * get_option('page_topics')) < $topic->topic_posts )
			$r .=  '<a class="prev" href="' . bb_specialchars( add_query_arg('page', $page - 1) ) . '">&laquo; Previous Page</a>';
		if ( get_option('page_topics') < $topic->topic_posts )
			$r .=  ' <a class="next" href="' . bb_specialchars( add_query_arg('page', $page + 1) ) . '">Next Page &raquo;</a>';
	}
	echo apply_filters('forum_pages', $r);
}

// POSTS

function post_id() {
	global $post;
	echo $post->post_id;
}

function post_author() {
	echo apply_filters('post_author', get_post_author() );
}

function get_post_author() {
	global $bbdb, $user_cache;
	$id = get_post_author_id();
	if ( $id ) :
		if ( isset( $user_cache[$id] ) ) {
			return $user_cache[$id]->username;
		} else {
			$user_cache[$id] = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_id = $id");
			return $user_cache[$id]->username;
		}
	else : 
		return 'Anonymous';
	endif;
}

function post_author_link() {
	if ( get_user_link( get_post_author_id() ) ) {
		echo '<a href="' . get_user_link( get_post_author_id() ) . '">' . get_post_author() . '</a>';
	} else {
		post_author();
	}
}

function post_text() {
	global $post;
	echo apply_filters('post_text', $post->post_text);
}

function post_time() {
	global $post;
	echo apply_filters('post_time', $post->post_time);
}

function post_author_id() {
	echo apply_filters('post_author_id', get_post_author_id() );
}
function get_post_author_id() {
	global $post;
	return $post->poster_id;
}

function post_author_type() {
	$type = get_user_type ( get_post_author_id() );
	if ('Unregistered' == $type) {
		echo $type;
	} else {
		echo '<a href="' . user_profile_link( get_post_author_id() ) . '">' . $type . '</a>';
	}
}

// USERS
function user_profile_link( $id ) {
	if ( get_option('mod_rewrite') ) {
		$r = get_option('domain') . get_option('path') . 'user/' . $id;
	} else {
		$r =  get_option('domain') . get_option('path') . 'profile.php?id=' . $id;
	}
	return $r;
}

function get_user_link( $id ) {
	global $user_cache, $bbdb;
	if ( $id ) :
		if ( isset( $user_cache[$id] ) ) {
			return $user_cache[$id]->user_website;
		} else {
			$user_cache[$id] = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_id = $id");
			return $user_cache[$id]->user_website;
		}
	endif;
}

function user_link( $id ) {
	echo apply_filters('user_link', get_user_link($id) );
}

function get_user_type ( $id) {
	global $user_cache, $bbdb;
	if ( $id ) :
		if ( isset( $user_cache[$id] ) ) {
			$type = $user_cache[$id]->user_type;
		} else {
			$user_cache[$id] = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_id = $id");
			$type = $user_cache[$id]->user_type;
		}
		switch ($type) :
			case 0 :
				return 'Member';
				break;
			case 1 :
				return 'Moderator';
				break;
			case 2 :
				return 'Developer';
				break;
			case 5 :
				return 'Admin';
				break;
		endswitch;
	else :
		return 'Unregistered';
	endif;
}

function user_type( $id ) {
	echo apply_filters('user_type', get_user_type($id) );
}


?>