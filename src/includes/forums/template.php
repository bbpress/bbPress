<?php

/**
 * bbPress Forum Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Post Type *****************************************************************/

/**
 * Output the unique id of the custom post type for forums
 *
 * @since 2.0.0 bbPress (r2857)
 */
function bbp_forum_post_type() {
	echo bbp_get_forum_post_type();
}
	/**
	 * Return the unique id of the custom post type for forums
	 *
	 * @since 2.0.0 bbPress (r2857)
	 *
	 * @return string The unique forum post type id
	 */
	function bbp_get_forum_post_type() {

		// Filter & return
		return apply_filters( 'bbp_get_forum_post_type', bbpress()->forum_post_type );
	}


/**
 * Return array of labels used by the forum post type
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_forum_post_type_labels() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_post_type_labels', array(
		'name'                     => esc_attr__( 'Forums',                     'bbpress' ),
		'menu_name'                => esc_attr__( 'Forums',                     'bbpress' ),
		'singular_name'            => esc_attr__( 'Forum',                      'bbpress' ),
		'all_items'                => esc_attr__( 'All Forums',                 'bbpress' ),
		'add_new'                  => esc_attr__( 'Add New',                    'bbpress' ),
		'add_new_item'             => esc_attr__( 'Create New Forum',           'bbpress' ),
		'edit'                     => esc_attr__( 'Edit',                       'bbpress' ),
		'edit_item'                => esc_attr__( 'Edit Forum',                 'bbpress' ),
		'new_item'                 => esc_attr__( 'New Forum',                  'bbpress' ),
		'view'                     => esc_attr__( 'View Forum',                 'bbpress' ),
		'view_item'                => esc_attr__( 'View Forum',                 'bbpress' ),
		'view_items'               => esc_attr__( 'View Forums',                'bbpress' ),
		'search_items'             => esc_attr__( 'Search Forums',              'bbpress' ),
		'not_found'                => esc_attr__( 'No forums found',            'bbpress' ),
		'not_found_in_trash'       => esc_attr__( 'No forums found in Trash',   'bbpress' ),
		'filter_items_list'        => esc_attr__( 'Filter forums list',         'bbpress' ),
		'items_list'               => esc_attr__( 'Forums list',                'bbpress' ),
		'items_list_navigation'    => esc_attr__( 'Forums list navigation',     'bbpress' ),
		'parent_item_colon'        => esc_attr__( 'Parent Forum:',              'bbpress' ),
		'archives'                 => esc_attr__( 'Forums',                     'bbpress' ),
		'attributes'               => esc_attr__( 'Forum Attributes',           'bbpress' ),
		'insert_into_item'         => esc_attr__( 'Insert into forum',          'bbpress' ),
		'uploaded_to_this_item'    => esc_attr__( 'Uploaded to this forum',     'bbpress' ),
		'featured_image'           => esc_attr__( 'Forum Image',                'bbpress' ),
		'set_featured_image'       => esc_attr__( 'Set forum image',            'bbpress' ),
		'remove_featured_image'    => esc_attr__( 'Remove forum image',         'bbpress' ),
		'use_featured_image'       => esc_attr__( 'Use as forum image',         'bbpress' ),
		'item_published'           => esc_attr__( 'Forum published.',           'bbpress' ),
		'item_published_privately' => esc_attr__( 'Forum published privately.', 'bbpress' ),
		'item_reverted_to_draft'   => esc_attr__( 'Forum reverted to draft.',   'bbpress' ),
		'item_scheduled'           => esc_attr__( 'Forum scheduled.',           'bbpress' ),
		'item_updated'             => esc_attr__( 'Forum updated.',             'bbpress' )
	) );
}

/**
 * Return array of forum post type rewrite settings
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_forum_post_type_rewrite() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_post_type_rewrite', array(
		'slug'       => bbp_get_forum_slug(),
		'with_front' => false
	) );
}

/**
 * Return array of features the forum post type supports
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_forum_post_type_supports() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_post_type_supports', array(
		'title',
		'editor',
		'revisions'
	) );
}

/** Forum Loop ****************************************************************/

/**
 * The main forum loop.
 *
 * WordPress makes this easy for us.
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param array $args All the arguments supported by {@link WP_Query}
 *
 * @return object Multidimensional array of forum information
 */
function bbp_has_forums( $args = array() ) {

	// Forum archive only shows root
	if ( bbp_is_forum_archive() ) {
		$default_post_parent = 0;

	// User subscriptions shows any
	} elseif ( bbp_is_subscriptions() ) {
		$default_post_parent = 'any';

	// Could be anything, so look for possible parent ID
	} else {
		$default_post_parent = bbp_get_forum_id();
	}

	$default_forum_search = bbp_sanitize_search_request( 'fs' );

	// Default argument array
	$default = array(
		'post_type'           => bbp_get_forum_post_type(),
		'post_parent'         => $default_post_parent,
		'post_status'         => bbp_get_public_status_id(),
		'posts_per_page'      => get_option( '_bbp_forums_per_page', 50 ),
		'orderby'             => 'menu_order title',
		'order'               => 'ASC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,

		// Conditionally prime the cache for last active posts
		'update_post_family_cache' => true
	);

	// Only add 's' arg if searching for forums
	// See https://bbpress.trac.wordpress.org/ticket/2607
	if ( ! empty( $default_forum_search ) ) {
		$default['s'] = $default_forum_search;
	}

	// Parse arguments with default forum query for most circumstances
	$r = bbp_parse_args( $args, $default, 'has_forums' );

	// Run the query
	$bbp              = bbpress();
	$bbp->forum_query = new WP_Query( $r );

	// Maybe prime last active posts
	if ( ! empty( $r['update_post_family_cache'] ) ) {
		bbp_update_post_family_caches( $bbp->forum_query->posts );
	}

	// Filter & return
	return apply_filters( 'bbp_has_forums', $bbp->forum_query->have_posts(), $bbp->forum_query );
}

/**
 * Whether there are more forums available in the loop
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @return object Forum information
 */
function bbp_forums() {

	// Put into variable to check against next
	$have_posts = bbpress()->forum_query->have_posts();

	// Reset the post data when finished
	if ( empty( $have_posts ) ) {
		wp_reset_postdata();
	}

	return $have_posts;
}

/**
 * Loads up the current forum in the loop
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @return object Forum information
 */
function bbp_the_forum() {
	return bbpress()->forum_query->the_post();
}

/** Forum *********************************************************************/

/**
 * Output forum id
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param $forum_id Optional. Used to check emptiness
 */
