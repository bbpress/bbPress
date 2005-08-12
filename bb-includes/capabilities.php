<?php

class BB_Roles {
	var $roles;

	var $role_objects = array();
	var $role_names = array();
	var $role_key;

	function BB_Roles() {
		global $table_prefix;
		$this->role_key = $table_prefix . 'user_roles';

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
						'keep_gate' => true,		// Make new Key Masters
						'recount' => true,		// bb-do-counts.php
						'manage_options' => true,	// backend
						'activate_plugins' => true,
						'edit_users' => true,
						'manage_tags' => true,		// Rename, Merge, Destroy
						'edit_deleted' => true,		// Edit deleted topics/posts
						'browse_deleted' => true,	// Use 'deleted' View
						'view_by_ip' => true,		// view-ip.php
						'edit_others_favorites' => true,
						'edit_others_tags' => true,
						'edit_others_topics' => true,
						'ignore_edit_lock' => true,
						'edit_others_posts' => true,
						'edit_favorites' => true,
						'edit_tags' => true,
						'edit_topics' => true,
						'edit_posts' => true,
						'edit_profile' => true,
						'write_topics' => true,		// Not implemented
						'write_posts' => true,		// Not implemented
						'read' => true			// Not implemented
				)),

				'administrator' => array(
					'name' => __('Administrator'),
					'capabilities' => array(
						'edit_users' => true,
						'manage_tags' => true,
						'edit_deleted' => true,
						'browse_deleted' => true,
						'view_by_ip' => true,
						'edit_others_favorites' => true,
						'edit_others_tags' => true,
						'edit_others_topics' => true,
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
						'edit_deleted' => true,
						'browse_deleted' => true,
						'view_by_ip' => true,
						'edit_others_favorites' => true,
						'edit_others_tags' => true,
						'edit_others_topics' => true,
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
	var $caps = array();
	var $user_type; //Temporary
	var $ID; //Temporary
	var $user_status; //Temporary
	var $favorites; //Temporary
	var $user_login; //Temporary
	var $topics_replied; //Temporary
	var $cap_key;
	var $roles = array();
	var $allcaps = array();

	function BB_User($id) {
		global $bb_roles, $table_prefix;

		if ( is_numeric($id) ) {
			$this->data = bb_get_user($id);
		} else {
			$this->data = bb_get_user_by_name($id);
		}

		if ( empty($this->data->ID) )
			return;

		$this->id = $this->data->ID;
		$this->cap_key = $table_prefix . 'capabilities';
		$this->caps = &$this->data->capabilities; // prefix it?
		$this->user_type = &$this->data->user_type; //
		$this->favorites = &$this->data->favorites; //
		$this->topics_replied = &$this->data->topics_replied; //
		$this->ID = $this->data->ID; //
		$this->user_status = $this->data->user_status; //
		$this->user_login = $this->data->user_login; //
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
		update_usermeta($this->id, $this->cap_key, $this->caps);
		$this->get_role_caps();
	}
	
	function remove_role($role) {
		if ( empty($this->roles[$role]) || (count($this->roles) <= 1) )
			return;
		unset($this->caps[$role]);
		update_usermeta($this->id, $this->cap_key, $this->caps);
		$this->get_role_caps();
	}
	
	function set_role($role) {
		foreach($this->roles as $oldrole)
			unset($this->caps[$oldrole]);
		$this->caps[$role] = true;
		$this->roles = array($role => true);
		update_usermeta($this->id, $this->cap_key, $this->caps);
		$this->get_role_caps();
	}

	function add_cap($cap, $grant = true) {
		$this->caps[$cap] = $grant;
		update_usermeta($this->id, $this->cap_key, $this->caps);
	}

	function remove_cap($cap) {
		if ( empty($this->roles[$cap]) ) return;
		unset($this->caps[$cap]);
		update_usermeta($this->id, $this->cap_key, $this->caps);
	}
	
	//has_cap(capability_or_role_name) or
	//has_cap('edit_post', post_id)
	function has_cap($cap) {
		global $bb_roles;

		$args = array_slice(func_get_args(), 1);
		$args = array_merge(array($cap, $this->id), $args);
		$caps = call_user_func_array('map_meta_cap', $args);
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
function map_meta_cap($cap, $user_id) {
	$args = array_slice(func_get_args(), 2);
	$caps = array();

	switch ($cap) {
		// edit_post breaks down to edit_posts, edit_published_posts, or
		// edit_others_posts
	case 'edit_post':
		$author_data = bb_get_user($user_id);
		//echo "post ID: {$args[0]}<br/>";
		$post = get_post($args[0]);
		$post_author_data = bb_get_user($post->poster_id);
		//echo "current user id : $user_id, post author id: " . $post_author_data->ID . "<br/>";
		// If the user is the author...
		if ($user_id == $post_author_data->ID) {
			// If the post is published...
			$caps[] = 'edit_posts';
			if ($post->post_status == '1')
				// If the post is deleted...
				$caps[] = 'edit_deleted';
		} else {
			// The user is trying to edit someone else's post.
			$caps[] = 'edit_others_posts';
			// The post is deleted, extra cap required.
			if ($post->post_status == '1')
				$caps[] = 'edit_deleted';
		}
		break;
	default:
		// If no meta caps match, return the original cap.
		$caps[] = $cap;
	}

	return $caps;
}

// Capability checking wrapper around the global $current_user object.
function current_user_can($capability) {
	global $current_user;

	$args = array_slice(func_get_args(), 1);
	$args = array_merge(array($capability), $args);

	if ( empty($current_user) )
		return false;

	return call_user_func_array(array(&$current_user, 'has_cap'), $args);
}
?>
