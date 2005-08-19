<?php

class BB_Roles {
	var $roles;

	var $role_objects = array();
	var $role_names = array();
	var $role_key;

	function BB_Roles() {
		global $bb_table_prefix;
		$this->role_key = $bb_table_prefix . 'user_roles';

		$this->roles = $this->get_roles($this->role_key);

		if ( empty($this->roles) )
			return;

		foreach ($this->roles as $role => $data) {
			$this->role_objects[$role] = new BB_Role($role, $this->roles[$role]['capabilities']);
			$this->role_names[$role] = $this->roles[$role]['name'];
		}
	}

	function get_roles( $role_key ) {
		return array(	'keymaster' => array(
					'name' => __('Key Master'),
					'capabilities' => array(
						'keep_gate' => true,		// Make new Key Masters		//+
						'recount' => true,		// bb-do-counts.php		//+
						'manage_options' => true,	// backend			//+
						'edit_users' => true,
						'manage_tags' => true,		// Rename, Merge, Destroy
						'edit_others_favorites' => true,
						'manage_forums' => true,	// Add forum
						'manage_topics' => true,	// Delete/Close/Stick
						'view_by_ip' => true,		// view-ip.php
						'edit_closed' => true,		// Edit closed topics
						'edit_deleted' => true,		// Edit deleted topics
						'browse_deleted' => true,	// Use 'deleted' view
						'edit_others_tags' => true,
						'edit_others_topics' => true,
						'manage_posts' => true,		// Delete
						'ignore_edit_lock' => true,
						'edit_others_posts' => true,
						'edit_favorites' => true,
						'edit_tags' => true,
						'edit_topics' => true,		// Edit title, resolution status
						'edit_posts' => true,
						'edit_profile' => true,
						'write_topics' => true,
						'write_posts' => true,
						'read' => true			// Not implemented
				)),

				'administrator' => array(
					'name' => __('Administrator'),
					'capabilities' => array(
						'edit_users' => true,			//+
						'manage_tags' => true,			//+
						'edit_others_favorites' => true,	//+
						'manage_forums' => true,		//+
						'manage_topics' => true,
						'view_by_ip' => true,
						'edit_closed' => true,
						'edit_deleted' => true,
						'browse_deleted' => true,
						'edit_others_tags' => true,
						'edit_others_topics' => true,
						'manage_posts' => true,
						'ignore_edit_lock' => true,
						'edit_others_posts' => true,
						'edit_favorites' => true,
						'edit_tags' => true,
						'edit_topics' => true,
						'edit_posts' => true,
						'edit_profile' => true,
						'write_topics' => true,
						'write_posts' => true,
						'read' => true
				)),

				'moderator' => array(
					'name' => __('Moderator'),
					'capabilities' => array(
						'manage_topics' => true,	//+
						'view_by_ip' => true,		//+
						'edit_closed' => true,		//+
						'edit_deleted' => true,		//+
						'browse_deleted' => true,	//+
						'edit_others_tags' => true,	//+
						'edit_others_topics' => true,	//+
						'manage_posts' => true,		//+
						'ignore_edit_lock' => true,	//+
						'edit_others_posts' => true,	//+
						'edit_favorites' => true,
						'edit_tags' => true,
						'edit_topics' => true,
						'edit_posts' => true,
						'edit_profile' => true,
						'write_topics' => true,
						'write_posts' => true,
						'read' => true
				)),

				'member' => array(
					'name' => __('Member'),
					'capabilities' => array(
						'edit_favorites' => true,
						'edit_tags' => true,
						'edit_topics' => true,
						'edit_posts' => true,
						'edit_profile' => true,
						'write_topics' => true,
						'write_posts' => true,
						'read' => true
				)),
											
				'inactive' => array(
					'name' => __('Inactive'),
					'capabilities' => array(
						'read' => true
				)),

				'blocked' => array(
					'name' => __('Blocked'),
					'capabilities' => array())
			);
	}


