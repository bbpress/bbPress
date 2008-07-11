<?php

function bb_load_template( $file, $globals = false ) {
	global $bb, $bbdb, $bb_current_user, $page, $bb_cache,
		$posts, $bb_post, $post_id, $topics, $topic, $topic_id,
		$forums, $forum, $forum_id, $tags, $tag, $tag_name, $user, $user_id, $view,
		$del_class, $bb_alt;

	if ( $globals )
		foreach ( $globals as $global => $v )
			if ( !is_numeric($global) )
				$$global = $v;
			else
				global $$v;

	$template = apply_filters( 'bb_template', bb_get_template( $file ), $file );
	include($template);
}

function bb_get_template( $file ) {
	if ( file_exists( bb_get_active_theme_directory() .  $file) )
		return bb_get_active_theme_directory() .  $file;
	return BB_DEFAULT_THEME_DIR . $file;
}

function bb_get_header() {
	bb_load_template( 'header.php' );
}

function bb_language_attributes( $xhtml = 0 ) {
	$output = '';
	if ( $dir = bb_get_option('text_direction') )
		$output = "dir=\"$dir\" ";
	if ( $lang = bb_get_option('language') ) {
		$output .= "xml:lang=\"$lang\" ";
		if ( $xhtml < '1.1' )
			$output .= "lang=\"$lang\"";
	}

	echo ' ' . rtrim($output);
}

function bb_stylesheet_uri( $stylesheet = '' ) {
	echo wp_specialchars( bb_get_stylesheet_uri( $stylesheet ) );
}

function bb_get_stylesheet_uri( $stylesheet = '' ) {
	if ( 'rtl' == $stylesheet )
		$css_file = 'style-rtl.css';
	else
		$css_file = 'style.css';

	$active_theme = bb_get_active_theme_directory();

	if ( file_exists( $active_theme . 'style.css' ) )
		$r = bb_get_active_theme_uri() . $css_file;
	else
		$r = BB_DEFAULT_THEME_URL . $css_file;
	return apply_filters( 'bb_get_stylesheet_uri', $r, $stylesheet );
}

function bb_active_theme_uri() {
	echo bb_get_active_theme_uri();
}

function bb_get_active_theme_uri() {
	if ( !$active_theme = bb_get_option( 'bb_active_theme' ) )
		$active_theme_uri = BB_DEFAULT_THEME_URL;
	else
		$active_theme_uri = bb_get_theme_uri( $active_theme );
	return apply_filters( 'bb_get_active_theme_uri', $active_theme_uri );
}

function bb_get_theme_uri( $theme = false ) {
	if ( !$theme ) {
		$theme_uri = BB_THEME_URL;
	} else {
		$theme_uri = str_replace(
			array('core#', 'user#'),
			array(BB_CORE_THEME_URL, BB_THEME_URL),
			$theme
		) . '/';
	}
	return apply_filters( 'bb_get_theme_uri', $theme_uri, $theme );
}

function bb_get_footer() {
	bb_load_template( 'footer.php' );
}

function bb_head() {
        do_action('bb_head');
}

function profile_menu() {
	global $user_id, $profile_menu, $self, $profile_page_title;
	$list  = "<ul id='profile-menu'>";
	$list .= "\n\t<li" . ( ( $self ) ? '' : ' class="current"' ) . '><a href="' . attribute_escape( get_user_profile_link( $user_id ) ) . '">' . __('Profile') . '</a></li>';
	$id = bb_get_current_user_info( 'id' );
	foreach ($profile_menu as $item) {
		// 0 = name, 1 = users cap, 2 = others cap, 3 = file
		$class = '';
		if ( $item[3] == $self ) {
			$class = ' class="current"';
			$profile_page_title = $item[0];
		}
		if ( can_access_tab( $item, $id, $user_id ) )
			if ( file_exists($item[3]) || is_callable($item[3]) )
				$list .= "\n\t<li$class><a href='" . attribute_escape( get_profile_tab_link($user_id, $item[4]) ) . "'>{$item[0]}</a></li>";
	}
	$list .= "\n</ul>";
	echo $list;
}

function login_form() {
	if ( bb_is_user_logged_in() )
		bb_load_template( 'logged-in.php' );
	else
		bb_load_template( 'login-form.php', array('user_login', 'remember_checked', 'redirect_to', 're') );
}

function search_form( $q = '' ) {
	bb_load_template( 'search-form.php', array('q' => $q) );
}

function bb_post_template() {
	bb_load_template( 'post.php' );
}

function post_form( $h2 = '' ) {
	global $page, $topic, $forum;
	
	if ($forum->forum_is_category)
		return;
	
	$add = topic_pages_add();
	if ( empty($h2) && false !== $h2 ) {
		if ( is_topic() )
			$h2 =  __('Reply');
		elseif ( is_forum() )
			$h2 = __('New Topic in this Forum');
		elseif ( is_bb_tag() || is_front() )
			$h2 = __('Add New Topic');
	}

	$last_page = get_page_number( $topic->topic_posts + $add );

	if ( !empty($h2) ) {
		if ( is_topic() && $page != $last_page )
			$h2 = $h2 . ' <a href="' . attribute_escape( get_topic_link( 0, $last_page ) . '#postform' ) . '">&raquo;</a>';
		echo '<h2 class="post-form">' . $h2 . '</h2>' . "\n";
	}

	do_action('pre_post_form');

	if ( ( is_topic() && bb_current_user_can( 'write_post', $topic->topic_id ) && $page == $last_page ) || ( !is_topic() && bb_current_user_can( 'write_topic', $forum->forum_id ) ) ) {
		echo '<form class="postform post-form" id="postform" method="post" action="' . bb_get_uri('bb-post.php', null, BB_URI_CONTEXT_FORM_ACTION) . '">' . "\n";
		echo '<fieldset>' . "\n";
		bb_load_template( 'post-form.php', array('h2' => $h2) );
		bb_nonce_field( is_topic() ? 'create-post_' . $topic->topic_id : 'create-topic' );
		if ( is_forum() )
			echo '<input type="hidden" name="forum_id" value="' . $forum->forum_id . '" />' . "\n";
		else if ( is_topic() )
			echo '<input type="hidden" name="topic_id" value="' . $topic->topic_id . '" />' . "\n";
		do_action('post_form');
		echo "\n</fieldset>\n</form>\n";
	} elseif ( !bb_is_user_logged_in() ) {
		echo '<p>';
		printf(
			__('You must <a href="%s">log in</a> to post.'),
			attribute_escape( bb_get_uri('bb-login.php', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_USER_FORMS) )
		);
		echo '</p>';
	}
	do_action('post_post_form');
}

function edit_form() {
	global $bb_post;
	do_action('pre_edit_form');
	echo '<form class="postform edit-form" method="post" action="' . bb_get_uri('bb-edit.php', null, BB_URI_CONTEXT_FORM_ACTION)  . '">' . "\n";
	echo '<fieldset>' . "\n";
	bb_load_template( 'edit-form.php', array('topic_title') );
	bb_nonce_field( 'edit-post_' . $bb_post->post_id );
	do_action('edit_form');
	echo "\n" . '</fieldset>' . "\n" . '</form>' . "\n";
	do_action('post_edit_form');
}

function alt_class( $key, $others = '' ) {
	echo get_alt_class( $key, $others );
}

function get_alt_class( $key, $others = '' ) {
	global $bb_alt;
	$class = '';
	if ( !isset( $bb_alt[$key] ) ) $bb_alt[$key] = -1;
	++$bb_alt[$key];
	$others = trim($others);
	if ( $others xor $bb_alt[$key] % 2 )
		$class = ' class="' . ( ($others) ? $others : 'alt' ) . '"';
	elseif ( $others && $bb_alt[$key] % 2 )
		$class = ' class="' . $others . ' alt"';
	return $class;
}

function bb_location() {
	echo apply_filters( 'bb_location', bb_get_location() );
}

