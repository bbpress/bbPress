<?php

/**
 * bbPress User Notification Preferences.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Get the notification types that support a user email preference.
 *
 * Each registered subscription subtype gets its own preference.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @return array<string, array{label:string, default:bool}> Notification types, labels, and default values.
 */
function bbp_get_notification_types() {
	$types = array(
		'mentions' => array(
			'label'   => __( 'Mention emails', 'bbpress' ),
			'default' => false
		)
	);

	foreach ( bbp_get_subscription_subtype_labels() as $subtype => $label ) {
		$types[ 'subscriptions:' . $subtype ] = array(
			/* translators: %s: Subscription type, such as Forums or Topic Tags. */
			'label'   => sprintf( __( '%s emails', 'bbpress' ), $label ),
			'default' => true
		);
	}

	/**
	 * Filters the notification types that support a user email preference.
	 *
	 * @since 2.7.0 bbPress (r8363)
	 *
	 * @param array<string, array{label:string, default:bool}> $types Notification types, labels, and default values.
	 */
	return (array) apply_filters( 'bbp_get_notification_types', $types );
}

/**
 * Get a user's explicit notification preference.
 *
 * A missing key is distinct from an explicit false value.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @param int|string $user_id User ID.
 * @param string $type    Registered notification type.
 * @return bool|null Explicit preference, or null when unset.
 */
function bbp_get_user_notification_preference( $user_id = 0, $type = '' ) {
	$user_id = bbp_get_user_id( $user_id, false, true );
	$types   = bbp_get_notification_types();

	if ( empty( $user_id ) || ! is_string( $type ) || ! isset( $types[ $type ] ) ) {
		return null;
	}

	$preferences = get_user_meta( $user_id, '_bbp_notification_preferences', true );

	return is_array( $preferences ) && array_key_exists( $type, $preferences ) && is_bool( $preferences[ $type ] )
		? $preferences[ $type ]
		: null;
}

/**
 * Get the notification setting used when a user has not made a choice.
 *
 * @since 2.7.0 bbPress (r8376)
 *
 * @param int|string $user_id User ID.
 * @param string $type    Registered notification type.
 * @return bool Whether the default sends email.
 */
function bbp_get_user_notification_default( $user_id = 0, $type = '' ) {
	$user_id = bbp_get_user_id( $user_id, false, true );
	$types   = bbp_get_notification_types();

	if ( empty( $user_id ) || ! is_string( $type ) || ! isset( $types[ $type ] ) ) {
		return false;
	}

	/**
	 * Filters the inherited notification preference for a user.
	 *
	 * @since 2.7.0 bbPress (r8363)
	 *
	 * @param bool|null $value The inherited notification preference for a user.
	 * @param int|string $user_id User id.
	 * @param string $type Engagement type.
	 */
	$inherited = apply_filters( 'bbp_get_user_notification_inherited_preference', null, $user_id, $type );

	return is_bool( $inherited )
		? $inherited
		: ! empty( $types[ $type ]['default'] );
}

/**
 * Resolve a user's notification preference.
 *
 * An explicit choice wins. Integrations can supply a bool when the choice is
 * unset; otherwise the registered default is used.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @param int|string $user_id User ID.
 * @param string $type    Registered notification type.
 * @return bool Whether the notification is enabled.
 */
function bbp_is_user_notification_enabled( $user_id = 0, $type = '' ) {
	$user_id = bbp_get_user_id( $user_id, false, true );
	$types   = bbp_get_notification_types();

	if ( empty( $user_id ) || ! is_string( $type ) || ! isset( $types[ $type ] ) ) {
		return false;
	}

	$preference = bbp_get_user_notification_preference( $user_id, $type );

	if ( null !== $preference ) {
		$enabled = $preference;
	} else {
		/** This filter is documented in src/includes/users/notifications.php. */
		$inherited = apply_filters( 'bbp_get_user_notification_inherited_preference', null, $user_id, $type );
		$enabled   = is_bool( $inherited )
			? $inherited
			: ( ! empty( $types[ $type ]['default'] ) );
	}

	/**
	 * Filters whether the notification is enabled.
	 *
	 * @since 2.7.0 bbPress (r8363)
	 *
	 * @param bool $enabled Whether the notification is enabled.
	 * @param int|string $user_id User ID.
	 * @param string $type Registered notification type.
	 * @param bool|null $preference Explicit notification preference, or null when inherited.
	 */
	return (bool) apply_filters( 'bbp_is_user_notification_enabled', $enabled, $user_id, $type, $preference );
}

