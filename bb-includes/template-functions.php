<?php

function bb_get_header() {
	global $bb, $bbdb, $forum, $forum_id, $topic, $bb_current_user;
	if (file_exists( BBPATH . 'my-templates/header.php') )
		include( BBPATH . 'my-templates/header.php');
	else	include( BBPATH . 'bb-templates/header.php');
}

function bb_stylesheet_uri() {
	if ( file_exists( BBPATH . 'my-templates/style.css') )
		echo bb_get_option('uri') . 'my-templates/style.css';
	else	echo bb_get_option('uri') . 'bb-templates/style.css';
}

function bb_get_footer() {
	global $bb, $bbdb, $forum, $forum_id, $topic, $bb_current_user;
	if (file_exists( BBPATH . 'my-templates/footer.php') )
		include( BBPATH . 'my-templates/footer.php');
	else	include( BBPATH . 'bb-templates/footer.php');
}

function bb_head() {
        do_action('bb_head');
}

function profile_menu() {
	global $bb, $bbdb, $bb_current_user, $user_id, $profile_menu, $self, $profile_page_title;
	$list  = "<ul id='profile-menu'>";
	$list .= "\n\t<li" . ( ( $self ) ? '' : ' class="current"' ) . '><a href="' . get_user_profile_link( $user_id ) . '">' . __('Profile') . '</a></li>';
	foreach ($profile_menu as $item) {
		// 0 = name, 1 = users cap, 2 = others cap, 3 = file
		$class = '';
		if ( $item[3] == $self ) {
			$class = ' class="current"';
			$profile_page_title = $item[0];
		}
		if ( can_access_tab( $item, $bb_current_user->ID, $user_id ) )
			if ( file_exists($item[3]) || function_exists($item[3]) )
				$list .= "\n\t<li$class><a href='" . wp_specialchars( get_profile_tab_link($user_id, $item[0]) ) . "'>{$item[0]}</a></li>";
	}
	if ( bb_is_user_logged_in() ) :
		$list .= "\n\t<li class='last'><a href='" . bb_get_option('uri') . 'bb-login.php?logout' . "' title='" . __('Log out of this account') . "'>";
		$list .= 	__('Logout') . ' (' . get_user_name( $bb_current_user->ID ) . ')</a></li>';
	else:
		$list .=  "\n\t<li class='last'><a href='" . bb_get_option('uri') . "bb-login.php'>" . __('Login') . '</a></li>';
	endif;
	$list .= "\n</ul>";
	echo $list;
}

function login_form() {
	global $bb_current_user, $bb;
	if ( bb_is_user_logged_in() ) {
        printf('<p class="login">'. __('Welcome, %1$s!'). ' <a href="' . get_user_profile_link( $bb_current_user->ID ) . '">'. __('View your profile') ."&raquo;</a>\n<small>(",get_user_name( $bb_current_user->ID ));
	if ( bb_current_user_can('moderate') )
		echo "<a href='" . bb_get_option('uri') . "bb-admin/'>Admin</a> | ";
	echo "<a href='" . bb_get_option('uri') . "bb-login.php?logout'>". __('Logout') ."</a>)</small></p>";
	} else {
		include( BBPATH . '/bb-templates/login-form.php');
	}
}

function search_form( $q = '' ) {
	require( BBPATH . '/bb-templates/search-form.php');
}

function bb_post_template() {
	global $bb_current_user, $topic, $bb_post;
	if ( file_exists( BBPATH . 'my-templates/post.php' ) ) {
		include( BBPATH . 'my-templates/post.php' );
	} else	{
		include( BBPATH . 'bb-templates/post.php' );
	}
}

function post_form() {
	global $bb_current_user, $bb, $page, $topic;
	$add = topic_pages_add();
	if ( ( is_topic() && bb_current_user_can('write_posts') && $page == get_page_number( $topic->topic_posts + $add ) ) || ( !is_topic() && bb_current_user_can('write_topics') ) ) {
		echo "<form class='postform' name='postform' id='postform' method='post' action='" . bb_get_option('uri') . "bb-post.php'>\n";
		if ( file_exists( BBPATH . 'my-templates/post-form.php' ) ) {
			include( BBPATH . 'my-templates/post-form.php' );
		} else {
			include( BBPATH . 'bb-templates/post-form.php');
		}
		bb_nonce_field( is_topic() ? 'create-post_' . $topic->topic_id : 'create-topic' );
		echo "\n</form>";
	} elseif ( !bb_is_user_logged_in() ) {
		echo "<p>You must login to post.</p>";
		include( BBPATH . 'bb-templates/login-form.php');
	}
}

