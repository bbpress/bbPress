<?php

/**
 * bbPress Forum Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

/** Post Type *****************************************************************/

/**
 * Return the unique ID of the custom post type for forums
 *
 * @since bbPress (r2857)
 *
 * @global bbPress $bbp
 * @return string
 */
function bbp_forum_post_type() {
	echo bbp_get_forum_post_type();
}
	/**
	 * Return the unique ID of the custom post type for forums
	 *
	 * @since bbPress (r2857)
	 *
	 * @global bbPress $bbp
	 * @return string
	 */
	function bbp_get_forum_post_type() {
		global $bbp;
		return apply_filters( 'bbp_get_forum_post_type', $bbp->forum_post_type );
	}

/** Forum Loop ****************************************************************/

/**
 * The main forum loop.
 *
 * WordPress makes this easy for us.
 *
 * @since bbPress (r2464)
 *
 * @param mixed $args All the arguments supported by {@link WP_Query}
 * @uses WP_Query To make query and get the forums
 * @uses current_user_can() To check if the current user is capable of editing
 *                           others' forums
 * @uses apply_filters() Calls 'bbp_has_forums' with
 *                        bbPres::forum_query::have_posts()
 *                        and bbPres::forum_query
 * @return object Multidimensional array of forum information
 */
function bbp_has_forums( $args = '' ) {
	global $wp_query, $bbp;

	$default = array (
		'post_type'      => bbp_get_forum_post_type(),
		'post_parent'    => bbp_get_forum_id(),
		'posts_per_page' => get_option( '_bbp_forums_per_page', 15 ),
		'orderby'        => 'menu_order',
		'order'          => 'ASC'
	);

	$r = wp_parse_args( $args, $default );

	// Allow all forums to be queried if post_parent is set to -1
	if ( -1 == $r['post_parent'] )
		unset( $r['post_parent'] );

	// Don't show private forums to normal users
	if ( !current_user_can( 'read_private_forums' ) && empty( $r['meta_key'] ) && empty( $r['meta_value'] ) ) {
		$r['meta_key']   = '_bbp_visibility';
		$r['meta_value'] = 'public';
	}

	$bbp->forum_query = new WP_Query( $r );

	return apply_filters( 'bbp_has_forums', $bbp->forum_query->have_posts(), $bbp->forum_query );
}

/**
 * Whether there are more forums available in the loop
 *
 * @since bbPress (r2464)
 *
 * @uses bbPress:forum_query::have_posts() To check if there are more forums
 *                                          available
 * @return object Forum information
 */
function bbp_forums() {
	global $bbp;
	return $bbp->forum_query->have_posts();
}

/**
 * Loads up the current forum in the loop
 *
 * @since bbPress (r2464)
 *
 * @uses bbPress:forum_query::the_post() To get the current forum
 * @return object Forum information
 */
function bbp_the_forum() {
	global $bbp;
	return $bbp->forum_query->the_post();
}

/** Forum *********************************************************************/

/**
 * Output forum id
 *
 * @since bbPress (r2464)
 *
 * @param $forum_id Optional. Used to check emptiness
 * @uses bbp_get_forum_id() To get the forum id
 */
function bbp_forum_id( $forum_id = 0 ) {
	echo bbp_get_forum_id( $forum_id );
}
	/**
	 * Return the forum id
	 *
	 * @since bbPress (r2464)
	 *
	 * @param $forum_id Optional. Used to check emptiness
	 * @uses bbPress::forum_query::in_the_loop To check if we're in the loop
	 * @uses bbPress::forum_query::post::ID To get the forum id
	 * @uses WP_Query::post::ID To get the forum id
	 * @uses bbp_is_forum() To check if it's a forum page
	 * @uses bbp_is_topic() To check if it's a topic page
	 * @uses bbp_get_topic_forum_id() To get the topic forum id
	 * @uses apply_filters() Calls 'bbp_get_forum_id' with the forum id
	 * @return int Forum id
	 */
	function bbp_get_forum_id( $forum_id = 0 ) {
		global $bbp, $wp_query;

		// Easy empty checking
		if ( !empty( $forum_id ) && is_numeric( $forum_id ) )
			$bbp_forum_id = $bbp->current_forum_id = $forum_id;

		// Currently inside a forum loop
		elseif ( !empty( $bbp->forum_query->in_the_loop ) && isset( $bbp->forum_query->post->ID ) )
			$bbp_forum_id = $bbp->current_forum_id = $bbp->forum_query->post->ID;

		// Currently viewing a forum
		elseif ( bbp_is_forum() && isset( $wp_query->post->ID ) )
			$bbp_forum_id = $bbp->current_forum_id = $wp_query->post->ID;

		// Currently viewing a topic
		elseif ( bbp_is_topic() )
			$bbp_forum_id = $bbp->current_forum_id = bbp_get_topic_forum_id();

		// Fallback
		else
			$bbp_forum_id = 0;

		return apply_filters( 'bbp_get_forum_id', (int) $bbp_forum_id, $forum_id );
	}

