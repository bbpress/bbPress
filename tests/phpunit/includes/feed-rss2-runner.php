<?php

// Run an RSS feed in a separate PHP process because the feed exits after output.
putenv( 'WP_TESTS_SKIP_INSTALL=1' );
if ( isset( $argv[2] ) && 'multisite' === $argv[2] ) {
	define( 'WP_TESTS_MULTISITE', true );
}

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/bootstrap.php';

$mode = isset( $argv[1] ) ? $argv[1] : '';
if ( ! in_array( $mode, array( 'topics', 'replies', 'user-topics', 'user-replies', 'user-topics-privileged', 'user-replies-privileged' ), true ) ) {
	exit( 2 );
}

if ( in_array( $mode, array( 'user-topics', 'user-replies', 'user-topics-privileged', 'user-replies-privileged' ), true ) ) {
	$old_permalinks     = get_option( 'permalink_structure' );
	$old_private_forums = get_option( '_bbp_private_forums', null );
	$old_hidden_forums  = get_option( '_bbp_hidden_forums', null );
	$author_id          = 0;
	$other_id           = 0;
	$post_ids           = array();
	register_shutdown_function( function () use ( &$post_ids, &$author_id, &$other_id, $old_permalinks, $old_private_forums, $old_hidden_forums ) {
		global $wpdb;

		foreach ( array_reverse( $post_ids ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( is_int( $author_id ) && $author_id > 0 ) {
			wp_delete_user( $author_id );
		}
		if ( is_int( $other_id ) && $other_id > 0 ) {
			wp_delete_user( $other_id );
		}
		update_option( 'permalink_structure', $old_permalinks );
		foreach ( array( '_bbp_private_forums' => $old_private_forums, '_bbp_hidden_forums' => $old_hidden_forums ) as $option => $value ) {
			if ( null === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
		if ( $post_ids ) {
			$ids = implode( ', ', array_map( 'intval', $post_ids ) );
			if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID IN ({$ids})" ) ) {
				exit( 4 );
			}
		}
	} );

	update_option( 'permalink_structure', '/%postname%/' );
	$author_id = wp_create_user( 'bbp-feed-' . strtolower( wp_generate_password( 8, false ) ), 'test-password' );
	$other_id  = wp_create_user( 'bbp-feed-' . strtolower( wp_generate_password( 8, false ) ), 'test-password' );

	$forum_specs = array(
		'Public'   => array( 'post_status' => bbp_get_public_status_id() ),
		'Private'  => array( 'post_status' => bbp_get_private_status_id() ),
		'Hidden'   => array( 'post_status' => bbp_get_hidden_status_id() ),
		'Password' => array( 'post_status' => bbp_get_public_status_id(), 'post_password' => 'forum-password' ),
	);

	foreach ( $forum_specs as $label => $forum_args ) {
		$forum_id = wp_insert_post( array_merge( array(
			'post_type'  => bbp_get_forum_post_type(),
			'post_title' => $label . ' User Feed Forum',
		), $forum_args ) );
		$post_ids[] = $forum_id;

		$topic_id = wp_insert_post( array(
			'post_type'    => bbp_get_topic_post_type(),
			'post_status'  => bbp_get_public_status_id(),
			'post_parent'  => $forum_id,
			'post_author'  => $author_id,
			'post_title'   => $label . ' User Feed Topic',
			'post_content' => $label . ' user topic body',
		) );
		$post_ids[] = $topic_id;
		update_post_meta( $topic_id, '_bbp_forum_id', $forum_id );
		update_post_meta( $topic_id, '_bbp_last_active_time', current_time( 'mysql', true ) );

		$reply_id = wp_insert_post( array(
			'post_type'    => bbp_get_reply_post_type(),
			'post_status'  => bbp_get_public_status_id(),
			'post_parent'  => $topic_id,
			'post_author'  => $author_id,
			'post_title'   => $label . ' User Feed Reply',
			'post_content' => $label . ' user reply body',
		) );
		$post_ids[] = $reply_id;
		update_post_meta( $reply_id, '_bbp_forum_id', $forum_id );
		update_post_meta( $reply_id, '_bbp_topic_id', $topic_id );

		if ( 'Public' === $label ) {
			$public_forum_id = $forum_id;
			$public_topic_id = $topic_id;
		} elseif ( 'Private' === $label ) {
			$private_forum_id = $forum_id;
			$private_topic_id = $topic_id;
		}
	}

	$topic_id = wp_insert_post( array(
		'post_type'   => bbp_get_topic_post_type(),
		'post_status' => bbp_get_public_status_id(),
		'post_parent' => $private_forum_id,
		'post_author' => $author_id,
		'post_title'  => 'Missing Forum Meta User Feed Topic',
	) );
	$post_ids[] = $topic_id;
	delete_post_meta( $topic_id, '_bbp_forum_id' );

	$reply_id = wp_insert_post( array(
		'post_type'   => bbp_get_reply_post_type(),
		'post_status' => bbp_get_public_status_id(),
		'post_parent' => $private_topic_id,
		'post_author' => $author_id,
		'post_title'  => 'Missing Forum Meta User Feed Reply',
	) );
	$post_ids[] = $reply_id;
	delete_post_meta( $reply_id, '_bbp_forum_id' );
	update_post_meta( $reply_id, '_bbp_topic_id', $private_topic_id );

	$reply_id = wp_insert_post( array(
		'post_type'     => bbp_get_reply_post_type(),
		'post_status'   => bbp_get_public_status_id(),
		'post_parent'   => $public_topic_id,
		'post_author'   => $author_id,
		'post_password' => 'reply-password',
		'post_title'    => 'Protected Reply User Feed Reply',
		'post_content'  => 'Protected Reply user reply body',
	) );
	$post_ids[] = $reply_id;
	update_post_meta( $reply_id, '_bbp_forum_id', $public_forum_id );
	update_post_meta( $reply_id, '_bbp_topic_id', $public_topic_id );

	foreach ( array(
		'Protected' => array( 'post_password' => 'topic-password' ),
		'Pending'   => array( 'post_status' => bbp_get_pending_status_id() ),
		'Other'     => array( 'post_author' => $other_id ),
	) as $label => $topic_args ) {
		$topic_id = wp_insert_post( array_merge( array(
			'post_type'    => bbp_get_topic_post_type(),
			'post_status'  => bbp_get_public_status_id(),
			'post_parent'  => $public_forum_id,
			'post_author'  => $author_id,
			'post_title'   => $label . ' User Feed Topic',
			'post_content' => $label . ' user topic body',
		), $topic_args ) );
		$post_ids[] = $topic_id;
		update_post_meta( $topic_id, '_bbp_forum_id', $public_forum_id );
		update_post_meta( $topic_id, '_bbp_last_active_time', current_time( 'mysql', true ) );

		$reply_id = wp_insert_post( array(
			'post_type'    => bbp_get_reply_post_type(),
			'post_status'  => bbp_get_public_status_id(),
			'post_parent'  => $topic_id,
			'post_author'  => ( 'Other' === $label ) ? $other_id : $author_id,
			'post_title'   => $label . ' User Feed Reply',
			'post_content' => $label . ' user reply body',
		) );
		$post_ids[] = $reply_id;
		update_post_meta( $reply_id, '_bbp_forum_id', $public_forum_id );
		update_post_meta( $reply_id, '_bbp_topic_id', $topic_id );
	}

	$is_topics     = in_array( $mode, array( 'user-topics', 'user-topics-privileged' ), true );
	$is_privileged = in_array( $mode, array( 'user-topics-privileged', 'user-replies-privileged' ), true );
	if ( $is_privileged ) {
		bbp_set_user_role( $author_id, bbp_get_keymaster_role() );
	}
	wp_set_current_user( $is_privileged ? $author_id : 0 );
	$query_vars = array(
		'feed'                    => 'rss2',
		bbp_get_user_rewrite_id() => get_userdata( $author_id )->user_nicename,
	);
	$query_vars[ $is_topics ? bbp_get_user_topics_rewrite_id() : bbp_get_user_replies_rewrite_id() ] = 1;
	bbp_request_feed_trap( $query_vars );
	exit( 3 );
}

$forum_id = wp_insert_post( array(
	'post_type'   => bbp_get_forum_post_type(),
	'post_status' => bbp_get_public_status_id(),
	'post_title'  => 'Feed Test Forum',
) );
$topic_id = wp_insert_post( array(
	'post_type'    => bbp_get_topic_post_type(),
	'post_status'  => bbp_get_public_status_id(),
	'post_parent'  => $forum_id,
	'post_title'   => 'Feed Test Topic',
	'post_content' => 'Feed test topic content',
) );
$reply_id = wp_insert_post( array(
	'post_type'    => bbp_get_reply_post_type(),
	'post_status'  => bbp_get_public_status_id(),
	'post_parent'  => $topic_id,
	'post_title'   => 'Feed Test Reply',
	'post_content' => 'Feed test reply content',
) );
$protected_topic_id = wp_insert_post( array(
	'post_type'     => bbp_get_topic_post_type(),
	'post_status'   => bbp_get_public_status_id(),
	'post_parent'   => $forum_id,
	'post_title'    => 'Protected Feed Topic',
	'post_content'  => 'Protected topic secret content',
	'post_password' => 'secret',
) );
$private_topic_id = wp_insert_post( array(
	'post_type'    => bbp_get_topic_post_type(),
	'post_status'  => bbp_get_private_status_id(),
	'post_parent'  => $forum_id,
	'post_title'   => 'Private Feed Topic',
	'post_content' => 'Private topic secret content',
) );
$private_reply_id = wp_insert_post( array(
	'post_type'    => bbp_get_reply_post_type(),
	'post_status'  => bbp_get_public_status_id(),
	'post_parent'  => $private_topic_id,
	'post_title'   => 'Private Feed Reply',
	'post_content' => 'Private reply secret content',
) );

update_post_meta( $topic_id, '_bbp_forum_id', $forum_id );
update_post_meta( $topic_id, '_bbp_last_active_time', current_time( 'mysql', true ) );
update_post_meta( $reply_id, '_bbp_forum_id', $forum_id );
update_post_meta( $reply_id, '_bbp_topic_id', $topic_id );
update_post_meta( $protected_topic_id, '_bbp_forum_id', $forum_id );
update_post_meta( $protected_topic_id, '_bbp_last_active_time', current_time( 'mysql', true ) );
update_post_meta( $private_topic_id, '_bbp_forum_id', $forum_id );
update_post_meta( $private_reply_id, '_bbp_forum_id', $forum_id );
update_post_meta( $private_reply_id, '_bbp_topic_id', $private_topic_id );

register_shutdown_function( function () use ( $reply_id, $topic_id, $protected_topic_id, $private_topic_id, $private_reply_id, $forum_id ) {
	wp_delete_post( $private_reply_id, true );
	wp_delete_post( $reply_id, true );
	wp_delete_post( $private_topic_id, true );
	wp_delete_post( $protected_topic_id, true );
	wp_delete_post( $topic_id, true );
	wp_delete_post( $forum_id, true );
} );

if ( 'topics' === $mode ) {
	bbp_display_topics_feed_rss2( array( 'post__in' => array( $topic_id, $protected_topic_id ), 'posts_per_page' => 2 ) );
} else {
	bbp_display_replies_feed_rss2( array( 'post__in' => array( $reply_id, $private_reply_id ), 'posts_per_page' => 2 ) );
}
