<?php

/**
 * bbPress Abstractions
 *
 * This file contains functions for abstracting WordPress core functionality
 * into convenient wrappers so they can be used more reliably.
 *
 * Many of the functions in this file are considered superfluous by
 * WordPress coding standards, but they're handy for plugins of plugins to use.
 *
 * @package bbPress
 * @subpackage Core
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Setup Admin
 *
 * This exists outside of "/includes/admin/" because the converter may need to
 * be setup to convert the passwords of users that were migrated from another
 * forum platform.
 *
 * @since 2.6.0 bbPress (r2596)
 */
function bbp_setup_admin() {
	$bbp = bbpress();

	// Skip if already setup
	if ( empty( $bbp->admin ) ) {

		// Require the admin class
		require_once $bbp->includes_dir . 'admin/classes/class-bbp-admin.php';

		// Setup
		$bbp->admin = class_exists( 'BBP_Admin' )
			? new BBP_Admin()
			: new stdClass();
	}

	// Return the admin object
	return $bbp->admin;
}

/**
 * Setup Converter
 *
 * This exists outside of "/includes/admin/" because the converter may need to
 * be setup to convert the passwords of users that were migrated from another
 * forum platform.
 *
 * @since 2.6.0 bbPress (r2596)
 */
function bbp_setup_converter() {
	$bbp_admin = bbp_setup_admin();

	// Skip if already setup
	if ( empty( $bbp_admin->converter ) ) {

		// Require the converter files
		require_once $bbp_admin->admin_dir . 'tools/converter.php';
		require_once $bbp_admin->admin_dir . 'classes/class-bbp-converter.php';
		require_once $bbp_admin->admin_dir . 'classes/class-bbp-converter-db.php';
		require_once $bbp_admin->admin_dir . 'classes/class-bbp-converter-base.php';

		// Setup
		$bbp_admin->converter = class_exists( 'BBP_Converter' )
			? new BBP_Converter()
			: new stdClass();
	}

	// Return the converter
	return $bbp_admin->converter;
}

/** Globals *******************************************************************/

/**
 * Lookup and return a global variable
 *
 * @since 2.5.8 bbPress (r5814)
 *
 * @param  string  $name     Name of global variable
 * @param  string  $type     Type of variable to check with `is_a()`
 * @param  mixed   $default  Default value to return if no global found
 *
 * @return mixed   Verified object if valid, Default or null if invalid
 */
function bbp_get_global_object( $name = '', $type = '', $default = null ) {

	// If no name passed
	if ( empty( $name ) ) {
		$retval = $default;

	// If no global exists
	} elseif ( ! isset( $GLOBALS[ $name ] ) ) {
		$retval = $default;

	// If not the correct type of global
	} elseif ( ! empty( $type ) && ! is_a( $GLOBALS[ $name ], $type ) ) {
		$retval = $default;

	// Global variable exists
	} else {
		$retval = $GLOBALS[ $name ];
	}

	// Filter & return
	return apply_filters( 'bbp_get_global_object', $retval, $name, $type, $default );
}

/**
 * Get the `$wp_query` global without needing to declare it everywhere
 *
 * @since 2.6.0 bbPress (r6582)
 *
 * @return WP_Roles
 */
function bbp_get_wp_query() {
	return bbp_get_global_object( 'wp_query', 'WP_Query' );
}

/**
 * Get the `$wp_roles` global without needing to declare it everywhere
 *
 * @since 2.2.0 bbPress (r4293)
 *
 * @return WP_Roles
 */
function bbp_get_wp_roles() {
	return bbp_get_global_object( 'wp_roles', 'WP_Roles' );
}

/**
 * Return the database class being used to interface with the environment.
 *
 * This function is abstracted to avoid global touches to the primary database
 * class. bbPress supports WordPress's `$wpdb` global by default, and can be
 * filtered to support other configurations if needed.
 *
 * @since 2.5.8 bbPress (r5814)
 *
 * @return object
 */
function bbp_db() {
	return bbp_get_global_object( 'wpdb', 'WPDB' );
}

/** Pagination ****************************************************************/