/**
 * Gets a forum
 *
 * @since bbPress (r2787)
 *
 * @param int|object $forum forum id or forum object
 * @param string $output Optional. OBJECT, ARRAY_A, or ARRAY_N. Default = OBJECT
 * @param string $filter Optional Sanitation filter. See {@link sanitize_post()}
 * @uses get_post() To get the forum
 * @uses apply_filters() Calls 'bbp_get_forum' with the forum, output type and
 *                        sanitation filter
 * @return mixed Null if error or forum (in specified form) if success
 */
function bbp_get_forum( $forum, $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $forum ) || is_numeric( $forum ) )
		$forum = bbp_get_forum_id( $forum );

	if ( !$forum = get_post( $forum, OBJECT, $filter ) )
		return $forum;

	if ( $forum->post_type !== bbp_get_forum_post_type() )
		return null;

	if ( $output == OBJECT ) {
		return $forum;

	} elseif ( $output == ARRAY_A ) {
		$_forum = get_object_vars( $forum );
		return $_forum;

	} elseif ( $output == ARRAY_N ) {
		$_forum = array_values( get_object_vars( $forum ) );
		return $_forum;

	}

	return apply_filters( 'bbp_get_forum', $forum, $output, $filter );
}

/**
 * Output the link to the forum
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_permalink() To get the permalink
 */
function bbp_forum_permalink( $forum_id = 0 ) {
	echo bbp_get_forum_permalink( $forum_id );
}
	/**
	 * Return the link to the forum
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_permalink() Get the permalink of the forum
	 * @uses apply_filters() Calls 'bbp_get_forum_permalink' with the forum
	 *                        link
	 * @return string Permanent link to forum
	 */
	function bbp_get_forum_permalink( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_permalink', get_permalink( $forum_id ) );
	}

/**
 * Output the title of the forum
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_title() To get the forum title
 */
function bbp_forum_title( $forum_id = 0 ) {
	echo bbp_get_forum_title( $forum_id );
}
	/**
	 * Return the title of the forum
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_the_title() To get the forum title
	 * @uses apply_filters() Calls 'bbp_get_forum_title' with the title
	 * @return string Title of forum
	 */
	function bbp_get_forum_title( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		return apply_filters( 'bbp_get_forum_title', get_the_title( $forum_id ) );
	}

/**
 * Output the content of the forum
 *
 * @since bbPress (r2780)
 *
 * @param int $forum_id Optional. Topic id
 * @uses bbp_get_forum_content() To get the forum content
 */
function bbp_forum_content( $forum_id = 0 ) {
	echo bbp_get_forum_content( $forum_id );
}
	/**
	 * Return the content of the forum
	 *
	 * @since bbPress (r2780)
	 *
	 * @param int $forum_id Optional. Topic id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses post_password_required() To check if the forum requires pass
	 * @uses get_the_password_form() To get the password form
	 * @uses get_post_field() To get the content post field
	 * @uses apply_filters() Calls 'bbp_get_forum_content' with the content
	 *                        and forum id
	 * @return string Content of the forum
	 */
	function bbp_get_forum_content( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		// Check if password is required
		if ( post_password_required( $forum_id ) )
			return get_the_password_form();

		$content = get_post_field( 'post_content', $forum_id );

		return apply_filters( 'bbp_get_forum_content', $content, $forum_id );
	}

/**
 * Output the forums last active ID
 *
 * @since bbPress (r2860)
 *
 * @uses bbp_get_forum_last_active_id() To get the forum's last active id
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_active_id( $forum_id = 0 ) {
	echo bbp_get_forum_last_active_id( $forum_id );
}
	/**
	 * Return the forums last active ID
	 *
	 * @since bbPress (r2860)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To get the forum's last active id
	 * @uses apply_filters() Calls 'bbp_get_forum_last_active_id' with
	 *                        the last active id and forum id
	 * @return int Forum's last active id
	 */
	function bbp_get_forum_last_active_id( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$active_id = get_post_meta( $forum_id, '_bbp_last_active_id', true );

		return apply_filters( 'bbp_get_forum_last_active_id', (int) $active_id, $forum_id );
	}

