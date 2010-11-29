<?php

/** START - WordPress Add-on Actions ******************************************/

/**
 * bbp_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
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
 * @since bbPress (r2464)
 */
function bbp_footer () {
	do_action( 'bbp_footer' );
}
add_action( 'wp_footer', 'bbp_footer' );

/** END - WordPress Add-on Actions ********************************************/

/** START - Forum Loop Functions **********************************************/

/**
 * bbp_has_forums ()
 *
 * The main forum loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @param array $args Possible arguments to change returned forums
 * @return object Multidimensional array of forum information
 */
function bbp_has_forums ( $args = '' ) {
	global $wp_query, $bbp_forums_template, $bbp;

	$default = array (
		'post_type'      => $bbp->forum_id,
		'post_parent'    => bbp_get_forum_id(),
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC'
	);

	$r = wp_parse_args( $args, $default );

	$bbp_forums_template = new WP_Query( $r );

	return apply_filters( 'bbp_has_forums', $bbp_forums_template->have_posts(), $bbp_forums_template );
}

/**
 * bbp_forums ()
 *
 * Whether there are more forums available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @return object Forum information
 */
function bbp_forums () {
	global $bbp_forums_template;
	return $bbp_forums_template->have_posts();
}

/**
 * bbp_the_forum ()
 *
 * Loads up the current forum in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @global WP_Query $bbp_forums_template
 * @return object Forum information
 */
function bbp_the_forum () {
	global $bbp_forums_template;
	return $bbp_forums_template->the_post();
}

/** FORUM *********************************************************************/

/**
 * bbp_forum_id ()
 *
 * Output id from bbp_forum_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_id()
 */
function bbp_forum_id () {
	echo bbp_get_forum_id();
}
	/**
	 * bbp_get_forum_id ()
	 *
	 * Return the forum ID
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @param $forum_id Use to check emptiness
	 * @global object $forums_template
	 * @return string Forum id
	 */
	function bbp_get_forum_id ( $forum_id = 0 ) {
		global $bbp_forums_template, $wp_query, $bbp;

		// Easy empty checking
		if ( !empty( $forum_id ) && is_numeric( $forum_id ) )
			$bbp_forum_id = $forum_id;

		// Currently inside a forum loop
		elseif ( !empty( $bbp_forums_template->in_the_loop ) && isset( $bbp_forums_template->post->ID ) )
			$bbp_forum_id = $bbp_forums_template->post->ID;

		// Currently viewing a forum
		elseif ( bbp_is_forum() && isset( $wp_query->post->ID ) )
			$bbp_forum_id = $wp_query->post->ID;

		// Currently viewing a topic
		elseif ( bbp_is_topic() )
			$bbp_forum_id = bbp_get_topic_forum_id();

		// Fallback
		else
			$bbp_forum_id = 0;

		// Set global
		$bbp->current_forum_id = $bbp_forum_id;

		return apply_filters( 'bbp_get_forum_id', (int)$bbp_forum_id );
	}

/**
 * bbp_forum_permalink ()
 *
 * Output the link to the forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @param int $forum_id optional
 * @uses bbp_get_forum_permalink()
 */
function bbp_forum_permalink ( $forum_id = 0 ) {
	echo bbp_get_forum_permalink( $forum_id );
}
	/**
	 * bbp_get_forum_permalink ()
	 *
	 * Return the link to the forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to forum
	 */
	function bbp_get_forum_permalink ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_permalink', get_permalink( $forum_id ) );
	}

/**
 * bbp_forum_title ()
 *
 * Output the title of the forum in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
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
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @return string Title of forum
	 *
	 */
	function bbp_get_forum_title ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		return apply_filters( 'bbp_get_forum_title', get_the_title( $forum_id ) );
	}

/**
 * bbp_forum_last_active ()
 *
 * Output the forums last update date/time (aka freshness)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
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
	 * @since bbPress (r2464)
	 *
	 * @return string
	 * @param int $forum_id optional
	 */
	function bbp_get_forum_last_active ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_active', bbp_get_time_since( bbp_get_modified_time( $forum_id ) ) );
	}

/**
 * bbp_get_forum_parent ()
 *
 * Return ID of forum parent, if exists
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 * @return int
 */
function bbp_get_forum_parent ( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	return apply_filters( 'bbp_get_forum_parent', (int)get_post_field( 'post_parent', $forum_id ) );
}

/**
 * bbp_get_forum_ancestors ()
 *
 * Return array of parent forums
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 * @return array
 */
function bbp_get_forum_ancestors ( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( $forum = get_post( $forum_id ) ) {
		$ancestors = array();
		while ( 0 !== $forum->post_parent ) {
			$ancestors[] = $forum->post_parent;
			$forum       = get_post( $forum->post_parent );
		}
	}

	return apply_filters( 'bbp_get_forum_ancestors', $ancestors, $forum_id );
}

/**
 * bbp_forum_has_sub_forums ()
 *
 * Return if forum has sub forums
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 * @return false if none, array of subs if yes
 */
function bbp_forum_has_sub_forums( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$has_subs = false;

	if ( !empty( $forum_id ) )
		$has_subs = bbp_get_sub_forums( $forum_id );

	return apply_filters( 'bbp_forum_has_sub_forums', $has_subs );
}

/** FORUM LAST TOPIC **********************************************************/

/**
 * bbp_forum_last_topic_id ()
 *
 * Output the forums last topic id
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_last_active()
 * @param int $forum_id optional
 */
function bbp_forum_last_topic_id ( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_id( $forum_id );
}
	/**
	 * bbp_get_forum_last_topic_id ()
	 *
	 * Return the forums last topic
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @return string
	 * @param int $forum_id optional
	 */
	function bbp_get_forum_last_topic_id ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topic_id = get_post_meta( $forum_id, '_bbp_forum_last_topic_id', true );

		if ( '' === $topic_id )
			$topic_id = bbp_update_forum_last_topic_id( $forum_id );

		return apply_filters( 'bbp_get_forum_last_topic_id', $topic_id );
	}

/**
 * bbp_update_forum_last_topic_id ()
 *
 * Update the forum last topic id
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @todo everything
 * @param int $forum_id
 */
function bbp_update_forum_last_topic_id ( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
}

/**
 * bbp_forum_last_topic_title ()
 *
 * Output the title of the last topic inside a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 */
function bbp_forum_last_topic_title ( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_title( $forum_id );
}
	/**
	 * bbp_get_forum_last_topic_title ()
	 *
	 * Return the title of the last topic inside a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id
	 * @return string
	 */
	function bbp_get_forum_last_topic_title( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_topic_title', bbp_get_topic_title( bbp_get_forum_last_topic_id( $forum_id ) ) );
	}

/**
 * bbp_forum_last_topic_permalink ()
 *
 * Output the link to the last topic in a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @param int $forum_id optional
 * @uses bbp_get_forum_permalink()
 */
function bbp_forum_last_topic_permalink ( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_permalink( $forum_id );
}
	/**
	 * bbp_get_forum_last_topic_permalink ()
	 *
	 * Return the link to the last topic in a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to topic
	 */
	function bbp_get_forum_last_topic_permalink ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_topic_permalink', bbp_get_topic_permalink( bbp_get_forum_last_topic_id( $forum_id ) ) );
	}

/** FORUM LAST REPLY **********************************************************/

/**
 * bbp_forum_last_reply_id ()
 *
 * Output the forums last reply id
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_last_reply_id()
 * @param int $forum_id optional
 */
function bbp_forum_last_reply_id ( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_id( $forum_id );
}
	/**
	 * bbp_get_forum_last_reply_id ()
	 *
	 * Return the forums last reply id
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @return string
	 * @param int $forum_id optional
	 */
	function bbp_get_forum_last_reply_id ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$reply_id = get_post_meta( $forum_id, '_bbp_forum_last_reply_id', true );

		if ( '' === $reply_id )
			$reply_id = bbp_update_forum_last_reply_id( $forum_id );

		return apply_filters( 'bbp_get_forum_last_reply_id', $reply_id );
	}

/**
 * bbp_update_forum_last_reply_id ()
 *
 * Update the forum last reply id
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @todo everything
 * @param int $forum_id
 */
function bbp_update_forum_last_reply_id ( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
}

/**
 * bbp_forum_last_reply_title ()
 *
 * Output the title of the last reply inside a forum
 *
 * @param int $forum_id
 */
function bbp_forum_last_reply_title ( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_title( $forum_id );
}
	/**
	 * bbp_get_forum_last_reply_title ()
	 *
	 * Return the title of the last reply inside a forum
	 *
	 * @param int $forum_id
	 * @return string
	 */
	function bbp_get_forum_last_reply_title( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_topic_title', bbp_get_reply_title( bbp_get_forum_last_reply_id( $forum_id ) ) );
	}

