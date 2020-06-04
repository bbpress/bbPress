<?php

/**
 * bbPress Signups
 *
 * This file contains functions for assisting with adding forum data to user
 * accounts during signup, account creation, and invitation.
 *
 * @package bbPress
 * @subpackage Signups
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Output the forum-role field when adding a new user
 *
 * @since 2.6.0 bbPress (r6674)
 */
function bbp_add_user_form_role_field() {

	// Bail if current user cannot promote users
	if ( ! current_user_can( 'promote_users' ) ) {
		return;
	} ?>

	<table class="form-table">
		<tr class="form-field">
			<th scope="row"><label for="bbp-forums-role"><?php esc_html_e( 'Forum Role', 'bbpress' ); ?></label></th>
			<td><?php

				// Default user role
				$default_role  = isset( $_POST['bbp-forums-role'] )
					? sanitize_key( $_POST['bbp-forums-role'] )
					: bbp_get_default_role();

				// Get the folum roles
				$dynamic_roles = bbp_get_dynamic_roles();

				// Only keymasters can set other keymasters
				if ( ! bbp_is_user_keymaster() ) {
					unset( $dynamic_roles[ bbp_get_keymaster_role() ] );
				} ?>

				<select name="bbp-forums-role" id="bbp-forums-role">

					<?php foreach ( $dynamic_roles as $role => $details ) : ?>

						<option <?php selected( $default_role, $role ); ?> value="<?php echo esc_attr( $role ); ?>"><?php echo bbp_translate_user_role( $details['name'] ); ?></option>

					<?php endforeach; ?>

				</select>
			</td>
		</tr>
	</table>

<?php
}

/**
 * Maybe add forum role to signup meta array
 *
 * @since 2.6.0 bbPress (r6674)
 *
 * @param array $meta
 *
 * @return array
 */
function bbp_user_add_role_to_signup_meta( $meta = array() ) {

	// Bail if already added
	if ( ! empty( $meta['bbp_new_role'] ) ) {
		return $meta;
	}

	// Role to validate
	$to_validate = ! empty( $_POST['bbp-forums-role'] ) && is_string( $_POST['bbp-forums-role'] )
		? sanitize_key( $_POST['bbp-forums-role'] )
		: '';

	// Validate the signup role
	$valid_role = bbp_validate_registration_role( $to_validate );

	// Bail if errors
	if ( bbp_has_errors() ) {
		return $meta;
	}

	// Add role to meta
	$meta['bbp_new_role'] = $valid_role;

	// Return meta
	return $meta;
}

/**
 * Add forum meta data when inviting a user to a site
 *
 * @since 2.6.0 bbPress (r6674)
 *
 * @param int    $user_id     The invited user's ID.
 * @param array  $role        The role of invited user.
 * @param string $newuser_key The key of the invitation.
 */
function bbp_user_add_role_on_invite( $user_id = '', $role = '', $newuser_key = '' ) {

	// Role to validate
	$to_validate = ! empty( $_POST['bbp-forums-role'] ) && is_string( $_POST['bbp-forums-role'] )
		? sanitize_key( $_POST['bbp-forums-role'] )
		: '';

	// Validate the signup role
	$valid_role = bbp_validate_registration_role( $to_validate );

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	// Option key
	$option_key = 'new_user_' . $newuser_key;

	// Get the user option
	$user_option = get_option( $option_key, array() );

	// Add the new role
	$user_option['bbp_new_role'] = $valid_role;

	// Update the invitation
	update_option( $option_key, $user_option );
}

/**
 * Single-site handler for adding a new user
 *
 * @since 2.6.0 bbPress (r6674)
 *
 * @param int $user_id
 */
function bbp_user_add_role_on_register( $user_id = '' ) {

	// Role to validate
	$to_validate = ! empty( $_POST['bbp-forums-role'] ) && is_string( $_POST['bbp-forums-role'] )
		? sanitize_key( $_POST['bbp-forums-role'] )
		: '';

	// Validate the signup role
	$valid_role = bbp_validate_registration_role( $to_validate );

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	// Set the user role
	bbp_set_user_role( $user_id, $valid_role );
}

/**
 * Multi-site handler for adding a new user
 *
 * @since 2.6.0 bbPress (r6674)
 *
 * @param int $user_id User ID.
 */
function bbp_user_add_role_on_activate( $user_id = 0, $password = '', $meta = array() ) {

	// Role to validate
	$to_validate = ! empty( $meta['bbp_new_role'] ) && is_string( $meta['bbp_new_role'] )
		? sanitize_key( $meta['bbp_new_role'] )
		: '';

	// Validate the signup role
	$valid_role = bbp_validate_activation_role( $to_validate );

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	// Set the user role
	bbp_set_user_role( $user_id, $valid_role );
}

/** Validators ****************************************************************/

/**
 * Validate the Forum role during signup
 *
 * This helper function performs a number of generic checks, and encapsulates
 * the logic used to validate if a Forum Role is valid, typically during new
 * user registration, but also when adding an existing user to a site in
 * Multisite installations.
 *
 * @since 2.6.5
 *
 * @param string $to_validate A role ID to validate
 * @return string A valid role ID, or empty string on error
 */
function bbp_validate_signup_role( $to_validate = '' ) {

	// Default return value
	$retval = '';

	// Add error if role is empty
	if ( empty( $to_validate ) ) {
		bbp_add_error( 'bbp_signup_role_empty', __( '<strong>Error</strong>: Empty role.', 'bbpress' ) );
	}

	// Add error if posted role is not a valid role
	if ( ! bbp_is_valid_role( $to_validate ) ) {
		bbp_add_error( 'bbp_signup_role_invalid', __( '<strong>Error</strong>: Invalid role.', 'bbpress' ) );
	}

	// If no errors, set return value to the role to validate
	if ( ! bbp_has_errors() ) {
		$retval = $to_validate;
	}

	// Filter & return
	return (string) apply_filters( 'bbp_validate_signup_role', $retval, $to_validate );
}

/**
 * Validate the Forum role during the registration process
 *
 * @since 2.6.5
 *
 * @param string $to_validate A well-formed (string) role ID to validate
 * @return string A valid role ID, or empty string on error
 */
function bbp_validate_registration_role( $to_validate = '' ) {

	// Default return value
	$retval = bbp_get_default_role();

	// Conditionally handle posted values for capable users
	if ( is_admin() && current_user_can( 'create_users' ) ) {
		$retval = $to_validate;
	}

	// Validate & return
	return bbp_validate_signup_role( $retval );
}

/**
 * Validate the Forum role during activation
 *
 * This function exists simply for parity with registrations, and to maintain an
 * intentional layer of abstraction from the more generic function it uses.
 *
 * @since 2.6.5
 *
 * @param string $to_validate A well-formed (string) role ID to validate
 * @return string A valid role ID, or empty string on error
 */
function bbp_validate_activation_role( $to_validate = '' ) {

	// Validate & return
	return bbp_validate_signup_role( $to_validate );
}
