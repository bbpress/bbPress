<?php

function get_header() {
	global $bb, $bbdb, $forum, $forum_id, $topic, $current_user;
	include( BBPATH . 'bb-templates/header.php');
}

function get_footer() {
	global $bb, $bbdb, $forum, $forum_id, $topic, $current_user;
	include( BBPATH . 'bb-templates/footer.php');
}

function profile_menu() {
	global $bb, $bbdb, $current_user, $user_id, $profile_menu, $self, $profile_page_title;
	
	$list  = "<ul id='profile-menu'>";
	$list .= "\n\t<li" . ( ( $self ) ? '' : ' class="current"' ) . '><a href="' . get_user_profile_link( $user_id ) . '">' . __('Profile') . '</a></li>';
	foreach ($profile_menu as $item) {
		// 0 = name, 1 = user_type, 2 = others, 3 = file
		$class = '';
		if ( $item[3] == $self ) {
			$class = ' class="current"';
			$profile_page_title = $item[0];
		}
		if ( can_access_tab( $item, $current_user->ID, $user_id ) )
			if ( file_exists($item[3]) || function_exists($item[3]) )
				$list .= "\n\t<li$class><a href='" . get_profile_tab_link($user_id, $item[0]) . "'>{$item[0]}</a></li>";
	}
	if ( $current_user ) :
		$list .= "\n\t<li class='last'><a href='" . bb_get_option('uri') . 'bb-login.php?logout' . "' title='" . __('Log out of this account') . "'>";
		$list .= 	__('Logout') . ' (' . $current_user->user_login . ')</a></li>';
	else:
		$list .=  "\n\t<li class='last'><a href='" . bb_get_option('uri') . "bb-login.php'>" . __('Login') . '</a></li>';
	endif;
	$list .= "\n</ul>";
	echo $list;
}