/**
 * Output the forums last update date/time (aka freshness)
 *
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_last_active_time() To get the forum freshness
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_active_time( $forum_id = 0 ) {
	echo bbp_get_forum_last_active_time( $forum_id );
}
	/**
	 * Return the forums last update date/time (aka freshness)
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To retrieve forum last active meta
	 * @uses bbp_get_forum_last_reply_id() To get forum's last reply id
	 * @uses get_post_field() To get the post date of the reply
	 * @uses bbp_get_forum_last_topic_id() To get forum's last topic id
	 * @uses bbp_get_topic_last_active_time() To get time when the topic was
	 *                                    last active
	 * @uses bbp_convert_date() To convert the date
	 * @uses bbp_get_time_since() To get time in since format
	 * @uses apply_filters() Calls 'bbp_get_forum_last_active' with last
	 *                        active time and forum id
	 * @return string Forum last update date/time (freshness)
	 */
	function bbp_get_forum_last_active_time( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		if ( !$last_active = get_post_meta( $forum_id, '_bbp_last_active_time', true ) ) {
			if ( $reply_id = bbp_get_forum_last_reply_id( $forum_id ) ) {
				$last_active = get_post_field( 'post_date', $reply_id );
			} else {
				if ( $topic_id = bbp_get_forum_last_topic_id( $forum_id ) ) {
					$last_active = bbp_get_topic_last_active_time( $topic_id );
				}
			}
		}

		$last_active = !empty( $last_active ) ? bbp_get_time_since( bbp_convert_date( $last_active ) ) : '';

		return apply_filters( 'bbp_get_forum_last_active', $last_active, $forum_id );
	}

/**
 * Output link to the most recent activity inside a forum.
 *
 * Outputs a complete link with attributes and content.
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_freshness_link() To get the forum freshness link
 */
function bbp_forum_freshness_link( $forum_id = 0) {
	echo bbp_get_forum_freshness_link( $forum_id );
}
	/**
	 * Returns link to the most recent activity inside a forum.
	 *
	 * Returns a complete link with attributes and content.
	 *
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_reply_url() To get the forum last reply url
	 * @uses bbp_get_forum_last_reply_title() To get the forum last reply
	 *                                         title
	 * @uses bbp_get_forum_last_active_time() To get the time when the forum was
	 *                                    last active
	 * @uses apply_filters() Calls 'bbp_get_forum_freshness_link' with the
	 *                        link and forum id
	 */
	function bbp_get_forum_freshness_link( $forum_id = 0 ) {
		$forum_id   = bbp_get_forum_id( $forum_id );
		$active_id  = bbp_get_forum_last_active_id( $forum_id );

		if ( empty( $active_id ) )
			$active_id = bbp_get_forum_last_reply_id( $forum_id );

		if ( empty( $active_id ) )
			$active_id = bbp_get_forum_last_topic_id( $forum_id );

		if ( bbp_is_topic( $active_id ) ) {
			$link_url = bbp_get_forum_last_topic_permalink( $forum_id );
			$title    = bbp_get_forum_last_topic_title( $forum_id );
		} elseif ( bbp_is_reply( $active_id ) ) {
			$link_url = bbp_get_forum_last_reply_url( $forum_id );
			$title    = bbp_get_forum_last_reply_title( $forum_id );
		}

		$time_since = bbp_get_forum_last_active_time( $forum_id );

		if ( !empty( $time_since ) && !empty( $link_url ) )
			$anchor = '<a href="' . $link_url . '" title="' . esc_attr( $title ) . '">' . $time_since . '</a>';
		else
			$anchor = __( 'No Topics', 'bbpress' );

		return apply_filters( 'bbp_get_forum_freshness_link', $anchor, $forum_id );
	}

/**
 * Return ID of forum parent, if exists
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses get_post_field() To get the forum parent
 * @uses apply_filters() Calls 'bbp_get_forum_parent' with the parent & forum id
 * @return int Forum parent
 */
function bbp_get_forum_parent( $forum_id = 0 ) {
	$forum_id  = bbp_get_forum_id( $forum_id );
	$parent_id = get_post_field( 'post_parent', $forum_id );

	return apply_filters( 'bbp_get_forum_parent', (int) $parent_id, $forum_id );
}

/**
 * Return array of parent forums
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_forum() To get the forum
 * @uses apply_filters() Calls 'bbp_get_forum_ancestors' with the ancestors
 *                        and forum id
 * @return array Forum ancestors
 */
function bbp_get_forum_ancestors( $forum_id = 0 ) {
	$forum_id  = bbp_get_forum_id( $forum_id );
	$ancestors = array();

	if ( $forum = bbp_get_forum( $forum_id ) ) {
		while ( 0 !== $forum->post_parent ) {
			$ancestors[] = $forum->post_parent;
			$forum       = bbp_get_forum( $forum->post_parent );
		}
	}

	return apply_filters( 'bbp_get_forum_ancestors', $ancestors, $forum_id );
}

/**
 * Return subforums of given forum
 *
 * @since bbPress (r2747)
 *
 * @param mixed $args All the arguments supported by {@link WP_Query}
 * @uses bbp_get_forum_id() To get the forum id
 * @uses current_user_can() To check if the current user is capable of
 *                           reading private forums
 * @uses get_posts() To get the subforums
 * @uses apply_filters() Calls 'bbp_forum_get_subforums' with the subforums
 *                        and the args
 * @return mixed false if none, array of subs if yes
 */
