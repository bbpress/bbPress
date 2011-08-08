<?php

/**
 * bbPress Core Theme Compatibility
 *
 * @package bbPress
 * @subpackage ThemeCompatibility
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Theme Compat **************************************************************/

/**
 * What follows is an attempt at intercepting the natural page load process
 * to replace the_content() with the appropriate bbPress content.
 *
 * To do this, bbPress does several direct manipulations of global variables
 * and forces them to do what they are not supposed to be doing.
 *
 * Don't try anything you're about to witness here, at home. Ever.
 */

/**
 * Set the theme compat theme URL and DIR
 *
 * @since bbPress (r3311)
 *
 * @global bbPress $bbp
 * @param string $theme
 * @uses current_theme_supports()
 */
function bbp_setup_theme_compat( $theme = array() ) {
	global $bbp;

	// Check if current theme supports bbPress
	if ( empty( $bbp->theme_compat->theme ) && !current_theme_supports( 'bbpress' ) ) {
		if ( empty( $theme ) ) {
			$theme = array(
				'dir' => $bbp->themes_dir . '/bbp-twentyten',
				'url' => $bbp->themes_url . '/bbp-twentyten'
			);
		}

		// Set the theme compat globals for help with loading template parts
		$bbp->theme_compat->theme = $theme;
	}
}

/**
 * If not using a bbPress compatable theme, enqueue some basic styling and js
 *
 * @since bbPress (r3029)
 *
 * @uses bbp_set_compat_theme_dir() Set the compatable theme to bbp-twentyten
 * @uses current_theme_supports() Check bbPress theme support
 * @uses wp_enqueue_style() Enqueue the bbp-twentyten default CSS
 * @uses wp_enqueue_script() Enqueue the bbp-twentyten default topic JS
 */
function bbp_theme_compat_enqueue_css() {

	// Check if current theme supports bbPress
	if ( !current_theme_supports( 'bbpress' ) ) {

		/** Default CSS ***************************************************/

		// Version of CSS
		$version = '20110808b';

		// Right to left
		if ( is_rtl() ) {
			wp_enqueue_style( 'bbpress-style', bbp_get_theme_compat_url() . '/css/bbpress-rtl.css', '', $version, 'screen' );

		// Left to right
		} else {
			wp_enqueue_style( 'bbpress-style', bbp_get_theme_compat_url() . '/css/bbpress.css',     '', $version, 'screen' );
		}
	}
}

/**
 * Adds bbPress theme support to any active WordPress theme
 *
 * This function is really cool because it's responsible for managing the
 * theme compatability layer when the current theme does not support bbPress.
 * It uses the current_theme_supports() WordPress function to see if 'bbpress'
 * is explicitly supported. If not, it will directly load the requested template
 * part using load_template(). If so, it proceeds with using get_template_part()
 * as per normal, and no one is the wiser.
 *
 * @since bbPress (r3032)
 *
 * @param string $slug
 * @param string $name Optional. Default null
 * @uses current_theme_supports()
 * @uses load_template()
 * @uses get_template_part()
 */
function bbp_get_template_part( $slug, $name = null ) {

	// Current theme does not support bbPress, so we need to do some heavy
	// lifting to see if a bbPress template is needed in the current context
	if ( !current_theme_supports( 'bbpress' ) )
		load_template( bbp_get_theme_compat_dir() . '/' . $slug . '-' . $name . '.php', false );

	// Current theme supports bbPress to proceed as usual
	else
		get_template_part( $slug, $name );

}

/**
 * Gets the bbPress compatable theme used in the event the currently active
 * WordPress theme does not explicitly support bbPress. This can be filtered,
 * or set manually. Tricky theme authors can override the default and include
 * their own bbPress compatability layers for their themes.
 *
 * @since bbPress (r3032)
 *
 * @global bbPress $bbp
 * @uses apply_filters()
 * @return string
 */
function bbp_get_theme_compat_dir() {
	global $bbp;

	return apply_filters( 'bbp_get_theme_compat_dir', $bbp->theme_compat->theme['dir'] );
}

/**
 * Gets the bbPress compatable theme used in the event the currently active
 * WordPress theme does not explicitly support bbPress. This can be filtered,
 * or set manually. Tricky theme authors can override the default and include
 * their own bbPress compatability layers for their themes.
 *
 * @since bbPress (r3032)
 *
 * @global bbPress $bbp
 * @uses apply_filters()
 * @return string
 */
function bbp_get_theme_compat_url() {
	global $bbp;

	return apply_filters( 'bbp_get_theme_compat_url', $bbp->theme_compat->theme['url'] );
}

/**
 * Gets true/false if page is currently inside theme compatibility
 *
 * @since bbPress (r3265)
 *
 * @global bbPress $bbp
 *
 * @return bool
 */
function bbp_is_theme_compat_active() {
	global $bbp;

	if ( empty( $bbp->theme_compat->active ) )
		return false;

	return $bbp->theme_compat->active;
}

/**
 * Sets true/false if page is currently inside theme compatibility
 *
 * @since bbPress (r3265)
 *
 * @global bbPress $bbp
 *
 * @param bool $set
 *
 * @return bool
 */
