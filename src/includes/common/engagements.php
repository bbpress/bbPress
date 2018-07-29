<?php

/**
 * bbPress Common Engagements
 *
 * This file contains the common classes and functions for interacting with the
 * bbPress engagements API. See `includes/users/engagements.php` for more.
 *
 * @package bbPress
 * @subpackage Common
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

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
