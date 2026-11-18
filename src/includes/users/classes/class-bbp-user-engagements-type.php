<?php

/**
 * bbPress User Engagements Base Type.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Base class for an object type that users can engage with.
 *
 * The engagements strategy decides where a relationship between a user and an
 * object is stored. Engagement types describe the object itself, so code that
 * validates or links to an engagement does not need to know whether the object
 * is a post or something else.
 *
 * Extend this class and pass it as the class argument to
 * bbp_register_engagement_type() to support another object type.
 *
 * @since 2.7.0 bbPress (r8306)
 */
class BBP_User_Engagements_Type {

	/**
	 * Object type name, matching the relationship type passed to the
	 * engagements API (for example, 'post').
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Arguments the type was registered with.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @var array<string, mixed>
	 */
	public $args = array();

	/**
	 * Setup the engagement type.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param string $name Engagement type name.
	 * @param array<string, mixed> $args Arguments the type was registered with.
	 */
	public function __construct( $name = '', $args = array() ) {
		$this->name = $name;
		$this->args = $args;
	}

	/**
	 * Register callbacks that clean up relationships when an object is deleted.
	 *
	 * The default type does not register any callbacks. Types that do should
	 * remove the same callbacks in unregister_cleanup().
	 *
	 * @since 2.7.0 bbPress (r8390)
	 * @return void
	 */
	public function register_cleanup() {
		// Intentionally empty
	}

	/**
	 * Remove callbacks registered by this type.
	 *
	 * @since 2.7.0 bbPress (r8390)
	 * @return void
	 */
	public function unregister_cleanup() {
		// Intentionally empty
	}

	/**
	 * Check whether an object supports an engagement.
	 *
	 * Default denies by returning null.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param string $engagement Favorite or subscription.
	 * @param int    $object_id  Object ID.
	 * @return bool|null Whether the object supports the engagement, or null for the base implementation.
	 */
	public function supports( $engagement = '', $object_id = 0 ) {
		return null;
	}

	/**
	 * Check whether the current user can read an object.
	 *
	 * Default denies by returning null.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param int $object_id Object ID.
	 * @return bool|null Whether the current user can read the object, or null for the base implementation.
	 */
	public function current_user_can_read( $object_id = 0 ) {
		return null;
	}

	/**
	 * Check whether queries for this type support `tax_query`.
	 *
	 * Default does not, by returning null.
	 *
	 * @since 2.7.0 bbPress (r8318)
	 *
	 * @return bool|null Whether queries for this type support `tax_query`, or null for the base implementation.
	 */
	public function supports_tax_query() {
		return null;
	}

	/**
	 * Get query arguments that limit results to specific objects.
	 *
	 * Default has no query arguments and returns null. Queryable types must
	 * return arguments that limit results to the supplied object IDs.
	 *
	 * @since 2.7.0 bbPress (r8318)
	 *
	 * @param array<int, int> $object_ids Object IDs.
	 * @return array<string, mixed>|null Query arguments, or null for the base implementation.
	 */
	public function get_query_args( $object_ids = array() ) {
		return null;
	}

	/**
	 * Get query arguments that limit results to an object subtype.
	 *
	 * Default has no query arguments and returns null.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param string $object_subtype Registered object subtype.
	 * @return array<string, mixed>|null Query arguments, or null for the base implementation.
	 */
	public function get_subtype_query_args( $object_subtype = '' ) {
		return null;
	}

	/**
	 * Get the URL of an object.
	 *
	 * Default has no URL and returns null.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param int $object_id Object ID.
	 * @return string|false|null Object URL, false if it has none, or null for the base implementation.
	 */
	public function get_url( $object_id = 0 ) {
		return null;
	}
}
