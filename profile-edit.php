<?php
require_once('./bb-load.php');

bb_auth();

if ( !bb_current_user_can( 'edit_user', $user_id ) ) {
	$sendto = bb_get_option('uri');
	wp_redirect( $sendto );
}

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( $bb_current_user->ID, 'edit' );
	wp_redirect( $sendto );
}

require_once(BBPATH . BBINC . '/registration-functions.php');

if ( !$user->capabilities )
	$user->capabilities = array('inactive' => true);
$profile_info_keys = get_profile_info_keys();
if ( bb_current_user_can('edit_users') ) {
	$profile_admin_keys = get_profile_admin_keys();
	$assignable_caps = get_assignable_caps();
}
$updated = false;
$user_email = true;

if ($_POST) :
	$_POST = stripslashes_deep( $_POST );
	bb_check_admin_referer( 'edit-profile_' . $user_id );

	$user_url = bb_fix_link( $_POST['user_url'] );
	if ( isset($_POST['user_email']) && $bb_current_user->ID == $user->ID )
		$user_email = bb_verify_email( $_POST['user_email'] );

	foreach ( $profile_info_keys as $key => $label ) :
		if ( is_null($$key) )
			$$key = $_POST[$key];
		$$key = apply_filters( 'sanitize_profile_info', $$key );
		if ( !$$key && $label[0] == 1 ) :
			$bad_input = true;
			$$key = false;
		endif;
	endforeach;

	if ( bb_current_user_can('edit_users') ):
		$role = $_POST['role'];
		foreach ( $profile_admin_keys as $key => $label ) :
			$$key = apply_filters( 'sanitize_profile_admin', $_POST[$key] );
			if ( !$$key && $label[0] == 1 ) :
				$bad_input = true;
				$$key = false;
			endif;
		endforeach;
		foreach ( $assignable_caps as $cap => $label )
			$$cap = ( isset($_POST[$cap]) && $_POST[$cap] ) ? 1 : 0;
		if ( isset($_POST['delete-user']) && $_POST['delete-user'] )
			$delete_user = 1;
	endif;

	$updated = true;

	if ( $user_email && !$bad_input ) :
		if ( bb_current_user_can( 'edit_user', $user->ID ) ) :
			$user_url = addslashes( $user_url );
			if ( is_string($user_email) ) {
				$user_email = addslashes( $user_email );
				bb_update_user( $user->ID, $user_email, $user_url );
			} else
				bb_update_user( $user->ID, $user->user_email, $user_url );
			foreach( $profile_info_keys as $key => $label )
				if ( strpos($key, 'user_') !== 0 )
					if ( $$key != ''  || isset($user->$key) )
						bb_update_usermeta( $user->ID, $key, $$key );
		endif;

		if ( bb_current_user_can('edit_users') ) :
			$user_obj = new BB_User( $user->ID );
			if ( !array_key_exists($role, $user->capabilities) && array_key_exists($role, $bb_roles->roles) ) {
				$old_role = $user_obj->roles[0];
				$user_obj->set_role($role); // Only support one role for now
				if ( 'blocked' == $role && 'blocked' != $old_role )
					bb_break_password( $user->ID );
				elseif ( 'blocked' != $role && 'blocked' == $old_role )
					bb_fix_password( $user->ID );
			}
			if ( isset($delete_user) && $delete_user )
				bb_delete_user( $user->ID );
			foreach( $profile_admin_keys as $key => $label )
				if ( $$key != ''  || isset($user->$key) )
					bb_update_usermeta( $user->ID, $key, $$key );
			foreach( $assignable_caps as $cap => $label ) :
				if ( ( !$already = array_key_exists($cap, $user->capabilities) ) && $$cap)
					$user_obj->add_cap($cap);
				elseif ( !$$cap && $already )
					$user_obj->remove_cap($cap);
			endforeach;
		endif;

		if ( bb_current_user_can( 'change_password' ) && !empty( $_POST['pass1'] ) && $_POST['pass1'] == $_POST['pass2'] && $bb_current_user->ID == $user->ID ) :
			$_POST['pass1'] = addslashes($_POST['pass1']);
			bb_update_user_password ( $bb_current_user->ID, $_POST['pass1'] );
			bb_cookie( bb_get_option( 'passcookie' ), md5( md5( $_POST['pass1'] ) ) ); // One week
		endif;
		
		do_action('profile_edited', $user->ID);

		$sendto = $delete_user ? bb_get_option( 'uri' ) : add_query_arg( 'updated', 'true', get_user_profile_link( $user->ID ) );
		wp_redirect( $sendto );
		exit();	
	endif;
endif;

if ( file_exists(BBPATH . 'my-templates/profile-edit.php') ) {
	require( BBPATH . 'my-templates/profile-edit.php' );
} else {
	require( BBPATH . 'bb-templates/profile-edit.php' );
}
?>
