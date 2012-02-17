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

	// Bail if current theme does not support bbPress
	if ( !current_theme_supports( 'bbpress' ) )
		return $template;

	// Viewing a user
	if     ( bbp_is_single_user()      && ( $new_template = bbp_get_single_user_template()      ) ) :

	// Editing a user
	elseif ( bbp_is_single_user_edit() && ( $new_template = bbp_get_single_user_edit_template() ) ) :

	// Single View
	elseif ( bbp_is_single_view()      && ( $new_template = bbp_get_single_view_template()      ) ) :

	// Topic edit
	elseif ( bbp_is_forum_edit()       && ( $new_template = bbp_get_forum_edit_template()       ) ) :

	// Topic merge
	elseif ( bbp_is_topic_merge()      && ( $new_template = bbp_get_topic_merge_template()      ) ) :

	// Topic split
	elseif ( bbp_is_topic_split()      && ( $new_template = bbp_get_topic_split_template()      ) ) :

	// Topic edit
	elseif ( bbp_is_topic_edit()       && ( $new_template = bbp_get_topic_edit_template()       ) ) :

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

		// Single User nicename
		'single-user-'         . $nicename . '.php',
		'bbpress/single-user-' . $nicename . '.php',
		'forums/single-user-'  . $nicename . '.php',

		// Single User ID
		'single-user-'         . $user_id . '.php',
		'bbpress/single-user-' . $user_id . '.php',
		'forums/single-user-'  . $user_id . '.php',

		// Single User
		'single-user.php',
		'bbpress/single-user.php',
		'forums/single-user.php',

		// User
		'user.php',
		'bbpress/user.php',
		'forums/user.php',
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

		// Single User nicename
		'single-user-edit-'         . $nicename . '.php',
		'bbpress/single-user-edit-' . $nicename . '.php',
		'forums/single-user-edit-'  . $nicename . '.php',

		// Single User Edit ID
		'single-user-edit-'         . $user_id . '.php',
		'bbpress/single-user-edit-' . $user_id . '.php',
		'forums/single-user-edit-'  . $user_id . '.php',

		// Single User Edit
		'single-user-edit.php',
		'bbpress/single-user-edit.php',
		'forums/single-user-edit.php',

		// User Edit
		'user-edit.php',
		'bbpress/user-edit.php',
		'forums/user-edit.php',

		// User
		'forums/user.php',
		'bbpress/user.php',
		'user.php',
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

		// Single View ID
		'single-view-'         . $view_id . '.php',
		'bbpress/single-view-' . $view_id . '.php',
		'forums/single-view-'  . $view_id . '.php',

		// View ID
		'view-'         . $view_id . '.php',
		'bbpress/view-' . $view_id . '.php',
		'forums/view-'  . $view_id . '.php',

		// Single View
		'single-view.php',
		'bbpress/single-view.php',
		'forums/single-view.php',

		// View
		'view.php',
		'bbpress/view.php',
		'forums/view.php',
	);

	return bbp_get_query_template( 'single_view', $templates );
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

		// Single Forum Edit
		'single-'         . $post_type . '-edit.php',
		'bbpress/single-' . $post_type . '-edit.php',
		'forums/single-'  . $post_type . '-edit.php',

		// Single Forum
		'single-'         . $post_type . '.php',
		'forums/single-'  . $post_type . '.php',
		'bbpress/single-' . $post_type . '.php',
	);

	return bbp_get_query_template( 'forum_edit', $templates );
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

		// Single Topic Edit
		'single-'         . $post_type . '-edit.php',
		'bbpress/single-' . $post_type . '-edit.php',
		'forums/single-'  . $post_type . '-edit.php',

		// Single Topic
		'single-'         . $post_type . '.php',
		'forums/single-'  . $post_type . '.php',
		'bbpress/single-' . $post_type . '.php',
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

		// Topic Split
		'single-'         . $post_type . '-split.php',
		'bbpress/single-' . $post_type . '-split.php',
		'forums/single-'  . $post_type . '-split.php',
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

		// Topic Merge
		'single-'         . $post_type . '-merge.php',
		'bbpress/single-' . $post_type . '-merge.php',
		'forums/single-'  . $post_type . '-merge.php',
	);

	return bbp_get_query_template( 'topic_merge', $templates );
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

		// Single Reply Edit
		'single-'         . $post_type . '-edit.php',
		'bbpress/single-' . $post_type . '-edit.php',
		'forums/single-'  . $post_type . '-edit.php',

		// Single Reply
		'single-'         . $post_type . '.php',
		'forums/single-'  . $post_type . '.php',
		'bbpress/single-' . $post_type . '.php',
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

		// Single Topic Tag
		'taxonomy-'         . $tt_slug . '.php',
		'forums/taxonomy-'  . $tt_slug . '.php',
		'bbpress/taxonomy-' . $tt_slug . '.php',
		
		'taxonomy-'         . $tt_id . '.php',
		'forums/taxonomy-'  . $tt_id . '.php',
		'bbpress/taxonomy-' . $tt_id . '.php',
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

		// Single Topic Tag Edit
		'taxonomy-'         . $tt_slug . '-edit.php',
		'bbpress/taxonomy-' . $tt_slug . '-edit.php',
		'forums/taxonomy-'  . $tt_slug . '-edit.php',

		'taxonomy-'         . $tt_id . '-edit.php',
		'bbpress/taxonomy-' . $tt_id . '-edit.php',
		'forums/taxonomy-'  . $tt_id . '-edit.php',

		// Single Topic Tag
		'taxonomy-'         . $tt_slug . '.php',
		'forums/taxonomy-'  . $tt_slug . '.php',
		'bbpress/taxonomy-' . $tt_slug . '.php',
		
		'taxonomy-'         . $tt_id . '.php',
		'forums/taxonomy-'  . $tt_id . '.php',
		'bbpress/taxonomy-' . $tt_id . '.php',
	);

	return bbp_get_query_template( 'topic_tag_edit', $templates );
}

/**
 * Get the files to fallback on to use for theme compatibility
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