/**
 * bbp_forum_last_reply_permalink ()
 *
 * Output the link to the last reply in a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @param int $forum_id optional
 * @uses bbp_get_forum_permalink()
 */
function bbp_forum_last_reply_permalink ( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_permalink( $forum_id );
}
	/**
	 * bbp_get_forum_last_reply_permalink ()
	 *
	 * Return the link to the last reply in a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to topic
	 */
	function bbp_get_forum_last_reply_permalink ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_reply_permalink', bbp_get_reply_permalink( bbp_get_forum_last_reply_id( $forum_id ) ) );
	}

/**
 * bbp_forum_freshness_link ()
 *
 * Output link to the most recent activity inside a forum, complete with
 * link attributes and content.
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 */
function bbp_forum_freshness_link ( $forum_id = 0) {
	echo bbp_get_forum_freshness_link( $forum_id );
}
	/**
	 * bbp_get_forum_freshness_link ()
	 *
	 * Returns link to the most recent activity inside a forum, complete with
	 * link attributes and content.
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id
	 */
	function bbp_get_forum_freshness_link ( $forum_id = 0 ) {
		$forum_id   = bbp_get_forum_id( $forum_id );
		$link_url   = bbp_get_forum_last_reply_permalink( $forum_id );
		$title      = bbp_get_forum_last_reply_title( $forum_id );
		$time_since = bbp_get_forum_last_active( $forum_id );
		$anchor     = '<a href="' . $link_url . '" title="' . esc_attr( $title ) . '">' . $time_since . '</a>';

		return apply_filters( 'bbp_get_forum_freshness_link', $anchor );
	}

/**
 * bbp_get_forum_last_topic_author_id ()
 *
 * Return the author ID of the last topic of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 */
function bbp_get_forum_last_topic_author_id ( $forum_id = 0 ) {
	$forum_id  = bbp_get_forum_id( $forum_id );
	$author_id = get_post_field( 'post_author', bbp_get_forum_last_topic_id( $forum_id ) );
	return apply_filters( 'bbp_get_forum_last_topic_author', $author_id );
}

/**
 * bbp_forum_last_topic_author_link ()
 *
 * Output link to author of last topic of forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 */
function bbp_forum_last_topic_author_link ( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_author_link( $forum_id );
}
	/**
	 * bbp_get_forum_last_topic_author_link ()
	 *
	 * Return link to author of last topic of forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id
	 * @return string
	 */
	function bbp_get_forum_last_topic_author_link ( $forum_id = 0 ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$author_id   = bbp_get_forum_last_topic_author_id( $forum_id );
		$name        = get_the_author_meta( 'display_name', $author_id );
		$author_link = '<a href="' . get_author_posts_url( $author_id ) . '" title="' . esc_attr( $name ) . '">' . $name . '</a>';
		return apply_filters( 'bbp_get_forum_last_topic_author_link', $author_link );
	}

/**
 * bbp_forum_last_reply_author_id ()
 *
 * Output author ID of last reply of forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 */
function bbp_forum_last_reply_author_id ( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_author_id( $forum_id );
}
	/**
	 * bbp_get_forum_last_reply_author_id ()
	 *
	 * Return author ID of last reply of forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id
	 */
	function bbp_get_forum_last_reply_author_id ( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$author_id = get_post_field( 'post_author', bbp_get_forum_last_reply_id( $forum_id ) );
		return apply_filters( 'bbp_get_forum_last_reply_author', $author_id );
	}

/**
 * bbp_forum_last_reply_author_link ()
 *
 * Output link to author of last reply of forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $forum_id
 */
function bbp_forum_last_reply_author_link ( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_author_link( $forum_id );
}
	/**
	 * bbp_get_forum_last_reply_author_link ()
	 *
	 * Return link to author of last reply of forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id
	 * @return string
	 */
	function bbp_get_forum_last_reply_author_link ( $forum_id = 0 ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$author_id   = bbp_get_forum_last_reply_author_id( $forum_id );
		$name        = get_the_author_meta( 'display_name', $author_id );
		$author_link = '<a href="' . get_author_posts_url( $author_id ) . '" title="' . esc_attr( $name ) . '">' . $name . '</a>';
		return apply_filters( 'bbp_get_forum_last_reply_author_link', $author_link );
	}

/** FORUM COUNTS **************************************************************/

/**
 * bbp_forum_subforum_count ()
 *
 * Output total sub-forum count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_subforum_count()
 * @param int $forum_id optional Forum ID to check
 */
function bbp_forum_subforum_count ( $forum_id = 0 ) {
	echo bbp_get_forum_subforum_count( $forum_id );
}
	/**
	 * bbp_get_forum_subforum_count ()
	 *
	 * Return total sub-forum count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @uses bbp_get_forum_id
	 * @uses get_pages
	 * @uses apply_filters
	 *
	 * @param int $forum_id optional Forum ID to check
	 */
	function bbp_get_forum_subforum_count ( $forum_id = 0 ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$forum_count = get_post_meta( $forum_id, '_bbp_forum_subforum_count', true );

		if ( '' === $forum_count )
			$forum_count = bbp_update_forum_subforum_count( $forum_id );

		return apply_filters( 'bbp_get_forum_subforum_count', $forum_count );
	}

/**
 * bbp_update_forum_subforum_count ()
 *
 * Update the forum sub-forum count
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @todo everything
 * @param int $forum_id
 */
function bbp_update_forum_subforum_count ( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
}

/**
 * bbp_forum_topic_count ()
 *
 * Output total topic count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
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
	 * @since bbPress (r2464)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_forum_id
	 * @uses get_pages
	 * @uses apply_filters
	 *
	 * @param int $forum_id optional Forum ID to check
	 */
	function bbp_get_forum_topic_count ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topics   = get_post_meta( $forum_id, '_bbp_forum_topic_count', true );

		if ( '' === $topics )
			$topics = bbp_update_forum_topic_count( $forum_id );

		return apply_filters( 'bbp_get_forum_topic_count', $topics );
	}

/**
 * bbp_update_forum_topic_count ()
 *
 * Adjust the total topic count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @param int $forum_id optional
 * @return int
 */
function bbp_update_forum_topic_count ( $forum_id = 0 ) {
	global $wpdb, $bbp;

	$forum_id = bbp_get_forum_id( $forum_id );

	// If it's a reply, then get the parent (topic id)
	if ( $bbp->topic_id == get_post_field( 'post_type', $forum_id ) )
		$forum_id = get_post_field( 'post_parent', $forum_id );

	// Get topics count
	$topics = count( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->topic_id . "';", $forum_id ) ) );

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_topic_count', (int)$topics );

	return apply_filters( 'bbp_update_forum_topic_count', (int)$topics );
}

/**
 * bbp_forum_reply_count ()
 *
 * Output total reply count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_topic_reply_count()
 * @param int $forum_id optional
 */
function bbp_forum_reply_count ( $forum_id = 0 ) {
	echo bbp_get_forum_reply_count( $forum_id );
}
	/**
	 * bbp_forum_reply_count ()
	 *
	 * Return total post count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @todo stash and cache (see commented out code)
	 *
	 * @uses bbp_get_forum_id()
	 * @uses get_pages
	 * @uses apply_filters
	 *
	 * @param int $forum_id optional
	 */
	function bbp_get_forum_reply_count ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$replies  = get_post_meta( $forum_id, '_bbp_forum_reply_count', true );

		if ( '' === $replies )
			$replies = bbp_update_forum_reply_count( $forum_id );

		return apply_filters( 'bbp_get_forum_reply_count', (int)$replies );
	}

/**
 * bbp_update_forum_reply_count ()
 *
 * Adjust the total post count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_id(0
 * @uses apply_filters
 *
 * @param int $forum_id optional
 *
 * @return int
 */
function bbp_update_forum_reply_count ( $forum_id = 0 ) {
	global $wpdb, $bbp;

	$forum_id = bbp_get_forum_id( $forum_id );

	// If it's a reply, then get the parent (topic id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $forum_id ) )
		$forum_id = get_post_field( 'post_parent', $forum_id );

	// There should always be at least 1 voice
	$replies = count( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "';", $forum_id ) ) );

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_reply_count', (int)$replies );

	return apply_filters( 'bbp_update_forum_reply_count', (int)$replies );
}

/**
 * bbp_forum_voice_count ()
 *
 * Output total voice count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2567)
 *
 * @uses bbp_get_forum_voice_count()
 * @uses apply_filters
 *
 * @param int $forum_id
 */
function bbp_forum_voice_count ( $forum_id = 0 ) {
	echo bbp_get_forum_voice_count( $forum_id );
}
	/**
	 * bbp_get_forum_voice_count ()
	 *
	 * Return total voice count of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2567)
	 *
	 * @uses bbp_get_forum_id()
	 * @uses apply_filters
	 *
	 * @param int $forum_id
	 *
	 * @return int Voice count of the forum
	 */
	function bbp_get_forum_voice_count ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$voices   = get_post_meta( $forum_id, '_bbp_forum_voice_count', true );

		if ( '' === $voices )
			$voices = bbp_update_forum_voice_count( $forum_id );

		return apply_filters( 'bbp_get_forum_voice_count', (int)$voices, $forum_id );
	}