	function add_role($role, $capabilities, $display_name) {
		$this->roles[$role] = array('name' => $display_name, 'capabilities' => $capabilities);
		$this->role_objects[$role] = new BB_Role($role, $capabilities);
		$this->role_names[$role] = $display_name;
	}
	
	function remove_role($role) {
		if ( ! isset($this->role_objects[$role]) )
			return;
		
		unset($this->role_objects[$role]);
		unset($this->role_names[$role]);
		unset($this->roles[$role]);
	}

	function add_cap($role, $cap, $grant) {
		$this->roles[$role]['capabilities'][$cap] = $grant;
	}

	function remove_cap($role, $cap) {
		unset($this->roles[$role]['capabilities'][$cap]);
	}

	function &get_role($role) {
		if ( isset($this->role_objects[$role]) )
			return $this->role_objects[$role];
		else
			return null;
	}

	function get_names() {
		return $this->role_names;
	}

	function is_role($role)
	{
		return isset($this->role_names[$role]);
	}	
}

class BB_Role {
	var $name;
	var $capabilities;

	function BB_Role($role, $capabilities) {
		$this->name = $role;
		$this->capabilities = $capabilities;
	}

	function add_cap($cap, $grant) {
		global $bb_roles;

		$this->capabilities[$cap] = $grant;
		$bb_roles->add_cap($this->name, $cap);
	}

	function remove_cap($cap) {
		global $bb_roles;

		unset($this->capabilities[$cap]);
		$bb_roles->remove_cap($this->name, $cap);
	}

	function has_cap($cap) {
		if ( !empty($this->capabilities[$cap]) )
			return $this->capabilities[$cap];
		else
			return false;
	}

}

class BB_User {
	var $data;
	var $id = 0;
	var $ID = 0;
	var $caps = array();
	var $cap_key;
	var $roles = array();
	var $allcaps = array();

	function BB_User($id) {
		global $bb_roles, $bb_table_prefix;

		if ( is_numeric($id) ) {
			$this->data = bb_get_user($id);
		} else {
			$this->data = bb_get_user_by_name($id);
		}

		if ( empty($this->data->ID) )
			return;

		$this->id = $this->ID = $this->data->ID;
		$this->cap_key = $bb_table_prefix . 'capabilities';
		$this->caps = &$this->data->capabilities;
		if ( ! is_array($this->caps) )

			$this->caps = array();
		$this->get_role_caps();
	}
	
	function get_role_caps() {
		global $bb_roles;
		//Filter out caps that are not role names and assign to $this->roles
		if(is_array($this->caps))
			$this->roles = array_filter(array_keys($this->caps), array(&$bb_roles, 'is_role'));

		//Build $allcaps from role caps, overlay user's $caps
		$this->allcaps = array();
		foreach($this->roles as $role) {
			$role = $bb_roles->get_role($role);
			$this->allcaps = array_merge($this->allcaps, $role->capabilities);
		}
		$this->allcaps = array_merge($this->allcaps, $this->caps);
	}
	
	function add_role($role) {
		$this->caps[$role] = true;
		bb_update_usermeta($this->id, $this->cap_key, $this->caps);
		$this->get_role_caps();
	}
	
	function remove_role($role) {
		if ( empty($this->roles[$role]) || (count($this->roles) <= 1) )
			return;
		unset($this->caps[$role]);
		bb_update_usermeta($this->id, $this->cap_key, $this->caps);
		$this->get_role_caps();
	}
	
	function set_role($role) {
		foreach($this->roles as $oldrole)
			unset($this->caps[$oldrole]);
		$this->caps[$role] = true;
		$this->roles = array($role => true);
		bb_update_usermeta($this->id, $this->cap_key, $this->caps);
		$this->get_role_caps();
	}

	function add_cap($cap, $grant = true) {
		$this->caps[$cap] = $grant;
		bb_update_usermeta($this->id, $this->cap_key, $this->caps);
	}

	function remove_cap($cap) {
		if ( empty($this->roles[$cap]) ) return;
		unset($this->caps[$cap]);
		bb_update_usermeta($this->id, $this->cap_key, $this->caps);
	}
	
