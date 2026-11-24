<?php

/**
 * bbPress Common Template Tags.
 *
 * Common template tags are ones that are used by more than one component, like
 * forums, topics, replies, users, topic tags, etc...
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** URLs **********************************************************************/

/**
 * Output the forum URL.
 *
 * @since 2.1.0 bbPress (r3979)
 *
 * @param string $path Additional path with leading slash.
 * @return void
 */
function bbp_forums_url( $path = '/' ) {
	echo esc_url( bbp_get_forums_url( $path ) );
}

/**
 * Return the forum URL.
 *
 * @since 2.1.0 bbPress (r3979)
 *
 * @param string $path Additional path with leading slash.
 * @return string URL to the forums archive.
 */
function bbp_get_forums_url( $path = '/' ) {
	return home_url( bbp_get_root_slug() . $path );
}

/**
 * Output the forum URL.
 *
 * @since 2.1.0 bbPress (r3979)
 *
 * @param string $path Additional path with leading slash.
 * @return void
 */
function bbp_topics_url( $path = '/' ) {
	echo esc_url( bbp_get_topics_url( $path ) );
}

/**
 * Return the forum URL.
 *
 * @since 2.1.0 bbPress (r3979)
 *
 * @param string $path Additional path with leading slash.
 *
 * @return string URL to the topics archive.
 */
function bbp_get_topics_url( $path = '/' ) {
	return home_url( bbp_get_topic_archive_slug() . $path );
}

/** Navigation ****************************************************************/

/**
 * Mark the forum root and its menu ancestors while viewing forum content.
 *
 * A forum archive query does not identify a Page at the forum root as current.
 * WordPress also does not treat the archive as the parent of individual forums,
 * topics, and replies.
 *
 * @since 2.7.0 bbPress (r8608)
 *
 * @param array<WP_Post> $menu_items Sorted navigation menu items.
 * @return array<WP_Post> Navigation menu items with forum parent classes.
 */
function bbp_set_forum_nav_menu_classes( $menu_items ) {
	$is_archive = bbp_is_forum_archive();

	if ( ! $is_archive && ! bbp_is_single_forum() && ! bbp_is_single_topic() && ! bbp_is_single_reply() ) {
		return $menu_items;
	}

	$root_slug     = bbp_get_root_slug();
	$root_page     = ( '' !== $root_slug ) ? bbp_get_page_by_path( $root_slug ) : null;
	$root_page_id  = $root_page ? (int) $root_page->ID : 0;
	$posts_page_id = (int) get_option( 'page_for_posts' );
	$menu_keys     = array();
	$root_keys     = array();
	$root_urls     = array();

	if ( '' !== $root_slug ) {
		$forum_urls = array( bbp_get_forums_url(), get_post_type_archive_link( bbp_get_forum_post_type() ) );

		if ( $root_page ) {
			$forum_urls[] = get_permalink( $root_page );
		}

		foreach ( array_filter( $forum_urls ) as $forum_url ) {
			$root_url    = untrailingslashit( set_url_scheme( $forum_url ) );
			$root_urls[] = $root_url;
			$root_urls[] = untrailingslashit( wp_make_link_relative( $root_url ) );
		}
	}

	foreach ( $menu_items as $key => $item ) {
		$menu_keys[ (int) $item->db_id ] = $key;

		$is_page = ( 'post_type' === $item->type ) && ( 'page' === $item->object );

		// The posts page is not the parent of forum content.
		if ( $is_page && $posts_page_id && ( $posts_page_id === (int) $item->object_id ) && ! in_array( 'current-menu-parent', (array) $item->classes, true ) ) {
			$menu_items[ $key ]->classes = array_diff( (array) $item->classes, array( 'current_page_parent' ) );
		}

		$is_root_page    = $is_page && $root_page_id && ( $root_page_id === (int) $item->object_id );
		$is_root_archive = ( 'post_type_archive' === $item->type ) && ( bbp_get_forum_post_type() === $item->object );
		$is_root_link    = false;

		if ( ( 'custom' === $item->type ) && ! empty( $item->url ) ) {
			$url          = explode( '#', $item->url, 2 );
			$menu_url     = untrailingslashit( set_url_scheme( $url[0] ) );
			$is_root_link = in_array( $menu_url, $root_urls, true );
		}

		if ( ! $is_root_page && ! $is_root_archive && ! $is_root_link ) {
			continue;
		}

		$classes = (array) $menu_items[ $key ]->classes;

		if ( $is_archive ) {
			$classes[] = 'current-menu-item';
			$menu_items[ $key ]->current = true;

			if ( $is_root_page ) {
				$classes[] = 'page_item';
				$classes[] = 'page-item-' . $item->object_id;
				$classes[] = 'current_page_item';
			}
		} else {
			$classes = array_merge( $classes, array( 'current-menu-parent', 'current-menu-ancestor' ) );

			if ( $is_root_page ) {
				$classes = array_merge( $classes, array( 'current_page_parent', 'current_page_ancestor' ) );
			}

			$menu_items[ $key ]->current_item_parent   = true;
			$menu_items[ $key ]->current_item_ancestor = true;
		}

		$menu_items[ $key ]->classes = array_unique( $classes );
		$root_keys[] = $key;
	}

	// A parent menu item also needs a class when Forums is nested beneath it.
	foreach ( $root_keys as $key ) {
		$parent_id = (int) $menu_items[ $key ]->menu_item_parent;
		$seen      = array();
		$depth     = 0;

		while ( $parent_id && isset( $menu_keys[ $parent_id ] ) && ! isset( $seen[ $parent_id ] ) ) {
			$seen[ $parent_id ] = true;
			$parent_key         = $menu_keys[ $parent_id ];
			$parent             = $menu_items[ $parent_key ];
			$classes            = array_merge( (array) $parent->classes, array( 'current-menu-ancestor' ) );

			if ( $is_archive && ( 0 === $depth ) ) {
				$classes[]                   = 'current-menu-parent';
				$parent->current_item_parent = true;
			}

			if ( ( 'post_type' === $parent->type ) && ( 'page' === $parent->object ) ) {
				$classes[] = 'current_page_ancestor';

				if ( $is_archive && ( 0 === $depth ) ) {
					$classes[] = 'current_page_parent';
				}
			}

			$parent->classes               = array_unique( $classes );
			$parent->current_item_ancestor = true;
			$parent_id                     = (int) $parent->menu_item_parent;
			++$depth;
		}
	}

	return $menu_items;
}

/** Add-on Actions ************************************************************/

/**
 * Add our custom head action to wp_head.
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @return void
 */
function bbp_head() {
	/**
	 * Fires in the document head for bbPress content.
	 *
	 * @since 2.0.0 bbPress (r2464)
	 */
	do_action( 'bbp_head' );
}

/**
 * Add our custom footer action to wp_footer.
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @return void
 */
function bbp_footer() {
	/**
	 * Fires in the document footer for bbPress content.
	 *
	 * @since 2.0.0 bbPress (r2464)
	 */
	do_action( 'bbp_footer' );
}

/** is_ ***********************************************************************/

/**
 * Check if current site is public.
 *
 * @since 2.0.0 bbPress (r3398)
 *
 * @param int $site_id Site ID to check. Defaults to the current site.
 *
 * @return bool True if site is public, false if private.
 */
function bbp_is_site_public( $site_id = 0 ) {

	// Get the current site ID
	if ( empty( $site_id ) ) {
		$site_id = get_current_blog_id();
	}

	// Get the site visibility setting
	$public = is_multisite()
		? get_blog_option( $site_id, 'blog_public', 1 )
		: get_option( 'blog_public', 1 );

	/**
	 * Filters whether current site is public.
	 *
	 * @since 2.0.0 bbPress (r3398)
	 *
	 * @param bool $public True if site is public, false if private.
	 * @param int $site_id Site ID to check. Defaults to the current site.
	 */
	return (bool) apply_filters( 'bbp_is_site_public', $public, $site_id );
}

/**
 * Check if current page is a bbPress forum.
 *
 * @since 2.0.0 bbPress (r2549)
 *
 * @param int|WP_Post $post_id Possible post ID or object to check.
 *
 * @return bool True if it's a forum page, false if not.
 */
