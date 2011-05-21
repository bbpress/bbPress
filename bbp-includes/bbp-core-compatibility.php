<?php

/**
 * bbPress Core Theme Compatibility
 *
 * @package bbPress
 * @subpackage ThemeCompatibility
 */

// Redirect if accessed directly
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
		load_template( bbp_get_theme_compat() . '/' . $slug . '-' . $name . '.php', false );

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
function bbp_get_theme_compat() {
	global $bbp;

	return apply_filters( 'bbp_get_theme_compat', $bbp->theme_compat );
}

/**
 * Sets the bbPress compatable theme used in the event the currently active
 * WordPress theme does not explicitly support bbPress. This can be filtered,
 * or set manually. Tricky theme authors can override the default and include
 * their own bbPress compatability layers for their themes.
 *
 * @since bbPress (r3032)
 *
 * @global bbPress $bbp
 * @param string $theme Optional. Must be full absolute path to theme
 * @uses apply_filters()
 * @return string
 */
function bbp_set_theme_compat( $theme = '' ) {
	global $bbp;

	// Set theme to bundled bbp-twentyten if nothing is passed
	if ( empty( $theme ) && !empty( $bbp->themes_dir ) )
		$bbp->theme_compat = $bbp->themes_dir . '/bbp-twentyten';

	// Set to what is passed
	else
		$bbp->theme_compat = $theme;

	return apply_filters( 'bbp_get_theme_compat', $bbp->theme_compat );
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
			'post_status'  => get_post_status()
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
			'post_status'  => 'publish'
		);
	}
	$dummy = wp_parse_args( $args, $defaults );

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
	$wp_query->posts[] = $wp_query->post;

	// Prevent comments form from appearing
	$wp_query->post_count = 1;
	$wp_query->is_404     = false;
	$wp_query->is_page    = false;
	$wp_query->is_single  = false;
	$wp_query->is_archive = false;
	$wp_query->is_tax     = false;
}

/**
 * Add checks for view page, user page, user edit, topic edit and reply edit
 * pages.
 *
 * If it's a user page, WP_Query::bbp_is_user_profile_page is set to true.
 * If it's a user edit page, WP_Query::bbp_is_user_profile_edit is set to true
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
	global $bbp;

	// Bail if $posts_query is not an object or of incorrect class
	if ( !is_object( $posts_query ) || ( 'WP_Query' != get_class( $posts_query ) ) )
		return $posts_query;

	// Bail if filters are suppressed on this query
	if ( true == $posts_query->get( 'suppress_filters' ) )
		return $posts_query;

	// Get query variables
	$bbp_user = $posts_query->get( 'bbp_user' );
	$bbp_view = $posts_query->get( 'bbp_view' );
	$is_edit  = $posts_query->get( 'edit'     );

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
			$posts_query->bbp_is_user_profile_edit = true;

			// Load the core WordPress contact methods
			if ( !function_exists( '_wp_get_user_contactmethods' ) )
				include_once( ABSPATH . 'wp-includes/registration.php' );

			// Load the edit_user functions
			if ( !function_exists( 'edit_user' ) )
				require_once( ABSPATH . 'wp-admin/includes/user.php' );

		// We are viewing a profile
		} else {
			$posts_query->bbp_is_user_profile_page = true;
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

		// We save post revisions on our own
		remove_action( 'pre_post_update', 'wp_save_post_revision' );
	}

	return $posts_query;
}

/**
 * Possibly intercept the template being loaded
 *
 * Listens to the 'template_include' filter and waits for a bbPress post_type
 * to appear. If the current theme does not explicitly support bbPress, it
 * intercepts the page template and uses one served from the bbPress compatable
 * theme, set as the $bbp->theme_compat global. If the current theme does
 * support bbPress, we'll explore the template hierarchy and try to locate one.
 *
 * @since bbPress (r3032)
 *
 * @global bbPress $bbp
 * @global WP_Query $post
 * @param string $template
 * @return string
 */
