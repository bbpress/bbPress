<?php

/**
 * bbPress Template Loader.
 *
 * @package bbPress
 * @subpackage TemplateLoader
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Possibly intercept the template being loaded.
 *
 * Listens to the 'template_include' filter and waits for any bbPress specific
 * template condition to be met. If one is met and the template file exists,
 * it will be used; otherwise.
 *
 * Note that the _edit() checks are ahead of their counterparts, to prevent them
 * from being stomped on accident.
 *
 * @since 2.0.0 bbPress (r3032)
 *
 * @param string $template
 *
 * @return string The path to the template file that is being used.
 */
function bbp_template_include_theme_supports( $template = '' ) {

	// Default return value
	$retval = $template;

	// Get the templates
	$bbp_templates = bbp_get_template_include_templates();

	// Loop through each of the template conditionals
	if ( ! empty( $bbp_templates ) ) {
		foreach ( $bbp_templates as $checker => $getter ) {

			// Skip if check fails
			if ( ! function_exists( $checker ) || ! call_user_func( $checker ) ) {
				continue;
			}

			// Maybe get a template file
			$new_template = function_exists( $getter )
				? call_user_func( $getter )
				: '';

			// Skip if new template not found
			if ( empty( $new_template ) ) {
				continue;
			}

			// A bbPress template file was located, so set the return value
			// that will override the WordPress template, and switches off
			// theme compatibility.
			$retval = bbp_set_template_included( $new_template );

			// Exit the loop
			break;
		}
	}

	/**
	 * Filters the path to the template file that is being used.
	 *
	 * @since 2.0.0 bbPress (r3032)
	 *
	 * @param string $retval   Path to the filtered template file.
	 * @param string $template Path to the original template file.
	 */
	return apply_filters( 'bbp_template_include_theme_supports', $retval, $template );
}

/**
 * Set the included template.
 *
 * @since 2.4.0 bbPress (r4975)
 *
 * @param mixed $template Default false.
 * @return mixed False if empty. Template name if template included.
 */
function bbp_set_template_included( $template = false ) {
	bbpress()->theme_compat->bbpress_template = $template;

	return bbpress()->theme_compat->bbpress_template;
}

/**
 * Is a bbPress template being included?
 *
 * @since 2.4.0 bbPress (r4975)
 *
 * @return bool True if yes, false if no.
 */
function bbp_is_template_included() {
	return ! empty( bbpress()->theme_compat->bbpress_template );
}

/** Custom Functions **********************************************************/

/**
 * Attempt to load a custom bbPress functions file, similar to each themes
 * functions.php file.
 *
 * @since 2.1.0 bbPress (r3732)
 *
 * @global string $pagenow The filename of the current screen.
 */
function bbp_load_theme_functions() {
	global $pagenow;

	// If bbPress is being deactivated, do not load any more files
	if ( bbp_is_deactivation() ) {
		return;
	}

	if ( ! defined( 'WP_INSTALLING' ) || ( ! empty( $pagenow ) && ( 'wp-activate.php' !== $pagenow ) ) ) {
		bbp_locate_template( 'bbpress-functions.php', true );
	}
}

/** Individual Templates ******************************************************/

/**
 * Get the user profile template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return string Path to template file.
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
 * Get the user profile edit template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return string Path to template file.
 */
function bbp_get_single_user_edit_template() {
	$nicename  = bbp_get_displayed_user_field( 'user_nicename' );
	$user_id   = bbp_get_displayed_user_id();
	$templates = array(
		'single-user-edit-' . $nicename . '.php', // Single User Edit nicename
		'single-user-edit-' . $user_id  . '.php', // Single User Edit ID
		'single-user-edit.php',                   // Single User Edit
		'user-edit.php',                          // User Edit
		'user.php',                               // User
	);
	return bbp_get_query_template( 'profile_edit', $templates );
}

/**
 * Get the user favorites template.
 *
 * @since 2.2.0 bbPress (r4225)
 *
 * @return string Path to template file.
 */
function bbp_get_favorites_template() {
	$nicename  = bbp_get_displayed_user_field( 'user_nicename' );
	$user_id   = bbp_get_displayed_user_id();
	$templates = array(
		'single-user-favorites-' . $nicename . '.php', // Single User Favs nicename
		'single-user-favorites-' . $user_id  . '.php', // Single User Favs ID
		'favorites-' . $nicename  . '.php',            // Favorites nicename
		'favorites-' . $user_id   . '.php',            // Favorites ID
		'favorites.php',                               // Favorites
		'user.php',                                    // User
	);
	return bbp_get_query_template( 'favorites', $templates );
}

