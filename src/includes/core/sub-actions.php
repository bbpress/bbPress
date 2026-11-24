<?php

/**
 * Plugin Dependency.
 *
 * The purpose of the following hooks is to mimic the behavior of something
 * called 'plugin dependency' which enables a plugin to have plugins of their
 * own in a safe and reliable way.
 *
 * We do this in bbPress by mirroring existing WordPress hooks in many places
 * allowing dependant plugins to hook into the bbPress specific ones, thus
 * guaranteeing proper code execution only when bbPress is active.
 *
 * The following functions are wrappers for hooks, allowing them to be
 * manually called and/or piggy-backed on top of other hooks if needed.
 *
 * @package bbPress
 * @subpackage Core
 *
 * @todo use anonymous functions when PHP minimum requirement allows (5.3)
 */

/** Activation Actions ********************************************************/

/**
 * Runs on bbPress activation.
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @return void
 */
function bbp_activation() {
	do_action( 'bbp_activation' );
}

/**
 * Runs on bbPress deactivation.
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @return void
 */
function bbp_deactivation() {
	do_action( 'bbp_deactivation' );
}

/**
 * Runs when uninstalling bbPress.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_uninstall() {
	do_action( 'bbp_uninstall' );
}

/** Main Actions **************************************************************/

/**
 * Main action responsible for constants, globals, and includes.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_loaded() {
	do_action( 'bbp_loaded' );
}

/**
 * Setup constants.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_constants() {
	do_action( 'bbp_constants' );
}

/**
 * Setup globals BEFORE includes.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_boot_strap_globals() {
	do_action( 'bbp_boot_strap_globals' );
}

/**
 * Include files.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_includes() {
	do_action( 'bbp_includes' );
}

/**
 * Setup globals AFTER includes.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_setup_globals() {
	do_action( 'bbp_setup_globals' );
}

/**
 * Register any objects before anything is initialized.
 *
 * @since 2.2.0 bbPress (r4181)
 *
 * @return void
 */
function bbp_register() {
	do_action( 'bbp_register' );
}

/**
 * Initialize any code after everything has been loaded.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_init() {
	do_action( 'bbp_init' );
}

/**
 * Initialize the bbPress REST API.
 *
 * @since 2.7.0 bbPress (r7481)
 *
 * @return void
 */
function bbp_rest_api_init() {
	do_action( 'bbp_rest_api_init' );
}

/**
 * Pass an XML-RPC request through bbPress.
 *
 * @since 2.7.0 bbPress (r7485)
 *
 * @param string           $method XML-RPC method name.
 * @param array<array-key, mixed> $args XML-RPC method arguments.
 * @param wp_xmlrpc_server|null $server XML-RPC server instance.
 *
 * @return void
 */
function bbp_xmlrpc_call( $method = '', $args = array(), $server = null ) {
	do_action( 'bbp_xmlrpc_call', $method, $args, $server );
}

/**
 * Initialize roles.
 *
 * @since 2.6.0 bbPress (r6106)
 *
 * @param WP_Roles $wp_roles The array of WP_Role objects that was initialized.
 *
 * @return void
 */
function bbp_roles_init( $wp_roles ) {
	do_action( 'bbp_roles_init', $wp_roles );
}

/**
 * Initialize widgets.
 *
 * @since 2.0.0 bbPress (r3391)
 *
 * @return void
 */
function bbp_widgets_init() {
	do_action( 'bbp_widgets_init' );
}

/**
 * Setup the currently logged-in user.
 *
 * @link https://bbpress.trac.wordpress.org/ticket/2309
 * @link https://core.trac.wordpress.org/ticket/24169
 *
 * @since 2.0.0 bbPress (r2697)
 *
 * @return void
 */
function bbp_setup_current_user() {
	do_action( 'bbp_setup_current_user' );
}

/**
 * Setup the user engagements strategy.
 *
 * @since 2.6.0 bbPress (r6876)
 *
 * @return void
 */
function bbp_setup_engagements() {
	do_action( 'bbp_setup_engagements' );
}

/**
 * Run bbPress actions before PHP shuts down.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return void
 */
