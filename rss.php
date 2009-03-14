<?php
require('./bb-load.php');

// Determine the type of feed and the id of the object
if ( isset($_GET['view']) || get_path() == 'view' ) {
	
	// View
	$feed = 'view';
	$feed_id = isset($_GET['view']) ? $_GET['view'] : get_path(2);
	
} elseif ( isset($_GET['topic']) || get_path() == 'topic' ) {
	
	// Topic
	$feed = 'topic';
	$topic = get_topic(isset($_GET['topic']) ? $_GET['topic'] : get_path(2));
	$feed_id = $topic->topic_id;
	
} elseif ( isset($_GET['profile']) || get_path() == 'profile' ) {
	
	// Profile
	$feed = 'profile';
	$feed_id = isset($_GET['profile']) ? $_GET['profile'] : get_path(2);
	
} elseif ( isset($_GET['tag']) || get_path() == 'tags' ) {
	
	if ( isset($_GET['topics']) || get_path(3) == 'topics' ) {
		// Tag recent topics
		$feed = 'tag-topics';
	} else {
		// Tag recent posts
		$feed = 'tag-posts';
	}
	$feed_id = isset($_GET['tag']) ? $_GET['tag'] : get_path(2);
	
} elseif ( isset($_GET['forum']) || get_path() == 'forum' ) {
	
	if ( isset($_GET['topics']) || get_path(3) == 'topics' ) {
		// Forum recent topics
		$feed = 'forum-topics';
	} else {
		// Forum recent posts
		$feed = 'forum-posts';
	}
	$forum = get_forum(isset($_GET['forum']) ? $_GET['forum'] : get_path(2));
	$feed_id = $forum->forum_id;
	
} elseif ( isset($_GET['topics']) || get_path() == 'topics' ) {
	
	// Recent topics
	$feed = 'all-topics';
	
} else {
	
	// Recent posts
	$feed = 'all-posts';
	
}

// Initialise the override variable
$bb_db_override = false;
do_action( 'bb_rss.php_pre_db' );

if ( !$bb_db_override ) {
	
	// Get the posts and the title for the given feed
	switch ($feed) {
		case 'view':
			if ( !isset($bb_views[$feed_id]) )
				die();
			if ( !$bb_views[$feed_id]['feed'] )
				die();
			if ( !$topics_object = new BB_Query( 'topic', $bb_views[$feed_id]['query'], "bb_view_$feed_id" ) )
				die();
			
			$topics = $topics_object->results;
			if ( !$topics || !is_array($topics) )
				die();
			
			$posts = array();
			foreach ($topics as $topic) {
				$posts[] = bb_get_first_post($topic->topic_id);
			}
			
			$title = wp_specialchars( sprintf( __( '%1$s View: %2$s' ), bb_get_option( 'name' ), $bb_views[$feed_id]['title'] ) );
			$link = get_view_link($feed_id);
			$link_self = bb_get_view_rss_link($feed_id);
			break;
		
		case 'topic':
			if ( !$topic = get_topic ( $feed_id ) )
				die();
			if ( !$posts = get_thread( $feed_id, 0, 1 ) )
				die();
			$title = wp_specialchars( sprintf( __( '%1$s Topic: %2$s' ), bb_get_option( 'name' ), get_topic_title() ) );
			$link = get_topic_link($feed_id);
			$link_self = get_topic_rss_link($feed_id);
			break;
		
		case 'profile':
			if ( !$user = bb_get_user( $feed_id ) )
				if ( !$user = bb_get_user_by_nicename( $feed_id ) )
					die();
			if ( !$posts = get_user_favorites( $user->ID ) )
				die();
			$title = wp_specialchars( sprintf( __( '%1$s User Favorites: %2$s' ), bb_get_option( 'name' ), $user->user_login ) );
			$link = bb_get_profile_link($feed_id);
			$link_self = get_favorites_rss_link($feed_id);
			break;
		
		case 'tag-topics':
			if ( !$tag = bb_get_tag( $feed_id ) )
				die();
			if ( !$topics = get_tagged_topics( array( 'tag_id' => $tag->tag_id, 'page' => 0 ) ) )
				die();
			
			$posts = array();
			foreach ($topics as $topic) {
				$posts[] = bb_get_first_post($topic->topic_id);
			}
			
			$title = wp_specialchars( sprintf( __( '%1$s Tag: %2$s - Recent Topics' ), bb_get_option( 'name' ), bb_get_tag_name() ) );
			$link = bb_get_tag_link($feed_id);
			$link_self = bb_get_tag_topics_rss_link($feed_id);
			break;
		
		case 'tag-posts':
			if ( !$tag = bb_get_tag( $feed_id ) )
				die();
			if ( !$posts = get_tagged_topic_posts( array( 'tag_id' => $tag->tag_id, 'page' => 0 ) ) )
				die();
			$title = wp_specialchars( sprintf( __( '%1$s Tag: %2$s - Recent Posts' ), bb_get_option( 'name' ), bb_get_tag_name() ) );
			$link = bb_get_tag_link($feed_id);
			$link_self = bb_get_tag_posts_rss_link($feed_id);
			break;
		
		case 'forum-topics':
			if ( !$topics = get_latest_topics( $feed_id ) )
				die();
			
			$posts = array();
			foreach ($topics as $topic) {
				$posts[] = bb_get_first_post($topic->topic_id);
			}
			
			$title = wp_specialchars( sprintf( __( '%1$s Forum: %2$s - Recent Topics' ), bb_get_option( 'name' ), get_forum_name( $feed_id ) ) );
			$link = get_forum_link($feed_id);
			$link_self = bb_get_forum_topics_rss_link($feed_id);
			break;
		
		case 'forum-posts':
			if ( !$posts = get_latest_forum_posts( $feed_id ) )
				die();
			$title = wp_specialchars( sprintf( __( '%1$s Forum: %2$s - Recent Posts' ), bb_get_option( 'name' ), get_forum_name( $feed_id ) ) );
			$link = get_forum_link($feed_id);
			$link_self = bb_get_forum_posts_rss_link($feed_id);
			break;
		
		// Get just the first post from the latest topics
		case 'all-topics':
			if ( !$topics = get_latest_topics() )
				die();
			
			$posts = array();
			foreach ($topics as $topic) {
				$posts[] = bb_get_first_post($topic->topic_id);
			}
			
			$title = wp_specialchars( sprintf( __( '%1$s: Recent Topics' ), bb_get_option( 'name' ) ) );
			$link = bb_get_uri();
			$link_self = bb_get_topics_rss_link();
			break;
		
		// Get latest posts by default
		case 'all-posts':
		default:
			if ( !$posts = get_latest_posts( 35 ) )
				die();
			$title = wp_specialchars( sprintf( __( '%1$s: Recent Posts' ), bb_get_option( 'name' ) ) );
			$link = bb_get_uri();
			$link_self = bb_get_posts_rss_link();
			break;
	}
}

bb_send_304( $posts[0]->post_time );

if (!$description = wp_specialchars( bb_get_option('description') )) {
	$description = $title;
}
$title = apply_filters( 'bb_title_rss', $title, $feed );
$description = apply_filters( 'bb_description_rss', $description, $feed );
$posts = apply_filters( 'bb_posts_rss', $posts, $feed );
$link_self = apply_filters( 'bb_link_self_rss', $link_self, $feed );

bb_load_template( 'rss2.php', array('bb_db_override', 'title', 'description', 'link', 'link_self'), $feed );

?>