function bbp_forum_get_subforums( $args = '' ) {
	if ( is_numeric( $args ) )
		$args = array( 'post_parent' => $args );

	$default = array(
		'post_parent'    => 0,
		'post_type'      => bbp_get_forum_post_type(),
		'posts_per_page' => get_option( '_bbp_forums_per_page', 15 ),
		'sort_column'    => 'menu_order, post_title'
	);

	$r = wp_parse_args( $args, $default );

	$r['post_parent'] = bbp_get_forum_id( $r['post_parent'] );

	// Don't show private forums to normal users
	if ( !current_user_can( 'read_private_forums' ) && empty( $r['meta_key'] ) && empty( $r['meta_value'] ) ) {
		$r['meta_key']   = '_bbp_visibility';
		$r['meta_value'] = 'public';
	}

	// No forum passed
	$sub_forums = !empty( $r['post_parent'] ) ? get_posts( $r ) : '';

	return apply_filters( 'bbp_forum_get_sub_forums', (array) $sub_forums, $args );
}

/**
 * Output a list of forums (can be used to list subforums)
 *
 * @param mixed $args The function supports these args:
 *  - before: To put before the output. Defaults to '<ul class="bbp-forums">'
 *  - after: To put after the output. Defaults to '</ul>'
 *  - link_before: To put before every link. Defaults to '<li class="bbp-forum">'
 *  - link_after: To put after every link. Defaults to '</li>'
 *  - separator: Separator. Defaults to ', '
 *  - forum_id: Forum id. Defaults to ''
 *  - show_topic_count - To show forum topic count or not. Defaults to true
 *  - show_reply_count - To show forum reply count or not. Defaults to true
 * @uses bbp_forum_get_subforums() To check if the forum has subforums or not
 * @uses bbp_get_forum_permalink() To get forum permalink
 * @uses bbp_get_forum_title() To get forum title
 * @uses bbp_is_forum_category() To check if a forum is a category
 * @uses bbp_get_forum_topic_count() To get forum topic count
 * @uses bbp_get_forum_reply_count() To get forum reply count
 */
function bbp_list_forums( $args = '' ) {
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
		'count_before'      => ' (',
		'count_after'       => ')',
		'count_sep'         => ', ',
		'separator'         => ', ',
		'forum_id'          => '',
		'show_topic_count'  => true,
		'show_reply_count'  => true,
	);
	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	// Loop through forums and create a list
	if ( $sub_forums = bbp_forum_get_subforums( $forum_id ) ) {
		// Total count (for separator)
		$total_subs = count( $sub_forums );
		foreach( $sub_forums as $sub_forum ) {
			$i++; // Separator count

			// Get forum details
			$show_sep  = $total_subs > $i ? $separator : '';
			$permalink = bbp_get_forum_permalink( $sub_forum->ID );
			$title     = bbp_get_forum_title( $sub_forum->ID );

			// Show topic and reply counts
			if ( !empty( $show_topic_count ) && !bbp_is_forum_category( $sub_forum->ID ) )
				$count['topic'] = bbp_get_forum_topic_count( $sub_forum->ID );

			if ( !empty( $show_reply_count ) && !bbp_is_forum_category( $sub_forum->ID ) )
				$count['reply'] = bbp_get_forum_reply_count( $sub_forum->ID );

			$output .= $link_before . '<a href="' . $permalink . '" class="bbp-forum-link">' . $title . $count_before . implode( $count_sep, $count ) . $count_after . '</a>' . $show_sep . $link_after;
		}

		// Output the list
		echo $before . $output . $after;
	}
}

/** Forum Last Topic **********************************************************/

/**
 * Output the forum's last topic id
 *
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_last_topic_id() To get the forum's last topic id
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_topic_id( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_id( $forum_id );
}
	/**
	 * Return the forum's last topic id
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To get the forum's last topic id
	 * @uses apply_filters() Calls 'bbp_get_forum_last_topic_id' with the
	 *                        forum and topic id
	 * @return int Forum's last topic id
	 */
	function bbp_get_forum_last_topic_id( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topic_id = get_post_meta( $forum_id, '_bbp_last_topic_id', true );

		return apply_filters( 'bbp_get_forum_last_topic_id', (int) $topic_id, $forum_id );
	}

/**
 * Output the title of the last topic inside a forum
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_last_topic_title() To get the forum's last topic's title
 */
function bbp_forum_last_topic_title( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_title( $forum_id );
}
	/**
	 * Return the title of the last topic inside a forum
	 *
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_topic_id() To get the forum's last topic id
	 * @uses bbp_get_topic_title() To get the topic's title
	 * @uses apply_filters() Calls 'bbp_get_forum_last_topic_title' with the
	 *                        topic title and forum id
	 * @return string Forum's last topic's title
	 */
	function bbp_get_forum_last_topic_title( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_topic_title', bbp_get_topic_title( bbp_get_forum_last_topic_id( $forum_id ) ), $forum_id );
	}

/**
 * Output the link to the last topic in a forum
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_last_topic_permalink() To get the forum's last topic's
 *                                             permanent link
 */