function bbp_shutdown() {
	do_action( 'bbp_shutdown' );
}

/** Supplemental Actions ******************************************************/

/**
 * Load translations for current language.
 *
 * @since 2.0.0 bbPress (r3344)
 *
 * @return void
 */
function bbp_load_textdomain() {
	do_action( 'bbp_load_textdomain' );
}

/**
 * Setup the post types.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_register_post_types() {
	do_action( 'bbp_register_post_types' );
}

/**
 * Setup the post statuses.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @return void
 */
function bbp_register_post_statuses() {
	do_action( 'bbp_register_post_statuses' );
}

/**
 * Register the built in bbPress taxonomies.
 *
 * @since 2.0.0 bbPress (r2599)
 *
 * @return void
 */
function bbp_register_taxonomies() {
	do_action( 'bbp_register_taxonomies' );
}

/**
 * Register the default bbPress views.
 *
 * @since 2.0.0 bbPress (r2789)
 *
 * @return void
 */
function bbp_register_views() {
	do_action( 'bbp_register_views' );
}

/**
 * Register what bbPress engagements need before they are set up, such as the
 * default engagement types.
 *
 * @since 2.7.0 bbPress (r8306)
 *
 * @return void
 */
function bbp_register_engagements() {
	do_action( 'bbp_register_engagements' );
}

/**
 * Register the default bbPress shortcodes.
 *
 * @since 2.0.0 bbPress (r3032)
 *
 * @return void
 */
function bbp_register_shortcodes() {
	do_action( 'bbp_register_shortcodes' );
}

/**
 * Register the default bbPress blocks.
 *
 * @since 2.7.0 bbPress (r7383)
 *
 * @return void
 */
function bbp_register_blocks() {
	do_action( 'bbp_register_blocks' );
}

/**
 * Register the default bbPress meta-data.
 *
 * @since 2.6.0 bbPress (r6302)
 *
 * @return void
 */
function bbp_register_meta() {
	do_action( 'bbp_register_meta' );
}

/**
 * Enqueue bbPress specific CSS and JS.
 *
 * @since 2.0.0 bbPress (r3374)
 *
 * @return void
 */
function bbp_enqueue_scripts() {
	do_action( 'bbp_enqueue_scripts' );
}

/**
 * Add the bbPress-specific rewrite tags.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @return void
 */
function bbp_add_rewrite_tags() {
	do_action( 'bbp_add_rewrite_tags' );
}

/**
 * Add the bbPress-specific rewrite rules.
 *
 * @since 2.4.0 bbPress (r4918)
 *
 * @return void
 */
function bbp_add_rewrite_rules() {
	do_action( 'bbp_add_rewrite_rules' );
}

/**
 * Add the bbPress-specific permalink structures.
 *
 * @since 2.4.0 bbPress (r4918)
 *
 * @return void
 */
function bbp_add_permastructs() {
	do_action( 'bbp_add_permastructs' );
}

/**
 * Add the bbPress-specific login forum action.
 *
 * @since 2.1.0 bbPress (r4072)
 *
 * @return void
 */
function bbp_login_form_login() {
	do_action( 'bbp_login_form_login' );
}

/**
 * Add the bbPress-specific post status transition action.
 *
 * @since 2.6.0 bbPress (r6792)
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post|false $post Post object, or false when omitted.
 *
 * @return void
 */
function bbp_transition_post_status( $new_status = '', $old_status = '', $post = false ) {
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}

	// Get bbPress post types
	$post_type = get_post_type( $post );
	$types     = bbp_get_post_types();

	// Bail if post is not a bbPress post type
	if ( ! in_array( $post_type, $types, true ) ) {
		return;
	}

	// Do the action
	do_action( 'bbp_transition_post_status', $new_status, $old_status, $post );
}

/**
 * Add the bbPress-specific post updated action.
 *
 * @since 2.6.17 bbPress (r7453)
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post|false $post_after  Post object following the update.
 * @param WP_Post|false $post_before Post object before the update.
 *
 * @return void
 */