function bb_get_location() { // Not for display.  Do not internationalize.
	static $location;
	
	if ( isset($location) )
		return $location;
	
	$file = '';
	foreach ( array($_SERVER['PHP_SELF'], $_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME']) as $name )
		if ( false !== strpos($name, '.php') )
			$file = $name;

	switch ( bb_find_filename( $file ) ) {
		case 'index.php' :
			$location = 'front-page';
			break;
		case 'forum.php' :
			$location = 'forum-page';
			break;
		case 'tags.php' :
			$location = 'tag-page';
			break;
		case 'edit.php' :
		case 'topic.php' :
			$location = 'topic-page';
			break;
		case 'rss.php' :
			$location = 'feed-page';
			break;
		case 'search.php' :
			$location = 'search-page';
			break;
		case 'profile.php' :
			$location = 'profile-page';
			break;
		case 'favorites.php' :
			$location = 'favorites-page';
			break;
		case 'view.php' :
			$location = 'view-page';
			break;
		case 'statistics.php' :
			$location = 'stats-page';
			break;
		case 'bb-login.php' :
			$location = 'login-page';
			break;
		case 'register.php' :
			$location = 'register-page';
			break;
		default:
			$location = apply_filters( 'bb_get_location', '', $file );
			break;
	}
	
	return $location;
}

function is_front() {
	return 'front-page' == bb_get_location();
}

function is_forum() {
	return 'forum-page' == bb_get_location();
}

function is_bb_tags() {
	return 'tag-page' == bb_get_location();
}

function is_bb_tag() {
	global $tag, $tag_name;
	return $tag && $tag_name && is_bb_tags();
}

function is_topic() {
	return 'topic-page' == bb_get_location();
}

function is_bb_feed() {
	return 'feed-page' == bb_get_location();
}

function is_bb_search() {
	return 'search-page' == bb_get_location();
}

function is_bb_profile() {
	return 'profile-page' == bb_get_location();
}

function is_bb_favorites() {
	return 'favorites-page' == bb_get_location();
}

function is_view() {
	return 'view-page' == bb_get_location();
}

function is_bb_stats() {
	return 'stats-page' == bb_get_location();
}

function is_bb_admin() {
	if ( defined('BB_IS_ADMIN') )
		return BB_IS_ADMIN;
	return false;
}

function bb_title( $args = '' ) {
	echo apply_filters( 'bb_title', bb_get_title( $args ) );
}

function bb_get_title( $args = '' ) {
	$defaults = array(
		'separator' => ' &laquo; ',
		'order' => 'normal',
		'front' => ''
	);
	$args = wp_parse_args( $args, $defaults );
	$title = array();
	
	switch ( bb_get_location() ) {
		case 'front-page':
			if ( !empty( $args['front'] ) )
				$title[] = $args['front'];
			break;
		
		case 'topic-page':
			$title[] = get_topic_title();
			break;
		
		case 'forum-page':
			$title[] = get_forum_name();
			break;
		
		case 'tag-page':
			if ( is_bb_tag() )
				$title[] = wp_specialchars( bb_get_tag_name() );
			
			$title[] = __('Tags');
			break;
		
		case 'profile-page':
			$title[] = get_user_name();
			break;
		
		case 'view-page':
			$title[] = get_view_name();
			break;
	}
	
	if ( $st = bb_get_option( 'static_title' ) )
		$title = array( $st );
	
	$title[] = bb_get_option( 'name' );
	
	if ( 'reversed' == $args['order'] )
		$title = array_reverse( $title );
	
	return apply_filters( 'bb_get_title', implode( $args['separator'], $title ) );
}

function bb_feed_head() {
	
	$feeds = array();
	
	switch (bb_get_location()) {
		case 'profile-page':
			if ( $tab = isset($_GET['tab']) ? $_GET['tab'] : get_path(2) )
				if ($tab != 'favorites')
					break;
			
			$feeds[] = array(
				'title' => sprintf(__('User Favorites: %s'), get_user_name()),
				'href'  => get_favorites_rss_link(0, BB_URI_CONTEXT_LINK_ALTERNATE_HREF + BB_URI_CONTEXT_BB_FEED)
			);
			break;
		
		case 'topic-page':
			$feeds[] = array(
				'title' => sprintf(__('Topic: %s'), get_topic_title()),
				'href'  => get_topic_rss_link(0, BB_URI_CONTEXT_LINK_ALTERNATE_HREF + BB_URI_CONTEXT_BB_FEED)
			);
			break;
		
		case 'tag-page':
			if (is_bb_tag()) {
				$feeds[] = array(
					'title' => sprintf(__('Tag: %s'), bb_get_tag_name()),
					'href'  => bb_get_tag_rss_link(0, BB_URI_CONTEXT_LINK_ALTERNATE_HREF + BB_URI_CONTEXT_BB_FEED)
				);
			}
			break;
		
		case 'forum-page':
			$feeds[] = array(
				'title' => sprintf(__('Forum: %s - Recent Posts'), get_forum_name()),
				'href'  => bb_get_forum_posts_rss_link(0, BB_URI_CONTEXT_LINK_ALTERNATE_HREF + BB_URI_CONTEXT_BB_FEED)
			);
			$feeds[] = array(
				'title' => sprintf(__('Forum: %s - Recent Topics'), get_forum_name()),
				'href'  => bb_get_forum_topics_rss_link(0, BB_URI_CONTEXT_LINK_ALTERNATE_HREF + BB_URI_CONTEXT_BB_FEED)
			);
			break;
		
		case 'front-page':
			$feeds[] = array(
				'title' => __('Recent Posts'),
				'href'  => bb_get_posts_rss_link(BB_URI_CONTEXT_LINK_ALTERNATE_HREF + BB_URI_CONTEXT_BB_FEED)
			);
			$feeds[] = array(
				'title' => __('Recent Topics'),
				'href'  => bb_get_topics_rss_link(BB_URI_CONTEXT_LINK_ALTERNATE_HREF + BB_URI_CONTEXT_BB_FEED)
			);
			break;
		
		case 'view-page':
			global $bb_views, $view;
			if ($bb_views[$view]['feed']) {
				$feeds[] = array(
					'title' => get_view_name(),
					'href'  => bb_get_view_rss_link(BB_URI_CONTEXT_LINK_ALTERNATE_HREF + BB_URI_CONTEXT_BB_FEED)
				);
			}
			break;
	}
	
	if (count($feeds)) {
		$feed_links = array();
		foreach ($feeds as $feed) {
			$link = '<link rel="alternate" type="application/rss+xml" ';
			$link .= 'title="' . attribute_escape($feed['title']) . '" ';
			$link .= 'href="' . attribute_escape($feed['href']) . '" />';
			$feed_links[] = $link;
		}
		$feed_links = join("\n", $feed_links);
	} else {
		$feed_links = '';
	}
	
	echo apply_filters('bb_feed_head', $feed_links);
}

function bb_get_posts_rss_link($context = 0) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	if ( bb_get_option( 'mod_rewrite' ) )
		$link = bb_get_uri('rss/', null, $context);
	else
		$link = bb_get_uri('rss.php', null, $context);
	return apply_filters( 'bb_get_posts_rss_link', $link, $context );
}

function bb_get_topics_rss_link($context = 0) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	if ( bb_get_option( 'mod_rewrite' ) )
		$link = bb_get_uri('rss/topics', null, $context);
	else
		$link = bb_get_uri('rss.php', array('topics' => 1), $context);
	return apply_filters( 'bb_get_topics_rss_link', $link, $context );
}

function bb_get_view_rss_link($context = 0) {
	global $view;
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	if ( bb_get_option( 'mod_rewrite' ) )
		$link = bb_get_uri('rss/view/' . $view, null, $context);
	else
		$link = bb_get_uri('rss.php', array('view' => $view), $context);
	return apply_filters( 'bb_get_view_rss_link', $link, $context );
}

// FORUMS

function forum_id( $forum_id = 0 ) {
	echo apply_filters( 'forum_id', get_forum_id( $forum_id ) );
}

function get_forum_id( $forum_id = 0 ) {
	global $forum;
	$forum_id = (int) $forum_id;
	if ( $forum_id )
		$_forum = get_forum( $forum_id );
	else
		$_forum =& $forum;
	return $_forum->forum_id;
}

function forum_link( $forum_id = 0, $page = 1, $context = BB_URI_CONTEXT_A_HREF ) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF;
	}
	echo apply_filters('forum_link', get_forum_link( $forum_id, $page, $context ), $forum_id, $context );
}

function get_forum_link( $forum_id = 0, $page = 1, $context = BB_URI_CONTEXT_A_HREF ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF;
	}
	
	$rewrite = bb_get_option( 'mod_rewrite' );
	if ( $rewrite ) {
		if ( $rewrite === 'slugs' ) {
			$column = 'forum_slug';
		} else {
			$column = 'forum_id';
		}
		$page = $page > 1 ? $page : '';
		$link = bb_get_uri('forum/' . $forum->$column . $page, null, $context);
	} else {
		$query = array(
			'id' => $forum->forum_id,
			'page' => $page > 1 ? $page : false
		);
		$link = bb_get_uri('forum.php', $query, $context);
	}

	return apply_filters( 'get_forum_link', $link, $forum->forum_id, $context );
}

function forum_name( $forum_id = 0 ) {
	echo apply_filters( 'forum_name', get_forum_name( $forum_id ), $forum_id );
}

function get_forum_name( $forum_id = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	return apply_filters( 'get_forum_name', $forum->forum_name, $forum->forum_id );
}

function forum_description( $args = null ) {
	if ( is_numeric($args) )
		$args = array( 'id' => $args );
	elseif ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'before' => $args );
	$defaults = array( 'id' => 0, 'before' => ' &#8211; ', 'after' => '' );
	$args = wp_parse_args( $args, $defaults );

	if ( $desc = apply_filters( 'forum_description', get_forum_description( $args['id'] ), $args['id'], $args ) )
		echo $args['before'] . $desc . $args['after'];
}

function get_forum_description( $forum_id = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	return apply_filters( 'get_forum_description', $forum->forum_desc, $forum->forum_id );
}

function get_forum_parent( $forum_id = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	return apply_filters( 'get_forum_parent', $forum->forum_parent, $forum->forum_id );
}

function get_forum_position( $forum_id = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	return apply_filters( 'get_forum_position', $forum->forum_order, $forum->forum_id );
}

function bb_get_forum_is_category( $forum_id = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	return apply_filters( 'bb_get_forum_is_category', $forum->forum_is_category, $forum->forum_id );
}

function forum_topics( $forum_id = 0 ) {
	echo apply_filters( 'forum_topics', get_forum_topics( $forum_id ), $forum_id );
}

function get_forum_topics( $forum_id = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	return apply_filters( 'get_forum_topics', $forum->topics, $forum->forum_id );
}

function forum_posts( $forum_id = 0 ) {
	echo apply_filters( 'forum_posts', get_forum_posts( $forum_id ), $forum_id );
}

function get_forum_posts( $forum_id = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	return apply_filters( 'get_forum_posts', $forum->posts, $forum->forum_id );
}

function forum_pages( $forum_id = 0 ) {
	global $page;
	$forum = get_forum( get_forum_id( $forum_id ) );
	echo apply_filters( 'forum_pages', get_page_number_links( $page, $forum->topics ), $forum->forum_topics );
}

function bb_forum_posts_rss_link( $forum_id = 0, $context = 0 ) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	echo apply_filters('bb_forum_posts_rss_link', bb_get_forum_posts_rss_link( $forum_id, $context ), $context );
}

function bb_get_forum_posts_rss_link( $forum_id = 0, $context = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	
	$rewrite = bb_get_option( 'mod_rewrite' );
	if ( $rewrite ) {
		if ( $rewrite === 'slugs' ) {
			$column = 'forum_slug';
		} else {
			$column = 'forum_id';
		}
		$link = bb_get_uri('rss/forum/' . $forum->$column, null, $context);
	} else {
		$link = bb_get_uri('rss.php', array('forum' => $forum->forum_id), $context);
	}
	return apply_filters( 'bb_get_forum_posts_rss_link', $link, $forum->forum_id, $context );
}

function bb_forum_topics_rss_link( $forum_id = 0, $context = 0 ) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	echo apply_filters('bb_forum_topics_rss_link', bb_get_forum_topics_rss_link( $forum_id, $context ), $context );
}

function bb_get_forum_topics_rss_link( $forum_id = 0, $context = 0 ) {
	$forum = get_forum( get_forum_id( $forum_id ) );
	
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	
	$rewrite = bb_get_option( 'mod_rewrite' );
	if ( $rewrite ) {
		if ( $rewrite === 'slugs' ) {
			$column = 'forum_slug';
		} else {
			$column = 'forum_id';
		}
		$link = bb_get_uri('rss/forum/' . $forum->$column . '/topics', null, $context);
	} else {
		$link = bb_get_uri('rss.php', array('forum' => $forum->forum_id, 'topics' => 1), $context);
	}
	return apply_filters( 'bb_get_forum_topics_rss_link', $link, $forum->forum_id, $context );
}

function bb_get_forum_bread_crumb($args = '') {
	$defaults = array(
		'forum_id' => 0,
		'separator' => ' &raquo; ',
		'class' => null
	);
	$args = wp_parse_args($args, $defaults);
	extract($args, EXTR_SKIP);

	$trail = '';
	$trail_forum = get_forum(get_forum_id($forum_id));
	if ($class) {
		$class = ' class="' . $class . '"';
	}
	$current_trail_forum_id = $trail_forum->forum_id;
	while ($trail_forum->forum_id > 0) {
		$crumb = $separator;
		if ($current_trail_forum_id != $trail_forum->forum_id || !is_forum()) {
			$crumb .= '<a' . $class . ' href="' . get_forum_link($trail_forum->forum_id) . '">';
		} elseif ($class) {
			$crumb .= '<span' . $class . '>';
		}
		$crumb .= get_forum_name($trail_forum->forum_id);
		if ($current_trail_forum_id != $trail_forum->forum_id || !is_forum()) {
			$crumb .= '</a>';
		} elseif ($class) {
			$crumb .= '</span>';
		}
		$trail = $crumb . $trail;
		$trail_forum = get_forum($trail_forum->forum_parent);
	}

	return apply_filters('bb_get_forum_bread_crumb', $trail, $forum_id );
}

function bb_forum_bread_crumb( $args = '' ) {
	echo apply_filters('bb_forum_bread_crumb', bb_get_forum_bread_crumb( $args ) );
}

// Forum Loop //

