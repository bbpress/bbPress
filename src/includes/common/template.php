<?php

/**
 * bbPress Common Template Tags
 *
 * Common template tags are ones that are used by more than one component, like
 * forums, topics, replies, users, topic tags, etc...
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** URLs **********************************************************************/

/**
 * Output the forum URL
 *
 * @since 2.1.0 bbPress (r3979)
 *
 * @param string $path Additional path with leading slash
 */
function bbp_forums_url( $path = '/' ) {
	echo esc_url( bbp_get_forums_url( $path ) );
}
	/**
	 * Return the forum URL
	 *
	 * @since 2.1.0 bbPress (r3979)
	 *
	 * @param string $path Additional path with leading slash
	 */
	function bbp_get_forums_url( $path = '/' ) {
		return home_url( bbp_get_root_slug() . $path );
	}

/**
 * Output the forum URL
 *
 * @since 2.1.0 bbPress (r3979)
 *
 * @param string $path Additional path with leading slash
 */
function bbp_topics_url( $path = '/' ) {
	echo esc_url( bbp_get_topics_url( $path ) );
}
	/**
	 * Return the forum URL
	 *
	 * @since 2.1.0 bbPress (r3979)
	 *
	 * @param string $path Additional path with leading slash
	 * @return The URL to the topics archive
	 */
	function bbp_get_topics_url( $path = '/' ) {
		return home_url( bbp_get_topic_archive_slug() . $path );
	}

/** Add-on Actions ************************************************************/

/**
 * Add our custom head action to wp_head
 *
 * @since 2.0.0 bbPress (r2464)
 */
function bbp_head() {
	do_action( 'bbp_head' );
}

/**
 * Add our custom head action to wp_head
 *
 * @since 2.0.0 bbPress (r2464)
 */
function bbp_footer() {
	do_action( 'bbp_footer' );
}

/** is_ ***********************************************************************/

/**
 * Check if current site is public
 *
 * @since 2.0.0 bbPress (r3398)
 *
 * @param int $site_id
 * @return bool True if site is public, false if private
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

	// Filter & return
	return (bool) apply_filters( 'bbp_is_site_public', $public, $site_id );
}

/**
 * Check if current page is a bbPress forum
 *
 * @since 2.0.0 bbPress (r2549)
 *
 * @param int $post_id Possible post_id to check
 * @return bool True if it's a forum page, false if not
 */
function bbp_is_forum( $post_id = 0 ) {

	// Assume false
	$retval = false;

	// Supplied ID is a forum
	if ( ! empty( $post_id ) && ( bbp_get_forum_post_type() === get_post_type( $post_id ) ) ) {
		$retval = true;
	}

	// Filter & return
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

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_archive', $retval );
}

/**
 * Viewing a single forum
 *
 * @since 2.0.0 bbPress (r3338)
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

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_forum', $retval );
}

/**
 * Check if current page is a forum edit page
 *
 * @since 2.1.0 bbPress (r3553)
 *
 * @return bool True if it's the forum edit page, false if not
 */