function bbp_post_updated( $post_id = 0, $post_after = false, $post_before = false ) {
	// Bail if the post update has no complete object pair
	if ( ! ( $post_after instanceof WP_Post ) || ! ( $post_before instanceof WP_Post ) ) {
		return;
	}

	$post_types = bbp_get_post_types();

	// Bail if neither version is a bbPress post type
	if ( ! in_array( $post_after->post_type, $post_types, true ) && ! in_array( $post_before->post_type, $post_types, true ) ) {
		return;
	}

	// Do the action
	do_action( 'bbp_post_updated', $post_id, $post_after, $post_before );
}

/** User Actions **************************************************************/

/**
 * The main action for hooking into when a user account is updated.
 *
 * @since 2.2.0 bbPress (r4305)
 *
 * @param int           $user_id       ID of user being edited.
 * @param WP_User|array<string, mixed> $old_user_data Old, unmodified user data.
 *
 * @return void
 */
function bbp_profile_update( $user_id = 0, $old_user_data = array() ) {
	do_action( 'bbp_profile_update', $user_id, $old_user_data );
}

/**
 * The main action for hooking into a user being registered.
 *
 * @since 2.2.0 bbPress (r4305)
 *
 * @param int $user_id ID of user being edited.
 *
 * @return void
 */
function bbp_user_register( $user_id = 0 ) {
	do_action( 'bbp_user_register', $user_id );
}

/** Final Action **************************************************************/

/**
 * bbPress has loaded and initialized everything, and is okay to go.
 *
 * @since 2.0.0 bbPress (r2618)
 *
 * @return void
 */
function bbp_ready() {
	do_action( 'bbp_ready' );
}

/** Theme Permissions *********************************************************/

/**
 * The main action used for redirecting bbPress theme actions that are not
 * permitted by the current_user.
 *
 * @since 2.1.0 bbPress (r3607)
 *
 * @return void
 */
function bbp_template_redirect() {
	do_action( 'bbp_template_redirect' );
}

/** Theme Helpers *************************************************************/

/**
 * The main action used for executing code before the theme has been setup.
 *
 * @since 2.1.0 bbPress (r3830)
 *
 * @return void
 */
function bbp_register_theme_packages() {
	do_action( 'bbp_register_theme_packages' );
}

/**
 * The main action used for executing code before the theme has been setup.
 *
 * @since 2.1.0 bbPress (r3754)
 *
 * @return void
 */
function bbp_setup_theme() {
	do_action( 'bbp_setup_theme' );
}

/**
 * The main action used for executing code after the theme has been setup.
 *
 * @since 2.1.0 bbPress (r3733)
 *
 * @return void
 */
function bbp_after_setup_theme() {
	do_action( 'bbp_after_setup_theme' );
}

/**
 * The main action used for handling theme-side POST requests.
 *
 * @since 2.3.0 bbPress (r4551)
 *
 * @return void
 */
function bbp_post_request() {

	// Bail if not a POST action
	if ( ! bbp_is_post_request() ) {
		return;
	}

	// Get the POST action
	$action = bbp_get_post_action();

	// Bail if action was totally invalid
	if ( empty( $action ) ) {
		return;
	}

	// This dynamic action is probably the one you want to use. It narrows down
	// the scope of the 'action' without needing to check it in your function.
	do_action( 'bbp_post_request_' . $action );

	// Use this static action if you don't mind checking the 'action' yourself.
	do_action( 'bbp_post_request',   $action );
}

/**
 * The main action used for handling theme-side GET requests.
 *
 * @since 2.3.0 bbPress (r4551)
 *
 * @return void
 */
function bbp_get_request() {

	// Bail if not a POST action
	if ( ! bbp_is_get_request() ) {
		return;
	}

	// Bail if no action, or if not a string (arrays not supported)
	if ( empty( $_GET['action'] ) || ! is_string( $_GET['action'] ) ) {
		return;
	}

	// Sanitize the GET action
	$action = sanitize_key( $_GET['action'] );

	// Bail if action was totally invalid
	if ( empty( $action ) ) {
		return;
	}

	// This dynamic action is probably the one you want to use. It narrows down
	// the scope of the 'action' without needing to check it in your function.
	do_action( 'bbp_get_request_' . $action );

	// Use this static action if you don't mind checking the 'action' yourself.
	do_action( 'bbp_get_request',   $action );
}