function bbp_set_theme_compat_active( $set = true ) {
	global $bbp;

	$bbp->theme_compat->active = $set;

	return (bool) $bbp->theme_compat->active;
}

/**
 * Set the theme compat templates global
 *
 * Stash possible template files for the current query. Useful if plugins want
 * to override them, or see what files are being scanned for inclusion.
 *
 * @since bbPress (r3311)
 *
 * @global $bbp;
 */
function bbp_set_theme_compat_templates( $templates = array() ) {
	global $bbp;

	$bbp->theme_compat->templates = $templates;

	return $bbp->theme_compat->templates;
}

/**
 * Set the theme compat template global
 *
 * Stash the template file for the current query. Useful if plugins want
 * to override it, or see what file is being included.
 *
 * @since bbPress (r3311)
 *
 * @global $bbp;
 */
function bbp_set_theme_compat_template( $template = '' ) {
	global $bbp;

	$bbp->theme_compat->template = $template;

	return $bbp->theme_compat->template;
}

/**
 * This fun little function fills up some WordPress globals with dummy data to
 * stop your average page template from complaining about it missing.
 *
 * @since bbPress (r3108)
 *
 * @global WP_Query $wp_query
 * @global object $post
 * @param array $args
 */
function bbp_theme_compat_reset_post( $args = array() ) {
	global $wp_query, $post;

	// Why would you ever want to do this otherwise?
	if ( current_theme_supports( 'bbpress' ) )
		wp_die( __( 'Hands off, partner!', 'bbpress' ) );

	// Default for current post
	if ( isset( $wp_query->post ) ) {
		$defaults = array(
			'ID'           => get_the_ID(),
			'post_title'   => get_the_title(),
			'post_author'  => get_the_author_meta('ID'),
			'post_date'    => get_the_date(),
			'post_content' => get_the_content(),
			'post_type'    => get_post_type(),
			'post_status'  => get_post_status(),
			'is_404'       => false,
			'is_page'      => false,
			'is_single'    => false,
			'is_archive'   => false,
			'is_tax'       => false,
		);

	// Empty defaults
	} else {
		$defaults = array(
			'ID'           => 0,
			'post_title'   => '',
			'post_author'  => 0,
			'post_date'    => 0,
			'post_content' => '',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'is_404'       => false,
			'is_page'      => false,
			'is_single'    => false,
			'is_archive'   => false,
			'is_tax'       => false,
		);
	}
	$dummy = wp_parse_args( $args, $defaults );

	// Clear out the post related globals
	unset( $wp_query->posts );
	unset( $wp_query->post  );
	unset( $post            );

	// Setup the dummy post object
	$wp_query->post->ID           = $dummy['ID'];
	$wp_query->post->post_title   = $dummy['post_title'];
	$wp_query->post->post_author  = $dummy['post_author'];
	$wp_query->post->post_date    = $dummy['post_date'];
	$wp_query->post->post_content = $dummy['post_content'];
	$wp_query->post->post_type    = $dummy['post_type'];
	$wp_query->post->post_status  = $dummy['post_status'];

	// Set the $post global
	$post = $wp_query->post;

	// Setup the dummy post loop
	$wp_query->posts[0] = $wp_query->post;

	// Prevent comments form from appearing
	$wp_query->post_count = 1;
	$wp_query->is_404     = $dummy['is_404'];
	$wp_query->is_page    = $dummy['is_page'];
	$wp_query->is_single  = $dummy['is_single'];
	$wp_query->is_archive = $dummy['is_archive'];
	$wp_query->is_tax     = $dummy['is_tax'];

	// If we are resetting a post, we are in theme compat
	bbp_set_theme_compat_active();
}

/** Templates *****************************************************************/

/**
 * Get the user profile template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_displayed_user_id()
 * @uses apply_filters()
 *
 * @return array
 */
