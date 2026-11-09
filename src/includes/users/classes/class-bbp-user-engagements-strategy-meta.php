<?php

/**
 * bbPress User Engagements Meta Strategy.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Meta strategy for interfacing with User Engagements.
 *
 * @since 2.6.0 bbPress (r6723)
 */
class BBP_User_Engagements_Meta extends BBP_User_Engagements_Base {

	/**
	 * Type of strategy being used.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 *
	 * @var string
	 */
	public $type = 'meta';

	/**
	 * Add a user id to an object.
	 *
	 * @since 2.6.0 bbPress (r6723)
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
		$storage_key = $this->get_storage_key( $meta_key, $meta_type );

		return add_metadata( $meta_type, $object_id, $storage_key, $user_id, $unique );
	}

	/**
	 * Remove a user id from an object.
	 *
	 * @since 2.6.0 bbPress (r6723)
	 *
	 * @param int    $object_id The object id.
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure
	 */
	public function remove_user_from_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$storage_key = $this->get_storage_key( $meta_key, $meta_type );

		return delete_metadata( $meta_type, $object_id, $storage_key, $user_id, false );
	}

	/**
	 * Remove a user id from all objects.
	 *
	 * @since 2.6.0 bbPress (r6723)
	 *
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_user_from_all_objects( $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$storage_key = $this->get_storage_key( $meta_key, $meta_type );

		return delete_metadata( $meta_type, null, $storage_key, $user_id, true );
	}

	/**
	 * Remove an object from all users.
	 *
	 * @since 2.6.0 bbPress (r6723)
	 *
	 * @param int    $object_id The object id.
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_object_from_all_users( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$storage_key = $this->get_storage_key( $meta_key, $meta_type );

		return delete_metadata( $meta_type, $object_id, $storage_key, null, false );
	}

	/**
	 * Remove all users from all objects.
	 *
	 * @since 2.6.0 bbPress (r6723)
	 *
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_all_users_from_all_objects( $meta_key = '', $meta_type = 'post' ) {
		$storage_key = $this->get_storage_key( $meta_key, $meta_type );

		return delete_metadata( $meta_type, null, $storage_key, null, true );
	}

	/**
	 * Get users of an object.
	 *
	 * @since 2.6.0 bbPress (r6723)
	 *
	 * @param int    $object_id The object id.
	 * @param string $meta_key  The key used to index this relationship.
	 * @param string $meta_type The type of meta to look in.
	 *
	 * @return array Returns ids of users.
	 */
	public function get_users_for_object( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		$storage_key = $this->get_storage_key( $meta_key, $meta_type );

		return wp_parse_id_list( get_metadata( $meta_type, $object_id, $storage_key, false ) );
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
		$storage_key = $this->get_storage_key( $meta_key, $meta_type );

		// Backwards compat for pre-2.6.0
		if ( is_numeric( $args ) ) {
			$args = array(
				'meta_query' => array(
					array(
						'key'     => $storage_key,
						'value'   => bbp_get_user_id( $args, false, false ),
						'compare' => 'NUMERIC'
					)
				)
			);
		}

		// Default arguments
		$defaults = array(
			'meta_query' => array(
				array(
					'key'     => $storage_key,
					'value'   => bbp_get_displayed_user_id(),
					'compare' => 'NUMERIC'
				)
			)
		);

		// Parse arguments
		return bbp_parse_args( $args, $defaults, $context_key );
	}
}
