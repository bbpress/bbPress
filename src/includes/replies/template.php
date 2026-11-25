<?php

/**
 * bbPress Reply Template Tags.
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Post Type *****************************************************************/

/**
 * Return the unique id of the custom post type for replies.
 *
 * @since 2.0.0 bbPress (r2858)
 *
 * @return void
 */
function bbp_reply_post_type() {
	echo bbp_get_reply_post_type();
}

/**
 * Return the unique id of the custom post type for replies.
 *
 * @since 2.0.0 bbPress (r2858)
 *
 * @return string The unique reply post type id.
 */
function bbp_get_reply_post_type() {

	/**
	 * Filters the reply post type ID.
	 *
	 * @since 2.0.0 bbPress (r2858)
	 *
	 * @param string $reply_post_type The unique reply post type id.
	 */
	return apply_filters( 'bbp_get_reply_post_type', bbpress()->reply_post_type );
}

/**
 * Return array of labels used by the reply post type.
 *
 * @since 2.5.0 bbPress (r5130)
 *
 * @return array<string, string>
 */
function bbp_get_reply_post_type_labels() {

	/**
	 * Filters the array of labels used by the reply post type.
	 *
	 * @since 2.5.0 bbPress (r5130)
	 *
	 * @param array<string, string> $value The array of labels used by the reply post type.
	 */
	return (array) apply_filters(
		'bbp_get_reply_post_type_labels',
		array(
			'name'                     => esc_attr__( 'Replies',                    'bbpress' ),
			'menu_name'                => esc_attr__( 'Replies',                    'bbpress' ),
			'singular_name'            => esc_attr_x( 'Reply', 'noun',              'bbpress' ),
			'all_items'                => esc_attr__( 'All Replies',                'bbpress' ),
			'add_new'                  => esc_attr__( 'Add New',                    'bbpress' ),
			'add_new_item'             => esc_attr__( 'Add Reply',                  'bbpress' ),
			'edit'                     => esc_attr__( 'Edit',                       'bbpress' ),
			'edit_item'                => esc_attr__( 'Edit Reply',                 'bbpress' ),
			'new_item'                 => esc_attr__( 'New Reply',                  'bbpress' ),
			'view'                     => esc_attr__( 'View Reply',                 'bbpress' ),
			'view_item'                => esc_attr__( 'View Reply',                 'bbpress' ),
			'view_items'               => esc_attr__( 'View Replies',               'bbpress' ),
			'search_items'             => esc_attr__( 'Search Replies',             'bbpress' ),
			'not_found'                => esc_attr__( 'No replies found',           'bbpress' ),
			'not_found_in_trash'       => esc_attr__( 'No replies found in Trash',  'bbpress' ),
			'filter_items_list'        => esc_attr__( 'Filter replies list',        'bbpress' ),
			'items_list'               => esc_attr__( 'Replies list',               'bbpress' ),
			'items_list_navigation'    => esc_attr__( 'Replies list navigation',    'bbpress' ),
			'parent_item_colon'        => esc_attr__( 'Parent Topic:',              'bbpress' ),
			'archives'                 => esc_attr__( 'Forum Replies',              'bbpress' ),
			'attributes'               => esc_attr__( 'Reply Attributes',           'bbpress' ),
			'insert_into_item'         => esc_attr__( 'Insert into reply',          'bbpress' ),
			'uploaded_to_this_item'    => esc_attr__( 'Uploaded to this reply',     'bbpress' ),
			'featured_image'           => esc_attr__( 'Reply Image',                'bbpress' ),
			'set_featured_image'       => esc_attr__( 'Set reply image',            'bbpress' ),
			'remove_featured_image'    => esc_attr__( 'Remove reply image',         'bbpress' ),
			'use_featured_image'       => esc_attr__( 'Use as reply image',         'bbpress' ),
			'item_published'           => esc_attr__( 'Reply published.',           'bbpress' ),
			'item_published_privately' => esc_attr__( 'Reply published privately.', 'bbpress' ),
			'item_reverted_to_draft'   => esc_attr__( 'Reply reverted to draft.',   'bbpress' ),
			'item_scheduled'           => esc_attr__( 'Reply scheduled.',           'bbpress' ),
			'item_updated'             => esc_attr__( 'Reply updated.',             'bbpress' )
		)
	);
}

/**
 * Return array of reply post type rewrite settings.
 *
 * @since 2.5.0 bbPress (r5130)
 *
 * @return array<string, mixed>
 */
function bbp_get_reply_post_type_rewrite() {

	/**
	 * Filters the array of reply post type rewrite settings.
	 *
	 * @since 2.5.0 bbPress (r5130)
	 *
	 * @param array<string, mixed> $value The array of reply post type rewrite settings.
	 */
	return (array) apply_filters(
		'bbp_get_reply_post_type_rewrite',
		array(
			'slug'       => bbp_get_reply_slug(),
			'with_front' => false
		)
	);
}

/**
 * Return array of features the reply post type supports.
 *
 * @since 2.5.0 bbPress (r5130)
 *
 * @return array<array-key, string>
 */
function bbp_get_reply_post_type_supports() {

	/**
	 * Filters the array of features the reply post type supports.
	 *
	 * @since 2.5.0 bbPress (r5130)
	 *
	 * @param array<array-key, string> $value The array of features the reply post type supports.
	 */
	return (array) apply_filters(
		'bbp_get_reply_post_type_supports',
		array(
			'title',
			'editor',
			'revisions'
		)
	);
}

/** Reply Loop Functions ******************************************************/

/**
 * The main reply loop. WordPress makes this easy for us.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param array<string, mixed> $args All the arguments supported by {@link WP_Query}.
 * @return bool Whether replies are available.
 */
function bbp_has_replies( $args = array() ) {

	/** Defaults **************************************************************/

	// Other defaults
	$default_reply_search   = bbp_sanitize_search_request( 'rs' );
	$default_post_parent    = ( bbp_is_single_topic() ) ? bbp_get_topic_id() : 'any';
	$default_post_type      = ( bbp_is_single_topic() && bbp_show_lead_topic() ) ? bbp_get_reply_post_type() : array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$default_thread_replies = (bool) ( bbp_is_single_topic() && bbp_thread_replies() );

	// Default query args
	$default = array(
		'post_type'              => $default_post_type,         // Only replies
		'post_parent'            => $default_post_parent,       // Of this topic
		'posts_per_page'         => bbp_get_replies_per_page(), // This many
		'paged'                  => bbp_get_paged(),            // On this page
		'orderby'                => 'date',                     // Sorted by date
		'order'                  => 'ASC',                      // Oldest to newest
		'hierarchical'           => $default_thread_replies,    // Hierarchical replies
		'ignore_sticky_posts'    => true,                       // Stickies not supported
		'update_post_term_cache' => false,                      // No terms to cache

		// Conditionally prime the cache for all related posts
		'update_post_family_cache' => true
	);

	// Only add 's' arg if searching for replies
	// See https://bbpress.trac.wordpress.org/ticket/2607
	if ( ! empty( $default_reply_search ) ) {
		$default['s'] = $default_reply_search;
	}
	$view_all_args   = wp_parse_args( $args );
	$view_all_parent = isset( $view_all_args['post_parent'] ) ? $view_all_args['post_parent'] : $default_post_parent;
	$view_all_parent = is_numeric( $view_all_parent ) && ( (int) $view_all_parent > 0 ) ? (int) $view_all_parent : 0;
	$view_all        = bbp_get_view_all_for_object( 'edit_others_replies', $view_all_parent );

	// What are the default allowed statuses (based on user caps)
	if ( $view_all ) {

		// Default view=all statuses
		$post_statuses = array_keys( bbp_get_topic_statuses() );

		// Add support for private status
		if ( current_user_can( 'read_private_replies' ) ) {
			$post_statuses[] = bbp_get_private_status_id();
		}

		// Join post statuses together
		$default['post_status'] = $post_statuses;

	// Lean on the 'perm' query var value of 'readable' to provide statuses
	} else {
		$default['perm'] = 'readable';
	}

	/** Setup *****************************************************************/

	// Parse arguments against default values
	$r                = bbp_parse_args( $args, $default, 'has_replies' );
	$requested_parent = bbp_parse_int( $r['post_parent'] );
	if ( $view_all && ! current_user_can( 'edit_others_replies' ) && ( $view_all_parent !== $requested_parent ) ) {
		unset( $r['post_status'] );
		$r['perm'] = 'readable';
		$view_all  = false;
	}

	// Set posts_per_page value if replies are threaded
	$replies_per_page = bbp_parse_int( $r['posts_per_page'] );
	if ( true === $r['hierarchical'] ) {
		$r['posts_per_page'] = -1;
	}

	// Get bbPress
	$bbp = bbpress();

	// Call the query
	$bbp->reply_query = bbp_get_reply_loop_query( $r );

	// Add pagination values to query object
	$bbp->reply_query->posts_per_page = (int) $replies_per_page;
	$bbp->reply_query->paged          = bbp_parse_int( $r['paged'] );

	// Never home, regardless of what parse_query says
	$bbp->reply_query->is_home        = false;

	// Reset is_single if single topic
	if ( bbp_is_single_topic() ) {
		$bbp->reply_query->is_single = true;
	}

	// Only add reply to if query returned results
	if ( ! empty( $bbp->reply_query->found_posts ) && is_array( $bbp->reply_query->posts ) ) {

		// Get reply to for each reply
		foreach ( $bbp->reply_query->posts as &$post ) {
			if ( ! ( $post instanceof WP_Post ) ) {
				continue;
			}

			// Check for reply post type
			if ( bbp_get_reply_post_type() === $post->post_type ) {
				$reply_to = bbp_get_reply_to( $post->ID );

				// Make sure it's a reply to a reply
				if ( empty( $reply_to ) || ( bbp_get_reply_topic_id( $post->ID ) === $reply_to ) ) {
					$reply_to = 0;
				}

				// Add reply_to to the post object so we can walk it later
				$post->reply_to = $reply_to;
			}
		}
	}

	// Only add pagination if query returned results
	if ( ! empty( $bbp->reply_query->found_posts ) && ! empty( $bbp->reply_query->posts_per_page ) && is_array( $bbp->reply_query->posts ) ) {

		// Figure out total pages
		if ( true === $r['hierarchical'] ) {
			$walker      = new BBP_Walker_Reply();
			$total_pages = ceil( $walker->get_number_of_root_elements( $bbp->reply_query->posts ) / $bbp->reply_query->posts_per_page );
		} else {

			// Total for pagination boundaries
			$total_pages = ( $bbp->reply_query->posts_per_page === $bbp->reply_query->found_posts )
				? 1
				: ceil( $bbp->reply_query->found_posts / $bbp->reply_query->posts_per_page );

			// Maybe add view-all args
			$add_args = $view_all
				? array( 'view' => 'all' )
				: false;

			// Pagination settings with filter

			/**
			 * Filters the replies pagination arguments.
			 *
			 * @since 2.0.0 bbPress (r2553)
			 *
			 * @param array $args The replies pagination arguments.
			 */
			$bbp_replies_pagination = apply_filters(
				'bbp_replies_pagination',
				array(
					'base'     => bbp_get_replies_pagination_base( bbp_get_topic_id() ),
					'total'    => $total_pages,
					'current'  => $bbp->reply_query->paged,
					'add_args' => $add_args
				)
			);

			// Add pagination to query object
			$bbp->reply_query->pagination_links = bbp_paginate_links( $bbp_replies_pagination );
		}
	}

	/**
	 * Filters whether replies are available.
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param bool $value Whether replies are available.
	 * @param WP_Query $reply_query Query object.
	 */
	return apply_filters( 'bbp_has_replies', $bbp->reply_query->have_posts(), $bbp->reply_query );
}

/**
 * Query replies for a reply loop.
 *
 * @since 2.7.0 bbPress (r8610)
 *
 * @param array<string, mixed> $args Parsed reply query arguments.
 * @return WP_Query Reply loop query.
 */
function bbp_get_reply_loop_query( $args ) {
	$query = new WP_Query( $args );

	// Maybe prime the post author caches
	if ( ! empty( $args['update_post_family_cache'] ) && is_array( $query->posts ) ) {
		bbp_update_post_family_caches( $query->posts );
	}

	return $query;
}

/**
 * Whether there are more replies available in the loop.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @return bool Whether another reply is available.
 */
function bbp_replies() {

	// Put into variable to check against next
	$have_posts = bbpress()->reply_query->have_posts();

	// Reset the post data when finished
	if ( empty( $have_posts ) ) {
		wp_reset_postdata();
	}

	return $have_posts;
}

/**
 * Loads up the current reply in the loop.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @return null Sets up the current reply and returns null.
 */
function bbp_the_reply() {
	return bbpress()->reply_query->the_post();
}

/**
 * Output reply id.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int|string $reply_id Optional. Reply ID.
 * @return void
 */