function &bb_forums( $args = '' ) {
	global $bb_forums_loop;

	$default_type = 'flat';

	if ( is_numeric($args) ) {
		$args = array( 'child_of' => $args );
	} elseif ( func_num_args() > 1 ) { // bb_forums( 'ul', $args ); Deprecated
		$default_type = $args;
		$args = func_get_arg(1);
	} elseif ( $args && is_string($args) && false === strpos($args, '=') ) {
		$args = array( 'type' => $args );
	}

	// hierarchical not used here.  Sent to get_forums for proper ordering.
	$args = wp_parse_args( $args, array('hierarchical' => true, 'type' => $default_type, 'walker' => 'BB_Walker_Blank') );

	$levels = array( '', '' );

	if ( in_array($args['type'], array('list', 'ul')) )
		$levels = array( '<ul>', '</ul>' );

	$forums = get_forums( $args );

	if ( !class_exists($args['walker']) )
		$args['walker'] = 'BB_Walker_Blank';

	if ( $bb_forums_loop = BB_Loop::start( $forums, $args['walker'] ) ) {
		$bb_forums_loop->preserve( array('forum', 'forum_id') );
		$bb_forums_loop->walker->db_fields = array( 'id' => 'forum_id', 'parent' => 'forum_parent' );
		list($bb_forums_loop->walker->start_lvl, $bb_forums_loop->walker->end_lvl) = $levels;
		return $bb_forums_loop->elements;
	}
	return false;
}

function bb_forum() { // Returns current depth
	global $bb_forums_loop;
	if ( !is_object($bb_forums_loop) || !is_a($bb_forums_loop, 'BB_Loop') )
		return false;
	if ( !is_array($bb_forums_loop->elements) )
		return false;

	if ( $bb_forums_loop->step() ) {
		$GLOBALS['forum'] =& $bb_forums_loop->elements[key($bb_forums_loop->elements)]; // Globalize the current forum object
	} else {
		$bb_forums_loop->reinstate();
		return $bb_forums_loop = null; // All done?  Kill the object and exit the loop.
	}

	return $bb_forums_loop->walker->depth;
}

function bb_forum_pad( $pad, $offset = 0 ) {
	global $bb_forums_loop;
	if ( !is_object($bb_forums_loop) || !is_a($bb_forums_loop, 'BB_Loop') )
		return false;

	echo $bb_forums_loop->pad( $pad, $offset );
}

function bb_forum_class( $args = null ) {
	if ( is_numeric($args) ) // Not used
		$args = array( 'id' => $args );
	elseif ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'class' => $args );
	$defaults = array( 'id' => 0, 'key' => 'forum', 'class' => '' );
	$args = wp_parse_args( $args, $defaults );

	global $bb_forums_loop;
	if ( is_object($bb_forums_loop) && is_a($bb_forums_loop, 'BB_Loop') )
		$args['class'] .= ' ' . $bb_forums_loop->classes();

	echo apply_filters( 'bb_forum_class', get_alt_class( 'forum', $args['class'] ) );
}

// TOPICS
function topic_id( $id = 0 ) {
	echo apply_filters( 'topic_id', get_topic_id( $id ) );
}

function get_topic_id( $id = 0 ) {
	global $topic;
	$id = (int) $id;
	if ( $id )
		$_topic = get_topic( $id );
	else
		$_topic =& $topic;
	return $_topic->topic_id;
}

function topic_link( $id = 0, $page = 1, $context = BB_URI_CONTEXT_A_HREF ) {
	echo apply_filters( 'topic_link', get_topic_link( $id ), $id, $context );
}

function get_topic_link( $id = 0, $page = 1, $context = BB_URI_CONTEXT_A_HREF ) {
	$topic = get_topic( get_topic_id( $id ) );

	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF;
	}

	$args = array();

	$rewrite = bb_get_option( 'mod_rewrite' );
	if ( $rewrite ) {
		if ( $rewrite === 'slugs' ) {
			$column = 'topic_slug';
		} else {
			$column = 'topic_id';
		}
		$page = $page > 1 ? '/page/' . $page : '';
		$link = bb_get_uri('topic/' . $topic->$column . $page, null, $context);
	} else {
		$page = $page > 1 ? $page : false;
		$link = bb_get_uri('topic.php', array('id' => $topic->topic_id, 'page' => $page), $context);
	}

	return apply_filters( 'get_topic_link', $link, $topic->topic_id, $context );
}

function topic_rss_link( $id = 0, $context = 0 ) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	echo apply_filters('topic_rss_link', get_topic_rss_link($id, $context), $id, $context );
}

function get_topic_rss_link( $id = 0, $context = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );

	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}

	$rewrite = bb_get_option( 'mod_rewrite' );
	if ( $rewrite ) {
		if ( $rewrite === 'slugs' ) {
			$column = 'topic_slug';
		} else {
			$column = 'topic_id';
		}
		$link = bb_get_uri('rss/topic/' . $topic->$column, null, $context);
	} else {
		$link = bb_get_uri('rss.php', array('topic' => $topic->topic_id), $context);
	}
	return apply_filters( 'get_topic_rss_link', $link, $topic->topic_id, $context );
}

function bb_topic_labels() {
	echo apply_filters( 'bb_topic_labels', null );
}

function topic_title( $id = 0 ) {
	echo apply_filters( 'topic_title', get_topic_title( $id ), get_topic_id( $id ) );
}

function get_topic_title( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	return apply_filters( 'get_topic_title', $topic->topic_title, $topic->topic_id );
}

function topic_posts( $id = 0 ) {
	echo apply_filters( 'topic_posts', get_topic_posts( $id ), get_topic_id( $id ) );
}

function get_topic_posts( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	return apply_filters( 'get_topic_posts', $topic->topic_posts, $topic->topic_id );
}

function get_topic_deleted_posts( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	return apply_filters( 'get_topic_deleted_posts', $topic->deleted_posts, $topic->topic_id );
}

function topic_noreply( $title ) {
	if ( 1 == get_topic_posts() && ( is_front() || is_forum() ) )
		$title = "<strong>$title</strong>";
	return $title;
}

function topic_last_poster( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	echo apply_filters( 'topic_last_poster', get_topic_last_poster( $id ), $topic->topic_last_poster ); // Last arg = user ID
}

function get_topic_last_poster( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	return apply_filters( 'get_topic_last_poster', $topic->topic_last_poster_name, $topic->topic_last_poster ); // Last arg = user ID
}

function topic_author( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	echo apply_filters( 'topic_author', get_topic_author( $id ), $topic->topic_poster ); // Last arg = user ID
}

function get_topic_author( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	return apply_filters( 'get_topic_author', $topic->topic_poster_name, $topic->topic_poster ); // Last arg = user ID
}

// Filters expect the format to by mysql on both topic_time and get_topic_time
function topic_time( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );
	$time = apply_filters( 'topic_time', get_topic_time( array('format' => 'mysql') + $args), $args );
	echo _bb_time_function_return( $time, $args );
}

function get_topic_time( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );

	$topic = get_topic( get_topic_id( $args['id'] ) );

	$time = apply_filters( 'get_topic_time', $topic->topic_time, $args );

	return _bb_time_function_return( $time, $args );
}

function topic_start_time( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );
	$time = apply_filters( 'topic_start_time', get_topic_start_time( array('format' => 'mysql') + $args), $args );
	echo _bb_time_function_return( $time, $args );
}

function get_topic_start_time( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );

	$topic = get_topic( get_topic_id( $args['id'] ) );

	$time = apply_filters( 'get_topic_start_time', $topic->topic_start_time, $args );

	return _bb_time_function_return( $time, $args );
}

function topic_last_post_link( $id = 0 ) {
	echo apply_filters( 'topic_last_post_link', get_topic_last_post_link( $id ));
}

function get_topic_last_post_link( $id = 0 ){
	$topic = get_topic( get_topic_id( $id ) );
	$page = get_page_number( $topic->topic_posts );
	return apply_filters( 'get_post_link', get_topic_link( $topic->topic_id, $page ) . "#post-$topic->topic_last_post_id", $topic->topic_last_post_id );
}

function topic_pages( $id = 0 ) {
	global $page;
	$topic = get_topic( get_topic_id( $id ) );
	$add = topic_pages_add( $topic->topic_id );
	echo apply_filters( 'topic_pages', get_page_number_links( $page, $topic->topic_posts + $add ), $topic->topic_id );
}

function topic_pages_add( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	if ( isset($_GET['view']) && 'all' == $_GET['view'] && bb_current_user_can('browse_deleted') )
		$add += $topic->deleted_posts;
	return apply_filters( 'topic_pages_add', $add, $topic->topic_id );
}

function get_page_number_links($page, $total) {
	$args = array();
	$uri = $_SERVER['REQUEST_URI'];
	if ( bb_get_option('mod_rewrite') ) {
		$format = '/page/%#%';
		if ( 1 == $page ) {
			if ( false === $pos = strpos($uri, '?') )
				$uri = $uri . '%_%';
			else
				$uri = substr_replace($uri, '%_%', $pos, 0);
		} else {
			$uri = preg_replace('|/page/[0-9]+|', '%_%', $uri);
		}
		$uri = str_replace( '/%_%', '%_%', $uri );
	} else {
		if ( 1 == $page ) {
			if ( false === $pos = strpos($uri, '?') ) {
				$uri = $uri . '%_%';
				$format = '?page=%#%';
			} else {
				$uri = substr_replace($uri, '?%_%', $pos, 1);
				$format = 'page=%#%&';
			}
		} else {
			if ( false === strpos($uri, '?page=') ) {
				$uri = preg_replace('!&page=[0-9]+!', '%_%', $uri );
				$format = '&page=%#%';
			} else {
				$uri = preg_replace('!?page=[0-9]+!', '%_%', $uri );
				$format = '?page=%#%';
			}
		}
	}

	if ( isset($_GET['view']) && in_array($_GET['view'], bb_get_views()) )
		$args['view'] = $_GET['view'];

	return paginate_links( array(
		'base' => $uri,
		'format' => $format,
		'total' => ceil($total/bb_get_option('page_topics')),
		'current' => $page,
		'add_args' => $args
	) );
}

function bb_topic_admin( $args = '' ) {
	
	$id = 0;
	
	if ($args && is_array($args) && isset($args['id']) && !empty($args['id'])) {
		$id = $args['id'];
	}
	
	$parts = array(
		'delete' => bb_get_topic_delete_link( $args ),
		'close' => bb_get_topic_close_link( $args ),
		'sticky' => bb_get_topic_sticky_link( $args )
	);
	echo join("\n", apply_filters('bb_topic_admin', $parts));
	
	topic_move_dropdown( $id );
}

function topic_delete_link( $args = '' ) {
	echo bb_get_topic_delete_link( $args );
}

function bb_get_topic_delete_link( $args = '' ) {
	$defaults = array( 'id' => 0, 'before' => '[', 'after' => ']' );
	extract(wp_parse_args( $args, $defaults ), EXTR_SKIP);
	$id = (int) $id;

	$topic = get_topic( get_topic_id( $id ) );

	if ( !$topic || !bb_current_user_can( 'delete_topic', $topic->topic_id ) )
		return;

	if ( 0 == $topic->topic_status ) {
		$query   = array('id' => $topic->topic_id);
		$confirm = __('Are you sure you wanna delete that?');
		$display = __('Delete entire topic');
	} else {
		$query   = array('id' => $topic->topic_id, 'view' => 'all');
		$confirm = __('Are you sure you wanna undelete that?');
		$display = __('Undelete entire topic');
	}
	$uri = bb_get_uri('bb-admin/delete-topic.php', $query, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN);
	$uri = attribute_escape( bb_nonce_url( $uri, 'delete-topic_' . $topic->topic_id ) );
	
	return $before . '<a href="' . $uri . '" onclick="return confirm(\'' . js_escape( $confirm ) . '\');">' . $display . '</a>' . $after;
}

