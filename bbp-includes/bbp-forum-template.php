<?php

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
 * @global WP_Query $bbp->forum_query
 * @param array $args Possible arguments to change returned forums
 * @return object Multidimensional array of forum information
 */
function bbp_has_forums ( $args = '' ) {
	global $wp_query, $bbp;

	$default = array (
		'post_type'      => $bbp->forum_id,
		'post_parent'    => bbp_get_forum_id(),
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC'
	);

	$r = wp_parse_args( $args, $default );

	// Don't show private forums to normal users
	if ( !current_user_can( 'edit_others_forums' ) && empty( $r['meta_key'] ) && empty( $r['meta_value'] ) && empty( $r['meta_compare'] ) ) {
		$r['meta_key']     = '_bbp_forum_visibility';
		$r['meta_value']   = 'public';
		$r['meta_compare'] = '==';
	}

	$bbp->forum_query = new WP_Query( $r );

	return apply_filters( 'bbp_has_forums', $bbp->forum_query->have_posts(), $bbp->forum_query );
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
 * @global WP_Query $bbp->forum_query
 * @return object Forum information
 */
function bbp_forums () {
	global $bbp;
	return $bbp->forum_query->have_posts();
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
 * @global WP_Query $bbp->forum_query
 * @return object Forum information
 */
function bbp_the_forum () {
	global $bbp;
	return $bbp->forum_query->the_post();
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
		global $bbp, $wp_query;

		// Easy empty checking
		if ( !empty( $forum_id ) && is_numeric( $forum_id ) )
			$bbp_forum_id = $forum_id;

		// Currently inside a forum loop
		elseif ( !empty( $bbp->forum_query->in_the_loop ) && isset( $bbp->forum_query->post->ID ) )
			$bbp_forum_id = $bbp->forum_query->post->ID;

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

		if ( !$last_active = get_post_meta( $forum_id, '_bbp_forum_last_active', true ) ) {
			if ( $reply_id = bbp_get_forum_last_reply_id( $forum_id ) ) {
				$last_active = get_post_field( 'post_date', $reply_id );
			} else {
				if ( $topic_id = bbp_get_forum_last_topic_id( $forum_id ) ) {
					$last_active = bbp_get_topic_last_active( $forum_id, '_bbp_forum_last_active', true );
				}
			}
		}

		$last_active = !empty( $last_active ) ? bbp_get_time_since( bbp_convert_date( $last_active ) ) : '';

		return apply_filters( 'bbp_get_forum_last_active', $last_active );
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
 * Return sub forums of given forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2705)
 *
 * @param mixed $args All the arguments supported by {@link WP_Query}
 * @return false if none, array of subs if yes
 */
function bbp_forum_has_sub_forums ( $args = '' ) {
	global $bbp;

	if ( is_numeric( $args ) )
		$args = array( 'post_parent' => $args );

	$default = array(
		'post_parent' => 0,
		'post_type'   => $bbp->forum_id,
		'sort_column' => 'menu_order, post_title'
	);

	$r = wp_parse_args( $args, $default );

	$r['post_parent'] = bbp_get_forum_id( $r['post_parent'] );

	// Don't show private forums to normal users
	if ( !current_user_can( 'edit_others_forums' ) && empty( $r['meta_key'] ) && empty( $r['meta_value'] ) && empty( $r['meta_compare'] ) ) {
		$r['meta_key']     = '_bbp_forum_visibility';
		$r['meta_value']   = 'public';
		$r['meta_compare'] = '==';
	}

	// No forum passed
	$sub_forums = !empty( $r['post_parent'] ) ? get_posts( $r ) : '';

	return apply_filters( 'bbp_forum_has_sub_forums', (array) $sub_forums, $args );
}

/**
 * bbp_list_forums ()
 *
 * Output a list of forums (can be used to list sub forums)
 *
 * @param int $forum_id
 */
function bbp_list_forums ( $args = '' ) {
	global $bbp;

	// Define used variables
	$output = $sub_forums = $topic_count = $reply_count = '';
	$i = 0;

	// Defaults and arguments
	$defaults = array (
		'before'            => '<ul class="bbp-forums">',
		'after'             => '</ul>',
		'link_before'       => '<li class="bbp-forum">',
		'link_after'        => '</li>',
		'separator'         => ', ',
		'forum_id'          => '',
		'show_topic_count'  => true,
		'show_reply_count'  => true,
	);
	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	// Loop through forums and create a list
	if ( $sub_forums = bbp_forum_has_sub_forums( $forum_id ) ) {
		// Total count (for separator)
		$total_subs = count( $sub_forums );
		foreach( $sub_forums as $sub_forum ) {
			$i++; // Separator count

			// Get forum details
			$show_sep  = $total_subs > $i ? $separator : '';
			$permalink = bbp_get_forum_permalink( $sub_forum->ID );
			$title     = bbp_get_forum_title( $sub_forum->ID );

			// Show topic and reply counts
			if ( !empty( $show_topic_count ) )
				$topic_count = ' (' . bbp_get_forum_topic_count( $sub_forum->ID ) . ')';

			// @todo - Walk tree and update counts
			//if ( !empty( $show_reply_count ) )
			//	$reply_count = ' (' . bbp_get_forum_reply_count( $sub_forum->ID ) . ')';

			$output .= $link_before . '<a href="' . $permalink . '" class="bbp-forum-link">' . $title . $topic_count . $reply_count . '</a>' . $show_sep . $link_after;
		}

		// Output the list
		echo $before . $output . $after;
	}
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

		return apply_filters( 'bbp_get_forum_last_topic_id', $topic_id );
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
 * bbp_forum_last_reply_url ()
 *
 * Output the link to the last reply in a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2683)
 *
 * @param int $forum_id optional
 * @uses bbp_get_forum_url()
 */
function bbp_forum_last_reply_url ( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_url( $forum_id );
}
	/**
	 * bbp_get_forum_last_reply_url ()
	 *
	 * Return the link to the last reply in a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2683)
	 *
	 * @param int $forum_id optional
	 * @uses apply_filters
	 * @uses get_url
	 * @return string Paginated URL to latest reply
	 */
	function bbp_get_forum_last_reply_url ( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );

		// If forum has replies, get the last reply and use its url
		if ( $reply_id  = bbp_get_forum_last_reply_id( $forum_id ) ) {
			$reply_url = bbp_get_reply_url( $reply_id );

		// No replies, so look for topics and use last permalink
		} else {
			if ( $topic_id = bbp_get_forum_last_topic_id( $forum_id ) ) {
				$reply_url = bbp_get_topic_permalink( $topic_id );

			// No topics either, so set $reply_url as empty
			} else {
				$reply_url = '';
			}
		}

		// Filter and return
		return apply_filters( 'bbp_get_forum_last_reply_url', $reply_url );
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
		$link_url   = bbp_get_forum_last_reply_url( $forum_id );
		$title      = bbp_get_forum_last_reply_title( $forum_id );
		$time_since = bbp_get_forum_last_active( $forum_id );

		if ( !empty( $time_since ) )
			$anchor = '<a href="' . $link_url . '" title="' . esc_attr( $title ) . '">' . $time_since . '</a>';
		else
			$anchor = __( 'No Topics', 'bbpress' );

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
		$author_link = bbp_get_user_profile_link( $author_id );
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
		$author_link = bbp_get_user_profile_link( $author_id );
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

		return apply_filters( 'bbp_get_forum_topic_count', (int) $topics, $forum_id );
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

		return apply_filters( 'bbp_get_forum_reply_count', (int) $replies, $forum_id );
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

		return apply_filters( 'bbp_get_forum_voice_count', (int) $voices, $forum_id );
	}

/**
 * bbp_forum_status ()
 *
 * Output the status of the forum in the loop
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 * @param int $forum_id optional
 *
 * @uses bbp_get_forum_status()
 */
function bbp_forum_status ( $forum_id = 0 ) {
	echo bbp_get_forum_status( $forum_id );
}
	/**
	 * bbp_get_forum_status ()
	 *
	 * Return the status of the forum in the loop
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @uses apply_filters
	 * @uses get_post_status()
	 * @param int $forum_id optional
	 *
	 * @return string Status of forum
	 */
	function bbp_get_forum_status ( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		return apply_filters( 'bbp_get_forum_status', get_post_meta( $forum_id, '_bbp_forum_status', true ) );
	}

/**
 * Closes a forum
 *
 * @since bbPress (r2744)
 *
 * @param int $forum_id forum id
 * @uses wp_get_single_post() To get the forum
 * @uses do_action() Calls 'bbp_close_forum' with the forum id
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_insert_post() To update the forum with the new status
 * @uses do_action() Calls 'bbp_opened_forum' with the forum id
 * @return mixed False or {@link WP_Error} on failure, forum id on success
 */
function bbp_close_forum( $forum_id = 0 ) {
	global $bbp;

	if ( !$forum = wp_get_single_post( $forum_id, ARRAY_A ) )
		return $forum;

	do_action( 'bbp_close_forum', $forum_id );

	update_post_meta( $forum_id, '_bbp_forum_status', 'closed' );

	do_action( 'bbp_closed_forum', $forum_id );

	return $forum_id;
}

/**
 * Opens a forum
 *
 * @since bbPress (r2744)
 *
 * @param int $forum_id forum id
 * @uses wp_get_single_post() To get the forum
 * @uses do_action() Calls 'bbp_open_forum' with the forum id
 * @uses get_post_meta() To get the previous status
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_insert_post() To update the forum with the new status
 * @uses do_action() Calls 'bbp_opened_forum' with the forum id
 * @return mixed False or {@link WP_Error} on failure, forum id on success
 */
function bbp_open_forum( $forum_id = 0 ) {
	global $bbp;

	if ( !$forum = wp_get_single_post( $forum_id, ARRAY_A ) )
		return $forum;

	do_action( 'bbp_open_forum', $forum_id );

	update_post_meta( $forum_id, '_bbp_forum_status', 'open' );

	do_action( 'bbp_opened_forum', $forum_id );

	return $forum_id;
}

/**
 * Make the forum a category
 *
 * @since bbPress (r2744)
 *
 * @param int $forum_id Optional. Forum id
 * @uses update_post_meta() To update the forum category meta
 * @return bool False on failure, true on success
 */
function bbp_categorize_forum( $forum_id = 0 ) {
	return update_post_meta( $forum_id, '_bbp_forum_type', 'category' );
}

/**
 * Remove the category status from a forum
 *
 * @since bbPress (r2744)
 *
 * @param int $forum_id Optional. Forum id
 * @uses delete_post_meta() To delete the forum category meta
 * @return bool False on failure, true on success
 */
function bbp_normalize_forum( $forum_id = 0 ) {
	return update_post_meta( $forum_id, '_bbp_forum_type', 'forum' );
}

/**
 * Mark the forum as private
 *
 * @since bbPress (r2744)
 *
 * @param int $forum_id Optional. Forum id
 * @uses update_post_meta() To update the forum private meta
 * @return bool False on failure, true on success
 */
function bbp_privatize_forum( $forum_id = 0 ) {
	return update_post_meta( $forum_id, '_bbp_forum_visibility', 'private' );
}

/**
 * Unmark the forum as private
 *
 * @since bbPress (r2744)
 *
 * @param int $forum_id Optional. Forum id
 * @uses delete_post_meta() To delete the forum private meta
 * @return bool False on failure, true on success
 */
function bbp_publicize_forum( $forum_id = 0 ) {
	return update_post_meta( $forum_id, '_bbp_forum_visibility', 'public' );
}

/**
 * Is the forum a category?
 *
 * @since bbPress (r2744)
 *
 * @param int $forum_id Optional. Forum id
 * @uses get_post_meta() To get the forum category meta
 * @return bool Whether the forum is a category or not
 */
function bbp_is_forum_category( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$type     = get_post_meta( $forum_id, '_bbp_forum_type', true );

	if ( !empty( $type ) && 'category' == $type )
		return true;

	return false;
}

/**
 * Is the forum open?
 *
 * @since bbPress (r2744)
 * @param int $forum_id Optional. Forum id
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_is_forum_closed() To check if the forum is closed or not
 * @return bool Whether the forum is open or not
 */
function bbp_is_forum_open( $forum_id = 0 ) {
	return !bbp_is_forum_closed( $forum_id );
}

	/**
	 * Is the forum closed?
	 *
	 * @since bbPress (r2744)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $check_ancestors Check if the ancestors are closed (only
	 *                               if they're a category)
	 * @uses bbp_get_forum_status() To get the forum status
	 * @uses bbp_get_forum_ancestors() To get the forum ancestors
	 * @uses bbp_is_forum_category() To check if the forum is a category
	 * @uses bbp_is_forum_closed() To check if the forum is closed
	 * @return bool True if closed, false if not
	 */
	function bbp_is_forum_closed( $forum_id = 0, $check_ancestors = true ) {
		global $bbp;

		$forum_id = bbp_get_forum_id( $forum_id );

		if ( $bbp->closed_status_id == bbp_get_forum_status( $forum_id ) )
			return true;

		if ( !empty( $check_ancestors ) ) {
			$ancestors = bbp_get_forum_ancestors( $forum_id );

			foreach ( (array) $ancestors as $ancestor ) {
				if ( bbp_is_forum_category( $ancestor, false ) && bbp_is_forum_closed( $ancestor, false ) )
					return true;
			}
		}

		return false;
	}

/**
 * Is the forum private?
 *
 * @since bbPress (r2744)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $check_ancestors Check if the ancestors are private (only if
 *                               they're a category)
 * @uses get_post_meta() To get the forum private meta
 * @uses bbp_get_forum_ancestors() To get the forum ancestors
 * @uses bbp_is_forum_category() To check if the forum is a category
 * @uses bbp_is_forum_closed() To check if the forum is closed
 * @return bool True if closed, false if not
 */
function bbp_is_forum_private( $forum_id = 0, $check_ancestors = true ) {
	global $bbp;

	$forum_id   = bbp_get_forum_id( $forum_id );
	$visibility = get_post_meta( $forum_id, '_bbp_forum_visibility', true );

	if ( !empty( $visibility ) && 'private' == $visibility )
		return true;

	if ( !empty( $check_ancestors ) ) {
		$ancestors = bbp_get_forum_ancestors( $forum_id );

		foreach ( (array) $ancestors as $ancestor ) {
			if ( bbp_is_forum_private( $ancestor, false ) )
				return true;
		}
	}

	return false;
}

/**
 * bbp_forum_class ()
 *
 * Output the row class of a forum
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2667)
 */
function bbp_forum_class ( $forum_id = 0 ) {
	echo bbp_get_forum_class( $forum_id );
}
	/**
	 * bbp_get_forum_class ()
	 *
	 * Return the row class of a forum
	 *
	 * @package bbPress
	 * @subpackage Template Tags
	 * @since bbPress (r2667)
	 *
	 * @global WP_Query $bbp->forum_query
	 * @param int $forum_id
	 * @return string
	 */
	function bbp_get_forum_class ( $forum_id = 0 ) {
		global $bbp;

		$alternate = $bbp->forum_query->current_post % 2 ? 'even' : 'odd';
		$status    = 'status-'  . bbp_get_forum_status();
		$post      = post_class( array( $alternate, $status ) );

		return apply_filters( 'bbp_get_forum_class', $post );
	}

/** Forum Updaters ************************************************************/

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
function bbp_update_forum_last_topic_id ( $forum_id = 0, $topic_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$topic_id = bbp_get_topic_id( $topic_id );

	// Update the last reply ID
	if ( !empty( $topic_id ) ) {
		update_post_meta( $forum_id, '_bbp_forum_last_topic_id', $topic_id );
		return true;
	}

	return false;
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
function bbp_update_forum_last_reply_id ( $forum_id = 0, $reply_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$reply_id = bbp_get_reply_id( $reply_id );

	// Update the last reply ID
	if ( !empty( $reply_id ) ) {
		update_post_meta( $forum_id, '_bbp_forum_last_reply_id', $reply_id );
		return true;
	}

	return false;
}

/**
 * bbp_update_forum_last_active ()
 *
 * Update the forums last active date/time (aka freshness)
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2680)
 *
 * @param int $forum_id optional
 *
 * @return string
 */
function bbp_update_forum_last_active ( $forum_id = 0, $new_time = '' ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Check time and use current if empty
	if ( empty( $new_time ) )
		$new_time = current_time( 'mysql' );

	// Update the last reply ID
	if ( !empty( $forum_id ) ) {
		update_post_meta( $forum_id, '_bbp_forum_last_active', $new_time );
		return true;
	}

	return false;
}

/**
 * bbp_update_forum_subforum_count ()
 *
 * Update the forum sub-forum count
 *
 * @todo Make this work.
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
		$forum_id = bbp_get_topic_forum_id( $forum_id );

	// Get topics count
	$topics = count( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->topic_id . "';", $forum_id ) ) );

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_topic_count', (int) $topics );

	return apply_filters( 'bbp_update_forum_topic_count', (int) $topics, $forum_id );
}

/**
 * bbp_update_forum_reply_count ()
 *
 * Adjust the total post count of a forum
 *
 * @todo Make this work
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_id()
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
	if ( $bbp->reply_id == get_post_field( 'post_type', $forum_id ) ) {
		$topic_id = bbp_get_reply_topic_id( $forum_id );
		$forum_id = bbp_get_topic_forum_id( $topic_id );
	}

	// There should always be at least 1 voice
	$replies = count( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "';", $forum_id ) ) );

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_reply_count', (int) $replies );

	return apply_filters( 'bbp_update_forum_reply_count', (int) $replies, $forum_id );
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
		$forum_id = bbp_get_topic_forum_id( $forum_id );

	// There should always be at least 1 voice
	if ( !$voices = count( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE ( post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "' ) OR ( ID = %d AND post_type = '" . $bbp->forum_id . "' );", $forum_id, $forum_id ) ) ) )
		$voices = 1;

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_voice_count', (int) $voices );

	return apply_filters( 'bbp_update_forum_voice_count', (int) $voices, $forum_id );
}

/** END - Forum Loop Functions ************************************************/

?>
