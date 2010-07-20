<?php

/**
 * bbp_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
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
 * @since bbPress (1.2-r2464)
 */
function bbp_footer () {
	do_action( 'bbp_footer' );
}
add_action( 'wp_footer', 'bbp_footer' );

/** START Forum Loop Functions ***************************************

/**
 * bbp_has_forums()
 *
 * The main forum loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @param array $args Possible arguments to change returned forums
 * @return object Multidimensional array of forum information
 */
function bbp_has_forums ( $args = '' ) {
	global $bbp_forums_template;
	
	$default = array (
		'post_type'     => BBP_FORUM_POST_TYPE_ID,
		'post_parent'   => '0',
		'orderby'       => 'menu_order'
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
 * @since bbPress (1.2-r2464)
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
 * @since bbPress (1.2-r2464)
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
 * @since bbPress (1.2-r2464)
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
	 * @since bbPress (1.2-r2464)
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
 * @since bbPress (1.2-r2464)
 *
 * @param int $forum_id optional
 * @uses bbp_get_forum_permalink()
 */
function bbp_forum_permalink ( $forum_id = 0 ) {
	echo bbp_get_forum_permalink( $forum_id );
}
	/**
	 * bbp_get_forum_permalink()
	 *
	 * Return the link to the forum in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to forum
	 */
	function bbp_get_forum_permalink ( $forum_id = 0 ) {
		return apply_filters( 'bbp_get_forum_permalink', get_permalink( $forum_id ) );
	}

/**
 * bbp_forum_title ()
 *
 * Output the title of the forum in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @param int $forum_id optional
 * @uses bbp_get_forum_title()
 */
function bbp_forum_title ( $forum_id = 0 ) {
	echo bbp_get_forum_title( $forum_id );
}
	/**
	 * bbp_get_forum_title ()
	 *
	 * Return the title of the forum in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @return string Title of forum
	 *
	 */
	function bbp_get_forum_title ( $forum_id = 0 ) {
		return apply_filters( 'bbp_get_forum_title', get_the_title( $forum_id ) );
	}

/**
 * bbp_forum_last_active ()
 *
 * Output the forums last update date/time (aka freshness)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_last_active()
 * @param int $forum_id optional
 */
function bbp_forum_last_active ( $forum_id = 0 ) {
	echo bbp_get_forum_last_active( $forum_id );
}
	/**
	 * bbp_get_forum_last_active ()
	 *
	 * Return the forums last update date/time (aka freshness)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @return string
	 * @param int $forum_id optional
	 */
	function bbp_get_forum_last_active ( $forum_id = 0 ) {
		if ( !$forum_id )
			$forum_id = bbp_get_forum_id();

		return apply_filters( 'bbp_get_forum_last_active', get_post_meta( $forum_id, 'bbp_forum_last_active', true ) );
	}

/**
 * bbp_forum_topic_count ()
 *
 * Output total topic count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_topic_count()
 * @param int $forum_id optional Forum ID to check
 */
function bbp_forum_topic_count ( $forum_id = 0 ) {
	echo bbp_get_forum_topic_count( $forum_id );
}
	/**
	 * bbp_get_forum_topic_count ()
	 *
	 * Return total topic count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_forum_id
	 * @uses get_children
	 * @uses apply_filters
	 *
	 * @param int $forum_id optional Forum ID to check
	 */
	function bbp_get_forum_topic_count ( $forum_id = 0 ) {
		if ( !$forum_id )
			$forum_id = bbp_get_forum_id();

		$children = get_children( array( 'post_parent' => $forum_id, 'post_type' => BBP_TOPIC_POST_TYPE_ID ) );

		return apply_filters( 'bbp_get_forum_topic_count', count( $children ) );

		//return apply_filters( 'bbp_get_forum_topic_count', (int)get_post_meta( $forum_id, 'bbp_forum_topic_count', true ) );
	}

/**
 * bbp_update_forum_topic_count ()
 *
 * Adjust the total topic count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @todo make this not suck
 *
 * @param int $new_topic_count
 * @param int $forum_id optional
 * @return int
 */
function bbp_update_forum_topic_count ( $new_topic_count, $forum_id = 0 ) {
	if ( !$forum_id )
		$forum_id = bbp_get_forum_id();

	return apply_filters( 'bbp_update_forum_topic_count', (int)update_post_meta( $forum_id, 'bbp_forum_topic_count', $new_topic_count ) );
}

/**
 * bbp_forum_topic_reply_count ()
 *
 * Output total post count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @uses bbp_get_forum_topic_reply_count()
 * @param int $forum_id optional
 */
function bbp_forum_topic_reply_count ( $forum_id = 0 ) {
	echo bbp_get_forum_topic_reply_count( $forum_id );
}
	/**
	 * bbp_forum_topic_reply_count ()
	 *
	 * Return total post count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2464)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_forum_id()
	 * @uses get_children
	 * @uses apply_filters
	 *
	 * @param int $forum_id optional
	 */
	function bbp_get_forum_topic_reply_count ( $forum_id = 0 ) {
		if ( !$forum_id )
			$forum_id = bbp_get_forum_id();

		$children = get_children( array( 'post_parent' => $forum_id, 'post_type' => BBP_TOPIC_REPLY_POST_TYPE_ID ) );

		return apply_filters( 'bbp_get_forum_topic_reply_count', count( $children ) );

		//return apply_filters( 'bbp_get_forum_topic_reply_count', (int)get_post_meta( $forum_id, 'bbp_forum_topic_reply_count', true ) );
	}

/**
 * bbp_update_forum_topic_reply_count ()
 *
 * Adjust the total post count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @todo make this not suck
 *
 * @uses bbp_get_forum_id(0
 * @uses apply_filters
 * 
 * @param int $new_topic_reply_count New post count
 * @param int $forum_id optional
 *
 * @return int
 */
function bbp_update_forum_topic_reply_count ( $new_topic_reply_count, $forum_id = 0 ) {
	if ( !$forum_id )
		$forum_id = bbp_get_forum_id();

	return apply_filters( 'bbp_update_forum_topic_reply_count', (int)update_post_meta( $forum_id, 'bbp_forum_topic_reply_count', $new_topic_reply_count ) );
}

/** END Forum Loop Functions ***************************************/

/** START Topic Loop Functions *************************************/

/**
 * bbp_has_topics()
 *
 * The main topic loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @global WP_Query $bbp_topics_template
 * @param array $args Possible arguments to change returned topics
 * @return object Multidimensional array of topic information
 */
function bbp_has_topics ( $args = '' ) {
	global $bbp_topics_template;

	$default = array (
		'post_type'     => BBP_TOPIC_POST_TYPE_ID,
		'post_parent'   => '0',
		'orderby'       => 'menu_order'
	);

	$r = wp_parse_args( $args, $default );

	$bbp_topics_template = new WP_Query( $r );

	return apply_filters( 'bbp_has_topics', $bbp_topics_template->have_posts(), &$bbp_topics_template );
}

/**
 * bbp_topics()
 *
 * Whether there are more topics available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @global WP_Query $bbp_topics_template
 * @return object Forum information
 */
function bbp_topics () {
	global $bbp_topics_template;
	return $bbp_topics_template->have_posts();
}

/**
 * bbp_the_topic()
 *
 * Loads up the current topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @global WP_Query $bbp_topics_template
 * @return object Forum information
 */
function bbp_the_topic () {
	global $bbp_topics_template;
	return $bbp_topics_template->the_post();
}

/**
 * bbp_topic_id()
 *
 * Echo id from bbp_topic_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @uses bbp_get_topic_id()
 */
function bbp_topic_id () {
	echo bbp_get_topic_id();
}
	/**
	 * bbp_get_topic_id()
	 *
	 * Get the id of the user in a topics loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @global object $topics_template
	 * @return string Forum id
	 */
	function bbp_get_topic_id () {
		return apply_filters( 'bbp_get_topic_id', get_the_ID() );
	}

/**
 * bbp_topic_permalink ()
 *
 * Output the link to the topic in the topic loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @uses bbp_get_topic_permalink()
 * @param int $topic_id optional
 */
function bbp_topic_permalink ( $topic_id = 0 ) {
	echo bbp_get_topic_permalink( $topic_id );
}
	/**
	 * bbp_get_topic_permalink()
	 *
	 * Return the link to the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @uses apply_filters
	 * @uses get_permalink
	 * @param int $topic_id optional
	 *
	 * @return string Permanent link to topic
	 */
	function bbp_get_topic_permalink ( $topic_id = 0 ) {
		return apply_filters( 'bbp_get_topic_permalink', get_permalink( $topic_id ) );
	}

/**
 * bbp_topic_title ()
 *
 * Output the title of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_title()
 */
function bbp_topic_title ( $topic_id = 0 ) {
	echo bbp_get_topic_title( $topic_id );
}
	/**
	 * bbp_get_topic_title ()
	 *
	 * Return the title of the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 * 
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @param int $topic_id optional
	 *
	 * @return string Title of topic
	 */
	function bbp_get_topic_title ( $topic_id = 0 ) {
		return apply_filters( 'bbp_get_topic_title', get_the_title( $topic_id ) );
	}

/**
 * bbp_topic_forum ()
 *
 * Output the forum a topic belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_forum()
 */
function bbp_topic_forum ( $topic_id = '' ) {
	echo bbp_get_topic_forum( $topic_id );
}
	/**
	 * bbp_get_topic_forum ()
	 *
	 * Return the forum a topic belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @param int $topic_id optional
	 *
	 * @return string
	 */
	function bbp_get_topic_forum ( $topic_id = '' ) {
		$forum_id = bbp_get_topic_forum_id( $topic_id );
		return apply_filters( 'bbp_get_topic_forum', bbp_forum_title( $forum_id ) );
	}

	/**
	 * bbp_topic_forum_id ()
	 *
	 * Output the forum ID a topic belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2491)
	 *
	 * @param int $topic_id optional
	 *
	 * @uses bbp_get_topic_forum()
	 */
	function bbp_topic_forum_id ( $topic_id = '' ) {
		echo bbp_get_topic_forum_id( $topic_id );
	}
		/**
		 * bbp_get_topic_forum_id ()
		 *
		 * Return the forum ID a topic belongs to
		 *
		 * @package bbPress
		 * @subpackage Template Tags
		 * @since bbPress (1.2-r2491)
		 *
		 * @param int $topic_id optional
		 *
		 * @return string
		 */
		function bbp_get_topic_forum_id ( $topic_id = '' ) {
			if ( !$topic_id )
				$topic_id = bbp_get_topic_id();

			$forum_id = get_post_field( 'post_parent', $bbp_topics_template );
			return apply_filters( 'bbp_get_topic_forum_id', $forum_id );
		}

/**
 * bbp_topic_last_active ()
 *
 * Output the topics last update date/time (aka freshness)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_last_active()
 */
function bbp_topic_last_active ( $topic_id = '' ) {
	echo bbp_get_topic_last_active( $topic_id );
}
	/**
	 * bbp_get_topic_last_active ()
	 *
	 * Return the topics last update date/time (aka freshness)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @param int $topic_id optional
	 *
	 * @return string
	 */
	function bbp_get_topic_last_active ( $topic_id = '' ) {
		if ( !$topic_id )
			$topic_id = bbp_get_topic_id();

		return apply_filters( 'bbp_get_topic_last_active', get_post_meta( $topic_id, 'bbp_topic_last_active', true ) );
	}

/**
 * bbp_topic_reply_count ()
 *
 * Output total post count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @uses bbp_get_topic_reply_count()
 * @param int $topic_id
 */
function bbp_topic_reply_count ( $topic_id = '' ) {
	echo bbp_get_topic_reply_count( $topic_id );
}
	/**
	 * bbp_topic_reply_count ()
	 *
	 * Return total post count of a topic
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (1.2-r2485)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_topic_id()
	 * @uses get_children
	 * @uses apply_filters
	 *
	 * @param int $topic_id
	 */
	function bbp_get_topic_reply_count ( $topic_id = '' ) {
		if ( !$topic_id )
			$topic_id = bbp_get_topic_id();

		$children = get_children( array( 'post_parent' => $topic_id, 'post_type' => BBP_TOPIC_REPLY_POST_TYPE_ID ) );

		return apply_filters( 'bbp_get_topic_reply_count', count( $children ) );

		//return apply_filters( 'bbp_get_topic_topic_reply_count', (int)get_post_meta( $topic_id, 'bbp_topic_topic_reply_count', true ) );
	}

/**
 * bbp_update_topic_reply_count ()
 *
 * Adjust the total post count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2485)
 *
 * @todo make this not suck
 *
 * @uses bbp_get_topic_id(0
 * @uses apply_filters
 *
 * @param int $new_topic_reply_count New post count
 * @param int $topic_id optional Forum ID to update
 *
 * @return int
 */
function bbp_update_topic_reply_count ( $new_topic_reply_count, $topic_id = '' ) {
	if ( !$topic_id )
		$topic_id = bbp_get_topic_id();

	return apply_filters( 'bbp_update_topic_reply_count', (int)update_post_meta( $topic_id, 'bbp_topic_reply_count', $new_topic_reply_count ) );
}

/** END Topic Loop Functions *************************************/

?>
