<?php

/**
 * bbp_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
*/
function bbp_head () {
	do_action( 'bbp_head' );
}
add_action( 'wp_head', 'bbp_head' );

/**
 * bbp_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 */
function bbp_footer () {
	do_action( 'bbp_footer' );
}
add_action( 'wp_footer', 'bbp_footer' );

/** Forum Loop Functions ***************************************

/**
 * bbp_has_forums()
 *
 * The main forum loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @param array $args Possible arguments to change returned forums
 * @return object Multidimensional array of forum information
 */
function bbp_has_forums ( $args = '' ) {
	global $bbp_forums_template;
	
	$default = array (
		'post_type'		=> BBP_FORUM_POST_TYPE_ID,
		'post_parent'	=> '0',
		'orderby'		=> 'menu_order'
	);

	$r = wp_parse_args( $args, $default );

	$bbp_forums_template = new WP_Query( $r );

	return apply_filters( 'bbp_has_forums', $bbp_forums_template->have_posts(), &$bbp_forums_template );
}

/**
 * bbp_forums()
 *
 * Whether there are more forums available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @return object Forum information
 */
function bbp_forums () {
	global $bbp_forums_template;
	return $bbp_forums_template->have_posts();
}

/**
 * bbp_the_forum()
 *
 * Loads up the current forum in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @return object Forum information
 */
function bbp_the_forum () {
	global $bbp_forums_template;
	return $bbp_forums_template->the_post();
}

/**
 * bbp_forum_id()
 *
 * Echo id from bbp_forum_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_id()
 */
function bbp_forum_id () {
	echo bbp_get_forum_id();
}
	/**
	 * bbp_get_forum_id()
	 *
	 * Get the id of the user in a forums loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 ** @since bbPress (1.2-r2464)
	 *
	 * @global object $forums_template
	 * @return string Forum id
	 */
	function bbp_get_forum_id () {
		return apply_filters( 'bbp_get_forum_id', get_the_ID() );
	}

/**
 * bbp_forum_permalink ()
 *
 * Output the link to the forum in the forum loop
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_permalink()
 */
function bbp_forum_permalink () {
	echo bbp_get_forum_permalink();
}
	/**
	 * bbp_get_forum_permalink()
	 *
	 * Return the link to the forum in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 ** @since bbPress (1.2-r2464)
	 *
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to forum
	 */
	function bbp_get_forum_permalink () {
		return apply_filters( 'bbp_get_forum_permalink', get_permalink() );
	}

/**
 * bbp_forum_title ()
 *
 * Output the title of the forum in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_title()
 */
function bbp_forum_title () {
	echo bbp_get_forum_title();
}
	/**
	 * bbp_get_forum_title ()
	 *
	 * Return the title of the forum in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 ** @since bbPress (1.2-r2464)
	 *
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @return string Title of forum
	 */
	function bbp_get_forum_title () {
		return apply_filters( 'bbp_get_forum_title', get_the_title() );
	}

/**
 * bbp_forum_last_active ()
 *
 * Output the forums last update date/time (aka freshness)
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @param int $forum_id optional
 * 
 * @uses bbp_get_forum_last_active()
 */
function bbp_forum_last_active ( $forum_id = '' ) {
	echo bbp_get_forum_last_active( $forum_id );
}
	/**
	 * bbp_get_forum_last_active ()
	 *
	 * Return the forums last update date/time (aka freshness)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 ** @since bbPress (1.2-r2464)
	 *
	 * @param int $forum_id optional
	 * 
	 * @return string
	 */
	function bbp_get_forum_last_active ( $forum_id = '' ) {
		if ( !$forum_id )
			$forum_id = bbp_get_forum_id();

		return apply_filters( 'bbp_get_forum_last_active', get_post_meta( $forum_id, 'bbp_forum_last_active' ) );
	}

/**
 * BBP_has_access()
 *
 * Make sure user can perform special tasks
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @uses is_super_admin ()
 * @uses apply_filters
 *
 * @todo bbPress port of existing roles/caps
 * @return bool $has_access
 */
function bbp_has_access () {

	if ( is_super_admin () )
		$has_access = true;
	else
		$has_access = false;

	return apply_filters( 'bbp_has_access', $has_access );
}

/**
 * bbp_forum_topic_count ()
 *
 * Output total topic count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_topic_count()
 * @param int $forum_id Forum ID to check
 */
function bbp_forum_topic_count ( $forum_id = '' ) {
	echo bbp_get_forum_topic_count( $forum_id );
}
	/**
	 * bbp_get_forum_topic_count ()
	 *
	 * Return total topic count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 ** @since bbPress (1.2-r2464)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_forum_id
	 * @uses get_children
	 * @uses apply_filters
	 *
	 * @param int $forum_id Forum ID to check
	 */
	function bbp_get_forum_topic_count ( $forum_id = '' ) {
		if ( !$forum_id )
			$forum_id = bbp_get_forum_id();

		$children = get_children( array( 'post_parent' => $forum_id, 'post_type' => BBP_TOPIC_REPLY_POST_TYPE_ID ) );

		return apply_filters( 'bbp_get_forum_topic_count', count( $children ) );

		//return apply_filters( 'bbp_get_forum_topic_count', (int)get_post_meta( $forum_id, 'bbp_forum_topic_count' ) );
	}

/**
 * bbp_update_forum_topic_count ()
 *
 * Adjust the total topic count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @todo make this not suck
 *
 * @param int $new_topic_count
 * @return int
 */
function bbp_update_forum_topic_count ( $new_topic_count, $forum_id = '' ) {
	if ( !$forum_id )
		$forum_id = bbp_get_forum_id();

	return apply_filters( 'bbp_update_forum_topic_count', (int)update_post_meta( $forum_id, 'bbp_forum_topic_count', $new_post_count ) );
}

/**
 * bbp_forum_post_count ()
 *
 * Output total post count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_post_count()
 * @param int $forum_id
 */
function bbp_forum_post_count ( $forum_id = '' ) {
	echo bbp_get_forum_post_count( $forum_id );
}
	/**
	 * bbp_forum_post_count ()
	 *
	 * Return total post count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 ** @since bbPress (1.2-r2464)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_forum_id()
	 * @uses get_children
	 * @uses apply_filters
	 *
	 * @param int $forum_id
	 */
	function bbp_get_forum_post_count ( $forum_id = '' ) {
		if ( !$forum_id )
			$forum_id = bbp_get_forum_id();

		$children = get_children( array( 'post_parent' => $forum_id, 'post_type' => BBP_TOPIC_REPLY_POST_TYPE_ID ) );

		return apply_filters( 'bbp_get_forum_post_count', count( $children ) );

		//return apply_filters( 'bbp_get_forum_post_count', (int)get_post_meta( $forum_id, 'bbp_forum_post_count' ) );
	}

/**
 * bbp_update_forum_post_count ()
 *
 * Adjust the total post count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 ** @since bbPress (1.2-r2464)
 *
 * @todo make this not suck
 *
 * @uses bbp_get_forum_id(0
 * @uses apply_filters
 * 
 * @param int $new_post_count New post count
 * @param int $forum_id optional Forum ID to update
 *
 * @return int
 */
function bbp_update_forum_post_count ( $new_post_count, $forum_id = '' ) {
	if ( !$forum_id )
		$forum_id = bbp_get_forum_id();

	return apply_filters( 'bbp_update_forum_post_count', (int)update_post_meta( $forum_id, 'bbp_forum_post_count', $new_post_count ) );
}

?>