function topic_close_link( $args = '' ) {
	echo bb_get_topic_close_link( $args );
}

function bb_get_topic_close_link( $args = '' ) {
	$defaults = array( 'id' => 0, 'before' => '[', 'after' => ']' );
	extract(wp_parse_args( $args, $defaults ), EXTR_SKIP);
	$id = (int) $id;

	$topic = get_topic( get_topic_id( $id ) );

	if ( !$topic || !bb_current_user_can( 'close_topic', $topic->topic_id ) )
		return;

	$display = topic_is_open( $topic->topic_id ) ? __('Close topic') : __('Open topic');
	$uri = bb_get_uri('bb-admin/topic-toggle.php', array('id' => $topic->topic_id), BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN);
	$uri = attribute_escape( bb_nonce_url( $uri, 'close-topic_' . $topic->topic_id ) );
	
	return $before . '<a href="' . $uri . '" onclick="return confirm(\'' . js_escape( $confirm ) . '\');">' . $display . '</a>' . $after;
}

function topic_sticky_link( $args = '' ) {
	echo bb_get_topic_sticky_link( $args );
}

function bb_get_topic_sticky_link( $args = '' ) {
	$defaults = array( 'id' => 0, 'before' => '[', 'after' => ']' );
	extract(wp_parse_args( $args, $defaults ), EXTR_SKIP);
	$id = (int) $id;

	$topic = get_topic( get_topic_id( $id ) );

	if ( !$topic || !bb_current_user_can( 'stick_topic', $topic->topic_id ) )
		return;

	$uri_stick = bb_get_uri('bb-admin/sticky.php', array('id' => $topic->topic_id), BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN);
	$uri_stick = attribute_escape( bb_nonce_url( $uri_stick, 'stick-topic_' . $topic->topic_id ) );

	$uri_super = bb_get_uri('bb-admin/sticky.php', array('id' => $topic->topic_id, 'super' => 1), BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN);
	$uri_super = attribute_escape( bb_nonce_url( $uri_super, 'stick-topic_' . $topic->topic_id ) );

	if ( topic_is_sticky( $topic->topic_id ) )
		return "$before<a href='" . $uri_stick . "'>". __('Unstick topic') ."</a>$after";
	else
		return "$before<a href='" . $uri_stick . "'>". __('Stick topic') . "</a> (<a href='" . $uri_super . "'>" . __('to front') . "</a>)$after";
}

function topic_show_all_link( $id = 0 ) {
	if ( !bb_current_user_can( 'browse_deleted' ) )
		return;
	if ( 'all' == @$_GET['view'] )
		echo "<a href='" . attribute_escape( get_topic_link( $id ) ) . "'>". __('View normal posts') ."</a>";
	else
		echo "<a href='" . attribute_escape( add_query_arg( 'view', 'all', get_topic_link( $id ) ) ) . "'>". __('View all posts') ."</a>";
}

function topic_posts_link( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	$post_num = get_topic_posts( $id );
	$posts = sprintf(__ngettext( '%s post', '%s posts', $post_num ), $post_num);
	if ( 'all' == @$_GET['view'] && bb_current_user_can('browse_deleted') )
		echo "<a href='" . attribute_escape( get_topic_link( $id ) ) . "'>$posts</a>";
	else
		echo $posts;

	if ( bb_current_user_can( 'browse_deleted' ) ) {
		$user_id = bb_get_current_user_info( 'id' );
		if ( isset($topic->bozos[$user_id]) && 'all' != @$_GET['view'] )
			add_filter('get_topic_deleted_posts', create_function('$a', "\$a -= {$topic->bozos[$user_id]}; return \$a;") );
		if ( $deleted = get_topic_deleted_posts( $id ) ) {
			$extra = sprintf(__('+%d more'), $deleted);
			if ( 'all' == @$_GET['view'] )
				echo " $extra";
			else
				echo " <a href='" . attribute_escape( add_query_arg( 'view', 'all', get_topic_link( $id ) ) ) . "'>$extra</a>";
		}
	}
}

function topic_move_dropdown( $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	if ( !bb_current_user_can( 'move_topic', $topic->topic_id ) )
		return;

	$dropdown = bb_get_forum_dropdown( array(
		'callback' => 'bb_current_user_can',
		'callback_args' => array('move_topic', $topic->topic_id),
		'selected' => $topic->forum_id
	) );

	if ( !$dropdown )
		return;

	echo '<form id="topic-move" method="post" action="' . bb_get_uri('bb-admin/topic-move.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN) . '"><fieldset><div>' . "\n\t";
	echo "<input type='hidden' name='topic_id' value='$topic->topic_id' />\n\t";
	echo '<label for="forum-id">'. __('Move this topic to the selected forum:') . ' ';
	echo $dropdown;
	echo "</label>\n\t";
	bb_nonce_field( 'move-topic_' . $topic->topic_id );
	echo "<input type='submit' name='Submit' value='". __('Move') ."' />\n</div></fieldset></form>";
}

function topic_class( $class = '', $key = 'topic', $id = 0 ) {
	$topic = get_topic( get_topic_id( $id ) );
	$class = $class ? explode(' ', $class ) : array();
	if ( '1' === $topic->topic_status && bb_current_user_can( 'browse_deleted' ) )
		$class[] = 'deleted';
	elseif ( 1 < $topic->topic_status && bb_current_user_can( 'browse_deleted' ) )
		$class[] = 'bozo';
	if ( '0' === $topic->topic_open )
		$class[] = 'closed';
	if ( 1 == $topic->topic_sticky && is_forum() )
		$class[] = 'sticky';
	elseif ( 2 == $topic->topic_sticky && ( is_front() || is_forum() ) )
		$class[] = 'sticky super-sticky';
	$class = apply_filters( 'topic_class', $class, $topic->topic_id );
	$class = join(' ', $class);
	alt_class( $key, $class );
}

function new_topic( $args = null ) {
	$defaults = array( 'text' => __('Add New &raquo;'), 'forum' => 0, 'tag' => '' );
	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'text' => $args );

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( $forum && $forum = get_forum( $forum ) )
		$url = get_forum_link( $forum->forum_id ) . '#postform';
	elseif ( $tag && ( ( is_numeric($tag) && $tag = bb_get_tag( $tag ) ) || $tag = bb_get_tag_by_name( $tag ) ) )
		$url = bb_get_tag_link( $tag->tag ) . '#postform';
	elseif ( is_forum() || is_bb_tag() )
		$url = '#postform';
	elseif ( is_topic() )
		$url = get_forum_link() . '#postform';
	elseif ( is_front() )
		$url = bb_get_uri(null, array('new' => 1));

	if ( !bb_is_user_logged_in() )
		$url = bb_get_uri('bb-login.php', array('re' => $url), BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_USER_FORMS);
	elseif ( is_forum() || is_topic() ) {
		if ( !bb_current_user_can( 'write_topic', get_forum_id() ) )
			return;
	} else {
		if ( !bb_current_user_can( 'write_topics' ) )
			return;
	}

	if ( $url = attribute_escape( apply_filters( 'new_topic_url', $url ) ) )
		echo '<a href="' . $url . '" class="new-topic">' . $text . '</a>' . "\n";
}

function bb_new_topic_forum_dropdown() {
	bb_forum_dropdown( 'bb_current_user_can', array('write_topic') );
}

function bb_topic_search_form( $args = null, $query_obj = null ) {
	global $bb_query_form;

	if ( $query_obj && is_a($query_obj, 'BB_Query_Form') ); // [sic]
	else
		$query_obj =& $bb_query_form;

	$query_obj->form( $args );
}

/**
 * bb_topic_pagecount() - Print the total page count for a topic
 *
 * @since 0.9
 * @param int $topic_id The topic id of the topic being queried
 * @return void
 **/
function bb_topic_pagecount( $topic_id = 0 ) {
	echo bb_get_topic_pagecount( $topic_id );
}

/**
 * bb_get_topic_pagecount() - Get the total page count for a topic
 *
 * @since 0.9
 * @param int $topic_id The topic id of the topic being queried
 * @return int The total number of pages in the topic
 **/
function bb_get_topic_pagecount( $topic_id = 0 ) {
	$topic = get_topic( get_topic_id( $topic_id ) );
	return get_page_number( $topic->topic_posts + topic_pages_add() );
}

/**
 * bb_is_topic_lastpage() - Report whether the current page is the last page of a given topic
 *
 * @since 0.9
 * @param int $topic_id The topic id of the topic being queried
 * @return boolean True if called on the last page of a topic, otherwise false
 **/
function bb_is_topic_lastpage( $topic_id = 0 ) {
	global $page;
	return ( $page == bb_get_topic_pagecount( $topic_id ) );
}

// POSTS

function post_id( $post_id = 0 ) {
	echo get_post_id( $post_id );
}

function get_post_id( $post_id = 0 ) {
	global $bb_post;
	$post_id = (int) $post_id;
	if ( $post_id )
		$post = bb_get_post( $post_id );
	else
		$post =& $bb_post;
	return $post->post_id;
}

function post_link( $post_id = 0 ) {
	echo apply_filters( 'post_link', get_post_link( $post_id ), get_post_id( $post_id ) );
}

function get_post_link( $post_id = 0 ) {
	$bb_post = bb_get_post( get_post_id( $post_id ) );
	$page = get_page_number( $bb_post->post_position );
	return apply_filters( 'get_post_link', get_topic_link( $bb_post->topic_id, $page ) . "#post-$bb_post->post_id", $bb_post->post_id );
}

function post_anchor_link( $force_full = false ) {
	if ( defined('DOING_AJAX') || $force_full )
		post_link();
	else
		echo '#post-' . get_post_id();
}


function post_author( $post_id = 0 ) {
	echo apply_filters('post_author', get_post_author( $post_id ) );
}

function get_post_author( $post_id = 0 ) {
	if ( $user = bb_get_user( get_post_author_id( $post_id ) ) )
		return apply_filters( 'get_post_author', $user->user_login, $user->ID );
	else
		return __('Anonymous');
}

function post_author_link( $post_id = 0 ) {
	if ( $link = get_user_link( get_post_author_id( $post_id ) ) ) {
		echo '<a href="' . attribute_escape( $link ) . '">' . get_post_author( $post_id ) . '</a>';
	} else {
		post_author( $post_id );
	}
}

function post_author_avatar( $size = '48', $default = '', $post_id = 0 ) {
	if ( ! bb_get_option('avatars_show') )
		return false;
	
	$author_id = get_post_author_id( $post_id );
	if ( $link = get_user_link( $author_id ) ) {
		echo '<a href="' . attribute_escape( $link ) . '">' . bb_get_avatar( $author_id, $size, $default ) . '</a>';
	} else {
		echo bb_get_avatar( $author_id, $size, $default );
	}
}

function post_text( $post_id = 0 ) {
	echo apply_filters( 'post_text', get_post_text( $post_id ), get_post_id( $post_id ) );
}