function bbp_is_forum_edit() {
	global $pagenow;

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_forum_edit ) && ( $wp_query->bbp_is_forum_edit === true ) ) {
		$retval = true;

	// Editing in admin
	} elseif ( is_admin() && ( 'post.php' === $pagenow ) && ( get_post_type() === bbp_get_forum_post_type() ) && ( ! empty( $_GET['action'] ) && ( 'edit' === $_GET['action'] ) ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_forum_edit', $retval );
}

/**
 * Check if current page is a bbPress topic
 *
 * @since 2.0.0 bbPress (r2549)
 *
 * @param int $post_id Possible post_id to check
 * @return bool True if it's a topic page, false if not
 */
function bbp_is_topic( $post_id = 0 ) {

	// Assume false
	$retval = false;

	// Supplied ID is a topic
	if ( ! empty( $post_id ) && ( bbp_get_topic_post_type() === get_post_type( $post_id ) ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic', $retval, $post_id );
}

/**
 * Viewing a single topic
 *
 * @since 2.0.0 bbPress (r3338)
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

	// Filter & return
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

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_archive', $retval );
}

/**
 * Check if current page is a topic edit page
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @return bool True if it's the topic edit page, false if not
 */
function bbp_is_topic_edit() {
	global $pagenow;

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_topic_edit ) && ( $wp_query->bbp_is_topic_edit === true ) ) {
		$retval = true;

	// Editing in admin
	} elseif ( is_admin() && ( 'post.php' === $pagenow ) && ( get_post_type() === bbp_get_topic_post_type() ) && ( ! empty( $_GET['action'] ) && ( 'edit' === $_GET['action'] ) ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_edit', $retval );
}

/**
 * Check if current page is a topic merge page
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @return bool True if it's the topic merge page, false if not
 */
function bbp_is_topic_merge() {

	// Assume false
	$retval = false;

	// Check topic edit and GET params
	if ( bbp_is_topic_edit() && ! empty( $_GET['action'] ) && ( 'merge' === $_GET['action'] ) ) {
		return true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_merge', $retval );
}

/**
 * Check if current page is a topic split page
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @return bool True if it's the topic split page, false if not
 */
function bbp_is_topic_split() {

	// Assume false
	$retval = false;

	// Check topic edit and GET params
	if ( bbp_is_topic_edit() && ! empty( $_GET['action'] ) && ( 'split' === $_GET['action'] ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_split', $retval );
}

/**
 * Check if the current page is a topic tag
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return bool True if it's a topic tag, false if not
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

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_tag', $retval );
}

/**
 * Check if the current page is editing a topic tag
 *
 * @since 2.0.0 bbPress (r3346)
 *
 * @return bool True if editing a topic tag, false if not
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
	if ( ! empty( $wp_query->bbp_is_topic_tag_edit ) && ( true === $wp_query->bbp_is_topic_tag_edit ) ) {
		$retval = true;

	// Editing in admin
	} elseif ( is_admin() && ( 'edit-tags.php' === $pagenow ) && ( bbp_get_topic_tag_tax_id() === $taxnow ) && ( ! empty( $_GET['action'] ) && ( 'edit' === $_GET['action'] ) ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_tag_edit', $retval );
}

/**
 * Check if the current post type is one that comes with bbPress
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @param mixed $the_post Optional. Post object or post ID.
 *
 * @return bool
 */
function bbp_is_custom_post_type( $the_post = false ) {

	// Assume false
	$retval = false;

	// Viewing one of the bbPress post types
	if ( in_array( get_post_type( $the_post ), array(
		bbp_get_forum_post_type(),
		bbp_get_topic_post_type(),
		bbp_get_reply_post_type()
	), true ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_custom_post_type', $retval, $the_post );
}

/**
 * Check if current page is a bbPress reply
 *
 * @since 2.0.0 bbPress (r2549)
 *
 * @param int $post_id Possible post_id to check
 * @return bool True if it's a reply page, false if not
 */
function bbp_is_reply( $post_id = 0 ) {

	// Assume false
	$retval = false;

	// Supplied ID is a reply
	if ( ! empty( $post_id ) && ( bbp_get_reply_post_type() === get_post_type( $post_id ) ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply', $retval, $post_id );
}

/**
 * Check if current page is a reply edit page
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @return bool True if it's the reply edit page, false if not
 */
function bbp_is_reply_edit() {
	global $pagenow;

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_reply_edit ) && ( true === $wp_query->bbp_is_reply_edit ) ) {
		$retval = true;

	// Editing in admin
	} elseif ( is_admin() && ( 'post.php' === $pagenow ) && ( get_post_type() === bbp_get_reply_post_type() ) && ( ! empty( $_GET['action'] ) && ( 'edit' === $_GET['action'] ) ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_edit', $retval );
}

/**
 * Check if current page is a reply move page
 *
 * @return bool True if it's the reply move page, false if not
 */
function bbp_is_reply_move() {

	// Assume false
	$retval = false;

	// Check reply edit and GET params
	if ( bbp_is_reply_edit() && ! empty( $_GET['action'] ) && ( 'move' === $_GET['action'] ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_move', $retval );
}

/**
 * Viewing a single reply
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
	if ( is_singular( bbp_get_reply_post_type() ) || ( bbp_is_query_name( 'bbp_single_reply' ) ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_reply', $retval );
}

/**
 * Check if current page is a bbPress user's favorites page (profile page)
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @return bool True if it's the favorites page, false if not
 */
function bbp_is_favorites() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_favs ) && ( true === $wp_query->bbp_is_single_user_favs ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_favorites', $retval );
}

/**
 * Check if current page is a bbPress user's subscriptions page (profile page)
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @return bool True if it's the subscriptions page, false if not
 */
function bbp_is_subscriptions() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_subs ) && ( true === $wp_query->bbp_is_single_user_subs ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_subscriptions', $retval );
}

/**
 * Check if current page shows the topics created by a bbPress user (profile
 * page)
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return bool True if it's the topics created page, false if not
 */
function bbp_is_topics_created() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_topics ) && ( true === $wp_query->bbp_is_single_user_topics ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topics_created', $retval );
}

/**
 * Check if current page shows the replies created by a bbPress user (profile
 * page)
 *
 * @since 2.2.0 bbPress (r4225)
 *
 * @return bool True if it's the replies created page, false if not
 */
function bbp_is_replies_created() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_replies ) && ( true === $wp_query->bbp_is_single_user_replies ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_replies_created', $retval );
}

/**
 * Check if current page is the currently logged in users author page
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return bool True if it's the user's home, false if not
 */
function bbp_is_user_home() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_home ) && ( true === $wp_query->bbp_is_single_user_home ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_home', $retval );
}

/**
 * Check if current page is the currently logged in users author edit page
 *
 * @since 2.1.0 bbPress (r3918)
 *
 * @return bool True if it's the user's home, false if not
 */
function bbp_is_user_home_edit() {

	// Assume false
	$retval = false;

	if ( bbp_is_user_home() && bbp_is_single_user_edit() ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_home_edit', $retval );
}

/**
 * Check if current page is a user profile page
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return bool True if it's a user's profile page, false if not
 */
function bbp_is_single_user() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user ) && ( true === $wp_query->bbp_is_single_user ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_user', $retval );
}

/**
 * Check if current page is a user profile edit page
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @return bool True if it's a user's profile edit page, false if not
 */
function bbp_is_single_user_edit() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_edit ) && ( true === $wp_query->bbp_is_single_user_edit ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_user_edit', $retval );
}

/**
 * Check if current page is a user profile page
 *
 * @since 2.2.0 bbPress (r4225)
 *
 * @return bool True if it's a user's profile page, false if not
 */
function bbp_is_single_user_profile() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_profile ) && ( true === $wp_query->bbp_is_single_user_profile ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_user_profile', $retval );
}

/**
 * Check if current page is a user topics created page
 *
 * @since 2.2.0 bbPress (r4225)
 *
 * @return bool True if it's a user's topics page, false if not
 */
function bbp_is_single_user_topics() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_topics ) && ( true === $wp_query->bbp_is_single_user_topics ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_user_topics', $retval );
}

/**
 * Check if current page is a user replies created page
 *
 * @since 2.2.0 bbPress (r4225)
 *
 * @return bool True if it's a user's replies page, false if not
 */
function bbp_is_single_user_replies() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_replies ) && ( true === $wp_query->bbp_is_single_user_replies ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_user_replies', $retval );
}

/**
 * Check if current page is a user engagements page
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @return bool True if it's a user's replies page, false if not
 */
function bbp_is_single_user_engagements() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_single_user_engagements ) && ( true === $wp_query->bbp_is_single_user_engagements ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_user_engagements', $retval );
}

/**
 * Check if current page is a view page
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @global WP_Query $wp_query To check if WP_Query::bbp_is_view is true
 * @return bool Is it a view page?
 */
function bbp_is_single_view() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_view ) && ( true === $wp_query->bbp_is_view ) ) {
		$retval = true;
	}

	// Check query name
	if ( empty( $retval ) && bbp_is_query_name( 'bbp_single_view' ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_single_view', $retval );
}

/**
 * Check if current page is a search page
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @global WP_Query $wp_query To check if WP_Query::bbp_is_search is true
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

	// Get the rewrite ID (one time, to avoid repeated calls)
	$rewrite_id = bbp_get_search_rewrite_id();

	// Check query
	if ( ! empty( $wp_query->bbp_is_search ) && ( true === $wp_query->bbp_is_search ) ) {
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

	// Filter & return
	return (bool) apply_filters( 'bbp_is_search', $retval );
}

/**
 * Check if current page is a search results page
 *
 * @since 2.4.0 bbPress (r4919)
 *
 * @global WP_Query $wp_query To check if WP_Query::bbp_is_search is true
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

	// Filter & return
	return (bool) apply_filters( 'bbp_is_search_results', $retval );
}

/**
 * Check if current page is an edit page
 *
 * @since 2.1.0 bbPress (r3585)
 *
 * @return bool True if it's the edit page, false if not
 */
function bbp_is_edit() {

	// Assume false
	$retval = false;

	// Get the main query global
	$wp_query = bbp_get_wp_query();

	// Check query
	if ( ! empty( $wp_query->bbp_is_edit ) && ( $wp_query->bbp_is_edit === true ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_edit', $retval );
}

/**
 * Use the above is_() functions to output a body class for each scenario
 *
 * @since 2.0.0 bbPress (r2926)
 *
 * @param array $wp_classes
 * @param array $custom_classes
 * @return array Body Classes
 */
function bbp_body_class( $wp_classes = array(), $custom_classes = false ) {

	// Default to no classes
	$bbp_classes = array();

	/** Archives **************************************************************/

	if ( bbp_is_forum_archive() ) {
		$bbp_classes[] = bbp_get_forum_post_type() . '-archive';

	} elseif ( bbp_is_topic_archive() ) {
		$bbp_classes[] = bbp_get_topic_post_type() . '-archive';

	/** Topic Tags ************************************************************/

	} elseif ( bbp_is_topic_tag() ) {
		$bbp_classes[] = bbp_get_topic_tag_tax_id();
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-' . bbp_get_topic_tag_slug();
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-' . bbp_get_topic_tag_id();
	} elseif ( bbp_is_topic_tag_edit() ) {
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-edit';
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-' . bbp_get_topic_tag_slug() . '-edit';
		$bbp_classes[] = bbp_get_topic_tag_tax_id() . '-' . bbp_get_topic_tag_id()   . '-edit';

	/** Components ************************************************************/

	} elseif ( bbp_is_single_forum() ) {
		$bbp_classes[] = bbp_get_forum_post_type();

	} elseif ( bbp_is_single_topic() ) {
		$bbp_classes[] = bbp_get_topic_post_type();

	} elseif ( bbp_is_single_reply() ) {
		$bbp_classes[] = bbp_get_reply_post_type();

	} elseif ( bbp_is_topic_edit() ) {
		$bbp_classes[] = bbp_get_topic_post_type() . '-edit';

	} elseif ( bbp_is_topic_merge() ) {
		$bbp_classes[] = bbp_get_topic_post_type() . '-merge';

	} elseif ( bbp_is_topic_split() ) {
		$bbp_classes[] = bbp_get_topic_post_type() . '-split';

	} elseif ( bbp_is_reply_edit() ) {
		$bbp_classes[] = bbp_get_reply_post_type() . '-edit';

	} elseif ( bbp_is_reply_move() ) {
		$bbp_classes[] = bbp_get_reply_post_type() . '-move';

	} elseif ( bbp_is_single_view() ) {
		$bbp_classes[] = 'bbp-view';
		$bbp_classes[] = 'bbp-view-' . bbp_get_view_id();

	/** User ******************************************************************/

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

	} elseif ( bbp_is_user_home_edit() ) {
		$bbp_classes[] = 'bbp-user-home-edit';
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

	/** Search ****************************************************************/

	} elseif ( bbp_is_search() ) {
		$bbp_classes[] = 'bbp-search';
		$bbp_classes[] = 'forum-search';

	} elseif ( bbp_is_search_results() ) {
		$bbp_classes[] = 'bbp-search-results';
		$bbp_classes[] = 'forum-search-results';

	/** Shortcodes ************************************************************/

	} elseif ( bbp_has_shortcode() ) {
		$bbp_classes[] = 'bbp-shortcode';
	}

	/** General ***************************************************************/

	// Any page with bbPress content
	if ( ! empty( $bbp_classes ) ) {
		$bbp_classes[] = 'bbpress';
		$bbp_classes[] = 'no-js';
	}

	/** Clean up **************************************************************/

	// Merge WP classes with bbPress classes and remove any duplicates
	$classes = array_unique( array_merge( (array) $bbp_classes, (array) $wp_classes ) );

	// Deprecated filter (do not use)
	$classes = apply_filters( 'bbp_get_the_body_class', $classes, $bbp_classes, $wp_classes, $custom_classes );

	// Filter & return
	return (array) apply_filters( 'bbp_body_class', $classes, $bbp_classes, $wp_classes, $custom_classes );
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
 * @since 2.6.0
 *
 * @param string $text
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

	// Filter & return
	return (bool) apply_filters( 'bbp_has_shortcode', $retval, $found, $text );
}

/**
 * Use the above is_() functions to return if in any bbPress page
 *
 * @since 2.0.0 bbPress (r3344)
 *
 * @return bool In a bbPress page
 */
function is_bbpress() {

	// Default to false
	$retval = false;

	// Bail if main query has not been populated.
	if ( ! bbp_get_wp_query() ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Conditional query tags do not work before the query is run. Before then, they always return false.', 'bbpress' ), '2.7.0' );
		return $retval;
	}

	/** Archives **************************************************************/

	if ( bbp_is_forum_archive() ) {
		$retval = true;

	} elseif ( bbp_is_topic_archive() ) {
		$retval = true;

	/** Topic Tags ************************************************************/

	} elseif ( bbp_is_topic_tag() ) {
		$retval = true;

	} elseif ( bbp_is_topic_tag_edit() ) {
		$retval = true;

	/** Components ************************************************************/

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

	/** User ******************************************************************/

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

	/** Search ****************************************************************/

	} elseif ( bbp_is_search() ) {
		$retval = true;

	} elseif ( bbp_is_search_results() ) {
		$retval = true;

	/** Shortcodes ************************************************************/

	} elseif ( bbp_has_shortcode() ) {
		$retval = true;
	}

	/** Done ******************************************************************/

	// Filter & return
	return (bool) apply_filters( 'is_bbpress', $retval );
}

/** Forms *********************************************************************/

/**
 * Output the login form action url
 *
 * @since 2.0.0 bbPress (r2815)
 *
 * @param array $args This function supports these arguments:
 *  - action: The action being taken
 *  - context: The context the action is being taken from
 */
function bbp_wp_login_action( $args = array() ) {
	echo esc_url( bbp_get_wp_login_action( $args ) );
}

	/**
	 * Return the login form action url
	 *
	 * @since 2.6.0 bbPress (r5684)
	 *
	 * @param array $args This function supports these arguments:
	 *  - action: The action being taken
	 *  - context: The context the action is being taken from
	 */
	function bbp_get_wp_login_action( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'action'  => '',
			'context' => '',
			'url'     => 'wp-login.php'
		), 'login_action' );

		// Add action as query arg
		$login_url = ! empty( $r['action'] )
			? add_query_arg( array( 'action' => $r['action'] ), $r['url'] )
			: $r['url'];

		$login_url = site_url( $login_url, $r['context'] );

		// Filter & return
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
 * @param string $redirect_to Pass a URL to redirect to
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

	// Filter & return
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
 * @param string $request Name of $_REQUEST to look for
 * @param string $input_type Type of input. Default: text. Accepts:
 *                            textarea|password|select|radio|checkbox
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
	 * @param string $request Name of $_REQUEST to look for
	 * @param string $input_type Type of input. Default: text. Accepts:
	 *                            textarea|password|select|radio|checkbox
	 *
	 * @return string Sanitized value ready for screen display
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
			case 'text'     :
			case 'textarea' :
				$retval = esc_attr( wp_unslash( $pre_ret_val ) );
				break;

			case 'password' :
			case 'select'   :
			case 'radio'    :
			case 'checkbox' :
			default :
				$retval = esc_attr( $pre_ret_val );
				break;
		}

		// Filter & return
		return apply_filters( 'bbp_get_sanitize_val', $retval, $request, $input_type );
	}

