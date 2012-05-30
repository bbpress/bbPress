<?php

/**
 * bbPress Template Loader
 *
 * @package bbPress
 * @subpackage TemplateLoader
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Possibly intercept the template being loaded
 *
 * Listens to the 'template_include' filter and waits for a bbPress post_type
 * to appear. If the current theme does not explicitly support bbPress, it
 * intercepts the page template and uses one served from the bbPress compatable
 * theme, set in the $bbp->theme_compat global. If the current theme does
 * support bbPress, we'll explore the template hierarchy and try to locate one.
 *
 * @since bbPress (r3032)
 *
 * @param string $template
 *
 * @uses bbp_is_single_user() To check if page is single user
 * @uses bbp_get_single_user_template() To get user template
 * @uses bbp_is_single_user_edit() To check if page is single user edit
 * @uses bbp_get_single_user_edit_template() To get user edit template
 * @uses bbp_is_single_view() To check if page is single view
 * @uses bbp_get_single_view_template() To get view template
 * @uses bbp_is_forum_edit() To check if page is forum edit
 * @uses bbp_get_forum_edit_template() To get forum edit template
 * @uses bbp_is_topic_merge() To check if page is topic merge
 * @uses bbp_get_topic_merge_template() To get topic merge template
 * @uses bbp_is_topic_split() To check if page is topic split
 * @uses bbp_get_topic_split_template() To get topic split template
 * @uses bbp_is_topic_edit() To check if page is topic edit
 * @uses bbp_get_topic_edit_template() To get topic edit template
 * @uses bbp_is_reply_edit() To check if page is reply edit
 * @uses bbp_get_reply_edit_template() To get reply edit template
 * @uses bbp_set_theme_compat_template() To set the global theme compat template
 *
 * @return string The path to the template file that is being used
 */
function bbp_template_include_theme_supports( $template = '' ) {

	// Viewing a user
	if     ( bbp_is_single_user()      && ( $new_template = bbp_get_single_user_template()      ) ) :

	// Editing a user
	elseif ( bbp_is_single_user_edit() && ( $new_template = bbp_get_single_user_edit_template() ) ) :

	// Single View
	elseif ( bbp_is_single_view()      && ( $new_template = bbp_get_single_view_template()      ) ) :

	// Single Forum
	elseif ( bbp_is_single_forum()     && ( $new_template = bbp_get_single_forum_template()     ) ) :

	// Forum Archive
	elseif ( bbp_is_forum_archive()    && ( $new_template = bbp_get_forum_archive_template()    ) ) :

	// Forum edit
	elseif ( bbp_is_forum_edit()       && ( $new_template = bbp_get_forum_edit_template()       ) ) :

	// Single Topic
	elseif ( bbp_is_single_topic()     && ( $new_template = bbp_get_single_topic_template()     ) ) :

	// Topic Archive
	elseif ( bbp_is_topic_archive()    && ( $new_template = bbp_get_topic_archive_template()    ) ) :

	// Topic merge
	elseif ( bbp_is_topic_merge()      && ( $new_template = bbp_get_topic_merge_template()      ) ) :

	// Topic split
	elseif ( bbp_is_topic_split()      && ( $new_template = bbp_get_topic_split_template()      ) ) :

	// Topic edit
	elseif ( bbp_is_topic_edit()       && ( $new_template = bbp_get_topic_edit_template()       ) ) :

	// Single Reply
	elseif ( bbp_is_single_reply()     && ( $new_template = bbp_get_single_reply_template()     ) ) :

	// Editing a reply
	elseif ( bbp_is_reply_edit()       && ( $new_template = bbp_get_reply_edit_template()       ) ) :

	// Viewing a topic tag
	elseif ( bbp_is_topic_tag()        && ( $new_template = bbp_get_topic_tag_template()        ) ) :

	// Editing a topic tag
	elseif ( bbp_is_topic_tag_edit()   && ( $new_template = bbp_get_topic_tag_edit_template()   ) ) :
	endif;

	// Custom template file exists
	$template = !empty( $new_template ) ? $new_template : $template;

	return apply_filters( 'bbp_template_include_theme_supports', $template );
}

/** Custom Functions **********************************************************/

/**
 * Attempt to load a custom bbPress functions file, similar to each themes
 * functions.php file.
 *
 * @since bbPress (r3732)
 *
 * @global string $pagenow
 * @uses bbp_locate_template()
 */
function bbp_load_theme_functions() {
	global $pagenow;

	if ( ! defined( 'WP_INSTALLING' ) || ( !empty( $pagenow ) && ( 'wp-activate.php' !== $pagenow ) ) ) {
		bbp_locate_template( 'bbpress-functions.php', true );
	}
}

/** Individual Templates ******************************************************/

