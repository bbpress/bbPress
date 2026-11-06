<?php

/**
 * bbPress User Engagements Term Strategy.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Term strategy for interfacing with User Engagements.
 *
 * @since 2.6.0 bbPress (r6739)
 */
class BBP_User_Engagements_Term extends BBP_User_Engagements_Base {

	/**
	 * Type of strategy being used.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 *
	 * @var string
	 */
	public $type = 'term';

	/**
	 * Register an engagement taxonomy just-in-time for immediate use.
	 *
	 * @since 2.6.0 bbPress (r6739)
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
		register_taxonomy(
			$tax_key,
			'bbp_' . $object_type,
			array(
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
			)
		);
	}

	/**
	 * Add a user id to an object.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 *
	 * @param int    $object_id The object id.
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 * @param bool   $unique    Whether meta key should be unique to the object.
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function add_user_to_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post', $unique = false ) {
		$user_key = "{$meta_key}_user_id_{$user_id}";
		$tax_key  = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );

		return wp_add_object_terms( $object_id, $user_key, $tax_key );
	}

	/**
	 * Remove a user id from an object.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 *
	 * @param int    $object_id The object id.
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_user_from_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$user_key = "{$meta_key}_user_id_{$user_id}";
		$tax_key  = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );

		return wp_remove_object_terms( $object_id, $user_key, $tax_key );
	}

	/**
	 * Remove a user id from all objects.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 * @since 2.6.20 bbPress (r7958) Avoid a warning when the user relationship does not exist.
	 *
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_user_from_all_objects( $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$user_key = "{$meta_key}_user_id_{$user_id}";
		$tax_key  = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );
		$term     = get_term_by( 'slug', $user_key, $tax_key );

		// Bail if term does not exist.
		if ( empty( $term ) ) {
			return false;
		}

		return wp_delete_term( $term->term_id, $tax_key );
	}

	/**
	 * Remove an object from all users.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 * @since 2.6.17 bbPress (r7467) Limit removal to the requested relationship taxonomy.
	 * @since 2.6.20 bbPress (r7988) Return true after removing the requested relationships.
	 *
	 * @param int    $object_id The object id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Always returns true; term relationship removal does not report failures.
	 */
	public function remove_object_from_all_users( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$tax_key = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );

		wp_delete_object_term_relationships( $object_id, $tax_key );

		return true;
	}

	/**
	 * Remove all users from all objects.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 * @since 2.6.20 bbPress (r7958) Implement bulk term relationship cleanup.
	 *
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_all_users_from_all_objects( $meta_key = '', $meta_type = 'post' ) {
		$tax_key = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );
		$term_ids = get_terms(
			array(
				'taxonomy'               => $tax_key,
				'hide_empty'             => false,
				'fields'                 => 'ids',
				'orderby'                => 'none',
				'cache_results'          => false,
				'update_term_meta_cache' => false
			)
		);

		// Bail if terms cannot be queried.
		if ( is_wp_error( $term_ids ) ) {
			return false;
		}

		// Delete all relationship terms and their object relationships.
		foreach ( $term_ids as $term_id ) {
			$deleted = wp_delete_term( $term_id, $tax_key );

			if ( is_wp_error( $deleted ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get users of an object.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 *
	 * @param int    $object_id The object id.
	 * @param string $meta_key  The key used to index this relationship.
	 * @param string $meta_type The type of meta to look in.
	 *
	 * @return array Returns ids of users.
	 */
	public function get_users_for_object( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$user_key = "{$meta_key}_user_id_";
		$tax_key  = "{$meta_key}_{$meta_type}";
		$this->jit_taxonomy( $tax_key );

		// Get terms
		$terms = get_terms(
			array(
				'object_ids' => $object_id,
				'taxonomy'   => $tax_key
			)
		);

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
	 * @since 2.6.0 bbPress (r6739)
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
				'tax_query' => array(
					array(
						'taxonomy' => $tax_key,
						'terms'    => $user_key . bbp_get_user_id( $args, false, false ),
						'field'    => 'slug'
					)
				)
			);
		}

		// Default arguments
		$defaults = array(
			'tax_query' => array(
				array(
					'taxonomy' => $tax_key,
					'terms'    => $user_key . bbp_get_displayed_user_id(),
					'field'    => 'slug'
				)
			)
		);

		// Parse arguments
		return bbp_parse_args( $args, $defaults, $context_key );
	}
}
