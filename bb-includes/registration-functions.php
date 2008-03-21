<?php
/**
 * bbPress User Registration Tools
 *
 * @package bbPress
 */

/**
 * bb_verify_email() - Verifies that an email is valid
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @param string $email Email address to verify
 * @return string|bool
 */
function bb_verify_email( $email ) {
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
 * bb_update_user() - Updates a user's details in the database
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 * @global BB_Cache $bb_cache
 *
 * @param int $user_id
 * @param string $user_email
 * @param string $user_url
 * @return int
 */
function bb_update_user( $user_id, $user_email, $user_url ) {
	global $bbdb, $bb_cache;

	$ID = (int) $user_id;
	$user_url = bb_fix_link( $user_url );

	$bbdb->update( $bbdb->users, compact( 'user_email', 'user_url' ), compact( 'ID' ) );
	$bb_cache->flush_one( 'user', $ID );

	do_action('bb_update_user', $ID);
	return $ID;
}

/**
 * bb_reset_email() - Sends a reset password email
 *
 * Sends an email to the email address specified in the user's profile
 * prompting them to change their password.
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 *
 * @param string $user_login
 * @return bool
 */
function bb_reset_email( $user_login ) {
	global $bbdb;

	$user_login = sanitize_user( $user_login );

	if ( !$user = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->users WHERE user_login = %s", $user_login ) ) )
		return false;

	$resetkey = substr(md5(wp_generate_password()), 0, 15);
	bb_update_usermeta( $user->ID, 'newpwdkey', $resetkey );

	$message = sprintf( __("If you wanted to reset your password, you may do so by visiting the following address:\n\n%s\n\nIf you don't want to reset your password, just ignore this email. Thanks!"), bb_get_option('uri') . "bb-reset-password.php?key=$resetkey" );

	return bb_mail( bb_get_user_email( $user->ID ), bb_get_option('name') . ': ' . __('Password Reset'), $message );
}

/**
 * bb_reset_password() - Handles the resetting of users' passwords
 *
 * Handles resetting a user's password, prompted by an email sent by
 * {@see bb_reset_email()}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 *
 * @param string $key
 * @return unknown
 */
function bb_reset_password( $key ) {
	global $bbdb;
	$key = sanitize_user( $key );
	if ( empty( $key ) )
		bb_die(__('Key not found.'));
	if ( !$user_id = $bbdb->get_var( $bbdb->prepare( "SELECT user_id FROM $bbdb->usermeta WHERE meta_key = 'newpwdkey' AND meta_value = %s", $key ) ) )
		bb_die(__('Key not found.'));
	if ( $user = new WP_User( $user_id ) ) :
		if ( bb_has_broken_pass( $user->ID ) )
			bb_block_current_user();
		if ( !$user->has_cap( 'change_user_password', $user->ID ) )
			bb_die( __('You are not allowed to change your password.') );
		$newpass = wp_generate_password();
		bb_update_user_password( $user->ID, $newpass );
		bb_send_pass           ( $user->ID, $newpass );
		bb_update_usermeta( $user->ID, 'newpwdkey', '' );
	else :
		bb_die(__('Key not found.'));
	endif;
}

/**
 * bb_update_user_password() - Updates a user's password in the database
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 * @global BB_Cache $bb_cache
 *
 * @param int $user_id
 * @param string $password
 * @return int
 */
function bb_update_user_password( $user_id, $password ) {
	global $bbdb, $bb_cache;

	$ID = (int) $user_id;

	$user_pass = wp_hash_password( $password );

	$bbdb->update( $bbdb->users, compact( 'user_pass' ), compact( 'ID' ) );
	$bb_cache->flush_one( 'user', $ID );

	do_action('bb_update_user_password', $ID);
	return $ID;
}

/**
 * bb_send_pass() - Sends an email with the user's new password
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb {@internal Not used}}
 *
 * @param int|string $user
 * @param string $pass
 * @return bool
 */
function bb_send_pass( $user, $pass ) {
	global $bbdb;
	if ( !$user = bb_get_user( $user ) )
		return false;

	$message = __("Your username is: %1\$s \nYour password is: %2\$s \nYou can now log in: %3\$s \n\nEnjoy!");

	return bb_mail(
		bb_get_user_email( $user->ID ),
		bb_get_option('name') . ': ' . __('Password'),
		sprintf( $message, $user->user_login, $pass, bb_get_option('uri') )
	);
}
?>
