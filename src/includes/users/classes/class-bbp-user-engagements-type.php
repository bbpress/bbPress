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
	 * @var array
	 */
	public $args = array();

	/**
	 * Setup the engagement type.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param string $name Engagement type name.
	 * @param array  $args Arguments the type was registered with.
	 */
	public function __construct( $name = '', $args = array() ) {
		$this->name = $name;
		$this->args = $args;
	}

	/**
	 * Check whether an object supports an engagement.
	 *
	 * Default denies by returning nothing.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param string $engagement Favorite or subscription.
	 * @param int    $object_id  Object ID.
	 * @return bool Whether the object supports the engagement.
	 */
	public function supports( $engagement = '', $object_id = 0 ) {
		// Intentionally empty
	}

	/**
	 * Check whether the current user can read an object.
	 *
	 * Default denies by returning nothing.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param int $object_id Object ID.
	 * @return bool Whether the current user can read the object.
	 */
	public function current_user_can_read( $object_id = 0 ) {
		// Intentionally empty
	}

	/**
	 * Get the URL of an object.
	 *
	 * Default has no URL and returns nothing.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param int $object_id Object ID.
	 * @return string|false Object URL, or false if it has none.
	 */
	public function get_url( $object_id = 0 ) {
		// Intentionally empty
	}
}
