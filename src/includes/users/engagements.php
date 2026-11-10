<?php

/**
 * bbPress User Engagement Functions.
 *
 * @package bbPress
 * @subpackage Engagements
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return the strategy used for storing user engagements.
 *
 * @since 2.6.0 bbPress (r6723)
 *
 * @param string $rel_key  The key used to index this relationship.
 * @param string $rel_type The type of meta to look in.
 *
 * @return string
 */
function bbp_user_engagements_interface( $rel_key = '', $rel_type = 'post' ) {
	return apply_filters( 'bbp_user_engagements_interface', bbpress()->engagements, $rel_key, $rel_type );
}

/** Engagement Types **********************************************************/

/**
 * Register an engagement type.
 *
 * The name must already be a sanitized key. Request handlers sanitize the
 * object type they receive, and lookups by name are exact.
 *
 * @since 2.7.0 bbPress (r8306)
 *
 * @param string $name Engagement type name, matching the relationship type
 *                     passed to the engagements API (for example, 'post').
 * @param array  $args {
 *     Optional. Engagement type arguments.
 *
 *     @type string $class Class name. Must be or extend BBP_User_Engagements_Type.
 * }
 * @return BBP_User_Engagements_Type|false The registered type, or false on failure.
 */
function bbp_register_engagement_type( $name = '', $args = array() ) {

	// Bail if name is empty or not already sanitized
	if ( ! is_string( $name ) || ( '' === $name ) || ( sanitize_key( $name ) !== $name ) ) {
		return false;
	}

	// Parse arguments
	$r = bbp_parse_args(
		$args,
		array(
			'class' => 'BBP_User_Engagements_Type'
		),
		'register_engagement_type'
	);

	// Bail if class is not an engagement type
	if ( ! is_string( $r['class'] ) || ! is_a( $r['class'], 'BBP_User_Engagements_Type', true ) ) {
		return false;
	}

	// Build and register the type
	$type = new $r['class']( $name, $r );

	bbpress()->engagement_types[ $name ] = $type;

	return $type;
}

/**
 * Deregister an engagement type.
 *
 * @since 2.7.0 bbPress (r8306)
 *
 * @param string $name Engagement type name.
 * @return bool False if the type does not exist, true on success.
 */
function bbp_deregister_engagement_type( $name = '' ) {
	$bbp = bbpress();

	if ( ! is_string( $name ) || ! isset( $bbp->engagement_types[ $name ] ) ) {
		return false;
	}

	unset( $bbp->engagement_types[ $name ] );

	return true;
}

/**
 * Get a registered engagement type.
 *
 * @since 2.7.0 bbPress (r8306)
 *
 * @param string $name Engagement type name, such as 'post'.
 * @return BBP_User_Engagements_Type|false The engagement type, or false if it is not registered.
 */
function bbp_get_engagement_type( $name = '' ) {
	$types  = bbpress()->engagement_types;
	$retval = ( is_string( $name ) && isset( $types[ $name ] ) )
		? $types[ $name ]
		: false;

	// Filter
	$retval = apply_filters( 'bbp_get_engagement_type', $retval, $name );

	// Return only valid engagement types
	return ( $retval instanceof BBP_User_Engagements_Type )
		? $retval
		: false;
}

/** User Relationships ********************************************************/

/**
 * Add a user id to an object.
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id.
 * @param int    $user_id   The user id.
 * @param string $rel_key   The relationship key.
 * @param string $rel_type  The relationship type (usually 'post').
 * @param bool   $unique    Whether meta key should be unique to the object.
 *
 * @return bool Returns true on success, false on failure.
 */
function bbp_add_user_to_object( $object_id = 0, $user_id = 0, $rel_key = '', $rel_type = 'post', $unique = false ) {
	$object_id = absint( $object_id );
	$user_id   = absint( $user_id );
	$retval    = bbp_user_engagements_interface( $rel_key, $rel_type )->add_user_to_object( $object_id, $user_id, $rel_key, $rel_type, $unique );

	// Filter & return
	return (bool) apply_filters( 'bbp_add_user_to_object', $retval, $object_id, $user_id, $rel_key, $rel_type, $unique );
}