function get_post_text( $post_id = 0 ) {
	$bb_post = bb_get_post( get_post_id( $post_id ) );
	return apply_filters( 'get_post_text', $bb_post->post_text, $bb_post->post_id );
}

function bb_post_time( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );
	$time = apply_filters( 'bb_post_time', bb_get_post_time( array('format' => 'mysql') + $args ), $args );
	echo _bb_time_function_return( $time, $args );
}

function bb_get_post_time( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );

	$bb_post = bb_get_post( get_post_id( $args['id'] ) );

	$time = apply_filters( 'bb_get_post_time', $bb_post->post_time, $args );

	return _bb_time_function_return( $time, $args );
}

function post_ip( $post_id = 0 ) {
	if ( bb_current_user_can( 'view_by_ip' ) )
		echo apply_filters( 'post_ip', get_post_ip( $post_id ), get_post_id( $post_id ) );
}

function get_post_ip( $post_id = 0 ) {
	$bb_post = bb_get_post( get_post_id( $post_id ) );
	return apply_filters( 'get_post_ip', $bb_post->poster_ip, $bb_post->post_id );
}

function bb_post_admin() {
	$parts = array(
		'ip' => bb_get_post_ip_link(),
		'edit' => bb_get_post_edit_link(),
		'delete' => bb_get_post_delete_link()
	);
	echo join("\n", apply_filters('bb_post_admin', $parts));
}

function post_ip_link( $post_id = 0 ) {
	echo bb_get_post_ip_link( $post_id );
}

function bb_get_post_ip_link( $post_id = 0 ) {
	if ( !bb_current_user_can( 'view_by_ip' ) )
		return;
	
	$uri = bb_get_uri('bb-admin/view-ip.php', array('ip' => get_post_ip($post_id)), BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN);
	$link = '<a href="' . attribute_escape( $uri ) . '">' . get_post_ip( $post_id ) . '</a>';
	return apply_filters( 'post_ip_link', $link, get_post_id( $post_id ) );
}

function post_edit_link( $post_id = 0 ) {
	echo bb_get_post_edit_link( $post_id );
}

function bb_get_post_edit_link( $post_id = 0 ) {
	$bb_post = bb_get_post( get_post_id( $post_id ) );
	if ( bb_current_user_can( 'edit_post', $bb_post->post_id ) ) {
		$uri = bb_get_uri('edit.php', array('id' => $bb_post->post_id));
		return "<a href='" . attribute_escape( apply_filters( 'post_edit_uri', $uri, $bb_post->post_id ) ) . "'>". __('Edit') ."</a>";
	}
}

function post_del_class( $post_id = 0 ) {
	$bb_post = bb_get_post( get_post_id( $post_id ) );
	switch ( $bb_post->post_status ) :
	case 0 : return ''; break;
	case 1 : return 'deleted'; break;
	default: return apply_filters( 'post_del_class', $bb_post->post_status, $bb_post->post_id );
	endswitch;
}

function post_delete_link( $post_id = 0 ) {
	echo bb_get_post_delete_link( $post_id );
}

function bb_get_post_delete_link( $post_id = 0 ) {
	$bb_post = bb_get_post( get_post_id( $post_id ) );
	if ( !bb_current_user_can( 'delete_post', $bb_post->post_id ) )
		return;

	if ( 1 == $bb_post->post_status ) {
		$query = array('id' => $bb_post->post_id, 'status' => 0, 'view' => 'all');
		$display = __('Undelete');
	} else {
		$query = array('id' => $bb_post->post_id, 'status' => 1);
		$display = __('Delete');
	}
	$uri = bb_get_uri('bb-admin/delete-post.php', $query, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN);
	$uri = attribute_escape( bb_nonce_url( $uri, 'delete-post_' . $bb_post->post_id ) );
	$r = '<a href="' . $uri . '" class="delete:thread:post-' . $bb_post->post_id . '">' . $display . '</a>';
	$r = apply_filters( 'post_delete_link', $r, $bb_post->post_status, $bb_post->post_id );
	return $r;
}

function post_author_id( $post_id = 0 ) {
	echo apply_filters( 'post_author_id', get_post_author_id( $post_id ), get_post_id( $post_id ) );
}

function get_post_author_id( $post_id = 0 ) {
	$bb_post = bb_get_post( get_post_id( $post_id ) );
	return apply_filters( 'get_post_author_id', (int) $bb_post->poster_id, get_post_id( $post_id ) );
}

function post_author_title( $post_id = 0 ) {
	$title = get_post_author_title( $post_id );
	if ( false === $title )
		$r = __('Unregistered'); // This should never happen
	else
		$r = '<a href="' . attribute_escape( get_user_profile_link( get_post_author_id( $post_id ) ) ) . '">' . $title . '</a>';

	echo apply_filters( 'post_author_title', $r, get_post_id( $post_id ) );
}

function get_post_author_title( $post_id = 0 ) {
	return get_user_title( get_post_author_id( $post_id ) );
}

function post_author_type( $post_id = 0 ) {
	$id = get_post_author_id( $post_id );
	$type = get_user_type( $id );
	if ( false === $type )
		$r = __('Unregistered'); // This should never happen
	else
		$r = '<a href="' . attribute_escape( get_user_profile_link( $id ) ) . '">' . $type . '</a>';

	echo apply_filters( 'post_author_type', $r );
}

function allowed_markup( $args = '' ) {
	echo apply_filters( 'allowed_markup', get_allowed_markup( $args ) );
}

// format=list or array( 'format' => 'list' )
function get_allowed_markup( $args = '' ) {
	$args = wp_parse_args( $args, array('format' => 'flat') );
	extract($args, EXTR_SKIP);

	$tags = bb_allowed_tags();
	unset($tags['pre'], $tags['br']);
	$tags = array_keys($tags);

	switch ( $format ) :
	case 'array' :
		$r = $tags;
		break;
	case 'list' :
		$r = "<ul class='allowed-markup'>\n\t<li>";
		$r .= join("</li>\n\t<li>", $tags);
		$r .= "</li>\n</ul>\n";
		break;
	default :
		$r = join(' ', $tags);
		break;
	endswitch;
	return apply_filters( 'get_allowed_markup', $r, $format );
}

// USERS
function bb_get_user_id( $id = 0 ) {
	global $user;
	if ( is_object($id) && isset($id->ID) )
		return (int) $id->ID;
	elseif ( !$id )
		return $user->ID;

	$_user = bb_get_user( $id );
	return $_user->ID;
}

function user_profile_link( $id = 0 , $page = 1, $context = BB_URI_CONTEXT_A_HREF ) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF;
	}
	echo apply_filters( 'user_profile_link', get_user_profile_link( $id ), bb_get_user_id( $id ), $context );
}

function get_user_profile_link( $id = 0, $page = 1, $context = BB_URI_CONTEXT_A_HREF ) {
	$user = bb_get_user( bb_get_user_id( $id ) );
	
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF;
	}
	
	$rewrite = bb_get_option( 'mod_rewrite' );
	if ( $rewrite ) {
		if ( $rewrite === 'slugs' ) {
			$column = 'user_nicename';
		} else {
			$column = 'ID';
		}
		$page = $page > 1 ? '/page/' . $page : '';
		$r = bb_get_uri('profile/' . $user->$column . $page, null, $context);
	} else {
		$query = array(
			'id' => $user->ID,
			'page' => $page > 1 ? $page : false
		);
		$r = bb_get_uri('profile.php', $query, $context);
	}
	return apply_filters( 'get_user_profile_link', $r, $user->ID, $context );
}

function user_delete_button() {
	global $user;
	if ( bb_current_user_can( 'edit_users' ) && bb_get_current_user_info( 'id' ) != (int) $user->ID )
		echo apply_filters( 'user_delete_button', get_user_delete_button() );
}

function get_user_delete_button() {
	$r  = '<input type="submit" class="delete" name="delete-user" value="' . __('Delete User &raquo;') . '" ';
	$r .= 'onclick="return confirm(\'' . js_escape(__('Are you sure you want to delete this user?')) . '\')" />';
	return apply_filters( 'get_user_delete_button', $r);
}

function profile_tab_link( $id = 0, $tab, $page = 1 ) {
	echo apply_filters( 'profile_tab_link', get_profile_tab_link( $id, $tab ) );
}

function get_profile_tab_link( $id = 0, $tab, $page = 1 ) {
	$tab = bb_sanitize_with_dashes($tab);
	if ( bb_get_option('mod_rewrite') )
		$r = get_user_profile_link( $id ) . "/$tab" . ( 1 < $page ? "/page/$page" : '' );
	else {
		$args = array('tab' => $tab);
		$args['page'] = 1 < $page ? $page : false;
		$r = add_query_arg( $args, get_user_profile_link( $id ) );
	}
	return apply_filters( 'get_profile_tab_link', $r, bb_get_user_id( $id ) );
}

function user_link( $id = 0 ) {
	echo apply_filters( 'user_link', get_user_link( $id ), $id );
}

function get_user_link( $id = 0 ) {
	if ( $user = bb_get_user( bb_get_user_id( $id ) ) )
		return apply_filters( 'get_user_link', $user->user_url, $user->ID );
}

function full_user_link( $id = 0 ) {
	echo get_full_user_link( $id );
}

function get_full_user_link( $id = 0 ) {
	if ( get_user_link( $id ) )
		$r = '<a href="' . attribute_escape( get_user_link( $id ) ) . '">' . get_user_name( $id ) . '</a>';
	else
		$r = get_user_name( $id );
	return $r;
}

function user_type_label( $type ) {
	echo apply_filters( 'user_type_label', get_user_type_label( $type ), $type );
}

function get_user_type_label( $type ) {
	global $wp_roles;
	if ( $wp_roles->is_role( $type ) )
		return apply_filters( 'get_user_type_label', $wp_roles->role_names[$type], $type );
}

function user_type( $id = 0 ) {
	echo apply_filters( 'user_type', get_user_type( $id ) );
}

function get_user_type( $id = 0 ) {
	if ( $user = bb_get_user( bb_get_user_id( $id ) ) ) :
		@$caps = array_keys($user->capabilities);
		if ( !$caps )
			$caps[] = 'inactive';

		$type = get_user_type_label( $caps[0] ); //Just support one role for now.
	else :
		$type = false;
	endif;
	return apply_filters( 'get_user_type', $type, $user->ID );
}

function get_user_name( $id = 0 ) {
	$user = bb_get_user( bb_get_user_id( $id ) );
	return apply_filters( 'get_user_name', $user->user_login, $user->ID );
}

function user_title( $id = 0 ) {
	echo apply_filters( 'user_title', get_user_title( $id ), bb_get_user_id( $id ) );
}

function get_user_title( $id = 0 ) {
	$user = bb_get_user( bb_get_user_id( $id ) );
	return empty( $user->title ) ? get_user_type( $id ) : apply_filters( 'get_user_title', $user->title, $user->ID );
}

function profile_pages() {
	global $user, $page;
	$add = 0;
	$add = apply_filters( 'profile_pages_add', $add );
	echo apply_filters( 'topic_pages', get_page_number_links( $page, $user->topics_replied + $add ) );
}

