<?php
require('./bb-load.php');
require_once( BBPATH . BBINC . 'feed-functions.php');

// Determine the type of feed and the id of the object
if ( isset($_GET['view']) || get_path() == 'view' ) {
	
	// View
	$feed = 'view';
	$feed_id = isset($_GET['view']) ? $_GET['view'] : get_path(2);
	
} elseif ( isset($_GET['topic']) || get_path() == 'topic' ) {
	
	// Topic
	$feed = 'topic';
	$feed_id = isset($_GET['topic']) ? $_GET['topic'] : get_path(2);
	
} elseif ( isset($_GET['profile']) || get_path() == 'profile' ) {
	
	// Profile
	$feed = 'profile';
	$feed_id = isset($_GET['profile']) ? $_GET['profile'] : get_path(2);
	
} elseif ( isset($_GET['tag']) || get_path() == 'tag' ) {
	
	// Tag
	$feed = 'tag';
	$feed_id = isset($_GET['tag']) ? $_GET['tag'] : get_path(2);
	
} elseif ( isset($_GET['forum']) || get_path() == 'forum' ) {
	
	if ( isset($_GET['topics']) || get_path(3) == 'topics' ) {
		// Forum recent topics
		$feed = 'forum-topics';
	} else {
		// Forum recent posts
		$feed = 'forum-posts';
	}
	$feed_id = isset($_GET['forum']) ? $_GET['forum'] : get_path(2);
	
} elseif ( isset($_GET['topics']) || get_path() == 'topics' ) {
	
	// Recent topics
	$feed = 'all-topics';
	
} else {
	
	// Recent posts
	$feed = 'all-posts';
	
}

// Initialise the override variable
$bb_db_override = false;
do_action( 'bb_rss.php_pre_db', '' );

if ( !$bb_db_override ) {
	
	// Get the posts and the title for the given feed
	switch ($feed) {
		case 'view':
			if ( !isset($bb_views[$feed_id]) )
				die();
			if ( !$bb_views[$feed_id]['feed'] )
				die();
			if ( !$topics_object = new BB_Query( 'topic', $bb_views[$feed_id]['query'], "bb_view_$view" ) )
				die();
			
			$topics = $topics_object->results;
			if ( !$topics || !is_array($topics) )
				die();
			
			$posts = array();
			foreach ($topics as $topic) {
				$posts[] = bb_get_first_post($topic->topic_id);
			}
			
			$title = $bb_views[$feed_id]['title'];
			break;
		
		case 'topic':
			if ( !$topic = get_topic ( $feed_id ) )
				die();
			if ( !$posts = get_thread( $feed_id, 0, 1 ) )
				die();
			$title = wp_specialchars( bb_get_option( 'name' ) . ' ' . __('Topic') . ': ' . get_topic_title() );
			break;
		
		case 'profile':
			if ( !$user = bb_get_user( $feed_id ) )
				if ( !$user = bb_get_user_by_name( $feed_id ) )
					die();
			if ( !$posts = get_user_favorites( $user->ID ) )
				die();
			$title = wp_specialchars( bb_get_option( 'name' ) . ' ' . __('User Favorites') . ': ' . $user->user_login );
			break;
		
		case 'tag':
			if ( !$tag = bb_get_tag_by_name( $feed_id ) )
				die();
			if ( !$posts = get_tagged_topic_posts( $tag->tag_id, 0 ) )
				die();
			$title = wp_specialchars( bb_get_option( 'name' ) . ' ' . __('Tag') . ': ' . bb_get_tag_name() );
			break;
		
		case 'forum-topics':
			if ( !$topics = get_latest_topics( $feed_id ) )
				die();
			
			$posts = array();
			foreach ($topics as $topic) {
				$posts[] = bb_get_first_post($topic->topic_id);
			}
			
			$title = wp_specialchars( bb_get_option( 'name' ) ) . ': ' . __('Forum') . ': ' . get_forum_name( $feed_id ) . ' - ' . __('Recent Topics');
			break;
		
		case 'forum-posts':
			if ( !$posts = get_latest_forum_posts( $feed_id ) )
				die();
			$title = wp_specialchars( bb_get_option( 'name' ) ) . ': ' . __('Forum') . ': ' . get_forum_name( $feed_id ) . ' - ' . __('Recent Posts');
			break;
		
		// Get just the first post from the latest topics
		case 'all-topics':
			if ( !$topics = get_latest_topics() )
				die();
			
			$posts = array();
			foreach ($topics as $topic) {
				$posts[] = bb_get_first_post($topic->topic_id);
			}
			
			$title = wp_specialchars( bb_get_option( 'name' ) ) . ': ' . __('Recent Topics');
			break;
		
		// Get latest posts by default
		case 'all-posts':
		default:
			if ( !$posts = get_latest_posts( 35 ) )
				die();
			$title = wp_specialchars( bb_get_option( 'name' ) ) . ': ' . __('Recent Posts');
			break;
	}
}

do_action( 'bb_rss.php', '' );

bb_send_304( $posts[0]->post_time );

$title = apply_filters( 'bb_title_rss', $title );

bb_load_template( 'rss2.php', array('bb_db_override', 'title') );

?>