function bbp_template_include( $template = false ) {
	global $bbp;

	// Prevent debug notices
	$templates    = array();
	$new_template = '';

	// Current theme supports bbPress
	if ( current_theme_supports( 'bbpress' ) ) {

		// Viewing a profile
		if ( bbp_is_user_profile_page() ) {
			$templates = apply_filters( 'bbp_profile_templates', array(
				'forums/user.php',
				'bbpress/user.php',
				'user.php',
				'author.php',
				'index.php'
			) );

		// Editing a profile
		} elseif ( bbp_is_user_profile_edit() ) {
			$templates = apply_filters( 'bbp_profile_edit_templates', array(
				'forums/user-edit.php',
				'bbpress/user-edit.php',
				'user-edit.php',
				'forums/user.php',
				'bbpress/user.php',
				'user.php',
				'author.php',
				'index.php'
			) );

		// View page
		} elseif ( bbp_is_view() ) {
			$templates = apply_filters( 'bbp_view_templates', array(
				'forums/view-' . bbp_get_view_id(),
				'bbpress/view-' . bbp_get_view_id(),
				'forums/view.php',
				'bbpress/view.php',
				'view-' . bbp_get_view_id(),
				'view.php',
				'index.php'
			) );

		// Editing a topic
		} elseif ( bbp_is_topic_edit() ) {
			$templates = array(
				'forums/action-edit.php',
				'bbpress/action-edit.php',
				'forums/single-' . bbp_get_topic_post_type(),
				'bbpress/single-' . bbp_get_topic_post_type(),
				'action-bbp-edit.php',
				'single-' . bbp_get_topic_post_type(),
				'single.php',
				'index.php'
			);

			// Add split/merge to front of array if present in _GET
			if ( !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'merge', 'split' ) ) ) {
				array_unshift( $templates,
					'forums/action-split-merge.php',
					'bbpress/action-split-merge.php',
					'action-split-merge.php'
				);
			}

			$templates = apply_filters( 'bbp_topic_edit_templates', $templates );

		// Editing a reply
		} elseif ( bbp_is_reply_edit() ) {
			$templates = apply_filters( 'bbp_reply_edit_templates', array(
				'forums/action-edit.php',
				'bbpress/action-edit.php',
				'forums/single-' . bbp_get_reply_post_type(),
				'bbpress/single-' . bbp_get_reply_post_type(),
				'action-bbp-edit.php',
				'single-' . bbp_get_reply_post_type(),
				'single.php',
				'index.php'
			) );
		}

		// Custom template file exists
		if ( !empty( $templates ) && ( $new_template = locate_template( $templates, false, false ) ) ) {
			$template = $new_template;
		}
	}

	/**
	 * In this next bit, either the current theme does not support bbPress, or
	 * the theme author has incorrectly used add_theme_support( 'bbpress' )
	 * and we are going to help them out by silently filling in the blanks.
	 */
	if ( !current_theme_supports( 'bbpress' ) || ( !empty( $templates ) && empty( $new_template ) ) ) {

		// Assume we are not in theme compat
		$in_theme_compat = false;

		/** Users *************************************************************/

		if ( bbp_is_user_profile_page() || bbp_is_user_profile_edit() ) {

			// In Theme Compat
			$in_theme_compat = true;
			bbp_theme_compat_reset_post( array(
				'post_title' => esc_attr( bbp_get_displayed_user_field( 'display_name' ) )
			) );

		/** Forums ************************************************************/

		// Forum archive
		} elseif ( is_post_type_archive( bbp_get_forum_post_type() ) ) {

			// In Theme Compat
			$in_theme_compat = true;
			bbp_theme_compat_reset_post( array(
				'ID'           => 0,
				'post_title'   => __( 'Forums', 'bbpress' ),
				'post_author'  => 0,
				'post_date'    => 0,
				'post_content' => '',
				'post_type'    => bbp_get_forum_post_type(),
				'post_status'  => 'publish'
			) );

		/** Topics ************************************************************/

		// Topic archive
		} elseif ( is_post_type_archive( bbp_get_topic_post_type() ) ) {

			// In Theme Compat
			$in_theme_compat = true;
			bbp_theme_compat_reset_post( array(
				'ID'           => 0,
				'post_title'   => __( 'Topics', 'bbpress' ),
				'post_author'  => 0,
				'post_date'    => 0,
				'post_content' => '',
				'post_type'    => bbp_get_topic_post_type(),
				'post_status'  => 'publish'
			) );

		// Single topic
		} elseif ( bbp_is_topic_edit() || bbp_is_topic_split() || bbp_is_topic_merge() ) {

			// In Theme Compat
			$in_theme_compat = true;
			bbp_theme_compat_reset_post( array(
				'ID'           => bbp_get_topic_id(),
				'post_title'   => bbp_get_topic_title(),
				'post_author'  => bbp_get_topic_author_id(),
				'post_date'    => 0,
				'post_content' => get_post_field( 'post_content', bbp_get_topic_id() ),
				'post_type'    => bbp_get_topic_post_type(),
				'post_status'  => bbp_get_topic_status()
			) );

		/** Replies ***********************************************************/

		// Reply archive
		} elseif ( is_post_type_archive( bbp_get_reply_post_type() ) ) {

			// In Theme Compat
			$in_theme_compat = true;
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

			// In Theme Compat
			$in_theme_compat = true;
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

		} elseif ( bbp_is_view() ) {

			// In Theme Compat
			$in_theme_compat = true;
			bbp_theme_compat_reset_post();

		/** Topic Tags ********************************************************/

		} elseif ( is_tax( $bbp->topic_tag_id ) ) {

			// In Theme Compat
			$in_theme_compat = true;

			// Stash the current term in a new var
			set_query_var( 'bbp_topic_tag', get_query_var( 'term' ) );

			// Reset the post with our new title
			bbp_theme_compat_reset_post( array(
				'post_title' => sprintf( __( 'Topic Tag: %s', 'bbpress' ), '<span>' . bbp_get_topic_tag_name() . '</span>' ),
			) );

		/** Single Forums/Topics/Replies **************************************/

		} else {

			// Are we looking at a forum/topic/reply?
			switch ( get_post_type() ) {

				// Single Forum
				case bbp_get_forum_post_type() :
					$forum_id = bbp_get_forum_id( get_the_ID() );

				// Single Topic
				case bbp_get_topic_post_type() :
					$forum_id = bbp_get_topic_forum_id( get_the_ID() );

				// Single Reply
				case bbp_get_reply_post_type() :
					$forum_id = bbp_get_reply_forum_id( get_the_ID() );

					// Display template
					if ( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) || bbp_is_forum_private( $forum_id ) ) {

						// In Theme Compat
						$in_theme_compat = true;

					// Display 404 page
					} elseif ( bbp_is_forum_hidden( $forum_id ) ) {
						bbp_set_404();
					}

					break;
			}
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
		if ( true === $in_theme_compat ) {

			// Remove all filters from the_content
			remove_all_filters( 'the_content' );

			// Add a filter on the_content late, which we will later remove
			add_filter( 'the_content', 'bbp_replace_the_content' );

			// Default to the page template
			$template = apply_filters( 'bbp_template_include', 'page.php' );
			$template = locate_template( $template, false, false );
		}
	}

	// Return $template
	return $template;
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

		// Prevent debug notice
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
		if ( bbp_is_user_profile_page() ) {
			ob_start();

			bbp_get_template_part( 'bbpress/single', 'user'  );

			$new_content = ob_get_contents();

			ob_end_clean();

		// Profile Edit
		} elseif ( bbp_is_user_profile_edit() ) {
			ob_start();

			bbp_get_template_part( 'bbpress/single', 'user'  );

			$new_content = ob_get_contents();

			ob_end_clean();


		/** Forums ************************************************************/

		// Forum archive
		} elseif ( is_post_type_archive( bbp_get_forum_post_type() ) ) {
			$new_content = $bbp->shortcodes->display_forum_index();

		/** Topics ************************************************************/

		// Topic archive
		} elseif ( is_post_type_archive( bbp_get_topic_post_type() ) ) {
			$new_content = $bbp->shortcodes->display_topic_index();

		// Single topic
		} elseif ( bbp_is_topic_edit() ) {

			// Split
			if ( bbp_is_topic_split() ) {
				ob_start();

				bbp_get_template_part( 'bbpress/form', 'split' );

				$new_content = ob_get_contents();

				ob_end_clean();

			// Merge
			} elseif ( bbp_is_topic_merge() ) {
				ob_start();

				bbp_get_template_part( 'bbpress/form', 'merge' );

				$content = ob_get_contents();

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

		} elseif ( bbp_is_view() ) {
			$new_content = $bbp->shortcodes->display_view( array( 'id' => get_query_var( 'bbp_view' ) ) );

		/** Topic Tags ********************************************************/

		} elseif ( get_query_var( 'bbp_topic_tag' ) ) {
			$new_content = $bbp->shortcodes->display_topics_of_tag( array( 'id' => bbp_get_topic_tag_id() ) );

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

?>