function bb_profile_data( $id = 0 ) {
	if ( !$user = bb_get_user( bb_get_user_id( $id ) ) )
		return;

	$reg_time = bb_gmtstrtotime( $user->user_registered );
	$profile_info_keys = get_profile_info_keys();
	echo "<dl id='userinfo'>\n";
	echo "\t<dt>" . __('Member Since') . "</dt>\n";
	echo "\t<dd>" . bb_datetime_format_i18n($reg_time, 'date') . ' (' . bb_since($reg_time) . ")</dd>\n";
	if ( is_array( $profile_info_keys ) ) {
		foreach ( $profile_info_keys as $key => $label ) {
			$val = 'user_url' == $key ? get_user_link( $user->ID ) : $user->$key;
			if (
				( 'user_email' != $key || ( 'user_email' == $key && bb_current_user_can( 'edit_users' ) ) )
				&& isset( $user->$key )
				&& $val
				&& 'http://' != $val
			) {
				echo "\t<dt>{$label[1]}</dt>\n";
				echo "\t<dd>" . make_clickable( $val ) . "</dd>\n";
			}
		}
	}
	echo "</dl>\n";
}

function bb_profile_base_content() {
	global $self;
	if ( !is_callable( $self ) )
		return; // should never happen
	call_user_func( $self );
}

function bb_profile_data_form( $id = 0 ) {
	global $errors;
	if ( !$user = bb_get_user( bb_get_user_id( $id ) ) )
		return;

	if ( !bb_current_user_can( 'edit_user', $user->ID ) )
		return;

	$error_codes = $errors->get_error_codes();
	$profile_info_keys = get_profile_info_keys();
	$required = false;
?>
<table id="userinfo">
<?php
	if ( is_array($profile_info_keys) ) :
		$bb_current_id = bb_get_current_user_info( 'id' );
		foreach ( $profile_info_keys as $key => $label ) :
			if ( 'user_email' == $key && $bb_current_id != $user->ID )
				continue;

			if ( $label[0] ) {
				$class = 'form-field form-required required';
				$title = '<sup class="required">*</sup> ' . attribute_escape( $label[1] );
				$required = true;
			} else {
				$class = 'form-field';
				$title = attribute_escape( $label[1] );
			}


			$name = attribute_escape( $key );
			$type = isset($label[2]) ? attribute_escape( $label[2] ) : 'text';

			if ( in_array( $key, $error_codes ) ) {
				$class .= ' form-invalid';
				$data = $errors->get_error_data( $key );
				if ( isset($data['data']) )
					$value = $data['data'];
				else
					$value = $_POST[$key];

				$message = wp_specialchars( $errors->get_error_message( $key ) );
				$message = "<p class='error'>$message</p>";
			} else {
				$value = $user->$key;
				$message = '';
			}
			$value = attribute_escape( $value );

?>

<tr class="<?php echo $class; ?>">
	<th scope="row"><label for="<?php echo $name; ?>"><?php echo $title; ?></label></th>
	<td>
		<input name="<?php echo $name; ?>" type="<?php echo $type; ?>" id="<?php echo $name; ?>" value="<?php echo $value; ?>" />
		<?php echo $message; ?>
	</td>
</tr>

<?php endforeach; endif; // $profile_info_keys; $profile_info_keys ?>

</table>

<?php bb_nonce_field( 'edit-profile_' . $user->ID ); if ( $required ) : ?>

<p><sup class="required">*</sup> <?php _e('These items are <span class="required">required</span>.') ?></p>

<?php
	endif;
	do_action( 'extra_profile_info', $user->ID );
}

function bb_profile_admin_form( $id = 0 ) {
	global $wp_roles, $errors;
	if ( !$user = bb_get_user( bb_get_user_id( $id ) ) )
		return;

	if ( !bb_current_user_can( 'edit_user', $user->ID ) )
		return;

	$error_codes = $errors->get_error_codes();
	$bb_current_id = bb_get_current_user_info( 'id' );

	$profile_admin_keys = get_profile_admin_keys();
	$assignable_caps = get_assignable_caps();
	$required = false;

	$roles = $wp_roles->role_names;
	$can_keep_gate = bb_current_user_can( 'keep_gate' );

	// Keymasters can't demote themselves
	if ( ( $bb_current_id == $user->ID && $can_keep_gate ) || ( array_key_exists('keymaster', $user->capabilities) && !$can_keep_gate ) )
		$roles = array( 'keymaster' => $roles['keymaster'] );
	elseif ( !$can_keep_gate ) // only keymasters can promote others to keymaster status
		unset($roles['keymaster']);

?>
<table id="admininfo">
<tr class='form-field<?php if ( in_array( 'role', $error_codes ) ) echo ' form-invalid'; ?>'>
	<th scope="row"><?php _e('User Type'); ?></th>
	<td>
		<select name="role">
<?php foreach( $roles as $r => $n ) : ?>
			<option value="<?php echo $r; ?>"<?php if ( array_key_exists($r, $user->capabilities) ) echo ' selected="selected"'; ?>><?php echo $n; ?></option>
<?php endforeach; ?>
		</select>
		<?php if ( in_array( 'role', $error_codes ) ) echo '<p class="error">' . $errors->get_error_message( 'role' ) . '</p>'; ?>
	</td>
</tr>
<tr class="extra-caps-row">
	<th scope="row"><?php _e('Allow this user to'); ?></th>
	<td>
<?php
	foreach( $assignable_caps as $cap => $label ) :
		$name = attribute_escape( $cap );
		$checked = array_key_exists($cap, $user->capabilities) ? ' checked="checked"' : '';
		$label = wp_specialchars( $label );
?>

		<label><input name="<?php echo $name; ?>" value="1" type="checkbox"<?php echo $checked; ?> /> <?php echo $label; ?></label><br />

<?php endforeach; ?>

	</td>
</tr>

<?php
	if ( is_array($profile_admin_keys) ) :
		foreach ( $profile_admin_keys as $key => $label ) :
			if ( $label[0] ) {
				$class = 'form-field form-required required';
				$title = '<sup class="required">*</sup> ' . attribute_escape( $label[1] );
				$required = true;
			} else {
				$class = 'form-field';
				$title = attribute_escape( $label[1] );
			}


			$name = attribute_escape( $key );
			$type = isset($label[2]) ? attribute_escape( $label[2] ) : 'text';

			$checked = false;
			if ( in_array( $key, $error_codes ) ) {
				$class .= ' form-invalid';
				$data = $errors->get_error_data( $key );
				if ( 'checkbox' == $type ) {
					if ( isset($data['data']) )
						$checked = $data['data'];
					else
						$checked = $_POST[$key];
					$value = $label[3];
					$checked = $checked == $value;
				} else {
					if ( isset($data['data']) )
						$value = $data['data'];
					else
						$value = $_POST[$key];
				}

				$message = wp_specialchars( $errors->get_error_message( $key ) );
				$message = "<p class='error'>$message</p>";
			} else {
				if ( 'checkbox' == $type ) {
					$checked = $user->$key == $label[3] || $label[4] == $label[3];
					$value = $label[3];
				} else {
					$value = $user->$key;
				}
				$message = '';
			}

			$checked = $checked ? ' checked="checked"' : '';
			$value = attribute_escape( $value );

?>

<tr class="<?php echo $class; ?>">
	<th scope="row"><?php echo $title ?></th>
	<td>
		<?php if ( 'checkbox' == $type && isset($label[5]) ) echo "<label for='$name'>"; ?>
		<input name="<?php echo $name; ?>" id="<?php echo $name; ?>" type="<?php echo $type; ?>"<?php echo $checked; ?> value="<?php echo $value; ?>" />
		<?php if ( 'checkbox' == $type && isset($label[5]) ) echo wp_specialchars( $label[5] ) . "</label>"; ?>
		<?php echo $message; ?>
	</td>
</tr>

<?php endforeach; endif; // $profile_admin_keys; $profile_admin_keys ?>

</table>

<?php if ( $required ) : ?>
<p><sup class="required">*</sup> <?php _e('These items are <span class="required">required</span>.') ?></p>

<?php endif; ?>
<p><?php _e('Inactive users can login and look around but not do anything.
Blocked users just see a simple error message when they visit the site.</p>
<p><strong>Note</strong>: Blocking a user does <em>not</em> block any IP addresses.'); ?></p>
<?php
}

function bb_profile_password_form( $id = 0 ) {
	global $errors;
	if ( !$user = bb_get_user( bb_get_user_id( $id ) ) )
		return;

	if ( !bb_current_user_can( 'change_user_password', $user->ID ) )
		return;

	$class = 'form-field form-required';

	if ( $message = $errors->get_error_message( 'pass' ) ) {
		$class .= ' form-invalid';
		$message = '<p class="error">' . wp_specialchars( $message ) . '</p>';
	}
?>

<table>
<tr class="<?php echo $class; ?>">
	<th scope="row" rowspan="2"><label for="pass1"><?php _e('New password'); ?></label></th>
	<td><input name="pass1" type="password" id="pass1" autocomplete="off" /></td>
</tr>
<tr class="<?php echo $class; ?>">
	<td>
		<input name="pass2" type="password" id="pass2" autocomplete="off" />
		<?php echo $message; ?>
	</td>
</tr>
</table>

<?php

}

function bb_logout_link( $args = '' ) {
	echo apply_filters( 'bb_logout_link', bb_get_logout_link( $args ), $args );
}

function bb_get_logout_link( $args = '' ) {
	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'text' => $args );

	$defaults = array('text' => __('Log Out'), 'before' => '', 'after' => '');
	$args = wp_parse_args( $args, $defaults );
	extract($args, EXTR_SKIP);

	$uri = attribute_escape( bb_get_uri('bb-login.php', array('logout' => 1), BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_USER_FORMS) );

	return apply_filters( 'bb_get_logout_link', $before . '<a href="' . $uri . '">' . $text . '</a>' . $after, $args );
}

function bb_admin_link( $args = '' ) {
	if ( !bb_current_user_can( 'moderate' ) )
		return;
	echo apply_filters( 'bb_admin_link', bb_get_admin_link( $args ), $args );
}

function bb_get_admin_link( $args = '' ) {
	if ( !bb_current_user_can( 'moderate' ) )
		return;
	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'text' => $args );

	$defaults = array('text' => __('Admin'), 'before' => '', 'after' => '');
	$args = wp_parse_args( $args, $defaults );
	extract($args, EXTR_SKIP);

	$uri = attribute_escape( bb_get_uri('bb-admin/', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN) );

	return apply_filters( 'bb_get_admin_link', $before . '<a href="' . $uri . '">' . $text . '</a>' . $after, $args );
}

function bb_profile_link( $args = '' ) {
	echo apply_filters( 'bb_profile_link', bb_get_profile_link( $args ), $args );
}

function bb_get_profile_link( $args = '' ) {
	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'text' => $args );
	elseif ( is_numeric($args) )
		$args = array( 'id' => $args );

	$defaults = array( 'text' => __('View your profile'), 'before' => '', 'after' => '', 'id' => false );
	$args = wp_parse_args( $args, $defaults );
	extract($args, EXTR_SKIP);

	$id = (int) $id;
	if ( !$id )
		$id = bb_get_current_user_info( 'id' );

	return apply_filters( 'bb_get_profile_link', "$before<a href='" . attribute_escape( get_user_profile_link( $id ) ) . "'>$text</a>$after", $args );
}