/** Filters *******************************************************************/

/**
 * Filter the plugin locale and domain.
 *
 * @since 2.2.0 bbPress (r4214)
 *
 * @param string $locale Locale to filter.
 * @param string $domain Text domain.
 * @return string Filtered plugin locale.
 */
function bbp_plugin_locale( $locale = '', $domain = '' ) {

	/**
	 * Filters the bbPress locale.
	 *
	 * @since 2.2.0 bbPress (r4214)
	 *
	 * @param string $locale Filtered plugin locale.
	 * @param string $domain Text domain.
	 */
	return apply_filters( 'bbp_plugin_locale', $locale, $domain );
}

/**
 * Piggy back filter for WordPress's 'request' filter.
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param array<string, mixed> $query_vars Request query variables.
 * @return array<string, mixed> Filtered query variables.
 */
function bbp_request( $query_vars = array() ) {

	/**
	 * Filters the parsed bbPress request variables.
	 *
	 * @since 2.1.0 bbPress (r3759)
	 *
	 * @param array<string, mixed> $query_vars Filtered query variables.
	 */
	return apply_filters( 'bbp_request', $query_vars );
}

/**
 * Piggy back filter for WordPress's 'wp_nav_menu_objects' filter.
 *
 * @since 2.7.0 bbPress (r8608)
 *
 * @param array<array-key, WP_Post> $menu_items Sorted navigation menu items.
 * @param stdClass|null $args Navigation menu arguments.
 * @return array<array-key, WP_Post> Filtered navigation menu items.
 */
function bbp_nav_menu_objects( $menu_items = array(), $args = null ) {

	/**
	 * Filters navigation menu items after bbPress updates their classes.
	 *
	 * @since 2.7.0 bbPress (r8608)
	 *
	 * @param array<array-key, WP_Post> $menu_items Navigation menu items.
	 * @param stdClass|null            $args       Navigation menu arguments.
	 */
	return (array) apply_filters( 'bbp_nav_menu_objects', $menu_items, $args );
}

/**
 * The main filter used for theme compatibility and displaying custom bbPress
 * theme files.
 *
 * @since 2.0.0 bbPress (r3032)
 *
 * @param string $template Template file to filter.
 * @return string Template file to use.
 */
function bbp_template_include( $template = '' ) {

	/**
	 * Filters the template file to use.
	 *
	 * @since 2.0.0 bbPress (r3032)
	 *
	 * @param string $template Template file to use.
	 */
	return apply_filters( 'bbp_template_include', $template );
}

/**
 * Generate bbPress-specific rewrite rules.
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @deprecated 2.4.0 bbPress (r4918)
 *
 * @param WP_Rewrite $wp_rewrite WordPress rewrite rules object.
 *
 * @return void
 */
function bbp_generate_rewrite_rules( $wp_rewrite ) {
	do_action_ref_array( 'bbp_generate_rewrite_rules', array( &$wp_rewrite ) );
}

/**
 * Filter the allowed themes list for bbPress specific themes.
 *
 * @since 2.0.0 bbPress (r2944)
 *
 * @param array<array-key, mixed> $themes Allowed themes.
 *
 * @return array<array-key, mixed> Array of allowed themes.
 */
function bbp_allowed_themes( $themes ) {

	/**
	 * Filters the array of allowed themes.
	 *
	 * @since 2.0.0 bbPress (r2944)
	 *
	 * @param array<array-key, mixed> $themes Array of allowed themes.
	 */
	return (array) apply_filters( 'bbp_allowed_themes', $themes );
}

/**
 * Maps forum/topic/reply caps to built in WordPress caps.
 *
 * @since 2.0.0 bbPress (r2593)
 *
 * @param array<array-key, mixed> $caps Capabilities for meta capability.
 * @param string $cap     Capability name.
 * @param int    $user_id User ID.
 * @param array<array-key, mixed> $args Arguments.
 *
 * @return array<array-key, mixed> Array of capabilities.
 */