/**
 * Get the user subscriptions template.
 *
 * @since 2.2.0 bbPress (r4225)
 *
 * @return string Path to template file.
 */
function bbp_get_subscriptions_template() {
	$nicename  = bbp_get_displayed_user_field( 'user_nicename' );
	$user_id   = bbp_get_displayed_user_id();
	$templates = array(
		'single-user-subscriptions-' . $nicename . '.php', // Single User Subs nicename
		'single-user-subscriptions-' . $user_id  . '.php', // Single User Subs ID
		'subscriptions-' . $nicename  . '.php',            // Subscriptions nicename
		'subscriptions-' . $user_id   . '.php',            // Subscriptions ID
		'subscriptions.php',                               // Subscriptions
		'user.php',                                        // User
	);
	return bbp_get_query_template( 'subscriptions', $templates );
}

/**
 * Get the view template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return string Path to template file.
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
 * Get the search template.
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @return string Path to template file.
 */
function bbp_get_search_template() {
	$templates = array(
		'page-forum-search.php', // Single Search
		'forum-search.php',      // Search
	);
	return bbp_get_query_template( 'single_search', $templates );
}

/**
 * Get the single forum template.
 *
 * @since 2.1.0 bbPress (r3922)
 *
 * @return string Path to template file.
 */
function bbp_get_single_forum_template() {
	$templates = array(
		'single-' . bbp_get_forum_post_type() . '.php' // Single Forum
	);
	return bbp_get_query_template( 'single_forum', $templates );
}

/**
 * Get the forum archive template.
 *
 * @since 2.1.0 bbPress (r3922)
 *
 * @return string Path to template file.
 */
function bbp_get_forum_archive_template() {
	$templates = array(
		'archive-' . bbp_get_forum_post_type() . '.php' // Forum Archive
	);
	return bbp_get_query_template( 'forum_archive', $templates );
}

/**
 * Get the forum edit template.
 *
 * @since 2.1.0 bbPress (r3566)
 *
 * @return string Path to template file.
 */
function bbp_get_forum_edit_template() {
	$templates = array(
		'single-' . bbp_get_forum_post_type() . '-edit.php' // Single Forum Edit
	);
	return bbp_get_query_template( 'forum_edit', $templates );
}

/**
 * Get the single topic template.
 *
 * @since 2.1.0 bbPress (r3922)
 *
 * @return string Path to template file.
 */
function bbp_get_single_topic_template() {
	$templates = array(
		'single-' . bbp_get_topic_post_type() . '.php'
	);
	return bbp_get_query_template( 'single_topic', $templates );
}

/**
 * Get the topic archive template.
 *
 * @since 2.1.0 bbPress (r3922)
 *
 * @return string Path to template file.
 */
function bbp_get_topic_archive_template() {
	$templates = array(
		'archive-' . bbp_get_topic_post_type() . '.php' // Topic Archive
	);
	return bbp_get_query_template( 'topic_archive', $templates );
}

/**
 * Get the topic edit template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return string Path to template file.
 */
function bbp_get_topic_edit_template() {
	$templates = array(
		'single-' . bbp_get_topic_post_type() . '-edit.php' // Single Topic Edit
	);
	return bbp_get_query_template( 'topic_edit', $templates );
}

/**
 * Get the topic split template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return string Path to template file.
 */
function bbp_get_topic_split_template() {
	$templates = array(
		'single-' . bbp_get_topic_post_type() . '-split.php', // Topic Split
	);
	return bbp_get_query_template( 'topic_split', $templates );
}

/**
 * Get the topic merge template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return string Path to template file.
 */
function bbp_get_topic_merge_template() {
	$templates = array(
		'single-' . bbp_get_topic_post_type() . '-merge.php', // Topic Merge
	);
	return bbp_get_query_template( 'topic_merge', $templates );
}

/**
 * Get the single reply template.
 *
 * @since 2.1.0 bbPress (r3922)
 *
 * @return string Path to template file.
 */
function bbp_get_single_reply_template() {
	$templates = array(
		'single-' . bbp_get_reply_post_type() . '.php'
	);
	return bbp_get_query_template( 'single_reply', $templates );
}

/**
 * Get the reply edit template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
* @return string Path to template file.
 */
function bbp_get_reply_edit_template() {
	$templates = array(
		'single-' . bbp_get_reply_post_type() . '-edit.php' // Single Reply Edit
	);
	return bbp_get_query_template( 'reply_edit', $templates );
}

