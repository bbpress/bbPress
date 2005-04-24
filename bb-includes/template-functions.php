<?php

function get_header() {
	global $bb, $bbdb, $forum, $forum_id, $topic;
	include( BBPATH . '/bb-templates/header.php');
}

function get_footer() {
	global $bb, $bbdb, $forum, $forum_id, $topic;
	include( BBPATH . '/bb-templates/footer.php');
}

function login_form() {
	global $current_user, $bb;
	if ($current_user) {
		echo "<p>Welcome, $current_user->username! <a href='" . user_profile_link( $current_user->user_id) . "'>View your profile &raquo;</a> 
		<small>(<a href='" . bb_get_option('uri') . "bb-login.php?logout'>Logout</a>)</small></p>";
	} else {
		include( BBPATH . '/bb-templates/login-form.php');
	}
}

function search_form( $q = '' ) {
	require( BBPATH . '/bb-templates/search-form.php');
}

function post_form() {
	global $current_user, $bb;
	if ($current_user) {
		include( BBPATH . '/bb-templates/post-form.php');
	} else {
		echo "<p>You must login to post.</p>";
		include( BBPATH . '/bb-templates/login-form.php');
	}
}

function edit_form( $post = '', $topic_title = '' ) {
	require( BBPATH . '/bb-templates/edit-form.php');
}

function alt_class( $key ) {
	global $bb_alt;
	if ( !isset( $bb_alt[$key] ) ) $bb_alt[$key] = -1;
	++$bb_alt[$key];
	if ( $bb_alt[$key] % 2 ) echo ' class="alt"';
}

function is_front() {
	if ( 'index.php' == basename($_SERVER['SCRIPT_NAME']) )
		return true;
	else
		return false;
}

function is_forum() {
	if ( 'forum.php' == basename($_SERVER['SCRIPT_NAME']) )
		return true;
	else
		return false;
}

function is_topic() {
	if ( 'topic.php' == basename($_SERVER['SCRIPT_NAME']) )
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
	$title .= bb_get_option('name');
	echo $title;
}

// FORUMS

function forum_link() {
	global $forum;
	echo bb_apply_filters('forum_link', get_forum_link() );
}

function get_forum_link( $id = 0 ) {
	global $forum, $bb;
	if ( $id )
		$forum = get_forum( $id );
	if ( $bb->mod_rewrite )
		$link = $bb->path . 'forum/' . $forum->forum_id;
	else
		$link = $bb->path . "forum.php?id=$forum->forum_id";

	return bb_apply_filters('get_forum_link', $link);
}

function forum_name() {
	echo bb_apply_filters('forum_name', get_forum_name() );
}
function get_forum_id() {
	global $forum;
	return $forum->forum_id;
}
function forum_id() {
	echo bb_apply_filters('forum_id', get_forum_id() );
}
function get_forum_name() {
	global $forum;
	return bb_apply_filters('get_forum_name', $forum->forum_name);
}

function forum_description() {
	global $forum;
	echo bb_apply_filters('forum_description', $forum->forum_desc);
}

function forum_topics() {
	global $forum;
	echo bb_apply_filters('forum_topics', $forum->topics);
}

function forum_posts() {
	global $forum;
	echo bb_apply_filters('forum_posts', $forum->posts);
}

function forum_pages() {
	global $forum, $page;
	if ( 0 == $forum->posts )
		$forum->posts = 1;
	$r = '';
	if ( bb_get_option('mod_rewrite') ) {
		
	} else {
		if ( $page && ($page * bb_get_option('page_topics')) < $forum->posts )
			$r .=  '<a class="prev" href="' . bb_specialchars( bb_add_query_arg('page', $page - 1) ) . '">&laquo; Previous Page</a>';
		if ( bb_get_option('page_topics') < $forum->posts )
			$r .=  ' <a class="next" href="' . bb_specialchars( bb_add_query_arg('page', $page + 1) ) . '">Next Page &raquo;</a>';
	}
	echo bb_apply_filters('forum_pages', $r);
}