function bbp_map_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

	/**
	 * Filters the array of capabilities.
	 *
	 * @since 2.0.0 bbPress (r2593)
	 *
	 * @param array<array-key, mixed> $caps Array of capabilities.
	 * @param string $cap Capability name.
	 * @param int $user_id User ID.
	 * @param array<array-key, mixed> $args Arguments.
	 */
	return (array) apply_filters( 'bbp_map_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Filter the arguments used by wp_mail for bbPress specific emails.
 *
 * @since 2.6.0 bbPress (r6919)
 *
 * @param array<string, mixed> $args A compacted array of wp_mail() arguments, including the "to" email,
 *                                   subject, message, headers, and attachments values.
 *
 * @return array<string, mixed> Filtered mail arguments.
 */
function bbp_mail( $args = array() ) {

	// Bail if headers are missing/malformed
	if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
		return $args;
	}

	// Header to search all headers for
	$bbp_header = bbp_get_email_header();

	// Bail if no bbPress header found
	if ( false === array_search( $bbp_header, $args['headers'], true ) ) {
		return $args;
	}

	/**
	 * Filters the arguments passed to the bbPress mailer.
	 *
	 * @since 2.6.0 bbPress (r6919)
	 *
	 * @param array<string, mixed> $args Filtered mail arguments.
	 */
	return (array) apply_filters( 'bbp_mail', $args );
}

/**
 * Redirects incoming links to the proper URL based on the site URL.
 *
 * @see redirect_canonical()
 *
 * @since 2.0.0 bbPress (r2634)
 * @since 2.7.0 bbPress (r7348) Converted to a sub-action.
 *
 * @param string $redirect_url  The redirect URL.
 * @param string $requested_url The requested URL.
 *
 * @return string Empty string if a topic/forum and their first page,
 *                 otherwise the redirect URL.
 */
function bbp_redirect_canonical( $redirect_url = '', $requested_url = '' ) {

	/**
	 * Filters the canonical redirect URL.
	 *
	 * @since 2.0.0 bbPress (r2634)
	 *
	 * @param string $redirect_url Empty string if a topic/forum and their first page, otherwise the redirect URL.
	 * @param string $requested_url The requested URL.
	 */
	return (string) apply_filters( 'bbp_redirect_canonical', $redirect_url, $requested_url );
}

/**
 * Prevent old-slug redirects from exposing unreadable topics and replies.
 *
 * @since 2.6.21 bbPress (r8067)
 *
 * @param int $post_id Old-slug redirect post ID.
 * @return int Redirect post ID, or zero when access is denied.
 */
function bbp_filter_old_slug_redirect_post_id( $post_id = 0 ) {
	$post = get_post( $post_id );

	if ( $post && ( bbp_is_topic( $post ) || bbp_is_reply( $post ) ) ) {
		$topic_id       = bbp_is_reply( $post ) ? bbp_get_reply_topic_id( $post->ID ) : $post->ID;
		$forum_id       = bbp_get_topic_forum_id( $topic_id );
		$can_read_forum = bbp_is_forum_visibility_valid( $forum_id )
			&& ! bbp_is_forum_restricted_for_user( $forum_id, bbp_get_current_user_id() );
		$can_read_topic = ! empty( $topic_id )
			&& bbp_is_topic( $topic_id )
			&& ( bbp_is_topic_public( $topic_id ) || current_user_can( 'read_topic', $topic_id ) );
		$can_read_reply = ! bbp_is_reply( $post )
			|| bbp_is_reply_public( $post->ID )
			|| current_user_can( 'read_reply', $post->ID );

		if ( ! $can_read_forum || ! $can_read_topic || ! $can_read_reply ) {
			$post_id = 0;
		}
	}

	return $post_id;
}

/**
 * Check whether bbPress metadata can be exposed in an oEmbed response.
 *
 * @since 2.6.19 bbPress (r7633)
 *
 * @param int $post_id bbPress post ID.
 * @return bool Whether the post can be embedded for the current visitor.
 */
