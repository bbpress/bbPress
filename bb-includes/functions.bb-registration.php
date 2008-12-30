<?php
/**
 * bbPress User Registration Tools
 *
 * @package bbPress
 */



/**
 * Verifies that an email is valid
 *
 * {@internal Missing Long Description}}
 *
 * @since 0.7.2
 * @param string $email Email address to verify
 * @return string|bool
 */
function bb_verify_email( $email, $check_domain = false ) {
	if (ereg('^[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+'.'@'.
		'[-!#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.
		'[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$', $email)) {
		if ( $check_domain && function_exists('checkdnsrr') ) {
			list (, $domain)  = explode('@', $email);
			if ( checkdnsrr($domain . '.', 'MX') || checkdnsrr($domain . '.', 'A') ) {
				$r = $email;
			} else {
				$r = false;
			}
		} else {
			$r = $email;
		}
	} else {
		$r = false;
	}
	return apply_filters( 'bb_verify_email', $r, $email );
}

/**
 * Updates a user's details in the database
 *
 * {@internal Missing Long Description}}
 *
 * @since 0.7.2
 * @global bbdb $bbdb
 *
 * @param int $user_id
 * @param string $user_email
 * @param string $user_url
 * @return int
 */
function bb_update_user( $user_id, $user_email, $user_url, $display_name ) {
	global $wp_users_object;

	$user_id = (int) $user_id;
	$user_url = bb_fix_link( $user_url );

	$wp_users_object->update_user( $user_id, compact( 'user_email', 'user_url', 'display_name' ) );

	do_action('bb_update_user', $user_id);
	return $user_id;
}

/**
 * Sends a reset password email
 *
 * Sends an email to the email address specified in the user's profile
 * prompting them to change their password.
 *
 * @since 0.7.2
 * @global bbdb $bbdb
 *
 * @param string $user_login
 * @return bool
 */
function bb_reset_email( $user_login ) {
	global $bbdb;

	$user_login = sanitize_user( $user_login, true );

	if ( !$user = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->users WHERE user_login = %s", $user_login ) ) )
		return new WP_Error('user_does_not_exist', __('The specified user does not exist.'));

	$resetkey = substr(md5(bb_generate_password()), 0, 15);
	bb_update_usermeta( $user->ID, 'newpwdkey', $resetkey );

	$message = sprintf(
		__("If you wanted to reset your password, you may do so by visiting the following address:\n\n%s\n\nIf you don't want to reset your password, just ignore this email. Thanks!"),
		bb_get_uri(
			'bb-reset-password.php',
			array('key' => $resetkey),
			BB_URI_CONTEXT_TEXT + BB_URI_CONTEXT_BB_USER_FORMS
		)
	);

	$mail_result = bb_mail(
		bb_get_user_email( $user->ID ),
		bb_get_option('name') . ': ' . __('Password Reset'),
		$message
	);

	if (!$mail_result) {
		return new WP_Error('sending_mail_failed', __('The email containing the password reset link could not be sent.'));
	} else {
		return true;
	}
}

/**
 * Handles the resetting of users' passwords
 *
 * Handles resetting a user's password, prompted by an email sent by
 * {@see bb_reset_email()}
 *
 * @since 0.7.2
 * @global bbdb $bbdb
 *
 * @param string $key
 * @return unknown
 */
function bb_reset_password( $key ) {
	global $bbdb;
	$key = sanitize_user( $key, true );
	if ( empty( $key ) )
		return new WP_Error('key_not_found', __('Key not found.'));
	if ( !$user_id = $bbdb->get_var( $bbdb->prepare( "SELECT user_id FROM $bbdb->usermeta WHERE meta_key = 'newpwdkey' AND meta_value = %s", $key ) ) )
		return new WP_Error('key_not_found', __('Key not found.'));
	if ( $user = new BP_User( $user_id ) ) {
		if ( bb_has_broken_pass( $user->ID ) )
			bb_block_current_user();
		if ( !$user->has_cap( 'change_user_password', $user->ID ) )
			return new WP_Error('permission_denied', __('You are not allowed to change your password.'));
		$newpass = bb_generate_password();
		bb_update_user_password( $user->ID, $newpass );
		if (!bb_send_pass( $user->ID, $newpass )) {
			return new WP_Error('sending_mail_failed', __('The email containing the new password could not be sent.'));
		} else {
			bb_update_usermeta( $user->ID, 'newpwdkey', '' );
			return true;
		}
	} else {
		return new WP_Error('key_not_found', __('Key not found.'));
	}
}

/**
 * Updates a user's password in the database
 *
 * {@internal Missing Long Description}}
 *
 * @since 0.7.2
 * @global bbdb $bbdb
 *
 * @param int $user_id
 * @param string $password
 * @return int
 */
function bb_update_user_password( $user_id, $password ) {
	global $wp_users_object;

	$user_id = (int) $user_id;

	$wp_users_object->set_password( $password, $user_id );

	do_action('bb_update_user_password', $user_id);
	return $user_id;
}

/**
 * Sends an email with the user's new password
 *
 * {@internal Missing Long Description}}
 *
 * @since 0.7.2
 * @global bbdb $bbdb {@internal Not used}}
 *
 * @param int|string $user
 * @param string $pass
 * @return bool
 */
function bb_send_pass( $user, $pass ) {
	if ( !$user = bb_get_user( $user ) )
		return false;

	$message = __("Your username is: %1\$s \nYour password is: %2\$s \nYou can now log in: %3\$s \n\nEnjoy!");

	return bb_mail(
		bb_get_user_email( $user->ID ),
		bb_get_option('name') . ': ' . __('Password'),
		sprintf($message, $user->user_login, $pass, bb_get_uri(null, null, BB_URI_CONTEXT_TEXT))
	);
}
