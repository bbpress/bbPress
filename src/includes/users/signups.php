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
?>

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

	// Posted role
	$forum_role = isset( $_POST['bbp-forums-role'] )
		? sanitize_key( $_POST['bbp-forums-role'] )
		: bbp_get_default_role();

	// Role keys
	$roles = array_keys( bbp_get_dynamic_roles() );

	// Bail if posted role is not in dynamic roles
	if ( empty( $forum_role ) || ! in_array( $forum_role, $roles, true ) ) {
		return $meta;
	}

	// Add role to meta
	$meta['bbp_new_role'] = $forum_role;

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

	// Posted role
	$forum_role = isset( $_POST['bbp-forums-role'] )
		? sanitize_key( $_POST['bbp-forums-role'] )
		: bbp_get_default_role();

	// Role keys
	$roles = array_keys( bbp_get_dynamic_roles() );

	// Bail if posted role is not in dynamic roles
	if ( empty( $forum_role ) || ! in_array( $forum_role, $roles, true ) ) {
		return;
	}

	// Option key
	$option_key = 'new_user_' . $newuser_key;

	// Get the user option
	$user_option = get_option( $option_key, array() );

	// Add the new role
	$user_option['bbp_new_role'] = $forum_role;

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

	// Posted role
	$forum_role = isset( $_POST['bbp-forums-role'] )
		? sanitize_key( $_POST['bbp-forums-role'] )
		: bbp_get_default_role();

	// Role keys
	$roles = array_keys( bbp_get_dynamic_roles() );

	// Bail if posted role is not in dynamic roles
	if ( empty( $forum_role ) || ! in_array( $forum_role, $roles, true ) ) {
		return;
	}

	// Set the user role
	bbp_set_user_role( $user_id, $forum_role );
}

/**
 * Multi-site handler for adding a new user
 *
 * @since 2.6.0 bbPress (r6674)
 *
 * @param int $user_id User ID.
 */
function bbp_user_add_role_on_activate( $user_id = 0, $password = '', $meta = array() ) {

	// Posted role
	$forum_role = isset( $meta['bbp_new_role'] )
		? sanitize_key( $meta['bbp_new_role'] )
		: bbp_get_default_role();

	// Sanitize role
	$roles = array_keys( bbp_get_dynamic_roles() );

	// Bail if posted role is not in dynamic roles
	if ( empty( $forum_role ) || ! in_array( $forum_role, $roles, true ) ) {
		return;
	}

	// Set the user role
	bbp_set_user_role( $user_id, $forum_role );
}