/**
 * bbp_update_forum_voice_count ()
 *
 * Adjust the total voice count of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2567)
 *
 * @uses bbp_get_forum_id()
 * @uses wpdb
 * @uses apply_filters
 *
 * @todo cache
 *
 * @param int $forum_id optional Topic ID to update
 *
 * @return bool false on failure, voice count on success
 */
function bbp_update_forum_voice_count ( $forum_id = 0 ) {
	global $wpdb, $bbp;

	$forum_id = bbp_get_forum_id( $forum_id );

	// If it is not a forum or reply, then we don't need it
	if ( !in_array( get_post_field( 'post_type', $forum_id ), array( $bbp->forum_id, $bbp->reply_id ) ) )
		return false;

	// If it's a reply, then get the parent (forum id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $forum_id ) )
		$forum_id = get_post_field( 'post_parent', $forum_id );

	// There should always be at least 1 voice
	if ( !$voices = count( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE ( post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "' ) OR ( ID = %d AND post_type = '" . $bbp->forum_id . "' );", $forum_id, $forum_id ) ) ) )
		$voices = 1;

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_voice_count', (int)$voices );

	return apply_filters( 'bbp_update_forum_voice_count', (int)$voices );
}

/** END - Forum Loop Functions ************************************************/

/** START - Topic Loop Functions **********************************************/

/**
 * bbp_has_topics()
 *
 * The main topic loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2485)
 *
 * @global WP_Query $bbp_topics_template
 * @param array $args Possible arguments to change returned topics
 * @return object Multidimensional array of topic information
 */
function bbp_has_topics ( $args = '' ) {
	global $wp_rewrite, $bbp_topics_template, $bbp;

	$default = array (
		// Narrow query down to bbPress topics
		'post_type'        => $bbp->topic_id,

		// Forum ID
		'post_parent'      => isset( $_REQUEST['forum_id'] ) ? $_REQUEST['forum_id'] : bbp_get_forum_id(),

		//'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'          => isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'date',

		// 'ASC', 'DESC'
		'order'            => isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'DESC',

		// @todo replace 15 with setting
		'posts_per_page'   => isset( $_REQUEST['posts'] ) ? $_REQUEST['posts'] : 15,

		// Page Number
		'paged'            => bbp_get_paged(),

		// Topic Search
		's'                => empty( $_REQUEST['ts'] ) ? '' : $_REQUEST['ts'],
	);

	// Don't pass post_parent if forum_id is empty or 0
	if ( empty( $default['post_parent'] ) ) {
		unset( $default['post_parent'] );
		$post_parent = get_the_ID();
	}

	// Set up topic variables
	$bbp_t = wp_parse_args( $args, $default );
	$r     = extract( $bbp_t );

	// Call the query
	$bbp_topics_template = new WP_Query( $bbp_t );

	if ( -1 == $posts_per_page )
		$posts_per_page = $bbp_topics_template->post_count;

	// Add pagination values to query object
	$bbp_topics_template->posts_per_page = $posts_per_page;
	$bbp_topics_template->paged          = $paged;

	// Only add pagination if query returned results
	if ( ( (int)$bbp_topics_template->post_count || (int)$bbp_topics_template->found_posts ) && (int)$bbp_topics_template->posts_per_page ) {

		// If pretty permalinks are enabled, make our pagination pretty
		if ( $wp_rewrite->using_permalinks() )
			$base = user_trailingslashit( trailingslashit( get_permalink( $post_parent ) ) . 'page/%#%/' );
		else
			$base = add_query_arg( 'page', '%#%' );

		// Pagination settings with filter
		$bbp_topic_pagination = apply_filters( 'bbp_topic_pagination', array (
			'base'      => $base,
			'format'    => '',
			'total'     => $posts_per_page == $bbp_topics_template->found_posts ? 1 : ceil( (int)$bbp_topics_template->found_posts / (int)$posts_per_page ),
			'current'   => (int)$bbp_topics_template->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$bbp_topics_template->pagination_links = paginate_links ( $bbp_topic_pagination );

		// Remove first page from pagination
		$bbp_topics_template->pagination_links = str_replace( 'page/1/\'', '\'', $bbp_topics_template->pagination_links );
	}

	// Return object
	return apply_filters( 'bbp_has_topics', $bbp_topics_template->have_posts(), $bbp_topics_template );
}

/**
 * bbp_topics()
 *
 * Whether there are more topics available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2485)
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
 * @since bbPress (r2485)
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
 * Output id from bbp_topic_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2485)
 *
 * @uses bbp_get_topic_id()
 */
function bbp_topic_id () {
	echo bbp_get_topic_id();
}
	/**
	 * bbp_get_topic_id()
	 *
	 * Return the topic ID
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2485)
	 *
	 * @global object $topics_template
	 * @return string Forum id
	 */
	function bbp_get_topic_id ( $topic_id = 0 ) {
		global $bbp_topics_template, $wp_query, $bbp;

		// Easy empty checking
		if ( !empty( $topic_id ) && is_numeric( $topic_id ) )
			$bbp_topic_id = $topic_id;

		// Currently inside a topic loop
		elseif ( !empty( $bbp_topics_template->in_the_loop ) && isset( $bbp_topics_template->post->ID ) )
			$bbp_topic_id = $bbp_topics_template->post->ID;

		// Currently viewing a topic
		elseif ( bbp_is_topic() && isset( $wp_query->post->ID ) )
			$bbp_topic_id = $wp_query->post->ID;

		// Currently viewing a singular reply
		elseif ( bbp_is_reply() )
			$bbp_topic_id = bbp_get_reply_topic_id();

		// Fallback
		else
			$bbp_topic_id = 0;

		$bbp->current_topic_id = $bbp_topic_id;

		return apply_filters( 'bbp_get_topic_id', (int)$bbp_topic_id );
	}

/**
 * bbp_topic_permalink ()
 *
 * Output the link to the topic in the topic loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2485)
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
	 * @since bbPress (r2485)
	 *
	 * @uses apply_filters
	 * @uses get_permalink
	 * @param int $topic_id optional
	 *
	 * @return string Permanent link to topic
	 */
	function bbp_get_topic_permalink ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_permalink', get_permalink( $topic_id ) );
	}

/**
 * bbp_topic_title ()
 *
 * Output the title of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2485)
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
	 * @since bbPress (r2485)
	 *
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @param int $topic_id optional
	 *
	 * @return string Title of topic
	 */
	function bbp_get_topic_title ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_title', get_the_title( $topic_id ) );
	}

/**
 * bbp_topic_status ()
 *
 * Output the status of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_status()
 */
function bbp_topic_status ( $topic_id = 0 ) {
	echo bbp_get_topic_status( $topic_id );
}
	/**
	 * bbp_get_topic_status ()
	 *
	 * Return the status of the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @todo custom topic ststuses
	 *
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @param int $topic_id optional
	 *
	 * @return string Status of topic
	 */
	function bbp_get_topic_status ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_status', get_post_status( $topic_id ) );
	}

/**
 * bbp_topic_author ()
 *
 * Output the author of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2590)
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_author()
 */
function bbp_topic_author ( $topic_id = 0 ) {
	echo bbp_get_topic_author( $topic_id );
}
	/**
	 * bbp_get_topic_author ()
	 *
	 * Return the author of the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2590)
	 *
	 * @uses apply_filters
	 * @param int $topic_id optional
	 *
	 * @return string Author of topic
	 */
	function bbp_get_topic_author ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_author', get_the_author() );
	}

/**
 * bbp_topic_author_id ()
 *
 * Output the author ID of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2590)
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_author()
 */
function bbp_topic_author_id ( $topic_id = 0 ) {
	echo bbp_get_topic_author_id( $topic_id );
}
	/**
	 * bbp_get_topic_author_id ()
	 *
	 * Return the author ID of the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2590)
	 *
	 * @uses apply_filters
	 * @param int $topic_id optional
	 *
	 * @return string Author of topic
	 */
	function bbp_get_topic_author_id ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_author_id', get_post_field( 'post_author', $topic_id ) );
	}

/**
 * bbp_topic_author_display_name ()
 *
 * Output the author display_name of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2590)
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_author()
 */
function bbp_topic_author_display_name ( $topic_id = 0 ) {
	echo bbp_get_topic_author_display_name( $topic_id );
}
	/**
	 * bbp_get_topic_author_display_name ()
	 *
	 * Return the author display_name of the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2485)
	 *
	 * @uses apply_filters
	 * @param int $topic_id optional
	 *
	 * @return string Author of topic
	 */
	function bbp_get_topic_author_display_name ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_author_id', esc_attr( get_the_author_meta( 'display_name' ) ) );
	}