function bbp_forum_last_topic_permalink( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_permalink( $forum_id );
}
	/**
	 * Return the link to the last topic in a forum
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_topic_id() To get the forum's last topic id
	 * @uses bbp_get_topic_permalink() To get the topic's permalink
	 * @uses apply_filters() Calls 'bbp_get_forum_last_topic_permalink' with
	 *                        the topic link and forum id
	 * @return string Permanent link to topic
	 */
	function bbp_get_forum_last_topic_permalink( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_topic_permalink', bbp_get_topic_permalink( bbp_get_forum_last_topic_id( $forum_id ) ), $forum_id );
	}

/**
 * Return the author ID of the last topic of a forum
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_forum_last_topic_id() To get the forum's last topic id
 * @uses bbp_get_topic_author_id() To get the topic's author id
 * @uses apply_filters() Calls 'bbp_get_forum_last_topic_author' with the author
 *                        id and forum id
 * @return int Forum's last topic's author id
 */
function bbp_get_forum_last_topic_author_id( $forum_id = 0 ) {
	$forum_id  = bbp_get_forum_id( $forum_id );
	$author_id = bbp_get_topic_author_id( bbp_get_forum_last_topic_id( $forum_id ) );
	return apply_filters( 'bbp_get_forum_last_topic_author_id', (int) $author_id, $forum_id );
}

/**
 * Output link to author of last topic of forum
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_last_topic_author_link() To get the forum's last topic's
 *                                               author link
 */
function bbp_forum_last_topic_author_link( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_author_link( $forum_id );
}
	/**
	 * Return link to author of last topic of forum
	 *
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_topic_author_id() To get the forum's last
	 *                                             topic's author id
	 * @uses bbp_get_user_profile_link() To get the author's profile link
	 * @uses apply_filters() Calls 'bbp_get_forum_last_topic_author_link'
	 *                        with the author link and forum id
	 * @return string Forum's last topic's author link
	 */
	function bbp_get_forum_last_topic_author_link( $forum_id = 0 ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$author_id   = bbp_get_forum_last_topic_author_id( $forum_id );
		$author_link = bbp_get_user_profile_link( $author_id );
		return apply_filters( 'bbp_get_forum_last_topic_author_link', $author_link, $forum_id );
	}

/** Forum Last Reply **********************************************************/

/**
 * Output the forums last reply id
 *
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_last_reply_id() To get the forum's last reply id
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_reply_id( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_id( $forum_id );
}
	/**
	 * Return the forums last reply id
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To get the forum's last reply id
	 * @uses apply_filters() Calls 'bbp_get_forum_last_reply_id' with
	 *                        the last reply id and forum id
	 * @return int Forum's last reply id
	 */
	function bbp_get_forum_last_reply_id( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$reply_id = get_post_meta( $forum_id, '_bbp_last_reply_id', true );

		if ( empty( $reply_id ) )
			$reply_id = bbp_get_forum_last_topic_id( $forum_id );

		return apply_filters( 'bbp_get_forum_last_reply_id', (int) $reply_id, $forum_id );
	}

/**
 * Output the title of the last reply inside a forum
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_last_reply_title() To get the forum's last reply's title
 */
function bbp_forum_last_reply_title( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_title( $forum_id );
}
	/**
	 * Return the title of the last reply inside a forum
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_reply_id() To get the forum's last reply id
	 * @uses bbp_get_reply_title() To get the reply title
	 * @uses apply_filters() Calls 'bbp_get_forum_last_reply_title' with the
	 *                        reply title and forum id
	 * @return string
	 */
	function bbp_get_forum_last_reply_title( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_reply_title', bbp_get_reply_title( bbp_get_forum_last_reply_id( $forum_id ) ), $forum_id );
	}

/**
 * Output the link to the last reply in a forum
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_last_reply_permalink() To get the forum last reply link
 */
function bbp_forum_last_reply_permalink( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_permalink( $forum_id );
}
	/**
	 * Return the link to the last reply in a forum
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_reply_id() To get the forum's last reply id
	 * @uses bbp_get_reply_permalink() To get the reply permalink
	 * @uses apply_filters() Calls 'bbp_get_forum_last_reply_permalink' with
	 *                        the reply link and forum id
	 * @return string Permanent link to the forum's last reply
	 */
	function bbp_get_forum_last_reply_permalink( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		return apply_filters( 'bbp_get_forum_last_reply_permalink', bbp_get_reply_permalink( bbp_get_forum_last_reply_id( $forum_id ) ), $forum_id );
	}

/**
 * Output the url to the last reply in a forum
 *
 * @since bbPress (r2683)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_last_reply_url() To get the forum last reply url
 */