function bbp_reply_id( $reply_id = 0 ) {
	echo bbp_get_reply_id( $reply_id );
}

/**
 * Return the id of the reply in a replies loop.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int|float|string|false $reply_id Optional. Reply ID, or false to use the current reply.
 * @return int The reply id.
 */
function bbp_get_reply_id( $reply_id = 0 ) {
	$bbp      = bbpress();
	$wp_query = bbp_get_wp_query();

	// Easy empty checking
	if ( ! empty( $reply_id ) && is_numeric( $reply_id ) ) {
		$bbp_reply_id = $reply_id;

	// Currently inside a replies loop
	} elseif ( ! empty( $bbp->reply_query->in_the_loop ) && isset( $bbp->reply_query->post->ID ) ) {
		$bbp_reply_id = $bbp->reply_query->post->ID;

	// Currently inside a search loop
	} elseif ( ! empty( $bbp->search_query->in_the_loop ) && isset( $bbp->search_query->post->ID ) && bbp_is_reply( $bbp->search_query->post->ID ) ) {
		$bbp_reply_id = $bbp->search_query->post->ID;

	// Currently viewing a forum
	} elseif ( ( bbp_is_single_reply() || bbp_is_reply_edit() ) && ! empty( $bbp->current_reply_id ) ) {
		$bbp_reply_id = $bbp->current_reply_id;

	// Currently viewing a reply
	} elseif ( ( bbp_is_single_reply() || bbp_is_reply_edit() ) && isset( $wp_query->post->ID ) ) {
		$bbp_reply_id = $wp_query->post->ID;

	// Fallback
	} else {
		$bbp_reply_id = 0;
	}

	/**
	 * Filters the id of the reply in a replies loop.
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param int|float|numeric-string $bbp_reply_id Reply ID before normalization to an integer.
	 * @param int|float|string|false $reply_id Reply ID, or false to use the current reply.
	 */
	return (int) apply_filters( 'bbp_get_reply_id', $bbp_reply_id, $reply_id );
}

/**
 * Gets a reply.
 *
 * @since 2.0.0 bbPress (r2787)
 *
 * @param int|object $reply reply id or reply object.
 * @param string     $output Optional. OBJECT, ARRAY_A, or ARRAY_N. Default = OBJECT.
 * @param string     $filter Optional Sanitation filter. See {@link sanitize_post()}.
 * @return WP_Post|array<array-key, mixed>|null Reply in the requested format, or null if unavailable.
 * @phpstan-return ($output is 'ARRAY_A'|'ARRAY_N' ? array<array-key, mixed>|null : WP_Post|null)
 */
function bbp_get_reply( $reply, $output = OBJECT, $filter = 'raw' ) {

	// Maybe get ID from empty or int
	if ( empty( $reply ) || is_numeric( $reply ) ) {
		$reply = bbp_get_reply_id( $reply );
	}

	// Bail if no post object
	$reply = get_post( $reply, OBJECT, $filter );
	if ( empty( $reply ) ) {
		return $reply;
	}

	// Bail if not correct post type
	if ( bbp_get_reply_post_type() !== $reply->post_type ) {
		return null;
	}

	// Default return value is OBJECT
	$retval = $reply;

	// Array A
	if ( ARRAY_A === $output ) {
		$retval = get_object_vars( $reply );

	// Array N
	} elseif ( ARRAY_N === $output ) {
		$retval = array_values( get_object_vars( $reply ) );
	}

	/**
	 * Filters a reply.
	 *
	 * @since 2.0.0 bbPress (r2788)
	 *
	 * @param WP_Post|array<array-key, mixed>|null $retval Reply in the requested format, or null if unavailable.
	 * @param int|object $reply reply id or reply object.
	 * @param string $output OBJECT, ARRAY_A, or ARRAY_N. Default = OBJECT.
	 * @param string $filter Optional Sanitation filter. See {@link sanitize_post()}.
	 */
	return apply_filters( 'bbp_get_reply', $retval, $reply, $output, $filter );
}

/**
 * Output the link to the reply in the reply loop.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_permalink( $reply_id = 0 ) {
	echo esc_url( bbp_get_reply_permalink( $reply_id ) );
}

/**
 * Return the link to the reply.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id.
 *
 * @return string Permanent link to reply.
 */
function bbp_get_reply_permalink( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	/**
	 * Filters the link to the reply.
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param mixed $value Reply permalink, or false when unavailable.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_permalink', get_permalink( $reply_id ), $reply_id );
}

/**
 * Output the paginated url to the reply in the reply loop.
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_url( $reply_id = 0 ) {
	echo esc_url( bbp_get_reply_url( $reply_id ) );
}

/**
 * Return the paginated url to the reply in the reply loop.
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param int    $reply_id Optional. Reply id.
 * @param string $redirect_to Optional. Pass a redirect value for use with
 *                              shortcodes and other fun things.
 * @return string Link to reply relative to paginated topic.
 */
function bbp_get_reply_url( $reply_id = 0, $redirect_to = '' ) {

	// Set needed variables
	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = 0;

	// Juggle reply & topic IDs for unpretty URL formatting
	if ( bbp_is_reply( $reply_id ) ) {
		$topic_id = bbp_get_reply_topic_id( $reply_id );
		$topic    = bbp_get_topic( $topic_id );
	} elseif ( bbp_is_topic( $reply_id ) ) {
		$topic_id = bbp_get_topic_id( $reply_id );
		$topic    = bbp_get_topic( $topic_id );
		$reply_id = $topic_id;
	}
	$view_all = bbp_get_view_all_for_object( 'edit_others_replies', $topic_id );

	// Hierarchical reply page
	if ( bbp_thread_replies() ) {
		$reply_page = 1;

	// Standard reply page
	} else {
		$reply_page = ceil( (int) bbp_get_reply_position( $reply_id, $topic_id, $view_all ) / (int) bbp_get_replies_per_page() );
	}

	// Get links & URLS
	$reply_hash = '#post-' . $reply_id;
	$topic_link = bbp_get_topic_permalink( $topic_id, $redirect_to );
	$topic_url  = remove_query_arg( 'view', $topic_link );

	// Get vars needed to support pending topics with unpretty links
	$has_slug   = ! empty( $topic ) ? $topic->post_name : '';
	$pretty     = bbp_use_pretty_urls();
	$published  = bbp_is_topic_public( $topic_id );

	// Don't include pagination if on first page
	if ( 1 >= $reply_page ) {

		// Pretty permalinks
		if ( ! empty( $has_slug ) && ! empty( $pretty ) && ! empty( $published ) ) {
			$url = user_trailingslashit( $topic_url ) . $reply_hash;

		// Unpretty links
		} else {
			$url = $topic_url . $reply_hash;
		}

	// Include pagination
	// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found
	} else {

		// Pretty permalinks
		if ( ! empty( $has_slug ) && ! empty( $pretty ) && ! empty( $published ) ) {
			$url = trailingslashit( $topic_url ) . trailingslashit( bbp_get_paged_slug() ) . $reply_page;
			$url = user_trailingslashit( $url ) . $reply_hash;

		// Unpretty links
		} else {
			$url = add_query_arg( 'paged', $reply_page, $topic_url ) . $reply_hash;
		}
	}

	// Add topic view query arg back to end if it is set
	if ( $view_all ) {
		$url = bbp_add_view_all( $url, true );
	}

	/**
	 * Filters the paginated url to the reply in the reply loop.
	 *
	 * @since 2.0.0 bbPress (r2680)
	 *
	 * @param string $url Link to reply relative to paginated topic.
	 * @param int $reply_id Reply id.
	 * @param string $redirect_to Pass a redirect value for use with shortcodes and other fun things.
	 */
	return apply_filters( 'bbp_get_reply_url', $url, $reply_id, $redirect_to );
}

/**
 * Output the title of the reply.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_title( $reply_id = 0 ) {
	echo bbp_get_reply_title( $reply_id );
}

/**
 * Return the title of the reply.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string Title of reply.
 */
function bbp_get_reply_title( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );
	$title    = get_post_field( 'post_title', $reply_id );
	$title    = apply_filters( 'the_title', $title, $reply_id );

	/**
	 * Filters the title of the reply.
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param string $title Title of reply.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_title', $title, $reply_id );
}

/**
 * Get empty reply title fallback.
 *
 * @since 2.5.0 bbPress (r5177)
 *
 * @param string $post_title Required. Reply Title.
 * @param int    $post_id Required. Reply ID.
 * @return string Title of reply.
 */
function bbp_get_reply_title_fallback( $post_title = '', $post_id = 0 ) {

	// Bail if title not empty, or post is not a reply
	if ( ! empty( $post_title ) || ! bbp_is_reply( $post_id ) ) {
		return $post_title;
	}

	// Do not use an unreadable parent's title as the reply title.
	if ( ! bbp_user_can_embed_post( $post_id ) ) {
		// Pending replies are not embeddable, so check the reader's access.
		$can_read = is_user_logged_in() && current_user_can( 'read_reply', $post_id );

		if ( $can_read ) {
			$topic_id = bbp_get_reply_topic_id( $post_id );
			$can_read = bbp_is_topic( $topic_id ) && current_user_can( 'read_topic', $topic_id );
		}

		if ( $can_read ) {
			$forum_id = bbp_get_reply_forum_id( $post_id );
			$can_read = bbp_is_forum_visibility_valid( $forum_id )
				&& bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) )
				&& ! bbp_is_forum_restricted_for_user( $forum_id, bbp_get_current_user_id() );
		}

		if ( $can_read ) {
			$can_read = ! bbp_is_password_protected( $post_id );
		}

		if ( ! $can_read ) {
			/** This filter is documented in src/includes/replies/template.php. */
			return apply_filters( 'bbp_get_reply_title_fallback', esc_html__( 'Reply', 'bbpress' ), $post_id, '' );
		}
	}

	// Get reply topic title.
	$topic_title = bbp_get_reply_topic_title( $post_id );

	// Get empty reply title fallback.
	$reply_title = sprintf(
		/* translators: %s: Topic title */
		esc_html__( 'Reply To: %s', 'bbpress' ),
		$topic_title
	);

	/**
	 * Filters the empty reply title fallback.
	 *
	 * @since 2.5.0 bbPress (r5177)
	 *
	 * @param string $reply_title Title of reply.
	 * @param int $post_id Required. Reply ID.
	 * @param string $topic_title Topic title.
	 */
	return apply_filters( 'bbp_get_reply_title_fallback', $reply_title, $post_id, $topic_title );
}

/**
 * Output the content of the reply.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. reply id.
 * @return void
 */
function bbp_reply_content( $reply_id = 0 ) {
	echo bbp_get_reply_content( $reply_id );
}

/**
 * Return the content of the reply.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. reply id.
 * @return string Content of the reply.
 */
function bbp_get_reply_content( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	// Check the reply and its topic and forum ancestors.
	$password_id = bbp_get_password_required_id( $reply_id );
	if ( ! empty( $password_id ) ) {
		return get_the_password_form( $password_id );
	}

	$content = get_post_field( 'post_content', $reply_id );

	/**
	 * Filters the content of the reply.
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param string $content Content of the reply.
	 * @param int $reply_id reply id.
	 */
	return apply_filters( 'bbp_get_reply_content', $content, $reply_id );
}

/**
 * Output the excerpt of the reply.
 *
 * @since 2.0.0 bbPress (r2751)
 *
 * @param int $reply_id Optional. Reply id.
 * @param int $length Optional. Length of the excerpt. Defaults to 100 letters.
 * @return void
 */
function bbp_reply_excerpt( $reply_id = 0, $length = 100 ) {
	echo bbp_get_reply_excerpt( $reply_id, $length );
}

/**
 * Return the excerpt of the reply.
 *
 * @since 2.0.0 bbPress (r2751)
 *
 * @param int $reply_id Optional. Reply id.
 * @param int $length Optional. Length of the excerpt. Defaults to 100
 *                     letters.
 * @return string Reply Excerpt.
 */
function bbp_get_reply_excerpt( $reply_id = 0, $length = 100 ) {
	$reply_id    = bbp_get_reply_id( $reply_id );
	$length      = (int) $length;
	$password_id = bbp_get_password_required_id( $reply_id );
	$excerpt     = ! empty( $password_id )
		? get_the_password_form( $password_id )
		: get_post_field( 'post_excerpt', $reply_id );

	if ( empty( $excerpt ) ) {
		$excerpt = bbp_get_reply_content( $reply_id );
	}

	$excerpt = wp_strip_all_tags( $excerpt );

	// Multibyte support
	if ( function_exists( 'mb_strlen' ) ) {
		$excerpt_length = mb_strlen( $excerpt );
	} else {
		$excerpt_length = strlen( $excerpt );
	}

	if ( ! empty( $length ) && ( $excerpt_length > $length ) ) {
		$excerpt  = mb_substr( $excerpt, 0, $length - 1 );
		$excerpt .= '&hellip;';
	}

	/**
	 * Filters the excerpt of the reply.
	 *
	 * @since 2.0.0 bbPress (r2751)
	 *
	 * @param string $excerpt Reply Excerpt.
	 * @param int $reply_id Reply id.
	 * @param int $length Length of the excerpt. Defaults to 100 letters.
	 */
	return apply_filters( 'bbp_get_reply_excerpt', $excerpt, $reply_id, $length );
}