/**
 * bbp_topic_author_avatar ()
 *
 * Output the author avatar of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2590)
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_author()
 */
function bbp_topic_author_avatar ( $topic_id = 0, $size = 40 ) {
	echo bbp_get_topic_author_avatar( $topic_id, $size );
}
	/**
	 * bbp_get_topic_author_avatar ()
	 *
	 * Return the author avatar of the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2590)
	 *
	 * @uses apply_filters
	 * @param int $topic_id optional
	 *
	 * @return string Author of topic
	 */
	function bbp_get_topic_author_avatar ( $topic_id = 0, $size = 40 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_author_avatar', get_avatar( get_post_field( 'post_author', $topic_id ), $size ) );
	}

/**
 * bbp_topic_author_avatar ()
 *
 * Output the author avatar of the topic in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2590)
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_author()
 */
function bbp_topic_author_url ( $topic_id = 0 ) {
	echo bbp_get_topic_author_url( $topic_id );
}
	/**
	 * bbp_get_topic_author_url ()
	 *
	 * Return the author url of the topic in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2590)
	 *
	 * @uses apply_filters
	 * @param int $topic_id optional
	 *
	 * @return string Author URL of topic
	 */
	function bbp_get_topic_author_url ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_author_url', get_author_posts_url( get_post_field( 'post_author', $topic_id ) ) );
	}

/**
 * bbp_topic_forum_title ()
 *
 * Output the title of the forum a topic belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2485)
 *
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_forum_title()
 */
function bbp_topic_forum_title ( $topic_id = 0 ) {
	echo bbp_get_topic_forum_title( $topic_id );
}
	/**
	 * bbp_get_topic_forum_title ()
	 *
	 * Return the title of the forum a topic belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2485)
	 *
	 * @param int $topic_id optional
	 *
	 * @return string
	 */
	function bbp_get_topic_forum_title ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		return apply_filters( 'bbp_get_topic_forum', bbp_get_forum_title( $forum_id ) );
	}

/**
 * bbp_topic_forum_id ()
 *
 * Output the forum ID a topic belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2491)
 *
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_forum_id()
 */
function bbp_topic_forum_id ( $topic_id = 0 ) {
	echo bbp_get_topic_forum_id( $topic_id );
}
	/**
	 * bbp_get_topic_forum_id ()
	 *
	 * Return the forum ID a topic belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2491)
	 *
	 * @param int $topic_id optional
	 *
	 * @return string
	 */
	function bbp_get_topic_forum_id ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = get_post_field( 'post_parent', $topic_id );

		return apply_filters( 'bbp_get_topic_forum_id', $forum_id, $topic_id );
	}

/**
 * bbp_topic_last_active ()
 *
 * Output the topics last update date/time (aka freshness)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 *
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_last_active()
 */
function bbp_topic_last_active ( $topic_id = 0 ) {
	echo bbp_get_topic_last_active( $topic_id );
}
	/**
	 * bbp_get_topic_last_active ()
	 *
	 * Return the topics last update date/time (aka freshness)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2625)
	 *
	 * @param int $topic_id optional
	 *
	 * @return string
	 */
	function bbp_get_topic_last_active ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_topic_last_active', bbp_get_time_since( bbp_get_modified_time( $topic_id ) ) );
	}

/** TOPIC LAST REPLY **********************************************************/

/**
 * bbp_topic_last_reply_id ()
 *
 * Output the id of the topics last reply
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $topic_id optional
 *
 * @uses bbp_get_topic_last_active()
 */
function bbp_topic_last_reply_id ( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_id( $topic_id );
}
	/**
	 * bbp_get_topic_last_reply_id ()
	 *
	 * Return the topics last update date/time (aka freshness)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2625)
	 *
	 * @param int $topic_id optional
	 *
	 * @return string
	 */
	function bbp_get_topic_last_reply_id ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$reply_id = get_post_meta( $topic_id, '_bbp_topic_last_reply_id', true );

		if ( '' === $reply_id )
			$reply_id = bbp_update_topic_last_reply_id( $topic_id );

		return apply_filters( 'bbp_get_topic_last_reply_id', $reply_id );
	}

/**
 * bbp_update_topic_last_reply_id ()
 *
 * Update the topic with the most recent reply ID
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @todo everything
 * @param int $topic_id
 */
function bbp_update_topic_last_reply_id ( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
}

/**
 * bbp_topic_last_reply_title ()
 *
 * Output the title of the last reply inside a topic
 *
 * @param int $topic_id
 */
function bbp_topic_last_reply_title ( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_title( $topic_id );
}
	/**
	 * bbp_get_topic_last_reply_title ()
	 *
	 * Return the title of the last reply inside a topic
	 *
	 * @param int $topic_id
	 * @return string
	 */
	function bbp_get_topic_last_reply_title( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		return apply_filters( 'bbp_get_topic_last_topic_title', bbp_get_reply_title( bbp_get_topic_last_reply_id( $topic_id ) ) );
	}

/**
 * bbp_topic_last_reply_permalink ()
 *
 * Output the link to the last reply in a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @param int $topic_id optional
 * @uses bbp_get_topic_permalink()
 */
function bbp_topic_last_reply_permalink ( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_permalink( $topic_id );
}
	/**
	 * bbp_get_topic_last_reply_permalink ()
	 *
	 * Return the link to the last reply in a topic
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2464)
	 *
	 * @param int $topic_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to topic
	 */
	function bbp_get_topic_last_reply_permalink ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		return apply_filters( 'bbp_get_topic_last_reply_permalink', bbp_get_reply_permalink( bbp_get_topic_last_reply_id( $topic_id ) ) );
	}

/**
 * bbp_topic_freshness_link ()
 *
 * Output link to the most recent activity inside a topic, complete with
 * link attributes and content.
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2625)
 *
 * @param int $topic_id
 */
function bbp_topic_freshness_link ( $topic_id = 0) {
	echo bbp_get_topic_freshness_link( $topic_id );
}
	/**
	 * bbp_get_topic_freshness_link ()
	 *
	 * Returns link to the most recent activity inside a topic, complete with
	 * link attributes and content.
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2625)
	 *
	 * @param int $topic_id
	 */
	function bbp_get_topic_freshness_link ( $topic_id = 0 ) {
		$topic_id   = bbp_get_topic_id( $topic_id );
		$link_url   = bbp_get_topic_last_reply_permalink( $topic_id );
		$title      = bbp_get_topic_last_reply_title( $topic_id );
		$time_since = bbp_get_topic_last_active( $topic_id );
		$anchor     = '<a href="' . $link_url . '" title="' . esc_attr( $title ) . '">' . $time_since . '</a>';

		return apply_filters( 'bbp_get_topic_freshness_link', $anchor );
	}

/**
 * bbp_topic_reply_count ()
 *
 * Output total post count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2485)
 *
 * @uses bbp_get_topic_reply_count()
 * @param int $topic_id
 */
function bbp_topic_reply_count ( $topic_id = 0 ) {
	echo bbp_get_topic_reply_count( $topic_id );
}
	/**
	 * bbp_get_topic_reply_count ()
	 *
	 * Return total post count of a topic
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2485)
	 *
	 * @uses bbp_get_topic_id()
	 * @uses get_pages
	 * @uses apply_filters
	 *
	 * @param int $topic_id
	 */
	function bbp_get_topic_reply_count ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$replies  = get_post_meta( $topic_id, '_bbp_topic_reply_count', true );

		if ( '' === $replies )
			$replies = bbp_update_topic_reply_count( $topic_id );

		return apply_filters( 'bbp_get_topic_reply_count', (int)$replies );
	}

/**
 * bbp_update_topic_reply_count ()
 *
 * Adjust the total post count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2467)
 *
 * @uses bbp_get_topic_id()
 * @uses apply_filters
 *
 * @param int $topic_id optional Forum ID to update
 *
 * @return int
 */
function bbp_update_topic_reply_count ( $topic_id = 0 ) {
	global $wpdb, $bbp;

	$topic_id = bbp_get_topic_id( $topic_id );

	// If it's a reply, then get the parent (topic id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $topic_id ) )
		$topic_id = get_post_field( 'post_parent', $topic_id );

	// Get replies of topic
	$replies = count( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "';", $topic_id ) ) );

	// Update the count
	update_post_meta( $topic_id, '_bbp_topic_reply_count', (int)$replies );

	return apply_filters( 'bbp_update_topic_reply_count', (int)$replies );
}

/**
 * bbp_topic_voice_count ()
 *
 * Output total voice count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2567)
 *
 * @uses bbp_get_topic_voice_count()
 * @uses apply_filters
 *
 * @param int $topic_id
 */