function bbp_forum_last_reply_url( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_url( $forum_id );
}
	/**
	 * Return the url to the last reply in a forum
	 *
	 * @since bbPress (r2683)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_reply_id() To get the forum's last reply id
	 * @uses bbp_get_reply_url() To get the reply url
	 * @uses bbp_get_forum_last_topic_permalink() To get the forum's last
	 *                                             topic's permalink
	 * @uses apply_filters() Calls 'bbp_get_forum_last_reply_url' with the
	 *                        reply url and forum id
	 * @return string Paginated URL to latest reply
	 */
	function bbp_get_forum_last_reply_url( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		// If forum has replies, get the last reply and use its url
		if ( $reply_id = bbp_get_forum_last_reply_id( $forum_id ) ) {
			$reply_url = bbp_get_reply_url( $reply_id );

		// No replies, so look for topics and use last permalink
		} else {
			if ( !$reply_url = bbp_get_forum_last_topic_permalink( $forum_id ) ) {
				// No topics either, so set $reply_url as empty
				$reply_url = '';
			}
		}

		// Filter and return
		return apply_filters( 'bbp_get_forum_last_reply_url', $reply_url, $forum_id );
	}

/**
 * Output author ID of last reply of forum
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_last_reply_author_id() To get the forum's last reply
 *                                             author id
 */
function bbp_forum_last_reply_author_id( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_author_id( $forum_id );
}
	/**
	 * Return author ID of last reply of forum
	 *
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_reply_author_id() To get the forum's last
	 *                                             reply's author id
	 * @uses bbp_get_reply_author_id() To get the reply's author id
	 * @uses apply_filters() Calls 'bbp_get_forum_last_reply_author_id' with
	 *                        the author id and forum id
	 * @return int Forum's last reply author id
	 */
	function bbp_get_forum_last_reply_author_id( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$author_id = bbp_get_reply_author_id( bbp_get_forum_last_reply_id( $forum_id ) );
		return apply_filters( 'bbp_get_forum_last_reply_author_id', $author_id, $forum_id );
	}

/**
 * Output link to author of last reply of forum
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_last_reply_author_link() To get the forum's last reply's
 *                                               author link
 */
function bbp_forum_last_reply_author_link( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_author_link( $forum_id );
}
	/**
	 * Return link to author of last reply of forum
	 *
	 * @since bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_last_reply_author_id() To get the forum's last
	 *                                             reply's author id
	 * @uses bbp_get_user_profile_link() To get the reply's author's profile
	 *                                    link
	 * @uses apply_filters() Calls 'bbp_get_forum_last_reply_author_link'
	 *                        with the author link and forum id
	 * @return string Link to author of last reply of forum
	 */
	function bbp_get_forum_last_reply_author_link( $forum_id = 0 ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$author_id   = bbp_get_forum_last_reply_author_id( $forum_id );
		$author_link = bbp_get_user_profile_link( $author_id );
		return apply_filters( 'bbp_get_forum_last_reply_author_link', $author_link, $forum_id );
	}

/** Forum Counts **************************************************************/

/**
 * Output the topics link of the forum
 *
 * @since bbPress (r2883)
 *
 * @param int $forum_id Optional. Topic id
 * @uses bbp_get_forum_topics_link() To get the forum topics link
 */
function bbp_forum_topics_link( $forum_id = 0 ) {
	echo bbp_get_forum_topics_link( $forum_id );
}

	/**
	 * Return the topics link of the forum
	 *
	 * @since bbPress (r2883)
	 *
	 * @param int $forum_id Optional. Topic id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum() To get the forum
	 * @uses bbp_get_forum_topic_count() To get the forum topic count
	 * @uses bbp_get_forum_permalink() To get the forum permalink
	 * @uses remove_query_arg() To remove args from the url
	 * @uses bbp_get_forum_hidden_topic_count() To get the forum hidden
	 *                                           topic count
	 * @uses current_user_can() To check if the current user can edit others
	 *                           topics
	 * @uses add_query_arg() To add custom args to the url
	 * @uses apply_filters() Calls 'bbp_get_forum_topics_link' with the
	 *                        topics link and forum id
	 */
	function bbp_get_forum_topics_link( $forum_id = 0 ) {
		global $bbp;

		$forum    = bbp_get_forum( bbp_get_forum_id( (int) $forum_id ) );
		$forum_id = $forum->ID;
		$topics   = bbp_get_forum_topic_count( $forum_id );
		$topics   = sprintf( _n( '%s topic', '%s topics', $topics, 'bbpress' ), $topics );
		$retval   = '';

		if ( !empty( $_GET['view'] ) && 'all' == $_GET['view'] && current_user_can( 'edit_others_topics' ) )
			$retval .= "<a href='" . esc_url( remove_query_arg( array( 'view' => 'all' ),  bbp_get_forum_permalink( $forum_id ) ) ) . "'>$topics</a>";
		else
			$retval .= $topics;

		if ( current_user_can( 'edit_others_topics' ) && $deleted = bbp_get_forum_hidden_topic_count( $forum_id ) ) {
			$extra = sprintf( __( ' + %d more', 'bbpress' ), $deleted );
			if ( !empty( $_GET['view'] ) && 'all' == $_GET['view'] )
				$retval .= " $extra";
			else
				$retval .= " <a href='" . esc_url( add_query_arg( array( 'view' => 'all' ) ) ) . "'>$extra</a>";
		}

		return apply_filters( 'bbp_get_forum_topics_link', $retval, $forum_id );
	}