/**
 * Remove a user id from an object.
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id.
 * @param int    $user_id   The user id.
 * @param string $rel_key   The relationship key.
 * @param string $rel_type  The relationship type (usually 'post').
 *
 * @return bool Returns true on success, false on failure.
 */
function bbp_remove_user_from_object( $object_id = 0, $user_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$retval = bbp_user_engagements_interface( $rel_key, $rel_type )->remove_user_from_object( $object_id, $user_id, $rel_key, $rel_type );

	// Filter & return
	return (bool) apply_filters( 'bbp_remove_user_from_object', $retval, $object_id, $user_id, $rel_key, $rel_type );
}

/**
 * Remove a user id from all objects.
 *
 * @since 2.6.0 bbPress (r6517)
 *
 * @param int    $user_id  The user id.
 * @param string $rel_key  The relationship key.
 * @param string $rel_type The relationship type (usually 'post').
 *
 * @return bool Returns true on success, false on failure.
 */
function bbp_remove_user_from_all_objects( $user_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$user_id = absint( $user_id );
	$retval  = bbp_user_engagements_interface( $rel_key, $rel_type )->remove_user_from_all_objects( $user_id, $rel_key, $rel_type );

	// Filter & return
	return (bool) apply_filters( 'bbp_remove_user_from_all_objects', $retval, $user_id, $rel_key, $rel_type );
}

/**
 * Remove an object from all users.
 *
 * @since 2.6.0 bbPress (r6544)
 *
 * @param int    $object_id The object id.
 * @param int    $user_id   The user id.
 * @param string $rel_key   The relationship key.
 * @param string $rel_type  The relationship type (usually 'post').
 *
 * @return bool Returns true on success, false on failure.
 */
function bbp_remove_object_from_all_users( $object_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$object_id = absint( $object_id );
	$retval    = bbp_user_engagements_interface( $rel_key, $rel_type )->remove_object_from_all_users( $object_id, $rel_key, $rel_type );

	// Filter & return
	return (bool) apply_filters( 'bbp_remove_object_from_all_users', $retval, $object_id, $rel_key, $rel_type );
}

/**
 * Remove all users from all objects.
 *
 * @since 2.6.0 bbPress (r6520)
 *
 * @param string $rel_key  The relationship key.
 * @param string $rel_type The relationship type (usually 'post').
 *
 * @return bool Returns true on success, false on failure.
 */
function bbp_remove_all_users_from_all_objects( $rel_key = '', $rel_type = 'post' ) {
	$retval = bbp_user_engagements_interface( $rel_key, $rel_type )->remove_all_users_from_all_objects( $rel_key, $rel_type );

	// Filter & return
	return (bool) apply_filters( 'bbp_remove_all_users_from_all_objects', $retval, $rel_key, $rel_type );
}

/**
 * Get users of an object.
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id.
 * @param string $rel_key   The key used to index this relationship.
 * @param string $rel_type  The type of meta to look in.
 *
 * @return array Returns ids of users
 */
function bbp_get_users_for_object( $object_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$object_id = absint( $object_id );
	$retval    = bbp_user_engagements_interface( $rel_key, $rel_type )->get_users_for_object( $object_id, $rel_key, $rel_type );

	// Filter & return
	return (array) apply_filters( 'bbp_get_users_for_object', $retval, $object_id, $rel_key, $rel_type );
}

/**
 * Check if an object has a specific user.
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id.
 * @param int    $user_id   The user id.
 * @param string $rel_key   The relationship key.
 * @param string $rel_type  The relationship type (usually 'post').
 *
 * @return bool Returns true if object has a user, false if not.
 */
function bbp_is_object_of_user( $object_id = 0, $user_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$object_id = absint( $object_id );
	$user_id   = absint( $user_id );
	$user_ids  = bbp_get_users_for_object( $object_id, $rel_key, $rel_type );
	$retval    = is_numeric( array_search( $user_id, $user_ids, true ) );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_object_of_user', $retval, $object_id, $user_id, $rel_key, $rel_type );
}

