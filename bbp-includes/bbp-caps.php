<?php

/**
 * bbp_map_meta_caps ()
 *
 * Maps forum/topic/reply caps to built in WordPress caps
 *
 */
function bbp_map_meta_caps ( $caps, $cap, $user_id, $args ) {

	switch ( $cap ) {
		case 'edit_forum' :
		case 'edit_topic' :
		case 'edit_reply' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );

				if ( (int)$user_id == (int)$post->post_author )
					$caps[] = $post_type->cap->edit_posts;
				else
					$caps[] = $post_type->cap->edit_others_posts;
			}

			break;

		case 'delete_forum' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );

				if ( (int)$user_id == (int) $post->post_author )
					$caps[] = $post_type->cap->delete_posts;
				else
					$caps[] = $post_type->cap->delete_others_posts;
			}

			break;

		case 'delete_topic' :
		case 'delete_reply' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );
				$caps[]    = $post_type->cap->delete_others_posts;
			}

			break;
	}

	return apply_filters( 'bbp_map_meta_caps', $caps, $cap, $user_id, $args );
}
add_filter ( 'map_meta_cap', 'bbp_map_meta_caps', 10, 4 );

/**
 * bbp_get_forum_caps ()
 *
 * Return forum caps
 *
 * @return array
 */
function bbp_get_forum_caps () {
	// Forum meta caps
	$caps = array (
		'delete_posts'        => 'delete_forums',
		'delete_others_posts' => 'delete_others_forums'
	);

	return apply_filters( 'bbp_get_forum_caps', $caps );
}

/**
 * bbp_get_topic_caps ()
 *
 * Return topic caps
 *
 * @return array
 */
function bbp_get_topic_caps () {
	// Forum meta caps
	$caps = array (
		'delete_posts'        => 'delete_topics',
		'delete_others_posts' => 'delete_others_topics'
	);

	return apply_filters( 'bbp_get_topic_caps', $caps );
}

/**
 * bbp_get_reply_caps ()
 *
 * Return reply caps
 *
 * @return array
 */
function bbp_get_reply_caps () {
	// Forum meta caps
	$caps = array (
		'edit_posts'          => 'edit_replies',
		'edit_others_posts'   => 'edit_others_replies',
		'publish_posts'       => 'publish_replies',
		'read_private_posts'  => 'read_private_replies',
		'delete_posts'        => 'delete_replies',
		'delete_others_posts' => 'delete_others_replies'
	);

	return apply_filters( 'bbp_get_reply_caps', $caps );
}

/**
 * bbp_get_topic_tag_caps ()
 *
 * Return topic tag caps
 *
 * @return array
 */
function bbp_get_topic_tag_caps () {
	// Forum meta caps
	$caps = array (
		'manage_terms' => 'manage_topic_tags',
		'edit_terms'   => 'edit_topic_tags',
		'delete_terms' => 'delete_topic_tags',
		'assign_terms' => 'assign_topic_tags'
	);

	return apply_filters( 'bbp_get_topic_tag_caps', $caps );
}

?>