/**
 * Output total sub-forum count of a forum
 *
 * @since bbPress (r2464)
 *
 * @uses bbp_get_forum_subforum_count() To get the forum's subforum count
 * @param int $forum_id Optional. Forum id to check
 */
function bbp_forum_subforum_count( $forum_id = 0 ) {
	echo bbp_get_forum_subforum_count( $forum_id );
}
	/**
	 * Return total subforum count of a forum
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To get the subforum count
	 * @uses apply_filters() Calls 'bbp_get_forum_subforum_count' with the
	 *                        subforum count and forum id
	 * @return int Forum's subforum count
	 */
	function bbp_get_forum_subforum_count( $forum_id = 0 ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$forum_count = get_post_meta( $forum_id, '_bbp_forum_subforum_count', true );

		return apply_filters( 'bbp_get_forum_subforum_count', (int) $forum_count, $forum_id );
	}

/**
 * Output total topic count of a forum
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $total_count Optional. To get the total count or normal count?
 * @uses bbp_get_forum_topic_count() To get the forum topic count
 */
function bbp_forum_topic_count( $forum_id = 0, $total_count = true ) {
	echo bbp_get_forum_topic_count( $forum_id );
}
	/**
	 * Return total topic count of a forum
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $total_count Optional. To get the total count or normal
	 *                           count? Defaults to total.
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To get the forum topic count
	 * @uses apply_filters() Calls 'bbp_get_forum_topic_count' with the
	 *                        topic count and forum id
	 * @return int Forum topic count
	 */
	function bbp_get_forum_topic_count( $forum_id = 0, $total_count = true ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topics   = get_post_meta( $forum_id, empty( $total_count ) ? '_bbp_forum_topic_count' : '_bbp_total_topic_count', true );

		return apply_filters( 'bbp_get_forum_topic_count', (int) $topics, $forum_id );
	}

/**
 * Output total reply count of a forum
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $total_count Optional. To get the total count or normal count?
 * @uses bbp_get_forum_reply_count() To get the forum reply count
 */
function bbp_forum_reply_count( $forum_id = 0, $total_count = true ) {
	echo bbp_get_forum_reply_count( $forum_id, $total_count );
}
	/**
	 * Return total post count of a forum
	 *
	 * @since bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $total_count Optional. To get the total count or normal
	 *                           count?
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To get the forum reply count
	 * @uses apply_filters() Calls 'bbp_get_forum_reply_count' with the
	 *                        reply count and forum id
	 * @return int Forum reply count
	 */
	function bbp_get_forum_reply_count( $forum_id = 0, $total_count = true ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$replies  = get_post_meta( $forum_id, empty( $total_count ) ? '_bbp_reply_count' : '_bbp_total_reply_count', true );

		return apply_filters( 'bbp_get_forum_reply_count', (int) $replies, $forum_id );
	}

/**
 * Output total post count of a forum
 *
 * @since bbPress (r2954)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $total_count Optional. To get the total count or normal count?
 * @uses bbp_get_forum_post_count() To get the forum post count
 */
function bbp_forum_post_count( $forum_id = 0, $total_count = true ) {
	echo bbp_get_forum_post_count( $forum_id, $total_count );
}
	/**
	 * Return total post count of a forum
	 *
	 * @since bbPress (r2954)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $total_count Optional. To get the total count or normal
	 *                           count?
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To get the forum post count
	 * @uses apply_filters() Calls 'bbp_get_forum_post_count' with the
	 *                        post count and forum id
	 * @return int Forum post count
	 */
	function bbp_get_forum_post_count( $forum_id = 0, $total_count = true ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topics   = bbp_get_forum_topic_count( $forum_id, $total_count );
		$replies  = get_post_meta( $forum_id, empty( $total_count ) ? '_bbp_reply_count' : '_bbp_total_reply_count', true );

		return apply_filters( 'bbp_get_forum_post_count', (int) $replies + (int) $topics, $forum_id );
	}

/**
 * Output total hidden topic count of a forum (hidden includes trashed and
 * spammed topics)
 *
 * @since bbPress (r2883)
 *
 * @param int $forum_id Optional. Topic id
 * @uses bbp_get_forum_hidden_topic_count() To get the forum hidden topic count
 */
function bbp_forum_hidden_topic_count( $forum_id = 0 ) {
	echo bbp_get_forum_hidden_topic_count( $forum_id );
}
	/**
	 * Return total hidden topic count of a forum (hidden includes trashed
	 * and spammed topics)
	 *
	 * @since bbPress (r2883)
	 *
	 * @param int $forum_id Optional. Topic id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_meta() To get the hidden topic count
	 * @uses apply_filters() Calls 'bbp_get_forum_hidden_topic_count' with
	 *                        the hidden topic count and forum id
	 * @return int Topic hidden topic count
	 */
	function bbp_get_forum_hidden_topic_count( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topics   = get_post_meta( $forum_id, '_bbp_topic_count_hidden', true );

		return apply_filters( 'bbp_get_forum_hidden_topic_count', (int) $topics, $forum_id );
	}