function bbp_user_can_embed_post( $post_id = 0 ) {
	$post = get_post( $post_id );
	if ( empty( $post ) || ! bbp_is_custom_post_type( $post ) || ! is_post_publicly_viewable( $post ) || bbp_is_password_protected( $post->ID ) ) {
		return false;
	}

	if ( bbp_is_forum( $post->ID ) ) {
		$forum_id = $post->ID;
	} elseif ( bbp_is_topic( $post->ID ) ) {
		$forum_id = bbp_get_topic_forum_id( $post->ID );
	} else {
		$topic_id = bbp_get_reply_topic_id( $post->ID );
		if ( ! $topic_id || ! is_post_publicly_viewable( $topic_id ) ) {
			return false;
		}
		$forum_id = bbp_get_reply_forum_id( $post->ID );
	}

	if ( ! $forum_id || ! bbp_is_forum_visibility_valid( $forum_id ) ) {
		return false;
	}

	return ! bbp_is_forum_restricted_for_user( $forum_id, bbp_get_current_user_id() );
}

/**
 * Prevent object-by-ID oEmbed requests from disclosing restricted posts.
 *
 * @since 2.6.19 bbPress (r7633)
 *
 * @param int $post_id Resolved post ID.
 * @return int Resolved post ID, or zero when access is denied.
 */
function bbp_filter_oembed_request_post_id( $post_id = 0 ) {
	$post = get_post( $post_id );

	if ( $post && bbp_is_custom_post_type( $post ) && ! bbp_user_can_embed_post( $post_id ) ) {
		return 0;
	}

	return $post_id;
}

/**
 * Return the post ID WordPress uses for an ID-based canonical redirect.
 *
 * @since 2.6.21 bbPress (r8086)
 *
 * @param string $requested_url Requested URL.
 * @return int Canonical redirect post ID, or zero when none was requested.
 */
function bbp_get_canonical_redirect_post_id( $requested_url = '' ) {

	// Explicitly trust an existing 404 post ID.
	if ( is_404() ) {
		$post_id = max( 0, (int) get_query_var( 'p' ), (int) get_query_var( 'page_id' ), (int) get_query_var( 'attachment_id' ) );
		if ( $post_id ) {
			return $post_id;
		}
	}

	$query = wp_parse_url( $requested_url, PHP_URL_QUERY );
	if ( empty( $query ) ) {
		return 0;
	}

	wp_parse_str( $query, $args );
	$p             = isset( $args['p'] ) && is_scalar( $args['p'] ) && ( (int) $args['p'] >= 0 )
		? (int) $args['p']
		: 0;
	$page_id       = isset( $args['page_id'] ) && is_scalar( $args['page_id'] )
		? absint( $args['page_id'] )
		: 0;
	$attachment_id = isset( $args['attachment_id'] ) && is_scalar( $args['attachment_id'] )
		? absint( $args['attachment_id'] )
		: 0;

	// Match WP_Query normalization before redirect_canonical() chooses an ID.
	if ( isset( $args['subpost_id'] ) && is_scalar( $args['subpost_id'] ) && ( '' !== $args['subpost_id'] ) ) {
		$attachment_id = absint( $args['subpost_id'] );
	}
	if ( $attachment_id ) {
		$p = $attachment_id;
	}
	if ( $page_id ) {
		$p = $page_id;
	}

	return max( $p, $page_id, $attachment_id );
}

/**
 * Prevent canonical redirects from exposing restricted bbPress permalinks.
 *
 * @since 2.6.19 bbPress (r7633)
 *
 * @param string $redirect_url Proposed redirect URL.
 * @param string $requested_url Requested URL.
 * @return string|false Redirect URL, or false when access is denied.
 */
function bbp_do_not_redirect_restricted_posts( $redirect_url = '', $requested_url = '' ) {
	$post_id = bbp_get_canonical_redirect_post_id( $requested_url );
	$post    = get_post( $post_id );
	if ( $post && bbp_is_custom_post_type( $post ) && ! bbp_user_can_embed_post( $post_id ) ) {
		return false;
	}

	return $redirect_url;
}