/**
 * Output the current tab index of a given form
 *
 * Use this function to handle the tab indexing of user facing forms within a
 * template file. Calling this function will automatically increment the global
 * tab index by default.
 *
 * @since 2.0.0 bbPress (r2810)
 *
 * @deprecated 2.6.0 bbPress (r5561)
 *
 * @link https://bbpress.trac.wordpress.org/attachment/ticket/2714 Trac Ticket
 * @param int $auto_increment Optional. Default true. Set to false to prevent
 *                             increment
 */
function bbp_tab_index( $auto_increment = true ) {
	echo bbp_get_tab_index( $auto_increment );
}

	/**
	 * Return the current tab index of a given form
	 *
	 * Use this function to handle the tab indexing of user facing forms
	 * within a template file. Calling this function will automatically
	 * increment the global tab index by default.
	 *
	 * @since 2.0.0 bbPress (r2810)
	 *
	 * @deprecated 2.6.0 bbPress (r5561)
	 *
	 * @link https://bbpress.trac.wordpress.org/attachment/ticket/2714 Trac Ticket
	 * @param int $auto_increment Optional. Default true. Set to false to
	 *                             prevent the increment
	 * @return int $bbp->tab_index The global tab index
	 */
	function bbp_get_tab_index( $auto_increment = true ) {
		$bbp = bbpress();

		if ( true === $auto_increment ) {
			++$bbp->tab_index;
		}

		// Filter & return
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
 * @since 2.6.0 bbPress (r6424)
 *
 * @param mixed $tab False to skip, any integer to use
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
	 * @since 2.6.0 bbPress (r6424)
	 *
	 * @param mixed $tab False to skip, any integer to use
	 *
	 * @return string
	 */
	function bbp_get_tab_index_attribute( $tab = false ) {

		// Get attribute
		$attr = is_numeric( $tab )
			? ' tabindex="' . (int) $tab . '"'
			: '';

		// Filter & return
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
 * @param array $args See {@link bbp_get_dropdown()} for arguments
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
	 * @param array $args The function supports these args:
	 *  - post_type: Post type, defaults to bbp_get_forum_post_type() (bbp_forum)
	 *  - selected: Selected ID, to not have any value as selected, pass
	 *               anything smaller than 0 (due to the nature of select
	 *               box, the first value would of course be selected -
	 *               though you can have that as none (pass 'show_none' arg))
	 *  - orderby: Defaults to 'menu_order title'
	 *  - post_parent: Post parent. Defaults to 0
	 *  - post_status: Which all post_statuses to find in? Can be an array
	 *                  or CSV of publish, category, closed, private, spam,
	 *                  trash (based on post type) - if not set, these are
	 *                  automatically determined based on the post_type
	 *  - posts_per_page: Retrieve all forums/topics. Defaults to -1 to get
	 *                     all posts
	 *  - walker: Which walker to use? Defaults to
	 *             {@link BBP_Walker_Dropdown}
	 *  - select_id: ID of the select box. Defaults to 'bbp_forum_id'
	 *  - tab: Deprecated. Tabindex value. False or integer
	 *  - options_only: Show only <options>? No <select>?
	 *  - show_none: Boolean or String __( '&mdash; No Forum &mdash;', 'bbpress' )
	 *  - disable_categories: Disable forum categories and closed forums?
	 *                         Defaults to true. Only for forums and when
	 * @return string The dropdown
	 */
	function bbp_get_dropdown( $args = array() ) {

		// Setup return value
		$retval = '';

		/** Arguments *********************************************************/

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(

			// Support for get_posts()
			'post_type'          => bbp_get_forum_post_type(),
			'post_parent'        => null,
			'post_status'        => null,
			'selected'           => 0,
			'include'            => array(),
			'exclude'            => array(),
			'numberposts'        => -1,
			'orderby'            => 'menu_order title',
			'order'              => 'ASC',

			// Preloaded content
			'posts'              => array(),

			// Custom hierarchy walkers
			'walker'             => '',

			// Output-related
			'select_id'          => 'bbp_forum_id',
			'select_class'       => 'bbp_dropdown',
			'tab'                => false,
			'options_only'       => false,
			'show_none'          => false,
			'disable_categories' => true,
			'disabled'           => ''
		), 'get_dropdown' );

		// Fallback to our walker
		if ( empty( $r['walker'] ) ) {
			$r['walker']            = new BBP_Walker_Dropdown();
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

		/** Setup Posts *******************************************************/

		/**
		 * Allow passing of custom posts data
		 *
		 * @see bbp_get_reply_to_dropdown() as an example
		 */
		if ( empty( $r['posts'] ) ) {
			$r['posts'] = get_posts( array(
				'post_type'   => $r['post_type'],
				'post_status' => $r['post_status'],
				'post_parent' => $r['post_parent'],
				'include'     => $r['include'],
				'exclude'     => $r['exclude'],
				'numberposts' => $r['numberposts'],
				'orderby'     => $r['orderby'],
				'order'       => $r['order'],
			) );
		}

		/** Drop Down *********************************************************/

		// Build the opening tag for the select element
		if ( empty( $r['options_only'] ) ) {

			// Should this select appear disabled?
			$disabled  = disabled( isset( bbpress()->options[ $r['disabled'] ] ), true, false );

			// Setup the tab index attribute
			$tab       = ! empty( $r['tab'] ) ? ' tabindex="' . intval( $r['tab'] ) . '"' : '';

			// Open the select tag
			$retval   .= '<select name="' . esc_attr( $r['select_id'] ) . '" id="' . esc_attr( $r['select_id'] ) . '" class="' . esc_attr( $r['select_class'] ) . '"' . $disabled . $tab . '>' . "\n";
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
					case bbp_get_topic_post_type() :
						$retval .= esc_html__( 'No topics available', 'bbpress' );
						break;

					// Forums
					case bbp_get_forum_post_type() :
						$retval .= esc_html__( 'No forums available', 'bbpress' );
						break;

					// Any other
					default :
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

		// Filter & return
		return apply_filters( 'bbp_get_dropdown', $retval, $r, $args );
	}

/**
 * Output the required hidden fields when creating/editing a forum
 *
 * @since 2.1.0 bbPress (r3553)
 */
function bbp_forum_form_fields() {

	if ( bbp_is_forum_edit() ) : ?>

		<input type="hidden" name="action"       id="bbp_post_action" value="bbp-edit-forum" />
		<input type="hidden" name="bbp_forum_id" id="bbp_forum_id"    value="<?php bbp_forum_id(); ?>" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) :
			wp_nonce_field( 'bbp-unfiltered-html-forum_' . bbp_get_forum_id(), '_bbp_unfiltered_html_forum', false );
		endif; ?>

		<?php wp_nonce_field( 'bbp-edit-forum_' . bbp_get_forum_id() );

	else :

		if ( bbp_is_single_forum() ) : ?>

			<input type="hidden" name="bbp_forum_parent_id" id="bbp_forum_parent_id" value="<?php bbp_forum_parent_id(); ?>" />

		<?php endif; ?>

		<input type="hidden" name="action" id="bbp_post_action" value="bbp-new-forum" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) :
			wp_nonce_field( 'bbp-unfiltered-html-forum_new', '_bbp_unfiltered_html_forum', false );
		endif; ?>

		<?php wp_nonce_field( 'bbp-new-forum' );

	endif;
}

/**
 * Output the required hidden fields when creating/editing a topic
 *
 * @since 2.0.0 bbPress (r2753)
 */
function bbp_topic_form_fields() {

	if ( bbp_is_topic_edit() ) : ?>

		<input type="hidden" name="action"       id="bbp_post_action" value="bbp-edit-topic" />
		<input type="hidden" name="bbp_topic_id" id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) :
			wp_nonce_field( 'bbp-unfiltered-html-topic_' . bbp_get_topic_id(), '_bbp_unfiltered_html_topic', false );
		endif; ?>

		<?php wp_nonce_field( 'bbp-edit-topic_' . bbp_get_topic_id() );

	else :

		if ( bbp_is_single_forum() ) : ?>

			<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="<?php bbp_forum_id(); ?>" />

		<?php endif; ?>

		<input type="hidden" name="action" id="bbp_post_action" value="bbp-new-topic" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) :
			wp_nonce_field( 'bbp-unfiltered-html-topic_new', '_bbp_unfiltered_html_topic', false );
		endif; ?>

		<?php wp_nonce_field( 'bbp-new-topic' );

	endif;
}