/**
 * Output the status of the forum
 *
 * @since bbPress (r2667)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_status() To get the forum status
 */
function bbp_forum_status( $forum_id = 0 ) {
	echo bbp_get_forum_status( $forum_id );
}
	/**
	 * Return the status of the forum
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_status() To get the forum's status
	 * @uses apply_filters() Calls 'bbp_get_forum_status' with the status
	 *                        and forum id
	 * @return string Status of forum
	 */
	function bbp_get_forum_status( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		return apply_filters( 'bbp_get_forum_status', get_post_meta( $forum_id, '_bbp_status', true ) );
	}

/**
 * Is the forum a category?
 *
 * @since bbPress (r2746)
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
 * @since bbPress (r2746)
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
	 * @since bbPress (r2746)
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
 * @since bbPress (r2746)
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
	$visibility = get_post_meta( $forum_id, '_bbp_visibility', true );

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
 * Output the row class of a forum
 *
 * @since bbPress (r2667)
 *
 * @uses bbp_get_forum_class() To get the row class of the forum
 */
function bbp_forum_class() {
	echo bbp_get_forum_class();
}
	/**
	 * Return the row class of a forum
	 *
	 * @since bbPress (r2667)
	 *
	 * @uses post_class() To get all the classes including ours
	 * @uses apply_filters() Calls 'bbp_get_forum_class' with the classes
	 * @return string Row class of the forum
	 */
	function bbp_get_forum_class() {
		global $bbp;

		$classes   = array();
		$classes[] = $bbp->forum_query->current_post % 2 ? 'even' : 'odd';
		$classes[] = bbp_is_forum_category() ? 'status-category' : '';
		$classes[] = bbp_is_forum_private()  ? 'status-private'  : '';
		$classes   = array_filter( $classes );

		$post      = post_class( $classes );

		return apply_filters( 'bbp_get_forum_class', $post );
	}

/** Single Forum **************************************************************/

/**
 * Output a fancy description of the current forum, including total topics,
 * total replies, and last activity.
 *
 * @since bbPress (r2860)
 *
 * @uses bbp_get_single_forum_description() Return the eventual output
 *
 * @param arr $args Arguments passed to alter output
 */
function bbp_single_forum_description( $args = '' ) {
	echo bbp_get_single_forum_description( $args );
}
	/**
	 * Return a fancy description of the current forum, including total topics,
	 * total replies, and last activity.
	 *
	 * @since bbPress (r2860)
	 *
	 * @uses wp_parse_args()
	 * @uses bbp_get_forum_id()
	 * @uses bbp_get_forum_topic_count()
	 * @uses bbp_get_forum_reply_count()
	 * @uses bbp_get_forum_subforum_count()
	 * @uses bbp_get_forum_freshness_link()
	 * @uses bbp_get_forum_last_reply_id()
	 * @uses bbp_get_reply_author_avatar()
	 * @uses bbp_get_reply_author_link()
	 * @uses apply_filters()
	 *
	 * @param arr $args Arguments passed to alter output
	 *
	 * @return string Filtered forum description
	 */
	function bbp_get_single_forum_description( $args = '' ) {
		// Default arguments
		$defaults = array (
			'forum_id'  => 0,
			'before'    => '<div class="bbp-template-notice info"><p class="post-meta description">',
			'after'     => '</p></div>',
			'size'      => 14
		);
		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		// Validate forum_id
		$forum_id = bbp_get_forum_id( $forum_id );

		// Unhook the 'view all' query var adder
		remove_filter( 'bbp_get_forum_permalink', 'bbp_add_view_all' );

		// Build the forum description
		$topic_count     = bbp_get_forum_topics_link   ( $forum_id );
		$reply_count     = bbp_get_forum_reply_count   ( $forum_id );
		$subforum_count  = bbp_get_forum_subforum_count( $forum_id );
		$time_since      = bbp_get_forum_freshness_link( $forum_id );

		// Forum has posts
		if ( $last_reply = bbp_get_forum_last_active_id( $forum_id ) ) {
			$last_updated_by = bbp_get_author_link( array( 'post_id' => $last_reply, 'size' => $size ) );
			$retstr = sprintf( __( 'This forum contains %s and %s replies, and was last updated by %s %s ago.', 'bbpress' ), $topic_count, $reply_count, $last_updated_by, $time_since );

		// Forum has no last active data
		} else {
			$retstr = sprintf( __( 'This forum contains %s and %s replies.', 'bbpress' ), $topic_count, $reply_count );
		}

		// Add the 'view all' filter back
		add_filter( 'bbp_get_forum_permalink', 'bbp_add_view_all' );

		// Combine the elements together
		$retstr = $before . $retstr . $after;

		// Return filtered result
		return apply_filters( 'bbp_get_single_forum_description', $retstr, $args );
	}

?>