/**
 * Get the reply move template.
 *
 * @since 2.3.0 bbPress (r4521)
 *
 * @return string Path to template file.
 */
function bbp_get_reply_move_template() {
	$templates = array(
		'single-' . bbp_get_reply_post_type() . '-move.php', // Reply move
	);
	return bbp_get_query_template( 'reply_move', $templates );
}

/**
 * Get the topic template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return string Path to template file.
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
 * Get the topic edit template.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @return string Path to template file.
 */
function bbp_get_topic_tag_edit_template() {
	$tt_slug   = bbp_get_topic_tag_slug();
	$tt_id     = bbp_get_topic_tag_tax_id();
	$templates = array(
		'taxonomy-' . $tt_slug . '-edit.php', // Single Topic Tag Edit slug
		'taxonomy-' . $tt_id   . '-edit.php'  // Single Topic Tag Edit ID
	);
	return bbp_get_query_template( 'topic_tag_edit', $templates );
}

/**
 * Get the template to use as the wrapper for bbPress template parts.
 *
 * @since 2.7.0 bbPress (r7393)
 *
 * @return string Path to template file.
 */
function bbp_get_theme_compat_template() {
	$templates = array(
		'plugin-bbpress.php',
		'bbpress.php',
		'forums.php',
		'forum.php',
		'generic.php',
		'page.php',
		'single.php',
		'singular.php',
		'index.php'
	);
	return bbp_get_query_template( 'bbpress', $templates );
}

/**
 * Get the theme canvas template, used for Block Themes.
 *
 * @since 2.7.0 bbPress (r7383)
 *
 * @return string Path to canvas file.
 */
function bbp_get_theme_canvas_template() {

	// Default WordPress Canvas
	$template = ABSPATH . WPINC . '/template-canvas.php';

	// Filter & return
	return apply_filters( 'bbp_get_theme_canvas_template', $template );
}

/**
 * Get the array of _is_ functions and their template finders.
 *
 * This function abstracts the template checkers & getters to allow third-party
 * plugins to add their own bbPress component functionality more easily.
 *
 * @since 2.7.0 bbPress (r7393)
 *
 * @return array Key => Value array of checkers & getters.
 */
function bbp_get_template_include_templates() {

	// Possible templates: checker => getter
	$templates = array(

		// User
		'bbp_is_single_user_edit' => 'bbp_get_single_user_edit_template',
		'bbp_is_favorites'        => 'bbp_get_favorites_template',
		'bbp_is_subscriptions'    => 'bbp_get_subscriptions_template',
		'bbp_is_single_user'      => 'bbp_get_single_user_template',

		// Topic view
		'bbp_is_single_view'      => 'bbp_get_single_view_template',

		// Search
		'bbp_is_search'           => 'bbp_get_search_template',

		// Forum
		'bbp_is_forum_edit'       => 'bbp_get_forum_edit_template',
		'bbp_is_single_forum'     => 'bbp_get_single_forum_template',
		'bbp_is_forum_archive'    => 'bbp_get_forum_archive_template',

		// Topic
		'bbp_is_topic_merge'      => 'bbp_get_topic_merge_template',
		'bbp_is_topic_split'      => 'bbp_get_topic_split_template',
		'bbp_is_topic_edit'       => 'bbp_get_topic_edit_template',
		'bbp_is_single_topic'     => 'bbp_get_single_topic_template',
		'bbp_is_topic_archive'    => 'bbp_get_topic_archive_template',

		// Reply
		'bbp_is_reply_move'       => 'bbp_get_reply_move_template',
		'bbp_is_reply_edit'       => 'bbp_get_reply_edit_template',
		'bbp_is_single_reply'     => 'bbp_get_single_reply_template',

		// Topic tag
		'bbp_is_topic_tag_edit'   => 'bbp_get_topic_tag_edit_template',
		'bbp_is_topic_tag'        => 'bbp_get_topic_tag_template',
	);

	// Filter & return
	return (array) apply_filters( 'bbp_get_template_include_templates', $templates );
}

/** Deprecated ****************************************************************/

/**
 * Get the templates to use as the endpoint for bbPress template parts.
 *
 * This function was deprecated because it was named plural when it should have
 * been singular, as it only returns the path to a single file.
 *
 * @since 2.0.0 bbPress (r3311)
 * @since 2.6.0 bbPress (r5950) Added `singular.php` to template stack
 * @deprecated 2.7.0 bbPress (r7393)
 *
 * @return string Path to template file.
 */
function bbp_get_theme_compat_templates() {
	return bbp_get_theme_compat_template();
}