/**
 * Output the required hidden fields when creating/editing a reply
 *
 * @since 2.0.0 bbPress (r2753)
 */
function bbp_reply_form_fields() {

	if ( bbp_is_reply_edit() ) : ?>

		<input type="hidden" name="bbp_reply_id"    id="bbp_reply_id"    value="<?php bbp_reply_id(); ?>" />
		<input type="hidden" name="action"          id="bbp_post_action" value="bbp-edit-reply" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) :
			wp_nonce_field( 'bbp-unfiltered-html-reply_' . bbp_get_reply_id(), '_bbp_unfiltered_html_reply', false );
		endif; ?>

		<?php wp_nonce_field( 'bbp-edit-reply_' . bbp_get_reply_id() );

	else : ?>

		<input type="hidden" name="bbp_topic_id"    id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />
		<input type="hidden" name="bbp_reply_to"    id="bbp_reply_to"    value="<?php bbp_form_reply_to(); ?>" />
		<input type="hidden" name="action"          id="bbp_post_action" value="bbp-new-reply" />

		<?php if ( current_user_can( 'unfiltered_html' ) ) :
			wp_nonce_field( 'bbp-unfiltered-html-reply_' . bbp_get_topic_id(), '_bbp_unfiltered_html_reply', false );
		endif; ?>

		<?php wp_nonce_field( 'bbp-new-reply' );

		// Show redirect field if not viewing a specific topic
		if ( bbp_is_query_name( 'bbp_single_topic' ) ) :
			$redirect_to = apply_filters( 'bbp_reply_form_redirect_to', get_permalink() );
			bbp_redirect_to_field( $redirect_to );
		endif;
	endif;
}

