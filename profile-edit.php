<?php
require_once('./bb-load.php');

bb_auth();

if ( !bb_current_user_can( 'edit_user', $user_id ) ) {
	$sendto = bb_get_option('uri');
	wp_redirect( $sendto );
}

$bb_current_id = bb_get_current_user_info( 'id' );

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( $bb_current_id, 'edit' );
	wp_redirect( $sendto );
}

require_once(BBPATH . BBINC . 'registration-functions.php');

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
	if ( isset($_POST['user_email']) && $bb_current_id == $user->ID )
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
		if ( isset($_POST['delete-user']) && $_POST['delete-user'] && $bb_current_id != $user->ID ) :
			bb_delete_user( $user->ID );
			wp_redirect( bb_get_option( 'uri' ) );
			exit;
		endif;
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
	endif;

	$updated = true;

	if ( $user_email && !$bad_input ) :
		if ( bb_current_user_can( 'edit_user', $user->ID ) ) :
			if ( is_string($user_email) && $bb_current_id == $user->ID ) {
				bb_update_user( $user->ID, $user_email, $user_url );
			} else
				bb_update_user( $user->ID, $user->user_email, $user_url );
			foreach( $profile_info_keys as $key => $label )
				if ( strpos($key, 'user_') !== 0 )
					if ( $$key != '' || isset($user->$key) )
						bb_update_usermeta( $user->ID, $key, $$key );
		endif;

		if ( bb_current_user_can( 'edit_users' ) ) :
			$user_obj = new BB_User( $user->ID );
			if ( ( 'keymaster' != $role || bb_current_user_can( 'keep_gate' ) ) && !array_key_exists($role, $user->capabilities) && array_key_exists($role, $bb_roles->roles) ) {
				$old_role = $user_obj->roles[0];
				if ( $bb_current_id != $user->ID || 'keymaster' != $old_role ) // keymasters cannot demote themselves
					$user_obj->set_role($role); // Only support one role for now
				if ( 'blocked' == $role && 'blocked' != $old_role )
					bb_break_password( $user->ID );
				elseif ( 'blocked' != $role && 'blocked' == $old_role )
					bb_fix_password( $user->ID );
			}
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

		if ( bb_current_user_can( 'change_user_password', $user->ID ) && !empty( $_POST['pass1'] ) && $_POST['pass1'] == $_POST['pass2'] ) :
			$_POST['pass1'] = addslashes($_POST['pass1']);
			bb_update_user_password( $user->ID, $_POST['pass1'] );
		endif;
		
		do_action('profile_edited', $user->ID);

		wp_redirect( add_query_arg( 'updated', 'true', get_user_profile_link( $user->ID ) ) );
		exit();	
	endif;
endif;

bb_load_template( 'profile-edit.php', array('profile_info_keys', 'profile_admin_keys', 'assignable_caps', 'updated', 'user_email', 'bb_roles') );

?>