function bbp_is_forum( $post_id = 0 ) {

	// Assume false
	$retval = false;

	// Supplied ID is a forum
	if ( ! empty( $post_id ) && ( bbp_get_forum_post_type() === get_post_type( $post_id ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a bbPress forum.
	 *
	 * @since 2.0.0 bbPress (r3344)
	 *
	 * @param bool $retval True if it's a forum page, false if not.
	 * @param int|WP_Post $post_id Possible post ID or object to check.
	 */
	return (bool) apply_filters( 'bbp_is_forum', $retval, $post_id );
}

/**
 * Check if we are viewing a forum archive.
 *
 * @since 2.0.0 bbPress (r3251)
 *
 * @return bool
 */
function bbp_is_forum_archive() {

	// Default to false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// In forum archive
	if ( is_post_type_archive( bbp_get_forum_post_type() ) || bbp_is_query_name( 'bbp_forum_archive' ) || ! empty( $wp_query->bbp_show_topics_on_root ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page shows a forum archive.
	 *
	 * @since 2.0.0 bbPress (r3251)
	 *
	 * @param bool $retval Whether the current page shows a forum archive.
	 */
	return (bool) apply_filters( 'bbp_is_forum_archive', $retval );
}

/**
 * Viewing a single forum.
 *
 * @since 2.0.0 bbPress (r3344)
 *
 * @return bool
 */
function bbp_is_single_forum() {

	// Assume false
	$retval = false;

	// Edit is not a single forum
	if ( bbp_is_forum_edit() ) {
		return false;
	}

	// Single and a match
	if ( is_singular( bbp_get_forum_post_type() ) || bbp_is_query_name( 'bbp_single_forum' ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page shows a single forum.
	 *
	 * @since 2.0.0 bbPress (r3344)
	 *
	 * @param bool $retval Whether the current page shows a single forum.
	 */
	return (bool) apply_filters( 'bbp_is_single_forum', $retval );
}

/**
 * Check if current page is a forum edit page.
 *
 * The current query and filters can change between calls.
 *
 * @since 2.1.0 bbPress (r3553)
 *
 * @global string $pagenow The filename of the current screen.
 *
 * @return bool True if it's the forum edit page, false if not.
 *
 * @phpstan-impure
 */
function bbp_is_forum_edit() {
	global $pagenow;

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_forum_edit ) && ( true === $wp_query->bbp_is_forum_edit ) ) {
		$retval = true;

	// Editing in admin
	} elseif ( is_admin() && ( 'post.php' === $pagenow ) && ( get_post_type() === bbp_get_forum_post_type() ) && ( ! empty( $_GET['action'] ) && ( 'edit' === $_GET['action'] ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a forum edit page.
	 *
	 * @since 2.1.0 bbPress (r3553)
	 *
	 * @param bool $retval True if it's the forum edit page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_forum_edit', $retval );
}

/**
 * Check if current page is a bbPress topic.
 *
 * @since 2.0.0 bbPress (r2549)
 *
 * @param int|WP_Post $post_id Possible post ID or object to check.
 *
 * @return bool True if it's a topic page, false if not.
 */
function bbp_is_topic( $post_id = 0 ) {

	// Assume false
	$retval = false;

	// Supplied ID is a topic
	if ( ! empty( $post_id ) && ( bbp_get_topic_post_type() === get_post_type( $post_id ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a bbPress topic.
	 *
	 * @since 2.0.0 bbPress (r3344)
	 *
	 * @param bool $retval True if it's a topic page, false if not.
	 * @param int|WP_Post $post_id Possible post ID or object to check.
	 */
	return (bool) apply_filters( 'bbp_is_topic', $retval, $post_id );
}

/**
 * Viewing a single topic.
 *
 * @since 2.0.0 bbPress (r3344)
 *
 * @return bool
 */
function bbp_is_single_topic() {

	// Assume false
	$retval = false;

	// Edit is not a single topic
	if ( bbp_is_topic_edit() ) {
		return false;
	}

	// Single and a match
	if ( is_singular( bbp_get_topic_post_type() ) || bbp_is_query_name( 'bbp_single_topic' ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page shows a single topic.
	 *
	 * @since 2.0.0 bbPress (r3344)
	 *
	 * @param bool $retval Whether the current page shows a single topic.
	 */
	return (bool) apply_filters( 'bbp_is_single_topic', $retval );
}

/**
 * Check if we are viewing a topic archive.
 *
 * @since 2.0.0 bbPress (r3251)
 *
 * @return bool
 */
function bbp_is_topic_archive() {

	// Default to false
	$retval = false;

	// In topic archive
	if ( is_post_type_archive( bbp_get_topic_post_type() ) || bbp_is_query_name( 'bbp_topic_archive' ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page shows a topic archive.
	 *
	 * @since 2.0.0 bbPress (r3251)
	 *
	 * @param bool $retval Whether the current page shows a topic archive.
	 */
	return (bool) apply_filters( 'bbp_is_topic_archive', $retval );
}

/**
 * Check if current page is a topic edit page.
 *
 * The current query and filters can change between calls.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @global string $pagenow The filename of the current screen.
 *
 * @return bool True if it's the topic edit page, false if not.
 *
 * @phpstan-impure
 */
function bbp_is_topic_edit() {
	global $pagenow;

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_topic_edit ) && ( true === $wp_query->bbp_is_topic_edit ) ) {
		$retval = true;

	// Editing in admin
	} elseif ( is_admin() && ( 'post.php' === $pagenow ) && ( get_post_type() === bbp_get_topic_post_type() ) && ( ! empty( $_GET['action'] ) && ( 'edit' === $_GET['action'] ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a topic edit page.
	 *
	 * @since 2.1.0 bbPress (r3553)
	 *
	 * @param bool $retval True if it's the topic edit page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_topic_edit', $retval );
}

/**
 * Check if current page is a topic merge page.
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @return bool True if it's the topic merge page, false if not.
 */
function bbp_is_topic_merge() {

	// Assume false
	$retval = false;

	// Check topic edit and GET params
	if ( bbp_is_topic_edit() && bbp_is_get_action( array( 'merge', 'bbp-merge-topic' ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a topic merge page.
	 *
	 * @since 2.1.0 bbPress (r3553)
	 *
	 * @param bool $retval True if it's the topic merge page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_topic_merge', $retval );
}

/**
 * Check if current page is a topic split page.
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @return bool True if it's the topic split page, false if not.
 */
function bbp_is_topic_split() {

	// Assume false
	$retval = false;

	// Check topic edit and GET params
	if ( bbp_is_topic_edit() && bbp_is_get_action( array( 'split', 'bbp-split-topic' ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a topic split page.
	 *
	 * @since 2.1.0 bbPress (r3553)
	 *
	 * @param bool $retval True if it's the topic split page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_topic_split', $retval );
}

/**
 * Check if the current page is a topic tag.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return bool True if it's a topic tag, false if not.
 */
function bbp_is_topic_tag() {

	// Bail if topic-tags are off
	if ( ! bbp_allow_topic_tags() ) {
		return false;
	}

	// Return false if editing a topic tag
	if ( bbp_is_topic_tag_edit() ) {
		return false;
	}

	// Assume false
	$retval = false;

	// Check tax and query vars
	if ( is_tax( bbp_get_topic_tag_tax_id() ) || ! empty( bbpress()->topic_query->is_tax ) || get_query_var( 'bbp_topic_tag' ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a topic tag.
	 *
	 * @since 2.1.0 bbPress (r3553)
	 *
	 * @param bool $retval True if it's a topic tag, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_topic_tag', $retval );
}

/**
 * Check if the current page is editing a topic tag.
 *
 * The current query and filters can change between calls.
 *
 * @since 2.0.0 bbPress (r3348)
 *
 * @global string $pagenow The filename of the current screen.
 * @global string $taxnow  The taxonomy of the current screen.
 *
 * @return bool True if editing a topic tag, false if not.
 *
 * @phpstan-impure
 */
function bbp_is_topic_tag_edit() {
	global $pagenow, $taxnow;

	// Bail if topic-tags are off
	if ( ! bbp_allow_topic_tags() ) {
		return false;
	}

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_topic_tag_edit ) && ( true === $wp_query->bbp_is_topic_tag_edit ) ) {
		$retval = true;

	// Editing in admin
	} elseif ( is_admin() && ( 'edit-tags.php' === $pagenow ) && ( bbp_get_topic_tag_tax_id() === $taxnow ) && ( ! empty( $_GET['action'] ) && ( 'edit' === $_GET['action'] ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is editing a topic tag.
	 *
	 * @since 2.1.0 bbPress (r3553)
	 *
	 * @param bool $retval True if editing a topic tag, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_topic_tag_edit', $retval );
}

/**
 * Check if the current post type is one that comes with bbPress.
 *
 * @since 2.0.0 bbPress (r3311)
 * @since 2.6.17 bbPress (r7499) Added support for post-type names and arrays.
 *
 * @param mixed $post_types Optional. Post object, post ID, post-type name, or
 *                          an array of post-type names.
 *
 * @return bool
 */
function bbp_is_custom_post_type( $post_types = false ) {
	$original_post_types = $post_types;

	// Preserve the existing scalar post object or ID behavior
	if ( ! is_array( $post_types ) ) {
		$post_type = get_post_type( $post_types );

		// Use a supplied post-type name if it is not a post object or ID
		$post_types = empty( $post_type ) && is_string( $post_types )
			? array( $post_types )
			: array( $post_type );
	}

	// Compare post types
	$post_types     = bbp_get_string_array_values( $post_types );
	$bbp_post_types = bbp_get_post_types();
	$retval         = ! empty( array_intersect( $post_types, $bbp_post_types ) );

	/**
	 * Filters whether the current post type is one that comes with bbPress.
	 *
	 * @since 2.1.0 bbPress (r3902)
	 *
	 * @param bool $retval Whether the current post type is one that comes with bbPress.
	 * @param array<int, string> $original_post_types Original post type IDs.
	 */
	return (bool) apply_filters( 'bbp_is_custom_post_type', $retval, $original_post_types );
}

/**
 * Check if current page is a bbPress reply.
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int|WP_Post $post_id Possible post ID or object to check.
 *
 * @return bool True if it's a reply page, false if not.
 */
function bbp_is_reply( $post_id = 0 ) {

	// Assume false
	$retval = false;

	// Supplied ID is a reply
	if ( ! empty( $post_id ) && ( bbp_get_reply_post_type() === get_post_type( $post_id ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a bbPress reply.
	 *
	 * @since 2.0.0 bbPress (r3344)
	 *
	 * @param bool $retval True if it's a reply page, false if not.
	 * @param int|WP_Post $post_id Possible post ID or object to check.
	 */
	return (bool) apply_filters( 'bbp_is_reply', $retval, $post_id );
}

/**
 * Check if current page is a reply edit page.
 *
 * The current query and filters can change between calls.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @global string $pagenow The filename of the current screen.
 *
 * @return bool True if it's the reply edit page, false if not.
 *
 * @phpstan-impure
 */
function bbp_is_reply_edit() {
	global $pagenow;

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_reply_edit ) && ( true === $wp_query->bbp_is_reply_edit ) ) {
		$retval = true;

	// Editing in admin
	} elseif ( is_admin() && ( 'post.php' === $pagenow ) && ( get_post_type() === bbp_get_reply_post_type() ) && ( ! empty( $_GET['action'] ) && ( 'edit' === $_GET['action'] ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a reply edit page.
	 *
	 * @since 2.1.0 bbPress (r3553)
	 *
	 * @param bool $retval True if it's the reply edit page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_reply_edit', $retval );
}

/**
 * Check if current page is a reply move page.
 *
 * @return bool True if it's the reply move page, false if not.
 */
function bbp_is_reply_move() {

	// Assume false
	$retval = false;

	// Check reply edit and GET params
	if ( bbp_is_reply_edit() && bbp_is_get_action( array( 'move', 'bbp-move-reply' ) ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a reply move page.
	 *
	 * @since 2.3.0 bbPress (r4522)
	 *
	 * @param bool $retval True if it's the reply move page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_reply_move', $retval );
}

/**
 * Viewing a single reply.
 *
 * @since 2.0.0 bbPress (r3344)
 *
 * @return bool
 */
function bbp_is_single_reply() {

	// Assume false
	$retval = false;

	// Edit is not a single reply
	if ( bbp_is_reply_edit() ) {
		return false;
	}

	// Single and a match
	if ( is_singular( bbp_get_reply_post_type() ) || bbp_is_query_name( 'bbp_single_reply' ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page shows a single reply.
	 *
	 * @since 2.0.0 bbPress (r3344)
	 *
	 * @param bool $retval Whether the current page shows a single reply.
	 */
	return (bool) apply_filters( 'bbp_is_single_reply', $retval );
}

/**
 * Check if current page is a bbPress user's favorites page (profile page).
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @return bool True if it's the favorites page, false if not.
 */
function bbp_is_favorites() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_favs ) && ( true === $wp_query->bbp_is_single_user_favs ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a bbPress user's favorites page (profile page).
	 *
	 * @since 2.0.0 bbPress (r3303)
	 *
	 * @param bool $retval True if it's the favorites page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_favorites', $retval );
}

/**
 * Check if current page is a bbPress user's subscriptions page (profile page).
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @return bool True if it's the subscriptions page, false if not.
 */
function bbp_is_subscriptions() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_subs ) && ( true === $wp_query->bbp_is_single_user_subs ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a bbPress user's subscriptions page (profile page).
	 *
	 * @since 2.0.0 bbPress (r3303)
	 *
	 * @param bool $retval True if it's the subscriptions page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_subscriptions', $retval );
}

/**
 * Check if current page shows the topics created by a bbPress user (profile
 * page).
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return bool True if it's the topics created page, false if not.
 */
function bbp_is_topics_created() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_topics ) && ( true === $wp_query->bbp_is_single_user_topics ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page shows the topics created by a bbPress user (profile.
	 *
	 * @since 2.0.0 bbPress (r3303)
	 *
	 * @param bool $retval True if it's the topics created page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_topics_created', $retval );
}

/**
 * Check if current page shows the replies created by a bbPress user (profile
 * page).
 *
 * @since 2.2.0 bbPress (r4228)
 *
 * @return bool True if it's the replies created page, false if not.
 */
function bbp_is_replies_created() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_replies ) && ( true === $wp_query->bbp_is_single_user_replies ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page shows the replies created by a bbPress user (profile.
	 *
	 * @since 2.2.0 bbPress (r4228)
	 *
	 * @param bool $retval True if it's the replies created page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_replies_created', $retval );
}

/**
 * Check if current page is the currently logged in users author page.
 *
 * @since 2.0.0 bbPress (r2660)
 *
 * @return bool True if it's the user's home, false if not.
 */
function bbp_is_user_home() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_home ) && ( true === $wp_query->bbp_is_single_user_home ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is the currently logged in users author page.
	 *
	 * @since 2.1.0 bbPress (r2660)
	 *
	 * @param bool $retval True if it's the user's home, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_user_home', $retval );
}

/**
 * Check if current page is the currently logged in users author edit page.
 *
 * @since 2.1.0 bbPress (r3918)
 *
 * @return bool True if it's the user's home, false if not.
 */
function bbp_is_user_home_edit() {

	// Assume false
	$retval = false;

	if ( bbp_is_user_home() && bbp_is_single_user_edit() ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is the currently logged in users author edit page.
	 *
	 * @since 2.1.0 bbPress (r3918)
	 *
	 * @param bool $retval True if it's the user's home, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_user_home_edit', $retval );
}

/**
 * Check if current page is a user profile page.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return bool True if it's a user's profile page, false if not.
 */
function bbp_is_single_user() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user ) && ( true === $wp_query->bbp_is_single_user ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a user profile page.
	 *
	 * @since 2.1.0 bbPress (r3552)
	 *
	 * @param bool $retval True if it's a user's profile page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_single_user', $retval );
}

/**
 * Check if current page is a user profile edit page.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return bool True if it's a user's profile edit page, false if not.
 */
function bbp_is_single_user_edit() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_edit ) && ( true === $wp_query->bbp_is_single_user_edit ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a user profile edit page.
	 *
	 * @since 2.1.0 bbPress (r3553)
	 *
	 * @param bool $retval True if it's a user's profile edit page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_single_user_edit', $retval );
}

/**
 * Check if current page is a user profile page.
 *
 * @since 2.2.0 bbPress (r4228)
 *
 * @return bool True if it's a user's profile page, false if not.
 */
function bbp_is_single_user_profile() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_profile ) && ( true === $wp_query->bbp_is_single_user_profile ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a user profile page.
	 *
	 * @since 2.2.0 bbPress (r4228)
	 *
	 * @param bool $retval True if it's a user's profile page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_single_user_profile', $retval );
}

/**
 * Check if current page is a user topics created page.
 *
 * @since 2.2.0 bbPress (r4228)
 *
 * @return bool True if it's a user's topics page, false if not.
 */
function bbp_is_single_user_topics() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_topics ) && ( true === $wp_query->bbp_is_single_user_topics ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a user topics created page.
	 *
	 * @since 2.2.0 bbPress (r4228)
	 *
	 * @param bool $retval True if it's a user's topics page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_single_user_topics', $retval );
}

/**
 * Check if current page is a user replies created page.
 *
 * @since 2.2.0 bbPress (r4228)
 *
 * @return bool True if it's a user's replies page, false if not.
 */
function bbp_is_single_user_replies() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_replies ) && ( true === $wp_query->bbp_is_single_user_replies ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a user replies created page.
	 *
	 * @since 2.2.0 bbPress (r4228)
	 *
	 * @param bool $retval True if it's a user's replies page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_single_user_replies', $retval );
}

/**
 * Check if current page is a user engagements page.
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @return bool True if it's a user's replies page, false if not.
 */
function bbp_is_single_user_engagements() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_single_user_engagements ) && ( true === $wp_query->bbp_is_single_user_engagements ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a user engagements page.
	 *
	 * @since 2.6.0 bbPress (r6320)
	 *
	 * @param bool $retval True if it's a user's replies page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_single_user_engagements', $retval );
}

/**
 * Check whether the current page is a user's mention history.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return bool
 */
function bbp_is_single_user_mentions() {
	$query = bbp_get_wp_query();

	/**
	 * Filters whether the request shows a user's mentions.
	 *
	 * @since 2.7.0 bbPress (r8264)
	 *
	 * @param bool $value Whether the request shows a user's mentions.
	 */
	return (bool) apply_filters( 'bbp_is_single_user_mentions', ! empty( $query->bbp_is_single_user_mentions ) );
}

/**
 * Check if current page is a view page.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return bool Is it a view page?
 */
function bbp_is_single_view() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_view ) && ( true === $wp_query->bbp_is_view ) ) {
		$retval = true;
	}

	// Check query name
	if ( empty( $retval ) && bbp_is_query_name( 'bbp_single_view' ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a view page.
	 *
	 * @since 2.0.1 bbPress (r3541)
	 *
	 * @param bool $retval Whether the current page is a view page.
	 */
	return (bool) apply_filters( 'bbp_is_single_view', $retval );
}

/**
 * Check if current page is a search page.
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @return bool Is it a search page?
 */
function bbp_is_search() {

	// Bail if search is disabled
	if ( ! bbp_allow_search() ) {
		return false;
	}

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Get the rewrite ID (one time, to avoid repeated calls )
	$rewrite_id = bbp_get_search_rewrite_id();

	// Check query
	if ( isset( $wp_query->bbp_is_search ) && ( true === $wp_query->bbp_is_search ) ) {
		$retval = true;
	}

	// Check query name
	if ( empty( $retval ) && bbp_is_query_name( $rewrite_id ) ) {
		$retval = true;
	}

	// Check $_GET
	if ( empty( $retval ) && isset( $_REQUEST[ $rewrite_id ] ) && empty( $_REQUEST[ $rewrite_id ] ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a search page.
	 *
	 * @since 2.3.0 bbPress (r4579)
	 *
	 * @param bool $retval Whether the current page is a search page.
	 */
	return (bool) apply_filters( 'bbp_is_search', $retval );
}

/**
 * Check if current page is a search results page.
 *
 * @since 2.4.0 bbPress (r4919)
 *
 * @return bool Is it a search page?
 */
function bbp_is_search_results() {

	// Bail if search is disabled
	if ( ! bbp_allow_search() ) {
		return false;
	}

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_search_terms ) ) {
		$retval = true;
	}

	// Check query name
	if ( empty( $retval ) && bbp_is_query_name( 'bbp_search_results' ) ) {
		$retval = true;
	}

	// Check $_REQUEST
	if ( empty( $retval ) && ! empty( $_REQUEST[ bbp_get_search_rewrite_id() ] ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is a search results page.
	 *
	 * @since 2.4.0 bbPress (r4919)
	 *
	 * @param bool $retval Whether the current page is a search results page.
	 */
	return (bool) apply_filters( 'bbp_is_search_results', $retval );
}

/**
 * Check if current page is an edit page.
 *
 * @since 2.1.0 bbPress (r3586)
 *
 * @return bool True if it's the edit page, false if not.
 */
function bbp_is_edit() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( isset( $wp_query->bbp_is_edit ) && ( true === $wp_query->bbp_is_edit ) ) {
		$retval = true;
	}

	/**
	 * Filters whether the current page is an edit page.
	 *
	 * @since 2.1.0 bbPress (r3586)
	 *
	 * @param bool $retval True if it's the edit page, false if not.
	 */
	return (bool) apply_filters( 'bbp_is_edit', $retval );
}

/**
 * Use the above is_() functions to output a body class for each scenario.
 *
 * @since 2.0.0 bbPress (r2926)
 *
 * @param array<int, string>      $wp_classes WordPress body classes.
 * @param array<int, string>|bool $custom_classes Custom classes passed to the body class filters.
 *
 * @return array<int, string> Body classes.
 */
function bbp_body_class( $wp_classes = array(), $custom_classes = false ) {

	// Default to no classes
	$bbp_classes = array();

	/* Subscription Unsubscribe ***********************************************/

	if ( bbp_is_subscription_unsubscribe() ) {
		$bbp_classes[] = 'bbp-subscription-unsubscribe';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';

	/* Archives ***************************************************************/
	} elseif ( bbp_is_forum_archive() ) {
		$bbp_classes[] = bbp_get_forum_post_type() . '-archive';
	} elseif ( bbp_is_topic_archive() ) {
		$bbp_classes[] = bbp_get_topic_post_type() . '-archive';

	/* Topic Tags *************************************************************/
	} elseif ( bbp_is_topic_tag() ) {
		$bbp_classes[] = bbp_get_topic_tag_tax_id();
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-' . bbp_get_topic_tag_slug();
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-' . bbp_get_topic_tag_id();
	} elseif ( bbp_is_topic_tag_edit() ) {
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-edit';
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-' . bbp_get_topic_tag_slug() . '-edit';
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-' . bbp_get_topic_tag_id() . '-edit';

	/* Components *************************************************************/
	} elseif ( bbp_is_single_forum() ) {
		$bbp_classes[] = bbp_get_forum_post_type();
	} elseif ( bbp_is_single_topic() ) {
		$bbp_classes[] = bbp_get_topic_post_type();
	} elseif ( bbp_is_single_reply() ) {
		$bbp_classes[] = bbp_get_reply_post_type();
	} elseif ( bbp_is_topic_merge() ) {
		$bbp_classes[] = bbp_get_topic_post_type() . '-merge';
		$bbp_classes[] = bbp_get_topic_post_type() . '-edit';
	} elseif ( bbp_is_topic_split() ) {
		$bbp_classes[] = bbp_get_topic_post_type() . '-split';
		$bbp_classes[] = bbp_get_topic_post_type() . '-edit';
	} elseif ( bbp_is_topic_edit() ) {
		$bbp_classes[] = bbp_get_topic_post_type() . '-edit';
	} elseif ( bbp_is_reply_move() ) {
		$bbp_classes[] = bbp_get_reply_post_type() . '-move';
		$bbp_classes[] = bbp_get_reply_post_type() . '-edit';
	} elseif ( bbp_is_reply_edit() ) {
		$bbp_classes[] = bbp_get_reply_post_type() . '-edit';
	} elseif ( bbp_is_single_view() ) {
		$bbp_classes[] = 'bbp-view';
		$bbp_classes[] = 'bbp-view-' . bbp_get_view_id();

	/* User *******************************************************************/
	} elseif ( bbp_is_user_home_edit() ) {
		$bbp_classes[] = 'bbp-user-home-edit';
		$bbp_classes[] = 'bbp-user-edit';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';
	} elseif ( bbp_is_single_user_edit() ) {
		$bbp_classes[] = 'bbp-user-edit';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';
	} elseif ( bbp_is_single_user() ) {
		$bbp_classes[] = 'bbp-user-page';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';
	} elseif ( bbp_is_user_home() ) {
		$bbp_classes[] = 'bbp-user-home';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';
	} elseif ( bbp_is_topics_created() ) {
		$bbp_classes[] = 'bbp-topics-created';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';
	} elseif ( bbp_is_replies_created() ) {
		$bbp_classes[] = 'bbp-replies-created';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';
	} elseif ( bbp_is_favorites() ) {
		$bbp_classes[] = 'bbp-favorites';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';
	} elseif ( bbp_is_subscriptions() ) {
		$bbp_classes[] = 'bbp-subscriptions';
		$bbp_classes[] = 'single';
		$bbp_classes[] = 'singular';

	/* Search *****************************************************************/
	} elseif ( bbp_is_search_results() ) {
		$bbp_classes[] = 'bbp-search-results';
		$bbp_classes[] = 'forum-search-results';

		if ( bbp_is_search() ) {
			$bbp_classes[] = 'bbp-search';
			$bbp_classes[] = 'forum-search';
		}
	} elseif ( bbp_is_search() ) {
		$bbp_classes[] = 'bbp-search';
		$bbp_classes[] = 'forum-search';

	/* Shortcodes *************************************************************/
	} elseif ( bbp_has_shortcode() ) {
		$bbp_classes[] = 'bbp-shortcode';
	}

	/* General ****************************************************************/

	// Any page with bbPress content
	if ( ! empty( $bbp_classes ) ) {
		$bbp_classes[] = 'bbpress';
		$bbp_classes[] = 'bbp-no-js';
	}

	/** Clean up **************************************************************/

	// Merge WP classes with bbPress classes and remove any duplicates
	$classes = array_unique(array_merge( (array) $bbp_classes, (array) $wp_classes ) );

	// Deprecated filter (do not use )

	/**
	 * Filters the CSS classes added to the document body.
	 *
	 * @since 2.0.0 bbPress (r2926)
	 *
	 * @param array $classes The CSS classes added to the document body.
	 * @param array<int, string> $bbp_classes Bbp classes.
	 * @param array<int, string> $wp_classes Wp classes.
	 * @param array<int, string>|bool $custom_classes Custom classes.
	 */
	$classes = apply_filters( 'bbp_get_the_body_class', $classes, $bbp_classes, $wp_classes, $custom_classes );

	/**
	 * Filters the body classes.
	 *
	 * @since 2.0.0 bbPress (r2926)
	 *
	 * @param array<int, string> $classes Body classes.
	 * @param array<int, string> $bbp_classes bbPress body classes.
	 * @param array<int, string> $wp_classes WordPress body classes.
	 * @param array<int, string>|bool $custom_classes Custom classes passed to the body class filters.
	 */
	return (array) apply_filters( 'bbp_body_class', $classes, $bbp_classes, $wp_classes, $custom_classes );
}

/**
 * Output a small piece of JavaScript to replace the "bbp-no-js" body class
 * with "bbp-js" to allow interactive & dynamic elements to work as intended.
 *
 * @since 2.6.10 bbPress (r7231)
 *
 * @return void
 */
function bbp_swap_no_js_body_class() {
	static $done = false;

	// Bail if already done
	if ( true === $done ) {
		return;
	}

	// Mark as done
	$done = true;

	?>

<script id="bbp-swap-no-js-body-class">
	document.body.className = document.body.className.replace( 'bbp-no-js', 'bbp-js' );
</script>

<?php
}

/**
 * Check if text contains a bbPress shortcode.
 *
 * Loops through registered bbPress shortcodes and keeps track of which ones
 * were used in a blob of text. If no text is passed, the current global post
 * content is assumed.
 *
 * A preliminary strpos() is performed before looping through each shortcode, to
 * prevent unnecessarily processing.
 *
 * @since 2.6.0 bbPress (r6440)
 *
 * @param string $text Content to check for a bbPress shortcode.
 *
 * @return bool
 */
function bbp_has_shortcode( $text = '' ) {

	// Default return value
	$retval = false;
	$found  = array();

	// Fallback to global post_content
	if ( empty( $text ) && is_singular() ) {
		$text = bbp_get_global_post_field( 'post_content', 'raw' );
	}

	// Skip if empty, or string doesn't contain the bbPress shortcode prefix
	if ( ! empty( $text ) && ( false !== strpos( $text, '[bbp' ) ) ) {

		// Get possible shortcodes
		$codes = array_keys( bbpress()->shortcodes->codes );

		// Loop through codes
		foreach ( $codes as $code ) {

			// Looking for shortcode in text
			if ( has_shortcode( $text, $code ) ) {
				$retval  = true;
				$found[] = $code;
			}
		}
	}

	/**
	 * Filters whether text contains a bbPress shortcode.
	 *
	 * @since 2.6.0 bbPress (r6440)
	 *
	 * @param bool $retval Whether text contains a bbPress shortcode.
	 * @param list<non-empty-string> $found Shortcode names found in the text.
	 * @param string $text Content to check for a bbPress shortcode.
	 */
	return (bool) apply_filters( 'bbp_has_shortcode', $retval, $found, $text );
}

/**
 * Use the above is_() functions to return if in any bbPress page.
 *
 * @since 2.0.0 bbPress (r3344)
 *
 * @return bool In a bbPress page.
 */
function is_bbpress() {

	// Default to false
	$retval = false;

	// Bail if main query has not been populated.
	if ( ! bbp_get_wp_query() ) {
		_doing_it_wrong(__FUNCTION__, esc_html__( 'Conditional query tags do not work before the query is run. Before then, they always return false.', 'bbpress' ), '2.7.0' );

		return $retval;
	}

	/* Subscription Unsubscribe ***********************************************/

	if ( bbp_is_subscription_unsubscribe() ) {
		$retval = true;

	/* Archives ***************************************************************/
	} elseif ( bbp_is_forum_archive() ) {
		$retval = true;
	} elseif ( bbp_is_topic_archive() ) {
		$retval = true;

	/* Topic Tags *************************************************************/
	} elseif ( bbp_is_topic_tag() ) {
		$retval = true;
	} elseif ( bbp_is_topic_tag_edit() ) {
		$retval = true;

	/* Components *************************************************************/
	} elseif ( bbp_is_single_forum() ) {
		$retval = true;
	} elseif ( bbp_is_single_topic() ) {
		$retval = true;
	} elseif ( bbp_is_single_reply() ) {
		$retval = true;
	} elseif ( bbp_is_topic_edit() ) {
		$retval = true;
	} elseif ( bbp_is_topic_merge() ) {
		$retval = true;
	} elseif ( bbp_is_topic_split() ) {
		$retval = true;
	} elseif ( bbp_is_reply_edit() ) {
		$retval = true;
	} elseif ( bbp_is_reply_move() ) {
		$retval = true;
	} elseif ( bbp_is_single_view() ) {
		$retval = true;

	/* User *******************************************************************/
	} elseif ( bbp_is_single_user_edit() ) {
		$retval = true;
	} elseif ( bbp_is_single_user() ) {
		$retval = true;
	} elseif ( bbp_is_user_home() ) {
		$retval = true;
	} elseif ( bbp_is_user_home_edit() ) {
		$retval = true;
	} elseif ( bbp_is_topics_created() ) {
		$retval = true;
	} elseif ( bbp_is_replies_created() ) {
		$retval = true;
	} elseif ( bbp_is_favorites() ) {
		$retval = true;
	} elseif ( bbp_is_subscriptions() ) {
		$retval = true;

	/* Search *****************************************************************/
	} elseif ( bbp_is_search() ) {
		$retval = true;
	} elseif ( bbp_is_search_results() ) {
		$retval = true;

	/* Shortcodes *************************************************************/
	} elseif ( bbp_has_shortcode() ) {
		$retval = true;
	}

	/* Done *******************************************************************/

	/**
	 * Filters whether the current page is a bbPress page.
	 *
	 * @since 2.0.0 bbPress (r3344)
	 *
	 * @param bool $retval In a bbPress page.
	 */
	return (bool) apply_filters( 'is_bbpress', $retval );
}

/** Forms *********************************************************************/

/**
 * Output the login form action url.
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @param array<string, mixed> $args Login action, context, and URL settings.
 * @return void
 */
function bbp_wp_login_action( $args = array() ) {
	echo esc_url( bbp_get_wp_login_action( $args ) );
}

/**
 * Return the login form action url.
 *
 * @since 2.6.0 bbPress (r5685)
 *
 * @param array<string, mixed> $args Login action, context, and URL settings.
 * @return string Login form action URL.
 */
function bbp_get_wp_login_action( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'action'  => '',
			'context' => '',
			'url'     => 'wp-login.php',
		),
		'login_action'
	);

	// Add action as query arg
	$login_url = ! empty( $r['action'] )
		? add_query_arg( array( 'action' => $r['action'] ), $r['url'] )
		: $r['url'];

	$login_url = site_url( $login_url, $r['context'] );

	/**
	 * Filters the login form action URL.
	 *
	 * @since 2.5.8 bbPress (r5698)
	 *
	 * @param string $login_url Login form action URL.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Login action, context, and URL settings.
	 */
	return apply_filters( 'bbp_get_wp_login_action', $login_url, $r, $args );
}

/**
 * Output hidden request URI field for user forms.
 *
 * The referer link is the current Request URI from the server super global. To
 * check the field manually, use bbp_get_redirect_to().
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @param string $redirect_to Pass a URL to redirect to.
 * @return void
 */
function bbp_redirect_to_field( $redirect_to = '' ) {

	// Make sure we are directing somewhere
	if ( empty( $redirect_to ) ) {
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$redirect_to = bbp_get_url_scheme() . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		} else {
			$redirect_to = wp_get_referer();
		}
	}

	// Remove loggedout query arg if it's there
	$redirect_to    = remove_query_arg( 'loggedout', $redirect_to );
	$redirect_field = '<input type="hidden" id="bbp_redirect_to" name="redirect_to" value="' . esc_url( $redirect_to ) . '" />';

	/**
	 * Filters the redirect URL stored in the form field.
	 *
	 * @since 2.0.0 bbPress (r2815)
	 *
	 * @param string $redirect_field Hidden redirect field markup.
	 * @param string $redirect_to Pass a URL to redirect to.
	 */
	echo apply_filters( 'bbp_redirect_to_field', $redirect_field, $redirect_to );
}

/**
 * Echo sanitized $_REQUEST value.
 *
 * Use the $input_type parameter to properly process the value. This
 * ensures correct sanitization of the value for the receiving input.
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @param string $request    Name of the request value to sanitize.
 * @param string $input_type Input type: text, textarea, password, select, radio, or checkbox.
 * @return void
 */
function bbp_sanitize_val( $request = '', $input_type = 'text' ) {
	echo bbp_get_sanitize_val( $request, $input_type );
}

/**
 * Return sanitized $_REQUEST value.
 *
 * Use the $input_type parameter to properly process the value. This
 * ensures correct sanitization of the value for the receiving input.
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @param string $request    Name of the request value to sanitize.
 * @param string $input_type Input type: text, textarea, password, select, radio, or checkbox.
 *
 * @return string|false Sanitized value, or false if the request value is empty.
 */
function bbp_get_sanitize_val( $request = '', $input_type = 'text' ) {

	// Check that requested
	if ( empty( $_REQUEST[ $request ] ) ) {
		return false;
	}

	// Set request varaible
	$pre_ret_val = $_REQUEST[ $request ];

	// Treat different kinds of fields in different ways
	switch ( $input_type ) {
		case 'text':
		case 'textarea':
			$retval = esc_attr(wp_unslash( $pre_ret_val ) );
			break;

		case 'password':
		case 'select':
		case 'radio':
		case 'checkbox':
		default:
			$retval = esc_attr( $pre_ret_val );
			break;
	}

	/**
	 * Filters the sanitized $_REQUEST value.
	 *
	 * @since 2.0.0 bbPress (r2815)
	 *
	 * @param string|false $retval Sanitized value, or false if the request value is empty.
	 * @param string $request Name of the request value to sanitize.
	 * @param string $input_type Input type: text, textarea, password, select, radio, or checkbox.
	 */
	return apply_filters( 'bbp_get_sanitize_val', $retval, $request, $input_type );
}

/**
 * Output the current tab index of a given form.
 *
 * Use this function to handle the tab indexing of user facing forms within a
 * template file. Calling this function will automatically increment the global
 * tab index by default.
 *
 * @since 2.0.0 bbPress (r2810)
 * @deprecated 2.6.0 bbPress (r5561)
 * @see https://bbpress.trac.wordpress.org/attachment/ticket/2714 Trac Ticket
 *
 * @param bool $auto_increment Optional. Default true. Set to false to prevent
 *                            increment.
 * @return void
 */
function bbp_tab_index( $auto_increment = true ) {
	echo bbp_get_tab_index( $auto_increment );
}

/**
 * Return the current tab index of a given form.
 *
 * Use this function to handle the tab indexing of user facing forms
 * within a template file. Calling this function will automatically
 * increment the global tab index by default.
 *
 * @since 2.0.0 bbPress (r2810)
 * @deprecated 2.6.0 bbPress (r5561)
 * @see https://bbpress.trac.wordpress.org/attachment/ticket/2714 Trac Ticket
 *
 * @param bool $auto_increment Optional. Default true. Set to false to
 *                            prevent the increment.
 *
 * @return int The global tab index.
 */
function bbp_get_tab_index( $auto_increment = true ) {
	$bbp = bbpress();

	if ( true === $auto_increment ) {
		++$bbp->tab_index;
	}

	/**
	 * Filters the current tab index of a given form.
	 *
	 * @since 2.0.0 bbPress (r2810)
	 *
	 * @param int $tab_index The global tab index.
	 */
	return apply_filters( 'bbp_get_tab_index', (int) $bbp->tab_index );
}

/**
 * Output a "tabindex" attribute for an element, if an index was passed.
 *
 * This helper function is in use, but it is generally considered impolite to
 * override the "tabindex" attribute beyond what the browser naturally assigns.
 *
 * Most internal usages pass `false` which results in no attribute being used.
 *
 * @since 2.6.0 bbPress (r6433)
 *
 * @param mixed $tab False to skip, any integer to use.
 * @return void
 */
function bbp_tab_index_attribute( $tab = false ) {
	echo bbp_get_tab_index_attribute( $tab );
}

/**
 * Return a "tabindex" attribute for an element, if an index was passed.
 *
 * This helper function is in use, but it is generally considered impolite to
 * override the "tabindex" attribute beyond what the browser naturally assigns.
 *
 * Most internal usages pass `false` which results in no attribute being used.
 *
 * @since 2.6.0 bbPress (r6433)
 *
 * @param mixed $tab False to skip, any integer to use.
 *
 * @return string
 */
function bbp_get_tab_index_attribute( $tab = false ) {

	// Get attribute
	$attr = is_numeric( $tab )
		? ' tabindex="' . (int) $tab . '"'
		: '';

	/**
	 * Filters a "tabindex" attribute for an element, if an index was passed.
	 *
	 * @since 2.6.0 bbPress (r6433)
	 *
	 * @param string $attr A "tabindex" attribute for an element, if an index was passed.
	 * @param mixed $tab False to skip, any integer to use.
	 */
	return apply_filters( 'bbp_get_tab_index_attribute', $attr, $tab );
}

/**
 * Output a select box allowing to pick which forum/topic a new topic/reply
 * belongs in.
 *
 * Can be used for any post type, but is mostly used for topics and forums.
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param array<string, mixed> $args See {@link bbp_get_dropdown()} for arguments.
 * @return void
 */
function bbp_dropdown( $args = array() ) {
	echo bbp_get_dropdown( $args );
}
/**
 * Return a select box allowing to pick which forum/topic a new
 * topic/reply belongs in.
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param array<string, mixed> $args Dropdown query and display settings. Supports post_type,
 *                    post_parent, post_status, selected, include, exclude,
 *                    numberposts, orderby, order, posts, walker, select_id,
 *                    select_class, tab, options_only, show_none,
 *                    disable_categories, and disabled.
 *
 * @return string The dropdown
 */
function bbp_get_dropdown( $args = array() ) {

	// Setup return value
	$retval = '';

	/** Arguments *************************************************************/

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(

			// Support for get_posts()
			'post_type'   => bbp_get_forum_post_type(),
			'post_parent' => null,
			'post_status' => null,
			'selected'    => 0,
			'include'     => array(),
			'exclude'     => array(),
			'numberposts' => -1,
			'orderby'     => 'menu_order title',
			'order'       => 'ASC',

			// Preloaded content
			'posts' => array(),

			// Custom hierarchy walkers
			'walker' => '',

			// Output-related
			'select_id'          => 'bbp_forum_id',
			'select_class'       => 'bbp_dropdown',
			'tab'                => false,
			'options_only'       => false,
			'show_none'          => false,
			'disable_categories' => true,
			'disabled'           => '',
		),
		'get_dropdown'
	);

	// Fallback to our walker
	if ( empty( $r['walker'] ) ) {
		$r['walker'] = new BBP_Walker_Dropdown();
		$r['walker']->tree_type = $r['post_type'];
	}

	// Force 0
	if ( is_numeric( $r['selected'] ) && ( $r['selected'] < 0 ) ) {
		$r['selected'] = 0;
	}

	// Force array
	if ( ! empty( $r['include'] ) && ! is_array( $r['include'] ) ) {
		$r['include'] = explode( ',', $r['include'] );
	}

	// Force array
	if ( ! empty( $r['exclude'] ) && ! is_array( $r['exclude'] ) ) {
		$r['exclude'] = explode( ',', $r['exclude'] );
	}

	/* Setup Posts ************************************************************/

	/*
	 * Allow passing of custom posts data
	 *
	 * @see bbp_get_reply_to_dropdown() as an example
	 */
	if ( empty( $r['posts'] ) ) {
		$r['posts'] = get_posts(
			array(
				'post_type'   => $r['post_type'],
				'post_status' => $r['post_status'],
				'post_parent' => $r['post_parent'],
				'include'     => $r['include'],
				'exclude'     => $r['exclude'],
				'numberposts' => $r['numberposts'],
				'orderby'     => $r['orderby'],
				'order'       => $r['order'],
			)
		);
	}

	/* Drop Down **************************************************************/

	// Build the opening tag for the select element
	if ( empty( $r['options_only'] ) ) {

		// Should this select appear disabled?
		$disabled = disabled( isset( bbpress()->options[ $r['disabled'] ] ), true, false );

		// Setup the tab index attribute
		$tab = ! empty( $r['tab'] )
			? ' tabindex="'. intval( $r['tab'] ) . '"'
			: '';

		// Open the select tag
		$retval .= '<select name="' . esc_attr( $r['select_id'] ) . '" id="' . esc_attr( $r['select_id'] ) . '" class="' . esc_attr( $r['select_class'] ) . '"' . $disabled . $tab . '>' . "\n";
	}

	// Display a leading 'no-value' option, with or without custom text
	if ( ! empty( $r['show_none'] ) || ! empty( $r['none_found'] ) ) {

		// Open the 'no-value' option tag
		$retval .= "\t<option value=\"\" class=\"level-0\">";

		// Use deprecated 'none_found' first for backpat
		if ( ! empty( $r['none_found'] ) && is_string( $r['none_found'] ) ) {
			$retval .= esc_html( $r['none_found'] );

		// Use 'show_none' second
		} elseif ( ! empty( $r['show_none'] ) && is_string( $r['show_none'] ) ) {
			$retval .= esc_html( $r['show_none'] );

		// Otherwise, make some educated guesses
		} else {

			// Switch the response based on post type
			switch ( $r['post_type'] ) {
				// Topics
				case bbp_get_topic_post_type():
					$retval .= esc_html__( 'No topics available', 'bbpress' );
					break;

				// Forums
				case bbp_get_forum_post_type():
					$retval .= esc_html__( 'No forums available', 'bbpress' );
					break;

				// Any other
				default:
					$retval .= esc_html__( 'None available', 'bbpress' );
					break;
			}
		}

		// Close the 'no-value' option tag
		$retval .= '</option>';
	}

	// Items found so walk the tree
	if ( ! empty( $r['posts'] ) ) {
		$retval .= walk_page_dropdown_tree( $r['posts'], 0, $r );
	}

	// Close the selecet tag
	if ( empty( $r['options_only'] ) ) {
		$retval .= '</select>';
	}

	/**
	 * Filters the forum or topic selection dropdown.
	 *
	 * @since 2.0.0 bbPress (r2746)
	 *
	 * @param string $retval Forum or topic dropdown markup.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_dropdown', $retval, $r, $args );
}

/**
 * Output the required hidden fields when creating/editing a forum.
 *
 * @since 2.1.0 bbPress (r3553)
 *
 * @return void
 */
function bbp_forum_form_fields() {
	if ( bbp_is_forum_edit() ) { ?>

		<input type="hidden" name="action"       id="bbp_post_action" value="bbp-edit-forum" />
		<input type="hidden" name="bbp_forum_id" id="bbp_forum_id"    value="<?php bbp_forum_id(); ?>" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) {
			wp_nonce_field( 'bbp-unfiltered-html-forum_' . bbp_get_forum_id(), '_bbp_unfiltered_html_forum', false );
		} ?>

		<?php wp_nonce_field( 'bbp-edit-forum_' . bbp_get_forum_id() );
	} else {
		if ( bbp_is_single_forum() ) { ?>

			<input type="hidden" name="bbp_forum_parent_id" id="bbp_forum_parent_id" value="<?php bbp_forum_parent_id(); ?>" />

		<?php } ?>

		<input type="hidden" name="action" id="bbp_post_action" value="bbp-new-forum" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) {
			wp_nonce_field( 'bbp-unfiltered-html-forum_new', '_bbp_unfiltered_html_forum', false );
		} ?>

		<?php wp_nonce_field( 'bbp-new-forum' );
	}
}

/**
 * Output the required hidden fields when creating/editing a topic.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @return void
 */
function bbp_topic_form_fields() {
	if ( bbp_is_topic_edit() ) { ?>

		<input type="hidden" name="action"       id="bbp_post_action" value="bbp-edit-topic" />
		<input type="hidden" name="bbp_topic_id" id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) {
			wp_nonce_field( 'bbp-unfiltered-html-topic_' . bbp_get_topic_id(), '_bbp_unfiltered_html_topic', false );
		} ?>

		<?php wp_nonce_field( 'bbp-edit-topic_' . bbp_get_topic_id() );
	} else {
		if ( bbp_is_single_forum() ) { ?>

			<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="<?php bbp_forum_id(); ?>" />

		<?php } ?>

		<input type="hidden" name="action" id="bbp_post_action" value="bbp-new-topic" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) {
			wp_nonce_field( 'bbp-unfiltered-html-topic_new', '_bbp_unfiltered_html_topic', false );
		} ?>

		<?php wp_nonce_field( 'bbp-new-topic' );
	}
}

/**
 * Output the required hidden fields when creating/editing a reply.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @return void
 */
function bbp_reply_form_fields() {
	if ( bbp_is_reply_edit() ) { ?>

		<input type="hidden" name="bbp_reply_id"    id="bbp_reply_id"    value="<?php bbp_reply_id(); ?>" />
		<input type="hidden" name="action"          id="bbp_post_action" value="bbp-edit-reply" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) {
			wp_nonce_field( 'bbp-unfiltered-html-reply_' . bbp_get_reply_id(), '_bbp_unfiltered_html_reply', false );
		} ?>

		<?php wp_nonce_field( 'bbp-edit-reply_' . bbp_get_reply_id() );
	} else { ?>

		<input type="hidden" name="bbp_topic_id"    id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />
		<input type="hidden" name="bbp_reply_to"    id="bbp_reply_to"    value="<?php bbp_form_reply_to(); ?>" />
		<input type="hidden" name="action"          id="bbp_post_action" value="bbp-new-reply" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) {
			wp_nonce_field( 'bbp-unfiltered-html-reply_' . bbp_get_topic_id(), '_bbp_unfiltered_html_reply', false );
		} ?>

		<?php wp_nonce_field( 'bbp-new-reply' );

		// Show redirect field if not viewing a specific topic
		if ( bbp_is_query_name( 'bbp_single_topic' ) ) {

			/**
			 * Filters the reply form redirect URL.
			 *
			 * @since 2.6.0 bbPress (r6728)
			 *
			 * @param mixed $value The reply form redirect URL, or false when unavailable.
			 */
			$redirect_to = apply_filters( 'bbp_reply_form_redirect_to', get_permalink() );
			bbp_redirect_to_field( $redirect_to );
		}
	}
}

/**
 * Output the required hidden fields when editing a user.
 *
 * @since 2.0.0 bbPress (r2690)
 *
 * @return void
 */
function bbp_edit_user_form_fields() {
?>

	<input type="hidden" name="action"  id="bbp_post_action" value="bbp-update-user" />
	<input type="hidden" name="user_id" id="user_id"         value="<?php bbp_displayed_user_id(); ?>" />

	<?php wp_nonce_field( 'update-user_' . bbp_get_displayed_user_id() );
}

/**
 * Merge topic form fields.
 *
 * Output the required hidden fields when merging a topic.
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @return void
 */
function bbp_merge_topic_form_fields() {
?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-merge-topic" />
	<input type="hidden" name="bbp_topic_id" id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />

	<?php wp_nonce_field( 'bbp-merge-topic_' . bbp_get_topic_id() );
}

/**
 * Split topic form fields.
 *
 * Output the required hidden fields when splitting a topic.
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @return void
 */
function bbp_split_topic_form_fields() {
?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-split-topic" />
	<input type="hidden" name="bbp_reply_id" id="bbp_reply_id"    value="<?php echo intval( $_GET['reply_id'] ); ?>" />

	<?php wp_nonce_field( 'bbp-split-topic_' . bbp_get_topic_id() );
}

/**
 * Move reply form fields.
 *
 * Output the required hidden fields when moving a reply.
 *
 * @since 2.3.0 bbPress (r4522)
 *
 * @return void
 */
function bbp_move_reply_form_fields() {
?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-move-reply" />
	<input type="hidden" name="bbp_reply_id" id="bbp_reply_id"    value="<?php echo intval( $_GET['reply_id'] ); ?>" />

	<?php wp_nonce_field( 'bbp-move-reply_' . bbp_get_reply_id() );
}

/**
 * Output a textarea or TinyMCE if enabled.
 *
 * @since 2.1.0 bbPress (r3586)
 *
 * @param array<string, mixed> $args Editor settings.
 * @return void
 */
function bbp_the_content( $args = array() ) {
	echo bbp_get_the_content( $args );
}

/**
 * Return a textarea or TinyMCE if enabled.
 *
 * @since 2.1.0 bbPress (r3586)
 *
 * @param array<string, mixed> $args Editor settings.
 *
 * @return string HTML from output buffer
 */
function bbp_get_the_content( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'context'           => 'topic',
			'before'            => '<div class="bbp-the-content-wrapper">',
			'after'             => '</div>',
			'wpautop'           => true,
			'media_buttons'     => false,
			'textarea_rows'     => '12',
			'tabindex'          => false,
			'tabfocus_elements' => 'bbp_topic_title,bbp_topic_tags',
			'editor_class'      => 'bbp-the-content',
			'tinymce'           => false,
			'teeny'             => true,
			'quicktags'         => true,
			'dfw'               => false,
		),
		'get_the_content'
	);

	// If using tinymce, remove our escaping and trust tinymce
	if ( bbp_use_wp_editor() && ( false !== $r['tinymce'] ) ) {
		remove_filter( 'bbp_get_form_forum_content', 'esc_textarea' );
		remove_filter( 'bbp_get_form_topic_content', 'esc_textarea' );
		remove_filter( 'bbp_get_form_reply_content', 'esc_textarea' );
	}

	// Assume we are not editing
	$content_callback = 'bbp_get_form_' . $r['context'] . '_content';
	$post_content     = is_callable( $content_callback ) ? call_user_func( $content_callback ) : '';

	// Start an output buffor
	ob_start();

	// Output something before the editor
	if ( ! empty( $r['before'] ) ) {
		echo $r['before'];
	}

	// Use TinyMCE if available
	if ( bbp_use_wp_editor() ) {

		// Enable additional TinyMCE plugins before outputting the editor
		add_filter( 'tiny_mce_plugins', 'bbp_get_tiny_mce_plugins' );
		add_filter( 'teeny_mce_plugins', 'bbp_get_tiny_mce_plugins' );
		add_filter( 'teeny_mce_buttons', 'bbp_get_teeny_mce_buttons' );
		add_filter( 'quicktags_settings', 'bbp_get_quicktags_settings' );

		// Output the editor
		wp_editor(
			$post_content,
			'bbp_' . $r['context'] . '_content',
			array(
				'wpautop'           => $r['wpautop'],
				'media_buttons'     => $r['media_buttons'],
				'textarea_rows'     => $r['textarea_rows'],
				'tabindex'          => $r['tabindex'],
				'tabfocus_elements' => $r['tabfocus_elements'],
				'editor_class'      => $r['editor_class'],
				'tinymce'           => $r['tinymce'],
				'teeny'             => $r['teeny'],
				'quicktags'         => $r['quicktags'],
				'dfw'               => $r['dfw'],
			)
		);

		// Remove additional TinyMCE plugins after outputting the editor
		remove_filter( 'tiny_mce_plugins', 'bbp_get_tiny_mce_plugins' );
		remove_filter( 'teeny_mce_plugins', 'bbp_get_tiny_mce_plugins' );
		remove_filter( 'teeny_mce_buttons', 'bbp_get_teeny_mce_buttons' );
		remove_filter( 'quicktags_settings', 'bbp_get_quicktags_settings' );

	/*
	 * Fallback to normal textarea.
	 *
	 * Note that we do not use esc_textarea() here to prevent double
	 * escaping the editable output, mucking up existing content.
	 */
	} else { ?>

		<textarea id="bbp_<?php echo esc_attr( $r['context'] ); ?>_content" class="<?php echo esc_attr( $r['editor_class'] ); ?>" name="bbp_<?php echo esc_attr( $r['context'] ); ?>_content" cols="60" rows="<?php echo esc_attr( $r['textarea_rows'] ); ?>" <?php bbp_tab_index_attribute( $r['tabindex'] ); ?>><?php echo $post_content; ?></textarea>

	<?php }

	// Output something after the editor
	if ( ! empty( $r['after'] ) ) {
		echo $r['after'];
	}

	// Put the output into a usable variable
	$output = ob_get_clean();

	/**
	 * Filters the content editor markup.
	 *
	 * @since 2.1.0 bbPress (r3586)
	 *
	 * @param mixed $output Content editor markup, or false if output buffering fails.
	 * @param array<string, mixed> $args Editor settings.
	 * @param string $post_content Post content.
	 */
	return apply_filters( 'bbp_get_the_content', $output, $args, $post_content );
}

/**
 * Edit TinyMCE plugins to match core behaviour.
 *
 * @since 2.3.0 bbPress (r4576)
 *
 * @param array<array-key, string> $plugins TinyMCE plugins.
 *
 * @see tiny_mce_plugins, teeny_mce_plugins
 *
 * @return array<array-key, string>
 */
function bbp_get_tiny_mce_plugins( $plugins = array() ) {

	// Unset fullscreen
	foreach ( $plugins as $key => $value ) {
		if ( 'fullscreen' === $value ) {
			unset( $plugins[ $key ] );
			break;
		}
	}

	// Add the tabfocus plugin
	$plugins[] = 'tabfocus';

	/**
	 * Filters the TinyMCE plugins used by the editor.
	 *
	 * @since 2.3.0 bbPress (r4576)
	 *
	 * @param array<array-key, string> $plugins The tinymce plugins used by the editor.
	 */
	return apply_filters( 'bbp_get_tiny_mce_plugins', $plugins );
}

/**
 * Edit TeenyMCE buttons to match allowedtags.
 *
 * @since 2.3.0 bbPress (r4606)
 *
 * @param array<array-key, string> $buttons TeenyMCE buttons.
 *
 * @see teeny_mce_buttons
 *
 * @return array<array-key, string>
 */
function bbp_get_teeny_mce_buttons( $buttons = array() ) {

	// Remove some buttons from TeenyMCE
	$buttons = array_diff(
		$buttons,
		array(
			'underline',
			'justifyleft',
			'justifycenter',
			'justifyright',
		)
	);

	// Images
	array_push( $buttons, 'image' );

	/**
	 * Filters the buttons in the compact TinyMCE editor.
	 *
	 * @since 2.3.0 bbPress (r4606)
	 *
	 * @param array<array-key, string> $buttons The buttons in the compact tinymce editor.
	 */
	return apply_filters( 'bbp_get_teeny_mce_buttons', $buttons );
}

/**
 * Edit TinyMCE quicktags buttons to match allowedtags.
 *
 * @since 2.3.0 bbPress (r4607)
 *
 * @param array<string, mixed> $settings Quicktags settings.
 *
 * @see quicktags_settings
 *
 * @return array<string, mixed> Quicktags settings.
 */
function bbp_get_quicktags_settings( $settings = array() ) {

	// Get buttons out of settings
	$buttons_array = explode( ',', $settings['buttons'] );

	// Diff the ones we don't want out
	$buttons = array_diff(
		$buttons_array,
		array(
			'ins',
			'more',
			'spell',
		)
	);

	// Put them back into a string in the $settings array
	$settings['buttons'] = implode( ',', $buttons );

	/**
	 * Filters the quicktags settings.
	 *
	 * @since 2.3.0 bbPress (r4607)
	 *
	 * @param array<string, mixed> $settings Quicktags settings.
	 */
	return apply_filters( 'bbp_get_quicktags_settings', $settings );
}

/** Views *********************************************************************/

/**
 * Output the view id.
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string|int|false $view Optional. View ID, or false to use the current view.
 * @return void
 */
function bbp_view_id( $view = '' ) {
	echo bbp_get_view_id( $view );
}

/**
 * Get the view id.
 *
 * Uses the supplied view ID, then the current view ID, then the view rewrite
 * query var.
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string|int|false $view Optional. View ID, or false to use the current view.
 *
 * @return string View ID, or empty string if none is set.
 */
function bbp_get_view_id( $view = '' ) {
	$bbp = bbpress();

	// User supplied string
	if ( ! empty( $view ) && ( is_string( $view ) || is_int( $view ) ) ) {
		$view_id = (string) $view;

	// Current view ID
	} elseif ( ! empty( $bbp->current_view_id ) ) {
		$view_id = $bbp->current_view_id;

	// Querying for view
	} else {
		$view_id = get_query_var( bbp_get_view_rewrite_id() );
	}

	// View IDs are strings.
	if ( is_int( $view_id ) ) {
		$view_id = (string) $view_id;
	} elseif ( ! is_string( $view_id ) ) {
		$view_id = '';
	}

	/**
	 * Filters the view id.
	 *
	 * @since 2.6.0 bbPress (r6930)
	 *
	 * @param string $view_id View ID, or empty string if none is set.
	 * @param string|int|false $view View ID, or false to use the current view.
	 */
	return apply_filters( 'bbp_get_view_id', $view_id, $view );
}

/**
 * Output the view name aka title.
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string|int $view Optional. View ID.
 * @return void
 */
function bbp_view_title( $view = '' ) {
	echo bbp_get_view_title( $view );
}

/**
 * Get the view name aka title.
 *
 * Uses the supplied view ID, then the current view ID, then the view rewrite
 * query var.
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string|int $view Optional. View ID.
 *
 * @return bool|string Title on success, false on failure.
 */
function bbp_get_view_title( $view = '' ) {
	$bbp = bbpress();

	$view = bbp_get_view_id( $view );
	if ( empty( $view ) || ! isset( $bbp->views[ $view ]['title'] ) ) {
		return false;
	}

	return $bbp->views[ $view ]['title'];
}

/**
 * Output the view url.
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string|int|false $view Optional. View ID.
 * @return void
 */
function bbp_view_url( $view = false ) {
	echo esc_url( bbp_get_view_url( $view ) );
}
/**
 * Return the view url.
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string|int|false $view Optional. View ID.
 *
 * @return string View URL, or home URL if no view resolves.
 */
function bbp_get_view_url( $view = false ) {

	$view = bbp_get_view_id( $view );
	if ( empty( $view ) ) {
		return home_url();
	}

	// Pretty permalinks
	if ( bbp_use_pretty_urls() ) {
		// Run through home_url()
		$url = trailingslashit( bbp_get_root_url() . bbp_get_view_slug() ) . $view;
		$url = user_trailingslashit( $url );
		$url = home_url( $url );

	// Unpretty permalinks
	} else {
		$url = add_query_arg(
			array(
				bbp_get_view_rewrite_id() => $view,
			),
			home_url( '/' )
		);
	}

	/**
	 * Filters the view url.
	 *
	 * @since 2.0.0 bbPress (r2789)
	 *
	 * @param string $url View URL, or home URL if no view resolves.
	 * @param string|int|false $view View ID.
	 */
	return apply_filters( 'bbp_get_view_link', $url, $view );
}

/** Query *********************************************************************/

/**
 * Check the passed parameter against the current _bbp_query_name.
 *
 * @since 2.0.0 bbPress (r2980)
 *
 * @param string $name Query name to compare.
 *
 * @return bool True if match, false if not.
 */
function bbp_is_query_name( $name = '' ) {
	return (bool) ( bbp_get_query_name() === $name );
}

/**
 * Get the '_bbp_query_name' setting.
 *
 * @since 2.0.0 bbPress (r2695)
 *
 * @return string To return the query var value.
 */
function bbp_get_query_name() {
	return get_query_var( '_bbp_query_name' );
}

/**
 * Set the '_bbp_query_name' setting to $name.
 *
 * @since 2.0.0 bbPress (r2692)
 *
 * @param string $name What to set the query var to.
 * @return void
 */
function bbp_set_query_name( $name = '' ) {
	set_query_var( '_bbp_query_name', $name );
}

/**
 * Used to clear the '_bbp_query_name' setting.
 *
 * @since 2.0.0 bbPress (r2692)
 *
 * @return void
 */
function bbp_reset_query_name() {
	bbp_set_query_name();
}

/** Breadcrumbs ***************************************************************/

/**
 * Output the page title as a breadcrumb.
 *
 * @since 2.0.0 bbPress (r2583)
 *
 * @param array<string, mixed> $args Breadcrumb settings.
 * @return void
 */
function bbp_title_breadcrumb( $args = array() ) {
	echo bbp_get_breadcrumb( $args );
}

/**
 * Output a breadcrumb.
 *
 * @since 2.0.0 bbPress (r2589)
 *
 * @param array<string, mixed> $args Breadcrumb settings.
 * @return void
 */
function bbp_breadcrumb( $args = array() ) {
	echo bbp_get_breadcrumb( $args );
}

/**
 * Return a breadcrumb ( forum -> topic -> reply )
 *
 * @since 2.0.0 bbPress (r2589)
 *
 * @param array<string, mixed> $args Breadcrumb settings.
 *
 * @return string|null Breadcrumb markup, or null when breadcrumbs are disabled.
 */
function bbp_get_breadcrumb( $args = array() ) {

	// Turn off breadcrumbs

	/**
	 * Filters whether to omit the breadcrumb on the front page.
	 *
	 * @since 2.0.0 bbPress (r3241)
	 *
	 * @param bool $omit Whether to omit the breadcrumb on the front page.
	 */
	if ( apply_filters( 'bbp_no_breadcrumb', is_front_page() ) ) {
		return null;
	}

	// Define variables
	$front_id         = $root_id          = $remove_root_id      = 0;
	$ancestors        = $crumbs           = $tag_data            = array();
	$pre_root_text    = $pre_front_text   = $pre_current_text    = '';
	$pre_include_root = $pre_include_home = $pre_include_current = true;

	/** Home Text *************************************************************/

	// No custom home text
	if ( empty( $args['home_text'] ) ) {

		$front_id = get_option( 'page_on_front' );

		// Set home text to page title
		if ( ! empty( $front_id ) ) {
			$pre_front_text = get_the_title( $front_id );

		// Default to 'Home'
		} else {
			$pre_front_text = esc_html__( 'Home', 'bbpress' );
		}
	}

	/** Root Text *************************************************************/

	// No custom root text
	if ( empty( $args['root_text'] ) ) {
		$page = bbp_get_page_by_path( bbp_get_root_slug() );
		if ( ! empty( $page ) ) {
			$root_id = $page->ID;
		}
		$pre_root_text = bbp_get_forum_archive_title();
	}

	/** Includes **************************************************************/

	// Root slug is also the front page
	if ( ! empty( $front_id ) && ( $front_id === $root_id ) ) {
		$pre_include_root = false;
	}

	// Don't show root if viewing forum archive
	if ( bbp_is_forum_archive() ) {
		$pre_include_root = false;
	}

	// Don't show root if viewing page in place of forum archive
	if ( ! empty( $root_id ) && ( ( is_single() || is_page() ) && ( get_the_ID() === $root_id ) ) ) {
		$pre_include_root = false;
	}

	/** Current Text **********************************************************/

	// Search page
	if ( bbp_is_search() ) {
		$pre_current_text = bbp_get_search_title();

	// Forum archive
	} elseif ( bbp_is_forum_archive() ) {
		$pre_current_text = bbp_get_forum_archive_title();

	// Topic archive
	} elseif ( bbp_is_topic_archive() ) {
		$pre_current_text = bbp_get_topic_archive_title();

	// View
	} elseif ( bbp_is_single_view() ) {
		$pre_current_text = bbp_get_view_title();

	// Single Forum
	} elseif ( bbp_is_single_forum() ) {
		$pre_current_text = bbp_get_forum_title();

	// Single Topic
	} elseif ( bbp_is_single_topic() ) {
		$pre_current_text = bbp_get_topic_title();

	// Single Topic
	} elseif ( bbp_is_single_reply() ) {
		$pre_current_text = bbp_get_reply_title();

	// Topic Tag (or theme compat topic tag)
	} elseif ( bbp_is_topic_tag() || ( get_query_var( 'bbp_topic_tag' ) && ! bbp_is_topic_tag_edit() ) ) {

		// Always include the tag name
		$tag_data[] = bbp_get_topic_tag_name();

		// If capable, include a link to edit the tag
		if ( current_user_can( 'manage_topic_tags' ) ) {
			$tag_data[] = '<a href="' . esc_url( bbp_get_topic_tag_edit_link() ) . '" class="bbp-edit-topic-tag-link">' . esc_html__( '(Edit)', 'bbpress' ) . '</a>';
		}

		// Implode the results of the tag data
		/* translators: %s: Topic tag data */
		$pre_current_text = sprintf( esc_html__( 'Topic Tag: %s', 'bbpress' ), implode( ' ', $tag_data ) );

	// Edit Topic Tag
	} elseif ( bbp_is_topic_tag_edit() ) {
		$pre_current_text = esc_html__( 'Edit', 'bbpress' );

	// Single
	} else {
		$pre_current_text = get_the_title();
	}

	/** Parse Args ************************************************************/

	// Parse args
	$r = bbp_parse_args(
		$args,
		array(

			// HTML
			'before'          => '<div class="bbp-breadcrumb"><p>',
			'after'           => '</p></div>',

			// Separator
			'sep'             => is_rtl() ? __( '&lsaquo;', 'bbpress' ) : __( '&rsaquo;', 'bbpress' ),
			'pad_sep'         => 1,
			'sep_before'      => '<span class="bbp-breadcrumb-sep">',
			'sep_after'       => '</span>',

			// Crumbs
			'crumb_before'    => '',
			'crumb_after'     => '',

			// Home
			'include_home'    => $pre_include_home,
			'home_text'       => $pre_front_text,

			// Forum root
			'include_root'    => $pre_include_root,
			'root_text'       => $pre_root_text,

			// Current
			'include_current' => $pre_include_current,
			'current_text'    => $pre_current_text,
			'current_before'  => '<span class="bbp-breadcrumb-current">',
			'current_after'   => '</span>',
		),
		'get_breadcrumb'
	);

	/** Ancestors *********************************************************/

	// Get post ancestors
	if ( is_singular() || bbp_is_forum_edit() || bbp_is_topic_edit() || bbp_is_reply_edit() ) {
		$post_id   = get_the_ID();
		$ancestors = $post_id ? array_reverse( (array) get_post_ancestors( $post_id ) ) : array();
	}

	// Do we want to include a link to home?
	if ( ! empty( $r['include_home'] ) || empty( $r['home_text'] ) ) {
		$crumbs[] = '<a href="' . esc_url( home_url() ) . '" class="bbp-breadcrumb-home">' . $r['home_text'] . '</a>';
	}

	// Do we want to include a link to the forum root?
	if ( ! empty( $r['include_root'] ) || empty( $r['root_text'] ) ) {

		// Page exists at root slug path, so use its permalink
		$page = bbp_get_page_by_path( bbp_get_root_slug() );
		if ( ! empty( $page ) ) {
			$root_url       = get_permalink( $page->ID );
			$remove_root_id = $page->ID;

		// Use the root slug
		} else {
			$root_url = get_post_type_archive_link( bbp_get_forum_post_type() );
		}

		// Add the breadcrumb
		$crumbs[] = '<a href="' . esc_url( $root_url ) . '" class="bbp-breadcrumb-root">' . $r['root_text'] . '</a>';
	}

	// Ancestors exist
	if ( ! empty( $ancestors ) ) {

		// Loop through parents
		foreach ( (array) $ancestors as $parent_id ) {

			// Parents
			$parent = get_post( $parent_id );

			// Skip parent if empty
			if ( empty( $parent ) ) {
				continue;
			}

			// Skip to prevent duplicate root on subpages
			if ( ! empty( $remove_root_id ) && ( $parent->ID === $remove_root_id ) ) {
				continue;
			}

			// Switch through post_type to ensure correct filters are applied
			switch ( $parent->post_type ) {

				// Forum
				case bbp_get_forum_post_type() :
					$crumbs[] = '<a href="' . esc_url( bbp_get_forum_permalink( $parent->ID ) ) . '" class="bbp-breadcrumb-forum">' . bbp_get_forum_title( $parent->ID ) . '</a>';
					break;

				// Topic
				case bbp_get_topic_post_type() :
					$crumbs[] = '<a href="' . esc_url( bbp_get_topic_permalink( $parent->ID ) ) . '" class="bbp-breadcrumb-topic">' . bbp_get_topic_title( $parent->ID ) . '</a>';
					break;

				// Reply (Note: not in most themes )
				case bbp_get_reply_post_type() :
					$crumbs[] = '<a href="' . esc_url( bbp_get_reply_permalink( $parent->ID ) ) . '" class="bbp-breadcrumb-reply">' . bbp_get_reply_title( $parent->ID ) . '</a>';
					break;

				// WordPress Post/Page/Other
				default :
					$crumbs[] = '<a href="' . esc_url( get_permalink( $parent->ID ) ) . '" class="bbp-breadcrumb-item">' . get_the_title( $parent->ID ) . '</a>';
					break;
			}
		}

	// Edit topic tag
	} elseif ( bbp_is_topic_tag_edit() ) {
		$term_link = get_term_link( bbp_get_topic_tag_id(), bbp_get_topic_tag_tax_id() );
		/* translators: %s: Topic Tag name */
		$term_name = sprintf( esc_html__( 'Topic Tag: %s', 'bbpress' ), bbp_get_topic_tag_name() );
		$crumbs[]  = is_wp_error( $term_link )
			? $term_name
			: '<a href="' . esc_url( $term_link ) . '" class="bbp-breadcrumb-topic-tag">' . $term_name . '</a>';

	// Search
	} elseif ( bbp_is_search() && bbp_get_search_terms() ) {
		$crumbs[] = '<a href="' . esc_url( bbp_get_search_url() ) . '" class="bbp-breadcrumb-search">' . esc_html__( 'Search', 'bbpress' ) . '</a>';
	}

	/** Current ***********************************************************/

	// Add current page to breadcrumb
	if ( ! empty( $r['include_current'] ) || empty( $r['current_text'] ) ) {
		$crumbs[] = $r['current_before'] . $r['current_text'] . $r['current_after'];
	}

	/** Separator *********************************************************/

	// Wrap the separator in before/after before padding and filter
	if ( ! empty( $r['sep'] ) ) {
		$sep = $r['sep_before'] . $r['sep'] . $r['sep_after'];
	} else {
		$sep = '';
	}

	// Pad the separator
	if ( ! empty( $r['pad_sep'] ) ) {
		if ( function_exists( 'mb_strlen' ) ) {
			$sep = str_pad( $sep, mb_strlen( $sep ) + ( (int) $r['pad_sep'] * 2 ), ' ', STR_PAD_BOTH );
		} else {
			$sep = str_pad( $sep, strlen( $sep ) + ( (int) $r['pad_sep'] * 2 ), ' ', STR_PAD_BOTH );
		}
	}

	/** Finish Up *********************************************************/

	// Filter the separator and breadcrumb

	/**
	 * Filters the separator used between breadcrumb items.
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @param string $sep The separator used between breadcrumb items.
	 */
	$sep    = apply_filters( 'bbp_breadcrumb_separator', $sep    );

	/**
	 * Filters the breadcrumb items before rendering them.
	 *
	 * @since 2.0.0 bbPress (r3241)
	 *
	 * @param array $crumbs The breadcrumb items before rendering them.
	 */
	$crumbs = apply_filters( 'bbp_breadcrumbs',          $crumbs );

	// Build the trail
	$trail  = ! empty( $crumbs )
		? ( $r['before'] . $r['crumb_before'] . implode( $sep . $r['crumb_after'] . $r['crumb_before'], $crumbs ) . $r['crumb_after'] . $r['after'] )
		: '';

	/**
	 * Filters a breadcrumb ( forum -> topic -> reply ).
	 *
	 * @since 2.0.0 bbPress (r2589)
	 *
	 * @param string|null $trail Breadcrumb markup, or null when breadcrumbs are disabled.
	 * @param array<int, string> $crumbs Breadcrumb items.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Breadcrumb settings.
	 */
	return apply_filters( 'bbp_get_breadcrumb', $trail, $crumbs, $r, $args );
}

/** Topic Tags ***************************************************************/

/**
 * Output all of the allowed tags in HTML format with attributes.
 *
 * This is useful for displaying in the post area, which elements and
 * attributes are supported. As well as any plugins which want to display it.
 *
 * @since 2.0.0 bbPress (r2780)
 *
 * @return void
 */
function bbp_allowed_tags() {
	echo bbp_get_allowed_tags();
}

/**
 * Display all of the allowed tags in HTML format with attributes.
 *
 * This is useful for displaying in the post area, which elements and
 * attributes are supported. As well as any plugins which want to display it.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @return string HTML allowed tags entity encoded.
 */
function bbp_get_allowed_tags() {
	$allowed = '';

	foreach ( (array) bbp_kses_allowed_tags() as $tag => $attributes ) {
		$allowed .= '<' . $tag;

		if ( 0 < count( $attributes ) ) {
			foreach ( array_keys( $attributes ) as $attribute ) {
				$allowed .= ' ' . $attribute. '=""';
			}
		}

		$allowed .= '> ';
	}

	/**
	 * Filters the HTML representation of allowed tags.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param string $value HTML allowed tags entity encoded.
	 */
	return apply_filters( 'bbp_get_allowed_tags', htmlentities( $allowed ) );
}

/** Errors & Messages *********************************************************/

/**
 * Return the template notice context for the current POST action.
 *
 * @since 2.6.20 bbPress (r7969)
 *
 * @return string Template notice context.
 */
function bbp_get_template_notice_context() {

	// Bail if not a POST request
	if ( ! bbp_is_post_request() ) {
		return '';
	}

	// Map form actions to their notice contexts
	$action   = bbp_get_post_action();
	$contexts = array(
		'bbp-new-forum'  => 'forum',
		'bbp-edit-forum' => 'forum',
		'bbp-new-topic'  => 'topic',
		'bbp-edit-topic' => 'topic',
		'bbp-new-reply'  => 'reply',
		'bbp-edit-reply' => 'reply',
	);
	$context  = isset( $contexts[ $action ] )
		? $contexts[ $action ]
		: '';

	/**
	 * Filters the template notice context for the current POST action.
	 *
	 * @since 2.6.20 bbPress (r7968)
	 *
	 * @param string $context Template notice context.
	 * @param string $action Current action.
	 */
	return apply_filters( 'bbp_get_template_notice_context', $context, $action );
}

/**
 * Return true|false if notices should display in one of the given contexts.
 *
 * @since 2.6.20 bbPress (r7969)
 *
 * @param string|array<int, string> $context Context or contexts to check.
 * @return bool
 */
function bbp_is_template_notice_context( $context = '' ) {
	$notice_context = bbp_get_template_notice_context();

	return empty( $context )
		|| empty( $notice_context )
		|| in_array( $notice_context, (array) $context, true );
}

/**
 * Display possible errors & messages inside a template file.
 *
 * @since 2.0.0 bbPress (r2970)
 * @since 2.6.20 bbPress (r7969) Added the `$context` parameter.
 *
 * @param string $context Optional. Form context. Accepts 'forum', 'topic', or
 *                        'reply'. Default empty.
 * @return void
 */
function bbp_template_notices( $context = '' ) {

	// Bail if no notices or errors
	if ( ! bbp_has_errors() ) {
		return;
	}

	// Keep form submission notices in the form that handled the request
	if ( ! bbp_is_template_notice_context( $context ) ) {
		return;
	}

	// Define local variable(s )
	$errors = $messages = array();

	// Get bbPress
	$bbp = bbpress();

	// Loop through notices
	foreach ( $bbp->errors->get_error_codes() as $code ) {

		// Get notice severity
		$severity = $bbp->errors->get_error_data( $code );

		// Loop through notices and separate errors from messages
		foreach ( $bbp->errors->get_error_messages( $code ) as $error ) {
			if ( 'message' === $severity ) {
				$messages[] = $error;
			} else {
				$errors[] = $error;
			}
		}
	}

	// Display errors first...
	if ( ! empty( $errors ) ) { ?>

		<div class="bbp-template-notice error" role="alert" tabindex="-1">
			<ul>
				<li><?php echo implode( "</li>\n<li>", $errors ); ?></li>
			</ul>
		</div>

	<?php }

	// ...and messages last
	if ( ! empty( $messages ) ) { ?>

		<div class="bbp-template-notice">
			<ul>
				<li><?php echo implode( "</li>\n<li>", $messages ); ?></li>
			</ul>
		</div>

	<?php

	}
}

/** Login/logout/register/lost pass *******************************************/

/**
 * Output the logout link.
 *
 * @since 2.0.0 bbPress (r2827)
 * @since 2.7.0 bbPress (r7975) Added the `$classes` parameter.
 *
 * @param string $redirect_to Redirect to url.
 * @param array<int, string> $classes Classes to add to the logout link.
 * @return void
 */
function bbp_logout_link( $redirect_to = '', $classes = array( 'button', 'logout-link' ) ) {
	echo bbp_get_logout_link( $redirect_to, $classes );
}

/**
 * Return the logout link.
 *
 * @since 2.0.0 bbPress (r2827)
 * @since 2.7.0 bbPress (r7975) Added the `$classes` parameter.
 *
 * @param string $redirect_to Redirect to url.
 * @param array<int, string> $classes Classes to add to the logout link.
 *
 * @return string The logout link.
 */
function bbp_get_logout_link( $redirect_to = '', $classes = array( 'button', 'logout-link' ) ) {

	// Sanitize classes
	$classes = implode( ' ', array_map( 'sanitize_html_class', $classes ) );

	// Build the link
	$link = '<a href="' . wp_logout_url( $redirect_to ) . '" class="' . esc_attr( $classes ) . '">' . esc_html__( 'Log Out', 'bbpress' ) . '</a>';

	/**
	 * Filters the logout link.
	 *
	 * @since 2.0.0 bbPress (r2827)
	 *
	 * @param string $link The logout link.
	 * @param string $redirect_to Redirect to url.
	 * @param string $classes Space-separated classes on the logout link.
	 */
	return apply_filters( 'bbp_get_logout_link', $link, $redirect_to, $classes );
}

/** Title *********************************************************************/

/**
 * Custom page title for bbPress pages.
 *
 * @since 2.0.0 bbPress (r2788)
 *
 * @param string $title       Optional. The title (not used).
 * @param string $sep         Optional, default is '&raquo;'. How to separate the
 *                            various items within the page title.
 * @param string $seplocation Optional. Direction to display title, 'right'.
 *                            separator and separator location.
 *
 * @return string The title.
 */
function bbp_title( $title = '', $sep = '&raquo;', $seplocation = '' ) {

	// Title array
	$new_title = array();

	/* Subscription Unsubscribe **********************************************/

	if ( bbp_is_subscription_unsubscribe() ) {
		$new_title['text'] = bbp_get_subscription_unsubscribe_title();

	/* Archives ************************************************************* */
	} elseif ( bbp_is_forum_archive() ) {
		$new_title['text'] = bbp_get_forum_archive_title();

	// Topic Archive
	} elseif ( bbp_is_topic_archive() ) {
		$new_title['text'] = bbp_get_topic_archive_title();

	/* Edit ***************************************************************** */

	// Forum edit page
	} elseif ( bbp_is_forum_edit() ) {
		$new_title['text'] = bbp_get_forum_title();
		/* translators: %s: Forum title */
		$new_title['format'] = esc_attr__( 'Forum Edit: %s', 'bbpress' );

	// Topic edit page
	} elseif ( bbp_is_topic_edit() ) {
		$new_title['text'] = bbp_get_topic_title();
		/* translators: %s: Topic title */
		$new_title['format'] = esc_attr__( 'Topic Edit: %s', 'bbpress' );

	// Reply edit page
	} elseif ( bbp_is_reply_edit() ) {
		$new_title['text'] = bbp_get_reply_title();
		/* translators: %s: Reply title */
		$new_title['format'] = esc_attr__( 'Reply Edit: %s', 'bbpress' );

	// Topic tag edit page
	} elseif ( bbp_is_topic_tag_edit() ) {
		$new_title['text'] = bbp_get_topic_tag_name();
		/* translators: %s: Topic tag name */
		$new_title['format'] = esc_attr__( 'Topic Tag Edit: %s', 'bbpress' );

	/* Singles ************************************************************** */

	// Forum page
	} elseif ( bbp_is_single_forum() ) {
		$new_title['text'] = bbp_get_forum_title();
		/* translators: %s: Forum title */
		$new_title['format'] = esc_attr__( 'Forum: %s', 'bbpress' );

	// Topic page
	} elseif ( bbp_is_single_topic() ) {
		$new_title['text'] = bbp_get_topic_title();
		/* translators: %s: Topic title */
		$new_title['format'] = esc_attr__( 'Topic: %s', 'bbpress' );

	// Replies
	} elseif ( bbp_is_single_reply() ) {
		$new_title['text'] = bbp_get_reply_title();

	// Topic tag page
	} elseif ( bbp_is_topic_tag() || get_query_var( 'bbp_topic_tag' ) ) {
		$new_title['text'] = bbp_get_topic_tag_name();
		/* translators: %s: Topic tag name */
		$new_title['format'] = esc_attr__( 'Topic Tag: %s', 'bbpress' );

	/* Users **************************************************************** */

	// Profile page
	} elseif ( bbp_is_single_user() ) {
		// Is user viewing their own profile?
		$is_user_home = bbp_is_user_home();
		$user         = ( true === $is_user_home ) ? false : get_userdata( bbp_get_user_id() );

		// User mention history
		if ( bbp_is_single_user_mentions() ) {
			$new_title['text'] = esc_attr__( 'Mentions', 'bbpress' );

		// User topics created
		} elseif ( bbp_is_single_user_topics() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Topics', 'bbpress' );
			} elseif ( $user instanceof WP_User ) {
				$new_title['text'] = $user->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Topics", 'bbpress' );
			}

		// User replies created
		} elseif ( bbp_is_single_user_replies() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Replies', 'bbpress' );
			} elseif ( $user instanceof WP_User ) {
				$new_title['text'] = $user->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Replies", 'bbpress' );
			}

		// User favorites
		} elseif ( bbp_is_favorites() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Favorites', 'bbpress' );
			} elseif ( $user instanceof WP_User ) {
				$new_title['text'] = $user->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Favorites", 'bbpress' );
			}

		// User subscriptions
		} elseif ( bbp_is_subscriptions() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Subscriptions', 'bbpress' );
			} elseif ( $user instanceof WP_User ) {
				$new_title['text'] = $user->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Subscriptions", 'bbpress' );
			}

		// User "home"
		// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found
		} else {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Profile', 'bbpress' );
			} elseif ( $user instanceof WP_User ) {
				$new_title['text'] = $user->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Profile", 'bbpress' );
			}
		}

	// Profile edit page
	} elseif ( bbp_is_single_user_edit() ) {
		// Current user
		if ( bbp_is_user_home_edit() ) {
			$new_title['text'] = esc_attr__( 'Edit Your Profile', 'bbpress' );

		// Other user
		} else {
			$user = get_userdata( bbp_get_user_id() );
			if ( $user instanceof WP_User ) {
				$new_title['text'] = $user->display_name;
				/* translators: %s: User's display name */
				$new_title['format'] = esc_attr__( "Edit %s's Profile", 'bbpress' );
			}
		}

	/* Views **************************************************************** */

	// Views
	} elseif ( bbp_is_single_view() ) {
		$new_title['text'] = bbp_get_view_title();
		/* translators: %s: View title */
		$new_title['format'] = esc_attr__( 'View: %s', 'bbpress' );

	/* Search *************************************************************** */

	// Search
	} elseif ( bbp_is_search() ) {
		$new_title['text'] = bbp_get_search_title();
	}

	// This filter is deprecated. Use 'bbp_before_title_parse_args' instead.

	/**
	 * Filters the unformatted title components.
	 *
	 * @since 2.4.0 bbPress (r5018)
	 *
	 * @param array $new_title The unformatted title components.
	 */
	$new_title = apply_filters( 'bbp_raw_title_array', $new_title );

	// Set title array defaults
	$new_title = bbp_parse_args(
		$new_title,
		array(
			'text'   => $title,
			'format' => '%s',
		),
		'title'
	);

	// Get the formatted raw title
	$new_title = sprintf( $new_title['format'], $new_title['text'] );

	// Filter the raw title

	/**
	 * Filters the unformatted page title.
	 *
	 * @since 2.0.0 bbPress (r2788)
	 *
	 * @param string $new_title The unformatted page title.
	 * @param string $sep Sep.
	 * @param string $seplocation Seplocation.
	 */
	$new_title = apply_filters( 'bbp_raw_title', $new_title, $sep, $seplocation );

	// Compare new title with original title
	if ( $new_title === $title ) {
		return $title;
	}

	// Temporary separator, for accurate flipping, if necessary
	$t_sep  = '%WP_TITILE_SEP%';
	$prefix = '';

	if ( ! empty( $new_title ) ) {
		$prefix = " $sep ";
	}

	// sep on right, so reverse the order
	if ( 'right' === $seplocation ) {
		$new_title_array = array_reverse( explode( $t_sep, $new_title ) );
		$new_title       = implode( " $sep ", $new_title_array ) . $prefix;

	// sep on left, do not reverse
	} else {
		$new_title_array = explode( $t_sep, $new_title );
		$new_title       = $prefix . implode( " $sep ", $new_title_array );
	}

	/**
	 * Filters the title.
	 *
	 * @since 2.0.0 bbPress (r2788)
	 *
	 * @param string $new_title The title.
	 * @param string $sep Optional, default is '&raquo;'. How to separate the various items within the page title.
	 * @param string $seplocation Direction to display title, 'right'. separator and separator location.
	 */
	return apply_filters( 'bbp_title', $new_title, $sep, $seplocation );
}