/**
 * Output the post date and time of a reply.
 *
 * @since 2.2.0 bbPress (r4156)
 * @since 2.6.20 bbPress (r7972) Added the `$date_format` parameter.
 *
 * @param int    $reply_id Optional. Reply id.
 * @param bool   $humanize Optional. Humanize output using time_since.
 * @param bool   $gmt Optional. Use GMT.
 * @param string $date_format Optional. PHP date format. Defaults to the WordPress date format.
 *                            Ignored when `$humanize` is true.
 *
 * @return void
 */
function bbp_reply_post_date( $reply_id = 0, $humanize = false, $gmt = false, $date_format = '' ) {
	echo bbp_get_reply_post_date( $reply_id, $humanize, $gmt, $date_format );
}

/**
 * Return the post date and time of a reply.
 *
 * @since 2.2.0 bbPress (r4156)
 * @since 2.6.20 bbPress (r7972) Added the `$date_format` parameter.
 *
 * @param int    $reply_id Optional. Reply id.
 * @param bool   $humanize Optional. Humanize output using time_since.
 * @param bool   $gmt Optional. Use GMT.
 * @param string $date_format Optional. PHP date format. Defaults to the WordPress date format.
 *                            Ignored when `$humanize` is true.
 * @return string
 */
function bbp_get_reply_post_date( $reply_id = 0, $humanize = false, $gmt = false, $date_format = '' ) {
	$reply_id    = bbp_get_reply_id( $reply_id );
	$date_format = ! empty( $date_format ) ? $date_format : get_option( 'date_format' );

	// 4 days, 4 hours ago
	if ( ! empty( $humanize ) ) {
		$gmt_s  = ! empty( $gmt ) ? 'G' : 'U';
		$date   = get_post_time( $gmt_s, $gmt, $reply_id );
		$time   = false; // For filter below
		$result = bbp_get_time_since( $date );

	// August 4, 2012 at 2:37 pm
	} else {
		$date   = get_post_time( $date_format, $gmt, $reply_id, true );
		$time   = get_post_time( get_option( 'time_format' ), $gmt, $reply_id, true );
		/* translators: 1: Date, 2: Time */
		$result = sprintf( _x( '%1$s at %2$s', 'date at time', 'bbpress' ), $date, $time );
	}

	/**
	 * Filters the post date and time of a reply.
	 *
	 * @since 2.2.0 bbPress (r4156)
	 *
	 * @param string $result The post date and time of a reply.
	 * @param int $reply_id Reply id.
	 * @param bool $humanize Humanize output using time_since.
	 * @param bool $gmt Use GMT.
	 * @param int|string|false $date Formatted date or timestamp, or false when unavailable.
	 * @param int|string|false $time Formatted time or timestamp, or false when unavailable.
	 * @param string $date_format PHP date format. Defaults to the WordPress date format. Ignored when `$humanize` is true.
	 */
	return apply_filters( 'bbp_get_reply_post_date', $result, $reply_id, $humanize, $gmt, $date, $time, $date_format );
}

/**
 * Append revisions to the reply content.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param string $content Optional. Content to which we need to append the revisions to.
 * @param int    $reply_id Optional. Reply id.
 * @return string Content with the revisions appended.
 */