/**
 * Output the required hidden fields when editing a user
 *
 * @since 2.0.0 bbPress (r2690)
 */
function bbp_edit_user_form_fields() {
?>

	<input type="hidden" name="action"  id="bbp_post_action" value="bbp-update-user" />
	<input type="hidden" name="user_id" id="user_id"         value="<?php bbp_displayed_user_id(); ?>" />

	<?php wp_nonce_field( 'update-user_' . bbp_get_displayed_user_id() );
}

/**
 * Merge topic form fields
 *
 * Output the required hidden fields when merging a topic
 *
 * @since 2.0.0 bbPress (r2756)
 */
function bbp_merge_topic_form_fields() {
?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-merge-topic" />
	<input type="hidden" name="bbp_topic_id" id="bbp_topic_id"    value="<?php bbp_topic_id(); ?>" />

	<?php wp_nonce_field( 'bbp-merge-topic_' . bbp_get_topic_id() );
}

/**
 * Split topic form fields
 *
 * Output the required hidden fields when splitting a topic
 *
 * @since 2.0.0 bbPress (r2756)
 */
function bbp_split_topic_form_fields() {
?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-split-topic" />
	<input type="hidden" name="bbp_reply_id" id="bbp_reply_id"    value="<?php echo intval( $_GET['reply_id'] ); ?>" />

	<?php wp_nonce_field( 'bbp-split-topic_' . bbp_get_topic_id() );
}

/**
 * Move reply form fields
 *
 * Output the required hidden fields when moving a reply
 */
function bbp_move_reply_form_fields() {
?>

	<input type="hidden" name="action"       id="bbp_post_action" value="bbp-move-reply" />
	<input type="hidden" name="bbp_reply_id" id="bbp_reply_id"    value="<?php echo intval( $_GET['reply_id'] ); ?>" />

	<?php wp_nonce_field( 'bbp-move-reply_' . bbp_get_reply_id() );
}

/**
 * Output a textarea or TinyMCE if enabled
 *
 * @since 2.1.0 bbPress (r3586)
 *
 * @param array $args
 */