function login_form() {
	global $current_user, $bb;
	if ($current_user) {
		echo "<p>Welcome, $current_user->user_login! <a href='" . get_user_profile_link( $current_user->ID) . "'>View your profile &raquo;</a> 
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

function alt_class( $key, $others = '' ) {
	global $bb_alt;
	if ( !isset( $bb_alt[$key] ) ) $bb_alt[$key] = -1;
	++$bb_alt[$key];
	if ( $others xor $bb_alt[$key] % 2 )
		$class = ' class="' . ( ($others) ? $others : 'alt' ) . '"';
	elseif ( $others && $bb_alt[$key] % 2 )
		$class = ' class="' . $others . ' alt"';
	echo $class;
}

function is_front() {
	if ( 'index.php' == bb_find_filename($_SERVER['PHP_SELF']) )
		return true;
	else
		return false;
}

function is_forum() {
	if ( 'forum.php' == bb_find_filename($_SERVER['PHP_SELF']) )
		return true;
	else
		return false;
}

function is_tag() {
	if ( 'tags.php' == bb_find_filename($_SERVER['PHP_SELF']) )
		return true;
	else
		return false;
}

function is_topic() {
	if ( 'topic.php' == bb_find_filename($_SERVER['PHP_SELF']) )
		return true;
	else
		return false;
}

function is_bb_search() {
	if ( 'search.php' == bb_find_filename($_SERVER['PHP_SELF']) )
		return true;
	else
		return false;
}

function is_bb_profile() {
	if ( 'profile.php' == bb_find_filename($_SERVER['PHP_SELF']) )
		return true;
	else
		return false;
}

function is_bb_favorites() {
	if ( 'favorites.php' == bb_find_filename($_SERVER['PHP_SELF']) )
		return true;
	else
		return false;
}

function bb_title() {
	global $topic, $forum, $static_title, $tag;
	$title = '';
	if ( is_topic() )
		$title = get_topic_title(). ' &laquo; ';
	if ( is_forum() )
		$title = get_forum_name() . ' &laquo; ';
	if ( is_tag() )
		$title = get_tag_name() . ' &laquo; Tags ';
	if ( !empty($static_title) )
		$title = $static_title . ' &laquo; ';
	$title .= bb_get_option('name');
	echo $title;
}

function bb_feed_head() {
	global $tag;
	$feed_link = '';
	if ( is_topic() )
		$feed_link = '<link rel="alternate" type="application/rss+xml" title="Thread: ' . bb_specialchars( get_topic_title(), 1 ) . '" href="' . get_topic_rss_link() . '" />';
	elseif ( is_tag() && $tag )
		$feed_link = '<link rel="alternate" type="application/rss+xml" title="Tag: ' . bb_specialchars( get_tag_name(), 1 ) . '" href="' . get_tag_rss_link() . '" />';
	elseif ( is_front() )
		$feed_link = '<link rel="alternate" type="application/rss+xml" title="Recent Posts" href="' . get_recent_rss_link() . '" />';
	echo bb_apply_filters('bb_feed_head', $feed_link);
}

function get_recent_rss_link() {
	global $bb;
	if ( $bb->mod_rewrite )
		$link = bb_get_option('uri') . 'rss/';
	else
		$link = bb_get_option('uri') . "rss.php";
	return bb_apply_filters('get_recent_rss_link', $link);
}

// FORUMS

function forum_link() {
	echo bb_apply_filters('forum_link', get_forum_link() );
}

function get_forum_link( $id = 0 ) {
	global $forum, $bb;
	if ( $id )
		$forum = get_forum( $id );
	if ( $bb->mod_rewrite )
		$link = bb_get_option('uri') . 'forum/' . $forum->forum_id;
	else
		$link = bb_get_option('uri') . "forum.php?id=$forum->forum_id";

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
	echo bb_apply_filters( 'forum_pages', get_page_number_links( $page, $forum->topics ) );
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
	echo bb_apply_filters('topic_rss_link', get_topic_rss_link($id) );
}

function get_topic_rss_link( $id = 0 ) {
	global $topic;

	if ( $id )
		$topic = get_topic( $id );

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . "rss/topic/$topic->topic_id";
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
	if ( 1 == get_topic_posts() && ( is_front() || is_forum() ) )
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

function topic_start_time( $id = 0 ) {
	echo bb_apply_filters('topic_start_time', get_topic_start_time($id) );
}

function get_topic_start_time( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return $topic->topic_start_time;
}

function topic_start_date( $format = '', $id = 0 ) {
	echo gmdate( $format, get_topic_start_timestamp( $id ) );
}

function get_topic_start_timestamp( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return strtotime( $topic->topic_start_time );
}

function topic_resolved( $yes = 'resolved', $no = 'not resolved', $mu = 'not a support question', $id = 0 ) {
	global $current_user, $topic;
	if ( can_edit_topic( $topic->topic_id ) ) :
		$resolved_form  = '<form id="resolved" method="post" action="' . bb_get_option('uri') . 'topic-resolve.php">' . "\n";
		$resolved_form .= '<input type="hidden" name="id" value="' . $topic->topic_id . "\" />\n";
		$resolved_form .= '<select name="resolved" tabindex="2">' . "\n";

		$cases = array( 'yes', 'no', 'mu' );
		$resolved = get_topic_resolved( $id );
		foreach ( $cases as $case ) {
			$selected = ( $case == $resolved ) ? ' selected="selected"' : '';
			$resolved_form .= "<option value=\"$case\"$selected>${$case}</option>\n";
		}

		$resolved_form .= "</select>\n";
		$resolved_form .= '<input type="submit" name="submit" value="Change" />' . "\n</form>";
		echo $resolved_form;
	else:
		switch ( get_topic_resolved( $id ) ) {
			case 'yes' : echo $yes; break;
			case 'no'  : echo $no;  break;
			case 'mu'  : echo $mu;  break;
		}
	endif;
}	

function get_topic_resolved( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return $topic->topic_resolved;
}

function topic_last_post_link( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	echo get_post_link( $topic->topic_last_post_id );
}

function topic_pages() {
	global $topic, $page;
	echo bb_apply_filters( 'topic_pages', get_page_number_links( $page, $topic->topic_posts ) );
}

function get_page_number_links($page, $total) {
	$r = '';
	if ( $page )
		$r .=  '<a class="prev" href="' . bb_specialchars( bb_add_query_arg('page', $page - 1) ) . '">&laquo; Previous Page</a>' . "\n";
	if ( ( $total_pages = ceil( $total / bb_get_option('page_topics') ) ) > 1 ) {
		for ( $page_num = 0; $page_num < $total_pages; $page_num++ ) :
			if ( $page == $page_num ) :
				$r .= ( $page_num + 1 ) . "\n";
			else :
				$p = false;
				if ( $page_num < 2 || ( $page_num >= $page - 3 && $page_num <= $page + 3 ) || $page_num > $total_pages - 3 ) :
					$r .= '<a class="page-numbers" href="' . bb_specialchars( bb_add_query_arg('page', $page_num) ) . '">' . ( $page_num + 1 ) . "</a>\n";
					$in = true;
				elseif ( $in == true ) :
					$r .= "...\n";
					$in = false;
				endif;
			endif;
		endfor;
	}
	if ( ( $page + 1 ) * bb_get_option('page_topics') < $total )
		$r .=  '<a class="next" href="' . bb_specialchars( bb_add_query_arg('page', $page + 1) ) . '">Next Page &raquo;</a>' . "\n";
	return $r;
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
	global $bbdb;
	$id = get_post_author_id();
	if ( $id )
		if ( $user = bb_get_user( $id ) )
			return $user->user_login;
	else
		return 'Anonymous';
}

function post_author_link() {
	if ( get_user_link( get_post_author_id() ) ) {
		echo '<a href="' . get_user_link( get_post_author_id() ) . '">' . get_post_author() . '</a>';
	} else {
		post_author();
	}
}

function post_text() {
	echo bb_apply_filters('post_text', get_post_text() );
}

function get_post_text() {
	global $post;
	return $post->post_text;
}

function post_time() {
	echo bb_apply_filters('post_time', get_post_time() );
}

function get_post_time() {
	global $post;
	return bb_apply_filters('get_post_time', $post->post_time);
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
	if ( can_moderate( get_post_author_id() ) )
		echo bb_apply_filters('post_ip', get_post_ip() );
}

function post_edit_link() {
	global $current_user, $post;

	if ( can_edit_post( $post->post_id ) )
		echo "<a href='" . bb_get_option('uri') . 'edit.php?id=' . get_post_id() . "'>Edit</a>";
}

function post_delete_link() {
	global $current_user;

	if ( $current_user->user_type > 1 )
		echo "<a href='" . bb_get_option('uri') . 'bb-admin/delete-post.php?id=' . get_post_id() . "' onclick=\"return confirm('Are you sure you wanna delete that?')\">Delete</a>";
}

function topic_delete_link() {
	global $current_user;

	if ( $current_user->user_type > 1 )
		echo "<a href='" . bb_get_option('uri') . 'bb-admin/delete-topic.php?id=' . get_topic_id() . "' onclick=\"return confirm('Are you sure you wanna delete that?')\">Delete entire topic</a>";
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
		echo '<a href="' . get_user_profile_link( get_post_author_id() ) . '">' . $type . '</a>';
	}
}

// USERS
function user_profile_link( $id ) {
	echo bb_apply_filters('user_profile_link', get_user_profile_link( $id ));
}

function get_user_profile_link( $id ) {
	if ( bb_get_option('mod_rewrite') ) {
		$r = bb_get_option('uri') . 'profile/' . $id;
	} else {
		$r = bb_get_option('uri') . 'profile.php?id=' . $id;
	}
	return bb_apply_filters('get_user_profile_link', $r);
}

function profile_tab_link( $id, $tab ) {
	echo bb_apply_filters('profile_tab_link', get_profile_tab_link( $id, $tab ));
}

function get_profile_tab_link( $id, $tab ) {
	$tab = tag_sanitize($tab);
	if ( bb_get_option('mod_rewrite') )
		$r = get_user_profile_link( $id ) . "/$tab";
	else
		$r = bb_add_query_arg('tab', $tab, get_user_profile_link( $id ));
	return bb_apply_filters('get_profile_tab_link', $r);
}

function get_user_link( $user_id ) {
	global $bbdb;
	if ( $user_id )
		if ( $user = bb_get_user( $user_id ) )
			return bb_apply_filters('get_user_link', $user->user_url);
}

function user_link( $id ) {
	echo bb_apply_filters('user_link', get_user_link($id) );
}

function bb_label_user_type( $type ) {
	switch ($type) :
		case 0 :
			return __('Member');
			break;
		case 1 :
			return __('Moderator');
			break;
		case 2 :
			return __('Developer');
			break;
		case 5 :
			return __('Admin');
			break;
	endswitch;
}

function get_user_type_label( $type ) {
	return bb_apply_filters('get_user_type_label', $type );
}

function user_type_label( $type ) {
	echo bb_apply_filters( 'user_type_label', get_user_type_label( $type ) );
}

function get_user_type ( $id ) {
	global $bbdb;
	$user = bb_get_user( $id );
	if ( $user->user_status == 2 )
		return __('Inactive');
	if ( $id && false !== $user ) :
		if ( !empty( $user->title ) )
			return $user->title;
		return get_user_type_label( $user->user_type );
	else :
		return __('Unregistered');
	endif;
}

function user_type( $id ) {
	echo bb_apply_filters('user_type', get_user_type($id) );
}

function profile_pages() {
	global $user, $page;
	echo bb_apply_filters( 'topic_pages', get_page_number_links( $page, $user->topics_replied ) );
}

//TAGS
function topic_tags () {
	global $tags, $tag, $topic_tag_cache, $user_tags, $other_tags, $current_user;
	if ( is_array( $tags ) || $current_user )
		include( BBPATH . '/bb-templates/topic-tags.php');
}

function get_tag_page_link() {
	global $bb;
	if ( bb_get_option('mod_rewrite') )
		return $bb->tagpath . 'tags/';
	else
		return $bb->tagpath . 'tags.php';
}

function tag_page_link() {
	echo get_tag_page_link();
}

function get_tag_link( $tag_name = 0 ) {
	global $tag, $bb;
	if ( $tag_name )
		$tag = get_tag_by_name( $tag_name );
	if ( bb_get_option('mod_rewrite') )
		return bb_get_option('domain') . $bb->tagpath . 'tags/' . $tag->tag;
	else
		return bb_get_option('domain') . $bb->tagpath . 'tags.php?tag=' . $tag->tag;
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

function tag_rss_link( $id = 0 ) {
	echo bb_apply_filters('tag_rss_link', get_tag_rss_link($id) );
}

function get_tag_rss_link( $tag_id = 0 ) {
	global $tag;
	if ( $tag_id )
		$tag = get_tag( $tag_id );

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . "rss/tags/$tag->tag";
	else
		$link = bb_get_option('uri') . "rss.php?tag=$tag->tag";

	return bb_apply_filters('get_tag_rss_link', $link);
}

function tag_form() {
	global $topic, $current_user;
	if ( !$current_user || $current_user->user_type < 1 && !topic_is_open($topic->topic_id) )
		return false;
	else
		include( BBPATH . '/bb-templates/tag-form.php');
}

function tag_rename_form() {
	global $tag, $current_user;
	if ( $current_user->user_type < 2 )
		return false;
	$tag_rename_form  = '<form id="tag-rename" method="post" action="' . bb_get_option('uri') . 'bb-admin/tag-rename.php">' . "\n";
	$tag_rename_form .= "<p>\n" . '<input type="text"   name="tag" size="10" maxlength="30" />' . "\n";
	$tag_rename_form .= '<input type="hidden" name="id" value="' . $tag->tag_id . '" />' . "\n";
	$tag_rename_form .= '<input type="submit" name="Submit" value="Rename" />' . "\n</p>\n</form>";
	echo $tag_rename_form;
}

function tag_merge_form() {
	global $tag, $current_user;
	if ( $current_user->user_type < 2 )
		return false;
	$tag_merge_form  = '<form id="tag-merge" method="post" action="' . bb_get_option('uri') . 'bb-admin/tag-merge.php">' . "\n";
	$tag_merge_form .= "<p>Merge this tag into the tag specified</p>\n<p>\n" . '<input type="text"   name="tag" size="10" maxlength="30" />' . "\n";
	$tag_merge_form .= '<input type="hidden" name="id" value="' . $tag->tag_id . '" />' . "\n";
	$tag_merge_form .= '<input type="submit" name="Submit" value="Merge" ';
	$tag_merge_form .= 'onclick="return confirm(\'Are you sure you want to merge the \\\'' . $tag->raw_tag . '\\\' tag into the tag you specified? This is permanent and cannot be undone.\')" />' . "\n</p>\n</form>";
	echo $tag_merge_form;
}

function tag_destroy_form() {
	global $tag, $current_user;
	if ( $current_user->user_type < 2 )
		return false;
	$tag_destroy_form  = '<form id="tag-destroy" method="post" action="' . bb_get_option('uri') . 'bb-admin/tag-destroy.php">' . "\n";
	$tag_destroy_form .= '<input type="hidden" name="id" value="' . $tag->tag_id . '" />' . "\n";
	$tag_destroy_form .= '<input type="submit" name="Submit" value="Destroy" ';
	$tag_destroy_form .= 'onclick="return confirm(\'Are you sure you want to destroy the \\\'' . $tag->raw_tag . '\\\' tag? This is permanent and cannot be undone.\')" />' . "\n</form>";
	echo $tag_destroy_form;
}

function tag_remove_link( $tag_id = 0, $user_id = 0, $topic_id = 0 ) {
	global $tag, $current_user;
	if ( $current_user->user_type < 1 && ( !topic_is_open($tag->topic_id) || $current_user->ID != $tag->user_id ) )
		return false;
	echo '[<a href="' . bb_get_option('uri') . 'tag-remove.php?tag=' . $tag->tag_id . '&user=' . $tag->user_id . '&topic=' . $tag->topic_id . '" onclick="return confirm(\'Are you sure you want to remove the \\\'' . $tag->raw_tag . '\\\' tag?\')" title="Remove this tag">x</a>]';
}

function tag_heat_map( $smallest = 8, $largest = 22, $unit = 'pt', $limit = 45 ) {
	global $tag;

	$tags = get_top_tags( false, $limit );
	if (empty($tags))
		return;
	foreach ( $tags as $tag ) {
		$counts{$tag->raw_tag} = $tag->tag_count;
		$taglinks{$tag->raw_tag} = get_tag_link();
	}

	$spread = max($counts) - min($counts); 
	if ( $spread <= 0 )
		$spread = 1;
	$fontspread = $largest - $smallest;
	$fontstep = $spread / $fontspread;
	if ($fontspread <= 0) { $fontspread = 1; }
	uksort($counts, 'strnatcasecmp');
	foreach ($counts as $tag => $count) {
		$taglink = $taglinks{$tag};
		print "<a href='$taglink' title='$count topics' style='font-size: ".
		($smallest + ($count/$fontstep))."$unit;'>$tag</a> \n";
	}
}

function tag_pages() {
	global $page, $tagged_topic_count;
	echo bb_apply_filters( 'topic_pages', get_page_number_links( $page, $tagged_topic_count ) );
}

function forum_dropdown() {
	$forums = get_forums();
	echo '<select name="forum_id" tabindex="4">';
    
	foreach ( $forums as $forum ) :
		echo "<option value='$forum->forum_id'>$forum->forum_name</option>";
	endforeach;
	echo '</select>';
}

//FAVORITES
function favorites_link() {
	echo bb_apply_filters('favorites_link', get_favorites_link());
}

function get_favorites_link() {
	global $current_user;
	return bb_apply_filters('get_favorites_link', get_profile_tab_link($current_user->ID, 'favorites'));
}

function user_favorites_link($add = 'Add to Favorites', $rem = 'Remove from Favorites') {
	global $topic, $current_user;
	if ( $favs = explode(',', $current_user->favorites) )
		if ( in_array($topic->topic_id, $favs) ) :
			$favs = array('fav' => '0', 'topic_id' => $topic->topic_id);
			$text = $rem;
		else :
			$favs = array('fav' => '1', 'topic_id' => $topic->topic_id);
			$text = $add;
		endif;
		echo '<a href="' . bb_add_query_arg( $favs, get_favorites_link() ) . '">' . $text . '</a>';
}

function favorites_rss_link( $id = 0 ) {
	echo bb_apply_filters('favorites_rss_link', get_favorites_rss_link( $id ));
}

function get_favorites_rss_link( $id = 0 ) {
	global $user;
	if ( $id )
		$user = bb_get_user( $id );

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . "rss/profile/$user->ID";
	else
		$link = bb_get_option('uri') . "rss.php?profile=$user->ID";

	return bb_apply_filters('get_favorites_rss_link', $link);
}
?>
