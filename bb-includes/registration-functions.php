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
	$now      = current_time('mysql');
	$password = bb_random_pass();

	$bbdb->query("INSERT INTO $bbdb->users
	(username,    user_regdate, user_password, user_email, user_website, user_from,  user_interest)
	VALUES
	('$username', '$now',       '$password',   '$email',   '$website',  '$location', '$interests')");
	
	$user_id = $bbdb->insert_id;
	bb_do_action('bb_new_user', $user_id);
	return $user_id;
}

function bb_random_pass( $length = 6) {
	mt_srand( microtime() );
	$number = mt_rand(1, 15);
	$string = md5( uniqid( microtime() ) );
 	$password = substr( $string, $number, $length );
	return $password;
}
?>