function bbp_topic_voice_count ( $topic_id = 0 ) {
	echo bbp_get_topic_voice_count( $topic_id );
}
	/**
	 * bbp_get_topic_voice_count ()
	 *
	 * Return total voice count of a topic
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2567)
	 *
	 * @uses bbp_get_topic_id()
	 * @uses apply_filters
	 *
	 * @param int $topic_id
	 *
	 * @return int Voice count of the topic
	 */
	function bbp_get_topic_voice_count ( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Look for existing count, and populate if does not exist
		if ( !$voices = get_post_meta( $topic_id, '_bbp_topic_voice_count', true ) )
			$voices = bbp_update_topic_voice_count( $topic_id );

		return apply_filters( 'bbp_get_topic_voice_count', (int)$voices, $topic_id );
	}

/**
 * bbp_update_topic_voice_count ()
 *
 * Adjust the total voice count of a topic
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2567)
 *
 * @uses bbp_get_topic_id()
 * @uses apply_filters
 *
 * @todo cache
 *
 * @param int $topic_id optional Topic ID to update
 * @return bool false on failure, voice count on success
 */
function bbp_update_topic_voice_count ( $topic_id = 0 ) {
	global $wpdb, $bbp;

	$topic_id = bbp_get_topic_id( $topic_id );

	// If it is not a topic or reply, then we don't need it
	if ( !in_array( get_post_field( 'post_type', $topic_id ), array( $bbp->topic_id, $bbp->reply_id ) ) )
		return false;

	// If it's a reply, then get the parent (topic id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $topic_id ) )
		$topic_id = get_post_field( 'post_parent', $topic_id );

	// There should always be at least 1 voice
	if ( !$voices = count( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE ( post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "' ) OR ( ID = %d AND post_type = '" . $bbp->topic_id . "' );", $topic_id, $topic_id ) ) ) )
		$voices = 1;

	// Update the count
	update_post_meta( $topic_id, '_bbp_topic_voice_count', (int)$voices );

	return apply_filters( 'bbp_update_topic_voice_count', (int)$voices );
}

/**
 * bbp_topic_tag_list ( $topic_id = 0, $args = '' )
 *
 * Output a the tags of a topic
 *
 * @param int $topic_id
 * @param array $args
 */
function bbp_topic_tag_list ( $topic_id = 0, $args = '' ) {
	echo bbp_get_topic_tag_list( $topic_id, $args );
}
	/**
	 * bbp_get_topic_tag_list ( $topic_id = 0, $args = '' )
	 *
	 * Return the tags of a topic
	 *
	 * @param int $topic_id
	 * @param array $args
	 * @return string
	 */
	function bbp_get_topic_tag_list ( $topic_id = 0, $args = '' ) {
		global $bbp;

		$defaults = array(
			'before' => '<p>' . __( 'Tagged:', 'bbpress' ) . '&nbsp;',
			'sep'    => ', ',
			'after'  => '</p>'
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$topic_id = bbp_get_topic_id( $topic_id );

		return get_the_term_list( $topic_id, $bbp->topic_tag_id, $before, $sep, $after );
	}


/**
 * bbp_topic_admin_links()
 *
 * Output admin links for topic
 *
 * @param array $args
 */
function bbp_topic_admin_links( $args = '' ) {
	echo bbp_get_topic_admin_links( $args );
}
	/**
	 * bbp_get_topic_admin_links()
	 *
	 * Return admin links for topic
	 *
	 * @param array $args
	 * @return string
	 */
	function bbp_get_topic_admin_links( $args = '' ) {
		if ( !current_user_can( 'edit_others_topics' ) )
			return '&nbsp';

		$defaults = array (
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array (
				'delete' => __( 'Delete' ), // bbp_get_topic_delete_link( $args ),
				'close'  => __( 'Close' ),  // bbp_get_topic_close_link( $args ),
				'sticky' => __( 'Sticky' ), // bbp_get_topic_sticky_link( $args ),
				'move'   => __( 'Move' ),   // bbp_get_topic_move_dropdown( $args )
			),
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		// Process the admin links
		$links = implode( $sep, $links );

		return apply_filters( 'bbp_get_topic_admin_links', $before . $links . $after, $args );
	}

/**
 * bbp_forum_pagination_count ()
 *
 * Output the pagination count
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2519)
 *
 * @global WP_Query $bbp_topics_template
 */
function bbp_forum_pagination_count () {
	echo bbp_get_forum_pagination_count();
}
	/**
	 * bbp_get_forum_pagination_count ()
	 *
	 * Return the pagination count
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2519)
	 *
	 * @global WP_Query $bbp_topics_template
	 * @return string
	 */
	function bbp_get_forum_pagination_count () {
		global $bbp_topics_template;

		if ( !isset( $bbp_topics_template ) )
			return false;

		// Set pagination values
		$start_num = intval( ( $bbp_topics_template->paged - 1 ) * $bbp_topics_template->posts_per_page ) + 1;
		$from_num  = bbp_number_format( $start_num );
		$to_num    = bbp_number_format( ( $start_num + ( $bbp_topics_template->posts_per_page - 1 ) > $bbp_topics_template->found_posts ) ? $bbp_topics_template->found_posts : $start_num + ( $bbp_topics_template->posts_per_page - 1 ) );
		$total     = bbp_number_format( !empty( $bbp_topics_template->found_posts ) ? $bbp_topics_template->found_posts : $bbp_topics_template->post_count );

		// Set return string
		if ( $total > 1 && (int)$from_num == (int)$to_num )
			$retstr = sprintf( __( 'Viewing topic %1$s (of %2$s total)', 'bbpress' ), $from_num, $total );
		elseif ( $total > 1 && empty( $to_num ) )
			$retstr = sprintf( __( 'Viewing %1$s topics', 'bbpress' ), $total );
		elseif ( $total > 1 && (int)$from_num != (int)$to_num )
			$retstr = sprintf( __( 'Viewing topics %1$s through %2$s (of %3$s total)', 'bbpress' ), $from_num, $to_num, $total );
		else
			$retstr = sprintf( __( 'Viewing %1$s topic', 'bbpress' ), $total );

		// Filter and return
		return apply_filters( 'bbp_get_topic_pagination_count', $retstr );
	}

/**
 * bbp_forum_pagination_links ()
 *
 * Output pagination links
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2519)
 */
function bbp_forum_pagination_links () {
	echo bbp_get_forum_pagination_links();
}
	/**
	 * bbp_get_forum_pagination_links ()
	 *
	 * Return pagination links
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2519)
	 *
	 * @global WP_Query $bbp_topics_template
	 * @return string
	 */
	function bbp_get_forum_pagination_links () {
		global $bbp_topics_template;

		if ( !isset( $bbp_topics_template ) )
			return false;

		return apply_filters( 'bbp_get_topic_pagination_links', $bbp_topics_template->pagination_links );
	}

/** END - Topic Loop Functions ************************************************/

/** START - Reply Loop Functions **********************************************/

/**
 * bbp_has_replies ( $args )
 *
 * The main reply loop. WordPress makes this easy for us
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @global WP_Query $bbp_replies_template
 * @param array $args Possible arguments to change returned replies
 * @return object Multidimensional array of reply information
 */
function bbp_has_replies ( $args = '' ) {
	global $wp_rewrite, $bbp_replies_template, $bbp;

	$default = array(
		// Narrow query down to bbPress topics
		'post_type'        => $bbp->reply_id,

		// Forum ID
		'post_parent'      => isset( $_REQUEST['topic_id'] ) ? $_REQUEST['topic_id'] : bbp_get_topic_id(),

		//'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'          => isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'date',

		// 'ASC', 'DESC'
		'order'            => isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC',

		// @todo replace 15 with setting
		'posts_per_page'   => isset( $_REQUEST['posts'] ) ? $_REQUEST['posts'] : 15,

		// Page Number
		'paged'            => bbp_get_paged(),

		// Reply Search
		's'                => empty( $_REQUEST['rs'] ) ? '' : $_REQUEST['rs'],
	);

	// Set up topic variables
	$bbp_r = wp_parse_args( $args, $default );
	$r     = extract( $bbp_r );

	// Call the query
	$bbp_replies_template = new WP_Query( $bbp_r );

	// Add pagination values to query object
	$bbp_replies_template->posts_per_page = $posts_per_page;
	$bbp_replies_template->paged = $paged;

	// Only add pagination if query returned results
	if ( (int)$bbp_replies_template->found_posts && (int)$bbp_replies_template->posts_per_page ) {

		// If pretty permalinks are enabled, make our pagination pretty
		if ( $wp_rewrite->using_permalinks() )
			$base = user_trailingslashit( trailingslashit( get_permalink( $post_parent ) ) . 'page/%#%/' );
		else
			$base = add_query_arg( 'page', '%#%' );

		// Pagination settings with filter
		$bbp_replies_pagination = apply_filters( 'bbp_replies_pagination', array(
			'base'      => $base,
			'format'    => '',
			'total'     => ceil( (int)$bbp_replies_template->found_posts / (int)$posts_per_page ),
			'current'   => (int)$bbp_replies_template->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$bbp_replies_template->pagination_links = paginate_links( $bbp_replies_pagination );

		// Remove first page from pagination
		$bbp_replies_template->pagination_links = str_replace( 'page/1/\'', '\'', $bbp_replies_template->pagination_links );
	}

	// Return object
	return apply_filters( 'bbp_has_replies', $bbp_replies_template->have_posts(), $bbp_replies_template );
}

/**
 * bbp_replies ()
 *
 * Whether there are more replies available in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @global WP_Query $bbp_replies_template
 * @return object Replies information
 */
function bbp_replies () {
	global $bbp_replies_template;
	return $bbp_replies_template->have_posts();
}

/**
 * bbp_the_reply ()
 *
 * Loads up the current reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @global WP_Query $bbp_replies_template
 * @return object Reply information
 */
function bbp_the_reply () {
	global $bbp_replies_template;
	return $bbp_replies_template->the_post();
}

/**
 * bbp_reply_id ()
 *
 * Output id from bbp_get_reply_id()
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @uses bbp_get_reply_id()
 */
function bbp_reply_id () {
	echo bbp_get_reply_id();
}
	/**
	 * bbp_get_reply_id ()
	 *
	 * Return the id of the reply in a replies loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2553)
	 *
	 * @global object $bbp_replies_template
	 * @return int Reply id
	 */
	function bbp_get_reply_id ( $reply_id = 0 ) {
		global $bbp_replies_template, $wp_query, $bbp;

		// Easy empty checking
		if ( !empty( $reply_id ) && is_numeric( $reply_id ) )
			$bbp_reply_id = $reply_id;

		// Currently viewing a reply
		elseif ( bbp_is_reply() && isset( $wp_query->post->ID ) )
			$bbp_reply_id = $wp_query->post->ID;

		// Currently inside a replies loop
		elseif ( isset( $bbp_replies_template->post->ID ) )
			$bbp_reply_id = $bbp_replies_template->post->ID;

		// Fallback
		else
			$bbp_reply_id = 0;

		$bbp->current_reply_id = $bbp_reply_id;

		return apply_filters( 'bbp_get_reply_id', (int)$bbp_reply_id );
	}

/**
 * bbp_reply_permalink ()
 *
 * Output the link to the reply in the reply loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @uses bbp_get_reply_permalink()
 * @param int $reply_id optional
 */
function bbp_reply_permalink ( $reply_id = 0 ) {
	echo bbp_get_reply_permalink( $reply_id );
}
	/**
	 * bbp_get_reply_permalink()
	 *
	 * Return the link to the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2553)
	 *
	 * @uses apply_filters
	 * @uses get_permalink
	 * @param int $reply_id optional
	 *
	 * @return string Permanent link to reply
	 */
	function bbp_get_reply_permalink ( $reply_id = 0 ) {
		return apply_filters( 'bbp_get_reply_permalink', get_permalink( $reply_id ), $reply_id );
	}

/**
 * bbp_reply_title ()
 *
 * Output the title of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_title()
 */
function bbp_reply_title ( $reply_id = 0 ) {
	echo bbp_get_reply_title( $reply_id );
}

	/**
	 * bbp_get_reply_title ()
	 *
	 * Return the title of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2553)
	 *
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @param int $reply_id optional
	 *
	 * @return string Title of reply
	 */
	function bbp_get_reply_title ( $reply_id = 0 ) {
		return apply_filters( 'bbp_get_reply_title', get_the_title( $reply_id ), $reply_id );
	}

/**
 * bbp_reply_content ()
 *
 * Output the content of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @todo Have a parameter reply_id
 *
 * @uses bbp_get_reply_content()
 */
function bbp_reply_content () {
	echo bbp_get_reply_content();
}
	/**
	 * bbp_get_reply_content ()
	 *
	 * Return the content of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2553)
	 *
	 * @uses apply_filters
	 * @uses get_the_content()
	 *
	 * @return string Content of the reply
	 */
	function bbp_get_reply_content () {
		return apply_filters( 'bbp_get_reply_content', get_the_content() );
	}

/**
 * bbp_reply_status ()
 *
 * Output the status of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_status()
 */
function bbp_reply_status ( $reply_id = 0 ) {
	echo bbp_get_reply_status( $reply_id );
}
	/**
	 * bbp_get_reply_status ()
	 *
	 * Return the status of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @todo custom topic ststuses
	 *
	 * @uses apply_filters
	 * @uses get_post_status()
	 * @param int $reply_id optional
	 *
	 * @return string Status of reply
	 */
	function bbp_get_reply_status ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_status', get_post_status( $reply_id ) );
	}

/**
 * bbp_reply_author ()
 *
 * Output the author of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author()
 */
function bbp_reply_author ( $reply_id = 0 ) {
	echo bbp_get_reply_author( $reply_id );
}
	/**
	 * bbp_get_reply_author ()
	 *
	 * Return the author of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Author of reply
	 */
	function bbp_get_reply_author ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_author', get_the_author() );
	}

/**
 * bbp_reply_author_id ()
 *
 * Output the author ID of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author()
 */
function bbp_reply_author_id ( $reply_id = 0 ) {
	echo bbp_get_reply_author_id( $reply_id );
}
	/**
	 * bbp_get_reply_author_id ()
	 *
	 * Return the author ID of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Author of reply
	 */
	function bbp_get_reply_author_id ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_author_id', get_post_field( 'post_author', $reply_id ) );
	}

/**
 * bbp_reply_author_display_name ()
 *
 * Output the author display_name of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author()
 */
function bbp_reply_author_display_name ( $reply_id = 0 ) {
	echo bbp_get_reply_author_display_name( $reply_id );
}
	/**
	 * bbp_get_reply_author_display_name ()
	 *
	 * Return the author display_name of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Author of reply
	 */
	function bbp_get_reply_author_display_name ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_author_id', esc_attr( get_the_author_meta( 'display_name' ) ) );
	}