/**
 * Return the rewrite rules class being used to interact with URLs.
 *
 * This function is abstracted to avoid global touches to the primary rewrite
 * rules class. bbPress supports WordPress's `$wp_rewrite` by default, but can
 * be filtered to support other configurations if needed.
 *
 * @since 2.5.8 bbPress (r5814)
 *
 * @return object
 */
function bbp_rewrite() {
	return bbp_get_global_object( 'wp_rewrite', 'WP_Rewrite', (object) array(
		'root'            => '',
		'pagination_base' => '',
	) );
}

/**
 * Get the root URL
 *
 * @since 2.5.8 bbPress (r5814)
 *
 * @return string
 */
function bbp_get_root_url() {

	// Filter & return
	return apply_filters( 'bbp_get_root_url', bbp_rewrite()->root );
}

/**
 * Get the slug used for paginated requests
 *
 * @since 2.4.0 bbPress (r4926)
 *
 * @return string
 */
function bbp_get_paged_slug() {

	// Filter & return
	return apply_filters( 'bbp_get_paged_slug', bbp_rewrite()->pagination_base );
}

/**
 * Remove the first-page from a pagination links result set, ensuring that it
 * points to the canonical first page URL.
 *
 * This is a bit of an SEO hack, to guarantee that the first page in a loop will
 * never have pagination appended to the end of it, regardless of what the other
 * functions have decided for us.
 *
 * @since 2.6.0 bbPress (r6678)
 *
 * @param string $pagination_links The HTML links used for pagination
 *
 * @return string
 */
function bbp_make_first_page_canonical( $pagination_links = '' ) {

	// Default value
	$retval = $pagination_links;

	// Remove first page from pagination
	if ( ! empty( $pagination_links ) ) {
		$retval = bbp_use_pretty_urls()
			? str_replace( bbp_get_paged_slug() . '/1/', '', $pagination_links )
			: preg_replace( '/&#038;paged=1(?=[^0-9])/m', '', $pagination_links );
	}

	// Filter & return
	return apply_filters( 'bbp_make_first_page_canonical', $retval, $pagination_links );
}

/**
 * A convenient wrapper for common calls to paginate_links(), complete with
 * support for parameters that aren't used internally by bbPress.
 *
 * @since 2.6.0 bbPress (r6679)
 *
 * @param array $args
 *
 * @return string
 */
function bbp_paginate_links( $args = array() ) {

	// Maybe add view-all args
	$add_args = empty( $args['add_args'] ) && bbp_get_view_all()
		? array( 'view' => 'all' )
		: false;

	// Pagination settings with filter
	$r = bbp_parse_args( $args, array(

		// Used by callers
		'base'      => '',
		'total'     => 1,
		'current'   => bbp_get_paged(),
		'prev_next' => true,
		'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
		'next_text' => is_rtl() ? '&larr;' : '&rarr;',
		'mid_size'  => 1,
		'end_size'  => 3,
		'add_args'  => $add_args,

		// Unused by callers
		'show_all'           => false,
		'type'               => 'plain',
		'format'             => '',
		'add_fragment'       => '',
		'before_page_number' => '',
		'after_page_number'  => ''
	), 'paginate_links' );

	// Return paginated links
	return bbp_make_first_page_canonical( paginate_links( $r ) );
}

/**
 * Is the environment using pretty URLs?
 *
 * @since 2.5.8 bbPress (r5814)
 *
 * @global object $wp_rewrite The WP_Rewrite object
 *
 * @return bool
 */
function bbp_use_pretty_urls() {

	// Default
	$retval  = false;
	$rewrite = bbp_rewrite();

	// Use $wp_rewrite->using_permalinks() if available
	if ( method_exists( $rewrite, 'using_permalinks' ) ) {
		$retval = $rewrite->using_permalinks();
	}

	// Filter & return
	return apply_filters( 'bbp_pretty_urls', $retval );
}

/**
 * Parse the WordPress core version number
 *
 * @since 2.6.0 bbPress (r6051)
 *
 * @global string $wp_version
 *
 * @return string $wp_version
 */
function bbp_get_major_wp_version() {
	global $wp_version;

	return (float) $wp_version;
}

/** Multisite *****************************************************************/