function bbp_the_content( $args = array() ) {
	echo bbp_get_the_content( $args );
}
	/**
	 * Return a textarea or TinyMCE if enabled
	 *
	 * @since 2.1.0 bbPress (r3586)
	 *
	 * @param array $args
	 *
	 * @return string HTML from output buffer
	 */
	function bbp_get_the_content( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
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
			'dfw'               => false
		), 'get_the_content' );

		// If using tinymce, remove our escaping and trust tinymce
		if ( bbp_use_wp_editor() && ( false !== $r['tinymce'] ) ) {
			remove_filter( 'bbp_get_form_forum_content', 'esc_textarea' );
			remove_filter( 'bbp_get_form_topic_content', 'esc_textarea' );
			remove_filter( 'bbp_get_form_reply_content', 'esc_textarea' );
		}

		// Assume we are not editing
		$post_content = call_user_func( 'bbp_get_form_' . $r['context'] . '_content' );

		// Start an output buffor
		ob_start();

		// Output something before the editor
		if ( ! empty( $r['before'] ) ) {
			echo $r['before'];
		}

		// Use TinyMCE if available
		if ( bbp_use_wp_editor() ) :

			// Enable additional TinyMCE plugins before outputting the editor
			add_filter( 'tiny_mce_plugins',   'bbp_get_tiny_mce_plugins'   );
			add_filter( 'teeny_mce_plugins',  'bbp_get_tiny_mce_plugins'   );
			add_filter( 'teeny_mce_buttons',  'bbp_get_teeny_mce_buttons'  );
			add_filter( 'quicktags_settings', 'bbp_get_quicktags_settings' );

			// Output the editor
			wp_editor( $post_content, 'bbp_' . $r['context'] . '_content', array(
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
			) );

			// Remove additional TinyMCE plugins after outputting the editor
			remove_filter( 'tiny_mce_plugins',   'bbp_get_tiny_mce_plugins'   );
			remove_filter( 'teeny_mce_plugins',  'bbp_get_tiny_mce_plugins'   );
			remove_filter( 'teeny_mce_buttons',  'bbp_get_teeny_mce_buttons'  );
			remove_filter( 'quicktags_settings', 'bbp_get_quicktags_settings' );

		/**
		 * Fallback to normal textarea.
		 *
		 * Note that we do not use esc_textarea() here to prevent double
		 * escaping the editable output, mucking up existing content.
		 */
		else : ?>

			<textarea id="bbp_<?php echo esc_attr( $r['context'] ); ?>_content" class="<?php echo esc_attr( $r['editor_class'] ); ?>" name="bbp_<?php echo esc_attr( $r['context'] ); ?>_content" cols="60" rows="<?php echo esc_attr( $r['textarea_rows'] ); ?>" <?php bbp_tab_index_attribute( $r['tabindex'] ); ?>><?php echo $post_content; ?></textarea>

		<?php endif;

		// Output something after the editor
		if ( ! empty( $r['after'] ) ) {
			echo $r['after'];
		}

		// Put the output into a usable variable
		$output = ob_get_clean();

		// Filter & return
		return apply_filters( 'bbp_get_the_content', $output, $args, $post_content );
	}

/**
 * Edit TinyMCE plugins to match core behaviour
 *
 * @since 2.3.0 bbPress (r4574)
 *
 * @param array $plugins
 * @see tiny_mce_plugins, teeny_mce_plugins
 * @return array
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

	// Filter & return
	return apply_filters( 'bbp_get_tiny_mce_plugins', $plugins );
}

/**
 * Edit TeenyMCE buttons to match allowedtags
 *
 * @since 2.3.0 bbPress (r4605)
 *
 * @param array $buttons
 * @see teeny_mce_buttons
 * @return array
 */
function bbp_get_teeny_mce_buttons( $buttons = array() ) {

	// Remove some buttons from TeenyMCE
	$buttons = array_diff( $buttons, array(
		'underline',
		'justifyleft',
		'justifycenter',
		'justifyright'
	) );

	// Images
	array_push( $buttons, 'image' );

	// Filter & return
	return apply_filters( 'bbp_get_teeny_mce_buttons', $buttons );
}

/**
 * Edit TinyMCE quicktags buttons to match allowedtags
 *
 * @since 2.3.0 bbPress (r4606)
 *
 * @param array $settings
 * @see quicktags_settings
 * @return array Quicktags settings
 */
function bbp_get_quicktags_settings( $settings = array() ) {

	// Get buttons out of settings
	$buttons_array = explode( ',', $settings['buttons'] );

	// Diff the ones we don't want out
	$buttons = array_diff( $buttons_array, array(
		'ins',
		'more',
		'spell'
	) );

	// Put them back into a string in the $settings array
	$settings['buttons'] = implode( ',', $buttons );

	// Filter & return
	return apply_filters( 'bbp_get_quicktags_settings', $settings );
}

/** Views *********************************************************************/

/**
 * Output the view id
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string $view Optional. View id
 */
function bbp_view_id( $view = '' ) {
	echo bbp_get_view_id( $view );
}

	/**
	 * Get the view id
	 *
	 * Use view id if supplied, otherwise bbp_get_view_rewrite_id() query var.
	 *
	 * @since 2.0.0 bbPress (r2789)
	 *
	 * @param string $view Optional. View id.
	 * @return bool|string ID on success, false on failure
	 */
	function bbp_get_view_id( $view = '' ) {
		$bbp = bbpress();

		// User supplied string
		if ( ! empty( $view ) && is_string( $view ) ) {
			$view_id = $view;

		// Current view ID
		} elseif ( ! empty( $bbp->current_view_id ) ) {
			$view_id = $bbp->current_view_id;

		// Querying for view
		} else {
			$view_id = get_query_var( bbp_get_view_rewrite_id() );
		}

		// Filter & return
		return apply_filters( 'bbp_get_view_id', $view_id, $view );
	}

/**
 * Output the view name aka title
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string $view Optional. View id
 */
function bbp_view_title( $view = '' ) {
	echo bbp_get_view_title( $view );
}

	/**
	 * Get the view name aka title
	 *
	 * If a view id is supplied, that is used. Otherwise the bbp_view
	 * query var is checked for.
	 *
	 * @since 2.0.0 bbPress (r2789)
	 *
	 * @param string $view Optional. View id
	 * @return bool|string Title on success, false on failure
	 */
	function bbp_get_view_title( $view = '' ) {
		$bbp = bbpress();

		$view = bbp_get_view_id( $view );
		if ( empty( $view ) ) {
			return false;
		}

		return $bbp->views[ $view ]['title'];
	}

/**
 * Output the view url
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @param string $view Optional. View id
 */
function bbp_view_url( $view = false ) {
	echo esc_url( bbp_get_view_url( $view ) );
}
	/**
	 * Return the view url
	 *
	 * @since 2.0.0 bbPress (r2789)
	 *
	 * @param string $view Optional. View id
	 *                        used view id
	 * @return string View url (or home url if the view was not found)
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
			$url = add_query_arg( array(
				bbp_get_view_rewrite_id() => $view
			), home_url( '/' ) );
		}

		// Filter & return
		return apply_filters( 'bbp_get_view_link', $url, $view );
	}

/** Query *********************************************************************/