/**
 * bbp_reply_author_avatar ()
 *
 * Output the author avatar of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author()
 */
function bbp_reply_author_avatar ( $reply_id = 0, $size = 40 ) {
	echo bbp_get_reply_author_avatar( $reply_id, $size );
}
	/**
	 * bbp_get_reply_author_avatar ()
	 *
	 * Return the author avatar of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Author of reply
	 */
	function bbp_get_reply_author_avatar ( $reply_id = 0, $size = 40 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_author_avatar', get_avatar( get_post_field( 'post_author', $reply_id ), $size ) );
	}

/**
 * bbp_reply_author_avatar ()
 *
 * Output the author avatar of the reply in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_author()
 */
function bbp_reply_author_url ( $reply_id = 0 ) {
	echo bbp_get_reply_author_url( $reply_id );
}
	/**
	 * bbp_get_reply_author_url ()
	 *
	 * Return the author url of the reply in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @param int $reply_id optional
	 *
	 * @return string Author URL of reply
	 */
	function bbp_get_reply_author_url ( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_author_url', get_author_posts_url( get_post_field( 'post_author', $reply_id ) ) );
	}

/**
 * bbp_reply_topic ()
 *
 * Output the topic title a reply belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_topic()
 */
function bbp_reply_topic ( $reply_id = 0 ) {
	echo bbp_get_reply_topic( $reply_id );
}
	/**
	 * bbp_get_reply_topic ()
	 *
	 * Return the topic title a reply belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2553)
	 *
	 * @param int $reply_id optional
	 *
	 * @uses bbp_get_reply_topic_id ()
	 * @uses bbp_topic_title ()
	 *
	 * @return string
	 */
	function bbp_get_reply_topic ( $reply_id = 0 ) {
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		return apply_filters( 'bbp_get_reply_topic', bbp_get_topic_title( $topic_id ), $reply_id, $topic_id );
	}

/**
 * bbp_reply_topic_id ()
 *
 * Output the topic ID a reply belongs to
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2553)
 *
 * @param int $reply_id optional
 *
 * @uses bbp_get_reply_topic_id ()
 */
function bbp_reply_topic_id ( $reply_id = 0 ) {
	echo bbp_get_reply_topic_id( $reply_id );
}
	/**
	 * bbp_get_reply_topic_id ()
	 *
	 * Return the topic ID a reply belongs to
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2553)
	 *
	 * @param int $reply_id optional
	 *
	 * @todo - Walk ancestors and look for topic post_type (for threaded replies)
	 *
	 * @return string
	 */
	function bbp_get_reply_topic_id ( $reply_id = 0 ) {
		global $bbp_replies_template;

		$reply_id = bbp_get_reply_id( $reply_id);
		$topic_id = get_post_field( 'post_parent', $reply_id );

		return apply_filters( 'bbp_get_reply_topic_id', $topic_id, $reply_id );
	}