/**
 * Is this a large bbPress installation?
 *
 * @since 2.6.0 bbPress (r6242)
 *
 * @return bool True if more than 10000 users, false not
 */
function bbp_is_large_install() {

	// Multisite has a function specifically for this
	$retval = function_exists( 'wp_is_large_network' )
		? wp_is_large_network( 'users' )
		: ( bbp_get_total_users() > 10000 );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_large_install', $retval );
}

/**
 * Get the total number of users on the forums
 *
 * @since 2.0.0 bbPress (r2769)
 *
 * @return int Total number of users
 */
function bbp_get_total_users() {
	$bbp_db = bbp_db();
	$count  = $bbp_db->get_var( "SELECT COUNT(ID) as c FROM {$bbp_db->users} WHERE user_status = '0'" );

	// Filter & return
	return (int) apply_filters( 'bbp_get_total_users', (int) $count );
}

/**
 * Switch to a site in a multisite installation.
 *
 * If not a multisite installation, no switching will occur.
 *
 * @since 2.6.0 bbPress (r6733)
 *
 * @param int $site_id
 */
function bbp_switch_to_site( $site_id = 0 ) {

	// Switch to a specific site
	if ( is_multisite() ) {
		switch_to_blog( $site_id );
	}
}

/**
 * Switch back to the original site in a multisite installation.
 *
 * If not a multisite installation, no switching will occur.
 *
 * @since 2.6.0 bbPress (r6733)
 */
function bbp_restore_current_site() {

	// Switch back to the original site
	if ( is_multisite() ) {
		restore_current_blog();
	}
}

/** Interception **************************************************************/

/**
 * Generate a default intercept value.
 *
 * @since 2.6.0
 *
 * @staticvar mixed $rand Null by default, random string on first call
 *
 * @return string
 */
function bbp_default_intercept() {
	static $rand = null;

	// Generate a new random and unique string
	if ( null === $rand ) {

		// If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
		$algo = function_exists( 'hash' )
			? 'sha256'
			: 'sha1';

		// Old WP installs may not have AUTH_SALT defined.
		$salt = defined( 'AUTH_SALT' ) && AUTH_SALT
			? AUTH_SALT
			: (string) wp_rand();

		// Create unique ID
		$rand = hash_hmac( $algo, uniqid( $salt, true ), $salt );
	}

	// Return random string (from locally static variable)
	return $rand;
}

/**
 * Whether a value has been intercepted
 *
 * @since 2.6.0
 *
 * @param bool $value
 */
function bbp_is_intercepted( $value = '' ) {
	return ( bbp_default_intercept() !== $value );
}

/**
 * Allow interception of a method or function call.
 *
 * @since 2.6.0
 *
 * @param string $action Typically the name of the caller function
 * @param array  $args   Typically the results of caller function func_get_args()
 *
 * @return mixed         Intercept results. Default bbp_default_intercept().
 */
function bbp_maybe_intercept( $action = '', $args = array() ) {

	// Backwards compatibility juggle
	$hook      = ( false === strpos( $action, 'pre_' ) )
		? "pre_{$action}"
		: $action;

	// Sanitize the hook same
	$key       = sanitize_key( $hook );

	// Default return value
	$default   = bbp_default_intercept();

	// Filter and return
	return apply_filters( $key, $default, extract( (array) $args ) );
}

/** Engagements ***************************************************************/

/**
 * Return the strategy used for storing user engagements
 *
 * @since 2.6.0 bbPress (r6722)
 *
 * @param string $rel_key  The key used to index this relationship
 * @param string $rel_type The type of meta to look in
 *
 * @return string
 */
function bbp_user_engagements_interface( $rel_key = '', $rel_type = 'post' ) {
	return apply_filters( 'bbp_user_engagements_interface', bbpress()->engagements, $rel_key, $rel_type );
}

/**
 * Meta strategy for interfacing with User Engagements
 *
 * @since 2.6.0 bbPress (r6722)
 */
class BBP_User_Engagements_Base {

	/**
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @var string
	 */
	public $type = '';