function bb_current_user_info( $key = '' ) {
	if ( !$key )
		return;
	echo apply_filters( 'bb_current_user_info', bb_get_current_user_info( $key ), $key );
}
	

function bb_get_current_user_info( $key = '' ) {
	if ( !is_string($key) )
		return;
	if ( !$user = bb_get_current_user() ) // Not globalized
		return false;

	switch ( $key ) :
	case '' :
		return $user;
		break;
	case 'id' :
	case 'ID' :
		return (int) $user->ID;
		break;
	case 'name' :
	case 'login' :
	case 'user_login' :
		return get_user_name( $user->ID );
		break;
	case 'email' :
	case 'user_email' :
		return bb_get_user_email( $user->ID );
		break;
	case 'url' :
	case 'uri' :
	case 'user_url' :
		return get_user_link( $user->ID );
		break;
	endswitch;
}

function bb_get_user_email( $id ) {
	if ( !$user = bb_get_user( $id ) )
		return false;

	return apply_filters( 'bb_get_user_email', $user->user_email, $id );
}

//TAGS
function topic_tags() {
	global $tags, $tag, $topic_tag_cache, $user_tags, $other_tags, $topic;
	if ( is_array( $tags ) || bb_current_user_can( 'edit_tag_by_on', bb_get_current_user_info( 'id' ), $topic->topic_id ) )
		bb_load_template( 'topic-tags.php', array('user_tags', 'other_tags', 'public_tags') );
}

function bb_tag_page_link() {
	echo bb_get_tag_page_link();
}

function bb_get_tag_page_link() {
	return apply_filters( 'bb_get_tag_page_link', bb_get_option( 'domain' ) . bb_get_option( 'tagpath' ) . ( bb_get_option( 'mod_rewrite' ) ? 'tags/' : 'tags.php' ) );
}

function bb_tag_link( $id = 0, $page = 1 ) {
	echo apply_filters( 'bb_tag_link', bb_get_tag_link( $id ), $id, $page );
}

function bb_get_tag_link( $tag_name = 0, $page = 1 ) {
	global $tag;
	if ( $tag_name )
		$_tag = bb_get_tag_by_name( $tag_name );
	else
		$_tag =& $tag;

	if ( bb_get_option('mod_rewrite') )
		$r = bb_get_option('domain') . bb_get_option( 'tagpath' ) . "tags/$_tag->tag" . ( 1 < $page ? "/page/$page" : '' );
	else
		$r = bb_get_option('domain') . bb_get_option( 'tagpath' ) . "tags.php?tag=$_tag->tag" . ( 1 < $page ? "&page=$page" : '' );
	return apply_filters( 'bb_get_tag_link', $r, $_tag->tag, $page );
}

function bb_tag_link_base() {
	echo bb_get_tag_link_base();
}

function bb_get_tag_link_base() {
	return bb_get_tag_page_link() . ( bb_get_option( 'mod_rewrite' ) ? '' : '?tag=' );
}

function bb_tag_name( $id = 0 ) {
	echo wp_specialchars( bb_get_tag_name( $id ) );
}

function bb_get_tag_name( $id = 0 ) {
	global $tag;
	$id = (int) $id;
	if ( $id )
		$_tag = bb_get_tag( $id );
	else
		$_tag =& $tag;
	return $_tag->raw_tag;
}

function bb_tag_rss_link( $id = 0, $context = 0 ) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	echo apply_filters( 'tag_rss_link', bb_get_tag_rss_link($id, $context), $id, $context );
}

function bb_get_tag_rss_link( $tag_id = 0, $context = 0 ) {
	global $tag;
	$tag_id = (int) $tag_id;
	if ( $tag_id )
		$_tag = bb_get_tag( $tag_id );
	else
		$_tag =& $tag;

	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}

	if ( bb_get_option('mod_rewrite') )
		$link = bb_get_uri('rss/tags/' . $_tag->tag, null, $context);
	else
		$link = bb_get_uri('rss.php', array('tag' => $_tag->tag), $context);

	return apply_filters( 'get_tag_rss_link', $link, $tag_id, $context );
}

function bb_list_tags( $args = null ) {
	$defaults = array(
		'tags' => false,
		'format' => 'list',
		'topic' => 0,
		'list_id' => 'tags-list'
	);

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( !$topic = get_topic( get_topic_id( $topic ) ) )
		return false;

	if ( !is_array($tags) )
		$tags = bb_get_topic_tags( $topic->topic_id );

	if ( !$tags )
		return false;

	$list_id = attribute_escape( $list_id );

	$r = '';
	switch ( strtolower($format) ) :
	case 'table' :
		break;
	case 'list' :
	default :
		$args['format'] = 'list';
		$r .= "<ul id='$list_id' class='tags-list list:tag'>\n";
		foreach ( $tags as $tag )
			$r .= _bb_list_tag_item( $tag, $args );
		$r .= "</ul>";
	endswitch;
	echo $r;
}

function _bb_list_tag_item( $tag, $args ) {
	$url = clean_url( bb_get_tag_link( $tag->tag ) );
	$name = wp_specialchars( bb_get_tag_name( $tag->tag_id ) );
	if ( 'list' == $args['format'] )
		return "\t<li id='tag-{$tag->tag_id}_{$tag->user_id}'><a href='$url' rel='tag'>$name</a> " . bb_get_tag_remove_link( array( 'tag' => $tag->tag_id, 'list_id' => $args['list_id'] ) ) . "</li>\n";
}
	
function tag_form( $args = null ) {
	$defaults = array( 'topic' => 0, 'submit' => __('Add &raquo;'), 'list_id' => 'tags-list' );
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( !$topic = get_topic( get_topic_id( $topic ) ) )
		return false;

	if ( !bb_current_user_can( 'edit_tag_by_on', bb_get_current_user_info( 'id' ), $topic->topic_id ) )
		return false;
?>

<form id="tag-form" method="post" action="<?php bb_uri('tag-add.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>" class="add:<?php echo attribute_escape( $list_id ); ?>:">
	<p>
		<input name="tag" type="text" id="tag" />
		<input type="hidden" name="id" value="<?php echo $topic->topic_id; ?>" />
		<?php bb_nonce_field( 'add-tag_' . $topic->topic_id ); ?>
		<input type="submit" name="submit" id="tagformsub" value="<?php echo attribute_escape( $submit ); ?>" />
	</p>
</form>

<?php
}

