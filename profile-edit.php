<?php
require_once('./bb-load.php');

bb_auth();

if ( !bb_current_user_can( 'edit_user', $user_id ) ) {
	$sendto = bb_get_uri(null, null, BB_URI_CONTEXT_HEADER);
	wp_redirect( $sendto );
}

$bb_current_id = bb_get_current_user_info( 'id' );

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( $bb_current_id, 'edit' );
	wp_redirect( $sendto );
}

require_once(BB_PATH . BB_INC . 'registration-functions.php');

if ( !$user->capabilities )
	$user->capabilities = array('inactive' => true);
$profile_info_keys = get_profile_info_keys();
if ( bb_current_user_can('edit_users') ) {
	$profile_admin_keys = get_profile_admin_keys();
	$assignable_caps = get_assignable_caps();
}
$updated = false;
$user_email = true;

$errors = new WP_Error;

if ( 'post' == strtolower($_SERVER['REQUEST_METHOD']) ) {
	$_POST = stripslashes_deep( $_POST );
	bb_check_admin_referer( 'edit-profile_' . $user_id );

	$user_url = bb_fix_link( $_POST['user_url'] );
	if ( isset($_POST['user_email']) && $bb_current_id == $user->ID )
		if ( !$user_email = bb_verify_email( $_POST['user_email'] ) )
			$errors->add( 'user_email', __( 'Invalid email address' ), array( 'data' => $_POST['user_email'] ) );

	foreach ( $profile_info_keys as $key => $label ) {
		if ( isset($$key) )
			continue;

		$$key = apply_filters( 'sanitize_profile_info', $_POST[$key], $key, $_POST[$key] );
		if ( !$$key && $label[0] == 1 ) {
			$errors->add( $key, sprintf( __( '%s is required.' ), wp_specialchars( $label[1] ) ) );
			$$key = false;
		}
	}

	if ( bb_current_user_can('edit_users') ) {
		if ( isset($_POST['delete-user']) && $_POST['delete-user'] && $bb_current_id != $user->ID ) {
			bb_delete_user( $user->ID );
			wp_redirect( bb_get_uri(null, null, BB_URI_CONTEXT_HEADER) );
			exit;
		}

		$user_obj = new WP_User( $user->ID );

		$role = $_POST['role'];

		$can_keep_gate = bb_current_user_can( 'keep_gate' );
		if ( !array_key_exists($role, $bb_roles->roles) )
			$errors->add( 'role', __( 'Invalid Role' ) );
		elseif ( !$can_keep_gate && ( 'keymaster' == $role || 'keymaster' == $user_obj->roles[0] ) )
			$errors->add( 'role', __( 'You are not the Gate Keeper.' ) );
		elseif ( 'keymaster' == $user_obj->roles[0] && 'keymaster' != $role && $bb_current_id == $user->ID )
			$errors->add( 'role', __( 'You, Keymaster, may not demote yourself.' ) );

		foreach ( $profile_admin_keys as $key => $label ) {
			if ( isset($$key) )
				continue;
			$$key = apply_filters( 'sanitize_profile_admin', $_POST[$key], $key, $_POST[$key] );
			if ( !$$key && $label[0] == 1 ) {
				$errors->add( $key, sprintf( __( '%s is required.' ), wp_specialchars( $label[1] ) ) );
				$$key = false;
			}
		}

		foreach ( $assignable_caps as $cap => $label ) {
			if ( isset($$cap) )
				continue;
			$$cap = ( isset($_POST[$cap]) && $_POST[$cap] ) ? 1 : 0;
		}
	}

	if ( bb_current_user_can( 'change_user_password', $user->ID ) ) {
		if ( ( !empty($_POST['pass1']) || !empty($_POST['pass2']) ) && $_POST['pass1'] !== $_POST['pass2'] )
			$errors->add( 'pass', __( 'You must enter the same password twice.' ) );
		elseif( !empty($_POST['pass1']) && !bb_current_user_can( 'change_user_password', $user->ID ) )
			$errors->add( 'pass', __( "You are not allowed to change this user's password." ) );
	}

	$updated = true;

	if ( $user_email && !$errors->get_error_codes() ) {
		if ( bb_current_user_can( 'edit_user', $user->ID ) ) {
			if ( is_string($user_email) && $bb_current_id == $user->ID ) {
				bb_update_user( $user->ID, $user_email, $user_url );
			} else {
				bb_update_user( $user->ID, $user->user_email, $user_url );
			}
			foreach( $profile_info_keys as $key => $label )
				if ( strpos($key, 'user_') !== 0 )
					if ( $$key != '' || isset($user->$key) )
						bb_update_usermeta( $user->ID, $key, $$key );
		}

		if ( bb_current_user_can( 'edit_users' ) ) {
			if ( !array_key_exists($role, $user->capabilities) ) {
				$user_obj->set_role($role); // Only support one role for now
				if ( 'blocked' == $role && 'blocked' != $old_role )
					bb_break_password( $user->ID );
				elseif ( 'blocked' != $role && 'blocked' == $old_role )
					bb_fix_password( $user->ID );
			}
			foreach( $profile_admin_keys as $key => $label )
				if ( $$key != ''  || isset($user->$key) )
					bb_update_usermeta( $user->ID, $key, $$key );
			foreach( $assignable_caps as $cap => $label ) {
				if ( ( !$already = array_key_exists($cap, $user->capabilities) ) && $$cap)
					$user_obj->add_cap($cap);
				elseif ( !$$cap && $already )
					$user_obj->remove_cap($cap);
			}
		}

		if ( bb_current_user_can( 'change_user_password', $user->ID ) && !empty($_POST['pass1']) ) {
			$_POST['pass1'] = addslashes($_POST['pass1']);
			bb_update_user_password( $user->ID, $_POST['pass1'] );
		}
		
		do_action('profile_edited', $user->ID);

		wp_redirect( add_query_arg( 'updated', 'true', get_user_profile_link( $user->ID ) ) );
		exit();	
	}
}

bb_load_template( 'profile-edit.php', array('profile_info_keys', 'profile_admin_keys', 'assignable_caps', 'updated', 'user_email', 'bb_roles', 'errors') );

?>
