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

function bb_new_user( $username, $email, $website, $location, $interests ) {
	global $bbdb;
	$now       = bb_current_time('mysql');
	$password  = bb_random_pass();
	$passcrypt = md5( $password );

	$bbdb->query("INSERT INTO $bbdb->users
	(username,    user_regdate, user_password, user_email, user_website, user_from,  user_interest)
	VALUES
	('$username', '$now',       '$passcrypt',  '$email',   '$website',  '$location', '$interests')");
	
	$user_id = $bbdb->insert_id;
	bb_send_pass( $user_id, $password );
	bb_do_action('bb_new_user', $user_id);
	return $user_id;
}

function bb_update_user( $user_id, $website, $location, $interests ) {
	global $bbdb;

	$bbdb->query("UPDATE $bbdb->users SET
	user_website  = '$website',
	user_from     = '$location',
	user_interest = '$interests'
	WHERE user_id = '$user_id'
	");

	bb_do_action('bb_update_user', $user_id);
	return $user_id;
}

function bb_reset_email( $username ) {
	global $bbdb;
	$user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE username = '$username'");

	$resetkey = bb_random_pass( 15 );
	$bbdb->query("UPDATE $bbdb->users SET user_newpwdkey = '$resetkey' WHERE username = '$username'");

	if ( $user ) :
		mail( $user->user_email, bb_get_option('name') . ': Password Reset', "If you wanted to reset your password, you may do so by visiting the following address:

" . bb_get_option('uri') . "bb-reset-password.php?key=$resetkey

If you don't want to reset your password, just ignore this email. Thanks!", 'From: ' . bb_get_option('admin_email') );

	endif;
}

function bb_reset_password( $key ) {
	global $bbdb;
	$key = user_sanitize( $key );
	$user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_newpwdkey = '$key'");
	if ( $user ) :
		$newpass = bb_random_pass( 6 );
		bb_update_user_password( $user->user_id, $newpass );
		bb_send_pass           ( $user->user_id, $newpass );
		$bbdb->query("UPDATE $bbdb->users SET user_newpwdkey = '' WHERE user_id = $user->user_id");
	else :
		die('Key not found.');
	endif;
}

function bb_update_user_password( $user_id, $password ) {
	global $bbdb;
	$passhash = md5( $password );

	$bbdb->query("UPDATE $bbdb->users SET
	user_password = '$passhash'
	WHERE user_id = '$user_id'
	");

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
	$user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_id = $user");

	if ( $user ) :
		mail( $user->user_email, bb_get_option('name') . ': Password', "Your password is: $pass
You can now login: " . bb_get_option('uri') . "

Enjoy!", 'From: ' . bb_get_option('admin_email') );

	endif;
}
?>