function manage_tags_forms() {
	global $tag;
	if ( !bb_current_user_can('manage_tags') )
		return false;
	$form  = "<ul id='manage-tags'>\n ";
	$form .= "<li id='tag-rename'>" . __('Rename tag:') . "\n\t";
	$form .= "<form method='post' action='" . bb_get_uri('bb-admin/tag-rename.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN) . "'><div>\n\t";
	$form .= "<input type='text' name='tag' size='10' maxlength='30' />\n\t";
	$form .= "<input type='hidden' name='id' value='$tag->tag_id' />\n\t";
	$form .= "<input type='submit' name='Submit' value='" . __('Rename') . "' />\n\t";
	echo $form;
	bb_nonce_field( 'rename-tag_' . $tag->tag_id );
	echo "\n\t</div></form>\n  </li>\n ";
	$form  = "<li id='tag-merge'>" . __('Merge this tag into:') . "\n\t";
	$form .= "<form method='post' action='" . bb_get_uri('bb-admin/tag-merge.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN) . "'><div>\n\t";
	$form .= "<input type='text' name='tag' size='10' maxlength='30' />\n\t";
	$form .= "<input type='hidden' name='id' value='$tag->tag_id' />\n\t";
	$form .= "<input type='submit' name='Submit' value='" . __('Merge') . "' ";
	$form .= 'onclick="return confirm(\'' . js_escape( sprintf(__('Are you sure you want to merge the "%s" tag into the tag you specified? This is permanent and cannot be undone.'), $tag->raw_tag) ) . "');\" />\n\t";
	echo $form;
	bb_nonce_field( 'merge-tag_' . $tag->tag_id );
	echo "\n\t</div></form>\n  </li>\n ";
	$form  = "<li id='tag-destroy'>" . __('Destroy tag:') . "\n\t";
	$form .= "<form method='post' action='" . bb_get_uri('bb-admin/tag-destroy.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN) . "'><div>\n\t";
	$form .= "<input type='hidden' name='id' value='$tag->tag_id' />\n\t";
	$form .= "<input type='submit' name='Submit' value='" . __('Destroy') . "' ";
	$form .= 'onclick="return confirm(\'' . js_escape( sprintf(__('Are you sure you want to destroy the "%s" tag? This is permanent and cannot be undone.'), $tag->raw_tag) ) . "');\" />\n\t";
	echo $form;
	bb_nonce_field( 'destroy-tag_' . $tag->tag_id );
	echo "\n\t</div></form>\n  </li>\n</ul>";
}

function bb_tag_remove_link( $args = null ) {
	echo bb_get_tag_remove_link( $args );
}

function bb_get_tag_remove_link( $args = null ) {
	if ( is_scalar($args) )
		$args = array( 'tag' => $args );
	$defaults = array( 'tag' => 0, 'topic' => 0, 'list_id' => 'tags-list' );
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if ( !$tag = bb_get_tag( bb_get_tag_id( $tag ) ) )
		return false;
	if ( !$topic = get_topic( get_topic_id( $topic ) ) )
		return false;
	if ( !bb_current_user_can( 'edit_tag_by_on', $tag->user_id, $topic->topic_id ) )
		return false;
	$url = bb_get_uri('tag-remove.php', array('tag' => $tag->tag_id, 'user' => $tag->user_id, 'topic' => $tag->topic_id) );
	$url = clean_url( bb_nonce_url( $url, 'remove-tag_' . $tag->tag_id . '|' . $tag->topic_id) );
	$title = attribute_escape( __('Remove this tag') );
	$list_id = attribute_escape( $list_id );
	return "[<a href='$url' class='delete:$list_id:tag-{$tag->tag_id}_{$tag->user_id}' title='$title'>&times;</a>]";
}

function bb_tag_heat_map( $args = '' ) {
	$defaults = array( 'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'limit' => 45, 'format' => 'flat' );
	$args = wp_parse_args( $args, $defaults );

	if ( 1 < $fn = func_num_args() ) : // For back compat
		$args['smallest'] = func_get_arg(0);
		$args['largest']  = func_get_arg(1);
		$args['unit']     = 2 < $fn ? func_get_arg(2) : $unit;
		$args['limit']    = 3 < $fn ? func_get_arg(3) : $limit;
	endif;

	extract($args, EXTR_SKIP);

	$tags = bb_get_top_tags( false, $limit );

	if ( empty($tags) )
		return;

	$r = bb_get_tag_heat_map( $tags, $args );
	echo apply_filters( 'tag_heat_map', $r, $args );
}

function bb_related_tags_heat_map( $args = '' ) {
	if ( $args && is_string($args) && false === strpos($args, '=') || is_numeric($args) )
		$args = array( 'tag' => $args );

	$defaults = array( 'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'limit' => 45, 'format' => 'flat', 'tag' => false );
	$args = wp_parse_args( $args, $defaults );

	if ( 1 < $fn = func_num_args() ) : // For back compat
		$args['smallest'] = func_get_arg(0);
		$args['largest']  = func_get_arg(1);
		$args['unit']     = 2 < $fn ? func_get_arg(2) : $unit;
		$args['limit']    = 3 < $fn ? func_get_arg(3) : $limit;
	endif;

	extract($args, EXTR_SKIP);

	$tags = bb_related_tags( $tag, $limit );

	if ( empty($tags) )
		return;

	$r = bb_get_tag_heat_map( $tags, $args );
	echo apply_filters( 'bb_related_tags_heat_map', $r, $args );
}

function bb_get_tag_heat_map( $tags, $args = '' ) {
	$defaults = array( 'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'limit' => 45, 'format' => 'flat' );
	$args = wp_parse_args( $args, $defaults );
	extract($args, EXTR_SKIP);

	if ( !$tags )
		return;

	foreach ( (array) $tags as $tag ) {
		$counts{$tag->raw_tag} = $tag->tag_count;
		$taglinks{$tag->raw_tag} = bb_get_tag_link( $tag->tag );
	}

	$min_count = min($counts);
	$spread = max($counts) - $min_count;
	if ( $spread <= 0 )
		$spread = 1;
	$fontspread = $largest - $smallest;
	if ( $fontspread <= 0 )
		$fontspread = 1;
	$fontstep = $fontspread / $spread;

	do_action_ref_array( 'sort_tag_heat_map', array(&$counts) );

	$a = array();

	foreach ( $counts as $tag => $count ) {
		$taglink = attribute_escape($taglinks{$tag});
		$tag = str_replace(' ', '&nbsp;', wp_specialchars( $tag ));
		$a[] = "<a href='$taglink' title='" . attribute_escape( sprintf( __('%d topics'), $count ) ) . "' rel='tag' style='font-size: " .
			( $smallest + ( ( $count - $min_count ) * $fontstep ) )
			. "$unit;'>$tag</a>";
	}

	switch ( $format ) :
	case 'array' :
		$r =& $a;
		break;
	case 'list' :
		$r = "<ul class='bb-tag-heat-map'>\n\t<li>";
		$r .= join("</li>\n\t<li>", $a);
		$r .= "</li>\n</ul>\n";
		break;
	default :
		$r = join("\n", $a);
		break;
	endswitch;

	return apply_filters( 'bb_get_tag_heat_map', $r, $tags, $args );
}

function bb_sort_tag_heat_map( &$tag_counts ) {
	uksort($tag_counts, 'strnatcasecmp');
}

function tag_pages() {
	global $page, $tagged_topic_count;
	echo apply_filters( 'topic_pages', get_page_number_links( $page, $tagged_topic_count ) );
}

function bb_forum_dropdown( $args = '' ) {
	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'callback' => $args );
	if ( 1 < func_num_args() )
		$args['callback_args'] = func_get_arg(1);
	echo bb_get_forum_dropdown( $args );
}

function bb_get_forum_dropdown( $args = '' ) {
	$defaults = array( 'callback' => false, 'callback_args' => false, 'id' => 'forum_id', 'none' => false, 'selected' => false, 'tab' => 5, 'hierarchical' => 1, 'depth' => 0, 'child_of' => 0, 'disable_categories' => 1 );
	if ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array( 'callback' => $args );
	if ( 1 < func_num_args() )
		$args['callback_args'] = func_get_arg(1);

	$args = wp_parse_args( $args, $defaults );

	extract($args, EXTR_SKIP);

	if ( !bb_forums( $args ) )
		return;

	global $forum_id, $forum;
	$old_global = $forum;

	$name = attribute_escape( $id );
	$id = str_replace( '_', '-', $name );
	$tab = (int) $tab;

	if ( $none && 1 == $none )
		$none = __('- None -');

	$r = '<select name="' . $name . '" id="' . $id . '" tabindex="' . $tab . '">' . "\n";
	if ( $none )
		$r .= "\n" . '<option value="0">' . $none . '</option>' . "\n";

	$no_option_selected = true;
	$options = array();
	while ( $depth = bb_forum() ) :
		global $forum; // Globals + References = Pain
		if ($disable_categories && $forum->forum_is_category) {
			$options[] = array(
				'value' => 0,
				'display' => str_repeat( '&nbsp;&nbsp;&nbsp;', $depth - 1 ) . $forum->forum_name,
				'disabled' => true,
				'selected' => false
			);
			continue;
		}
		$_selected = false;
		if ( (!$selected && $forum_id == $forum->forum_id) || $selected == $forum->forum_id ) {
			$_selected = true;
			$no_option_selected = false;
		}
		$options[] = array(
			'value' => $forum->forum_id,
			'display' => str_repeat( '&nbsp;&nbsp;&nbsp;', $depth - 1 ) . $forum->forum_name,
			'disabled' => false,
			'selected' => $_selected
		);
	endwhile;
	
	foreach ($options as $option_index => $option_value) {
		if (!$none && !$selected && $no_option_selected && !$option_value['disabled']) {
			$option_value['selected'] = true;
			$no_option_selected = false;
		}
		$option_disabled = $option_value['disabled'] ? ' disabled="disabled"' : '';
		$option_selected = $option_value['selected'] ? ' selected="selected"' : '';
		$r .= "\n" . '<option value="' . $option_value['value'] . '"' . $option_disabled . $option_selected . '>' . $option_value['display'] . '</option>' . "\n";
	}
	
	$forum = $old_global;
	$r .= '</select>' . "\n";
	return $r;
}

//FAVORITES
function favorites_link( $user_id = 0 ) {
	echo apply_filters( 'favorites_link', get_favorites_link( $user_id ) );
}

function get_favorites_link( $user_id = 0 ) {
	if ( !$user_id )
		$user_id = bb_get_current_user_info( 'id' );
	return apply_filters( 'get_favorites_link', get_profile_tab_link($user_id, 'favorites'), $user_id );
}

function user_favorites_link($add = array(), $rem = array(), $user_id = 0) {
	global $topic, $bb_current_user;
	if ( empty($add) || !is_array($add) )
		$add = array('mid' => __('Add this topic to your favorites'), 'post' => __(' (%?%)'));
	if ( empty($rem) || !is_array($rem) )
		$rem = array( 'pre' => __('This topic is one of your %favorites% ['), 'mid' => __('&times;'), 'post' => __(']'));
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

        $url = clean_url( get_favorites_link( $user_id ) );
	if ( $is_fav = is_user_favorite( $user->ID, $topic->topic_id ) ) :
		$rem = preg_replace('|%(.+)%|', "<a href='$url'>$1</a>", $rem);
		$favs = array('fav' => '0', 'topic_id' => $topic->topic_id);
		$pre  = ( is_array($rem) && isset($rem['pre'])  ) ? $rem['pre']  : '';
		$mid  = ( is_array($rem) && isset($rem['mid'])  ) ? $rem['mid']  : ( is_string($rem) ? $rem : '' );
		$post = ( is_array($rem) && isset($rem['post']) ) ? $rem['post'] : '';
	elseif ( false === $is_fav ) :
		$add = preg_replace('|%(.+)%|', "<a href='$url'>$1</a>", $add);
		$favs = array('fav' => '1', 'topic_id' => $topic->topic_id);
		$pre  = ( is_array($add) && isset($add['pre'])  ) ? $add['pre']  : '';
		$mid  = ( is_array($add) && isset($add['mid'])  ) ? $add['mid']  : ( is_string($add) ? $add : '' );
		$post = ( is_array($add) && isset($add['post']) ) ? $add['post'] : '';
	endif;

	$url = clean_url(  bb_nonce_url( add_query_arg( $favs, get_favorites_link( $user_id ) ), 'toggle-favorite_' . $topic->topic_id ) );

	if (  !is_null($is_fav) )
		echo "<span id='favorite-$topic->topic_id'>$pre<a href='$url' class='dim:favorite-toggle:favorite-$topic->topic_id:is-not-favorite'>$mid</a>$post</span>";
}

function favorites_rss_link( $id = 0, $context = 0 ) {
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	echo apply_filters('favorites_rss_link', get_favorites_rss_link( $id, $context ), $context);
}

function get_favorites_rss_link( $id = 0, $context = 0 ) {
	$user = bb_get_user( bb_get_user_id( $id ) );
	
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_FEED;
	}
	
	$rewrite = bb_get_option( 'mod_rewrite' );
	if ( $rewrite ) {
		if ( $rewrite === 'slugs' ) {
			$column = 'user_nicename';
		} else {
			$column = 'ID';
		}
		$link = bb_get_uri('rss/profile/' . $user->$column, null, $context);
	} else {
		$link = bb_get_uri('rss.php', array('profile' => $user->ID), $context);
	}
	return apply_filters( 'get_favorites_rss_link', $link, $user->ID, $context );
}

function favorites_pages() {
	global $page, $user, $favorites_total;
	echo apply_filters( 'favorites_pages', get_page_number_links( $page, $favorites_total ), $user->user_id );
}

//VIEWS
function view_name( $view = '' ) { // Filtration should be done at bb_register_view()
	echo get_view_name( $view );
}

function get_view_name( $_view = '' ) {
	global $view, $bb_views;
	if ( $_view )
		$v = bb_slug_sanitize($_view);
	else
		$v =& $view;

	if ( isset($bb_views[$v]) )
		return $bb_views[$v]['title'];
}

function view_pages() {
	global $page, $view_count;
	echo apply_filters( 'view_pages', get_page_number_links( $page, $view_count ) );
}

function view_link( $_view = false, $page = 1, $context = BB_URI_CONTEXT_A_HREF ) {
	echo get_view_link( $_view, $page, $context );
}

function get_view_link( $_view = false, $page = 1, $context = BB_URI_CONTEXT_A_HREF ) {
	global $view, $bb_views;
	if ( $_view )
		$v = bb_slug_sanitize($_view);
	else
		$v =& $view;
	
	if (!$context || !is_integer($context)) {
		$context = BB_URI_CONTEXT_A_HREF;
	}
	
	if ( !array_key_exists($v, $bb_views) )
		return bb_get_uri(null, null, $context);
	if ( bb_get_option('mod_rewrite') ) {
		$page = $page > 1 ? '/page/' . $page : '';
		$link = bb_get_uri('view/' . $v . $page, null, $context);
	} else {
		$query = array(
			'view' => $v,
			'page' => $page > 1 ? $page : false,
		);
		$link = bb_get_uri('view.php', $query, $context);
	}

	return apply_filters( 'get_view_link', $link, $v, $page, $context );
}

function _bb_parse_time_function_args( $args ) {
	if ( is_numeric($args) )
		$args = array('id' => $args);
	elseif ( $args && is_string($args) && false === strpos($args, '=') )
		$args = array('format' => $args);

	$defaults = array( 'id' => 0, 'format' => 'since', 'more' => 0 );
	return wp_parse_args( $args, $defaults );
}

function _bb_time_function_return( $time, $args ) {
	$time = bb_gmtstrtotime( $time );

	switch ( $format = $args['format'] ) :
	case 'since' :
		return bb_since( $time, $args['more'] );
		break;
	case 'timestamp' :
		$format = 'U';
		break;
	case 'mysql' :
		$format = 'Y-m-d H:i:s';
		break;
	endswitch;

	return bb_gmdate_i18n( $format, $time );
}

function bb_template_scripts() {
	if ( is_topic() && bb_is_user_logged_in() )
		wp_enqueue_script( 'topic' );
}