/**
 * Check the passed parameter against the current _bbp_query_name
 *
 * @since 2.0.0 bbPress (r2980)
 *
 * @return bool True if match, false if not
 */
function bbp_is_query_name( $name = '' ) {
	return (bool) ( bbp_get_query_name() === $name );
}

/**
 * Get the '_bbp_query_name' setting
 *
 * @since 2.0.0 bbPress (r2695)
 *
 * @return string To return the query var value
 */
function bbp_get_query_name() {
	return get_query_var( '_bbp_query_name' );
}

/**
 * Set the '_bbp_query_name' setting to $name
 *
 * @since 2.0.0 bbPress (r2692)
 *
 * @param string $name What to set the query var to
 */
function bbp_set_query_name( $name = '' ) {
	set_query_var( '_bbp_query_name', $name );
}

/**
 * Used to clear the '_bbp_query_name' setting
 *
 * @since 2.0.0 bbPress (r2692)
 *
 */
function bbp_reset_query_name() {
	bbp_set_query_name();
}

/** Breadcrumbs ***************************************************************/

/**
 * Output the page title as a breadcrumb
 *
 * @since 2.0.0 bbPress (r2589)
 *
 * @param string $sep Separator. Defaults to '&larr;'
 * @param bool $current_page Include the current item
 * @param bool $root Include the root page if one exists
 */
function bbp_title_breadcrumb( $args = array() ) {
	echo bbp_get_breadcrumb( $args );
}

/**
 * Output a breadcrumb
 *
 * @since 2.0.0 bbPress (r2589)
 *
 * @param string $sep Separator. Defaults to '&larr;'
 * @param bool $current_page Include the current item
 * @param bool $root Include the root page if one exists
 */
function bbp_breadcrumb( $args = array() ) {
	echo bbp_get_breadcrumb( $args );
}
	/**
	 * Return a breadcrumb ( forum -> topic -> reply )
	 *
	 * @since 2.0.0 bbPress (r2589)
	 *
	 * @param string $sep Separator. Defaults to '&larr;'
	 * @param bool $current_page Include the current item
	 * @param bool $root Include the root page if one exists
	 *
	 * @return string Breadcrumbs
	 */
	function bbp_get_breadcrumb( $args = array() ) {

		// Turn off breadcrumbs
		if ( apply_filters( 'bbp_no_breadcrumb', is_front_page() ) ) {
			return;
		}

		// Define variables
		$front_id         = $root_id                                 = 0;
		$ancestors        = $crumbs           = $tag_data            = array();
		$pre_root_text    = $pre_front_text   = $pre_current_text    = '';
		$pre_include_root = $pre_include_home = $pre_include_current = true;

		/** Home Text *********************************************************/

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

		/** Root Text *********************************************************/

		// No custom root text
		if ( empty( $args['root_text'] ) ) {
			$page = bbp_get_page_by_path( bbp_get_root_slug() );
			if ( ! empty( $page ) ) {
				$root_id = $page->ID;
			}
			$pre_root_text = bbp_get_forum_archive_title();
		}

		/** Includes **********************************************************/

		// Root slug is also the front page
		if ( ! empty( $front_id ) && ( $front_id === $root_id ) ) {
			$pre_include_root = false;
		}

		// Don't show root if viewing forum archive
		if ( bbp_is_forum_archive() ) {
			$pre_include_root = false;
		}

		// Don't show root if viewing page in place of forum archive
		if ( ! empty( $root_id ) && ( ( is_single() || is_page() ) && ( $root_id === get_the_ID() ) ) ) {
			$pre_include_root = false;
		}

		/** Current Text ******************************************************/

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
			$pre_current_text = sprintf( esc_html__( 'Topic Tag: %s', 'bbpress' ), implode( ' ', $tag_data ) );

		// Edit Topic Tag
		} elseif ( bbp_is_topic_tag_edit() ) {
			$pre_current_text = esc_html__( 'Edit', 'bbpress' );

		// Single
		} else {
			$pre_current_text = get_the_title();
		}

		/** Parse Args ********************************************************/

		// Parse args
		$r = bbp_parse_args( $args, array(

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
		), 'get_breadcrumb' );

		/** Ancestors *********************************************************/

		// Get post ancestors
		if ( is_singular() || bbp_is_forum_edit() || bbp_is_topic_edit() || bbp_is_reply_edit() ) {
			$ancestors = array_reverse( (array) get_post_ancestors( get_the_ID() ) );
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
				$root_url = get_permalink( $page->ID );

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

				// Skip parent if empty or error
				if ( empty( $parent ) || is_wp_error( $parent ) ) {
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

					// Reply (Note: not in most themes)
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
			$crumbs[] = '<a href="' . esc_url( get_term_link( bbp_get_topic_tag_id(), bbp_get_topic_tag_tax_id() ) ) . '" class="bbp-breadcrumb-topic-tag">' . sprintf( esc_html__( 'Topic Tag: %s', 'bbpress' ), bbp_get_topic_tag_name() ) . '</a>';

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
		$sep    = apply_filters( 'bbp_breadcrumb_separator', $sep    );
		$crumbs = apply_filters( 'bbp_breadcrumbs',          $crumbs );

		// Build the trail
		$trail  = ! empty( $crumbs ) ? ( $r['before'] . $r['crumb_before'] . implode( $sep . $r['crumb_after'] . $r['crumb_before'] , $crumbs ) . $r['crumb_after'] . $r['after'] ) : '';

		// Filter & return
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
	 * @since 2.0.0 bbPress (r2780)
	 *
	 * @return string HTML allowed tags entity encoded.
	 */
	function bbp_get_allowed_tags() {

		$allowed = '';

		foreach ( (array) bbp_kses_allowed_tags() as $tag => $attributes ) {
			$allowed .= '<' . $tag;
			if ( 0 < count( $attributes ) ) {
				foreach ( array_keys( $attributes ) as $attribute ) {
					$allowed .= ' ' . $attribute . '=""';
				}
			}
			$allowed .= '> ';
		}

		// Filter & return
		return apply_filters( 'bbp_get_allowed_tags', htmlentities( $allowed ) );
	}

/** Errors & Messages *********************************************************/

/**
 * Display possible errors & messages inside a template file
 *
 * @since 2.0.0 bbPress (r2688)
 */
function bbp_template_notices() {

	// Bail if no notices or errors
	if ( ! bbp_has_errors() ) {
		return;
	}

	// Define local variable(s)
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
				$errors[]   = $error;
			}
		}
	}

	// Display errors first...
	if ( ! empty( $errors ) ) : ?>

		<div class="bbp-template-notice error" role="alert" tabindex="-1">
			<ul>
				<li><?php echo implode( "</li>\n<li>", $errors ); ?></li>
			</ul>
		</div>

	<?php endif;

	// ...and messages last
	if ( ! empty( $messages ) ) : ?>

		<div class="bbp-template-notice">
			<ul>
				<li><?php echo implode( "</li>\n<li>", $messages ); ?></li>
			</ul>
		</div>

	<?php endif;
}