	/**
	 * Add a user id to an object
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 * @param bool   $unique    Whether meta key should be unique to the object
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function add_user_to_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post', $unique = false ) {

	}

	/**
	 * Remove a user id from an object
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_user_from_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {

	}

	/**
	 * Remove a user id from all objects
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_user_from_all_objects( $user_id = 0, $meta_key = '', $meta_type = 'post' ) {

	}

	/**
	 * Remove an object from all users
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_object_from_all_users( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {

	}

	/**
	 * Remove all users from all objects
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_all_users_from_all_objects( $meta_key = '', $meta_type = 'post' ) {

	}

	/**
	 * Get users of an object
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $object_id The object id
	 * @param string $meta_key  The key used to index this relationship
	 * @param string $meta_type The type of meta to look in
	 *
	 * @return array Returns ids of users
	 */
	public function get_users_for_object( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {

	}

	/**
	 * Get the part of the query responsible for JOINing objects to relationships.
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param array  $args
	 * @param string $meta_key
	 * @param string $meta_type
	 *
	 * @return array
	 */
	public function get_query( $args = array(), $context_key = '', $meta_key = '', $meta_type = 'post' ) {

	}
}

/**
 * Meta strategy for interfacing with User Engagements
 *
 * @since 2.6.0 bbPress (r6722)
 */
class BBP_User_Engagements_Meta extends BBP_User_Engagements_Base {

	/**
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @var string
	 */
	public $type = 'meta';

	/**
	 * Add a user id to an object
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 * @param bool   $unique    Whether meta key should be unique to the object
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function add_user_to_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post', $unique = false ) {
		return add_metadata( $meta_type, $object_id, $meta_key, $user_id, $unique );
	}

	/**
	 * Remove a user id from an object
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_user_from_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		return delete_metadata( $meta_type, $object_id, $meta_key, $user_id, false );
	}

	/**
	 * Remove a user id from all objects
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_user_from_all_objects( $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		return delete_metadata( $meta_type, null, $meta_key, $user_id, true );
	}

	/**
	 * Remove an object from all users
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_object_from_all_users( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		return delete_metadata( $meta_type, $object_id, $meta_key, null, false );
	}

	/**
	 * Remove all users from all objects
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_all_users_from_all_objects( $meta_key = '', $meta_type = 'post' ) {
		return delete_metadata( $meta_type, null, $meta_key, null, true );
	}

	/**
	 * Get users of an object
	 *
	 * @since 2.6.0 bbPress (r6722)
	 *
	 * @param int    $object_id The object id
	 * @param string $meta_key  The key used to index this relationship
	 * @param string $meta_type The type of meta to look in
	 *
	 * @return array Returns ids of users
	 */
	public function get_users_for_object( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		return wp_parse_id_list( get_metadata( $meta_type, $object_id, $meta_key, false ) );
	}

	/**
	 * Get the part of the query responsible for JOINing objects to relationships.
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param array  $args
	 * @param string $meta_key
	 * @param string $meta_type
	 *
	 * @return array
	 */
	public function get_query( $args = array(), $context_key = '', $meta_key = '', $meta_type = 'post' ) {

		// Backwards compat for pre-2.6.0
		if ( is_numeric( $args ) ) {
			$args = array(
				'meta_query' => array( array(
					'key'     => $meta_key,
					'value'   => bbp_get_user_id( $args, false, false ),
					'compare' => 'NUMERIC'
				) )
			);
		}

		// Default arguments
		$defaults = array(
			'meta_query' => array( array(
				'key'     => $meta_key,
				'value'   => bbp_get_displayed_user_id(),
				'compare' => 'NUMERIC'
			) )
		);

		// Parse arguments
		return bbp_parse_args( $args, $defaults, $context_key );
	}
}

/**
 * Term strategy for interfacing with User Engagements
 *
 * @since 2.6.0 bbPress (r6737)
 */
class BBP_User_Engagements_Term extends BBP_User_Engagements_Base {

	/**
	 * Term type
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @var string
	 */
	public $type = 'term';