/**
 * Update several notification preferences in one global user-meta row.
 *
 * True and false are explicit choices. Null removes an explicit choice.
 * Unregistered types and non-boolean values are ignored.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @param int|string $user_id User ID.
 * @param array<array-key, mixed> $changes Preferences keyed by notification type.
 * @return bool Whether the preferences were accepted.
 */
function bbp_update_user_notification_preferences( $user_id = 0, $changes = array() ) {
	$user_id = bbp_get_user_id( $user_id, false, true );
	if ( empty( $user_id ) || ! is_array( $changes ) ) {
		return false;
	}

	$types       = bbp_get_notification_types();
	$preferences = get_user_meta( $user_id, '_bbp_notification_preferences', true );
	$preferences = is_array( $preferences ) ? $preferences : array();

	foreach ( $changes as $type => $value ) {
		if ( ! is_string( $type ) || ! isset( $types[ $type ] ) || ( null !== $value && ! is_bool( $value ) ) ) {
			continue;
		}

		if ( null === $value ) {
			unset( $preferences[ $type ] );
		} else {
			$preferences[ $type ] = $value;
		}
	}

	if ( empty( $preferences ) ) {
		delete_user_meta( $user_id, '_bbp_notification_preferences' );
	} else {
		update_user_meta( $user_id, '_bbp_notification_preferences', $preferences );
	}

	return true;
}

/**
 * Remove users who have disabled a notification type.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @param array<array-key, int|string>  $user_ids User IDs.
 * @param string $type     Registered notification type.
 * @return array<array-key, int|string> User IDs with email enabled.
 */
function bbp_filter_notification_user_ids( $user_ids = array(), $type = '' ) {
	$types = bbp_get_notification_types();

	if ( ! is_string( $type ) || ! isset( $types[ $type ] ) ) {
		return $user_ids;
	}

	// Prime user metadata in batches before checking each preference
	/** This filter is documented in src/includes/common/functions.php. */
	$limit = apply_filters( 'bbp_get_users_chunk_limit', 100 );
	foreach ( array_chunk( wp_parse_id_list( $user_ids ), $limit ) as $user_ids_chunk ) {
		cache_users( $user_ids_chunk );
	}

	foreach ( $user_ids as $key => $user_id ) {
		if ( ! bbp_is_user_notification_enabled( $user_id, $type ) ) {
			unset( $user_ids[ $key ] );
		}
	}

	return $user_ids;
}

/**
 * Save the notification preferences from an updated user profile.
 *
 * @since 2.7.0 bbPress (r8363)
 *
 * @param int $user_id Updated user ID.
 * @return void
 */
function bbp_save_user_notification_preferences( $user_id = 0 ) {
	if ( ! bbp_current_user_can_edit_user_field( 'email', $user_id ) || ! isset( $_POST['_bbp_notification_nonce'] ) || ! is_string( $_POST['_bbp_notification_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_bbp_notification_nonce'] ), 'bbp-notification-preferences-' . $user_id ) ) {
		return;
	}

	$submitted = isset( $_POST['bbp_notification_preferences'] ) && is_array( $_POST['bbp_notification_preferences'] )
		? wp_unslash( $_POST['bbp_notification_preferences'] )
		: array();
	$changes   = array();

	foreach ( bbp_get_notification_types() as $type => $args ) {
		if ( ! isset( $submitted[ $type ] ) || ! is_string( $submitted[ $type ] ) ) {
			continue;
		}

		switch ( $submitted[ $type ] ) {
			case 'yes' :
				$changes[ $type ] = true;
				break;

			case 'no' :
				$changes[ $type ] = false;
				break;

			case 'default' :
				$changes[ $type ] = null;
				break;
		}
	}

	if ( ! empty( $changes ) ) {
		bbp_update_user_notification_preferences( $user_id, $changes );
	}
}
