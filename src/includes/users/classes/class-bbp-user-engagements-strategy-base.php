<?php

/**
 * bbPress User Engagements Base Strategy.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Base strategy class for interfacing with User Engagements, which other
 * classes will extend.
 *
 * @since 2.6.0 bbPress (r6739)
 */
class BBP_User_Engagements_Base {

	/**
	 * Type of strategy being used.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 *
	 * @var string
	 */
	public $type = '';

	/**
	 * Get the key a relationship is stored under.
	 *
	 * Strategies call this wherever they read or write a relationship, so a
	 * site can store a relationship under a different key without replacing
	 * the strategy.
	 *
	 * @since 2.7.0 bbPress (r8318)
	 *
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return string Storage key.
	 */
	protected function get_storage_key( $meta_key = '', $meta_type = 'post' ) {
		return (string) apply_filters( 'bbp_get_engagement_storage_key', $meta_key, $meta_type, $this );
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
		// Intentionally empty
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
		// Intentionally empty
	}

	/**
	 * Remove a user id from all objects.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 *
	 * @param int    $user_id   The user id.
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_user_from_all_objects( $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
		// Intentionally empty
	}

	/**
	 * Remove an object from all users.
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
	public function remove_object_from_all_users( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
		// Intentionally empty
	}

	/**
	 * Remove all users from all objects.
	 *
	 * @since 2.6.0 bbPress (r6739)
	 *
	 * @param string $meta_key  The relationship key.
	 * @param string $meta_type The relationship type (usually 'post').
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	public function remove_all_users_from_all_objects( $meta_key = '', $meta_type = 'post' ) {
		// Intentionally empty
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
		// Intentionally empty
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
	 * @return array Query arguments. An empty array means callers must not run a query.
	 */
	public function get_query( $args = array(), $context_key = '', $meta_key = '', $meta_type = 'post' ) {
		// Intentionally empty
	}
}