	/**
	 * Register an engagement taxonomy just-in-time
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param string $tax_key
	 * @param string $object_type
	 */
	private function jit_taxonomy( $tax_key = '', $object_type = 'user' ) {

		// Bail if taxonomy already exists
		if ( taxonomy_exists( $tax_key ) ) {
			return;
		}

		// Register the taxonomy
		register_taxonomy( $tax_key, 'bbp_' . $object_type, array(
			'labels'                => array(),
			'description'           => '',
			'public'                => false,
			'publicly_queryable'    => false,
			'hierarchical'          => false,
			'show_ui'               => false,
			'show_in_menu'          => false,
			'show_in_nav_menus'     => false,
			'show_tagcloud'         => false,
			'show_in_quick_edit'    => false,
			'show_admin_column'     => false,
			'meta_box_cb'           => false,
			'capabilities'          => array(),
			'rewrite'               => false,
			'query_var'             => '',
			'update_count_callback' => '',
			'show_in_rest'          => false,
			'rest_base'             => false,
			'rest_controller_class' => false,
			'_builtin'              => false
		) );
	}

	/**
	 * Add a user id to an object
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 * @param bool   $unique    Whether meta key should be unique to the object
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function add_user_to_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post', $unique = false ) {
		$user_key = "{$meta_key}_user_id_{$user_id}";
		$tax_key  = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );

		return wp_add_object_terms( $object_id, $user_key, $tax_key );
	}

	/**
	 * Remove a user id from an object
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_user_from_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$user_key = "{$meta_key}_user_id_{$user_id}";
		$tax_key  = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );

		return wp_remove_object_terms( $object_id, $user_key, $tax_key );
	}

	/**
	 * Remove a user id from all objects
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_user_from_all_objects( $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$user_key = "{$meta_key}_user_id_{$user_id}";
		$tax_key  = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );
		$term     = get_term_by( 'slug', $user_key, $tax_key );

		return wp_delete_term( $term->term_id, $tax_key );
	}

	/**
	 * Remove an object from all users
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param int    $object_id The object id
	 * @param int    $user_id   The user id
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_object_from_all_users( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		return wp_delete_object_term_relationships( $object_id, get_object_taxonomies( 'bbp_user' ) );
	}

	/**
	 * Remove all users from all objects
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param string $meta_key  The relationship key
	 * @param string $meta_type The relationship type (usually 'post')
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_all_users_from_all_objects( $meta_key = '', $meta_type = 'post' ) {
		// TODO
	}

	/**
	 * Get users of an object
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param int    $object_id The object id
	 * @param string $meta_key  The key used to index this relationship
	 * @param string $meta_type The type of meta to look in
	 *
	 * @return array Returns ids of users
	 */
	public function get_users_for_object( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$user_key = "{$meta_key}_user_id_";
		$tax_key  = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );

		// Get terms
		$terms = get_terms( array(
			'object_ids' => $object_id,
			'taxonomy'   => $tax_key
		) );

		// Slug part to replace
		$user_ids = array();

		// Loop through terms and get the user ID
		foreach ( $terms as $term ) {
			$user_ids[] = str_replace( $user_key, '', $term->slug );
		}

		// Parse & return
		return wp_parse_id_list( $user_ids );
	}

	/**
	 * Get the part of the query responsible for JOINing objects to relationships.
	 *
	 * @since 2.6.0 bbPress (r6737)
	 *
	 * @param array  $args
	 * @param string $meta_key
	 * @param string $meta_type
	 *
	 * @return array
	 */
	public function get_query( $args = array(), $context_key = '', $meta_key = '', $meta_type = 'post' ) {
		$tax_key  = "{$meta_key}_{$meta_type}";
		$user_key = "{$meta_key}_user_id_";

		// Make sure the taxonomy is registered
		$this->jit_taxonomy( $tax_key );

		// Backwards compat for pre-2.6.0
		if ( is_numeric( $args ) ) {
			$args = array(
				'tax_query' => array( array(
					'taxonomy' => $tax_key,
					'terms'    => $user_key . bbp_get_user_id( $args, false, false ),
					'field'    => 'slug'
				) )
			);
		}

		// Default arguments
		$defaults = array(
			'tax_query' => array( array(
				'taxonomy' => $tax_key,
				'terms'    => $user_key . bbp_get_displayed_user_id(),
				'field'    => 'slug'
			) )
		);

		// Parse arguments
		return bbp_parse_args( $args, $defaults, $context_key );
	}
}