	function has_cap($cap) {
		global $bb_roles;

		$args = array_slice(func_get_args(), 1);
		$args = array_merge(array($cap, $this->id), $args);
		$caps = call_user_func_array('bb_map_meta_cap', $args);
		// Must have ALL requested caps
		foreach ($caps as $cap) {
			//echo "Checking cap $cap<br/>";
			if(empty($this->allcaps[$cap]) || !$this->allcaps[$cap])
				return false;
		}

		return true;
	}

}

// Map meta capabilities to primitive capabilities.
function bb_map_meta_cap($cap, $user_id) {
	$args = array_slice(func_get_args(), 2);
	$caps = array();

	switch ($cap) {
	case 'edit_post': // edit_posts, edit_others_posts, edit_deleted, edit_closed, ignore_edit_lock
		if ( !$post = bb_get_post( $args[0] ) ) :
			$caps[] = 'magically_provide_data_given_bad_input';
			return $caps;
		endif;
		if ( $user_id == $post->poster_id )
			$caps[] = 'edit_posts';
		else	$caps[] = 'edit_others_posts';
		if ( $post->post_status == '1' )
			$caps[] = 'edit_deleted';
		if ( !topic_is_open( $post->topic_id ) )
			$caps[] = 'edit_closed';
		$post_time = strtotime($post->post_time . '+0000');
		$curr_time = time();
                if ( $curr_time - $post_time > bb_get_option( 'edit_lock' ) * 60 )
			$caps[] = 'ignore_edit_lock';
		break;
	case 'edit_topic': // edit_closed, edit_deleted, edit_topics, edit_others_topics
		if ( !$topic = get_topic( $args[0] ) ) :
			$caps[] = 'magically_provide_data_given_bad_input';
			return $caps;
		endif;
		if ( !topic_is_open( $args[0]) )
			$caps[] = 'edit_closed';
		if ( '1' == $topic->topic_status )
			$caps[] = 'edit_deleted';
		if ( $user_id == $topic->topic_poster )
			$caps[] = 'edit_topics';
		else	$caps[] = 'edit_others_topics';
		break;
	case 'add_tag_to': // edit_closed, edit_deleted, edit_tags;
		if ( !$topic = get_topic( $args[0] ) ) :
			$caps[] = 'magically_provide_data_given_bad_input';
			return $caps;
		endif;
		if ( !topic_is_open( $topic->topic_id ) )
			$caps[] = 'edit_closed';
		if ( '1' == $topic->topic_status )
			$caps[] = 'edit_deleted';
		$caps[] = 'edit_tags';
		break;
	case 'edit_tag_by_on': // edit_closed, edit_deleted, edit_tags, edit_others_tags
		if ( !$topic = get_topic( $args[1] ) ) :
			$caps[] = 'magically_provide_data_given_bad_input';
			return $caps;
		endif;
		if ( !topic_is_open( $topic->topic_id ) )
			$caps[] = 'edit_closed';
		if ( '1' == $topic->topic_status )
			$caps[] = 'edit_deleted';
		if ( $user_id == $args[0] )
			$caps[] = 'edit_tags';
		else	$caps[] = 'edit_others_tags';
		break;
	case 'edit_user': // edit_profile, edit_users;
		if ( $user_id == $args[0] )
			$caps[] = 'edit_profile';
		else	$caps[] = 'edit_users';
		break;
	case 'edit_favorites_of': // edit_favorites, edit_others_favorites;
		if ( $user_id == $args[0] )
			$caps[] = 'edit_favorites';
		else	$caps[] = 'edit_others_favorites';
		break;
	default:
		// If no meta caps match, return the original cap.
		$caps[] = $cap;
	}

	return $caps;
}

// Capability checking wrapper around the global $current_user object.
function bb_current_user_can($capability) {
	global $current_user;

	$args = array_slice(func_get_args(), 1);
	$args = array_merge(array($capability), $args);

	if ( empty($current_user) )
		return false;

	return call_user_func_array(array(&$current_user, 'has_cap'), $args);
}
?>