// TOPICS
function get_topic_id() {
	global $topic;
	return $topic->topic_id;
}

function topic_id() {
	echo bb_apply_filters('topic_id', get_topic_id() );
}

function topic_link( $id = 0 ) {
	echo bb_apply_filters('topic_link', get_topic_link($id) );
}

function topic_rss_link( $id = 0 ) {
	echo bb_apply_filters('topic_link', get_topic_rss_link($id) );
}

function get_topic_rss_link( $id = 0 ) {
	global $topic;

	if ( $id )
		$topic = get_topic( $id );

	if ( bb_get_option('mod_rewrite') )
		$link = get_topic_link() . '/rss/';
	else
		$link = bb_get_option('uri') . "rss.php?topic=$topic->topic_id";

	return bb_apply_filters('get_topic_rss_link', $link);
}

function get_topic_link( $id = 0 ) {
	global $topic;

	if ( $id )
		$topic = get_topic( $id );

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . 'topic/' . $topic->topic_id;
	else
		$link = bb_get_option('uri') . "topic.php?id=$topic->topic_id";

	return bb_apply_filters('get_topic_link', $link);
}

function topic_title( $id = 0 ) {
	echo bb_apply_filters('topic_title', get_topic_title( $id ) );
}

function get_topic_title( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return $topic->topic_title;
}

function topic_posts() {
	echo bb_apply_filters('topic_posts', get_topic_posts() );
}

function get_topic_posts() {
	global $topic;
	return bb_apply_filters('get_topic_posts', $topic->topic_posts);
}

function topic_noreply( $title ) {
	if ( 1 == get_topic_posts() )
		$title = "<strong>$title</strong>";
	return $title;
}

function topic_last_poster() {
	global $topic;
	echo bb_apply_filters('topic_last_poster', $topic->topic_last_poster_name);
}

function topic_time( $id = 0 ) {
	echo bb_apply_filters('topic_time', get_topic_time($id) );
}

function get_topic_time( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return $topic->topic_time;
}

function topic_date( $format = '', $id = 0 ) {
	echo gmdate( $format, get_topic_timestamp( $id ) );
}

function get_topic_timestamp( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return strtotime( $topic->topic_time );
}

function topic_pages() {
	global $topic, $page;
	if ( 0 == $topic->topic_posts )
		$topic->topic_posts = 1;
	$r = '';
	if ( bb_get_option('mod_rewrite') ) {
		if ( $page && ($page * bb_get_option('page_topics')) < $topic->topic_posts )
			$r .=  '<a class="prev" href="' . bb_specialchars( bb_add_query_arg('page', $page - 1) ) . '">&laquo; Previous Page</a>';
		if ( ( ($page + 1) * bb_get_option('page_topics')) < $topic->topic_posts )
			$r .=  ' <a class="next" href="' . bb_specialchars( bb_add_query_arg('page', $page + 1) ) . '">Next Page &raquo;</a>';		
	} else {
		if ( $page && ($page * bb_get_option('page_topics')) < $topic->topic_posts )
			$r .=  '<a class="prev" href="' . bb_specialchars( bb_add_query_arg('page', $page - 1) ) . '">&laquo; Previous Page</a>';
		if ( ( ($page + 1) * bb_get_option('page_topics')) < $topic->topic_posts )
			$r .=  ' <a class="next" href="' . bb_specialchars( bb_add_query_arg('page', $page + 1) ) . '">Next Page &raquo;</a>';
	}
	echo bb_apply_filters('forum_pages', $r);
}

// POSTS

function post_id() {
	global $post;
	echo $post->post_id;
}

function get_post_id() {
	global $post;
	return $post->post_id;
}

function post_author() {
	echo bb_apply_filters('post_author', get_post_author() );
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
	echo bb_apply_filters('post_text', get_post_text() );
}

function get_post_text() {
	global $post;
	return $post->post_text;
}

function post_time() {
	global $post;
	echo bb_apply_filters('post_time', $post->post_time);
}