function bbp_forum_id( $forum_id = 0 ) {
	echo bbp_get_forum_id( $forum_id );
}
	/**
	 * Return the forum id
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param $forum_id Optional. Used to check emptiness
	 * @return int The forum id
	 */
	function bbp_get_forum_id( $forum_id = 0 ) {
		$bbp      = bbpress();
		$wp_query = bbp_get_wp_query();

		// Easy empty checking
		if ( ! empty( $forum_id ) && is_numeric( $forum_id ) ) {
			$bbp_forum_id = $forum_id;

		// Currently inside a forum loop
		} elseif ( ! empty( $bbp->forum_query->in_the_loop ) && isset( $bbp->forum_query->post->ID ) ) {
			$bbp_forum_id = $bbp->forum_query->post->ID;

		// Currently inside a search loop
		} elseif ( ! empty( $bbp->search_query->in_the_loop ) && isset( $bbp->search_query->post->ID ) && bbp_is_forum( $bbp->search_query->post->ID ) ) {
			$bbp_forum_id = $bbp->search_query->post->ID;

		// Currently viewing a forum
		} elseif ( ( bbp_is_single_forum() || bbp_is_forum_edit() ) && ! empty( $bbp->current_forum_id ) ) {
			$bbp_forum_id = $bbp->current_forum_id;

		// Currently viewing a forum
		} elseif ( ( bbp_is_single_forum() || bbp_is_forum_edit() ) && isset( $wp_query->post->ID ) ) {
			$bbp_forum_id = $wp_query->post->ID;

		// Currently viewing a topic
		} elseif ( bbp_is_single_topic() ) {
			$bbp_forum_id = bbp_get_topic_forum_id();

		// Fallback
		} else {
			$bbp_forum_id = 0;
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_forum_id', (int) $bbp_forum_id, $forum_id );
	}

/**
 * Gets a forum
 *
 * @since 2.0.0 bbPress (r2787)
 *
 * @param int|object $forum forum id or forum object
 * @param string $output Optional. OBJECT, ARRAY_A, or ARRAY_N. Default = OBJECT
 * @param string $filter Optional Sanitation filter. See {@link sanitize_post()}
 *
 * @return mixed Null if error or forum (in specified form) if success
 */
function bbp_get_forum( $forum, $output = OBJECT, $filter = 'raw' ) {

	// Maybe get ID from empty or int
	if ( empty( $forum ) || is_numeric( $forum ) ) {
		$forum = bbp_get_forum_id( $forum );
	}

	// Bail if no post object
	$forum = get_post( $forum, OBJECT, $filter );
	if ( empty( $forum ) ) {
		return $forum;
	}

	// Bail if not correct post type
	if ( $forum->post_type !== bbp_get_forum_post_type() ) {
		return null;
	}

	// Default return value is OBJECT
	$retval = $forum;

	// Array A
	if ( $output === ARRAY_A ) {
		$retval = get_object_vars( $forum );

	// Array N
	} elseif ( $output === ARRAY_N ) {
		$retval = array_values( get_object_vars( $forum ) );
	}

	// Filter & return
	return apply_filters( 'bbp_get_forum', $retval, $forum, $output, $filter );
}

/**
 * Output the link to the forum
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @param string $redirect_to Optional. Pass a redirect value for use with
 *                              shortcodes and other fun things.
 */
function bbp_forum_permalink( $forum_id = 0, $redirect_to = '' ) {
	echo esc_url( bbp_get_forum_permalink( $forum_id, $redirect_to ) );
}
	/**
	 * Return the link to the forum
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param string $redirect_to Optional. Pass a redirect value for use with
	 *                              shortcodes and other fun things.
	 *
	 * @return string Permanent link to forum
	 */
	function bbp_get_forum_permalink( $forum_id = 0, $redirect_to = '' ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		// Use the redirect address
		if ( ! empty( $redirect_to ) ) {
			$forum_permalink = esc_url_raw( $redirect_to );

		// Use the topic permalink
		} else {
			$forum_permalink = get_permalink( $forum_id );
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_permalink', $forum_permalink, $forum_id );
	}

/**
 * Output the title of the forum
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_title( $forum_id = 0 ) {
	echo bbp_get_forum_title( $forum_id );
}
	/**
	 * Return the title of the forum
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Title of forum
	 */
	function bbp_get_forum_title( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$title    = get_the_title( $forum_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_title', $title, $forum_id );
	}

/**
 * Output the forum archive title
 *
 * @since 2.0.0 bbPress (r3249)
 *
 * @param string $title Default text to use as title
 */
function bbp_forum_archive_title( $title = '' ) {
	echo bbp_get_forum_archive_title( $title );
}
	/**
	 * Return the forum archive title
	 *
	 * @since 2.0.0 bbPress (r3249)
	 *
	 * @param string $title Default text to use as title
	 *
	 * @return string The forum archive title
	 */
	function bbp_get_forum_archive_title( $title = '' ) {

		// If no title was passed
		if ( empty( $title ) ) {

			// Set root text to page title
			$page = bbp_get_page_by_path( bbp_get_root_slug() );
			if ( ! empty( $page ) ) {
				$title = get_the_title( $page->ID );

			// Default to forum post type name label
			} else {
				$fto    = get_post_type_object( bbp_get_forum_post_type() );
				$title  = $fto->labels->name;
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_archive_title', $title );
	}

/**
 * Output the content of the forum
 *
 * @since 2.0.0 bbPress (r2780)
 *
 * @param int $forum_id Optional. Topic id
 */
function bbp_forum_content( $forum_id = 0 ) {
	echo bbp_get_forum_content( $forum_id );
}
	/**
	 * Return the content of the forum
	 *
	 * @since 2.0.0 bbPress (r2780)
	 *
	 * @param int $forum_id Optional. Topic id
	 *
	 * @return string Content of the forum
	 */
	function bbp_get_forum_content( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		// Check if password is required
		if ( post_password_required( $forum_id ) ) {
			return get_the_password_form();
		}

		$content = get_post_field( 'post_content', $forum_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_content', $content, $forum_id );
	}

/**
 * Allow forum rows to have administrative actions
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @todo Links and filter
 */
function bbp_forum_row_actions() {
	do_action( 'bbp_forum_row_actions' );
}

/**
 * Output the forums last active ID
 *
 * @since 2.0.0 bbPress (r2860)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_active_id( $forum_id = 0 ) {
	echo bbp_get_forum_last_active_id( $forum_id );
}
	/**
	 * Return the forums last active ID
	 *
	 * @since 2.0.0 bbPress (r2860)
	 *
	 * @param int $forum_id Optional. Forum id
	 *                        the last active id and forum id
	 * @return int Forum's last active id
	 */
	function bbp_get_forum_last_active_id( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$active_id = (int) get_post_meta( $forum_id, '_bbp_last_active_id', true );

		// Filter & return
		return (int) apply_filters( 'bbp_get_forum_last_active_id', $active_id, $forum_id );
	}

/**
 * Output the forums last update date/time (aka freshness)
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_active_time( $forum_id = 0 ) {
	echo bbp_get_forum_last_active_time( $forum_id );
}
	/**
	 * Return the forums last update date/time (aka freshness)
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Forum last update date/time (freshness)
	 */
	function bbp_get_forum_last_active_time( $forum_id = 0 ) {

		// Verify forum and get last active meta
		$forum_id    = bbp_get_forum_id( $forum_id );
		$last_active = get_post_meta( $forum_id, '_bbp_last_active_time', true );

		if ( empty( $last_active ) ) {
			$reply_id = bbp_get_forum_last_reply_id( $forum_id );
			if ( ! empty( $reply_id ) ) {
				$last_active = get_post_field( 'post_date', $reply_id );
			} else {
				$topic_id = bbp_get_forum_last_topic_id( $forum_id );
				if ( ! empty( $topic_id ) ) {
					$last_active = bbp_get_topic_last_active_time( $topic_id );
				}
			}
		}

		$active_time = ! empty( $last_active ) ? bbp_get_time_since( bbp_convert_date( $last_active ) ) : '';

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_active', $active_time, $forum_id );
	}

/**
 * Output link to the most recent activity inside a forum.
 *
 * Outputs a complete link with attributes and content.
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_freshness_link( $forum_id = 0) {
	echo bbp_get_forum_freshness_link( $forum_id );
}
	/**
	 * Returns link to the most recent activity inside a forum.
	 *
	 * Returns a complete link with attributes and content.
	 *
	 * @since 2.0.0 bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 */
	function bbp_get_forum_freshness_link( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$active_id = bbp_get_forum_last_active_id( $forum_id );
		$link_url  = $title = '';

		if ( empty( $active_id ) ) {
			$active_id = bbp_get_forum_last_reply_id( $forum_id );
		}

		if ( empty( $active_id ) ) {
			$active_id = bbp_get_forum_last_topic_id( $forum_id );
		}

		if ( bbp_is_topic( $active_id ) ) {
			$link_url = bbp_get_forum_last_topic_permalink( $forum_id );
			$title    = bbp_get_forum_last_topic_title( $forum_id );
		} elseif ( bbp_is_reply( $active_id ) ) {
			$link_url = bbp_get_forum_last_reply_url( $forum_id );
			$title    = bbp_get_forum_last_reply_title( $forum_id );
		}

		$time_since = bbp_get_forum_last_active_time( $forum_id );

		if ( ! empty( $time_since ) && ! empty( $link_url ) ) {
			$anchor = '<a href="' . esc_url( $link_url ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $time_since ) . '</a>';
		} else {
			$anchor = esc_html__( 'No Topics', 'bbpress' );
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_freshness_link', $anchor, $forum_id, $time_since, $link_url, $title, $active_id );
	}

/**
 * Output parent ID of a forum, if exists
 *
 * @since 2.1.0 bbPress (r3675)
 *
 * @param int $forum_id Forum ID
 */
function bbp_forum_parent_id( $forum_id = 0 ) {
	echo bbp_get_forum_parent_id( $forum_id );
}
	/**
	 * Return ID of forum parent, if exists
	 *
	 * @since 2.1.0 bbPress (r3675)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return int Forum parent
	 */
	function bbp_get_forum_parent_id( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$parent_id = (int) get_post_field( 'post_parent', $forum_id );

		// Meta-data fallback
		if ( empty( $parent_id ) ) {
			$parent_id = (int) get_post_meta( $forum_id, '_bbp_forum_id', true );
		}

		// Filter
		if ( ! empty( $parent_id ) ) {
			$parent_id = (int) bbp_get_forum_id( $parent_id );
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_forum_parent_id', $parent_id, $forum_id );
	}

/**
 * Return array of parent forums
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @return array Forum ancestors
 */
function bbp_get_forum_ancestors( $forum_id = 0 ) {
	$forum_id  = bbp_get_forum_id( $forum_id );
	$ancestors = array();
	$forum     = bbp_get_forum( $forum_id );

	if ( ! empty( $forum ) ) {
		while ( 0 !== (int) $forum->post_parent ) {
			$ancestors[] = $forum->post_parent;
			$forum       = bbp_get_forum( $forum->post_parent );
		}
	}

	// Filter & return
	return apply_filters( 'bbp_get_forum_ancestors', $ancestors, $forum_id );
}

/**
 * Return subforums of given forum
 *
 * @since 2.0.0 bbPress (r2747)
 *
 * @param array $args All the arguments supported by {@link WP_Query}
 * @return mixed false if none, array of subs if yes
 */
function bbp_forum_get_subforums( $args = array() ) {

	// Default return value
	$retval = array();

	// Use passed integer as post_parent
	if ( is_numeric( $args ) && ! empty( $args ) ) {
		$args = array( 'post_parent' => bbp_get_forum_id( $args ) );
	}

	// Parse arguments against default values
	$r = bbp_parse_args( $args, array(
		'post_parent'         => 0,
		'post_type'           => bbp_get_forum_post_type(),
		'posts_per_page'      => get_option( '_bbp_forums_per_page', 50 ),
		'orderby'             => 'menu_order title',
		'order'               => 'ASC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true
	), 'forum_get_subforums' );

	// Ensure post_parent is properly set
	$r['post_parent'] = bbp_get_forum_id( $r['post_parent'] );

	// Query if post_parent has subforums
	if ( ! empty( $r['post_parent'] ) && bbp_get_forum_subforum_count( $r['post_parent'], true ) ) {
		$get_posts = new WP_Query();
		$retval    = $get_posts->query( $r );
	}

	// Filter & return
	return (array) apply_filters( 'bbp_forum_get_subforums', $retval, $r, $args );
}

/**
 * Output a list of forums (can be used to list subforums)
 *
 * @since 2.0.0 bbPress (r2708)
 *
 * @param array $args The function supports these args:
 *  - before: To put before the output. Defaults to '<ul class="bbp-forums-list">'
 *  - after: To put after the output. Defaults to '</ul>'
 *  - link_before: To put before every link. Defaults to '<li class="bbp-forum">'
 *  - link_after: To put after every link. Defaults to '</li>'
 *  - sep: Separator. Defaults to ''. Make sure your markup is valid!
 *  - count_before: String before each count Defaults to ' ('
 *  - count_after: String before each count Defaults to ')'
 *  - count_sep: Count separator. Defaults to ', '
 *  - forum_id: Forum id. Defaults to ''
 *  - show_topic_count - To show forum topic count or not. Defaults to true
 *  - show_reply_count - To show forum reply count or not. Defaults to true
 */
function bbp_list_forums( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args( $args, array(
		'before'           => '<ul class="bbp-forums-list">',
		'after'            => '</ul>',
		'link_before'      => '<li class="bbp-forum css-sep">',
		'link_after'       => '</li>',
		'sep'              => '',
		'count_before'     => ' (',
		'count_after'      => ')',
		'count_sep'        => ', ',
		'forum_id'         => bbp_get_forum_id(),
		'show_topic_count' => true,
		'show_reply_count' => true,
		'echo'             => true,

		// Retired, use 'sep' instead
		'separator'        => false
	), 'list_forums' );

	/**
	 * Necessary for backwards compatibility
	 * @see https://bbpress.trac.wordpress.org/ticket/2900
	 */
	if ( ! empty( $r['separator'] ) ) {
		$r['sep'] = $r['separator'];
	}

	// Default values
	$links  = array();
	$output = '';

	// Query for subforums
	$sub_forums = ! empty( $r['forum_id'] )
		? bbp_forum_get_subforums( $r['forum_id'] )
		: array();

	// Loop through forums and create a list
	if ( ! empty( $sub_forums ) ) {
		foreach ( $sub_forums as $sub_forum ) {

			// Get forum details
			$count     = array();
			$permalink = bbp_get_forum_permalink( $sub_forum->ID );
			$title     = bbp_get_forum_title( $sub_forum->ID );

			// Show topic count
			if ( ! empty( $r['show_topic_count'] ) && ! bbp_is_forum_category( $sub_forum->ID ) ) {
				$count['topic'] = bbp_get_forum_topic_count( $sub_forum->ID );
			}

			// Show reply count
			if ( ! empty( $r['show_reply_count'] ) && ! bbp_is_forum_category( $sub_forum->ID ) ) {
				$count['reply'] = bbp_get_forum_reply_count( $sub_forum->ID );
			}

			// Counts to show
			$counts = ! empty( $count )
				? $r['count_before'] . implode( $r['count_sep'], $count ) . $r['count_after']
				: '';

			// Subforum classes
			$subforum_classes      = array( 'bbp-forum-link' );
			$subforum_classes      = apply_filters( 'bbp_list_forums_subforum_classes', $subforum_classes, $sub_forum->ID );

			// This could use bbp_get_forum_class() eventually...
			$subforum_classes_attr = 'class="' . implode( ' ', array_map( 'sanitize_html_class', $subforum_classes ) ) . '"';

			// Build this sub forums link
			$links[] = $r['link_before'] . '<a href="' . esc_url( $permalink ) . '" ' . $subforum_classes_attr . '>' . $title . $counts . '</a>' . $r['link_after'];
		}

		// Maybe wrap output
		$output = ! empty( $links )
			? $r['before'] . implode( $r['sep'], $links ) . $r['after']
			: '';
	}

	// Filter output
	$the_list = apply_filters( 'bbp_list_forums', $output, $r, $args );

	// Echo or return the forums list
	if ( ! empty( $r['echo'] ) ) {
		echo $the_list;
	} else {
		return $the_list;
	}
}

/** Forum Subscriptions *******************************************************/

/**
 * Output the forum subscription link
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6308) Add 'redirect_to' support
 */
function bbp_forum_subscription_link( $args = array() ) {
	echo bbp_get_forum_subscription_link( $args );
}

	/**
	 * Get the forum subscription link
	 *
	 * A custom wrapper for bbp_get_user_subscribe_link()
	 *
	 * @since 2.5.0 bbPress (r5156)
	 * @since 2.6.0 bbPress (r6308) Add 'redirect_to' support
	 */
	function bbp_get_forum_subscription_link( $args = array() ) {

		// Defaults
		$retval      = false;
		$redirect_to = bbp_is_subscriptions()
			? bbp_get_subscriptions_permalink()
			: '';

		// Parse the arguments
		$r = bbp_parse_args( $args, array(
			'user_id'     => bbp_get_current_user_id(),
			'object_id'   => bbp_get_forum_id(),
			'object_type' => 'post',
			'before'      => '',
			'after'       => '',
			'subscribe'   => esc_html__( 'Subscribe',   'bbpress' ),
			'unsubscribe' => esc_html__( 'Unsubscribe', 'bbpress' ),
			'redirect_to' => $redirect_to
		), 'get_forum_subscribe_link' );

		// No link for categories until we support subscription hierarchy
		// @see https://bbpress.trac.wordpress.org/ticket/2475
		if ( ! bbp_is_forum_category() ) {
			$retval = bbp_get_user_subscribe_link( $r );
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_subscribe_link', $retval, $r, $args );
	}

/** Forum Last Topic **********************************************************/

/**
 * Output the forum's last topic id
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_topic_id( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_id( $forum_id );
}
	/**
	 * Return the forum's last topic id
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return int Forum's last topic id
	 */
	function bbp_get_forum_last_topic_id( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topic_id = (int) get_post_meta( $forum_id, '_bbp_last_topic_id', true );

		// Filter & return
		return (int) apply_filters( 'bbp_get_forum_last_topic_id', $topic_id, $forum_id );
	}

/**
 * Output the title of the last topic inside a forum
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_topic_title( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_title( $forum_id );
}
	/**
	 * Return the title of the last topic inside a forum
	 *
	 * @since 2.0.0 bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Forum's last topic's title
	 */
	function bbp_get_forum_last_topic_title( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topic_id = bbp_get_forum_last_topic_id( $forum_id );
		$title    = ! empty( $topic_id ) ? bbp_get_topic_title( $topic_id ) : '';

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_topic_title', $title, $forum_id );
	}

/**
 * Output the link to the last topic in a forum
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_topic_permalink( $forum_id = 0 ) {
	echo esc_url( bbp_get_forum_last_topic_permalink( $forum_id ) );
}
	/**
	 * Return the link to the last topic in a forum
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Permanent link to topic
	 */
	function bbp_get_forum_last_topic_permalink( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topic_id = bbp_get_forum_last_topic_id( $forum_id );
		$link     = bbp_get_topic_permalink( $topic_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_topic_permalink', $link, $forum_id, $topic_id );
	}

/**
 * Return the author ID of the last topic of a forum
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @return int Forum's last topic's author id
 */
function bbp_get_forum_last_topic_author_id( $forum_id = 0 ) {
	$forum_id  = bbp_get_forum_id( $forum_id );
	$topic_id  = bbp_get_forum_last_topic_id( $forum_id );
	$author_id = bbp_get_topic_author_id( $topic_id );

	// Filter & return
	return (int) apply_filters( 'bbp_get_forum_last_topic_author_id', (int) $author_id, $forum_id, $topic_id );
}

/**
 * Output link to author of last topic of forum
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_topic_author_link( $forum_id = 0 ) {
	echo bbp_get_forum_last_topic_author_link( $forum_id );
}
	/**
	 * Return link to author of last topic of forum
	 *
	 * @since 2.0.0 bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Forum's last topic's author link
	 */
	function bbp_get_forum_last_topic_author_link( $forum_id = 0 ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$author_id   = bbp_get_forum_last_topic_author_id( $forum_id );
		$author_link = bbp_get_user_profile_link( $author_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_topic_author_link', $author_link, $forum_id );
	}

/** Forum Last Reply **********************************************************/

/**
 * Output the forums last reply id
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_reply_id( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_id( $forum_id );
}
	/**
	 * Return the forums last reply id
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return int Forum's last reply id
	 */
	function bbp_get_forum_last_reply_id( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$reply_id = (int) get_post_meta( $forum_id, '_bbp_last_reply_id', true );

		// Filter & return
		return (int) apply_filters( 'bbp_get_forum_last_reply_id', $reply_id, $forum_id );
	}

/**
 * Output the title of the last reply inside a forum
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_reply_title( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_title( $forum_id );
}
	/**
	 * Return the title of the last reply inside a forum
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string
	 */
	function bbp_get_forum_last_reply_title( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$reply_id = bbp_get_forum_last_reply_id( $forum_id );
		$title    = bbp_get_reply_title( $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_reply_title', $title, $forum_id, $reply_id );
	}

/**
 * Output the link to the last reply in a forum
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_reply_permalink( $forum_id = 0 ) {
	echo esc_url( bbp_get_forum_last_reply_permalink( $forum_id ) );
}
	/**
	 * Return the link to the last reply in a forum
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 *
	 * @return string Permanent link to the forum's last reply
	 */
	function bbp_get_forum_last_reply_permalink( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$reply_id = bbp_get_forum_last_reply_id( $forum_id );
		$link     = bbp_get_reply_permalink( $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_reply_permalink', $link, $forum_id, $reply_id );
	}

/**
 * Output the url to the last reply in a forum
 *
 * @since 2.0.0 bbPress (r2683)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_reply_url( $forum_id = 0 ) {
	echo esc_url( bbp_get_forum_last_reply_url( $forum_id ) );
}
	/**
	 * Return the url to the last reply in a forum
	 *
	 * @since 2.0.0 bbPress (r2683)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Paginated URL to latest reply
	 */
	function bbp_get_forum_last_reply_url( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );

		// If forum has replies, get the last reply and use its url
		$reply_id = bbp_get_forum_last_reply_id( $forum_id );
		if ( ! empty( $reply_id ) ) {
			$reply_url = bbp_get_reply_url( $reply_id );

		// No replies, so look for topics and use last permalink
		} else {
			$reply_url = bbp_get_forum_last_topic_permalink( $forum_id );

			// No topics either, so set $reply_url as empty string
			if ( empty( $reply_url ) ) {
				$reply_url = '';
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_reply_url', $reply_url, $forum_id, $reply_id );
	}

/**
 * Output author ID of last reply of forum
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_reply_author_id( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_author_id( $forum_id );
}
	/**
	 * Return author ID of last reply of forum
	 *
	 * @since 2.0.0 bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return int Forum's last reply author id
	 */
	function bbp_get_forum_last_reply_author_id( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$reply_id  = bbp_get_forum_last_reply_id( $forum_id );
		$author_id = bbp_get_reply_author_id( $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_reply_author_id', $author_id, $forum_id, $reply_id );
	}

/**
 * Output link to author of last reply of forum
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_last_reply_author_link( $forum_id = 0 ) {
	echo bbp_get_forum_last_reply_author_link( $forum_id );
}
	/**
	 * Return link to author of last reply of forum
	 *
	 * @since 2.0.0 bbPress (r2625)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Link to author of last reply of forum
	 */
	function bbp_get_forum_last_reply_author_link( $forum_id = 0 ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$author_id   = bbp_get_forum_last_reply_author_id( $forum_id );
		$author_link = bbp_get_user_profile_link( $author_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_last_reply_author_link', $author_link, $forum_id, $author_id );
	}

/** Forum Counts **************************************************************/

/**
 * Output the topics link of the forum
 *
 * @since 2.0.0 bbPress (r2883)
 *
 * @param int $forum_id Optional. Topic id
 */
function bbp_forum_topics_link( $forum_id = 0 ) {
	echo bbp_get_forum_topics_link( $forum_id );
}

	/**
	 * Return the topics link of the forum
	 *
	 * @since 2.0.0 bbPress (r2883)
	 *
	 * @param int $forum_id Optional. Topic id
	 */
	function bbp_get_forum_topics_link( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$link     = bbp_get_forum_permalink( $forum_id );
		$topics   = sprintf( _n( '%s topic', '%s topics', bbp_get_forum_topic_count( $forum_id, true, true ), 'bbpress' ), bbp_get_forum_topic_count( $forum_id, true, false ) );

		// First link never has view=all
		$retval = bbp_get_view_all( 'edit_others_topics' )
			? "<a href='" . esc_url( bbp_remove_view_all( $link ) ) . "'>" . esc_html( $topics ) . "</a>"
			: esc_html( $topics );

		// Get deleted topics
		$deleted_int = bbp_get_forum_topic_count_hidden( $forum_id, false, true );

		// This forum has hidden topics
		if ( ! empty( $deleted_int ) && current_user_can( 'edit_others_topics' ) ) {

			// Hidden text
			$deleted_num = bbp_get_forum_topic_count_hidden( $forum_id, false, false );
			$extra       = ' ' . sprintf( _n( '(+%s hidden)', '(+%s hidden)', $deleted_int, 'bbpress' ), $deleted_num );

			// Hidden link
			$retval .= ! bbp_get_view_all( 'edit_others_topics' )
				? " <a href='" . esc_url( bbp_add_view_all( $link, true ) ) . "'>" . esc_html( $extra ) . "</a>"
				: " {$extra}";
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_topics_link', $retval, $forum_id );
	}

/**
 * Output total sub-forum count of a forum
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id to check
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_forum_subforum_count( $forum_id = 0, $integer = false ) {
	echo bbp_get_forum_subforum_count( $forum_id, $integer );
}
	/**
	 * Return total subforum count of a forum
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Forum's subforum count
	 */
	function bbp_get_forum_subforum_count( $forum_id = 0, $integer = false ) {
		$forum_id    = bbp_get_forum_id( $forum_id );
		$forum_count = (int) get_post_meta( $forum_id, '_bbp_forum_subforum_count', true );
		$filter      = ( true === $integer )
			? 'bbp_get_forum_subforum_count_int'
			: 'bbp_get_forum_subforum_count';

		return apply_filters( $filter, $forum_count, $forum_id );
	}

/**
 * Output total topic count of a forum
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $total_count Optional. To get the total count or normal count?
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_forum_topic_count( $forum_id = 0, $total_count = true, $integer = false ) {
	echo bbp_get_forum_topic_count( $forum_id, $total_count, $integer );
}
	/**
	 * Return total topic count of a forum
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $total_count Optional. To get the total count or normal
	 *                           count? Defaults to total.
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Forum topic count
	 */
	function bbp_get_forum_topic_count( $forum_id = 0, $total_count = true, $integer = false ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$meta_key = empty( $total_count ) ? '_bbp_topic_count' : '_bbp_total_topic_count';
		$topics   = (int) get_post_meta( $forum_id, $meta_key, true );
		$filter   = ( true === $integer )
			? 'bbp_get_forum_topic_count_int'
			: 'bbp_get_forum_topic_count';

		return apply_filters( $filter, $topics, $forum_id );
	}

/**
 * Output total reply count of a forum
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $total_count Optional. To get the total count or normal count?
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_forum_reply_count( $forum_id = 0, $total_count = true, $integer = false ) {
	echo bbp_get_forum_reply_count( $forum_id, $total_count, $integer );
}
	/**
	 * Return total post count of a forum
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $total_count Optional. To get the total count or normal
	 *                           count?
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Forum reply count
	 */
	function bbp_get_forum_reply_count( $forum_id = 0, $total_count = true, $integer = false ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$meta_key = empty( $total_count ) ? '_bbp_reply_count' : '_bbp_total_reply_count';
		$replies  = (int) get_post_meta( $forum_id, $meta_key, true );
		$filter   = ( true === $integer )
			? 'bbp_get_forum_reply_count_int'
			: 'bbp_get_forum_reply_count';

		return apply_filters( $filter, $replies, $forum_id );
	}

/**
 * Output total post count of a forum
 *
 * @since 2.0.0 bbPress (r2954)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $total_count Optional. To get the total count or normal count?
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_forum_post_count( $forum_id = 0, $total_count = true, $integer = false ) {
	echo bbp_get_forum_post_count( $forum_id, $total_count, $integer );
}
	/**
	 * Return total post count of a forum
	 *
	 * @since 2.0.0 bbPress (r2954)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $total_count Optional. To get the total count or normal
	 *                           count?
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Forum post count
	 */
	function bbp_get_forum_post_count( $forum_id = 0, $total_count = true, $integer = false ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$topics   = bbp_get_forum_topic_count( $forum_id, $total_count, true );
		$replies  = bbp_get_forum_reply_count( $forum_id, $total_count, true );
		$retval   = ( $replies + $topics );
		$filter   = ( true === $integer )
			? 'bbp_get_forum_post_count_int'
			: 'bbp_get_forum_post_count';

		return apply_filters( $filter, $retval, $forum_id );
	}

/**
 * Output total hidden topic count of a forum (hidden includes trashed, spammed,
 * and pending topics)
 *
 * @since 2.0.0 bbPress (r2883)
 * @since 2.6.0 bbPress (r6922) Changed function signature to add total counts
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $total_count Optional. To get the total count or normal count?
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_forum_topic_count_hidden( $forum_id = 0, $total_count = true, $integer = null ) {
	echo bbp_get_forum_topic_count_hidden( $forum_id, $total_count, $integer );
}
	/**
	 * Return total hidden topic count of a forum (hidden includes trashed,
	 * spammed and pending topics)
	 *
	 * @since 2.0.0 bbPress (r2883)
	 * @since 2.6.0 bbPress (r6922) Changed function signature to add total counts
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $total_count Optional. To get the total count or normal count?
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Topic hidden topic count
	 */
	function bbp_get_forum_topic_count_hidden( $forum_id = 0, $total_count = true, $integer = null ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$meta_key = empty( $total_count ) ? '_bbp_topic_count_hidden' : '_bbp_topic_reply_count_hidden';
		$topics   = (int) get_post_meta( $forum_id, $meta_key, true );
		$filter   = ( true === $integer )
			? 'bbp_get_forum_topic_count_hidden_int'
			: 'bbp_get_forum_topic_count_hidden';

		return apply_filters( $filter, $topics, $forum_id );
	}

/**
 * Output total hidden reply count of a forum (hidden includes trashed, spammed,
 * and pending replies)
 *
 * @since 2.6.0 bbPress (r6922)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $total_count Optional. To get the total count or normal count?
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_forum_reply_count_hidden( $forum_id = 0, $total_count = true, $integer = false ) {
	echo bbp_get_forum_reply_count_hidden( $forum_id, $total_count, $integer );
}
	/**
	 * Return total hidden reply count of a forum (hidden includes trashed,
	 * spammed and pending replies)
	 *
	 * @since 2.6.0 bbPress (r6922)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @param bool $total_count Optional. To get the total count or normal
	 *                           count?
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Forum reply count
	 */
	function bbp_get_forum_reply_count_hidden( $forum_id = 0, $total_count = true, $integer = false ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$meta_key = empty( $total_count ) ? '_bbp_reply_count_hidden' : '_bbp_total_reply_count_hidden';
		$replies  = (int) get_post_meta( $forum_id, $meta_key, true );
		$filter   = ( true === $integer )
			? 'bbp_get_forum_reply_count_hidden_int'
			: 'bbp_get_forum_reply_count_hidden';

		return apply_filters( $filter, $replies, $forum_id );
	}

/**
 * Output the status of the forum
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_status( $forum_id = 0 ) {
	echo bbp_get_forum_status( $forum_id );
}
	/**
	 * Return the status of the forum
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Status of forum
	 */
	function bbp_get_forum_status( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$status   = get_post_meta( $forum_id, '_bbp_status', true );

		if ( empty( $status ) ) {
			$status = 'open';
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_status', $status, $forum_id );
	}

/**
 * Output the visibility of the forum
 *
 * @since 2.0.0 bbPress (r2997)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_visibility( $forum_id = 0 ) {
	echo bbp_get_forum_visibility( $forum_id );
}
	/**
	 * Return the visibility of the forum
	 *
	 * @since 2.0.0 bbPress (r2997)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Status of forum
	 */
	function bbp_get_forum_visibility( $forum_id = 0 ) {
		$forum_id   = bbp_get_forum_id( $forum_id );
		$visibility = get_post_status( $forum_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_visibility', $visibility, $forum_id );
	}

/**
 * Output the type of the forum
 *
 * @since 2.1.0 bbPress (r3563)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_type( $forum_id = 0 ) {
	echo bbp_get_forum_type( $forum_id );
}
	/**
	 * Return the type of forum (category/forum/etc...)
	 *
	 * @since 2.1.0 bbPress (r3563)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return bool Whether the forum is a category or not
	 */
	function bbp_get_forum_type( $forum_id = 0 ) {
		$forum_id = bbp_get_forum_id( $forum_id );
		$retval   = get_post_meta( $forum_id, '_bbp_forum_type', true );

		if ( empty( $retval ) ) {
			$retval = 'forum';
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_type', $retval, $forum_id );
	}

/**
 * Is the forum a category?
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @return bool Whether the forum is a category or not
 */
function bbp_is_forum_category( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$type     = bbp_get_forum_type( $forum_id );
	$retval   = ( ! empty( $type ) && 'category' === $type );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_category', (bool) $retval, $forum_id );
}

/**
 * Is the forum open?
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $check_ancestors Check if the ancestors are open (only
 *                               if they're a category)
 * @return bool Whether the forum is open or not
 */
function bbp_is_forum_open( $forum_id = 0, $check_ancestors = true ) {
	return ! bbp_is_forum_closed( $forum_id, $check_ancestors );
}

/**
* Is the forum closed?
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $check_ancestors Check if the ancestors are closed (only
 *                               if they're a category)
 * @return bool True if closed, false if not
 */
function bbp_is_forum_closed( $forum_id = 0, $check_ancestors = true ) {

	// Get the forum ID
	$forum_id = bbp_get_forum_id( $forum_id );

	// Check if the forum or one of it's ancestors is closed
	$retval   = bbp_is_forum_status( $forum_id, bbp_get_closed_status_id(), $check_ancestors, 'OR' );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_closed', (bool) $retval, $forum_id, $check_ancestors );
}

/**
 * Check if the forum status is a specific one, also maybe checking ancestors
 *
 * @since 2.6.0 bbPress (r5499)
 *
 * @param bool $status_name The forum status name to check
 * @param bool $check_ancestors Check the forum ancestors
 * @param string $operator The logical operation to perform.
 *      'OR' means only one forum from the tree needs to match;
 *      'AND' means all forums must match. The default is 'AND'.
 * @return bool True if match, false if not
 */
function bbp_is_forum_status( $forum_id, $status_name, $check_ancestors = true, $operator = 'AND' ) {

	// Setup some default variables
	$count        = 0;
	$retval       = false;
	$operator     = strtoupper( $operator );
	$forum_id     = bbp_get_forum_id( $forum_id );
	$forum_status = bbp_get_forum_status( $forum_id );

	// Quickly compare statuses of first forum ID
	if ( $status_name === $forum_status ) {
		$retval = true;
		$count++;
	}

	// Let's check the forum's ancestors too
	if ( ! empty( $check_ancestors ) ) {

		// Adjust the ancestor check based on the count
		switch( $operator ) {
			default:
			case 'AND':
				$check_ancestors = ( $count > 0 );
				break;

			case 'OR':
				$check_ancestors = ( $count < 1 );
				break;
		}

		// Ancestor check passed, so continue looping through them
		if ( ! empty( $check_ancestors ) ) {

			// Loop through the forum ancestors
			foreach ( (array) bbp_get_forum_ancestors( $forum_id ) as $ancestor ) {

				// Check if the forum is a category
				if ( bbp_is_forum_category( $ancestor ) ) {

					// Check the ancestor forum status
					$retval = bbp_is_forum_status( $ancestor, $status_name, false );
					if ( true === $retval ) {
						$count++;
					}
				}

				// Break when it reach the max count
				if ( ( $operator === 'OR' ) && ( $count >= 1 ) ) {
					break;
				}
			}
		}
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_status', $retval, $count, $forum_id, $status_name, $check_ancestors, $operator );
}

/**
 * Is the forum public?
 *
 * @since 2.0.0 bbPress (r2997)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $check_ancestors Check if the ancestors are public
 * @return bool True if closed, false if not
 */
function bbp_is_forum_public( $forum_id = 0, $check_ancestors = true ) {

	// Get the forum ID
	$forum_id = bbp_get_forum_id( $forum_id );

	// Check if the forum and all of it's ancestors are public
	$retval   = bbp_is_forum_visibility( $forum_id, bbp_get_public_status_id(), $check_ancestors );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_public', $retval, $forum_id, $check_ancestors );
}

/**
 * Is the forum private?
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $check_ancestors Check if the ancestors are private
 * @return bool True if private, false if not
 */
function bbp_is_forum_private( $forum_id = 0, $check_ancestors = true ) {

	// Get the forum ID
	$forum_id = bbp_get_forum_id( $forum_id );

	// Check if the forum or one of it's ancestors is private
	$retval   = bbp_is_forum_visibility( $forum_id, bbp_get_private_status_id(), $check_ancestors, 'OR' );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_private', $retval, $forum_id, $check_ancestors );
}

/**
 * Is the forum hidden?
 *
 * @since 2.0.0 bbPress (r2997)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $check_ancestors Check if the ancestors are private (only if
 *                               they're a category)
 * @return bool True if hidden, false if not
 */
function bbp_is_forum_hidden( $forum_id = 0, $check_ancestors = true ) {

	// Get the forum ID
	$forum_id = bbp_get_forum_id( $forum_id );

	// Check if the forum or one of it's ancestors is hidden
	$retval   = bbp_is_forum_visibility( $forum_id, bbp_get_hidden_status_id(), $check_ancestors, 'OR' );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_hidden', $retval, $forum_id, $check_ancestors );
}

/**
 * Check the forum visibility ID
 *
 * @since 2.6.0 bbPress (r5499)
 *
 * @param int $forum_id Optional. Forum id
 * @param bool $status_name The post status name to check
 * @param bool $check_ancestors Check the forum ancestors
 * @param string $operator The logical operation to perform.
 *      'OR' means only one forum from the tree needs to match;
 *      'AND' means all forums must match. The default is 'AND'.
 * @return bool True if match, false if not
 */
function bbp_is_forum_visibility( $forum_id, $status_name, $check_ancestors = true, $operator = 'AND' ) {

	// Setup some default variables
	$count      = 0;
	$retval     = false;
	$operator   = strtoupper( $operator );
	$forum_id   = bbp_get_forum_id( $forum_id );
	$visibility = bbp_get_forum_visibility( $forum_id );

	// Quickly compare visibility of first forum ID
	if ( $status_name === $visibility ) {
		$retval = true;
		$count++;
	}

	// Let's check the forum's ancestors too
	if ( ! empty( $check_ancestors ) ) {

		// Adjust the ancestor check based on the count
		switch ( $operator ) {

			// Adjust the ancestor check based on the count
			default:
			case 'AND':
				$check_ancestors = ( $count > 0 );
				break;

			case 'OR':
				$check_ancestors = ( $count < 1 );
				break;
		}

		// Ancestor check passed, so continue looping through them
		if ( ! empty( $check_ancestors ) ) {

			// Loop through the forum ancestors
			foreach ( (array) bbp_get_forum_ancestors( $forum_id ) as $ancestor ) {

				// Check if the forum is not a category
				if ( bbp_is_forum( $ancestor ) ) {

					// Check the forum visibility
					$retval = bbp_is_forum_visibility( $ancestor, $status_name, false );
					if ( true === $retval ) {
						$count++;
					}
				}

				// Break when it reach the max count
				if ( ( $operator === 'OR' ) && ( $count >= 1 ) ) {
					break;
				}
			}
		}
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_visibility', $retval, $count, $forum_id, $status_name, $check_ancestors, $operator );
}

/**
 * Output the author ID of the forum
 *
 * @since 2.1.0 bbPress (r3675)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_author_id( $forum_id = 0 ) {
	echo bbp_get_forum_author_id( $forum_id );
}
	/**
	 * Return the author ID of the forum
	 *
	 * @since 2.1.0 bbPress (r3675)
	 *
	 * @param int $forum_id Optional. Forum id
	 *                        id and forum id
	 * @return string Author of forum
	 */
	function bbp_get_forum_author_id( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$author_id = get_post_field( 'post_author', $forum_id );

		// Filter & return
		return (int) apply_filters( 'bbp_get_forum_author_id', (int) $author_id, $forum_id );
	}

/**
 * Output the author of the forum
 *
 * @since 2.1.0 bbPress (r3675)
 *
 * @param int $forum_id Optional. Forum id
 */
function bbp_forum_author_display_name( $forum_id = 0 ) {
	echo bbp_get_forum_author_display_name( $forum_id );
}
	/**
	 * Return the author of the forum
	 *
	 * @since 2.1.0 bbPress (r3675)
	 *
	 * @param int $forum_id Optional. Forum id
	 * @return string Author of forum
	 */
	function bbp_get_forum_author_display_name( $forum_id = 0 ) {
		$forum_id  = bbp_get_forum_id( $forum_id );
		$author_id = bbp_get_forum_author_id( $forum_id );
		$author    = get_the_author_meta( 'display_name', $author_id );

		// Filter & return
		return apply_filters( 'bbp_get_forum_author_display_name', $author, $forum_id, $author_id );
	}

/**
 * Replace forum meta details for users that cannot view them.
 *
 * @since 2.0.0 bbPress (r3162)
 *
 * @param string $retval
 * @param int $forum_id
 *
 * @return string
 */
function bbp_suppress_private_forum_meta( $retval, $forum_id ) {
	if ( bbp_is_forum_private( $forum_id, false ) && ! current_user_can( 'read_forum', $forum_id ) ) {
		$retval = '-';
	}

	// Filter & return
	return apply_filters( 'bbp_suppress_private_forum_meta', $retval );
}

/**
 * Replace forum author details for users that cannot view them.
 *
 * @since 2.0.0 bbPress (r3162)
 *
 * @param string $author_link
 * @param array $args
 *
 * @return string
 */
function bbp_suppress_private_author_link( $author_link = '', $args = array() ) {

	// Assume the author link is the return value
	$retval = $author_link;

	// Show the normal author link
	if ( ! empty( $args['post_id'] ) && ! current_user_can( 'read_private_forums' ) ) {

		// What post type are we looking at?
		switch ( get_post_type( $args['post_id'] ) ) {

			// Topic
			case bbp_get_topic_post_type() :
				$forum_id = bbp_get_topic_forum_id( $args['post_id'] );
				break;

			// Reply
			case bbp_get_reply_post_type() :
				$forum_id = bbp_get_reply_forum_id( $args['post_id'] );
				break;

			// Post
			default :
				$forum_id = bbp_get_forum_id( $args['post_id'] );
				break;
		}

		// Hide if forum is private
		if ( bbp_is_forum_private( $forum_id ) ) {
			$retval = '';
		}
	}

	// Filter & return
	return apply_filters( 'bbp_suppress_private_author_link', $retval, $author_link, $args );
}

/**
 * Output the row class of a forum
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $forum_id Optional. Forum ID.
 * @param array Extra classes you can pass when calling this function
 */
function bbp_forum_class( $forum_id = 0, $classes = array() ) {
	echo bbp_get_forum_class( $forum_id, $classes );
}
	/**
	 * Return the row class of a forum
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $forum_id Optional. Forum ID
	 * @param array Extra classes you can pass when calling this function
	 * @return string Row class of the forum
	 */
	function bbp_get_forum_class( $forum_id = 0, $classes = array() ) {
		$bbp        = bbpress();
		$forum_id   = bbp_get_forum_id( $forum_id );
		$parent_id  = bbp_get_forum_parent_id( $forum_id );
		$author_id  = bbp_get_forum_author_id( $forum_id );
		$status     = bbp_get_forum_status( $forum_id );
		$visibility = bbp_get_forum_visibility( $forum_id );
		$classes    = array_filter( (array) $classes );
		$count      = isset( $bbp->forum_query->current_post )
			? (int) $bbp->forum_query->current_post
			: 1;

		//  Stripes
		$even_odd = ( $count % 2 )
			? 'even'
			: 'odd';

		// User is moderator of forum
		$forum_moderator = ( bbp_is_user_forum_moderator( $author_id, $forum_id ) === $author_id )
			? 'forum-mod'
			: '';

		// Is forum a non-postable category?
		$category = bbp_is_forum_category( $forum_id )
			? 'status-category'
			: '';

		// Forum has children?
		$subs = bbp_get_forum_subforum_count( $forum_id )
			? 'bbp-has-subforums'
			: '';

		// Forum has parent?
		$parent = ! empty( $parent_id )
			? 'bbp-parent-forum-' . $parent_id
			: '';

		// Get forum classes
		$forum_classes = array(
			'loop-item-'            . $count,
			'bbp-forum-status-'     . $status,
			'bbp-forum-visibility-' . $visibility,
			$even_odd,
			$forum_moderator,
			$category,
			$subs,
			$parent
		);

		// Run the topic classes through the post-class filters, which also
		// handles the escaping of each individual class.
		$post_classes = get_post_class( array_merge( $classes, $forum_classes ), $forum_id );

		// Filter
		$new_classes  = apply_filters( 'bbp_get_forum_class', $post_classes, $forum_id, $classes );

		// Return
		return 'class="' . implode( ' ', $new_classes ) . '"';
	}

/** Single Forum **************************************************************/

/**
 * Output a fancy description of the current forum, including total topics,
 * total replies, and last activity.
 *
 * @since 2.0.0 bbPress (r2860)
 *
 * @param array $args Arguments passed to alter output
 */
function bbp_single_forum_description( $args = array() ) {
	echo bbp_get_single_forum_description( $args );
}
	/**
	 * Return a fancy description of the current forum, including total
	 * topics, total replies, and last activity.
	 *
	 * @since 2.0.0 bbPress (r2860)
	 *
	 * @param array $args This function supports these arguments:
	 *  - forum_id: Forum id
	 *  - before: Before the text
	 *  - after: After the text
	 *  - size: Size of the avatar
	 * @return string Filtered forum description
	 */
	function bbp_get_single_forum_description( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'forum_id'  => 0,
			'before'    => '<div class="bbp-template-notice info"><ul><li class="bbp-forum-description">',
			'after'     => '</li></ul></div>',
			'size'      => 14,
			'feed'      => true
		), 'get_single_forum_description' );

		// Validate forum_id
		$forum_id = bbp_get_forum_id( $r['forum_id'] );

		// Unhook the 'view all' query var adder
		remove_filter( 'bbp_get_forum_permalink', 'bbp_add_view_all' );

		// Get some forum data
		$tc_int      = bbp_get_forum_topic_count( $forum_id, true, true  );
		$rc_int      = bbp_get_forum_reply_count( $forum_id, true, true  );
		$topic_count = bbp_get_forum_topic_count( $forum_id, true, false );
		$reply_count = bbp_get_forum_reply_count( $forum_id, true, false );
		$last_active = bbp_get_forum_last_active_id( $forum_id );

		// Has replies
		if ( ! empty( $reply_count ) ) {
			$reply_text = sprintf( _n( '%s reply', '%s replies', $rc_int, 'bbpress' ), $reply_count );
		}

		// Forum has active data
		if ( ! empty( $last_active ) ) {
			$topic_text      = bbp_get_forum_topics_link( $forum_id );
			$time_since      = bbp_get_forum_freshness_link( $forum_id );
			$last_updated_by = bbp_get_author_link( array( 'post_id' => $last_active, 'size' => $r['size'] ) );

		// Forum has no last active data
		} else {
			$topic_text      = sprintf( _n( '%s topic', '%s topics', $tc_int, 'bbpress' ), $topic_count );
		}

		// Forum has active data
		if ( ! empty( $last_active ) ) {

			// Has replies
			if ( ! empty( $reply_count ) ) {
				$retstr = bbp_is_forum_category( $forum_id )
					? sprintf( esc_html__( 'This category has %1$s, %2$s, and was last updated %3$s by %4$s.', 'bbpress' ), $topic_text, $reply_text, $time_since, $last_updated_by )
					: sprintf( esc_html__( 'This forum has %1$s, %2$s, and was last updated %3$s by %4$s.',    'bbpress' ), $topic_text, $reply_text, $time_since, $last_updated_by );

			// Only has topics
			} else {
				$retstr = bbp_is_forum_category( $forum_id )
					? sprintf( esc_html__( 'This category has %1$s, and was last updated %2$s by %3$s.', 'bbpress' ), $topic_text, $time_since, $last_updated_by )
					: sprintf( esc_html__( 'This forum has %1$s, and was last updated %2$s by %3$s.',    'bbpress' ), $topic_text, $time_since, $last_updated_by );
			}

		// Forum has no last active data (but does have topics & replies)
		} elseif ( ! empty( $reply_count ) ) {
			$retstr = bbp_is_forum_category( $forum_id )
				? sprintf( esc_html__( 'This category has %1$s and %2$s.', 'bbpress' ), $topic_text, $reply_text )
				: sprintf( esc_html__( 'This forum has %1$s and %2$s.',    'bbpress' ), $topic_text, $reply_text );

		// Forum has no last active data or replies (but does have topics)
		} elseif ( ! empty( $topic_count ) ) {
			$retstr = bbp_is_forum_category( $forum_id )
				? sprintf( esc_html__( 'This category has %1$s.', 'bbpress' ), $topic_text )
				: sprintf( esc_html__( 'This forum has %1$s.',    'bbpress' ), $topic_text );

		// Forum is empty
		} else {
			$retstr = esc_html__( 'This forum is empty.', 'bbpress' );
		}

		// Add the 'view all' filter back
		add_filter( 'bbp_get_forum_permalink', 'bbp_add_view_all' );

		// Combine the elements together
		$retstr = $r['before'] . $retstr . $r['after'];

		// Filter & return
		return apply_filters( 'bbp_get_single_forum_description', $retstr, $r, $args );
	}

/** Forms *********************************************************************/

/**
 * Output the value of forum title field
 *
 * @since 2.1.0 bbPress (r3551)
 */
function bbp_form_forum_title() {
	echo bbp_get_form_forum_title();
}
	/**
	 * Return the value of forum title field
	 *
	 * @since 2.1.0 bbPress (r3551)
	 *
	 * @return string Value of forum title field
	 */
	function bbp_get_form_forum_title() {

		// Get _POST data
		if ( bbp_is_forum_form_post_request() && isset( $_POST['bbp_forum_title'] ) ) {
			$forum_title = wp_unslash( $_POST['bbp_forum_title'] );

		// Get edit data
		} elseif ( bbp_is_forum_edit() ) {
			$forum_title = bbp_get_global_post_field( 'post_title', 'raw' );

		// No data
		} else {
			$forum_title = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_title', $forum_title );
	}

/**
 * Output the value of forum content field
 *
 * @since 2.1.0 bbPress (r3551)
 */
function bbp_form_forum_content() {
	echo bbp_get_form_forum_content();
}
	/**
	 * Return the value of forum content field
	 *
	 * @since 2.1.0 bbPress (r3551)
	 *
	 * @return string Value of forum content field
	 */
	function bbp_get_form_forum_content() {

		// Get _POST data
		if ( bbp_is_forum_form_post_request() && isset( $_POST['bbp_forum_content'] ) ) {
			$forum_content = wp_unslash( $_POST['bbp_forum_content'] );

		// Get edit data
		} elseif ( bbp_is_forum_edit() ) {
			$forum_content = bbp_get_global_post_field( 'post_content', 'raw' );

		// No data
		} else {
			$forum_content = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_content', $forum_content );
	}

/**
 * Output value of forum moderators field
 *
 * @since 2.6.0 bbPress (r5837)
 */
function bbp_form_forum_moderators() {
	echo bbp_get_form_forum_moderators();
}
	/**
	 * Return value of forum moderators field
	 *
	 * @since 2.6.0 bbPress (r5837)
	 *
	 * @return string Value of forum mods field
	 */
	function bbp_get_form_forum_moderators() {

		// Default return value
		$forum_mods = '';

		// Get _POST data
		if ( bbp_is_forum_form_post_request() && isset( $_POST['bbp_moderators'] ) ) {
			$forum_mods = wp_unslash( $_POST['bbp_moderators'] );

		// Get edit data
		} elseif ( bbp_is_single_forum() || bbp_is_forum_edit() ) {

			// Get the forum ID
			$forum_id = bbp_get_forum_id( get_the_ID() );

			// Forum exists
			if ( ! empty( $forum_id ) ) {

				// Get moderator IDs
				$user_ids = bbp_get_moderator_ids( $forum_id );
				if ( ! empty( $user_ids ) ) {
					$user_nicenames = bbp_get_user_nicenames_from_ids( $user_ids );

					// Comma separate user nicenames
					if ( ! empty( $user_nicenames ) ) {
						$forum_mods = implode( ', ', wp_list_pluck( $user_nicenames, 'user_nicename' ) );
					}
				}
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_moderators', $forum_mods );
	}

/**
 * Output value of forum parent
 *
 * @since 2.1.0 bbPress (r3551)
 */
function bbp_form_forum_parent() {
	echo bbp_get_form_forum_parent();
}
	/**
	 * Return value of forum parent
	 *
	 * @since 2.1.0 bbPress (r3551)
	 *
	 * @return string Value of topic content field
	 */
	function bbp_get_form_forum_parent() {

		// Get _POST data
		if ( bbp_is_forum_form_post_request() && isset( $_POST['bbp_forum_id'] ) ) {
			$forum_parent = (int) $_POST['bbp_forum_id'];

		// Get edit data
		} elseif ( bbp_is_forum_edit() ) {
			$forum_parent = bbp_get_forum_parent_id();

		// No data
		} else {
			$forum_parent = 0;
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_parent', $forum_parent );
	}

/**
 * Output value of forum type
 *
 * @since 2.1.0 bbPress (r3563)
 */
function bbp_form_forum_type() {
	echo bbp_get_form_forum_type();
}
	/**
	 * Return value of forum type
	 *
	 * @since 2.1.0 bbPress (r3563)
	 *
	 * @return string Value of topic content field
	 */
	function bbp_get_form_forum_type() {

		// Get _POST data
		if ( bbp_is_forum_form_post_request() && isset( $_POST['bbp_forum_type'] ) ) {
			$forum_type = sanitize_key( $_POST['bbp_forum_type'] );

		// Get edit data
		} elseif ( bbp_is_forum_edit() ) {
			$forum_type = bbp_get_forum_type();

		// No data
		} else {
			$forum_type = 'forum';
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_type', $forum_type );
	}

/**
 * Output value of forum visibility
 *
 * @since 2.1.0 bbPress (r3563)
 */
function bbp_form_forum_visibility() {
	echo bbp_get_form_forum_visibility();
}
	/**
	 * Return value of forum visibility
	 *
	 * @since 2.1.0 bbPress (r3563)
	 *
	 * @return string Value of topic content field
	 */
	function bbp_get_form_forum_visibility() {

		// Get _POST data
		if ( bbp_is_forum_form_post_request() && isset( $_POST['bbp_forum_visibility'] ) ) {
			$forum_visibility = sanitize_key( $_POST['bbp_forum_visibility'] );

		// Get edit data
		} elseif ( bbp_is_forum_edit() ) {
			$forum_visibility = bbp_get_forum_visibility();

		// No data
		} else {
			$forum_visibility = bbpress()->public_status_id;
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_visibility', $forum_visibility );
	}

/**
 * Output checked value of forum subscription
 *
 * @since 2.5.0 bbPress (r5156)
 */
function bbp_form_forum_subscribed() {
	echo bbp_get_form_forum_subscribed();
}
	/**
	 * Return checked value of forum subscription
	 *
	 * @since 2.5.0 bbPress (r5156)
	 *
	 * @return string Checked value of forum subscription
	 */
	function bbp_get_form_forum_subscribed() {

		// Default value
		$forum_subscribed = false;

		// Get _POST data
		if ( bbp_is_forum_form_post_request() && isset( $_POST['bbp_forum_subscription'] ) ) {
			$forum_subscribed = (bool) $_POST['bbp_forum_subscription'];

		// Get edit data
		} elseif ( bbp_is_forum_edit() || bbp_is_reply_edit() ) {
			$post_author      = (int) bbp_get_global_post_field( 'post_author', 'raw' );
			$forum_subscribed = bbp_is_user_subscribed( $post_author, bbp_get_forum_id() );

		// Get current status
		} elseif ( bbp_is_single_forum() ) {
			$forum_subscribed = bbp_is_user_subscribed( bbp_get_current_user_id(), bbp_get_forum_id() );
		}

		// Get checked output
		$checked = checked( $forum_subscribed, true, false );

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_subscribed', $checked, $forum_subscribed );
	}

/** Form Dropdowns ************************************************************/

/**
 * Output value forum type dropdown
 *
 * @since 2.1.0 bbPress (r3563)
 *
 * @param $args This function supports these arguments:
 *  - select_id: Select id. Defaults to bbp_forum_type
 *  - tab: Deprecated. Tabindex
 *  - forum_id: Forum id
 *  - selected: Override the selected option
 */
function bbp_form_forum_type_dropdown( $args = array() ) {
	echo bbp_get_form_forum_type_dropdown( $args );
}
	/**
	 * Return the forum type dropdown
	 *
	 * @since 2.1.0 bbPress (r3563)
	 *
	 * @param $args This function supports these arguments:
	 *  - select_id: Select id. Defaults to bbp_forum_type
	 *  - tab: Deprecated. Tabindex
	 *  - forum_id: Forum id
	 *  - selected: Override the selected option
	 * @return string HTML select list for selecting forum type
	 */
	function bbp_get_form_forum_type_dropdown( $args = array() ) {

		// Backpat for handling passing of a forum ID as integer
		if ( is_int( $args ) ) {
			$forum_id = (int) $args;
			$args     = array();
		} else {
			$forum_id = 0;
		}

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'select_id'    => 'bbp_forum_type',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'forum_id'     => $forum_id,
			'selected'     => false
		), 'forum_type_select' );

		// No specific selected value passed
		if ( empty( $r['selected'] ) ) {

			// Post value is passed
			if ( bbp_is_forum_form_post_request() && isset( $_POST[ $r['select_id'] ] ) ) {
				$r['selected'] = sanitize_key( $_POST[ $r['select_id'] ] );

			// No Post value was passed
			} else {

				// Edit topic
				if ( bbp_is_forum_edit() ) {
					$r['forum_id'] = bbp_get_forum_id( $r['forum_id'] );
					$r['selected'] = bbp_get_forum_type( $r['forum_id'] );

				// New topic
				} else {
					$r['selected'] = bbp_get_public_status_id();
				}
			}
		}

		// Start an output buffer, we'll finish it after the select loop
		ob_start(); ?>

		<select name="<?php echo esc_attr( $r['select_id'] ) ?>" id="<?php echo esc_attr( $r['select_id'] ) ?>_select" class="<?php echo esc_attr( $r['select_class'] ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

			<?php foreach ( bbp_get_forum_types( $r['forum_id'] ) as $key => $label ) : ?>

				<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

			<?php endforeach; ?>

		</select>

		<?php

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_type_dropdown', ob_get_clean(), $r, $args );
	}

/**
 * Output value forum status dropdown
 *
 * @since 2.1.0 bbPress (r3563)
 *
 * @param $args This function supports these arguments:
 *  - select_id: Select id. Defaults to bbp_forum_status
 *  - tab: Deprecated. Tabindex
 *  - forum_id: Forum id
 *  - selected: Override the selected option
 */
function bbp_form_forum_status_dropdown( $args = array() ) {
	echo bbp_get_form_forum_status_dropdown( $args );
}
	/**
	 * Return the forum status dropdown
	 *
	 * @since 2.1.0 bbPress (r3563)
	 *
	 * @param $args This function supports these arguments:
	 *  - select_id: Select id. Defaults to bbp_forum_status
	 *  - tab: Deprecated. Tabindex
	 *  - forum_id: Forum id
	 *  - selected: Override the selected option
	 * @return string HTML select list for selecting forum status
	 */
	function bbp_get_form_forum_status_dropdown( $args = array() ) {

		// Backpat for handling passing of a forum ID
		if ( is_int( $args ) ) {
			$forum_id = (int) $args;
			$args     = array();
		} else {
			$forum_id = 0;
		}

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'select_id'    => 'bbp_forum_status',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'forum_id'     => $forum_id,
			'selected'     => false
		), 'forum_status_select' );

		// No specific selected value passed
		if ( empty( $r['selected'] ) ) {

			// Post value is passed
			if ( bbp_is_forum_form_post_request() && isset( $_POST[ $r['select_id'] ] ) ) {
				$r['selected'] = sanitize_key( $_POST[ $r['select_id'] ] );

			// No Post value was passed
			} else {

				// Edit topic
				if ( bbp_is_forum_edit() ) {
					$r['forum_id'] = bbp_get_forum_id( $r['forum_id'] );
					$r['selected'] = bbp_get_forum_status( $r['forum_id'] );

				// New topic
				} else {
					$r['selected'] = bbp_get_public_status_id();
				}
			}
		}

		// Start an output buffer, we'll finish it after the select loop
		ob_start(); ?>

		<select name="<?php echo esc_attr( $r['select_id'] ) ?>" id="<?php echo esc_attr( $r['select_id'] ) ?>_select" class="<?php echo esc_attr( $r['select_class'] ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

			<?php foreach ( bbp_get_forum_statuses( $r['forum_id'] ) as $key => $label ) : ?>

				<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

			<?php endforeach; ?>

		</select>

		<?php

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_status_dropdown', ob_get_clean(), $r, $args );
	}

/**
 * Output value forum visibility dropdown
 *
 * @since 2.1.0 bbPress (r3563)
 *
 * @param $args This function supports these arguments:
 *  - select_id: Select id. Defaults to bbp_forum_visibility
 *  - tab: Deprecated. Tabindex
 *  - forum_id: Forum id
 *  - selected: Override the selected option
 */
function bbp_form_forum_visibility_dropdown( $args = array() ) {
	echo bbp_get_form_forum_visibility_dropdown( $args );
}
	/**
	 * Return the forum visibility dropdown
	 *
	 * @since 2.1.0 bbPress (r3563)
	 *
	 * @param $args This function supports these arguments:
	 *  - select_id: Select id. Defaults to bbp_forum_visibility
	 *  - tab: Deprecated. Tabindex
	 *  - forum_id: Forum id
	 *  - selected: Override the selected option
	 * @return string HTML select list for selecting forum visibility
	 */
	function bbp_get_form_forum_visibility_dropdown( $args = array() ) {

		// Backpat for handling passing of a forum ID
		if ( is_int( $args ) ) {
			$forum_id = (int) $args;
			$args     = array();
		} else {
			$forum_id = 0;
		}

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'select_id'    => 'bbp_forum_visibility',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'forum_id'     => $forum_id,
			'selected'     => false
		), 'forum_type_select' );

		// No specific selected value passed
		if ( empty( $r['selected'] ) ) {

			// Post value is passed
			if ( bbp_is_forum_form_post_request() && isset( $_POST[ $r['select_id'] ] ) ) {
				$r['selected'] = sanitize_key( $_POST[ $r['select_id'] ] );

			// No Post value was passed
			} else {

				// Edit topic
				if ( bbp_is_forum_edit() ) {
					$r['forum_id'] = bbp_get_forum_id( $r['forum_id'] );
					$r['selected'] = bbp_get_forum_visibility( $r['forum_id'] );

				// New topic
				} else {
					$r['selected'] = bbp_get_public_status_id();
				}
			}
		}

		// Start an output buffer, we'll finish it after the select loop
		ob_start(); ?>

		<select name="<?php echo esc_attr( $r['select_id'] ) ?>" id="<?php echo esc_attr( $r['select_id'] ) ?>_select" class="<?php echo esc_attr( $r['select_class'] ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

			<?php foreach ( bbp_get_forum_visibilities( $r['forum_id'] ) as $key => $label ) : ?>

				<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

			<?php endforeach; ?>

		</select>

		<?php

		// Filter & return
		return apply_filters( 'bbp_get_form_forum_type_dropdown', ob_get_clean(), $r, $args );
	}

/**
 * Verify if a POST request came from a failed forum attempt.
 *
 * Used to avoid cross-site request forgeries when checking posted forum form
 * content.
 *
 * @see bbp_forum_form_fields()
 *
 * @since 2.6.0 bbPress (r5558)
 *
 * @return boolean True if is a post request with valid nonce
 */
function bbp_is_forum_form_post_request() {

	// Bail if not a post request
	if ( ! bbp_is_post_request() ) {
		return false;
	}

	// Creating a new forum
	if ( bbp_verify_nonce_request( 'bbp-new-forum' ) ) {
		return true;
	}

	// Editing an existing forum
	if ( bbp_verify_nonce_request( 'bbp-edit-forum_' . bbp_get_forum_id() ) ) {
		return true;
	}

	return false;
}

/** Feeds *********************************************************************/

/**
 * Output the link for the forum feed
 *
 * @since 2.0.0 bbPress (r3172)
 *
 * @param int $forum_id Optional. Forum ID.
 */
function bbp_forum_topics_feed_link( $forum_id = 0 ) {
	echo bbp_get_forum_topics_feed_link( $forum_id );
}
	/**
	 * Retrieve the link for the forum feed
	 *
	 * @since 2.0.0 bbPress (r3172)
	 *
	 * @param int $forum_id Optional. Forum ID.
	 *
	 * @return string
	 */
	function bbp_get_forum_topics_feed_link( $forum_id = 0 ) {

		// Validate forum id
		$forum_id = bbp_get_forum_id( $forum_id );

		// Forum is valid
		if ( ! empty( $forum_id ) ) {

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

			$link = '<a href="' . esc_url( $url ) . '" class="bbp-forum-rss-link topics"><span>' . esc_attr__( 'Topics', 'bbpress' ) . '</span></a>';
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_topics_feed_link', $link, $url, $forum_id );
	}

/**
 * Output the link for the forum replies feed
 *
 * @since 2.0.0 bbPress (r3172)
 *
 * @param int $forum_id Optional. Forum ID.
 */
function bbp_forum_replies_feed_link( $forum_id = 0 ) {
	echo bbp_get_forum_replies_feed_link( $forum_id );
}
	/**
	 * Retrieve the link for the forum replies feed
	 *
	 * @since 2.0.0 bbPress (r3172)
	 *
	 * @param int $forum_id Optional. Forum ID.
	 *
	 * @return string
	 */
	function bbp_get_forum_replies_feed_link( $forum_id = 0 ) {

		// Validate forum id
		$forum_id = bbp_get_forum_id( $forum_id );

		// Forum is valid
		if ( ! empty( $forum_id ) ) {

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

			$link = '<a href="' . esc_url( $url ) . '" class="bbp-forum-rss-link replies"><span>' . esc_html__( 'Replies', 'bbpress' ) . '</span></a>';
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_replies_feed_link', $link, $url, $forum_id );
	}