function bbp_get_single_user_template() {

	$displayed = bbp_get_displayed_user_id();
	$templates = array(

		// Single User ID
		'single-user-'         . $displayed . '.php',
		'bbpress/single-user-' . $displayed . '.php',
		'forums/single-user-'  . $displayed . '.php',

		// Single User
		'single-user.php',
		'bbpress/single-user.php',
		'forums/single-user.php',

		// User
		'user.php',
		'bbpress/user.php',
		'forums/user.php',
	);

	$templates = apply_filters( 'bbp_get_profile_template', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

/**
 * Get the user profile edit template
 *
 * @since bbPress (r3311)
 *
 * @uses $displayed
 * @uses apply_filters()
 *
 * @return array
 */
function bbp_get_single_user_edit_template() {

	$displayed = bbp_get_displayed_user_id();
	$templates = array(

		// Single User Edit ID
		'single-user-edit-'         . $displayed . '.php',
		'bbpress/single-user-edit-' . $displayed . '.php',
		'forums/single-user-edit-'  . $displayed . '.php',

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

	$templates = apply_filters( 'bbp_get_profile_edit_template', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

/**
 * Get the view template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_view_id()
 * @uses apply_filters()
 *
 * @return array
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

	$templates = apply_filters( 'bbp_get_single_view_template', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

/**
 * Get the topic edit template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_post_type()
 * @uses apply_filters()
 *
 * @return array
 */
function bbp_get_topic_edit_template() {

	$post_type = bbp_get_topic_post_type();
	$templates = array(

		// Single Topic Edit
		'single-'         . $post_type . '-edit.php',
		'bbpress/single-' . $post_type . '-edit.php',
		'forums/single-'  . $post_type . '-edit.php',

		// Single Action Edit Topic
		'single-action-edit-'         . $post_type . '.php',
		'bbpress/single-action-edit-' . $post_type . '.php',
		'forums/single-action-edit-'  . $post_type . '.php',

		// Single Action Edit
		'single-action-edit.php',
		'bbpress/single-action-edit.php',
		'forums/single-action-edit.php',

		// Action Edit
		'action-edit.php',
		'bbpress/action-edit.php',
		'forums/action-edit.php',

		// Single Topic
		'single-'         . $post_type . '.php',
		'forums/single-'  . $post_type . '.php',
		'bbpress/single-' . $post_type . '.php',
	);

	$templates = apply_filters( 'bbp_get_topic_edit_template', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

/**
 * Get the topic split template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_post_type()
 * @uses apply_filters()
 *
 * @return array
 */
function bbp_get_topic_split_template() {

	$post_type = bbp_get_topic_post_type();
	$templates = array(

		// Topic Split
		'single-'         . $post_type . '-split.php',
		'bbpress/single-' . $post_type . '-split.php',
		'forums/single-'  . $post_type . '-split.php',

		// Action Split
		'single-action-split-merge.php',
		'bbpress/single-action-split-merge.php',
		'forums/single-action-split-merge.php',

		// Action Split
		'action-split-merge.php',
		'bbpress/action-split-merge.php',
		'forums/action-split-merge.php'
	);

	$templates = apply_filters( 'bbp_get_topic_split_template', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

/**
 * Get the topic merge template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_post_type()
 * @uses apply_filters()
 *
 * @return array
 */
function bbp_get_topic_merge_template() {

	$post_type = bbp_get_topic_post_type();
	$templates = array(

		// Topic Merge
		'single-'         . $post_type . '-merge.php',
		'bbpress/single-' . $post_type . '-merge.php',
		'forums/single-'  . $post_type . '-merge.php',

		// Action Merge
		'single-action-split-merge.php',
		'bbpress/single-action-split-merge.php',
		'forums/single-action-split-merge.php',

		// Action Merge
		'action-split-merge.php',
		'bbpress/action-split-merge.php',
		'forums/action-split-merge.php'
	);

	$templates = apply_filters( 'bbp_get_topic_merge_template', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

/**
 * Get the reply edit template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_reply_post_type()
 * @uses apply_filters()
 *
 * @return array
 */
function bbp_get_reply_edit_template() {

	$post_type = bbp_get_reply_post_type();
	$templates = array(

		// Single Reply Edit
		'single-'         . $post_type . '-edit.php',
		'bbpress/single-' . $post_type . '-edit.php',
		'forums/single-'  . $post_type . '-edit.php',

		// Single Action Edit Reply
		'single-action-edit-'         . $post_type . '.php',
		'bbpress/single-action-edit-' . $post_type . '.php',
		'forums/single-action-edit-'  . $post_type . '.php',

		// Single Action Edit
		'single-action-edit.php',
		'bbpress/single-action-edit.php',
		'forums/single-action-edit.php',

		// Action Edit
		'action-edit.php',
		'bbpress/action-edit.php',
		'forums/action-edit.php',

		// Single Reply
		'single-'         . $post_type . '.php',
		'forums/single-'  . $post_type . '.php',
		'bbpress/single-' . $post_type . '.php',
	);

	$templates = apply_filters( 'bbp_get_reply_edit_template', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

/**
 * Get the topic edit template
 *
 * @since bbPress (r3311)
 *
 * @uses bbp_get_topic_post_type()
 * @uses apply_filters()
 *
 * @return array
 */
function bbp_get_topic_tag_edit_template() {

	$tt_id = bbp_get_topic_tag_tax_id();
	$templates = array(

		// Single Topic Tag Edit
		'taxonomy-'         . $tt_id . '-edit.php',
		'bbpress/taxonomy-' . $tt_id . '-edit.php',
		'forums/taxonomy-'  . $tt_id . '-edit.php',

		// Single Topic Tag
		'taxonomy-'         . $tt_id . '.php',
		'forums/taxonomy-'  . $tt_id . '.php',
		'bbpress/taxonomy-' . $tt_id . '.php',
	);

	$templates = apply_filters( 'bbp_get_topic_tag_edit_template', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

/**
 * Get the files to fallback on to use for theme compatibility
 *
 * @since bbPress (r3311)
 *
 * @uses apply_filters()
 * @uses bbp_set_theme_compat_templates();
 *
 * @return type
 */
function bbp_get_theme_compat_templates() {

	$templates = array(
		'bbpress.php',
		'forum.php',
		'page.php',
		'single.php',
		'index.php'
	);

	$templates = apply_filters( 'bbp_get_theme_compat_templates', $templates );
	$templates = bbp_set_theme_compat_templates( $templates );

	$template  = locate_template( $templates, false, false );
	$template  = bbp_set_theme_compat_template( $template );

	return $template;
}

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
 * @global bbPress $bbp
 * @global WP_Query $post
 *
 * @param string $template
 *
 * @uses current_theme_supports() To check if theme supports bbPress
 * @uses bbp_is_single_user() To check if page is single user
 * @uses bbp_get_single_user_template() To get user template
 * @uses bbp_is_single_user_edit() To check if page is single user edit
 * @uses bbp_get_single_user_edit_template() To get user edit template
 * @uses bbp_is_single_view() To check if page is single view
 * @uses bbp_get_single_view_template() To get view template
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
	global $bbp;

	// Current theme supports bbPress
	if ( current_theme_supports( 'bbpress' ) ) {

		// Viewing a user
		if     ( bbp_is_single_user()      && ( $new_template = bbp_get_single_user_template()      ) ) :

		// Editing a user
		elseif ( bbp_is_single_user_edit() && ( $new_template = bbp_get_single_user_edit_template() ) ) :

		// Single View
		elseif ( bbp_is_single_view()      && ( $new_template = bbp_get_single_view_template()      ) ) :

		// Topic merge
		elseif ( bbp_is_topic_merge()      && ( $new_template = bbp_get_topic_merge_template()      ) ) :

		// Topic split
		elseif ( bbp_is_topic_split()      && ( $new_template = bbp_get_topic_split_template()      ) ) :

		// Topic edit
		elseif ( bbp_is_topic_edit()       && ( $new_template = bbp_get_topic_edit_template()       ) ) :

		// Editing a reply
		elseif ( bbp_is_reply_edit()       && ( $new_template = bbp_get_reply_edit_template()       ) ) :

		// Editing a topic tag
		elseif ( bbp_is_topic_tag()        && ( $new_template = bbp_get_topic_tag_template()        ) ) :

		// Editing a topic tag
		elseif ( bbp_is_topic_tag_edit()   && ( $new_template = bbp_get_topic_tag_edit_template()   ) ) :
		endif;

		// Custom template file exists
		$template = !empty( $new_template ) ? $new_template : $template;
	}

	return apply_filters( 'bbp_template_include_theme_supports', $template );
}

/**
 * In this next bit, either the current theme does not support bbPress, or
 * the theme author has incorrectly used add_theme_support( 'bbpress' )
 * and we are going to help them out by silently filling in the blanks.
 */
function bbp_template_include_theme_compat( $template = '' ) {
	global $bbp;

	if ( !current_theme_supports( 'bbpress' ) || ( !empty( $bbp->theme_compat->templates ) && empty( $bbp->theme_compat->template ) ) ) {

		/** Users *************************************************************/

		if ( bbp_is_single_user() || bbp_is_single_user_edit() ) {

			// Reset post
			bbp_theme_compat_reset_post( array(
				'post_title' => esc_attr( bbp_get_displayed_user_field( 'display_name' ) )
			) );

		/** Forums ************************************************************/

		// Forum archive
		} elseif ( bbp_is_forum_archive() ) {

			// Reset post
			bbp_theme_compat_reset_post( array(
				'ID'           => 0,
				'post_title'   => bbp_get_forum_archive_title(),
				'post_author'  => 0,
				'post_date'    => 0,
				'post_content' => '',
				'post_type'    => bbp_get_forum_post_type(),
				'post_status'  => 'publish',
				'is_archive'   => true
			) );

		/** Topics ************************************************************/

		// Topic archive
		} elseif ( bbp_is_topic_archive() ) {

			// Reset post
			bbp_theme_compat_reset_post( array(
				'ID'           => 0,
				'post_title'   => bbp_get_topic_archive_title(),
				'post_author'  => 0,
				'post_date'    => 0,
				'post_content' => '',
				'post_type'    => bbp_get_topic_post_type(),
				'post_status'  => 'publish',
				'is_archive'   => true
			) );

		// Single topic
		} elseif ( bbp_is_topic_edit() || bbp_is_topic_split() || bbp_is_topic_merge() ) {

			// Reset post
			bbp_theme_compat_reset_post( array(
				'ID'           => bbp_get_topic_id(),
				'post_title'   => bbp_get_topic_title(),
				'post_author'  => bbp_get_topic_author_id(),
				'post_date'    => 0,
				'post_content' => get_post_field( 'post_content', bbp_get_topic_id() ),
				'post_type'    => bbp_get_topic_post_type(),
				'post_status'  => bbp_get_topic_status(),
				'is_single'    => true
			) );

		/** Replies ***********************************************************/

		// Reply archive
		} elseif ( is_post_type_archive( bbp_get_reply_post_type() ) ) {

			// Reset post
			bbp_theme_compat_reset_post( array(
				'ID'           => 0,
				'post_title'   => __( 'Replies', 'bbpress' ),
				'post_author'  => 0,
				'post_date'    => 0,
				'post_content' => '',
				'post_type'    => bbp_get_reply_post_type(),
				'post_status'  => 'publish'
			) );

		// Single reply
		} elseif ( bbp_is_reply_edit() ) {

			// Reset post
			bbp_theme_compat_reset_post( array(
				'ID'           => bbp_get_reply_id(),
				'post_title'   => bbp_get_reply_title(),
				'post_author'  => bbp_get_reply_author_id(),
				'post_date'    => 0,
				'post_content' => get_post_field( 'post_content', bbp_get_reply_id() ),
				'post_type'    => bbp_get_reply_post_type(),
				'post_status'  => bbp_get_reply_status()
			) );

		/** Views *************************************************************/

		} elseif ( bbp_is_single_view() ) {

			// Reset post
			bbp_theme_compat_reset_post( array(
				'ID'           => 0,
				'post_title'   => bbp_get_view_title(),
				'post_author'  => 0,
				'post_date'    => 0,
				'post_content' => '',
				'post_type'    => '',
				'post_status'  => 'publish'
			) );


		/** Topic Tags ********************************************************/

		} elseif ( bbp_is_topic_tag_edit() ) {

			// Stash the current term in a new var
			set_query_var( 'bbp_topic_tag', get_query_var( 'term' ) );

			// Reset the post with our new title
			bbp_theme_compat_reset_post( array(
				'post_title' => sprintf( __( 'Edit Topic Tag: %s', 'bbpress' ), '<span>' . bbp_get_topic_tag_name() . '</span>' )
			) );

		} elseif ( bbp_is_topic_tag() ) {

			// Stash the current term in a new var
			set_query_var( 'bbp_topic_tag', get_query_var( 'term' ) );

			// Reset the post with our new title
			bbp_theme_compat_reset_post( array(
				'post_title' => sprintf( __( 'Topic Tag: %s', 'bbpress' ), '<span>' . bbp_get_topic_tag_name() . '</span>' )
			) );

		/** Single Forums/Topics/Replies **************************************/

		} elseif ( bbp_is_custom_post_type() ) {
			bbp_set_theme_compat_active();
		}

		/**
		 * If we are relying on bbPress's built in theme compatibility to load
		 * the proper content, we need to intercept the_content, replace the
		 * output, and display ours instead.
		 *
		 * To do this, we first remove all filters from 'the_content' and hook
		 * our own function into it, which runs a series of checks to determine
		 * the context, and then uses the built in shortcodes to output the
		 * correct results.
		 *
		 * We default to using page.php, since it's most likely to exist and
		 * should be coded to work without superfluous elements and logic, like
		 * prev/next navigation, comments, date/time, etc... You can hook into
		 * the 'bbp_template_include' filter to override page.php.
		 */
		if ( bbp_is_theme_compat_active() ) {

			// Remove all filters from the_content
			bbp_remove_all_filters( 'the_content' );

			// Add a filter on the_content late, which we will later remove
			add_filter( 'the_content', 'bbp_replace_the_content' );

			// Find the appropriate template file
			$template = bbp_get_theme_compat_templates();
		}
	}

	return apply_filters( 'bbp_template_include_theme_compat', $template );
}

/**
 * Replaces the_content() if the post_type being displayed is one that would
 * normally be handled by bbPress, but proper single page templates do not
 * exist in the currently active theme.
 *
 * @since bbPress (r3034)
 *
 * @global bbPress $bbp
 * @global WP_Query $post
 * @param string $content
 * @return type
 */
function bbp_replace_the_content( $content = '' ) {

	// Current theme does not support bbPress, so we need to do some heavy
	// lifting to see if a bbPress template is needed in the current context
	if ( !current_theme_supports( 'bbpress' ) ) {

		// Use the $post global to check it's post_type
		global $bbp;

		// Define local variable(s)
		$new_content = '';

		// Remove the filter that was added in bbp_template_include()
		remove_filter( 'the_content', 'bbp_replace_the_content' );

		// Bail if shortcodes are unset somehow
		if ( empty( $bbp->shortcodes ) )
			return $content;

		// Use shortcode API to display forums/topics/replies because they are
		// already output buffered and ready to fit inside the_content

		/** Users *************************************************************/

		// Profile View
		if ( bbp_is_single_user() ) {
			ob_start();

			bbp_get_template_part( 'bbpress/content', 'single-user' );

			$new_content = ob_get_contents();

			ob_end_clean();

		// Profile Edit
		} elseif ( bbp_is_single_user_edit() ) {
			ob_start();

			bbp_get_template_part( 'bbpress/content', 'single-user-edit' );

			$new_content = ob_get_contents();

			ob_end_clean();

		/** Forums ************************************************************/

		// Forum archive
		} elseif ( bbp_is_forum_archive() ) {

			// Page exists where this archive should be
			if ( $page = bbp_get_page_by_path( $bbp->root_slug ) ) {

				// Start output buffer
				ob_start();

				// Restore previously unset filters
				bbp_restore_all_filters( 'the_content' );

				// Grab the content of this page
				$new_content = do_shortcode( apply_filters( 'the_content', get_post_field( 'post_content', $page->ID ) ) );

				// Clean up the buffer
				ob_end_clean();

			// No page so show the archive
			} else {
				$new_content = $bbp->shortcodes->display_forum_index();
			}

		/** Topics ************************************************************/

		// Topic archive
		} elseif ( bbp_is_topic_archive() ) {

			// Page exists where this archive should be
			if ( $page = bbp_get_page_by_path( $bbp->topic_archive_slug ) ) {

				// Start output buffer
				ob_start();

				// Restore previously unset filters
				bbp_restore_all_filters( 'the_content' );

				// Grab the content of this page
				$new_content = do_shortcode( apply_filters( 'the_content', get_post_field( 'post_content', $page->ID ) ) );

				// Clean up the buffer
				ob_end_clean();


			// No page so show the archive
			} else {
				$new_content = $bbp->shortcodes->display_topic_index();
			}

		// Single topic
		} elseif ( bbp_is_topic_edit() ) {

			// Split
			if ( bbp_is_topic_split() ) {
				ob_start();

				bbp_get_template_part( 'bbpress/form', 'topic-split' );

				$new_content = ob_get_contents();

				ob_end_clean();

			// Merge
			} elseif ( bbp_is_topic_merge() ) {
				ob_start();

				bbp_get_template_part( 'bbpress/form', 'topic-merge' );

				$new_content = ob_get_contents();

				ob_end_clean();

			// Edit
			} else {
				$new_content = $bbp->shortcodes->display_topic_form();
			}

		/** Replies ***********************************************************/

		// Reply archive
		} elseif ( is_post_type_archive( bbp_get_reply_post_type() ) ) {
			//$new_content = $bbp->shortcodes->display_reply_index();

		// Reply Edit
		} elseif ( bbp_is_reply_edit() ) {
			$new_content = $bbp->shortcodes->display_reply_form();

		/** Views *************************************************************/

		} elseif ( bbp_is_single_view() ) {
			$new_content = $bbp->shortcodes->display_view( array( 'id' => get_query_var( 'bbp_view' ) ) );

		/** Topic Tags ********************************************************/

		} elseif ( get_query_var( 'bbp_topic_tag' ) ) {

			// Edit topic tag
			if ( bbp_is_topic_tag_edit() ) {
				$new_content = $bbp->shortcodes->display_topic_tag_form();

			// Show topics of tag
			} else {
				$new_content = $bbp->shortcodes->display_topics_of_tag( array( 'id' => bbp_get_topic_tag_id() ) );
			}

		/** Forums/Topics/Replies *********************************************/

		} else {

			// Check the post_type
			switch ( get_post_type() ) {

				// Single Forum
				case bbp_get_forum_post_type() :
					$new_content = $bbp->shortcodes->display_forum( array( 'id' => get_the_ID() ) );
					break;

				// Single Topic
				case bbp_get_topic_post_type() :
					$new_content = $bbp->shortcodes->display_topic( array( 'id' => get_the_ID() ) );
					break;

				// Single Reply
				case bbp_get_reply_post_type() :

					break;
			}
		}

		// Juggle the content around and try to prevent unsightly comments
		if ( !empty( $new_content ) && ( $new_content != $content ) ) {

			// Set the content to be the new content
			$content = apply_filters( 'bbp_replace_the_content', $new_content, $content );

			// Clean up after ourselves
			unset( $new_content );

			/**
			 * Supplemental hack to prevent stubborn comments_template() output.
			 *
			 * By this time we can safely assume that everything we needed from
			 * the {$post} global has been rendered into the buffer, so we're
			 * going to empty it and {$withcomments} for good measure. This has
			 * the added benefit of preventing an incorrect "Edit" link on the
			 * bottom of most popular page templates, at the cost of rendering
			 * these globals useless for the remaining page output without using
			 * wp_reset_postdata() to get that data back.
			 *
			 * @see comments_template() For why we're doing this :)
			 * @see wp_reset_postdata() If you need to get $post back
			 *
			 * Note: If a theme uses custom code to output comments, it's
			 *       possible all of this dancing around is for not.
			 *
			 * Note: If you need to keep these globals around for any special
			 *       reason, we've provided a failsafe hook to bypass this you
			 *       can put in your plugin or theme below ---v
			 *
			 *       apply_filters( 'bbp_spill_the_beans', '__return_true' );
			 */
			if ( !apply_filters( 'bbp_spill_the_beans', false ) ) {

				// Setup the chopping block
				global $post, $withcomments;

				// Empty out globals that aren't being used in this loop anymore
				$withcomments = $post = false;
			}
		}
	}

	// Return possibly hi-jacked content
	return $content;
}

/** Helpers *******************************************************************/

/**
 * Remove the canonical redirect to allow pretty pagination
 *
 * @since bbPress (r2628)
 *
 * @param string $redirect_url Redirect url
 * @uses WP_Rewrite::using_permalinks() To check if the blog is using permalinks
 * @uses bbp_get_paged() To get the current page number
 * @uses bbp_is_single_topic() To check if it's a topic page
 * @uses bbp_is_single_forum() To check if it's a forum page
 * @return bool|string False if it's a topic/forum and their first page,
 *                      otherwise the redirect url
 */
function bbp_redirect_canonical( $redirect_url ) {
	global $wp_rewrite;

	// Canonical is for the beautiful
	if ( $wp_rewrite->using_permalinks() ) {

		// Only if paginating
		if ( 1 < bbp_get_paged() ) {

			// Only on single topics...
			if ( bbp_is_single_topic() ) {
				$redirect_url = false;

			// ...and single replies
			} elseif ( bbp_is_single_forum() ) {
				$redirect_url = false;
			}
		}
	}

	return $redirect_url;
}

/**
 * Sets the 404 status.
 *
 * Used primarily with topics/replies inside hidden forums.
 *
 * @since bbPress (r3051)
 *
 * @global WP_Query $wp_query
 * @uses WP_Query::set_404()
 */
function bbp_set_404() {
	global $wp_query;

	if ( ! isset( $wp_query ) ) {
		_doing_it_wrong( __FUNCTION__, __( 'Conditional query tags do not work before the query is run. Before then, they always return false.' ), '3.1' );
		return false;
	}

	$wp_query->set_404();
}

/**
 * Used to guess if page exists at requested path
 *
 * @since bbPress (r3304)
 *
 * @uses get_option() To see if pretty permalinks are enabled
 * @uses get_page_by_path() To see if page exists at path
 *
 * @param string $path
 * @return mixed False if no page, Page object if true
 */
function bbp_get_page_by_path( $path = '' ) {

	// Default to false
	$retval = false;

	// Path is not empty
	if ( !empty( $path ) ) {

		// Pretty permalinks are on so path might exist
		if ( get_option( 'permalink_structure' ) ) {
			$retval = get_page_by_path( $path );
		}
	}

	return apply_filters( 'bbp_get_page_by_path', $retval, $path );
}

/** Filters *******************************************************************/

/**
 * Removes all filters from a WordPress filter, and stashes them in the $bbp
 * global in the event they need to be restored later.
 *
 * @since bbPress (r3251)
 *
 * @global bbPress $bbp
 * @global WP_filter $wp_filter
 * @global array $merged_filters
 *
 * @param string $tag
 * @param int $priority
 *
 * @return bool
 */
function bbp_remove_all_filters( $tag, $priority = false ) {
	global $bbp, $wp_filter, $merged_filters;

	// Filters exist
	if ( isset( $wp_filter[$tag] ) ) {

		// Filters exist in this priority
		if ( !empty( $priority ) && isset( $wp_filter[$tag][$priority] ) ) {

			// Store filters in a backup
			$bbp->filters->wp_filter[$tag][$priority] = $wp_filter[$tag][$priority];

			// Unset the filters
			unset( $wp_filter[$tag][$priority] );

		// Priority is empty
		} else {

			// Store filters in a backup
			$bbp->filters->wp_filter[$tag] = $wp_filter[$tag];

			// Unset the filters
			unset( $wp_filter[$tag] );
		}
	}

	// Check merged filters
	if ( isset( $merged_filters[$tag] ) ) {

		// Store filters in a backup
		$bbp->filters->merged_filters[$tag] = $merged_filters[$tag];

		// Unset the filters
		unset( $merged_filters[$tag] );
	}

	return true;
}

/**
 * Restores filters from the $bbp global that were removed using
 * bbp_remove_all_filters()
 *
 * @since bbPress (r3251)
 *
 * @global bbPress $bbp
 * @global WP_filter $wp_filter
 * @global array $merged_filters
 *
 * @param string $tag
 * @param int $priority
 *
 * @return bool
 */
function bbp_restore_all_filters( $tag, $priority = false ) {
	global $bbp, $wp_filter, $merged_filters;

	// Filters exist
	if ( isset( $bbp->filters->wp_filter[$tag] ) ) {

		// Filters exist in this priority
		if ( !empty( $priority ) && isset( $bbp->filters->wp_filter[$tag][$priority] ) ) {

			// Store filters in a backup
			$wp_filter[$tag][$priority] = $bbp->filters->wp_filter[$tag][$priority];

			// Unset the filters
			unset( $bbp->filters->wp_filter[$tag][$priority] );

		// Priority is empty
		} else {

			// Store filters in a backup
			$wp_filter[$tag] = $bbp->filters->wp_filter[$tag];

			// Unset the filters
			unset( $bbp->filters->wp_filter[$tag] );
		}
	}

	// Check merged filters
	if ( isset( $bbp->filters->merged_filters[$tag] ) ) {

		// Store filters in a backup
		$merged_filters[$tag] = $bbp->filters->merged_filters[$tag];

		// Unset the filters
		unset( $bbp->filters->merged_filters[$tag] );
	}

	return true;
}

/**
 * Add checks for view page, user page, user edit, topic edit and reply edit
 * pages.
 *
 * If it's a user page, WP_Query::bbp_is_single_user is set to true.
 * If it's a user edit page, WP_Query::bbp_is_single_user_edit is set to true
 * and the the 'wp-admin/includes/user.php' file is included.
 * In addition, on user/user edit pages, WP_Query::home is set to false & query
 * vars 'bbp_user_id' with the displayed user id and 'author_name' with the
 * displayed user's nicename are added.
 *
 * If it's a topic edit, WP_Query::bbp_is_topic_edit is set to true and
 * similarly, if it's a reply edit, WP_Query::bbp_is_reply_edit is set to true.
 *
 * If it's a view page, WP_Query::bbp_is_view is set to true
 *
 * @since bbPress (r2688)
 *
 * @global bbPress $bbp
 * #global WP_Query $wp_query
 *
 * @uses get_query_var() To get {@link WP_Query} query var
 * @uses is_email() To check if the string is an email
 * @uses get_user_by() To try to get the user by email and nicename
 * @uses WP_User to get the user data
 * @uses WP_Query::set_404() To set a 404 status
 * @uses current_user_can() To check if the current user can edit the user
 * @uses apply_filters() Calls 'enable_edit_any_user_configuration' with true
 * @uses wp_die() To die
 * @uses bbp_is_query_name() Check if query name is 'bbp_widget'
 * @uses bbp_get_view_query_args() To get the view query args
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses is_multisite() To check if it's a multisite
 * @uses remove_action() To remove the auto save post revision action
 */
function bbp_pre_get_posts( $posts_query ) {
	global $bbp, $wp_the_query;

	// Bail if $posts_query is not the main loop
	if ( $posts_query != $wp_the_query )
		return $posts_query;

	// Bail if filters are suppressed on this query, or in admin
	if ( true == $posts_query->get( 'suppress_filters' ) )
		return $posts_query;

	// Bail if in admin
	if ( is_admin() )
		return $posts_query;

	// Get query variables
	$bbp_user = $posts_query->get( $bbp->user_id );
	$bbp_view = $posts_query->get( $bbp->view_id );
	$is_edit  = $posts_query->get( $bbp->edit_id );

	// It is a user page - We'll also check if it is user edit
	if ( !empty( $bbp_user ) ) {

		// Not a user_id so try email and slug
		if ( !is_numeric( $bbp_user ) ) {

			// Email was passed
			if ( is_email( $bbp_user ) )
				$bbp_user = get_user_by( 'email', $bbp_user );
			// Try nicename
			else
				$bbp_user = get_user_by( 'slug', $bbp_user );

			// If we were successful, set to ID
			if ( is_object( $bbp_user ) )
				$bbp_user = $bbp_user->ID;
		}

		// Create new user
		$user = new WP_User( $bbp_user );

		// Stop if no user
		if ( !isset( $user ) || empty( $user ) || empty( $user->ID ) ) {
			$posts_query->set_404();
			return;
		}

		/** User Exists *******************************************************/

		// View or edit?
		if ( !empty( $is_edit ) ) {

			// Only allow super admins on multisite to edit every user.
			if ( ( is_multisite() && !current_user_can( 'manage_network_users' ) && $user_id != $current_user->ID && !apply_filters( 'enable_edit_any_user_configuration', true ) ) || !current_user_can( 'edit_user', $user->ID ) )
				wp_die( __( 'You do not have the permission to edit this user.', 'bbpress' ) );

			// We are editing a profile
			$posts_query->bbp_is_single_user_edit = true;

			// Load the core WordPress contact methods
			if ( !function_exists( '_wp_get_user_contactmethods' ) )
				include_once( ABSPATH . 'wp-includes/registration.php' );

			// Load the edit_user functions
			if ( !function_exists( 'edit_user' ) )
				require_once( ABSPATH . 'wp-admin/includes/user.php' );

		// We are viewing a profile
		} else {
			$posts_query->bbp_is_single_user = true;
		}

		// Make sure 404 is not set
		$posts_query->is_404  = false;

		// Correct is_home variable
		$posts_query->is_home = false;

		// Set bbp_user_id for future reference
		$posts_query->query_vars['bbp_user_id'] = $user->ID;

		// Set author_name as current user's nicename to get correct posts
		if ( !bbp_is_query_name( 'bbp_widget' ) )
			$posts_query->query_vars['author_name'] = $user->user_nicename;

		// Set the displayed user global to this user
		$bbp->displayed_user = $user;

	// View Page
	} elseif ( !empty( $bbp_view ) ) {

		// Check if the view exists by checking if there are query args are set
		$view_args = bbp_get_view_query_args( $bbp_view );

		// Stop if view args is false - means the view isn't registered
		if ( false === $view_args ) {
			$posts_query->set_404();
			return;
		}

		// Correct is_home variable
		$posts_query->is_home     = false;

		// We are in a custom topic view
		$posts_query->bbp_is_view = true;

	// Topic/Reply Edit Page
	} elseif ( !empty( $is_edit ) ) {

		// We are editing a topic
		if ( $posts_query->get( 'post_type' ) == bbp_get_topic_post_type() )
			$posts_query->bbp_is_topic_edit = true;

		// We are editing a reply
		elseif ( $posts_query->get( 'post_type' ) == bbp_get_reply_post_type() )
			$posts_query->bbp_is_reply_edit = true;

		// We are editing a topic tag
		elseif ( bbp_is_topic_tag() )
			$posts_query->bbp_is_topic_tag_edit = true;

		// We save post revisions on our own
		remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Topic tag page
	} elseif ( bbp_is_topic_tag() ) {
		$posts_query->query_vars['post_type']      = bbp_get_topic_post_type();
		$posts_query->query_vars['posts_per_page'] = get_option( '_bbp_topics_per_page', 15 );
	}

	return $posts_query;
}

?>