/**
 * bbp_reply_admin_links()
 *
 * Output admin links for reply
 *
 * @param array $args
 */
function bbp_reply_admin_links( $args = '' ) {
	echo bbp_get_reply_admin_links( $args );
}
	/**
	 * bbp_get_reply_admin_links()
	 *
	 * Return admin links for reply
	 *
	 * @param array $args
	 * @return string
	 */
	function bbp_get_reply_admin_links( $args = '' ) {
		if ( !current_user_can( 'edit_others_replies' ) )
			return;

		$defaults = array (
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array (
				'trash' => __( 'Trash', 'bbpress' ), // bbp_get_reply_delete_link( $args ),
				'edit'  => __( 'Edit', 'bbpress' ),  // bbp_get_reply_close_link( $args ),
			),
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		// Process the admin links
		$links = implode( $sep, $links );

		return apply_filters( 'bbp_get_reply_admin_links', $before . $links . $after, $args );
	}

/**
 * bbp_topic_pagination_count ()
 *
 * Output the pagination count
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2519)
 *
 * @global WP_Query $bbp_topics_template
 */
function bbp_topic_pagination_count () {
	echo bbp_get_topic_pagination_count();
}
	/**
	 * bbp_get_topic_pagination_count ()
	 *
	 * Return the pagination count
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2519)
	 *
	 * @global WP_Query $bbp_replies_template
	 * @return string
	 */
	function bbp_get_topic_pagination_count () {
		global $bbp_replies_template;

		// Set pagination values
		$start_num = intval( ( $bbp_replies_template->paged - 1 ) * $bbp_replies_template->posts_per_page ) + 1;
		$from_num  = bbp_number_format( $start_num );
		$to_num    = bbp_number_format( ( $start_num + ( $bbp_replies_template->posts_per_page - 1 ) > $bbp_replies_template->found_posts ) ? $bbp_replies_template->found_posts : $start_num + ( $bbp_replies_template->posts_per_page - 1 ) );
		$total     = bbp_number_format( $bbp_replies_template->found_posts );

		// Set return string
		if ( $total > 1 && $from_num != $to_num )
			$retstr = sprintf( __( 'Viewing replies %1$s through %2$s (of %3$s total)', 'bbpress' ), $from_num, $to_num, $total );
		elseif ( $total > 1 && $from_num == $to_num )
			$retstr = sprintf( __( 'Viewing reply %1$s (of %2$s total)', 'bbpress' ), $from_num, $total );
		else
			$retstr = sprintf( __( 'Viewing %1$s reply', 'bbpress' ), $total );

		// Filter and return
		return apply_filters( 'bbp_get_topic_pagination_count', $retstr );
	}

/**
 * bbp_topic_pagination_links ()
 *
 * Output pagination links
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2519)
 */
function bbp_topic_pagination_links () {
	echo bbp_get_topic_pagination_links();
}
	/**
	 * bbp_get_topic_pagination_links ()
	 *
	 * Return pagination links
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2519)
	 *
	 * @global WP_Query $bbp_replies_template
	 * @return string
	 */
	function bbp_get_topic_pagination_links () {
		global $bbp_replies_template;

		if ( !isset( $bbp_replies_template->pagination_links ) || empty( $bbp_replies_template->pagination_links ) )
			return false;
		else
			return apply_filters( 'bbp_get_topic_pagination_links', $bbp_replies_template->pagination_links );
	}

/** END reply Loop Functions **************************************************/

/** START is_ Functions *******************************************************/

/**
 * bbp_is_forum ()
 *
 * Check if current page is a bbPress forum
 *
 * @since bbPress (r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_forum () {
	global $wp_query, $bbp;

	if ( is_singular( $bbp->forum_id ) )
		return true;

	if ( isset( $wp_query->query_vars['post_type'] ) && $bbp->forum_id === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && $bbp->forum_id === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * bbp_is_topic ()
 *
 * Check if current page is a bbPress topic
 *
 * @since bbPress (r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_topic () {
	global $wp_query, $bbp;

	if ( is_singular( $bbp->topic_id ) )
		return true;

	if ( isset( $wp_query->query_vars['post_type'] ) && $bbp->topic_id === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && $bbp->topic_id === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * bbp_is_reply ()
 *
 * Check if current page is a bbPress topic reply
 *
 * @since bbPress (r2549)
 *
 * @global object $wp_query
 * @return bool
 */
function bbp_is_reply () {
	global $wp_query, $bbp;

	if ( is_singular( $bbp->reply_id ) )
		return true;

	if ( isset( $wp_query->query_vars['post_type'] ) && $bbp->reply_id === $wp_query->query_vars['post_type'] )
		return true;

	if ( isset( $_GET['post_type'] ) && !empty( $_GET['post_type'] ) && $bbp->reply_id === $_GET['post_type'] )
		return true;

	return false;
}

/**
 * bbp_is_favorites ()
 *
 * Check if current page is a bbPress user's favorites page (author page)
 *
 * @since bbPress (r2652)
 *
 * @return bool
 */
function bbp_is_favorites () {
	return (bool) is_author();
}

/**
 * bbp_is_user_home ()
 *
 * Check if current page is the currently logged in users author page
 *
 * @global object $current_user
 * @return bool
 */
function bbp_is_user_home() {
	global $current_user;

	$current_user = wp_get_current_user();

	if ( $current_user->ID == get_the_author_meta( 'ID' ) )
		$retval = true;
	else
		$retval = false;

	return apply_filters( 'bbp_is_user_home', $retval, $current_user );
}

/** END is_ Functions *********************************************************/

/** START Favorites Functions *************************************************/

/**
 * bbp_favorites_permalink ()
 *
 * Output the link to the user's favorites page (author page)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2652)
 *
 * @param int $user_id optional
 * @uses bbp_get_favorites_permalink()
 */
function bbp_favorites_permalink ( $user_id = 0 ) {
	echo bbp_get_favorites_permalink( $user_id );
}
	/**
	 * bbp_get_favorites_permalink ()
	 *
	 * Return the link to the user's favorites page (author page)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2652)
	 *
	 * @param int $user_id optional
	 * @uses apply_filters
	 * @uses get_author_posts_url
	 * @return string Permanent link to topic
	 */
	function bbp_get_favorites_permalink ( $user_id = 0 ) {
		return apply_filters( 'bbp_get_favorites_permalink', get_author_posts_url( $user_id ) );
	}

/**
 * bbp_user_favorites_link ()
 *
 * Output the link to the user's favorites page (author page)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2652)
 *
 * @param array $add optional
 * @param array $rem optional
 * @param int $user_id optional
 *
 * @uses bbp_get_favorites_link()
 */
function bbp_user_favorites_link ( $add = array(), $rem = array(), $user_id = 0 ) {
	echo bbp_get_user_favorites_link( $add, $rem, $user_id );
}
	/**
	 * bbp_get_user_favorites_link ()
	 *
	 * Return the link to the user's favorites page (author page)
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2652)
	 *
	 * @param array $add optional
	 * @param array $rem optional
	 * @param int $user_id optional
	 *
	 * @uses apply_filters
	 * @uses get_author_posts_url
	 * @return string Permanent link to topic
	 */
	function bbp_get_user_favorites_link ( $add = array(), $rem = array(), $user_id = 0 ) {
		global $current_user;

		$current_user = wp_get_current_user();

		if ( empty( $user_id ) && !$user_id = $current_user->ID )
			return false;

		if ( !current_user_can( 'edit_user', (int) $user_id ) )
			return false;

		if ( !$topic_id = bbp_get_topic_id() )
			return false;

		if ( empty( $add ) || !is_array( $add ) ) {
			$add = array(
				'mid'  => __( 'Add this topic to your favorites', 'bbpress' ),
				'post' => __( ' (%?%)', 'bbpress' )
			);
		}

		if ( empty( $rem ) || !is_array( $rem ) ) {
			$rem = array(
				'pre'  => __( 'This topic is one of your %favorites% [', 'bbpress' ),
				'mid'  => __( '&times;', 'bbpress' ),
				'post' => __( ']', 'bbpress' )
			);
		}

		if ( $is_fav = bbp_is_user_favorite( $user_id, $topic_id ) ) {
			$url  = esc_url( bbp_get_favorites_permalink( $user_id ) );
			$rem  = preg_replace( '|%(.+)%|', "<a href='$url'>$1</a>", $rem );
			$favs = array( 'action' => 'bbp_favorite_remove', 'topic_id' => $topic_id );
			$pre  = ( is_array( $rem ) && isset( $rem['pre']  ) ) ? $rem['pre']  : '';
			$mid  = ( is_array( $rem ) && isset( $rem['mid']  ) ) ? $rem['mid']  : ( is_string( $rem ) ? $rem : '' );
			$post = ( is_array( $rem ) && isset( $rem['post'] ) ) ? $rem['post'] : '';
		} else {
			$url  = esc_url( bbp_get_topic_permalink( $topic_id ) );
			$add  = preg_replace( '|%(.+)%|', "<a href='$url'>$1</a>", $add );
			$favs = array( 'action' => 'bbp_favorite_add', 'topic_id' => $topic_id );
			$pre  = ( is_array( $add ) && isset( $add['pre']  ) ) ? $add['pre']  : '';
			$mid  = ( is_array( $add ) && isset( $add['mid']  ) ) ? $add['mid']  : ( is_string( $add ) ? $add : '' );
			$post = ( is_array( $add ) && isset( $add['post'] ) ) ? $add['post'] : '';
		}

		$permalink = bbp_is_favorites() ? bbp_get_favorites_permalink( $user_id ) : bbp_get_topic_permalink( $topic_id );
		$url       = esc_url( wp_nonce_url( add_query_arg( $favs, $permalink ), 'toggle-favorite_' . $topic_id ) );
		$is_fav    = $is_fav ? 'is-favorite' : '';

		return apply_filters( 'bbp_get_user_favorites_link', "<span id='favorite-toggle'><span id='favorite-$topic_id' class='$is_fav'>$pre<a href='$url' class='dim:favorite-toggle:favorite-$topic_id:is-favorite'>$mid</a>$post</span></span>" );
	}

/** END Favorites Functions ***************************************************/

/** START User Functions ******************************************************/

/**
 * bbp_current_user_id ()
 *
 * Output ID of current user
 *
 * @uses bbp_get_current_user_id()
 */
function bbp_current_user_id () {
	echo bbp_get_current_user_id();
}
	/**
	 * bbp_get_current_user_id ()
	 *
	 * Return ID of current user
	 *
	 * @global object $current_user
	 * @global string $user_identity
	 * @return int
	 */
	function bbp_get_current_user_id () {
		global $current_user;

		if ( is_user_logged_in() )
			$current_user_id = $current_user->ID;
		else
			$current_user_id = -1;

		return apply_filters( 'bbp_get_current_user_id', $current_user_id );
	}

/**
 * bbp_current_user_name ()
 *
 * Output name of current user
 *
 * @uses bbp_get_current_user_name()
 */
function bbp_current_user_name () {
	echo bbp_get_current_user_name();
}
	/**
	 * bbp_get_current_user_name ()
	 *
	 * Return name of current user
	 *
	 * @global object $current_user
	 * @global string $user_identity
	 * @return string
	 */
	function bbp_get_current_user_name () {
		global $current_user, $user_identity;

		if ( is_user_logged_in() )
			$current_user_name = $user_identity;
		else
			$current_user_name = __( 'Anonymous', 'bbpress' );

		return apply_filters( 'bbp_get_current_user_name', $current_user_name );
	}

/**
 * bbp_current_user_avatar ()
 *
 * Output avatar of current user
 *
 * @uses bbp_get_current_user_avatar()
 */
function bbp_current_user_avatar ( $size = 40 ) {
	echo bbp_get_current_user_avatar( $size );
}

	/**
	 * bbp_get_current_user_avatar ( $size = 40 )
	 *
	 * Return avatar of current user
	 *
	 * @global object $current_user
	 * @param int $size
	 * @return string
	 */
	function bbp_get_current_user_avatar ( $size = 40 ) {
		global $current_user;

		return apply_filters( 'bbp_get_current_user_avatar', get_avatar( bbp_get_current_user_id(), $size ) );
	}

/** END User Functions ********************************************************/

/** START Form Functions ******************************************************/

/**
 * bbp_new_topic_form_fields ()
 *
 * Output the required hidden fields when creating a new topic
 *
 * @uses wp_nonce_field, bbp_forum_id
 */
function bbp_new_topic_form_fields () {

	if ( bbp_is_forum() ) : ?>

	<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="<?php bbp_forum_id(); ?>" />

	<?php endif; ?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-new-topic" />

	<?php wp_nonce_field( 'bbp-new-topic' );
}

/**
 * bbp_new_reply_form_fields ()
 *
 * Output the required hidden fields when creating a new reply
 *
 * @uses wp_nonce_field, bbp_forum_id, bbp_topic_id
 */
function bbp_new_reply_form_fields () { ?>

	<input type="hidden" name="bbp_reply_title" id="bbp_reply_title" value="<?php printf( __( 'Reply To: %s', 'bbpress' ), bbp_get_topic_title() ); ?>" />
	<input type="hidden" name="bbp_forum_id"    id="bbp_forum_id"    value="<?php bbp_forum_id(); ?>" />
	<input type="hidden" name="bbp_topic_id"    id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />
	<input type="hidden" name="action"          id="bbp_post_action" value="bbp-new-reply" />

	<?php wp_nonce_field( 'bbp-new-reply' );
}

/**
 * bbp_forum_dropdown ()
 *
 * Output a select box allowing to pick which forum a new topic belongs in.
 *
 * @param array $args
 */
function bbp_forum_dropdown ( $args = '' ) {
	echo bbp_get_forum_dropdown( $args );
}
	/**
	 * bbp_get_forum_dropdown ()
	 *
	 * Return a select box allowing to pick which forum a new topic belongs in.
	 *
	 * @global object $bbp
	 * @param array $args
	 * @return string
	 */
	function bbp_get_forum_dropdown ( $args = '' ) {
		global $bbp;

		$defaults = array (
			'post_type'         => $bbp->forum_id,
			'selected'          => bbp_get_forum_id(),
			'sort_column'       => 'menu_order, post_title',
			'child_of'          => '0',
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		if ( $forums = get_posts( $r ) ) {
			$output = '<select name="bbp_forum_id" id="bbp_forum_id">';
			$output .= walk_page_dropdown_tree( $forums, 0, $r );
			$output .= '</select>';
		} else {
			$output = __( 'No forums to post to!', 'bbpress' );
		}

		return apply_filters( 'bbp_get_forums_dropdown', $output );
	}

/** END Form Functions ********************************************************/

/** Start General Functions ***************************************************/

/**
 * bbp_title_breadcrumb ( $sep )
 *
 * Output the page title as a breadcrumb
 *
 * @param string $sep
 */
function bbp_title_breadcrumb ( $sep = '&larr;' ) {
	echo bbp_get_breadcrumb( $sep );
}

/**
 * bbp_breadcrumb ( $sep )
 *
 * Output a breadcrumb
 *
 * @param string $sep
 */
function bbp_breadcrumb ( $sep = '&larr;' ) {
	echo bbp_get_breadcrumb( $sep );
}
	/**
	 * bbp_get_breadcrumb ( $sep )
	 *
	 * Return a breadcrumb ( forum < topic
	 *
	 * @global object $post
	 * @param string $sep
	 * @return string
	 */
	function bbp_get_breadcrumb( $sep = '&larr;' ) {
		global $post, $bbp;

		$trail       = '';
		$parent_id   = $post->post_parent;
		$breadcrumbs = array();

		// Loop through parents
		while ( $parent_id ) {
			// Parents
			$parent = get_post( $parent_id );

			// Switch through post_type to ensure correct filters are applied
			switch ( $parent->post_type ) {
				// Forum
				case $bbp->forum_id :
					$breadcrumbs[] = '<a href="' . bbp_get_forum_permalink( $parent->ID ) . '">' . bbp_get_forum_title( $parent->ID ) . '</a>';
					break;

				// Topic
				case $bbp->topic_id :
					$breadcrumbs[] = '<a href="' . bbp_get_topic_permalink( $parent->ID ) . '">' . bbp_get_topic_title( $parent->ID ) . '</a>';
					break;

				// Reply (Note: not in most themes)
				case $bbp->reply_id :
					$breadcrumbs[] = '<a href="' . bbp_get_reply_permalink( $parent->ID ) . '">' . bbp_get_reply_title( $parent->ID ) . '</a>';
					break;

				// WordPress Post/Page/Other
				default :
					$breadcrumbs[] = '<a href="' . get_permalink( $parent->ID ) . '">' . get_the_title( $parent->ID ) . '</a>';
					break;
			}

			// Walk backwards up the tree
			$parent_id = $parent->post_parent;
		}

		// Reverse the breadcrumb
		$breadcrumbs = array_reverse( $breadcrumbs );

		// Build the trail
		foreach ( $breadcrumbs as $crumb )
			$trail .= $crumb . ' ' . $sep . ' ';

		return apply_filters( 'bbp_get_breadcrumb', $trail . get_the_title() );
	}

?>