/** Login/logout/register/lost pass *******************************************/

/**
 * Output the logout link
 *
 * @since 2.0.0 bbPress (r2827)
 *
 * @param string $redirect_to Redirect to url
 */
function bbp_logout_link( $redirect_to = '' ) {
	echo bbp_get_logout_link( $redirect_to );
}
	/**
	 * Return the logout link
	 *
	 * @since 2.0.0 bbPress (r2827)
	 *
	 * @param string $redirect_to Redirect to url
	 *                        redirect to url
	 * @return string The logout link
	 */
	function bbp_get_logout_link( $redirect_to = '' ) {

		// Build the link
		$link = '<a href="' . wp_logout_url( $redirect_to ) . '" class="button logout-link">' . esc_html__( 'Log Out', 'bbpress' ) . '</a>';

		// Filter & return
		return apply_filters( 'bbp_get_logout_link', $link, $redirect_to );
	}

/** Title *********************************************************************/

/**
 * Custom page title for bbPress pages
 *
 * @since 2.0.0 bbPress (r2788)
 *
 * @param string $title Optional. The title (not used).
 * @param string $sep Optional, default is '&raquo;'. How to separate the
 *                     various items within the page title.
 * @param string $seplocation Optional. Direction to display title, 'right'.
 *                        separator and separator location
 * @return string The title
 */
function bbp_title( $title = '', $sep = '&raquo;', $seplocation = '' ) {

	// Title array
	$new_title = array();

	/** Archives **************************************************************/

	// Forum Archive
	if ( bbp_is_forum_archive() ) {
		$new_title['text'] = bbp_get_forum_archive_title();

	// Topic Archive
	} elseif ( bbp_is_topic_archive() ) {
		$new_title['text'] = bbp_get_topic_archive_title();

	/** Edit ******************************************************************/

	// Forum edit page
	} elseif ( bbp_is_forum_edit() ) {
		$new_title['text']   = bbp_get_forum_title();
		$new_title['format'] = esc_attr__( 'Forum Edit: %s', 'bbpress' );

	// Topic edit page
	} elseif ( bbp_is_topic_edit() ) {
		$new_title['text']   = bbp_get_topic_title();
		$new_title['format'] = esc_attr__( 'Topic Edit: %s', 'bbpress' );

	// Reply edit page
	} elseif ( bbp_is_reply_edit() ) {
		$new_title['text']   = bbp_get_reply_title();
		$new_title['format'] = esc_attr__( 'Reply Edit: %s', 'bbpress' );

	// Topic tag edit page
	} elseif ( bbp_is_topic_tag_edit() ) {
		$new_title['text']   = bbp_get_topic_tag_name();
		$new_title['format'] = esc_attr__( 'Topic Tag Edit: %s', 'bbpress' );

	/** Singles ***************************************************************/

	// Forum page
	} elseif ( bbp_is_single_forum() ) {
		$new_title['text']   = bbp_get_forum_title();
		$new_title['format'] = esc_attr__( 'Forum: %s', 'bbpress' );

	// Topic page
	} elseif ( bbp_is_single_topic() ) {
		$new_title['text']   = bbp_get_topic_title();
		$new_title['format'] = esc_attr__( 'Topic: %s', 'bbpress' );

	// Replies
	} elseif ( bbp_is_single_reply() ) {
		$new_title['text']   = bbp_get_reply_title();

	// Topic tag page
	} elseif ( bbp_is_topic_tag() || get_query_var( 'bbp_topic_tag' ) ) {
		$new_title['text']   = bbp_get_topic_tag_name();
		$new_title['format'] = esc_attr__( 'Topic Tag: %s', 'bbpress' );

	/** Users *****************************************************************/

	// Profile page
	} elseif ( bbp_is_single_user() ) {

		// Is user viewing their own profile?
		$is_user_home = bbp_is_user_home();

		// User topics created
		if ( bbp_is_single_user_topics() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Topics', 'bbpress' );
			} else {
				$new_title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Topics", 'bbpress' );
			}

		// User replies created
		} elseif ( bbp_is_single_user_replies() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Replies', 'bbpress' );
			} else {
				$new_title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Replies", 'bbpress' );
			}

		// User favorites
		} elseif ( bbp_is_favorites() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Favorites', 'bbpress' );
			} else {
				$new_title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Favorites", 'bbpress' );
			}

		// User subscriptions
		} elseif ( bbp_is_subscriptions() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Subscriptions', 'bbpress' );
			} else {
				$new_title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Subscriptions", 'bbpress' );
			}

		// User "home"
		} else {
			if ( true === $is_user_home ) {
				$new_title['text'] = esc_attr__( 'Your Profile', 'bbpress' );
			} else {
				$new_title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$new_title['format'] = esc_attr__( "%s's Profile", 'bbpress' );
			}
		}

	// Profile edit page
	} elseif ( bbp_is_single_user_edit() ) {

		// Current user
		if ( bbp_is_user_home_edit() ) {
			$new_title['text']   = esc_attr__( 'Edit Your Profile', 'bbpress' );

		// Other user
		} else {
			$new_title['text']   = get_userdata( bbp_get_user_id() )->display_name;
			$new_title['format'] = esc_attr__( "Edit %s's Profile", 'bbpress' );
		}

	/** Views *****************************************************************/

	// Views
	} elseif ( bbp_is_single_view() ) {
		$new_title['text']   = bbp_get_view_title();
		$new_title['format'] = esc_attr__( 'View: %s', 'bbpress' );

	/** Search ****************************************************************/

	// Search
	} elseif ( bbp_is_search() ) {
		$new_title['text'] = bbp_get_search_title();
	}

	// This filter is deprecated. Use 'bbp_before_title_parse_args' instead.
	$new_title = apply_filters( 'bbp_raw_title_array', $new_title );

	// Set title array defaults
	$new_title = bbp_parse_args( $new_title, array(
		'text'   => $title,
		'format' => '%s'
	), 'title' );

	// Get the formatted raw title
	$new_title = sprintf( $new_title['format'], $new_title['text'] );

	// Filter the raw title
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

	// Filter & return
	return apply_filters( 'bbp_title', $new_title, $sep, $seplocation );
}