function edit_form() {
	global $bb_post, $topic_title;
	echo "<form name='post' id='post' method='post' action='" . bb_get_option('uri')  . "bb-edit.php'>\n";
	require( BBPATH . '/bb-templates/edit-form.php');
	bb_nonce_field( 'edit-post_' . $bb_post->post_id );
	echo "\n</form>";
}

function alt_class( $key, $others = '' ) {
	echo get_alt_class( $key, $others );
}

function get_alt_class( $key, $others = '' ) {
	global $bb_alt;
	$class = '';
	if ( !isset( $bb_alt[$key] ) ) $bb_alt[$key] = -1;
	++$bb_alt[$key];
	if ( $others xor $bb_alt[$key] % 2 )
		$class = ' class="' . ( ($others) ? $others : 'alt' ) . '"';
	elseif ( $others && $bb_alt[$key] % 2 )
		$class = ' class="' . $others . ' alt"';
	return $class;
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

function is_bb_feed() {
	if ( 'rss.php' == bb_find_filename($_SERVER['PHP_SELF']) )
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

function is_view() {
	if ( 'view.php' == bb_find_filename($_SERVER['PHP_SELF']) )
		return true;
	else
		return false;
}

function bb_get_title() {
	global $topic, $forum, $static_title, $tag, $user;
	$title = '';
	if ( is_topic() )
		$title = get_topic_title(). ' &laquo; ';
	if ( is_forum() )
		$title = get_forum_name() . ' &laquo; ';
	if ( is_tag() )
		$title = wp_specialchars( get_tag_name() ). ' &laquo; Tags &laquo; ';
	if ( is_bb_profile() )
		$title = $user->user_login . ' &laquo; ';
	if ( !empty($static_title) )
		$title = $static_title . ' &laquo; ';
	$title .= bb_get_option('name');
	return $title;
}

function bb_title() {
	$title = bb_get_title();
	echo $title;
}

function bb_feed_head() {
	global $tag;
	$feed_link = '';
	if ( is_topic() )
		$feed_link = '<link rel="alternate" type="application/rss+xml" title="Thread: ' . wp_specialchars( get_topic_title(), 1 ) . '" href="' . get_topic_rss_link() . '" />';
	elseif ( is_tag() && $tag )
		$feed_link = '<link rel="alternate" type="application/rss+xml" title="Tag: ' . wp_specialchars( get_tag_name(), 1 ) . '" href="' . get_tag_rss_link() . '" />';
	elseif ( is_front() )
		$feed_link = '<link rel="alternate" type="application/rss+xml" title="Recent Posts" href="' . get_recent_rss_link() . '" />';
	echo apply_filters('bb_feed_head', $feed_link);
}

function get_recent_rss_link() {
	global $bb;
	if ( $bb->mod_rewrite )
		$link = bb_get_option('uri') . 'rss/';
	else
		$link = bb_get_option('uri') . "rss.php";
	return apply_filters('get_recent_rss_link', $link);
}

// FORUMS

function forum_link() {
	echo apply_filters('forum_link', get_forum_link() );
}

function get_forum_link( $id = 0, $page = 1 ) {
	global $forum, $bb;
	if ( $id )
		$forum = get_forum( $id );
	if ( $bb->mod_rewrite )
		$link = bb_get_option('uri') . "forum/$forum->forum_id" . ( 1 < $page ? "/page/$page" : '' );
	else
		$link = bb_get_option('uri') . "forum.php?id=$forum->forum_id" . ( 1 < $page ? "&page=$page" : '' );

	return apply_filters('get_forum_link', $link);
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
	echo apply_filters('forum_description', get_forum_description());
}

function get_forum_description() {
	global $forum;
	return apply_filters('get_forum_description', $forum->forum_desc);
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
	echo apply_filters( 'forum_pages', get_page_number_links( $page, $forum->topics ) );
}

// TOPICS
function get_topic_id() {
	global $topic;
	return $topic->topic_id;
}

function topic_id() {
	echo apply_filters('topic_id', get_topic_id() );
}

function topic_link( $id = 0, $page = 1 ) {
	echo apply_filters('topic_link', get_topic_link($id) );
}

function get_topic_link( $id = 0, $page = 1 ) {
	global $topic;

	if ( $id )
		$topic = get_topic( $id );

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . "topic/$topic->topic_id" . ( 1 < $page ? "/page/$page" : '' );
	else
		$link = bb_get_option('uri') . "topic.php?id=$topic->topic_id" . ( 1 < $page ? "&page=$page" : '' );

	if ( bb_current_user_can('write_posts') )
		$link = add_query_arg( array( 'replies' => $topic->topic_posts ), $link );

	return apply_filters('get_topic_link', $link);
}

function topic_rss_link( $id = 0 ) {
	echo apply_filters('topic_rss_link', get_topic_rss_link($id) );
}

function get_topic_rss_link( $id = 0 ) {
	global $topic;

	if ( $id )
		$topic = get_topic( $id );

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . "rss/topic/$topic->topic_id";
	else
		$link = bb_get_option('uri') . "rss.php?topic=$topic->topic_id";

	return apply_filters('get_topic_rss_link', $link);
}

function topic_title( $id = 0 ) {
	echo apply_filters('topic_title', get_topic_title( $id ) );
}

function get_topic_title( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return $topic->topic_title;
}

function topic_posts() {
	echo apply_filters( 'topic_posts', get_topic_posts() );
}

function get_topic_posts() {
	global $topic;
	return apply_filters( 'get_topic_posts', $topic->topic_posts );
}

function get_topic_deleted_posts() {
	global $topic;
	return apply_filters( 'get_topic_deleted_posts', $topic->deleted_posts );
}

function topic_noreply( $title ) {
	if ( 1 == get_topic_posts() && ( is_front() || is_forum() ) )
		$title = "<strong>$title</strong>";
	return $title;
}

function topic_last_poster() {
	global $topic;
	echo apply_filters('topic_last_poster', $topic->topic_last_poster_name);
}

function topic_author() {
	global $topic;
	echo apply_filters( 'topic_author', $topic->topic_poster_name );
}

function topic_time( $id = 0 ) {
	echo apply_filters('topic_time', get_topic_time($id) );
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
	echo apply_filters('topic_start_time', get_topic_start_time($id) );
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
	global $bb_current_user, $topic;
	if ( bb_current_user_can( 'edit_topic', $topic->topic_id ) ) :
		$resolved_form  = '<form id="resolved" method="post" action="' . bb_get_option('uri') . 'topic-resolve.php"><div>' . "\n";
		$resolved_form .= '<input type="hidden" name="id" value="' . $topic->topic_id . "\" />\n";
		$resolved_form .= '<select name="resolved" id="resolvedformsel" tabindex="2">' . "\n";

		$cases = array( 'yes', 'no', 'mu' );
		$resolved = get_topic_resolved( $id );
		foreach ( $cases as $case ) {
			$selected = ( $case == $resolved ) ? ' selected="selected"' : '';
			$resolved_form .= "<option value=\"$case\"$selected>${$case}</option>\n";
		}

		$resolved_form .= "</select>\n";
		$resolved_form .= '<input type="submit" name="submit" id="resolvedformsub" value="'. __('Change') .'" />' . "\n</div>";
		echo $resolved_form;
		bb_nonce_field( 'resolve-topic_' . $topic->topic_id );
		echo "\n</form>";
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
	$page = get_page_number( $topic->topic_posts );
	echo apply_filters( 'get_post_link', get_topic_link( $topic->topic_id, $page ) . "#post-$topic->topic_last_post_id" );
}

function topic_pages() {
	global $topic, $page;
	$add = topic_pages_add();
	echo apply_filters( 'topic_pages', get_page_number_links( $page, $topic->topic_posts + $add ) );
}

function topic_pages_add() {
	global $topic;
	if ( isset($_GET['view']) && 'all' == $_GET['view'] && bb_current_user_can('browse_deleted') ) :
		$add += $topic->deleted_posts;
	endif;
	return apply_filters( 'topic_pages_add', $add );
}

function get_page_number_links($page, $total) {
	$r = '';
	$args = array();
	$uri = $_SERVER['REQUEST_URI'];
	if ( 1 == $page && bb_get_option('mod_rewrite') ) :
		if ( false === $pos = strpos($uri, '?') )
			$uri = $uri . '/page/1';
		else	$uri = substr_replace($uri, '/page/1', $pos, 0);
	endif;
	if ( isset($_GET['view']) && in_array($_GET['view'], get_views()) )
		$args['view'] = $_GET['view'];
	if ( 1 < $page ) {
		if ( !bb_get_option('mod_rewrite') )
			$args['page'] = ( 1 == $page - 1 ) ? '' : $page - 1;
		$r .=  '<a class="prev" href="' . wp_specialchars( add_query_arg(
								$args,
								str_replace("/page/$page", ( 2 == $page ? '' : '/page/' . ($page - 1) ), $uri)
								) ) . '">&laquo; '. __('Previous Page') .'</a>' . "\n";
	}
	if ( ( $total_pages = ceil( $total / bb_get_option('page_topics') ) ) > 1 ) {
		for ( $page_num = 1; $page_num <= $total_pages; $page_num++ ) :
			if ( $page == $page_num ) :
				$r .=  "<span>$page_num</span>\n";
			else :
				$p = false;
				if ( $page_num < 3 || ( $page_num >= $page - 3 && $page_num <= $page + 3 ) || $page_num > $total_pages - 3 ) :
					if ( !bb_get_option('mod_rewrite') )
						$args['page'] = ( 1 == $page_num ) ? '' : $page_num;
					$r .= '<a class="page-numbers" href="' . wp_specialchars( add_query_arg(
								$args,
								str_replace("/page/$page", ( 1 == $page_num ? '' : '/page/' . $page_num ), $uri)
								) ) . '">' . ( $page_num ) . "</a>\n";
					$in = true;
				elseif ( $in == true ) :
					$r .= "...\n";
					$in = false;
				endif;
			endif;
		endfor;
	}
	if ( ( $page ) * bb_get_option('page_topics') < $total || -1 == $total ) {
		if ( !bb_get_option('mod_rewrite') )
			$args['page'] = $page + 1;
		$r .=  '<a class="next" href="' . wp_specialchars( add_query_arg(
								$args,
								str_replace("/page/$page", '/page/' . ($page + 1), $uri)
								) ) . '">'. __('Next Page') .' &raquo;</a>' . "\n";
	}
	return $r;
}

function topic_delete_link() {
	global $bb_current_user, $topic;
	if ( !bb_current_user_can('manage_topics') )
		return;

	if ( 0 == $topic->topic_status )
		echo "<a href='" . bb_nonce_url( bb_get_option('uri') . 'bb-admin/delete-topic.php?id=' . get_topic_id(), 'delete-topic_' . $topic->topic_id ) . "' onclick=\"return confirm('". __('Are you sure you wanna delete that?') ."')\">Delete entire topic</a>";
	else
		echo "<a href='" . bb_nonce_url( bb_get_option('uri') . 'bb-admin/delete-topic.php?id=' . get_topic_id() . '&view=all', 'delete-topic_' . $topic->topic_id ) . "' onclick=\"return confirm('". __('Are you sure you wanna undelete that?') ."')\">Undelete entire topic</a>";
}

function topic_close_link() {
	global $bb_current_user, $topic;
	if ( !bb_current_user_can('manage_topics') )
		return;

	if ( topic_is_open( get_topic_id() ) )
		$text = __('Close topic');
	else
		$text = __('Open topic');
	echo "<a href='" . bb_nonce_url( bb_get_option('uri') . 'bb-admin/topic-toggle.php?id=' . get_topic_id(), 'close-topic_' . $topic->topic_id ) . "'>$text</a>";
}

function topic_sticky_link() {
	global $bb_current_user, $topic;
	if ( !bb_current_user_can('manage_topics') )
		return;

	if ( topic_is_sticky( get_topic_id() ) )
		echo "<a href='" . bb_nonce_url( bb_get_option('uri') . 'bb-admin/sticky.php?id=' . get_topic_id(), 'stick-topic_' . $topic->topic_id ) . "'>". __('Unstick topic') ."</a>";
	else
		echo "<a href='" . bb_nonce_url( bb_get_option('uri') . 'bb-admin/sticky.php?id=' . get_topic_id(), 'stick-topic_' . $topic->topic_id ) . "'>". __('Stick topic') . "</a> (<a href='" . bb_nonce_url( bb_get_option('uri') . 'bb-admin/sticky.php?id=' . get_topic_id() . '&super=1', 'stick-topic_' . $topic->topic_id ) . "'>" . __('to front') . "</a>)";
}

function topic_show_all_link() {
	global $bb_current_user;
	if ( !bb_current_user_can('browse_deleted') )
		return;
	if ( 'all' == @$_GET['view'] )
		echo "<a href='" . get_topic_link() . "'>". __('View normal posts') ."</a>";
	else
		echo "<a href='" . wp_specialchars( add_query_arg( 'view', 'all', get_topic_link() ) ) . "'>". __('View all posts') ."</a>";
}

function topic_posts_link() {
	global $bb_current_user, $topic;
	$posts = __(sprintf('%d posts', get_topic_posts()));
	if ( 'all' == @$_GET['view'] && bb_current_user_can('browse_deleted') )
		echo "<a href='" . get_topic_link() . "'>$posts</a>";
	else
		echo $posts;

	if ( bb_current_user_can('browse_deleted') ) {
		if ( isset($topic->bozos[$bb_current_user->ID]) && 'all' != @$_GET['view'] )
			add_filter('get_topic_deleted_posts', create_function('$a', "\$a -= {$topic->bozos[$bb_current_user->ID]}; return \$a;") );
		if ( $deleted = get_topic_deleted_posts() ) {
			$extra = __(sprintf('+%d more', $deleted));
			if ( 'all' == @$_GET['view'] )
				echo " $extra";
			else
				echo " <a href='" . wp_specialchars( add_query_arg( 'view', 'all', get_topic_link() ) ) . "'>$extra</a>";
		}
	}
}

function topic_move_dropdown() {
	global $bb_current_user, $forum_id, $topic;
	if ( !bb_current_user_can('manage_topics') )
		return;
	$forum_id = $topic->forum_id;

	echo '<form id="topic-move" method="post" action="' . bb_get_option('uri') . 'bb-admin/topic-move.php"><div>' . "\n\t";
	echo '<input type="hidden" name="topic_id" value="' . get_topic_id() . '" />' . "\n\t";
	echo '<label for="forum_id">'. __('Move this topic to the selected forum:');
	forum_dropdown();
	echo "</label>\n\t";
	bb_nonce_field( 'move-topic_' . $topic->topic_id );
	echo "<input type='submit' name='Submit' value='". __('Move') ."' />\n</div></form>";
}

function topic_class() {
	global $topic;
	$class = array();
	if ( '1' === $topic->topic_status && bb_current_user_can( 'browse_deleted' ) )
		$class[] = 'deleted';
	elseif ( 1 < $topic->topic_status && bb_current_user_can( 'browse_deleted' ) )
		$class[] = 'bozo';
	if ( 'yes' == $topic->topic_resolved )
		$class[] = 'resolved';
	if ( '0' === $topic->topic_open )
		$class[] = 'closed';
	if ( 1 == $topic->topic_sticky && is_forum() )
		$class[] = 'sticky';
	elseif ( 2 == $topic->topic_sticky && is_front() )
		$class[] = 'sticky super-sticky';
	$class = apply_filters( 'topic_class', $class );
	$class = join(' ', $class);
	alt_class( 'topic', $class );
}

// POSTS

function post_id() {
	global $bb_post;
	echo $bb_post->post_id;
}

function get_post_id() {
	global $bb_post;
	return $bb_post->post_id;
}

function post_anchor_link( $force_full = false ) {
	if ( defined('DOING_AJAX') || $force_full )
		post_link();
	else
		echo '#post-' . get_post_id();
}


function post_author() {
	echo apply_filters('post_author', get_post_author() );
}

function get_post_author() {
	global $bbdb;
	$id = get_post_author_id();
	if ( $id )
		if ( $user = bb_get_user( $id ) )
			return $user->user_login;
	else
		return __('Anonymous');
}

function post_author_link() {
	if ( get_user_link( get_post_author_id() ) ) {
		echo '<a href="' . get_user_link( get_post_author_id() ) . '">' . get_post_author() . '</a>';
	} else {
		post_author();
	}
}

function post_text() {
	echo apply_filters('post_text', get_post_text() );
}

function get_post_text() {
	global $bb_post;
	return $bb_post->post_text;
}

function bb_post_time() {
	echo apply_filters('bb_post_time', bb_get_post_time() );
}

function bb_get_post_time() {
	global $bb_post;
	return apply_filters('bb_get_post_time', $bb_post->post_time);
}

function post_date( $format ) {
	echo gmdate( $format, get_post_timestamp() );
}

function get_post_timestamp() {
	global $bb_post;
	return strtotime( $bb_post->post_time );
}

function get_post_ip() {
	global $bb_post;
	return $bb_post->poster_ip;
}

function post_ip() {
	if ( bb_current_user_can( 'view_by_ip' ) )
		echo apply_filters('post_ip', get_post_ip() );
}

function post_ip_link() {
	if ( !bb_current_user_can( 'view_by_ip' ) )
		return;
	$link = '<a href="' . bb_get_option('uri') . 'bb-admin/view-ip.php?ip=' . get_post_ip() . '">' . get_post_ip() . '</a>';
	echo apply_filters('post_ip_link', $link );
}

function post_edit_link() {
	global $bb_post;

	if ( bb_current_user_can( 'edit_post', $bb_post->post_id ) )
		echo "<a href='" . apply_filters( 'post_edit_uri', bb_get_option('uri') . 'edit.php?id=' . get_post_id() ) . "'>". __('Edit') ."</a>";
}

function post_del_class() {
	global $bb_current_user, $bb_post;
	switch ( $bb_post->post_status ) :
	case 0 : return ''; break;
	case 1 : return 'deleted'; break;
	default: return apply_filters( 'post_del_class', $bb_post->post_status );
	endswitch;
}

function post_delete_link() {
	global $bb_current_user, $bb_post;
	if ( !bb_current_user_can('manage_posts') )
		return;

	if ( 0 == $bb_post->post_status )
		$r = "<a href='" . bb_nonce_url( bb_get_option('uri') . 'bb-admin/delete-post.php?id=' . get_post_id() . '&status=1', 'delete-post_' . get_post_id() ) .  "' onclick='return ajaxPostDelete(" . get_post_id() . ", \"" . get_post_author() . "\");'>". __('Delete') ."</a>";
	else
		$r = "<a href='" . bb_nonce_url( bb_get_option('uri') . 'bb-admin/delete-post.php?id=' . get_post_id() . '&status=0&view=all', 'delete-post_' . get_post_id() ) . "' onclick='return confirm(\" ". __('Are you sure you wanna undelete that?') ." \");'>". __('Undelete') ."</a>";
	$r = apply_filters( 'post_delete_link', $r, $bb_post->post_status );
	echo $r;
}

function post_author_id() {
	echo apply_filters('post_author_id', get_post_author_id() );
}

function get_post_author_id() {
	global $bb_post;
	return $bb_post->poster_id;
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
function user_profile_link( $id, $page = 1 ) {
	echo apply_filters('user_profile_link', get_user_profile_link( $id ));
}

function get_user_profile_link( $id, $page = 1 ) {
	if ( bb_get_option('mod_rewrite') ) {
		$r = bb_get_option('uri') . "profile/$id" . ( 1 < $page ? "/page/$page" : '' );
	} else {
		$r = bb_get_option('uri') . "profile.php?id=$id" . ( 1 < $page ? "&page=$page" : '' );
	}
	return apply_filters('get_user_profile_link', $r);
}

function profile_tab_link( $id, $tab, $page = 1 ) {
	echo apply_filters('profile_tab_link', get_profile_tab_link( $id, $tab ));
}

function get_profile_tab_link( $id, $tab, $page = 1 ) {
	$tab = tag_sanitize($tab);
	if ( bb_get_option('mod_rewrite') )
		$r = get_user_profile_link( $id ) . "/$tab" . ( 1 < $page ? "/page/$page" : '' );
	else {
		$args = array('tab' => $tab);
		if ( 1 < $page )
			$args['page'] = $page;
		$r = add_query_arg( $args, get_user_profile_link( $id ) );
	}
	return apply_filters('get_profile_tab_link', $r);
}

function get_user_link( $user_id ) {
	global $bbdb;
	if ( $user_id )
		if ( $user = bb_get_user( $user_id ) )
			return apply_filters('get_user_link', $user->user_url, $user_id);
}

function user_link( $id ) {
	echo apply_filters('user_link', get_user_link($id), $user_id );
}

function get_full_user_link( $id ) {
	if ( get_user_link( $id ) )
		$r = '<a href="' . get_user_link( $id ) . '">' . get_user_name( $id ) . '</a>';
	else
		$r = get_user_name( $id );
	return $r;
}

function full_user_link( $id ) {
	echo get_full_user_link( $id );
}

function get_user_type_label( $type ) {
	global $bb_roles;
	if ( $bb_roles->is_role( $type ) )
		return $bb_roles->role_names[$type];
}

function user_type_label( $type ) {
	echo apply_filters( 'user_type_label', get_user_type_label( $type ) );
}

function get_user_type ( $id ) {
	global $bbdb, $bb_current_user;
	$user = bb_get_user( $id );

	if ( $id && false !== $user ) :
		if ( !empty( $user->title ) )
			return $user->title;
		@$caps = array_keys($user->capabilities);
		if ( !$caps )
			$caps[] = 'member';

		return get_user_type_label( $caps[0] ); //Just support one role for now.
	else :
		return __('Unregistered');
	endif;
}

function user_type( $id ) {
	echo apply_filters('user_type', get_user_type($id) );
}

function get_user_name( $id ) {
	$user = bb_get_user( $id );
	return $user->user_login;
}

function profile_pages() {
	global $user, $page;
	$add = 0;
	$add = apply_filters( 'profile_pages_add', $add );
	echo apply_filters( 'topic_pages', get_page_number_links( $page, $user->topics_replied + $add ) );
}

//TAGS
function topic_tags() {
	global $tags, $tag, $topic_tag_cache, $user_tags, $other_tags, $bb_current_user, $topic;
	if ( is_array( $tags ) || bb_current_user_can( 'edit_tag_by_on', $bb_current_user->ID, $topic->topic_id ) )
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

function tag_link( $id = 0, $page = 1 ) {
	echo get_tag_link( $id );
}

function get_tag_link( $tag_name = 0, $page = 1 ) {
	global $tag, $bb;
	if ( $tag_name )
		$tag = get_tag_by_name( $tag_name );
	if ( bb_get_option('mod_rewrite') )
		return bb_get_option('domain') . $bb->tagpath . "tags/$tag->tag" . ( 1 < $page ? "/page/$page" : '' );
	else
		return bb_get_option('domain') . $bb->tagpath . "tags.php?tag=$tag->tag" . ( 1 < $page ? "&page=$page" : '' );
}

function tag_link_base() {
	global $bb;
	if ( bb_get_option('mod_rewrite') )
		echo bb_get_option('domain') . $bb->tagpath . 'tags/';
	else	echo bb_get_option('domain') . $bb->tagpath . 'tags.php?tag=';
}

function get_tag_name( $id = 0 ) {
	global $tag;
	return $tag->raw_tag;
}

function tag_name( $id = 0 ) {
	echo wp_specialchars( get_tag_name( $id ) );
}

function tag_rss_link( $id = 0 ) {
	echo apply_filters('tag_rss_link', get_tag_rss_link($id) );
}

function get_tag_rss_link( $tag_id = 0 ) {
	global $tag;
	if ( $tag_id )
		$tag = get_tag( $tag_id );

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . "rss/tags/$tag->tag";
	else
		$link = bb_get_option('uri') . "rss.php?tag=$tag->tag";

	return apply_filters('get_tag_rss_link', $link);
}

function tag_form() {
	global $topic, $bb_current_user;
	if ( !bb_current_user_can( 'edit_tag_by_on', $bb_current_user->ID, $topic->topic_id ) )
		return false;
	echo "<form method='post' action='" . bb_get_option('uri') . "tag-add.php'>\n";
	include( BBPATH . '/bb-templates/tag-form.php');
	bb_nonce_field( 'add-tag_' . $topic->topic_id );
	echo "</form>";
}

function manage_tags_forms() {
	global $tag, $bb_current_user;
	if ( !bb_current_user_can('manage_tags') )
		return false;
	$form  = "<ul id='manage-tags'>\n ";
	$form .= "<li id='tag-rename'>". __('Rename tag:') ."\n\t";
	$form .= "<form method='post' action='" . bb_get_option('uri') . "bb-admin/tag-rename.php'><div>\n\t";
	$form .= "<input type='text' name='tag' size='10' maxlength='30' />\n\t";
	$form .= "<input type='hidden' name='id' value='$tag->tag_id' />\n\t";
	$form .= "<input type='submit' name='Submit' value='". __('Rename') ."' />\n\t";
	echo $form;
	bb_nonce_field( 'rename-tag_' . $tag->tag_id );
	echo "\n\t</div></form>\n  </li>\n ";
	$form  = "<li id='tag-merge'>". __('Merge this tag into:') ."\n\t";
	$form .= "<form method='post' action='" . bb_get_option('uri') . "bb-admin/tag-merge.php'><div>\n\t";
	$form .= "<input type='text' name='tag' size='10' maxlength='30' />\n\t";
	$form .= "<input type='hidden' name='id' value='$tag->tag_id' />\n\t";
	$form .= "<input type='submit' name='Submit' value='". __('Merge') ."'";
	$form .= "onclick='return confirm(\" ". sprintf(__('Are you sure you want to merge the &#039;%s&#039; tag into the tag you specified? This is permanent and cannot be undone.'), wp_specialchars( $tag->raw_tag )) ."\")' />\n\t";
	echo $form;
	bb_nonce_field( 'merge-tag_' . $tag->tag_id );
	echo "\n\t</div></form>\n  </li>\n ";
	$form  = "<li id='tag-destroy'>". __('Destroy tag:') ."\n\t";
	$form .= "<form method='post' action='" . bb_get_option('uri') . "bb-admin/tag-destroy.php'><div>\n\t";
	$form .= "<input type='hidden' name='id' value='$tag->tag_id' />\n\t";
	$form .= "<input type='submit' name='Submit' value='". __('Destroy') ."'";
	$form .= "onclick='return confirm(\" ". sprintf(__('Are you sure you want to destroy the &#039;%s&#039; tag? This is permanent and cannot be undone.'), wp_specialchars( $tag->raw_tag )) ."\")' />\n\t";
	echo $form;
	bb_nonce_field( 'destroy-tag_' . $tag->tag_id );
	echo "\n\t</div></form>\n  </li>\n</ul>";
}

function tag_remove_link( $tag_id = 0, $user_id = 0, $topic_id = 0 ) {
	global $tag, $bb_current_user, $topic;
	if ( !bb_current_user_can( 'edit_tag_by_on', $tag->user_id, $topic->topic_id ) )
		return false;

	echo '[<a href="' . bb_nonce_url( bb_get_option('uri') . 'tag-remove.php?tag=' . $tag->tag_id . '&user=' . $tag->user_id . '&topic=' . $tag->topic_id, 'remove-tag_' . $tag->tag_id . '|' . $tag->topic_id) . '" onclick="return ajaxDelTag(' . $tag->tag_id . ', ' . $tag->user_id . ', \'' . addslashes(htmlspecialchars($tag->raw_tag)) . '\');" title="'. __('Remove this tag') .'">x</a>]';
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
		$tag = str_replace(' ', '&nbsp;', wp_specialchars( $tag ));
		print "<a href='$taglink' title='$count topics' style='font-size: ".
		($smallest + ($count/$fontstep))."$unit;'>$tag</a> \n";
	}
}

function tag_pages() {
	global $page, $tagged_topic_count;
	echo apply_filters( 'topic_pages', get_page_number_links( $page, $tagged_topic_count ) );
}

function forum_dropdown() {
	global $forum_id;
	$forums = get_forums();
	echo '<select name="forum_id" id="forum_id" tabindex="5">';

	foreach ( $forums as $forum ) :
		$selected = ( $forum_id == $forum->forum_id ) ? " selected='selected'" : '';
		echo "<option value='$forum->forum_id'$selected>$forum->forum_name</option>";
	endforeach;
	echo '</select>';
}

//FAVORITES
function favorites_link( $user_id = 0 ) {
	echo apply_filters('favorites_link', get_favorites_link( $user_id ));
}

function get_favorites_link( $user_id = 0 ) {
	global $bb_current_user;
	if ( !$user_id )
		$user_id = $bb_current_user->ID;
	return apply_filters('get_favorites_link', get_profile_tab_link($user_id, 'favorites'));
}

function user_favorites_link($add = array('mid' => 'Add this topic to your favorites', 'post' => ' (%?%)'), $rem = array( 'pre' => 'This topic is one of your %favorites% [', 'mid' => 'x', 'post' => ']'), $user_id = 0) {
	global $topic, $bb_current_user;
	if ( $user_id ) :
		if ( !bb_current_user_can( 'edit_favorites_of', (int) $user_id ) )
			return false;
		if ( !$user = bb_get_user( $user_id ) ) :
			return false;
		endif;
	else :
		if ( !bb_current_user_can('edit_favorites') )
			return false;
		$user =& $bb_current_user->data;
	endif;

	if ( 1 == $is_fav = is_user_favorite( $user->ID, $topic->topic_id ) ) :
		$rem = preg_replace('|%(.+)%|', "<a href='" . get_favorites_link( $user_id ) . "'>$1</a>", $rem);
		$favs = array('fav' => '0', 'topic_id' => $topic->topic_id);
		$pre  = ( is_array($rem) && isset($rem['pre'])  ) ? $rem['pre']  : '';
		$mid  = ( is_array($rem) && isset($rem['mid'])  ) ? $rem['mid']  : ( is_string($rem) ? $rem : '' );
		$post = ( is_array($rem) && isset($rem['post']) ) ? $rem['post'] : '';
	elseif ( 0 === $is_fav ) :
		$add = preg_replace('|%(.+)%|', "<a href='" . get_favorites_link( $user_id ) . "'>$1</a>", $add);
		$favs = array('fav' => '1', 'topic_id' => $topic->topic_id);
		$pre  = ( is_array($add) && isset($add['pre'])  ) ? $add['pre']  : '';
		$mid  = ( is_array($add) && isset($add['mid'])  ) ? $add['mid']  : ( is_string($add) ? $add : '' );
		$post = ( is_array($add) && isset($add['post']) ) ? $add['post'] : '';
	endif;
	if ( false !== $is_fav )
		echo "$pre<a href='" . bb_nonce_url( add_query_arg( $favs, get_favorites_link( $user_id ) ), 'toggle-favorite_' . $topic->topic_id ) . "'>$mid</a>$post";
}

function favorites_rss_link( $id = 0 ) {
	echo apply_filters('favorites_rss_link', get_favorites_rss_link( $id ));
}

function get_favorites_rss_link( $id = 0 ) {
	global $user;
	if ( $id )
		$user = bb_get_user( $id );

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . "rss/profile/$user->ID";
	else
		$link = bb_get_option('uri') . "rss.php?profile=$user->ID";

	return apply_filters('get_favorites_rss_link', $link);
}

//VIEWS
function view_name() {
	global $view;
	$views = get_views();
	echo $views[$view];
}

function view_pages() {
	global $page;
	echo apply_filters( 'view_pages', get_page_number_links( $page, -1 ) );
}

function get_view_link( $view, $page = 1 ) {
	$views = get_views();
	if ( !array_key_exists($view, $views) )
		return bb_get_option('uri');
	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_option('uri') . 'view/' . $view . ( 1 < $page ? "/page/$page" : '' );
	else
		$link = bb_get_option('uri') . "view.php?view=$view" . ( 1 < $page ? "&page=$page" : '');

	return apply_filters('get_view_link', $link);
}
?>
