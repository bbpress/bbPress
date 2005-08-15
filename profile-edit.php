<?php
require_once('bb-config.php');

if ( !current_user_can( 'edit_user', $user_id ) ) {
	$sendto = bb_get_option('uri');
	header("Location: $sendto");
}

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( $current_user->ID, 'edit' );
	header("Location: $sendto");
}

require_once( BBPATH . 'bb-includes/registration-functions.php');

nocache_headers();

$profile_info_keys = get_profile_info_keys();
if ( current_user_can('edit_users') )
	$profile_admin_keys = get_profile_admin_keys();
$updated = false;
$user_email = true;

if ($_POST) :
	$user_url = bb_fix_link( $_POST['user_url'] );
	if ( isset($_POST['user_email']) && $current_user->ID == $user->ID )
		$user_email = bb_verify_email( $_POST['user_email'] );

	foreach ( $profile_info_keys as $key => $label ) :
		if ( is_string($$key) ) :
			$$key = bb_specialchars( $$key, 1 );
		elseif ( is_null($$key) ) :
			$$key = bb_specialchars( $_POST[$key], 1 );
		endif;
		if ( !$$key && $label[0] == 1 ) :
			$bad_input = true;
			$$key = false;
		endif;
	endforeach;

	if ( current_user_can('edit_users') ):
		$role = bb_specialchars( $_POST['role'], 1 );
		foreach ( $profile_admin_keys as $key => $label ) :
			$$key = bb_specialchars( $_POST[$key], 1 );
			if ( !$$key && $label[0] == 1 ) :
				$bad_input = true;
				$$key = false;
			endif;
		endforeach;
		$user_status = bb_specialchars( $_POST['user_status'], 1 );
	endif;

	$updated = true;

	if ( $user_email && !$bad_input ) :
		if ( current_user_can( 'edit_user', $user->ID ) ) :
			if ( is_string($user_email) ) 
				bb_update_user( $user->ID, $user_email, $user_url );
			else	bb_update_user( $user->ID, $user->user_email, $user_url );
			foreach( $profile_info_keys as $key => $label )
				if ( strpos($key, 'user_') !== 0 )
					if ( $$key != ''  || isset($user->$key) )
						update_usermeta( $user->ID, $key, $$key );
		endif;

		if ( current_user_can('edit_users') ) :
			if ( !array_key_exists($role, $user->capabilities) && array_key_exists($role, $bb_roles->roles) ) {
				$user_obj = new BB_User( $user->ID );
				$user_obj->set_role($role); // Only support one role for now
			}
			if ( $user_status != $user->user_status )
				update_user_status( $user->ID, $user_status );
			foreach( $profile_admin_keys as $key => $label )
				if ( $$key != ''  || isset($user->$key) )
					update_usermeta( $user->ID, $key, $$key );
		endif;

		if ( !empty( $_POST['pass1'] ) && $_POST['pass1'] == $_POST['pass2'] && $current_user->ID == $user->ID ) :
			bb_update_user_password ( $current_user->ID, $_POST['pass1'] );
			bb_cookie( $bb->passcookie, md5( md5( $_POST['pass1'] ) ) ); // One week
		endif;

		$sendto = bb_add_query_arg( 'updated', 'true', get_user_profile_link( $user->ID ) );
		header("Location: $sendto");
		exit();	
	endif;
endif;

if (file_exists( BBPATH . 'my-templates/profile-edit.php' ))
	require( BBPATH . 'my-templates/profile-edit.php' );
else	require( BBPATH . 'bb-templates/profile-edit.php' );
?>
