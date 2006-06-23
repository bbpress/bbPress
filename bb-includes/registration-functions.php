<?php

function bb_verify_email( $email ) {
	if (ereg('^[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+'.'@'.
		'[-!#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.
		'[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$', $email)) {
		if ( $check_domain && function_exists('checkdnsrr') ) {
			list (, $domain)  = explode('@', $email);
			if ( checkdnsrr($domain . '.', 'MX') || checkdnsrr($domain . '.', 'A') ) {
				return $email;
			}
			return false;
		}
		return $email;
	}
	return false;
}

function bb_new_user( $user_login, $email, $url ) {
	global $bbdb, $bb_table_prefix;
	$now       = bb_current_time('mysql');
	$password  = bb_random_pass();
	$passcrypt = md5( $password );

	$bbdb->query("INSERT INTO $bbdb->users
	(user_login,     user_pass, user_email,  user_url, user_registered)
	VALUES
	('$user_login', '$passcrypt', '$email', '$url',   '$now')");
	
	$user_id = $bbdb->insert_id;

	if ( defined( 'BB_INSTALLING' ) ) {
		bb_update_usermeta( $user_id, $bb_table_prefix . 'capabilities', array('keymaster' => true) );
		bb_do_action('bb_new_user', $user_id);
		return $password;
	} else {		
		bb_update_usermeta( $user_id, $bb_table_prefix . 'capabilities', array('member' => true) );
		bb_send_pass( $user_id, $password );
		bb_do_action('bb_new_user', $user_id);
		return $user_id;
	}
}

function bb_update_user( $user_id, $email, $url ) {
	global $bbdb, $bb_cache;

	$bbdb->query("UPDATE $bbdb->users SET
	user_email = '$email',
	user_url   = '$url'
	WHERE ID   = '$user_id'
	");
	$bb_cache->flush_one( 'user', $user_id );

	bb_do_action('bb_update_user', $user_id);
	return $user_id;
}

function bb_reset_email( $user_login ) {
	global $bbdb;
	$user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user_login'");

	$resetkey = bb_random_pass( 15 );
	bb_update_usermeta( $user->ID, 'newpwdkey', $resetkey );
	if ( $user ) :
		mail( $user->user_email, bb_get_option('name') . ': ' . __('Password Reset'), sprintf( __("If you wanted to reset your password, you may do so by visiting the following address:

%s

If you don't want to reset your password, just ignore this email. Thanks!"), bb_get_option('uri')."bb-reset-password.php?key=".$resetkey ), 'From: ' . bb_get_option('admin_email') );

	endif;
}

function bb_reset_password( $key ) {
	global $bbdb;
	$key = user_sanitize( $key );
	if ( empty( $key ) )
		die(__('Key not found.'));
	$user_id = $bbdb->get_var("SELECT user_id FROM $bbdb->usermeta WHERE meta_key = 'newpwdkey' AND meta_value = '$key'");
	if ( $user = bb_get_user( $user_id ) ) :
		$newpass = bb_random_pass( 6 );
		bb_update_user_password( $user->ID, $newpass );
		bb_send_pass           ( $user->ID, $newpass );
		bb_update_usermeta( $user->ID, 'newpwdkey', '' );
	else :
		die(__('Key not found.'));
	endif;
}

function bb_update_user_password( $user_id, $password ) {
	global $bbdb, $bb_cache;
	$passhash = md5( $password );

	$bbdb->query("UPDATE $bbdb->users SET
	user_pass = '$passhash'
	WHERE ID = '$user_id'
	");
	$bb_cache->flush_one( 'user', $user_id );

	bb_do_action('bb_update_user_password', $user_id);
	return $user_id;
}

function bb_random_pass( $length = 6) {
	$number = mt_rand(1, 15);
	$string = md5( uniqid( microtime() ) );
 	$password = substr( $string, $number, $length );
	return $password;
}

function bb_send_pass( $user, $pass ) {
	global $bbdb;
	$user = (int) $user;
	$user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE ID = $user");

	if ( $user ) :
		$message = __("Your username is: %1\$s \nYour password is: %2\$s \nYou can now login: %3\$s \n\nEnjoy!");
		mail( $user->user_email, bb_get_option('name') . ':' . __('Password'), 
			sprintf( $message, "$user->user_login", "$pass", bb_get_option('uri') ), 
			'From: ' . bb_get_option('admin_email') 
		);

	endif;
}
?>
