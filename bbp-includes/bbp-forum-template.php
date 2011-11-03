<?php

/**
 * bbPress Forum Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Post Type *****************************************************************/

/**
 * Output the unique id of the custom post type for forums
 *
 * @since bbPress (r2857)
 * @uses bbp_get_forum_post_type() To get the forum post type
 */
function bbp_forum_post_type() {
	echo bbp_get_forum_post_type();
}
	/**
	 * Return the unique id of the custom post type for forums
	 *
	 * @since bbPress (r2857)
	 *
	 * @uses apply_filters() Calls 'bbp_get_forum_post_type' with the forum
	 *                        post type id
	 * @return string The unique forum post type id
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
 * @uses bbp_get_forum_post_type() To get the forum post type id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses get_option() To get the forums per page option
 * @uses current_user_can() To check if the current user is capable of editing
 *                           others' forums
 * @uses apply_filters() Calls 'bbp_has_forums' with
 *                        bbPres::forum_query::have_posts()
 *                        and bbPres::forum_query
 * @return object Multidimensional array of forum information
 */
function bbp_has_forums( $args = '' ) {
	global $bbp;

	// Setup possible post__not_in array
	$post_stati[] = bbp_get_public_status_id();

	// Super admin get whitelisted post statuses
	if ( is_super_admin() ) {
		$post_stati = array( bbp_get_public_status_id(), bbp_get_private_status_id(), bbp_get_hidden_status_id() );

	// Not a super admin, so check caps
	} else {

		// Check if user can read private forums
		if ( current_user_can( 'read_private_forums' ) )
			$post_stati[] = bbp_get_private_status_id();

		// Check if user can read hidden forums
		if ( current_user_can( 'read_hidden_forums' ) )
			$post_stati[] = bbp_get_hidden_status_id();
	}

	// The default forum query for most circumstances
	$default = array (
		'post_type'      => bbp_get_forum_post_type(),
		'post_parent'    => bbp_is_forum_archive() ? 0 : bbp_get_forum_id() ,
		'post_status'    => implode( ',', $post_stati ),
		'posts_per_page' => get_option( '_bbp_forums_per_page', 50 ),
		'orderby'        => 'menu_order',
		'order'          => 'ASC'
	);

	// Parse the default against what is requested
	$bbp_f = wp_parse_args( $args, $default );

	// Filter the forums query to allow just-in-time modifications
	$bbp_f = apply_filters( 'bbp_has_forums_query', $bbp_f );

	// Run the query
	$bbp->forum_query = new WP_Query( $bbp_f );

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

	// Put into variable to check against next
	$have_posts = $bbp->forum_query->have_posts();

	// Reset the post data when finished
	if ( empty( $have_posts ) )
		wp_reset_postdata();

	return $have_posts;
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
	 * @uses bbp_is_single_forum() To check if it's a forum page
	 * @uses bbp_is_single_topic() To check if it's a topic page
	 * @uses bbp_get_topic_forum_id() To get the topic forum id
	 * @uses get_post_field() To get the post's post type
	 * @uses apply_filters() Calls 'bbp_get_forum_id' with the forum id and
	 *                        supplied forum id
	 * @return int The forum id
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
		elseif ( bbp_is_single_forum() && isset( $wp_query->post->ID ) )
			$bbp_forum_id = $bbp->current_forum_id = $wp_query->post->ID;

		// Currently viewing a topic
		elseif ( bbp_is_single_topic() )
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
 * Output the forum archive title
 *
 * @since bbPress (r3249)
 *
 * @param string $title Default text to use as title
 */
function bbp_forum_archive_title( $title = '' ) {
	echo bbp_get_forum_archive_title( $title );
}
	/**
	 * Return the forum archive title
	 *
	 * @since bbPress (r3249)
	 *
	 * @global bbPress $bbp The main bbPress class
	 * @param string $title Default text to use as title
	 *
	 * @uses bbp_get_page_by_path() Check if page exists at root path
	 * @uses get_the_title() Use the page title at the root path
	 * @uses get_post_type_object() Load the post type object
	 * @uses bbp_get_forum_post_type() Get the forum post type ID
	 * @uses get_post_type_labels() Get labels for forum post type
	 * @uses apply_filters() Allow output to be manipulated
	 *
	 * @return string The forum archive title
	 */
	function bbp_get_forum_archive_title( $title = '' ) {
		global $bbp;

		// If no title was passed
		if ( empty( $title ) ) {

			// Set root text to page title
			$page = bbp_get_page_by_path( $bbp->root_slug );
			if ( !empty( $page ) ) {
				$title = get_the_title( $page->ID );

			// Default to forum post type name label
			} else {
				$fto    = get_post_type_object( bbp_get_forum_post_type() );
				$title  = $fto->labels->name;
			}
		}

		return apply_filters( 'bbp_get_forum_archive_title', $title );
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
			$reply_id = bbp_get_forum_last_reply_id( $forum_id );
			if ( !empty( $reply_id ) ) {
				$last_active = get_post_field( 'post_date', $reply_id );
			} else {
				$topic_id = bbp_get_forum_last_topic_id( $forum_id );
				if ( !empty( $topic_id ) ) {
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
	 * @uses bbp_get_forum_last_active_id() To get the forum last active id
	 * @uses bbp_get_forum_last_reply_id() To get the forum last reply id
	 * @uses bbp_get_forum_last_topic_id() To get the forum last topic id
	 * @uses bbp_get_forum_last_reply_url() To get the forum last reply url
	 * @uses bbp_get_forum_last_reply_title() To get the forum last reply
	 *                                         title
	 * @uses bbp_get_forum_last_topic_permalink() To get the forum last
	 *                                             topic permalink
	 * @uses bbp_get_forum_last_topic_title() To get the forum last topic
	 *                                         title
	 * @uses bbp_get_forum_last_active_time() To get the time when the forum
	 *                                         was last active
	 * @uses apply_filters() Calls 'bbp_get_forum_freshness_link' with the
	 *                        link and forum id
	 */
	function bbp_get_forum_freshness_link( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$active_id = bbp_get_forum_last_active_id( $forum_id );

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
	$forum     = bbp_get_forum( $forum_id );

	if ( !empty( $forum ) ) {
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

	// Use passed integer as post_parent
	if ( is_numeric( $args ) )
		$args = array( 'post_parent' => $args );

	// Setup possible post__not_in array
	$post_stati[] = bbp_get_public_status_id();

	// Super admin get whitelisted post statuses
	if ( is_super_admin() ) {
		$post_stati = array( bbp_get_public_status_id(), bbp_get_private_status_id(), bbp_get_hidden_status_id() );

	// Not a super admin, so check caps
	} else {

		// Check if user can read private forums
		if ( current_user_can( 'read_private_forums' ) )
			$post_stati[] = bbp_get_private_status_id();

		// Check if user can read hidden forums
		if ( current_user_can( 'read_hidden_forums' ) )
			$post_stati[] = bbp_get_hidden_status_id();
	}

	$default = array(
		'post_parent'    => 0,
		'post_type'      => bbp_get_forum_post_type(),
		'post_status'    => implode( ',', $post_stati ),
		'posts_per_page' => get_option( '_bbp_forums_per_page', 50 ),
		'sort_column'    => 'menu_order, post_title',
		'order'          => 'ASC'
	);

	$r = wp_parse_args( $args, $default );
	$r['post_parent'] = bbp_get_forum_id( $r['post_parent'] );

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

	// Define used variables
	$output = $sub_forums = $topic_count = $reply_count = $counts = '';
	$i = 0;
	$count = array();

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

	// Bail if there are no subforums
	if ( !bbp_get_forum_subforum_count( $forum_id ) )
		return;

	// Loop through forums and create a list
	$sub_forums = bbp_forum_get_subforums( $forum_id );
	if ( !empty( $sub_forums ) ) {

		// Total count (for separator)
		$total_subs = count( $sub_forums );
		foreach ( $sub_forums as $sub_forum ) {
			$i++; // Separator count

			// Get forum details
			$count     = array();
			$show_sep  = $total_subs > $i ? $separator : '';
			$permalink = bbp_get_forum_permalink( $sub_forum->ID );
			$title     = bbp_get_forum_title( $sub_forum->ID );

			// Show topic count
			if ( !empty( $show_topic_count ) && !bbp_is_forum_category( $sub_forum->ID ) )
				$count['topic'] = bbp_get_forum_topic_count( $sub_forum->ID );

			// Show reply count
			if ( !empty( $show_reply_count ) && !bbp_is_forum_category( $sub_forum->ID ) )
				$count['reply'] = bbp_get_forum_reply_count( $sub_forum->ID );

			// Counts to show
			if ( !empty( $count ) )
				$counts = $count_before . implode( $count_sep, $count ) . $count_after;

			// Build this sub forums link
			$output .= $link_before . '<a href="' . $permalink . '" class="bbp-forum-link">' . $title . $counts . '</a>' . $show_sep . $link_after;
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
	 * @uses bbp_get_forum_last_topic_id() To get the forum's last topic id
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
		$reply_id = bbp_get_forum_last_reply_id( $forum_id );
		if ( !empty( $reply_id ) ) {
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
	 * @uses bbp_get_forum_topic_count_hidden() To get the forum hidden
	 *                                           topic count
	 * @uses current_user_can() To check if the current user can edit others
	 *                           topics
	 * @uses add_query_arg() To add custom args to the url
	 * @uses apply_filters() Calls 'bbp_get_forum_topics_link' with the
	 *                        topics link and forum id
	 */
	function bbp_get_forum_topics_link( $forum_id = 0 ) {

		$forum    = bbp_get_forum( bbp_get_forum_id( (int) $forum_id ) );
		$forum_id = $forum->ID;
		$topics   = bbp_get_forum_topic_count( $forum_id );
		$topics   = sprintf( _n( '%s topic', '%s topics', $topics, 'bbpress' ), $topics );
		$retval   = '';

		// First link never has view=all
		if ( bbp_get_view_all( 'edit_others_topics' ) )
			$retval .= "<a href='" . esc_url( bbp_remove_view_all( bbp_get_forum_permalink( $forum_id ) ) ) . "'>$topics</a>";
		else
			$retval .= $topics;

		// This forum has hidden topics
		if ( current_user_can( 'edit_others_topics' ) && ( $deleted = bbp_get_forum_topic_count_hidden( $forum_id ) ) ) {

			// Extra text
			$extra = sprintf( __( ' (+ %d hidden)', 'bbpress' ), $deleted );

			// No link
			if ( bbp_get_view_all() )
				$retval .= " $extra";

			// Link
			else
				$retval .= " <a href='" . esc_url( bbp_add_view_all( bbp_get_forum_permalink( $forum_id ), true ) ) . "'>$extra</a>";
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
		$topics   = get_post_meta( $forum_id, empty( $total_count ) ? '_bbp_topic_count' : '_bbp_total_topic_count', true );

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
 * @uses bbp_get_forum_topic_count_hidden() To get the forum hidden topic count
 */
function bbp_forum_topic_count_hidden( $forum_id = 0 ) {
	echo bbp_get_forum_topic_count_hidden( $forum_id );
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
	 * @uses apply_filters() Calls 'bbp_get_forum_topic_count_hidden' with
	 *                        the hidden topic count and forum id
	 * @return int Topic hidden topic count
	 */
	function bbp_get_forum_topic_count_hidden( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topics   = get_post_meta( $forum_id, '_bbp_topic_count_hidden', true );

		return apply_filters( 'bbp_get_forum_topic_count_hidden', (int) $topics, $forum_id );
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

		return apply_filters( 'bbp_get_forum_status', get_post_meta( $forum_id, '_bbp_status', true ), $forum_id );
	}

/**
 * Output the visibility of the forum
 *
 * @since bbPress (r2997)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_visibility() To get the forum visibility
 */
function bbp_forum_visibility( $forum_id = 0 ) {
	echo bbp_get_forum_visibility( $forum_id );
}
	/**
	 * Return the visibility of the forum
	 *
	 * @since bbPress (r2997)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses get_post_visibility() To get the forum's visibility
	 * @uses apply_filters() Calls 'bbp_get_forum_visibility' with the visibility
	 *                        and forum id
	 * @return string Status of forum
	 */
	function bbp_get_forum_visibility( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		return apply_filters( 'bbp_get_forum_visibility', get_post_status( $forum_id ), $forum_id );
	}

/**
 * Output the type of the forum
 *
 * @since bbPress (r3563)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_type() To get the forum type
 */
function bbp_forum_type( $forum_id = 0 ) {
	echo bbp_get_forum_type( $forum_id );
}
	/**
	 * Return the type of forum (category/forum/etc...)
	 *
	 * @since bbPress (r3563)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @uses get_post_meta() To get the forum category meta
	 * @return bool Whether the forum is a category or not
	 */
	function bbp_get_forum_type( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$retval   = get_post_meta( $forum_id, '_bbp_forum_type', true );

		return apply_filters( 'bbp_get_forum_type', $retval, $forum_id );
	}

/**
 * Is the forum a category?
 *
 * @since bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_type() To get the forum type
 * @return bool Whether the forum is a category or not
 */
function bbp_is_forum_category( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$type     = bbp_get_forum_type( $forum_id );
	$retval   = ( !empty( $type ) && 'category' == $type );

	return apply_filters( 'bbp_is_forum_category', (bool) $retval, $forum_id );
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

		$forum_id = bbp_get_forum_id( $forum_id );
		$retval    = ( bbp_get_closed_status_id() == bbp_get_forum_status( $forum_id ) );

		if ( !empty( $check_ancestors ) ) {
			$ancestors = bbp_get_forum_ancestors( $forum_id );

			foreach ( (array) $ancestors as $ancestor ) {
				if ( bbp_is_forum_category( $ancestor, false ) && bbp_is_forum_closed( $ancestor, false ) ) {
					$retval = true;
				}
			}
		}

		return apply_filters( 'bbp_is_forum_closed', (bool) $retval, $forum_id, $check_ancestors );
	}

/**
 * Is the forum public?
 *
 * @since bbPress (r2997)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $check_ancestors Check if the ancestors are public (only if
 *                               they're a category)
 * @uses get_post_meta() To get the forum public meta
 * @uses bbp_get_forum_ancestors() To get the forum ancestors
 * @uses bbp_is_forum_category() To check if the forum is a category
 * @uses bbp_is_forum_closed() To check if the forum is closed
 * @return bool True if closed, false if not
 */
function bbp_is_forum_public( $forum_id = 0, $check_ancestors = true ) {

	$forum_id   = bbp_get_forum_id( $forum_id );
	$visibility = bbp_get_forum_visibility( $forum_id );

	// If post status is public, return true
	$retval = ( bbp_get_public_status_id() == $visibility );

	// Check ancestors and inherit their privacy setting for display
	if ( !empty( $check_ancestors ) ) {
		$ancestors = bbp_get_forum_ancestors( $forum_id );

		foreach ( (array) $ancestors as $ancestor ) {
			if ( bbp_is_forum( $ancestor ) && bbp_is_forum_public( $ancestor, false ) ) {
				$retval = true;
			}
		}
	}

	return apply_filters( 'bbp_is_forum_public', (bool) $retval, $forum_id, $check_ancestors );
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

	$forum_id   = bbp_get_forum_id( $forum_id );
	$visibility = bbp_get_forum_visibility( $forum_id );

	// If post status is private, return true
	$retval = ( bbp_get_private_status_id() == $visibility );

	// Check ancestors and inherit their privacy setting for display
	if ( !empty( $check_ancestors ) ) {
		$ancestors = bbp_get_forum_ancestors( $forum_id );

		foreach ( (array) $ancestors as $ancestor ) {
			if ( bbp_is_forum( $ancestor ) && bbp_is_forum_private( $ancestor, false ) ) {
				$retval = true;
			}
		}
	}

	return apply_filters( 'bbp_is_forum_private', (bool) $retval, $forum_id, $check_ancestors );
}

/**
 * Is the forum hidden?
 *
 * @since bbPress (r2997)
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
function bbp_is_forum_hidden( $forum_id = 0, $check_ancestors = true ) {

	$forum_id   = bbp_get_forum_id( $forum_id );
	$visibility = bbp_get_forum_visibility( $forum_id );

	// If post status is private, return true
	$retval = ( bbp_get_hidden_status_id() == $visibility );

	// Check ancestors and inherit their privacy setting for display
	if ( !empty( $check_ancestors ) ) {
		$ancestors = bbp_get_forum_ancestors( $forum_id );

		foreach ( (array) $ancestors as $ancestor ) {
			if ( bbp_is_forum( $ancestor ) && bbp_is_forum_hidden( $ancestor, false ) ) {
				$retval = true;
			}
		}
	}

	return apply_filters( 'bbp_is_forum_hidden', (bool) $retval, $forum_id, $check_ancestors );
}

/**
 * Replace forum meta details for users that cannot view them.
 *
 * @since bbPress (r3162)
 *
 * @param string $retval
 * @param int $forum_id
 *
 * @uses bbp_is_forum_private()
 * @uses current_user_can()
 *
 * @return string
 */
function bbp_suppress_private_forum_meta( $retval, $forum_id ) {
	if ( bbp_is_forum_private( $forum_id, false ) && !current_user_can( 'read_private_forums' ) )
		$retval = '-';

	return apply_filters( 'bbp_suppress_private_forum_meta', $retval );
}

/**
 * Replace forum author details for users that cannot view them.
 *
 * @since bbPress (r3162)
 *
 * @param string $retval
 * @param int $forum_id
 *
 * @uses bbp_is_forum_private()
 * @uses get_post_field()
 * @uses bbp_get_topic_post_type()
 * @uses bbp_is_forum_private()
 * @uses bbp_get_topic_forum_id()
 * @uses bbp_get_reply_post_type()
 * @uses bbp_get_reply_forum_id()
 *
 * @return string
 */
function bbp_suppress_private_author_link( $author_link, $args ) {

	// Assume the author link is the return value
	$retval = $author_link;

	// Show the normal author link
	if ( !empty( $args['post_id'] ) && !current_user_can( 'read_private_forums' ) ) {

		// What post type are we looking at?
		$post_type = get_post_field( 'post_type', $args['post_id'] );

		switch ( $post_type ) {

			// Topic
			case bbp_get_topic_post_type() :
				if ( bbp_is_forum_private( bbp_get_topic_forum_id( $args['post_id'] ) ) )
					$retval = '';

				break;

			// Reply
			case bbp_get_reply_post_type() :
				if ( bbp_is_forum_private( bbp_get_reply_forum_id( $args['post_id'] ) ) )
					$retval = '';

				break;

			// Post
			default :
				if ( bbp_is_forum_private( $args['post_id'] ) )
					$retval = '';

				break;
		}
	}

	return apply_filters( 'bbp_suppress_private_author_link', $retval );
}

/**
 * Output the row class of a forum
 *
 * @since bbPress (r2667)
 *
 * @param int $forum_id Optional. Forum ID.
 * @uses bbp_get_forum_class() To get the row class of the forum
 */
function bbp_forum_class( $forum_id = 0 ) {
	echo bbp_get_forum_class( $forum_id );
}
	/**
	 * Return the row class of a forum
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $forum_id Optional. Forum ID
	 * @uses get_post_class() To get all the classes including ours
	 * @uses apply_filters() Calls 'bbp_get_forum_class' with the classes
	 * @return string Row class of the forum
	 */
	function bbp_get_forum_class( $forum_id = 0 ) {
		global $bbp;

		$forum_id  = bbp_get_forum_id( $forum_id );
		$count     = isset( $bbp->forum_query->current_post ) ? $bbp->forum_query->current_post : 1;
		$classes   = array();
		$classes[] = ( (int) $count % 2 )               ? 'even'            : 'odd';
		$classes[] = bbp_is_forum_category( $forum_id ) ? 'status-category' : '';
		$classes[] = bbp_is_forum_private( $forum_id )  ? 'status-private'  : '';
		$classes   = array_filter( $classes );
		$retval    = get_post_class( $classes, $forum_id );
		$retval    = 'class="' . join( ' ', $retval ) . '"';

		return apply_filters( 'bbp_get_forum_class', $retval, $forum_id );
	}

/** Single Forum **************************************************************/

/**
 * Output a fancy description of the current forum, including total topics,
 * total replies, and last activity.
 *
 * @since bbPress (r2860)
 *
 * @param array $args Arguments passed to alter output
 * @uses bbp_get_single_forum_description() Return the eventual output
 */
function bbp_single_forum_description( $args = '' ) {
	echo bbp_get_single_forum_description( $args );
}
	/**
	 * Return a fancy description of the current forum, including total
	 * topics, total replies, and last activity.
	 *
	 * @since bbPress (r2860)
	 *
	 * @param mixed $args This function supports these arguments:
	 *  - topic_id: Topic id
	 *  - before: Before the text
	 *  - after: After the text
	 *  - size: Size of the avatar
	 * @uses bbp_get_forum_id() To get the forum id
	 * @uses bbp_get_forum_topic_count() To get the forum topic count
	 * @uses bbp_get_forum_reply_count() To get the forum reply count
	 * @uses bbp_get_forum_subforum_count() To get the forum subforum count
	 * @uses bbp_get_forum_freshness_link() To get the forum freshness link
	 * @uses bbp_get_forum_last_active_id() To get the forum last active id
	 * @uses bbp_get_author_link() To get the author link
	 * @uses add_filter() To add the 'view all' filter back
	 * @uses apply_filters() Calls 'bbp_get_single_forum_description' with
	 *                        the description and args
	 * @return string Filtered forum description
	 */
	function bbp_get_single_forum_description( $args = '' ) {
		// Default arguments
		$defaults = array (
			'forum_id'  => 0,
			'before'    => '<div class="bbp-template-notice info"><p class="bbp-forum-description">',
			'after'     => '</p></div>',
			'size'      => 14,
			'feed'      => true
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

		// Singlular/Plural
		$reply_count     = sprintf( _n( '%s reply', '%s replies', $reply_count, 'bbpress' ), $reply_count );

		// Forum has posts
		$last_reply = bbp_get_forum_last_active_id( $forum_id );
		if ( !empty( $last_reply ) ) {

			// Freshness author
			$last_updated_by = bbp_get_author_link( array( 'post_id' => $last_reply, 'size' => $size ) );

			// Category
			if ( bbp_is_forum_category( $forum_id ) )
				$retstr = sprintf( __( 'This category contains %1$s and %2$s, and was last updated by %3$s %4$s ago.', 'bbpress' ), $topic_count, $reply_count, $last_updated_by, $time_since );

			// Forum
			else
				$retstr = sprintf( __( 'This forum contains %1$s and %2$s, and was last updated by %3$s %4$s ago.',    'bbpress' ), $topic_count, $reply_count, $last_updated_by, $time_since );

		// Forum has no last active data
		} else {

			// Category
			if ( bbp_is_forum_category( $forum_id ) )
				$retstr = sprintf( __( 'This category contains %1$s and %2$s.', 'bbpress' ), $topic_count, $reply_count );

			// Forum
			else
				$retstr = sprintf( __( 'This forum contains %1$s and %2$s.',    'bbpress' ), $topic_count, $reply_count );
		}

		// Add feeds
		$feed_links = ( !empty( $feed ) ) ? bbp_get_forum_topics_feed_link ( $forum_id ) . bbp_get_forum_replies_feed_link( $forum_id ) : '';

		// Add the 'view all' filter back
		add_filter( 'bbp_get_forum_permalink', 'bbp_add_view_all' );

		// Combine the elements together
		$retstr = $before . $retstr . $after;

		// Return filtered result
		return apply_filters( 'bbp_get_single_forum_description', $retstr, $args );
	}

/** Forms *********************************************************************/

/**
 * Output the value of forum title field
 *
 * @since bbPress (r3551)
 *
 * @uses bbp_get_form_forum_title() To get the value of forum title field
 */
function bbp_form_forum_title() {
	echo bbp_get_form_forum_title();
}
	/**
	 * Return the value of forum title field
	 *
	 * @since bbPress (r3551)
	 *
	 * @uses bbp_is_forum_edit() To check if it's forum edit page
	 * @uses apply_filters() Calls 'bbp_get_form_forum_title' with the title
	 * @return string Value of forum title field
	 */
	function bbp_get_form_forum_title() {
		global $post;

		// Get _POST data
		if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbp_forum_title'] ) )
			$forum_title = $_POST['bbp_forum_title'];

		// Get edit data
		elseif ( !empty( $post->post_title ) && bbp_is_forum_edit() )
			$forum_title = $post->post_title;

		// No data
		else
			$forum_title = '';

		return apply_filters( 'bbp_get_form_forum_title', esc_attr( $forum_title ) );
	}

/**
 * Output the value of forum content field
 *
 * @since bbPress (r3551)
 *
 * @uses bbp_get_form_forum_content() To get value of forum content field
 */
function bbp_form_forum_content() {
	echo bbp_get_form_forum_content();
}
	/**
	 * Return the value of forum content field
	 *
	 * @since bbPress (r3551)
	 *
	 * @uses bbp_is_forum_edit() To check if it's the forum edit page
	 * @uses apply_filters() Calls 'bbp_get_form_forum_content' with the content
	 * @return string Value of forum content field
	 */
	function bbp_get_form_forum_content() {
		global $post;

		// Get _POST data
		if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbp_forum_content'] ) )
			$forum_content = $_POST['bbp_forum_content'];

		// Get edit data
		elseif ( !empty( $post->post_content ) && bbp_is_forum_edit() )
			$forum_content = $post->post_content;

		// No data
		else
			$forum_content = '';

		return apply_filters( 'bbp_get_form_forum_content', esc_textarea( $forum_content ) );
	}

/**
 * Output value of forum parent
 *
 * @since bbPress (r3551)
 *
 * @uses bbp_get_form_forum_parent() To get the topic's forum id
 */
function bbp_form_forum_parent() {
	echo bbp_get_form_forum_parent();
}
	/**
	 * Return value of forum parent
	 *
	 * @since bbPress (r3551)
	 *
	 * @uses bbp_is_topic_edit() To check if it's the topic edit page
	 * @uses bbp_get_forum_parent_id() To get the topic forum id
	 * @uses apply_filters() Calls 'bbp_get_form_forum_parent' with the forum
	 * @return string Value of topic content field
	 */
	function bbp_get_form_forum_parent() {

		// Get _POST data
		if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbp_forum_id'] ) )
			$forum_parent = $_POST['bbp_forum_id'];

		// Get edit data
		elseif ( bbp_is_forum_edit() )
			$forum_parent = bbp_get_forum_parent_id();

		// No data
		else
			$forum_parent = 0;

		return apply_filters( 'bbp_get_form_forum_parent', esc_attr( $forum_parent ) );
	}

/**
 * Output value of forum type
 *
 * @since bbPress (r3563)
 *
 * @uses bbp_get_form_forum_type() To get the topic's forum id
 */
function bbp_form_forum_type() {
	echo bbp_get_form_forum_type();
}
	/**
	 * Return value of forum type
	 *
	 * @since bbPress (r3563)
	 *
	 * @uses bbp_is_topic_edit() To check if it's the topic edit page
	 * @uses bbp_get_forum_type_id() To get the topic forum id
	 * @uses apply_filters() Calls 'bbp_get_form_forum_type' with the forum
	 * @return string Value of topic content field
	 */
	function bbp_get_form_forum_type() {

		// Get _POST data
		if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbp_forum_type'] ) )
			$forum_type = $_POST['bbp_forum_type'];

		// Get edit data
		elseif ( bbp_is_forum_edit() )
			$forum_type = bbp_get_forum_type();

		// No data
		else
			$forum_type = 'forum';

		return apply_filters( 'bbp_get_form_forum_type', esc_attr( $forum_type ) );
	}

/**
 * Output value of forum visibility
 *
 * @since bbPress (r3563)
 *
 * @uses bbp_get_form_forum_visibility() To get the topic's forum id
 */
function bbp_form_forum_visibility() {
	echo bbp_get_form_forum_visibility();
}
	/**
	 * Return value of forum visibility
	 *
	 * @since bbPress (r3563)
	 *
	 * @uses bbp_is_topic_edit() To check if it's the topic edit page
	 * @uses bbp_get_forum_visibility_id() To get the topic forum id
	 * @uses apply_filters() Calls 'bbp_get_form_forum_visibility' with the forum
	 * @return string Value of topic content field
	 */
	function bbp_get_form_forum_visibility() {
		global $bbp;

		// Get _POST data
		if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbp_forum_visibility'] ) )
			$forum_visibility = $_POST['bbp_forum_visibility'];

		// Get edit data
		elseif ( bbp_is_forum_edit() )
			$forum_visibility = bbp_get_forum_visibility();

		// No data
		else
			$forum_visibility = $bbp->public_status_id;

		return apply_filters( 'bbp_get_form_forum_visibility', esc_attr( $forum_visibility ) );
	}

/** Form Dropdows *************************************************************/

/**
 * Output value forum type dropdown
 *
 * @since bbPress (r3563)
 *
 * @param int $forum_id The forum id to use
 * @uses bbp_get_form_forum_type() To get the topic's forum id
 */
function bbp_form_forum_type_dropdown( $forum_id = 0 ) {
	echo bbp_get_form_forum_type_dropdown( $forum_id );
}
	/**
	 * Return the forum type dropdown
	 *
	 * @since bbPress (r3563)
	 *
	 * @param int $forum_id The forum id to use
	 * @uses bbp_is_topic_edit() To check if it's the topic edit page
	 * @uses bbp_get_forum_type() To get the forum type
	 * @uses apply_filters()
	 * @return string HTML select list for selecting forum type
	 */
	function bbp_get_form_forum_type_dropdown( $forum_id = 0 ) {
		$forum_id   = bbp_get_forum_id( $forum_id );
		$forum_attr = apply_filters( 'bbp_forum_types', array(
			'forum'    => __( 'Forum',    'bbpress' ),
			'category' => __( 'Category', 'bbpress' )
		) );
		$type_output = '<select name="bbp_forum_type" id="bbp_forum_type_select">' . "\n";

		foreach( $forum_attr as $value => $label )
			$type_output .= "\t" . '<option value="' . $value . '"' . selected( bbp_get_forum_type( $forum_id ), $value, false ) . '>' . esc_html( $label ) . '</option>' . "\n";

		$type_output .= '</select>';

		return apply_filters( 'bbp_get_form_forum_type_dropdown', $type_output, $forum_id, $forum_attr );
	}

/**
 * Output value forum status dropdown
 *
 * @since bbPress (r3563)
 *
 * @param int $forum_id The forum id to use
 * @uses bbp_get_form_forum_status() To get the topic's forum id
 */
function bbp_form_forum_status_dropdown( $forum_id = 0 ) {
	echo bbp_get_form_forum_status_dropdown( $forum_id );
}
	/**
	 * Return the forum status dropdown
	 *
	 * @since bbPress (r3563)
	 *
	 * @param int $forum_id The forum id to use
	 * @uses bbp_is_topic_edit() To check if it's the topic edit page
	 * @uses bbp_get_forum_status() To get the forum status
	 * @uses apply_filters()
	 * @return string HTML select list for selecting forum status
	 */
	function bbp_get_form_forum_status_dropdown( $forum_id = 0 ) {
		$forum_id   = bbp_get_forum_id( $forum_id );
		$forum_attr = apply_filters( 'bbp_forum_statuses', array(
			'open'   => __( 'Open',   'bbpress' ),
			'closed' => __( 'Closed', 'bbpress' )
		) );
		$status_output = '<select name="bbp_forum_status" id="bbp_forum_status_select">' . "\n";

		foreach( $forum_attr as $value => $label )
			$status_output .= "\t" . '<option value="' . $value . '"' . selected( bbp_get_forum_status( $forum_id ), $value, false ) . '>' . esc_html( $label ) . '</option>' . "\n";

		$status_output .= '</select>';

		return apply_filters( 'bbp_get_form_forum_status_dropdown', $status_output, $forum_id, $forum_attr );
	}

/**
 * Output value forum visibility dropdown
 *
 * @since bbPress (r3563)
 *
 * @param int $forum_id The forum id to use
 * @uses bbp_get_form_forum_visibility() To get the topic's forum id
 */
function bbp_form_forum_visibility_dropdown( $forum_id = 0 ) {
	echo bbp_get_form_forum_visibility_dropdown( $forum_id );
}
	/**
	 * Return the forum visibility dropdown
	 *
	 * @since bbPress (r3563)
	 *
	 * @param int $forum_id The forum id to use
	 * @uses bbp_is_topic_edit() To check if it's the topic edit page
	 * @uses bbp_get_forum_visibility() To get the forum visibility
	 * @uses apply_filters()
	 * @return string HTML select list for selecting forum visibility
	 */
	function bbp_get_form_forum_visibility_dropdown( $forum_id = 0 ) {
		$forum_id   = bbp_get_forum_id( $forum_id );
		$forum_attr = apply_filters( 'bbp_forum_visibilities', array(
			bbp_get_public_status_id()  => __( 'Public',  'bbpress' ),
			bbp_get_private_status_id() => __( 'Private', 'bbpress' ),
			bbp_get_hidden_status_id()  => __( 'Hidden',  'bbpress' )
		) );
		$visibility_output = '<select name="bbp_forum_visibility" id="bbp_forum_visibility_select">' . "\n";

		foreach( $forum_attr as $value => $label )
			$visibility_output .= "\t" . '<option value="' . $value . '"' . selected( bbp_get_forum_visibility( $forum_id ), $value, false ) . '>' . esc_html( $label ) . '</option>' . "\n";

		$visibility_output .= '</select>';

		return apply_filters( 'bbp_get_form_forum_visibility_dropdown', $visibility_output, $forum_id, $forum_attr );
	}

/** Feeds *********************************************************************/

/**
 * Output the link for the forum feed
 *
 * @since bbPress (r3172)
 *
 * @param type $forum_id Optional. Forum ID.
 *
 * @uses bbp_get_forum_topics_feed_link()
 */
function bbp_forum_topics_feed_link( $forum_id = 0 ) {
	echo bbp_get_forum_topics_feed_link( $forum_id );
}
	/**
	 * Retrieve the link for the forum feed
	 *
	 * @since bbPress (r3172)
	 *
	 * @param int $forum_id Optional. Forum ID.
	 *
	 * @uses bbp_get_forum_id()
	 * @uses get_option()
	 * @uses trailingslashit()
	 * @uses bbp_get_forum_permalink()
	 * @uses user_trailingslashit()
	 * @uses bbp_get_forum_post_type()
	 * @uses get_post_field()
	 * @uses apply_filters()
	 *
	 * @return string
	 */
	function bbp_get_forum_topics_feed_link( $forum_id = 0 ) {

		// Validate forum id
		$forum_id = bbp_get_forum_id( $forum_id );

		// Forum is valid
		if ( !empty( $forum_id ) ) {

			// Define local variable(s)
			$link = '';

			// Pretty permalinks
			if ( get_option( 'permalink_structure' ) ) {

				// Forum link
				$url = trailingslashit( bbp_get_forum_permalink( $forum_id ) ) . 'feed';
				$url = user_trailingslashit( $url, 'single_feed' );

			// Unpretty permalinks
			} else {
				$url = home_url( add_query_arg( array(
					'feed'                    => 'rss2',
					bbp_get_forum_post_type() => get_post_field( 'post_name', $forum_id )
				) ) );
			}

			$link = '<a href="' . $url . '" class="bbp-forum-rss-link topics"><span>' . __( 'Topics', 'bbpress' ) . '</span></a>';
		}

		return apply_filters( 'bbp_get_forum_topics_feed_link', $link, $url, $forum_id );
	}

/**
 * Output the link for the forum replies feed
 *
 * @since bbPress (r3172)
 *
 * @param type $forum_id Optional. Forum ID.
 *
 * @uses bbp_get_forum_replies_feed_link()
 */
function bbp_forum_replies_feed_link( $forum_id = 0 ) {
	echo bbp_get_forum_replies_feed_link( $forum_id );
}
	/**
	 * Retrieve the link for the forum replies feed
	 *
	 * @since bbPress (r3172)
	 *
	 * @param int $forum_id Optional. Forum ID.
	 *
	 * @uses bbp_get_forum_id()
	 * @uses get_option()
	 * @uses trailingslashit()
	 * @uses bbp_get_forum_permalink()
	 * @uses user_trailingslashit()
	 * @uses bbp_get_forum_post_type()
	 * @uses get_post_field()
	 * @uses apply_filters()
	 *
	 * @return string
	 */
	function bbp_get_forum_replies_feed_link( $forum_id = 0 ) {

		// Validate forum id
		$forum_id = bbp_get_forum_id( $forum_id );

		// Forum is valid
		if ( !empty( $forum_id ) ) {

			// Define local variable(s)
			$link = '';

			// Pretty permalinks
			if ( get_option( 'permalink_structure' ) ) {

				// Forum link
				$url = trailingslashit( bbp_get_forum_permalink( $forum_id ) ) . 'feed';
				$url = user_trailingslashit( $url, 'single_feed' );
				$url = add_query_arg( array( 'type' => 'reply' ), $url );

			// Unpretty permalinks
			} else {
				$url = home_url( add_query_arg( array(
					'type'                    => 'reply',
					'feed'                    => 'rss2',
					bbp_get_forum_post_type() => get_post_field( 'post_name', $forum_id )
				) ) );
			}

			$link = '<a href="' . $url . '" class="bbp-forum-rss-link replies"><span>' . __( 'Replies', 'bbpress' ) . '</span></a>';
		}

		return apply_filters( 'bbp_get_forum_replies_feed_link', $link, $url, $forum_id );
	}

?>