/**
 * Get the user profile template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_displayed_user_id()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_single_user_template() {
	$nicename  = bbp_get_displayed_user_field( 'user_nicename' );
	$user_id   = bbp_get_displayed_user_id();
	$templates = array(
		'single-user-' . $nicename . '.php', // Single User nicename
		'single-user-' . $user_id  . '.php', // Single User ID
		'single-user.php',                   // Single User
		'user.php',                          // User
	);
	return bbp_get_query_template( 'profile', $templates );
}

/**
 * Get the user profile edit template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_displayed_user_id()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_single_user_edit_template() {
	$nicename  = bbp_get_displayed_user_field( 'user_nicename' );
	$user_id   = bbp_get_displayed_user_id();
	$templates = array(
		'single-user-edit-' . $nicename . '.php', // Single User Edt nicename
		'single-user-edit-' . $user_id  . '.php', // Single User Edit ID
		'single-user-edit.php',                   // Single User Edit
		'user-edit.php',                          // User Edit
		'user.php',                               // User
	);
	return bbp_get_query_template( 'profile_edit', $templates );
}

/**
 * Get the view template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_view_id()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_single_view_template() {
	$view_id   = bbp_get_view_id();
	$templates = array(
		'single-view-' . $view_id . '.php', // Single View ID
		'view-'        . $view_id . '.php', // View ID
		'single-view.php',                  // Single View
		'view.php',                         // View
	);
	return bbp_get_query_template( 'single_view', $templates );
}

/**
 * Get the single forum template
 *
 * @since bbPress (r3922)
 *
 * @uses bbp_get_forum_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_single_forum_template() {
	$templates = array(
		'single-' . bbp_get_forum_post_type() . '.php' // Single Forum
	);
	return bbp_get_query_template( 'single_forum', $templates );
}

/**
 * Get the forum archive template
 *
 * @since bbPress (r3922)
 *
 * @uses bbp_get_forum_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_forum_archive_template() {
	$templates = array(
		'archive-' . bbp_get_forum_post_type() . '.php' // Forum Archive
	);
	return bbp_get_query_template( 'forum_archive', $templates );
}

/**
 * Get the forum edit template
 *
 * @since bbPress (r3566)
 *
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_forum_edit_template() {
	$post_type = bbp_get_forum_post_type();
	$templates = array(
		'single-' . $post_type . '-edit.php', // Single Forum Edit
		'single-' . $post_type . '.php',      // Single Forum
	);
	return bbp_get_query_template( 'forum_edit', $templates );
}

/**
 * Get the single topic template
 *
 * @since bbPress (r3922)
 *
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_single_topic_template() {
	$templates = array(
		'single-' . bbp_get_topic_post_type() . '.php'
	);
	return bbp_get_query_template( 'single_topic', $templates );
}

/**
 * Get the topic archive template
 *
 * @since bbPress (r3922)
 *
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_topic_archive_template() {
	$templates = array(
		'archive-' . bbp_get_topic_post_type() . '.php' // Topic Archive
	);
	return bbp_get_query_template( 'topic_archive', $templates );
}

/**
 * Get the topic edit template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_topic_edit_template() {
	$post_type = bbp_get_topic_post_type();
	$templates = array(
		'single-' . $post_type . '-edit.php', // Single Topic Edit
		'single-' . $post_type . '.php',      // Single Topic
	);
	return bbp_get_query_template( 'topic_edit', $templates );
}

/**
 * Get the topic split template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_topic_split_template() {
	$post_type = bbp_get_topic_post_type();
	$templates = array(
		'single-' . $post_type . '-split.php', // Topic Split
	);
	return bbp_get_query_template( 'topic_split', $templates );
}

/**
 * Get the topic merge template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_topic_merge_template() {
	$post_type = bbp_get_topic_post_type();
	$templates = array(
		'single-' . $post_type . '-merge.php', // Topic Merge
	);
	return bbp_get_query_template( 'topic_merge', $templates );
}

/**
 * Get the single reply template
 *
 * @since bbPress (r3922)
 *
 * @uses bbp_get_reply_post_type()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_single_reply_template() {
	$templates = array(
		'single-' . bbp_get_reply_post_type() . '.php'
	);
	return bbp_get_query_template( 'single_reply', $templates );
}

/**
 * Get the reply edit template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_reply_post_type()
 * @uses bbp_get_query_template()
* @return string Path to template file
 */
function bbp_get_reply_edit_template() {
	$post_type = bbp_get_reply_post_type();
	$templates = array(
		'single-' . $post_type . '-edit.php', // Single Reply Edit
		'single-' . $post_type . '.php',      // Single Reply
	);
	return bbp_get_query_template( 'reply_edit', $templates );
}

/**
 * Get the topic template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_tag_tax_id()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_topic_tag_template() {
	$tt_slug   = bbp_get_topic_tag_slug();
	$tt_id     = bbp_get_topic_tag_tax_id();
	$templates = array(
		'taxonomy-' . $tt_slug . '.php', // Single Topic Tag slug
		'taxonomy-' . $tt_id   . '.php', // Single Topic Tag ID
	);
	return bbp_get_query_template( 'topic_tag', $templates );
}

/**
 * Get the topic edit template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_tag_tax_id()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_topic_tag_edit_template() {
	$tt_slug   = bbp_get_topic_tag_slug();
	$tt_id     = bbp_get_topic_tag_tax_id();
	$templates = array(
		'taxonomy-' . $tt_slug . '-edit.php', // Single Topic Tag Edit slug
		'taxonomy-' . $tt_id   . '-edit.php', // Single Topic Tag Edit ID
		'taxonomy-' . $tt_slug . '.php',      // Single Topic Tag slug
		'taxonomy-' . $tt_id   . '.php',      // Single Topic Tag ID
	);
	return bbp_get_query_template( 'topic_tag_edit', $templates );
}

/**
 * Get the templates to use as the endpoint for bbPress template parts
 *
 * @since bbPress (r3311)
 *
 * @uses apply_filters()
 * @uses bbp_set_theme_compat_templates()
 * @uses bbp_get_query_template()
 * @return string Path to template file
 */
function bbp_get_theme_compat_templates() {
	$templates = array(
		'bbpress.php',
		'forum.php',
		'page.php',
		'single.php',
		'index.php'
	);
	return bbp_get_query_template( 'bbpress', $templates );
}

?>