/**
 * Removes Protected/Private post title hints.
 *
 * This function is hooked to 2 WordPress filters that are responsible for
 * prepending hints to the beginning of Protected & Private post titles.
 *
 * These hints are a bit unsightly when used in functions like
 * bbp_get_breadcrumb(), so we strip them back out for bbPress post types.
 *
 * @since 2.7.0 bbPress (r7276)
 *
 * @param string      $prepend text displayed before a post title.
 * @param int|WP_Post $post    current post object.
 *
 * @return string
 */
function bbp_no_title_status_hints( $prepend = '', $post = 0 ) {

	// Bail if empty
	if ( empty( $prepend ) || empty( $post ) ) {
		return $prepend;
	}

	// Get post type
	$post_type = get_post_type( $post );

	// Maybe override return value
	$retval = in_array( $post_type, bbp_get_post_types(), true )
		? '%s'
		: $prepend;

	/**
	 * Filters the status hints shown when a title is absent.
	 *
	 * @since 2.7.0 bbPress (r7276)
	 *
	 * @param string $retval The status hints shown when a title is absent.
	 * @param string $prepend text displayed before a post title.
	 * @param int|WP_Post $post current post object.
	 */
	return (string) apply_filters( 'bbp_no_special_title_formatting', $retval, $prepend, $post );
}