function post_date( $format ) {
	echo gmdate( $format, get_post_timestamp() );
}

function get_post_timestamp() {
	global $post;
	return strtotime( $post->post_time );
}

function get_post_ip() {
	global $post;
	return $post->poster_ip;
}

function post_ip() {
	if ( can_edit( get_post_author_id() ) )
		echo bb_apply_filters('post_ip', get_post_ip() );
}

function post_edit_link() {
	global $current_user;
	$how_old = bb_current_time() - get_post_timestamp();
	$limit   = bb_get_option('edit_lock') * 60;

	if ( ( $current_user->user_type < 1 ) && ( $how_old > $limit ) )
		return false;

	if ( can_edit( get_post_author_id() ) )
		echo "<a href='" . bb_get_option('uri') . 'edit.php?id=' . get_post_id() . "'>Edit</a>";
}

function post_delete_link() {
	global $current_user;

	if ( $current_user->user_type > 1 )
		echo "<a href='" . bb_get_option('uri') . 'bb-admin/delete-post.php?id=' . get_post_id() . "'>Delete</a>";
}

function topic_delete_link() {
	global $current_user;

	if ( $current_user->user_type > 1 )
		echo "<a href='" . bb_get_option('uri') . 'bb-admin/delete-topic.php?id=' . get_topic_id() . "'>Delete entire topic</a>";
}

function topic_close_link() {
	global $current_user;
	if ( $current_user->user_type > 1 ) {
		if ( topic_is_open( get_topic_id() ) )
			$text = 'Close topic';
		else
			$text = 'Open topic';
		echo "<a href='" . bb_get_option('uri') . 'bb-admin/topic-toggle.php?id=' . get_topic_id() . "'>$text</a>";
	}
}

function topic_sticky_link() {
	global $current_user;
	if ( $current_user->user_type > 1 ) {
		if ( topic_is_sticky( get_topic_id() ) )
			$text = 'Unstick topic';
		else
			$text = 'Stick topic';
		echo "<a href='" . bb_get_option('uri') . 'bb-admin/sticky.php?id=' . get_topic_id() . "'>$text</a>";
	}
}


function post_author_id() {
	echo bb_apply_filters('post_author_id', get_post_author_id() );
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
	if ( bb_get_option('mod_rewrite') ) {
		$r = bb_get_option('domain') . bb_get_option('path') . 'profile/' . $id;
	} else {
		$r =  bb_get_option('domain') . bb_get_option('path') . 'profile.php?id=' . $id;
	}
	return $r;
}

function get_user_link( $id ) {
	global $user_cache, $bbdb;
	if ( $id ) :
		if ( isset( $user_cache[$id] ) ) {
			return bb_apply_filters('get_user_link', $user_cache[$id]->user_website);
		} else {
			$user_cache[$id] = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_id = $id");
			return bb_apply_filters('get_user_link', $user_cache[$id]->user_website);
		}
	endif;
}

function user_link( $id ) {
	echo bb_apply_filters('user_link', get_user_link($id) );
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
		if ( !empty( $user_cache[$id]->user_title ) )
			return $user_cache[$id]->user_title;

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
	echo bb_apply_filters('user_type', get_user_type($id) );
}

function topic_tags () {
	global $tags, $tag, $topic_tag_cache, $user_tags, $other_tags;
	if ( is_array( $tags ) )
		include( BBPATH . '/bb-templates/topic-tags.php');
}

function get_tag_link( $id = 0 ) {
	global $tag, $bb;
	if ( bb_get_option('mod_rewrite') )
		return $bb->path . 'tags/' . $tag->tag;
	else
		return $bb->path . 'tags.php?tag=' . $tag->tag;
}

function tag_link( $id = 0 ) {
	echo get_tag_link( $id );
}

function get_tag_name( $id = 0 ) {
	global $tag;
	return $tag->raw_tag;
}

function tag_name( $id = 0 ) {
	echo get_tag_name( $id );
}

?>