/**
 * Get the query part responsible for JOINing objects to user IDs.
 *
 * @since 2.6.0 bbPress (r6739)
 * @since 2.7.0 bbPress (r8318) Prevent unrestricted queries for unsupported types.
 * @since 2.7.0 bbPress (r8335) Pass optional object subtype context to queries.
 *
 * @param array  $args        Default query arguments.
 * @param string $context     Additional context.
 * @param string $rel_key     The relationship key.
 * @param string $rel_type    The relationship type (usually 'post').
 * @param string $rel_subtype Optional. Registered object subtype.
 *
 * @return array
 */
function bbp_get_user_object_query( $args = array(), $context = '', $rel_key = '', $rel_type = 'post', $rel_subtype = '' ) {
	$type        = bbp_get_engagement_type( $rel_type );
	$rel_subtype = is_string( $rel_subtype )
		? sanitize_key( $rel_subtype )
		: '';
	$retval      = bbp_user_engagements_interface( $rel_key, $rel_type )->get_query( $args, "get_user_{$context}", $rel_key, $rel_type );

	// Do not let filters turn an unsupported type into an unrestricted query
	if ( empty( $retval ) && ! empty( $type ) ) {
		return array();
	}

	// Add query arguments for the registered object subtype
	if ( ! empty( $retval ) && ! empty( $type ) && ! empty( $rel_subtype ) ) {
		$retval = array_merge( $retval, (array) $type->get_subtype_query_args( $rel_subtype ) );
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_user_object_query', $retval, $args, $context, $rel_key, $rel_type, $rel_subtype );
}

/** Engagements ***************************************************************/

/**
 * Get the users who have engaged in a topic.
 *
 * @since 2.6.0 bbPress (r6311)
 *
 * @param int $topic_id Optional. Topic id.
 *
 * @return array|bool Results if the topic has any engagements, otherwise false.
 */
function bbp_get_topic_engagements( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$users    = bbp_get_users_for_object( $topic_id, '_bbp_engagement' );

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_engagements', $users, $topic_id );
}

/**
 * Return the users who have engaged in a topic, directly with a database query.
 *
 * See: https://bbpress.trac.wordpress.org/ticket/3083
 *
 * @since 2.6.0 bbPress (r6522)
 * @since 2.6.17 bbPress (r7467) Honor filtered public reply statuses.
 *
 * @param int $topic_id Optional. Topic id.
 *
 * @return array
 */
function bbp_get_topic_engagements_raw( $topic_id = 0 ) {

	// Default variables
	$topic_id       = bbp_get_topic_id( $topic_id );
	$bbp_db         = bbp_db();
	$topic_type     = bbp_get_topic_post_type();
	$reply_type     = bbp_get_reply_post_type();
	$topic_statuses = array_unique( array_filter( bbp_get_public_topic_statuses() ) );
	$reply_statuses = array_unique( array_filter( bbp_get_public_reply_statuses() ) );
	$sql            = array();
	$values         = array();

	// Add the topic author for countable topic statuses
	if ( ! empty( $topic_statuses ) ) {
		$placeholders = implode( ', ', array_fill( 0, count( $topic_statuses ), '%s' ) );
		$sql[]        = "SELECT post_author FROM {$bbp_db->posts} WHERE ID = %d AND post_status IN ( {$placeholders} ) AND post_type = %s";
		$values       = array_merge( $values, array( $topic_id ), $topic_statuses, array( $topic_type ) );
	}

	// Add reply authors for countable reply statuses
	if ( ! empty( $reply_statuses ) ) {
		$placeholders = implode( ', ', array_fill( 0, count( $reply_statuses ), '%s' ) );
		$sql[]        = "SELECT post_author FROM {$bbp_db->posts} WHERE post_parent = %d AND post_status IN ( {$placeholders} ) AND post_type = %s";
		$values       = array_merge( $values, array( $topic_id ), $reply_statuses, array( $reply_type ) );
	}

	// Query unique topic and reply authors
	$results = ! empty( $sql )
		? $bbp_db->get_col( $bbp_db->prepare( implode( ' UNION ', $sql ), $values ) )
		: array();

	// Parse results into voices
	$engagements = wp_parse_id_list( array_filter( $results ) );

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_engagements_raw', $engagements, $topic_id );
}

/**
 * Get a user's topic engagements.
 *
 * @since 2.6.0 bbPress (r6311)
 * @since 2.6.0 bbPress (r6619) Signature changed to accept an array of arguments
 * @since 2.7.0 bbPress (r8318) Avoid querying when the registered type cannot limit results.
 *
 * @param array $args Optional. Arguments to pass into bbp_has_replies().
 *
 * @return bool True if user has engaged, otherwise false.
 */
function bbp_get_user_engagements( $args = array() ) {
	$r     = bbp_get_user_object_query( $args, 'engagements', '_bbp_engagement' );
	$query = ! empty( $r )
		? bbp_has_topics( $r )
		: array();

	// Filter & return
	return apply_filters( 'bbp_get_user_engagements', $query, 0, $r, $args );
}

/**
 * Check if a user is engaged in a topic or not.
 *
 * @since 2.6.0 bbPress (r6311)
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 *
 * @return bool True if the topic is in user's engagements, otherwise false.
 */
function bbp_is_user_engaged( $user_id = 0, $topic_id = 0 ) {
	$user_id  = bbp_get_user_id( $user_id, true, true );
	$topic_id = bbp_get_topic_id( $topic_id );
	$retval   = bbp_is_object_of_user( $topic_id, $user_id, '_bbp_engagement' );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_engaged', $retval, $user_id, $topic_id );
}

/**
 * Add a topic to user's engagements.
 *
 * Note that both the User and Topic should be verified to exist before using
 * this function. Originally both were validated, but because this function is
 * frequently used within a loop, those verifications were moved upstream to
 * improve performance on topics with many engaged users.
 *
 * @since 2.6.0 bbPress (r6311)
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 *
 * @return bool Always true
 */
function bbp_add_user_engagement( $user_id = 0, $topic_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if already a engaged
	if ( bbp_is_user_engaged( $user_id, $topic_id ) ) {
		return false;
	}

	// Bail if add fails
	if ( ! bbp_add_user_to_object( $topic_id, $user_id, '_bbp_engagement' ) ) {
		return false;
	}

	do_action( 'bbp_add_user_engagement', $user_id, $topic_id );

	return true;
}

/**
 * Remove a topic from user's engagements.
 *
 * @since 2.6.0 bbPress (r6311)
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 *
 * @return bool True if the topic was removed from user's engagements, otherwise
 *               false.
 */
function bbp_remove_user_engagement( $user_id = 0, $topic_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if not already engaged
	if ( ! bbp_is_user_engaged( $user_id, $topic_id ) ) {
		return false;
	}

	// Bail if remove fails
	if ( ! bbp_remove_user_from_object( $topic_id, $user_id, '_bbp_engagement' ) ) {
		return false;
	}

	do_action( 'bbp_remove_user_engagement', $user_id, $topic_id );

	return true;
}

/**
 * Recalculate all of the users who have engaged in a topic.
 *
 * This happens when permanently deleting a reply, because that reply author may
 * have authored other replies to that same topic, or the topic itself.
 *
 * You may need to do this manually on heavily active forums where engagement
 * count accuracy is important.
 *
 * @since 2.6.0 bbPress (r6522)
 *
 * @param int  $topic_id Topic id.
 * @param bool $force    Force remove & add, even if old equals new.
 *
 * @return boolean True if any engagements are added, false otherwise
 */
function bbp_recalculate_topic_engagements( $topic_id = 0, $force = false ) {

	// Default return value
	$retval = false;

	// Check post type
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Bail if no topic ID
	if ( empty( $topic_id ) ) {
		return $retval;
	}

	// Query for engagements
	$old_engagements = bbp_get_topic_engagements( $topic_id );
	$new_engagements = bbp_get_topic_engagements_raw( $topic_id );

	// Sort arrays
	sort( $old_engagements, SORT_NUMERIC );
	sort( $new_engagements, SORT_NUMERIC );

	// Only recalculate on change
	if ( ( true === $force ) || ( $old_engagements !== $new_engagements ) ) {

		// Delete all engagements
		bbp_remove_object_from_all_users( $topic_id, '_bbp_engagement' );

		// Update the voice count for this topic id
		foreach ( $new_engagements as $user_id ) {
			$retval = bbp_add_user_engagement( $user_id, $topic_id );
		}
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_recalculate_user_engagements', $retval, $topic_id );
}

/**
 * Update the engagements of a topic.
 *
 * Hooked to 'bbp_new_topic' and 'bbp_new_reply', this gets the post author and
 * if not anonymous, passes it into bbp_add_user_engagement().
 *
 * @since 2.6.0 bbPress (r6529)
 *
 * @param int $topic_id Topic id.
 */
function bbp_update_topic_engagements( $topic_id = 0 ) {

	// Is a reply
	if ( bbp_is_reply( $topic_id ) ) {

		// Bail if reply isn't published
		if ( ! bbp_is_reply_published( $topic_id ) ) {
			return;
		}

		$author_id = bbp_get_reply_author_id( $topic_id );
		$topic_id  = bbp_get_reply_topic_id( $topic_id );

	// Is a topic
	} elseif ( bbp_is_topic( $topic_id ) ) {

		// Bail if topic isn't published
		if ( ! bbp_is_topic_published( $topic_id ) ) {
			return;
		}

		$author_id = bbp_get_topic_author_id( $topic_id );
		$topic_id  = bbp_get_topic_id( $topic_id );

	// Is unknown
	} else {
		return;
	}

	// Bail if topic is not public
	if ( ! bbp_is_topic_public( $topic_id ) ) {
		return;
	}

	// Return whether engagement was added
	return bbp_add_user_engagement( $author_id, $topic_id );
}

/** Engagement Toggles ********************************************************/

/**
 * Check whether the current user can manage a user's engagements.
 *
 * With no user ID, this function uses the displayed user and returns false if
 * no user is displayed.
 *
 * @since 2.6.20 bbPress (r7849)
 *
 * @param string $engagement Favorite or subscription. Defaults to the current
 *                           favorites or subscriptions view.
 * @param int    $user_id    User ID. Defaults to the displayed user.
 * @return bool Whether the current user can manage the user's engagements.
 */
function bbp_current_user_can_manage_engagements( $engagement = '', $user_id = 0 ) {
	if ( empty( $engagement ) ) {
		if ( bbp_is_favorites() ) {
			$engagement = 'favorite';
		} elseif ( bbp_is_subscriptions() ) {
			$engagement = 'subscription';
		}
	}

	$user_id         = ! empty( $user_id )
		? bbp_get_user_id( $user_id, false, false )
		: (int) bbp_get_displayed_user_id();
	$current_user_id = bbp_get_current_user_id();
	$retval          = in_array( $engagement, array( 'favorite', 'subscription' ), true )
		&& ! empty( $user_id )
		&& ( ( $user_id === $current_user_id ) || current_user_can( 'edit_user', $user_id ) );

	// Filter & return.
	return (bool) apply_filters( 'bbp_current_user_can_manage_engagements', $retval, $engagement, $user_id, $current_user_id );
}

/**
 * Check whether the current user can toggle a favorite or subscription.
 *
 * The registered engagement type decides whether the object supports the
 * engagement, and whether the current user can read the object when adding
 * one. Existing engagements may be removed after visibility changes.
 *
 * @since 2.6.19 bbPress (r7642)
 * @since 2.6.20 bbPress (r7849) Added the `$user_id` parameter.
 * @since 2.7.0 bbPress (r8306) Delegate object checks to engagement types.
 *
 * @param int    $object_id   Object ID.
 * @param string $object_type Registered engagement type name.
 * @param string $engagement  Favorite or subscription.
 * @param string $action      Add or remove.
 * @param int    $user_id     User ID. Defaults to the current user.
 * @return bool Whether the current user can toggle the engagement.
 */
function bbp_current_user_can_toggle_engagement( $object_id = 0, $object_type = 'post', $engagement = 'subscription', $action = 'add', $user_id = 0 ) {
	$user_id = ! empty( $user_id )
		? bbp_get_user_id( $user_id, false, false )
		: bbp_get_current_user_id();

	$type = bbp_get_engagement_type( $object_type );

	if ( empty( $user_id ) || empty( $type ) || ! in_array( $action, array( 'add', 'remove' ), true ) || ! bbp_current_user_can_manage_engagements( $engagement, $user_id ) || ! $type->supports( $engagement, $object_id ) ) {
		return false;
	}

	return ( 'remove' === $action ) || (bool) $type->current_user_can_read( $object_id );
}

/** Query Helpers *************************************************************/

/**
 * These functions are no longer used in bbPress due to general performance
 * concerns on large installations. They are provided here for convenience and
 * backwards compatibility only.
 */

/**
 * Get a user's object IDs
 *
 * For the most part, you should not need to use this function, and may even
 * want to come up with a more efficient way to get IDs on your own. Nevertheless,
 * it is available here for your convenience, using the most efficient query
 * parameters available inside of the various query APIs.
 *
 * @since 2.6.0 bbPress (r6607)
 *
 * @param int    $user_id   The user id.
 * @param string $rel_key   The relationship key.
 * @param string $rel_type  The relationship type (usually 'post').
 * @param array  $args      The arguments to override defaults.
 *
 * @return array|bool Results if user has objects, otherwise null.
 */
function bbp_get_user_object_ids( $args = array() ) {
	$object_ids = $defaults = array();

	// Parse arguments
	$r = bbp_parse_args(
		$args,
		array(
			'user_id'     => 0,
			'object_type' => bbp_get_topic_post_type(),
			'rel_key'     => '',
			'rel_type'    => 'post',
			'filter'      => 'user_object_ids',
			'args'        => array()
		),
		'get_user_object_ids'
	);

	// Sanitize arguments
	$r['user_id']     = bbp_get_user_id( $r['user_id'] );
	$r['rel_key']     = sanitize_key( $r['rel_key'] );
	$r['rel_type']    = sanitize_key( $r['rel_type'] );
	$r['object_type'] = sanitize_key( $r['object_type'] );
	$r['filter']      = sanitize_key( $r['filter'] );

	// Defaults
	if ( 'post' === $r['rel_type'] ) {
		$defaults = array(
			'fields'         => 'ids',
			'post_type'      => $r['object_type'],
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => $r['rel_key'],
					'value'   => $r['user_id'],
					'compare' => 'NUMERIC'
				),
				// Performance
				'nopaging'               => true,
				'suppress_filters'       => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true
			)
		);
	}

	// Parse arguments
	$query_args = bbp_parse_args( $r['args'], $defaults, "get_{$r['filter']}_args" );

	// Queries
	if ( 'post' === $r['rel_type'] ) {
		$query      = new WP_Query( $query_args );
		$object_ids = $query->posts;
	}

	// Filter & return
	return (array) apply_filters( "bbp_get_{$r['filter']}", $object_ids, $r, $args );
}

/**
 * Get array of forum IDs that a user can moderate.
 *
 * @since 2.6.0 bbPress (r5834)
 *
 * @param int $user_id User id.
 *
 * @return array Return array of forum ids, or empty array.
 */
function bbp_get_moderator_forum_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids(
		array(
			'user_id'     => $user_id,
			'rel_key'     => '_bbp_moderator_id',
			'object_type' => bbp_get_forum_post_type(),
			'filter'      => 'moderator_forum_ids'
		)
	);
}

/**
 * Get a user's engaged topic ids.
 *
 * @since 2.6.0 bbPress (r6311)
 *
 * @param int $user_id Optional. User id.
 *
 * @return array Return array of topic ids, or empty array.
 */
function bbp_get_user_engaged_topic_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids(
		array(
			'user_id' => $user_id,
			'rel_key' => '_bbp_engagement',
			'filter'  => 'user_engaged_topic_ids'
		)
	);
}