function bbp_reply_content_append_revisions( $content = '', $reply_id = 0 ) {

	// Bail if in admin or feed
	if ( is_admin() || is_feed() ) {
		return $content;
	}

	// Validate the ID
	$reply_id = bbp_get_reply_id( $reply_id );

	/**
	 * Filters the content with the revisions appended.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param string $value Content with the revisions appended.
	 * @param string $content Content to which we need to append the revisions to.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_reply_append_revisions', $content . bbp_get_reply_revision_log( $reply_id ), $content, $reply_id );
}

/**
 * Output the revision log of the reply.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_revision_log( $reply_id = 0 ) {
	echo bbp_get_reply_revision_log( $reply_id );
}

/**
 * Return the formatted revision log of the reply.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string|false Reply revision log, or false when no revisions are available.
 */
function bbp_get_reply_revision_log( $reply_id = 0 ) {

	// Create necessary variables
	$reply_id = bbp_get_reply_id( $reply_id );

	// Show the topic reply log if this is a topic in a reply loop
	if ( bbp_is_topic( $reply_id ) ) {
		return bbp_get_topic_revision_log( $reply_id );
	}

	// Get the reply revision log (out of post meta
	$revision_log = bbp_get_reply_raw_revision_log( $reply_id );

	// Check reply and revision log exist
	if ( empty( $reply_id ) || empty( $revision_log ) || ! is_array( $revision_log ) ) {
		return false;
	}

	// Get the actual revisions
	$revisions = bbp_get_reply_revisions( $reply_id );
	if ( empty( $revisions ) ) {
		return false;
	}

	$r = "\n\n" . '<ul id="bbp-reply-revision-log-' . esc_attr( (string) $reply_id ) . '" class="bbp-reply-revision-log">' . "\n\n";

	// Loop through revisions
	foreach ( (array) $revisions as $revision ) {

		if ( empty( $revision_log[ $revision->ID ] ) ) {
			$author_id = $revision->post_author;
			$reason    = '';
		} else {
			$author_id = bbp_parse_int( $revision_log[ $revision->ID ]['author'] );
			$reason    = $revision_log[ $revision->ID ]['reason'];

			if ( empty( $author_id ) ) {
				$author_id = $revision->post_author;
			}
		}

		$since  = bbp_get_time_since( bbp_convert_date( $revision->post_modified ) );
		$author = bbp_get_author_link(
			array(
				'post_id'   => $revision->ID,
				'user_id'   => $author_id,
				'size'      => 14,
				'link_text' => bbp_get_reply_author_display_name( $revision->ID )
			)
		);

		$r .= "\t" . '<li id="bbp-reply-revision-log-' . esc_attr( (string) $reply_id ) . '-item-' . esc_attr( (string) $revision->ID ) . '" class="bbp-reply-revision-log-item">' . "\n";
		if ( ! empty( $reason ) ) {
			/* translators: 1: Time since modification, 2: Author linked display name, 3: Modification reason */
			$r .= "\t\t" . sprintf( esc_html__( 'This reply was modified %1$s by %2$s. Reason: %3$s', 'bbpress' ), esc_html( $since ), $author, esc_html( $reason ) ) . "\n";
		} else {
			/* translators: 1: Time since modification, 2: Author linked display name */
			$r .= "\t\t" . sprintf( esc_html__( 'This reply was modified %1$s by %2$s.', 'bbpress' ), esc_html( $since ), $author ) . "\n";
		}

		$r .= "\t" . '</li>' . "\n";
	}

	$r .= "\n" . '</ul>' . "\n\n";

	/**
	 * Filters the formatted revision log of the reply.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param string|false $r Reply revision log, or false when no revisions are available.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_revision_log', $r, $reply_id );
}

/**
 * Return the raw revision log of the reply.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id.
 * @return array<array-key, mixed> Raw revision log of the reply, or an empty array.
 */
function bbp_get_reply_raw_revision_log( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$revision_log = get_post_meta( $reply_id, '_bbp_revision_log', true );
	$revision_log = ! empty( $revision_log )
		? $revision_log
		: array();

	/**
	 * Filters the raw revision log of the reply.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param array<array-key, mixed> $revision_log Raw revision log of the reply, or an empty array.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_raw_revision_log', $revision_log, $reply_id );
}

/**
 * Return the revisions of the reply.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id.
 * @return WP_Post[] Reply revisions, keyed by revision ID.
 */
function bbp_get_reply_revisions( $reply_id = 0 ) {
	$reply_id  = bbp_get_reply_id( $reply_id );
	$revisions = wp_get_post_revisions( $reply_id, array( 'order' => 'ASC' ) );

	/**
	 * Filters the revisions of the reply.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param mixed $revisions Reply revision IDs or posts, keyed by revision ID.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_revisions', $revisions, $reply_id );
}

/**
 * Return the revision count of the reply.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int     $reply_id Optional. Reply id.
 * @param boolean $integer Optional. Whether or not to format the result.
 * @return string|int Formatted revision count, or integer when $integer is true.
 */
function bbp_get_reply_revision_count( $reply_id = 0, $integer = false ) {
	$reply_id = bbp_get_reply_id( $reply_id );
	$count    = count( bbp_get_reply_revisions( $reply_id ) );
	$filter   = ( true === $integer )
		? 'bbp_get_reply_revision_count_int'
		: 'bbp_get_reply_revision_count';

	/**
	 * Filters a reply's revision count.
	 *
	 * The hook name is `bbp_get_reply_revision_count_int` for integer output and
	 * `bbp_get_reply_revision_count` for formatted output.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 * @since 2.2.0 bbPress (r4258) Added the integer-count filter.
	 *
	 * @param int|string $count Unformatted count, or a formatted count after filtering.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( $filter, $count, $reply_id );
}

/**
 * Output the status of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_status( $reply_id = 0 ) {
	echo bbp_get_reply_status( $reply_id );
}

/**
 * Return the status of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string Status of reply.
 */
function bbp_get_reply_status( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	/**
	 * Filters the status of the reply.
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param mixed $value Status of reply.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_status', get_post_status( $reply_id ), $reply_id );
}

/**
 * Is the reply publicly viewable?
 *
 * See bbp_get_public_reply_statuses() for public statuses.
 *
 * @since 2.6.0 bbPress (r6791)
 *
 * @param int $reply_id Optional. Reply id.
 * @return bool True if public, false if not.
 */
function bbp_is_reply_public( $reply_id = 0 ) {
	$reply_id  = bbp_get_reply_id( $reply_id );
	$status    = bbp_get_reply_status( $reply_id );
	$public    = bbp_get_public_reply_statuses();
	$is_public = in_array( $status, $public, true );

	/**
	 * Filters whether the reply is publicly viewable.
	 *
	 * @since 2.6.0 bbPress (r6791)
	 *
	 * @param bool $is_public True if public, false if not.
	 * @param int $reply_id Reply id.
	 */
	return (bool) apply_filters( 'bbp_is_reply_public', $is_public, $reply_id );
}

/**
 * Is the reply not spam or deleted?
 *
 * @since 2.0.0 bbPress (r3501)
 * @since 2.6.0 bbPress (r6922) Returns false if topic is also not published
 *
 * @param int $reply_id Optional. Topic id.
 * @return bool True if published, false if not.
 */
function bbp_is_reply_published( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$topic_id     = bbp_get_reply_topic_id( $reply_id );
	$status       = bbp_get_public_status_id();
	$topic_status = bbp_is_topic_published( $topic_id );
	$reply_status = ( bbp_get_reply_status( $reply_id ) === $status );
	$retval       = ( $reply_status && $topic_status );

	/**
	 * Filters whether the reply is not spam or deleted.
	 *
	 * @since 2.0.0 bbPress (r3501)
	 *
	 * @param bool $retval True if published, false if not.
	 * @param int $reply_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_reply_published', (bool) $retval, $reply_id );
}

/**
 * Is the reply marked as spam?
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $reply_id Optional. Reply id.
 * @return bool True if spam, false if not.
 */
function bbp_is_reply_spam( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$status       = bbp_get_spam_status_id();
	$reply_status = bbp_get_reply_status( $reply_id ) === $status;

	/**
	 * Filters whether the reply is marked as spam.
	 *
	 * @since 2.0.0 bbPress (r2858)
	 *
	 * @param bool $reply_status True if spam, false if not.
	 * @param int $reply_id Reply id.
	 */
	return (bool) apply_filters( 'bbp_is_reply_spam', (bool) $reply_status, $reply_id );
}

/**
 * Is the reply trashed?
 *
 * @since 2.0.0 bbPress (r2892)
 *
 * @param int $reply_id Optional. Topic id.
 * @return bool True if spam, false if not.
 */
function bbp_is_reply_trash( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$status       = bbp_get_trash_status_id();
	$reply_status = bbp_get_reply_status( $reply_id ) === $status;

	/**
	 * Filters whether the reply is trashed.
	 *
	 * @since 2.0.0 bbPress (r2929)
	 *
	 * @param bool $reply_status Whether the reply is trashed.
	 * @param int $reply_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_reply_trash', (bool) $reply_status, $reply_id );
}

/**
 * Is the reply pending?
 *
 * @since 2.6.0 bbPress (r5507)
 *
 * @param int $reply_id Optional. Topic id.
 * @return bool True if pending, false if not.
 */
function bbp_is_reply_pending( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$status       = bbp_get_pending_status_id();
	$reply_status = bbp_get_reply_status( $reply_id ) === $status;

	/**
	 * Filters whether the reply is pending.
	 *
	 * @since 2.6.0 bbPress (r5507)
	 *
	 * @param bool $reply_status True if pending, false if not.
	 * @param int $reply_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_reply_pending', (bool) $reply_status, $reply_id );
}

/**
 * Is the reply private?
 *
 * @since 2.6.0 bbPress (r5507)
 *
 * @param int $reply_id Optional. Topic id.
 * @return bool True if private, false if not.
 */
function bbp_is_reply_private( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$status       = bbp_get_private_status_id();
	$reply_status = bbp_get_reply_status( $reply_id ) === $status;

	/**
	 * Filters whether the reply is private.
	 *
	 * @since 2.6.0 bbPress (r5507)
	 *
	 * @param bool $reply_status True if private, false if not.
	 * @param int $reply_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_reply_private', (bool) $reply_status, $reply_id );
}

/**
 * Is the reply by an anonymous user?
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $reply_id Optional. Reply id.
 * @return bool True if the post is by an anonymous user, false if not.
 */
function bbp_is_reply_anonymous( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );
	$retval   = false;

	if ( ! bbp_get_reply_author_id( $reply_id ) ) {
		$retval = true;

	} elseif ( get_post_meta( $reply_id, '_bbp_anonymous_name', true ) ) {
		$retval = true;

	} elseif ( get_post_meta( $reply_id, '_bbp_anonymous_email', true ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the reply is by an anonymous user.
	 *
	 * @since 2.0.0 bbPress (r2858)
	 *
	 * @param bool $retval True if the post is by an anonymous user, false if not.
	 * @param int $reply_id Reply id.
	 */
	return (bool) apply_filters( 'bbp_is_reply_anonymous', $retval, $reply_id );
}

/**
 * Deprecated. Use bbp_reply_author_display_name() instead.
 *
 * Output the author of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @deprecated 2.5.0 bbPress (r5119)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_author( $reply_id = 0 ) {
	echo bbp_get_reply_author_display_name( $reply_id );
}

/**
 * Deprecated. Use bbp_get_reply_author_display_name() instead.
 *
 * Return the author of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @deprecated 2.5.0 bbPress (r5119)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string Author of reply.
 */
function bbp_get_reply_author( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	if ( ! bbp_is_reply_anonymous( $reply_id ) ) {
		$author = get_the_author_meta( 'display_name', bbp_get_reply_author_id( $reply_id ) );
	} else {
		$author = get_post_meta( $reply_id, '_bbp_anonymous_name', true );
	}

	/**
	 * Filters the author of reply.
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param string $author Author of reply.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_author', $author, $reply_id );
}

/**
 * Output the author ID of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_author_id( $reply_id = 0 ) {
	echo bbp_get_reply_author_id( $reply_id );
}

/**
 * Return the author ID of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @return int Author ID of the reply.
 */
function bbp_get_reply_author_id( $reply_id = 0 ) {
	$reply_id  = bbp_get_reply_id( $reply_id );
	$author_id = get_post_field( 'post_author', $reply_id );

	/**
	 * Filters the author ID of the reply.
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param string $author_id Stored author ID before integer conversion.
	 * @param int $reply_id Reply id.
	 */
	return (int) apply_filters( 'bbp_get_reply_author_id', $author_id, $reply_id );
}

/**
 * Output the author display_name of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_author_display_name( $reply_id = 0 ) {
	echo bbp_get_reply_author_display_name( $reply_id );
}

/**
 * Return the author display_name of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string The display name of the author of the reply.
 */
function bbp_get_reply_author_display_name( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	// User is not a guest
	if ( ! bbp_is_reply_anonymous( $reply_id ) ) {

		// Get the author ID
		$author_id = bbp_get_reply_author_id( $reply_id );

		// Try to get a display name
		$author_name = get_the_author_meta( 'display_name', $author_id );

		// Fall back to user login
		if ( empty( $author_name ) ) {
			$author_name = get_the_author_meta( 'user_login', $author_id );
		}

	// User does not have an account
	} else {
		$author_name = get_post_meta( $reply_id, '_bbp_anonymous_name', true );
	}

	// Fallback if nothing could be found
	if ( empty( $author_name ) ) {
		$author_name = bbp_get_fallback_display_name( $reply_id );
	}

	/**
	 * Filters a reply author's display name.
	 *
	 * @since 2.0.0 bbPress (r2720)
	 *
	 * @param string $author_name The display name of the author of the reply.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_author_display_name', $author_name, $reply_id );
}

/**
 * Output the author avatar of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @param int $size Optional. Size of the avatar. Defaults to 40.
 * @return void
 */
function bbp_reply_author_avatar( $reply_id = 0, $size = 40 ) {
	echo bbp_get_reply_author_avatar( $reply_id, $size );
}

/**
 * Return the author avatar of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply ID.
 * @param int $size Optional. Size of the avatar. Defaults to 40.
 * @return string Avatar of author of the reply.
 */
function bbp_get_reply_author_avatar( $reply_id = 0, $size = 40 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	if ( ! empty( $reply_id ) ) {

		// Check for anonymous user
		if ( ! bbp_is_reply_anonymous( $reply_id ) ) {
			$author_avatar = get_avatar( bbp_get_reply_author_id( $reply_id ), $size );
		} else {
			$author_avatar = get_avatar( get_post_meta( $reply_id, '_bbp_anonymous_email', true ), $size );
		}
	} else {
		$author_avatar = '';
	}

	/**
	 * Filters the author avatar of the reply.
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param mixed $author_avatar Avatar of author of the reply.
	 * @param int $reply_id Reply ID.
	 * @param int $size Size of the avatar. Defaults to 40.
	 */
	return apply_filters( 'bbp_get_reply_author_avatar', $author_avatar, $reply_id, $size );
}

/**
 * Output the author link of the reply.
 *
 * @since 2.0.0 bbPress (r2720)
 *
 * @param array<string, mixed>|int $args Optional. Arguments or a reply ID.
 * @return void
 */
function bbp_reply_author_link( $args = array() ) {
	echo bbp_get_reply_author_link( $args );
}

/**
 * Return the author link of the reply.
 *
 * @since 2.0.0 bbPress (r2720)
 *
 * @param array<string, mixed>|int $args Optional. Arguments or a reply ID.
 * @return string Author link of reply.
 */
function bbp_get_reply_author_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'post_id'    => 0,
			'link_title' => '',
			'type'       => 'both',
			'size'       => 80,
			'sep'        => '',
			'show_role'  => false
		),
		'get_reply_author_link'
	);

	$post_arg  = is_numeric( $r['post_id'] ) ? $r['post_id'] : 0;
	$title_arg = bbp_get_string_arg( $r, 'link_title' );
	$sep       = bbp_get_string_arg( $r, 'sep' );
	$size      = is_numeric( $r['size'] ) ? (int) $r['size'] : 80;

	// Default return value
	$author_link = '';

	// Used as reply_id
	$reply_id = is_numeric( $args )
		? bbp_get_reply_id( $args )
		: bbp_get_reply_id( $post_arg );

	// Reply ID is good
	if ( ! empty( $reply_id ) ) {

		// Get some useful reply information
		$author_url = bbp_validate_url( bbp_get_reply_author_url( $reply_id ) );
		$anonymous  = bbp_is_reply_anonymous( $reply_id );

		// Tweak link title if empty
		if ( empty( $title_arg ) ) {
			$author = bbp_get_reply_author_display_name( $reply_id );
			$title  = empty( $anonymous )
				/* translators: %s: Author's display name */
				? esc_attr__( "View %s's profile",  'bbpress' )
				/* translators: %s: Author's display name */
				: esc_attr__( "Visit %s's website", 'bbpress' );

			$link_title = sprintf( $title, $author );

		// Use what was passed if not
		} else {
			$link_title = $title_arg;
		}

		// Setup title and author_links array
		$author_links = array();
		$link_title   = ! empty( $link_title )
			? ' title="' . esc_attr( $link_title ) . '"'
			: '';

		// Get avatar (unescaped, because HTML)
		if ( ( 'avatar' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
			$author_links['avatar'] = bbp_get_reply_author_avatar( $reply_id, $size );
		}

		// Get display name (escaped, because never HTML)
		if ( ( 'name' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
			$author_links['name'] = esc_html( bbp_get_reply_author_display_name( $reply_id ) );
		}

		// Empty array
		$links  = array();
		$sprint = '<span%1$s>%2$s</span>';

		// Wrap each link
		foreach ( $author_links as $link => $link_html ) {
			$link_class = ' class="bbp-author-' . esc_attr( $link ) . '"';
			$links[]    = sprintf( $sprint, $link_class, $link_html );
		}

		// Juggle
		$author_links = $links;
		unset( $links );

		// Filter sections

		/**
		 * Filters the links in a reply author's information.
		 *
		 * @since 2.6.0 bbPress (r6591)
		 *
		 * @param array $author_links The links in a reply author's information.
		 * @param array $r Parsed arguments.
		 * @param array<string, mixed>|int $args Original arguments.
		 */
		$sections    = apply_filters( 'bbp_get_reply_author_links', $author_links, $r, $args );

		// Assemble sections into author link
		$author_link = implode( $sep, $sections );

		// Only wrap in link if profile exists
		if ( empty( $anonymous ) && ( false !== $author_url ) && bbp_user_has_profile( bbp_get_reply_author_id( $reply_id ) ) ) {
			$author_link = sprintf( '<a href="%1$s"%2$s%3$s>%4$s</a>', esc_url( $author_url ), $link_title, ' class="bbp-author-link"', $author_link );
		}

		// Role is not linked
		if ( true === $r['show_role'] ) {
			$author_link .= bbp_get_reply_author_role( array( 'reply_id' => $reply_id ) );
		}
	}

	/**
	 * Filters the author link of the reply.
	 *
	 * @since 2.0.0 bbPress (r2720)
	 *
	 * @param string $author_link Author link of reply.
	 * @param array $r Arguments.
	 * @param array<string, mixed>|int $args Arguments or a reply ID.
	 */
	return apply_filters( 'bbp_get_reply_author_link', $author_link, $r, $args );
}

/**
 * Output the author url of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_author_url( $reply_id = 0 ) {
	echo esc_url( bbp_get_reply_author_url( $reply_id ) );
}

/**
 * Return the author url of the reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string Author URL of the reply.
 */
function bbp_get_reply_author_url( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	// Check for anonymous user or non-existant user
	if ( ! bbp_is_reply_anonymous( $reply_id ) && bbp_user_has_profile( bbp_get_reply_author_id( $reply_id ) ) ) {
		$author_url = bbp_get_user_profile_url( bbp_get_reply_author_id( $reply_id ) );
	} else {
		$author_url = get_post_meta( $reply_id, '_bbp_anonymous_website', true );

		if ( empty( $author_url ) ) {
			$author_url = '';
		}
	}

	/**
	 * Filters the author url of the reply.
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param string $author_url Author URL of the reply.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_author_url', $author_url, $reply_id );
}

/**
 * Output the reply author email address.
 *
 * @since 2.0.0 bbPress (r3446)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_author_email( $reply_id = 0 ) {
	echo bbp_get_reply_author_email( $reply_id );
}

/**
 * Return the reply author email address.
 *
 * @since 2.0.0 bbPress (r3446)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string Reply author email address.
 */
function bbp_get_reply_author_email( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	// Not anonymous
	if ( ! bbp_is_reply_anonymous( $reply_id ) ) {

		// Use reply author email address
		$user_id      = bbp_get_reply_author_id( $reply_id );
		$user         = get_userdata( $user_id );
		$author_email = ! empty( $user->user_email ) ? $user->user_email : '';

	// Anonymous
	} else {

		// Get email from post meta
		$author_email = get_post_meta( $reply_id, '_bbp_anonymous_email', true );

		// Sanity check for missing email address
		if ( empty( $author_email ) ) {
			$author_email = '';
		}
	}

	/**
	 * Filters the reply author email address.
	 *
	 * @since 2.0.0 bbPress (r3446)
	 *
	 * @param string $author_email Reply author email address.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_author_email', $author_email, $reply_id );
}

/**
 * Output the reply author role.
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param array<string, mixed> $args Optional.
 * @return void
 */
function bbp_reply_author_role( $args = array() ) {
	echo bbp_get_reply_author_role( $args );
}

/**
 * Return the reply author role.
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param array<string, mixed> $args Optional.
 * @return string Reply author role.
 */
function bbp_get_reply_author_role( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'reply_id' => 0,
			'class'    => false,
			'before'   => '<div class="bbp-author-role">',
			'after'    => '</div>'
		),
		'get_reply_author_role'
	);

	$reply_id = bbp_get_reply_id( $r['reply_id'] );
	$role     = bbp_get_user_display_role( bbp_get_reply_author_id( $reply_id ) );
	$css_role = sanitize_key( $role );

	// Add role as CSS class to default 'before' value.
	if ( empty( $args['before'] ) ) {
		$r['before'] = '<div class="bbp-author-role bbp-role-' . esc_attr( $css_role ) . '">';
	}

	$before_html = bbp_get_string_arg( $r, 'before' );
	$after_html  = bbp_get_string_arg( $r, 'after' );

	// Backwards compatibilty with old 'class' argument
	if ( ! empty( $r['class'] ) ) {
		$author_role = sprintf( '%1$s<div class="%2$s">%3$s</div>%4$s', $before_html, esc_attr( $r['class'] ), esc_html( $role ), $after_html );

	// Simpler before & after arguments
	// https://bbpress.trac.wordpress.org/ticket/2557
	} else {
		$author_role = $before_html . $role . $after_html;
	}

	/**
	 * Filters the reply author role.
	 *
	 * @since 2.1.0 bbPress (r3860)
	 *
	 * @param string $author_role Reply author role.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_reply_author_role', $author_role, $r, $args );
}

/**
 * Output the topic title a reply belongs to.
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_topic_title( $reply_id = 0 ) {
	echo bbp_get_reply_topic_title( $reply_id );
}

/**
 * Return the topic title a reply belongs to.
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string The topic title of the reply.
 */
function bbp_get_reply_topic_title( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = bbp_get_reply_topic_id( $reply_id );
	$title    = bbp_get_topic_title( $topic_id );

	/**
	 * Filters the topic title a reply belongs to.
	 *
	 * @since 2.0.0 bbPress (r2680)
	 *
	 * @param string $title The topic title of the reply.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_topic_title', $title, $reply_id );
}

/**
 * Output the topic id a reply belongs to.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_topic_id( $reply_id = 0 ) {
	echo bbp_get_reply_topic_id( $reply_id );
}

/**
 * Return the topic id a reply belongs to.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int|false $reply_id Optional. Reply ID, or false to use the current reply.
 * @return int The topic id of the reply.
 */
function bbp_get_reply_topic_id( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = (int) get_post_field( 'post_parent', $reply_id );

	// Meta-data fallback
	if ( empty( $topic_id ) ) {
		$topic_id = bbp_parse_int( get_post_meta( $reply_id, '_bbp_topic_id', true ) );
	}

	// Filter
	if ( ! empty( $topic_id ) ) {
		$topic_id = (int) bbp_get_topic_id( $topic_id );
	}

	/**
	 * Filters the topic id a reply belongs to.
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param int $topic_id The topic id of the reply.
	 * @param int|false $reply_id Reply ID, or false to use the current reply.
	 */
	return (int) apply_filters( 'bbp_get_reply_topic_id', $topic_id, $reply_id );
}

/**
 * Output the forum id a reply belongs to.
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_forum_id( $reply_id = 0 ) {
	echo bbp_get_reply_forum_id( $reply_id );
}

/**
 * Return the forum id a reply belongs to.
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param int $reply_id Optional. Reply id.
 *
 * @return int The forum id of the reply.
 */
function bbp_get_reply_forum_id( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = bbp_get_reply_topic_id( $reply_id );
	$forum_id = (int) get_post_field( 'post_parent', $topic_id );

	// Meta-data fallback
	if ( empty( $forum_id ) ) {
		$forum_id = bbp_parse_int( get_post_meta( $reply_id, '_bbp_forum_id', true ) );
	}

	// Filter
	if ( ! empty( $forum_id ) ) {
		$forum_id = (int) bbp_get_forum_id( $forum_id );
	}

	/**
	 * Filters the forum id a reply belongs to.
	 *
	 * @since 2.0.0 bbPress (r2858)
	 *
	 * @param int $forum_id The forum id of the reply.
	 * @param int $reply_id Reply id.
	 */
	return (int) apply_filters( 'bbp_get_reply_forum_id', $forum_id, $reply_id );
}

/**
 * Output the ancestor reply id of a reply.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_ancestor_id( $reply_id = 0 ) {
	echo bbp_get_reply_ancestor_id( $reply_id );
}

/**
 * Return the ancestor reply id of a reply.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param int $reply_id Reply ID.
 * @return int|false Ancestor reply ID, or false if the reply is invalid.
 */
function bbp_get_reply_ancestor_id( $reply_id = 0 ) {

	// Validation
	$reply_id = bbp_get_reply_id( $reply_id );
	if ( empty( $reply_id ) ) {
		return false;
	}

	// Find highest reply ancestor
	$ancestor_id = $reply_id;

	// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
	while ( $parent_id = bbp_get_reply_to( $ancestor_id ) ) {
		if ( empty( $parent_id ) || ( $parent_id === $ancestor_id ) || ( bbp_get_reply_topic_id( $reply_id ) === $parent_id ) || ( $parent_id === $reply_id ) ) {
			break;
		}

		$ancestor_id = $parent_id;
	}

	return (int) $ancestor_id;
}

/**
 * Output the reply to id of a reply.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_to( $reply_id = 0 ) {
	echo bbp_get_reply_to( $reply_id );
}

/**
 * Return the reply to id of a reply.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param int $reply_id Optional. Reply id.
 * @return int The parent reply id of the reply.
 */
function bbp_get_reply_to( $reply_id = 0 ) {

	// Assume there is no reply_to set
	$reply_to = 0;

	// Check that reply_id is valid
	$reply_id = bbp_get_reply_id( $reply_id );

	// Get reply_to value
	if ( ! empty( $reply_id ) ) {
		$reply_to = bbp_parse_int( get_post_meta( $reply_id, '_bbp_reply_to', true ) );
	}

	/**
	 * Filters the reply to id of a reply.
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param int $reply_to The parent reply id of the reply.
	 * @param int $reply_id Reply id.
	 */
	return (int) apply_filters( 'bbp_get_reply_to', $reply_to, $reply_id );
}

/**
 * Output the link for the reply to.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param array<string, mixed> $args Reply link arguments.
 * @return void
 */
function bbp_reply_to_link( $args = array() ) {
	echo bbp_get_reply_to_link( $args );
}

/**
 * Return the link for a reply to a reply.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param array<string, mixed> $args Arguments.
 * @return string|null Link for a reply to a reply, or null when unavailable.
 */
function bbp_get_reply_to_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'reply_text'  => esc_html_x( 'Reply', 'verb', 'bbpress' ),
			'depth'       => 0,
			'add_below'   => 'post',
			'respond_id'  => 'new-reply-' . bbp_get_topic_id(),
		),
		'get_reply_to_link'
	);

	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$reply_html  = bbp_get_string_arg( $r, 'reply_text' );
	$add_below   = bbp_get_string_arg( $r, 'add_below' );
	$respond_id  = bbp_get_string_arg( $r, 'respond_id' );

	// Get the reply and its topic ID
	$reply = bbp_get_reply_for_link( $r );
	if ( ! ( $reply instanceof WP_Post ) ) {
		return null;
	}
	$topic_id = bbp_get_reply_topic_id( $reply->ID );

	// Bail if no topic or user cannot reply
	if ( empty( $topic_id ) || bbp_is_single_reply() || ! bbp_current_user_can_access_create_reply_form() ) {
		return null;
	}

	// Build the URI and return value
	$uri = bbp_get_reply_url( $reply->ID );
	$uri = strtok( $uri, '#' );
	$uri = add_query_arg( array( 'bbp_reply_to' => $reply->ID ), $uri );
	$uri = wp_nonce_url( $uri, 'respond_id_' . $reply->ID );
	$uri = $uri . '#new-post';

	// Only add onclick if replies are threaded
	if ( bbp_thread_replies() ) {

		// Array of classes to pass to moveForm
		$move_form = array(
			$add_below . '-' . $reply->ID,
			$reply->ID,
			$respond_id,
			$reply->post_parent
		);

		// Build the onclick
		$onclick = ' onclick="return addReply.moveForm(\'' . implode( "','", $move_form ) . '\');"';

	// No onclick if replies are not threaded
	} else {
		$onclick = '';
	}

	// Add $uri to the array, to be passed through the filter
	$r['uri'] = $uri;
	$retval   = $link_before . '<a role="button" href="' . esc_url( $r['uri'] ) . '" class="bbp-reply-to-link"' . $onclick . '>' . $reply_html . '</a>' . $link_after;

	/**
	 * Filters the link for a reply to a reply.
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param string|null $retval Link for a reply to a reply, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments.
	 */
	return apply_filters( 'bbp_get_reply_to_link', $retval, $r, $args );
}

/**
 * Output the reply to a reply cancellation link.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param string $text Cancellation link text.
 * @return void
 */
function bbp_cancel_reply_to_link( $text = '' ) {
	echo bbp_get_cancel_reply_to_link( $text );
}

/**
 * Return the cancellation link for a reply to a reply.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param string $text The cancel text.
 * @return string|null The cancellation link, or null when unavailable.
 */
function bbp_get_cancel_reply_to_link( $text = '' ) {

	// Bail if not hierarchical or editing a reply
	if ( ! bbp_thread_replies() || bbp_is_reply_edit() ) {
		return null;
	}

	// Set default text
	if ( empty( $text ) ) {
		$text = esc_html__( 'Cancel', 'bbpress' );
	}

	// Replying to...
	$reply_to = isset( $_GET['bbp_reply_to'] )
		? bbp_parse_int( $_GET['bbp_reply_to'] )
		: 0;

	// Set visibility
	$style  = ! empty( $reply_to ) ? '' : ' style="display:none;"';
	$link   = remove_query_arg( array( 'bbp_reply_to', '_wpnonce' ) ) . "#post-{$reply_to}";
	$retval = sprintf( '<a href="%1$s" id="bbp-cancel-reply-to-link"%2$s>%3$s</a>', esc_url( $link ), $style, esc_html( $text ) );

	/**
	 * Filters the cancellation link for a reply to a reply.
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param string|null $retval The cancellation link, or null when unavailable.
	 * @param string $link Link markup.
	 * @param string $text The cancel text.
	 */
	return apply_filters( 'bbp_get_cancel_reply_to_link', $retval, $link, $text );
}

/**
 * Output the numeric position of a reply within a topic.
 *
 * @since 2.0.0 bbPress (r2984)
 *
 * @param int $reply_id Optional. Reply id.
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_reply_position( $reply_id = 0, $topic_id = 0 ) {
	echo bbp_get_reply_position( $reply_id, $topic_id );
}

/**
 * Return the numeric position of a reply within a topic.
 *
 * @since 2.0.0 bbPress (r2984)
 * @since 2.7.0 bbPress (r8609) Added the view-all position option.
 *
 * @param int $reply_id Optional. Reply id.
 * @param int $topic_id Optional. Topic id.
 * @param bool $view_all Whether to rank among moderator-visible replies.
 * @return int Reply position.
 */
function bbp_get_reply_position( $reply_id = 0, $topic_id = 0, $view_all = false ) {

	// Get required data
	$reply_id       = bbp_get_reply_id( $reply_id );
	$reply_position = $view_all ? 0 : get_post_field( 'menu_order', $reply_id );

	// Reply doesn't have a position so get the raw value
	if ( empty( $reply_position ) ) {

		// Get topic ID
		$topic_id = ! empty( $topic_id )
			? bbp_get_topic_id( $topic_id )
			: bbp_get_reply_topic_id( $reply_id );

		// Post is not the topic
		if ( $reply_id !== $topic_id ) {
			$is_public      = bbp_is_reply_public( $reply_id );
			$reply_position = bbp_get_reply_position_raw( $reply_id, $topic_id, $view_all || ! $is_public );

			// Update the reply position in the posts table so we'll never have
			// to hit the DB again.
			if ( ! empty( $reply_position ) && ! $view_all && $is_public ) {
				bbp_update_reply_position( $reply_id, $reply_position );
			}

			// Topic's position is always 0
			} else {
				$reply_position = 0;
			}
		}

	// Bump the position by one if the topic is included in the reply loop
	if ( ! bbp_show_lead_topic() ) {
		++$reply_position;
	}

	/**
	 * Filters the numeric position of a reply within a topic.
	 *
	 * @since 2.0.0 bbPress (r2984)
	 *
	 * @param int $reply_position Reply position.
	 * @param int $reply_id Reply id.
	 * @param int $topic_id Topic id.
	 */
	return (int) apply_filters( 'bbp_get_reply_position', $reply_position, $reply_id, $topic_id );
}

/** Reply Admin Links *********************************************************/

/**
 * Get the reply named by parsed link arguments.
 *
 * @since 2.7.0 bbPress (r8634)
 * @access private
 *
 * @param array<string, mixed> $args Parsed link arguments.
 * @return WP_Post|null Reply, or null for an invalid ID.
 */
function bbp_get_reply_for_link( $args ) {
	$reply = isset( $args['id'] ) ? $args['id'] : 0;
	if ( empty( $reply ) ) {
		return bbp_get_reply( 0 );
	}
	if ( $reply instanceof WP_Post ) {
		return bbp_get_reply( $reply );
	}
	return is_numeric( $reply ) ? bbp_get_reply( (int) $reply ) : null;
}

/**
 * Output admin links for reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param array<string, mixed> $args See {@link bbp_get_reply_admin_links()}.
 * @return void
 */
function bbp_reply_admin_links( $args = array() ) {
	echo bbp_get_reply_admin_links( $args );
}

/**
 * Return admin links for reply.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Reply id
 *  - before: HTML before the links. Defaults to
 *             '<span class="bbp-admin-links">'
 *  - after: HTML after the links. Defaults to '</span>'
 *  - sep: Separator. Defaults to ' | '
 *  - links: Array of the links to display. By default, edit, trash,
 *            spam, reply move, and topic split links are displayed.
 * @return string|null Reply admin links, or null when unavailable.
 */
function bbp_get_reply_admin_links( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'     => 0,
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array()
		),
		'get_reply_admin_links'
	);

	// Normalize filtered link arguments before building links
	$before = bbp_get_string_arg( $r, 'before' );
	$after  = bbp_get_string_arg( $r, 'after' );
	$sep    = bbp_get_string_arg( $r, 'sep' );

	$reply_arg = $r['id'];
	if ( ! is_int( $reply_arg ) && ! is_float( $reply_arg ) && ! is_string( $reply_arg ) ) {
		$reply_arg = false;
	}
	$r['id'] = bbp_get_reply_id( $reply_arg );

	// If post is a topic, return the topic admin links instead
	if ( bbp_is_topic( $r['id'] ) ) {
		return bbp_get_topic_admin_links( $args );
	}

	// If post is not a reply, return
	if ( ! bbp_is_reply( $r['id'] ) ) {
		return null;
	}

	// If topic is trashed, do not show admin links
	if ( bbp_is_topic_trash( bbp_get_reply_topic_id( $r['id'] ) ) ) {
		return null;
	}

	// If no links were passed, default to the standard
	if ( empty( $r['links'] ) ) {

		/**
		 * Filters the available reply admin links.
		 *
		 * @since 2.0.0 bbPress (r2667)
		 *
		 * @param array $value The available reply admin links.
		 * @param int $id Id.
		 */
		$r['links'] = apply_filters(
			'bbp_reply_admin_links',
			array(
				'edit'    => bbp_get_reply_edit_link   ( $r ),
				'move'    => bbp_get_reply_move_link   ( $r ),
				'split'   => bbp_get_topic_split_link  ( $r ),
				'trash'   => bbp_get_reply_trash_link  ( $r ),
				'spam'    => bbp_get_reply_spam_link   ( $r ),
				'approve' => bbp_get_reply_approve_link( $r ),
				'reply'   => bbp_get_reply_to_link     ( $r )
			),
			$r['id']
		);
	}

	// See if links need to be unset
	$links = bbp_get_admin_link_array( $r['links'] );
	$reply_status = bbp_get_reply_status( $r['id'] );
	if ( bbp_get_spam_status_id() === $reply_status ) {

		// Trash link should not be visible on spam replies
		unset( $links['trash'] );
	}

	// Process the admin links
	$r['links'] = $links;
	$retval = $before . implode( $sep, array_filter( $links ) ) . $after;

	/**
	 * Filters the admin links for reply.
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param string|null $retval Reply admin links, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_reply_admin_links', $retval, $r, $args );
}

/**
 * Output the edit link of the reply.
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array<string, mixed> $args See {@link bbp_get_reply_edit_link()}.
 * @return void
 */
function bbp_reply_edit_link( $args = array() ) {
	echo bbp_get_reply_edit_link( $args );
}

/**
 * Return the edit link of the reply.
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Reply id
 *  - link_before: HTML before the link
 *  - link_after: HTML after the link
 *  - edit_text: Edit text. Defaults to 'Edit'.
 * @return string|null Reply edit link, or null when unavailable.
 */
function bbp_get_reply_edit_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'edit_text'    => esc_html__( 'Edit', 'bbpress' )
		),
		'get_reply_edit_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$edit_html   = bbp_get_string_arg( $r, 'edit_text' );

	// Get reply
	$reply = bbp_get_reply_for_link( $r );
	if ( empty( $reply ) ) {
		return null;
	}

	// Bypass check if user has caps
	if ( ! current_user_can( 'edit_others_replies' ) && ! current_user_can( 'moderate', $reply->ID ) ) {

		// User cannot edit or it is past the lock time
		if ( ! current_user_can( 'edit_reply', $reply->ID ) || bbp_past_edit_lock( $reply->post_date_gmt ) ) {
			return null;
		}
	}

	// Get uri
	$uri = bbp_get_reply_edit_url( $r['id'] );

	// Bail if no uri
	if ( empty( $uri ) ) {
		return null;
	}

	$retval = $link_before . '<a href="' . esc_url( $uri ) . '" class="bbp-reply-edit-link">' . $edit_html . '</a>' . $link_after;

	/**
	 * Filters the edit link of the reply.
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param string|null $retval Reply edit link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_reply_edit_link', $retval, $r, $args );
}

/**
 * Output URL to the reply edit page.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $reply_id Optional. Reply id.
 * @return void
 */
function bbp_reply_edit_url( $reply_id = 0 ) {
	$url = bbp_get_reply_edit_url( $reply_id ) ?? '';
	echo esc_url( $url );
}

/**
 * Return URL to the reply edit page.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $reply_id Optional. Reply id.
 * @return string|null Reply edit URL, or null when unavailable.
 */
function bbp_get_reply_edit_url( $reply_id = 0 ) {

	// Bail if no reply
	$reply = bbp_get_reply( $reply_id );
	if ( empty( $reply ) ) {
		return null;
	}

	$reply_link = bbp_remove_view_all( bbp_get_reply_permalink( $reply_id ) );

	// Pretty permalinks, previously used `bbp_use_pretty_urls()`
	// https://bbpress.trac.wordpress.org/ticket/3054
	if ( false === strpos( $reply_link, '?' ) ) {
		$url = trailingslashit( $reply_link ) . bbp_get_edit_slug();
		$url = user_trailingslashit( $url );

	// Unpretty permalinks
	} else {
		$url = add_query_arg(
			array(
				bbp_get_reply_post_type() => $reply->post_name,
				bbp_get_edit_rewrite_id() => '1'
			),
			$reply_link
		);
	}

	// Maybe add view all
	$url = bbp_add_view_all( $url );

	/**
	 * Filters the URL to edit a reply.
	 *
	 * @since 2.0.0 bbPress (r2753)
	 *
	 * @param string|null $url Reply edit URL, or null when unavailable.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_reply_edit_url', $url, $reply_id );
}

/**
 * Output the trash link of the reply.
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array<string, mixed> $args See {@link bbp_get_reply_trash_link()}.
 * @return void
 */
function bbp_reply_trash_link( $args = array() ) {
	echo bbp_get_reply_trash_link( $args );
}

/**
 * Return the trash link of the reply.
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Reply id
 *  - link_before: HTML before the link
 *  - link_after: HTML after the link
 *  - sep: Separator
 *  - trash_text: Trash text
 *  - restore_text: Restore text
 *  - delete_text: Delete text.
 * @return string|null Reply trash link, or null when unavailable.
 */
function bbp_get_reply_trash_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'trash_text'   => esc_html__( 'Trash',   'bbpress' ),
			'restore_text' => esc_html__( 'Restore', 'bbpress' ),
			'delete_text'  => esc_html__( 'Delete',  'bbpress' )
		),
		'get_reply_trash_link'
	);

	// Normalize filtered link arguments before building links
	$link_before  = bbp_get_string_arg( $r, 'link_before' );
	$link_after   = bbp_get_string_arg( $r, 'link_after' );
	$sep          = bbp_get_string_arg( $r, 'sep' );
	$trash_html   = bbp_get_string_arg( $r, 'trash_text' );
	$restore_html = bbp_get_string_arg( $r, 'restore_text' );
	$delete_html  = bbp_get_string_arg( $r, 'delete_text' );

	// Get reply
	$reply = bbp_get_reply_for_link( $r );

	// Bail if no reply or current user cannot delete
	if ( empty( $reply ) || ! current_user_can( 'delete_reply', $reply->ID ) ) {
		return null;
	}

	$actions    = array();
	$trash_days = bbp_get_trash_days( bbp_get_reply_post_type() );

	// Trashed
	if ( bbp_is_reply_trash( $reply->ID ) ) {
		$query = array(
			'action'     => 'bbp_toggle_reply_trash',
			'sub_action' => 'untrash',
			'reply_id'   => $reply->ID
		);
		$actions['untrash'] = '<a title="' . esc_attr__( 'Restore this item from the Trash', 'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( $query ), 'untrash-' . $reply->post_type . '_' . $reply->ID ) ) . '" class="bbp-reply-restore-link">' . $restore_html . '</a>';

	// Trash
	} elseif ( ! empty( $trash_days ) ) {
		$query = array(
			'action'     => 'bbp_toggle_reply_trash',
			'sub_action' => 'trash',
			'reply_id'   => $reply->ID
		);
		$actions['trash']   = '<a title="' . esc_attr__( 'Move this item to the Trash',      'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( $query ), 'trash-'   . $reply->post_type . '_' . $reply->ID ) ) . '" class="bbp-reply-trash-link">'   . $trash_html   . '</a>';
	}

	// No trash
	if ( bbp_is_reply_trash( $reply->ID ) || empty( $trash_days ) ) {
		$query = array(
			'action'     => 'bbp_toggle_reply_trash',
			'sub_action' => 'delete',
			'reply_id'   => $reply->ID
		);
		$actions['delete']  = '<a title="' . esc_attr__( 'Delete this item permanently',     'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( $query ), 'delete-'  . $reply->post_type . '_' . $reply->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete that permanently?', 'bbpress' ) ) . '\' );" class="bbp-reply-delete-link">' . $delete_html . '</a>';
	}

	// Process the admin links
	$retval = $link_before . implode( $sep, $actions ) . $link_after;

	/**
	 * Filters the trash link of the reply.
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param string|null $retval Reply trash link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_reply_trash_link', $retval, $r, $args );
}

/**
 * Output the spam link of the reply.
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array<string, mixed> $args See {@link bbp_get_reply_spam_link()}.
 * @return void
 */
function bbp_reply_spam_link( $args = array() ) {
	echo bbp_get_reply_spam_link( $args );
}

/**
 * Return the spam link of the reply.
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Reply id
 *  - link_before: HTML before the link
 *  - link_after: HTML after the link
 *  - spam_text: Spam text
 *  - unspam_text: Unspam text.
 * @return string|null Reply spam link, or null when unavailable.
 */
function bbp_get_reply_spam_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'spam_text'    => esc_html__( 'Spam',   'bbpress' ),
			'unspam_text'  => esc_html__( 'Unspam', 'bbpress' )
		),
		'get_reply_spam_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$spam_html   = bbp_get_string_arg( $r, 'spam_text' );
	$unspam_html = bbp_get_string_arg( $r, 'unspam_text' );

	// Get reply
	$reply = bbp_get_reply_for_link( $r );

	// Bail if no reply or current user cannot moderate
	if ( empty( $reply ) || ! current_user_can( 'moderate', $reply->ID ) ) {
		return null;
	}

	$display = bbp_is_reply_spam( $reply->ID )
		? $unspam_html
		: $spam_html;

	$query = array(
		'action'   => 'bbp_toggle_reply_spam',
		'reply_id' => $reply->ID
	);

	$uri    = add_query_arg( $query );
	$uri    = wp_nonce_url( $uri, 'spam-reply_' . $reply->ID );
	$retval = $link_before . '<a href="' . esc_url( $uri ) . '" class="bbp-reply-spam-link">' . $display . '</a>' . $link_after;

	/**
	 * Filters the spam link of the reply.
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param string|null $retval Reply spam link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_reply_spam_link', $retval, $r, $args );
}

/**
 * Move reply link.
 *
 * Output the move link of the reply.
 *
 * @since 2.3.0 bbPress (r4522)
 *
 * @param array<string, mixed> $args See {@link bbp_get_reply_move_link()}.
 * @return void
 */
function bbp_reply_move_link( $args = array() ) {
	echo bbp_get_reply_move_link( $args );
}

/**
 * Get move reply link.
 *
 * Return the move link of the reply.
 *
 * @since 2.3.0 bbPress (r4522)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Reply id
 *  - link_before: HTML before the link
 *  - link_after: HTML after the link
 *  - move_text: Move text
 *  - move_title: Move title attribute.
 * @return string|null Reply move link, or null when unavailable.
 */
function bbp_get_reply_move_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'split_text'  => esc_html__( 'Move',            'bbpress' ),
			'split_title' => esc_attr__( 'Move this reply', 'bbpress' )
		),
		'get_reply_move_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$split_html  = bbp_get_string_arg( $r, 'split_text' );
	$split_title = bbp_get_string_arg( $r, 'split_title' );

	// Get IDs
	$reply    = bbp_get_reply_for_link( $r );
	$reply_id = $reply ? $reply->ID : 0;
	$topic_id = bbp_get_reply_topic_id( $reply_id );

	// Bail if no reply ID or user cannot moderate and edit its topic
	if ( empty( $reply_id ) || ! bbp_current_user_can_access_topic_moderation( $topic_id ) ) {
		return null;
	}

	$uri = add_query_arg(
		array(
			'action'   => 'bbp-move-reply',
			'reply_id' => $reply_id
		),
		bbp_get_reply_edit_url( $reply_id )
	);

	$retval = $link_before . '<a href="' . esc_url( $uri ) . '" title="' . $split_title . '" class="bbp-reply-move-link">' . $split_html . '</a>' . $link_after;

	/**
	 * Filters the move reply link.
	 *
	 * @since 2.3.0 bbPress (r4522)
	 *
	 * @param string|null $retval Reply move link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_reply_move_link', $retval, $r, $args );
}

/**
 * Split topic link.
 *
 * Output the split link of the topic (but is bundled with each reply).
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_split_link()}.
 * @return void
 */
function bbp_topic_split_link( $args = array() ) {
	echo bbp_get_topic_split_link( $args );
}

/**
 * Get split topic link.
 *
 * Return the split link of the topic (but is bundled with each reply).
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Reply id
 *  - link_before: HTML before the link
 *  - link_after: HTML after the link
 *  - split_text: Split text
 *  - split_title: Split title attribute.
 * @return string|null Topic split link, or null when unavailable.
 */
function bbp_get_topic_split_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'split_text'  => esc_html__( 'Split',                           'bbpress' ),
			'split_title' => esc_attr__( 'Split the topic from this reply', 'bbpress' )
		),
		'get_topic_split_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$split_html  = bbp_get_string_arg( $r, 'split_text' );
	$split_title = bbp_get_string_arg( $r, 'split_title' );

	// Get IDs
	$reply    = bbp_get_reply_for_link( $r );
	$reply_id = $reply ? $reply->ID : 0;
	$topic_id = bbp_get_reply_topic_id( $reply_id );

	// Bail if no reply/topic ID, or user cannot moderate and edit the topic
	if ( empty( $reply_id ) || empty( $topic_id ) || ! bbp_current_user_can_access_topic_moderation( $topic_id ) ) {
		return null;
	}

	$uri = add_query_arg(
		array(
			'action'   => 'bbp-split-topic',
			'reply_id' => $reply_id
		),
		bbp_get_topic_edit_url( $topic_id )
	);

	$retval = $link_before . '<a href="' . esc_url( $uri ) . '" title="' . $split_title . '" class="bbp-topic-split-link">' . $split_html . '</a>' . $link_after;

	/**
	 * Filters the split topic link.
	 *
	 * @since 2.0.0 bbPress (r2756)
	 *
	 * @param string|null $retval Topic split link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_split_link', $retval, $r, $args );
}

/**
 * Output the approve link of the reply.
 *
 * @since 2.6.0 bbPress (r5507)
 *
 * @param array<string, mixed> $args See {@link bbp_get_reply_approve_link()}.
 * @return void
 */
function bbp_reply_approve_link( $args = array() ) {
	echo bbp_get_reply_approve_link( $args );
}

/**
 * Return the approve link of the reply.
 *
 * @since 2.6.0 bbPress (r5507)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Reply id
 *  - link_before: Before the link
 *  - link_after: After the link
 *  - sep: Separator between links
 *  - approve_text: Approve text
 *  - unapprove_text: Unapprove text.
 * @return string|null Reply approve link, or null when unavailable.
 */
function bbp_get_reply_approve_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'             => 0,
			'link_before'    => '',
			'link_after'     => '',
			'sep'            => ' | ',
			'approve_text'   => _x( 'Approve',   'Pending Status', 'bbpress' ),
			'unapprove_text' => _x( 'Unapprove', 'Pending Status', 'bbpress' )
		),
		'get_reply_approve_link'
	);

	// Normalize filtered link arguments before building links
	$link_before    = bbp_get_string_arg( $r, 'link_before' );
	$link_after     = bbp_get_string_arg( $r, 'link_after' );
	$approve_html   = bbp_get_string_arg( $r, 'approve_text' );
	$unapprove_html = bbp_get_string_arg( $r, 'unapprove_text' );

	// Get reply
	$reply = bbp_get_reply_for_link( $r );

	// Bail if no reply or current user cannot moderate
	if ( empty( $reply ) || ! current_user_can( 'moderate', $reply->ID ) ) {
		return null;
	}

	$display = bbp_is_reply_pending( $reply->ID )
		? $approve_html
		: $unapprove_html;

	$query = array(
		'action'   => 'bbp_toggle_reply_approve',
		'reply_id' => $reply->ID
	);

	$uri    = add_query_arg( $query );
	$uri    = wp_nonce_url( $uri, 'approve-reply_' . $reply->ID );
	$retval = $link_before . '<a href="' . esc_url( $uri ) . '" class="bbp-reply-approve-link">' . $display . '</a>' . $link_after;

	/**
	 * Filters the approve link of the reply.
	 *
	 * @since 2.6.0 bbPress (r5507)
	 *
	 * @param string|null $retval Reply approve link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_reply_approve_link', $retval, $r, $args );
}

/**
 * Output the row class of a reply.
 *
 * @since 2.0.0 bbPress (r2678)
 *
 * @param int   $reply_id Optional. Reply ID.
 * @param array<array-key, string> $classes  Extra classes for the reply.
 * @return void
 */
function bbp_reply_class( $reply_id = 0, $classes = array() ) {
	echo bbp_get_reply_class( $reply_id, $classes );
}

/**
 * Return the row class of a reply.
 *
 * @since 2.0.0 bbPress (r2678)
 *
 * @param int   $reply_id Optional. Reply ID.
 * @param array<array-key, string> $classes  Extra classes for the reply.
 * @return string Row class of the reply.
 */
function bbp_get_reply_class( $reply_id = 0, $classes = array() ) {
	$bbp       = bbpress();
	$reply_id  = bbp_get_reply_id( $reply_id );
	$topic_id  = bbp_get_reply_topic_id( $reply_id );
	$forum_id  = bbp_get_reply_forum_id( $reply_id );
	$author_id = bbp_get_reply_author_id( $reply_id );
	$reply_pos = bbp_get_reply_position( $reply_id, $topic_id );
	$classes   = array_filter( (array) $classes );
	$count     = isset( $bbp->reply_query->current_post )
		? (int) $bbp->reply_query->current_post
		: 1;

	//  Stripes
	$even_odd = ( $count % 2 )
		? 'even'
		: 'odd';

	// Forum moderator replied to topic
	$forum_moderator = ( ! empty( $author_id ) && ! empty( $forum_id ) && bbp_is_user_forum_moderator( $author_id, $forum_id ) )
		? 'forum-mod'
		: '';

	// Topic author replied to others
	$topic_author = ( bbp_get_topic_author_id( $topic_id ) === $author_id )
		? 'topic-author'
		: '';

	// Get reply classes
	$reply_classes = array(
		'loop-item-'          . $count,
		'user-id-'            . $author_id,
		'bbp-parent-forum-'   . $forum_id,
		'bbp-parent-topic-'   . $topic_id,
		'bbp-reply-position-' . $reply_pos,
		$even_odd,
		$topic_author,
		$forum_moderator
	);

	// Run the topic classes through the post-class filters, which also
	// handles the escaping of each individual class.
	$post_classes = get_post_class( array_merge( $classes, $reply_classes ), $reply_id );

	// Filter

	/**
	 * Filters the CSS classes on a reply row.
	 *
	 * @since 2.0.0 bbPress (r2678)
	 *
	 * @param array<array-key, string> $post_classes The CSS classes on a reply row.
	 * @param int $reply_id Reply id.
	 * @param array<array-key, string> $classes Classes.
	 */
	$new_classes  = apply_filters( 'bbp_get_reply_class', $post_classes, $reply_id, $classes );

	// Return
	return 'class="' . implode( ' ', $new_classes ) . '"';
}

/** Pagination ****************************************************************/

/**
 * Return the base URL used inside of pagination links.
 *
 * @since 2.6.0 bbPress (r6680)
 *
 * @param int $topic_id Topic id.
 * @return string|false Pagination base, or false when its URL is invalid.
 */
function bbp_get_replies_pagination_base( $topic_id = 0 ) {

	// If pretty permalinks are enabled, make our pagination pretty
	if ( bbp_use_pretty_urls() && ! bbp_is_topic_pending( $topic_id ) ) {

		// User's replies
		if ( bbp_is_single_user_replies() ) {
			$base = bbp_get_user_replies_created_url( bbp_get_displayed_user_id() );

		// Root profile page
		} elseif ( bbp_is_single_user() ) {
			$base = bbp_get_user_profile_url( bbp_get_displayed_user_id() );

		// Any single post (for shortcodes)
		} elseif ( is_singular() ) {
			$base = get_permalink();

		// Single topic
		} else {
			$base = get_permalink( $topic_id );
		}

		$base = bbp_validate_url( $base );
		if ( false !== $base ) {
			$base = trailingslashit( $base ) . user_trailingslashit( bbp_get_paged_slug() . '/%#%/' );
		}

	// Unpretty permalinks
	} else {
		$base = add_query_arg( 'paged', '%#%' );
	}

	/**
	 * Filters the base URL used inside of pagination links.
	 *
	 * @since 2.6.0 bbPress (r6680)
	 *
	 * @param string|false $base Pagination base, or false when its URL is invalid.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_replies_pagination_base', $base, $topic_id );
}

/**
 * Output the topic pagination count.
 *
 * The results are unescaped by design, to allow them to be filtered freely via
 * the 'bbp_get_topic_pagination_count' filter.
 *
 * @since 2.0.0 bbPress (r2519)
 *
 * @return void
 */
function bbp_topic_pagination_count() {
	echo bbp_get_topic_pagination_count();
}

/**
 * Return the topic pagination count.
 *
 * @since 2.0.0 bbPress (r2519)
 *
 * @return string Topic pagination count.
 */
function bbp_get_topic_pagination_count() {
	$bbp = bbpress();

	// Define local variable(s)
	$retstr = '';

	// Set pagination values
	$count_int = intval( $bbp->reply_query->post_count     );
	$total_int = intval( $bbp->reply_query->found_posts    );
	$ppp_int   = intval( $bbp->reply_query->posts_per_page );
	$start_int = intval( ( $bbp->reply_query->paged - 1 ) * $ppp_int ) + 1;
	$to_int    = intval( ( $start_int + ( $ppp_int - 1 ) > $total_int )
		? $total_int
		: $start_int + ( $ppp_int - 1 )
	);

	// Format numbers for display
	$count_num = bbp_number_format( $count_int );
	$total_num = bbp_number_format( $total_int );
	$from_num  = bbp_number_format( $start_int );
	$to_num    = bbp_number_format( $to_int    );

	// We are threading replies
	if ( bbp_thread_replies() ) {
		$posts   = $bbp->reply_query->posts;
		$walker  = new BBP_Walker_Reply();
		$threads = is_array( $posts )
			? max( 0, $walker->get_number_of_root_elements( $posts ) - 1 )
			: 0;
		/* translators: %s: Number of reply threads */
		$retstr  = sprintf( _n( 'Viewing %1$s reply thread', 'Viewing %1$s reply threads', $threads, 'bbpress' ), bbp_number_format( $threads ) );

	// We are not including the lead topic
	} elseif ( bbp_show_lead_topic() ) {

		// Several replies in a topic with a single page
		if ( empty( $to_num ) ) {
			/* translators: %1$s: Number of replies */
			$retstr = sprintf( _n( 'Viewing %1$s reply', 'Viewing %1$s replies', $total_int, 'bbpress' ), $total_num );

		// Several replies in a topic with several pages
		} else {
			/* translators: 1: Number of replies being viewed, 2: Starting reply number, 3: Ending reply number, 4: Total replies */
			$retstr = sprintf( _n( 'Viewing %2$s replies (of %4$s total)', 'Viewing %1$s replies - %2$s through %3$s (of %4$s total)', $count_int, 'bbpress' ), $count_num, $from_num, $to_num, $total_num );  //phpcs:ignore
		}

	// We are including the lead topic
	// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found
	} else {

		// Several posts in a topic with a single page
		if ( empty( $to_num ) ) {
			/* translators: %1$s: Number of posts */
			$retstr = sprintf( _n( 'Viewing %1$s post', 'Viewing %1$s posts', $total_int, 'bbpress' ), $total_num );

		// Several posts in a topic with several pages
		} else {
			/* translators: 1: Number of posts being viewed, 2: Starting post number, 3: Ending post number, 4: Total posts */
			$retstr = sprintf( _n( 'Viewing %2$s post (of %4$s total)', 'Viewing %1$s posts - %2$s through %3$s (of %4$s total)', $count_int, 'bbpress' ), $count_num, $from_num, $to_num, $total_num );  //phpcs:ignore
		}
	}

	// Escape results of _n()
	$retstr = esc_html( $retstr );

	/**
	 * Filters the topic pagination count.
	 *
	 * @since 2.0.0 bbPress (r2519)
	 *
	 * @param string $retstr Topic pagination count.
	 */
	return apply_filters( 'bbp_get_topic_pagination_count', $retstr );
}

/**
 * Output topic pagination links.
 *
 * @since 2.0.0 bbPress (r2519)
 *
 * @return void
 */
function bbp_topic_pagination_links() {
	echo bbp_get_topic_pagination_links();
}

/**
 * Return topic pagination links.
 *
 * @since 2.0.0 bbPress (r2519)
 *
 * @return string|false Topic pagination links, or false when unavailable.
 */
function bbp_get_topic_pagination_links() {
	$bbp = bbpress();

	if ( ! isset( $bbp->reply_query->pagination_links ) || empty( $bbp->reply_query->pagination_links ) ) {
		return false;
	}

	/**
	 * Filters the topic pagination links.
	 *
	 * @since 2.0.0 bbPress (r2519)
	 *
	 * @param mixed $pagination_links Topic pagination HTML or a list of links.
	 */
	return apply_filters( 'bbp_get_topic_pagination_links', $bbp->reply_query->pagination_links );
}

/** Forms *********************************************************************/

/**
 * Output the value of reply content field.
 *
 * @since 2.0.0 bbPress (r3101)
 *
 * @return void
 */
function bbp_form_reply_content() {
	echo bbp_get_form_reply_content();
}

/**
 * Return the value of reply content field.
 *
 * @since 2.0.0 bbPress (r3101)
 *
 * @return string Value of reply content field.
 */
function bbp_get_form_reply_content() {

	// Get _POST data
	if ( bbp_is_reply_form_post_request() && isset( $_POST['bbp_reply_content'] ) ) {
		$reply_content = wp_unslash( $_POST['bbp_reply_content'] );

	// Get edit data
	} elseif ( bbp_is_reply_edit() ) {
		$reply_id      = bbp_get_reply_id();
		$reply_content = ! current_user_can( 'moderate', $reply_id ) && bbp_get_password_required_id( $reply_id )
			? ''
			: bbp_get_global_post_field( 'post_content', 'raw' );

	// No data
	} else {
		$reply_content = '';
	}

	/**
	 * Filters the value of reply content field.
	 *
	 * @since 2.0.0 bbPress (r3101)
	 *
	 * @param string $reply_content Value of reply content field.
	 */
	return apply_filters( 'bbp_get_form_reply_content', $reply_content );
}

/**
 * Output the value of the reply to field.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @return void
 */
function bbp_form_reply_to() {
	echo bbp_get_form_reply_to();
}

/**
 * Return the value of reply to field.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @return int Parent reply ID, or 0 when none is selected.
 */
function bbp_get_form_reply_to() {

	// Set initial value
	$reply_to = 0;

	// Get $_REQUEST data
	if ( isset( $_REQUEST['bbp_reply_to'] ) ) {
		$reply_to = bbp_validate_reply_to( $_REQUEST['bbp_reply_to'] );
	}

	// If empty, get from meta
	if ( empty( $reply_to ) ) {
		$reply_to = bbp_get_reply_to();
	}

	/**
	 * Filters the value of reply to field.
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param int $reply_to Parent reply ID, or 0 when none is selected.
	 */
	return apply_filters( 'bbp_get_form_reply_to', $reply_to );
}

/**
 * Output a select box allowing to pick which reply an existing hierarchical
 * reply belongs to.
 *
 * @since 2.6.0 bbPress (r5387)
 *
 * @param int $reply_id Reply id.
 * @return void
 */
function bbp_reply_to_dropdown( $reply_id = 0 ) {
	echo bbp_get_reply_to_dropdown( $reply_id );
}

/**
 * Return a select box allowing to pick which topic/reply a reply belongs.
 *
 * @since 2.6.0 bbPress (r5387)
 * @since 2.6.21 bbPress (r8137) Added the $topic_id parameter.
 *
 * @param int $reply_id Reply id.
 * @param int $topic_id Optional topic id when previewing a reply move.
 *
 * @return string The dropdown.
 */
function bbp_get_reply_to_dropdown( $reply_id = 0, $topic_id = 0 ) {

	// Validate the reply data
	$reply_id = bbp_get_reply_id( $reply_id );
	$reply_to = bbp_get_reply_to( $reply_id );
	$saved_topic_id = bbp_get_reply_topic_id( $reply_id );
	$topic_id       = ! empty( $topic_id ) ? (int) $topic_id : $saved_topic_id;

	// A reply in the old topic cannot be the parent in the new topic.
	if ( $topic_id !== $saved_topic_id ) {
		$reply_to = 0;
	}

	// Get the replies
	$posts = get_posts(
		array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_status' => bbp_get_public_status_id(),
			'post_parent' => $topic_id,
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		)
	);

	// Append `reply_to` for each reply so it can be walked
	foreach ( $posts as &$post ) {

		// Check for reply post type
		$_reply_to = bbp_get_reply_to( $post->ID );

		// Make sure it's a reply to a reply
		if ( empty( $_reply_to ) || ( $topic_id === $_reply_to ) ) {
			$_reply_to = 0;
		}

		// Add reply_to to the post object so we can walk it later
		$post->reply_to = $_reply_to;
	}

	// Default "None" text
	$show_none = ( 0 === $reply_id )
		? esc_attr_x( 'None', 'Default reply to dropdown text', 'bbpress' )
		/* translators: 1: Topic ID, 2: Topic title */
		: sprintf( esc_attr__( '%1$s - %2$s', 'bbpress' ), $topic_id, bbp_get_topic_title( $topic_id ) );

	// Get the dropdown and return it
	$retval = bbp_get_dropdown(
		array(
			'show_none'    => $show_none,
			'select_id'    => 'bbp_reply_to',
			'select_class' => 'bbp_dropdown',
			'exclude'      => $reply_id,
			'selected'     => $reply_to,
			'post_parent'  => $topic_id,
			'post_type'    => bbp_get_reply_post_type(),
			'max_depth'    => bbp_thread_replies_depth(),
			'page'         => 1,
			'per_page'     => -1,
			'walker'       => new BBP_Walker_Reply_Dropdown(),
			'posts'        => $posts
		)
	);

	/**
	 * Filters the reply parent selection dropdown.
	 *
	 * @since 2.6.0 bbPress (r5387)
	 *
	 * @param string $retval The dropdown.
	 * @param int $reply_id Reply id.
	 * @param int $reply_to Parent reply ID.
	 * @param int $topic_id Optional topic id when previewing a reply move.
	 */
	return apply_filters( 'bbp_get_reply_to_dropdown', $retval, $reply_id, $reply_to, $topic_id );
}

/**
 * Output checked value of reply log edit field.
 *
 * @since 2.0.0 bbPress (r3101)
 *
 * @return void
 */
function bbp_form_reply_log_edit() {
	echo bbp_get_form_reply_log_edit();
}

/**
 * Return checked value of reply log edit field.
 *
 * @since 2.0.0 bbPress (r3101)
 *
 * @return string Reply log edit checked value.
 */
function bbp_get_form_reply_log_edit() {

	// Get _POST data
	if ( bbp_is_reply_form_post_request() && isset( $_POST['bbp_log_reply_edit'] ) ) {
		$reply_revision = (bool) $_POST['bbp_log_reply_edit'];

	// No data
	} else {
		$reply_revision = true;
	}

	// Get checked output
	$checked = checked( $reply_revision, true, false );

	/**
	 * Filters the checked value of reply log edit field.
	 *
	 * @since 2.0.0 bbPress (r3101)
	 *
	 * @param string $checked Reply log edit checked value.
	 * @param bool $reply_revision Whether to log the reply edit.
	 */
	return apply_filters( 'bbp_get_form_reply_log_edit', $checked, $reply_revision );
}

/**
 * Output the value of the reply edit reason.
 *
 * @since 2.0.0 bbPress (r3101)
 *
 * @return void
 */
function bbp_form_reply_edit_reason() {
	echo bbp_get_form_reply_edit_reason();
}

/**
 * Return the value of the reply edit reason.
 *
 * @since 2.0.0 bbPress (r3101)
 *
 * @return string Reply edit reason value.
 */
function bbp_get_form_reply_edit_reason() {

	// Get _POST data
	if ( bbp_is_reply_form_post_request() && isset( $_POST['bbp_reply_edit_reason'] ) ) {
		$reply_edit_reason = wp_unslash( $_POST['bbp_reply_edit_reason'] );

	// No data
	} else {
		$reply_edit_reason = '';
	}

	/**
	 * Filters the value of the reply edit reason.
	 *
	 * @since 2.0.0 bbPress (r3101)
	 *
	 * @param string $reply_edit_reason Reply edit reason value.
	 */
	return apply_filters( 'bbp_get_form_reply_edit_reason', $reply_edit_reason );
}

/**
 * Output value reply status dropdown.
 *
 * @since 2.6.0 bbPress (r5399)
 *
 * @param array<string, mixed> $args Supported dropdown arguments.
 *  - select_id: Select id. Defaults to bbp_reply_status
 *  - tab: Deprecated. Tabindex
 *  - reply_id: Reply id
 *  - selected: Override the selected option.
 *
 * @return void
 */
function bbp_form_reply_status_dropdown( $args = array() ) {
	echo bbp_get_form_reply_status_dropdown( $args );
}

/**
 * Returns reply status dropdown.
 *
 * This dropdown is only intended to be seen by users with the 'moderate'
 * capability. Because of this, no additional capability checks are performed
 * within this function to check available reply statuses.
 *
 * @since 2.6.0 bbPress (r5399)
 *
 * @param array<string, mixed> $args Supported dropdown arguments.
 *  - select_id: Select id. Defaults to bbp_reply_status
 *  - tab: Deprecated. Tabindex
 *  - reply_id: Reply id
 *  - selected: Override the selected option.
 * @return string Reply status dropdown markup.
 */
function bbp_get_form_reply_status_dropdown( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'select_id'    => 'bbp_reply_status',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'reply_id'     => 0,
			'selected'     => false
		),
		'reply_status_dropdown'
	);

	// Use valid field names and IDs from the parsed arguments
	$select_id    = bbp_get_string_arg( $r, 'select_id' );
	$select_class = bbp_get_string_arg( $r, 'select_class' );
	$reply_arg    = is_numeric( $r['reply_id'] ) ? $r['reply_id'] : 0;
	$reply_id     = (int) $reply_arg;

	// No specific selected value passed
	if ( empty( $r['selected'] ) ) {

		// Post value is passed
		if ( bbp_is_reply_form_post_request() && isset( $_POST[ $select_id ] ) ) {
			$r['selected'] = sanitize_key( $_POST[ $select_id ] );

		// Edit reply
		} elseif ( bbp_is_reply_edit() ) {
			$reply_id = bbp_get_reply_id( $reply_arg );
			$r['reply_id'] = $reply_id;
			$r['selected'] = bbp_get_reply_status( $reply_id );

		// New reply
		} else {
			$r['selected'] = bbp_get_public_status_id();
		}
	}

	// Start an output buffer, we'll finish it after the select loop
	ob_start(); ?>

	<select name="<?php echo esc_attr( $select_id ); ?>" id="<?php echo esc_attr( $select_id ); ?>_select" class="<?php echo esc_attr( $select_class ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

		<?php foreach ( bbp_get_reply_statuses( $reply_id ) as $key => $label ) : ?>

			<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php

	/**
	 * Filters the reply status dropdown.
	 *
	 * @since 2.6.0 bbPress (r5399)
	 *
	 * @param mixed $value Reply status dropdown markup.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_form_reply_status_dropdown', ob_get_clean(), $r, $args );
}

/**
 * Verify if a POST request came from a failed reply attempt.
 *
 * Used to avoid cross-site request forgeries when checking posted reply form
 * content.
 *
 * @see bbp_reply_form_fields()
 *
 * @since 2.6.0 bbPress (r5558)
 *
 * @return boolean True if is a post request with valid nonce.
 */
function bbp_is_reply_form_post_request() {

	// Bail if not a post request
	if ( ! bbp_is_post_request() ) {
		return false;
	}

	$action = bbp_get_post_action();

	// Bail if not a reply form action
	if ( ! bbp_is_post_action( array( 'bbp-new-reply', 'bbp-edit-reply' ) ) ) {
		return false;
	}

	// Creating a new reply
	if ( ( 'bbp-new-reply' === $action ) && bbp_verify_nonce_request( 'bbp-new-reply' ) ) {
		return true;
	}

	// Editing an existing reply
	if ( ( 'bbp-edit-reply' === $action ) && bbp_verify_nonce_request( 'bbp-edit-reply_' . bbp_get_reply_id() ) ) {
		return true;
	}

	return false;
}
