<?php

/* Users */

function bb_block_current_user() {
	global $bbdb;
	if ( $id = bb_get_current_user_info( 'id' ) )
		bb_update_usermeta( $id, $bbdb->prefix . 'been_blocked', 1 ); // Just for logging.
	bb_die(__("You've been blocked.  If you think a mistake has been made, contact this site's administrator."));
}

function bb_get_user( $user_id, $args = null ) {
	global $wp_users_object;
	$user = $wp_users_object->get_user( $user_id, $args );
	if ( is_wp_error($user) )
		return false;
	return $user;
}

function bb_cache_users( $ids ) {
	global $wp_users_object;
	$wp_users_object->get_user( $ids );
}

function bb_get_user_by_nicename( $nicename ) {
	global $wp_users_object;
	$user = $wp_users_object->get_user( $nicename, array( 'by' => 'nicename' ) );
	if ( is_wp_error($user) )
		return false;
	return $user;
}

function bb_delete_user( $user_id, $reassign = 0 ) {
	global $wp_users_object;

	if ( !$user = bb_get_user( $user_id ) )
		return false;

	if ( $reassign ) {
		if ( !$new_user = bb_get_user( $reassign ) )
			return false;
		$bbdb->update( $bbdb->posts, array( 'poster_id' => $new_user->ID ), array( 'poster_id' => $user->ID ) );
		$bbdb->update( $bbdb->term_relationships, array( 'user_id' => $new_user->ID ), array( 'user_id' => $user->ID ) );
		$bbdb->update( $bbdb->topics, array( 'topic_poster' => $new_user->ID, 'topic_poster_name' => $new_user->user_login), array( 'topic_poster' => $user->ID ) );
		$bbdb->update( $bbdb->topics, array( 'topic_last_poster' => $new_user->ID, 'topic_last_poster_name' => $new_user->user_login ), array( 'topic_last_poster' => $user->ID ) );
		bb_update_topics_replied( $new_user->ID );
	}

	do_action( 'bb_delete_user', $user->ID, $reassign );

	$wp_users_object->delete_user( $user->ID );

	return true;
}

function bb_update_topics_replied( $user_id ) {
	global $bbdb;

	$user_id = (int) $user_id;

	if ( !$user = bb_get_user( $user_id ) )
		return false;

	$topics_replied = (int) $bbdb->get_var( $bbdb->prepare( "SELECT COUNT(DISTINCT topic_id) FROM $bbdb->posts WHERE post_status = '0' AND poster_id = %d", $user_id ) );
	return bb_update_usermeta( $user_id, $bbdb->prefix . 'topics_replied', $topics_replied );
}

function update_user_status( $user_id, $user_status = 0 ) {
	global $wp_users_object;
	$user = bb_get_user( $user_id );
	$user_status = (int) $user_status;
	$wp_users_object->update_user( $user->ID, compact( 'user_status' ) );
}

function bb_trusted_roles() {
	return apply_filters( 'bb_trusted_roles', array('moderator', 'administrator', 'keymaster') );
}

function bb_is_trusted_user( $user ) { // ID, user_login, BB_User, DB user obj
	if ( is_numeric($user) || is_string($user) )
		$user = new WP_User( $user );
	elseif ( is_object($user) && is_a($user, 'WP_User') ); // Intentional
	elseif ( is_object($user) && isset($user->ID) && isset($user->user_login) ) // Make sure it's actually a user object
		$user = new WP_User( $user->ID );
	else
		return;

	if ( !$user->ID )
		return;

	return apply_filters( 'bb_is_trusted_user', (bool) array_intersect(bb_trusted_roles(), $user->roles), $user->ID );
}

function bb_apply_wp_role_map_to_user( $user ) {
	if ( is_numeric($user) || is_string($user) ) {
		$user_id = (integer) $user;
	} elseif ( is_object($user) ) {
		$user_id = $user->ID;
	} else {
		return;
	}
	
	if ( $wordpress_roles_map = bb_get_option('wp_roles_map') ) {
		
		global $bbdb;
		global $wp_roles;
		global $bb;
		
		$bbpress_roles_map = array();
		foreach ( $wp_roles->get_names() as $_bbpress_role => $_bbpress_rolename ) {
			$bbpress_roles_map[$_bbpress_role] = 'subscriber';
		}
		unset( $_bbpress_role, $_bbpress_rolename );
		$bbpress_roles_map = array_merge( $bbpress_roles_map, array_flip( $wordpress_roles_map ) );
		unset( $bbpress_roles_map['inactive'], $bbpress_roles_map['blocked'] );
		
		$wordpress_userlevel_map = array(
			'administrator' => 10,
			'editor' => 7,
			'author' => 2,
			'contributor' => 1,
			'subscriber' => 0
		);
		
		$bbpress_roles = bb_get_usermeta($user_id, $bbdb->prefix . 'capabilities');
		
		$wordpress_table_prefix = bb_get_option('wp_table_prefix');
		if ( $wordpress_mu_primary_blog_id = bb_get_option('wordpress_mu_primary_blog_id') ) {
			$wordpress_table_prefix .= $wordpress_mu_primary_blog_id . '_';
		}
		
		$wordpress_roles = bb_get_usermeta($user_id, $wordpress_table_prefix . 'capabilities');
		
		if (!$bbpress_roles && is_array($wordpress_roles)) {
			$bbpress_roles_new = array();
			
			foreach ($wordpress_roles as $wordpress_role => $wordpress_role_value) {
				if ($wordpress_roles_map[strtolower($wordpress_role)] && $wordpress_role_value) {
					$bbpress_roles_new[$wordpress_roles_map[strtolower($wordpress_role)]] = true;
				}
			}
			
			if (count($bbpress_roles_new)) {
				bb_update_usermeta( $user_id, $bbdb->prefix . 'capabilities', $bbpress_roles_new );
			}
			
		} elseif (!$wordpress_roles && is_array($bbpress_roles)) {
			$wordpress_roles_new = array();
			
			foreach ($bbpress_roles as $bbpress_role => $bbpress_role_value) {
				if ($bbpress_roles_map[strtolower($bbpress_role)] && $bbpress_role_value) {
					$wordpress_roles_new[$bbpress_roles_map[strtolower($bbpress_role)]] = true;
					$wordpress_userlevels_new[] = $wordpress_userlevel_map[$bbpress_roles_map[strtolower($bbpress_role)]];
				}
			}
			
			if (count($wordpress_roles_new)) {
				bb_update_usermeta( $user_id, $wordpress_table_prefix . 'capabilities', $wordpress_roles_new );
				bb_update_usermeta( $user_id, $wordpress_table_prefix . 'user_level', max($wordpress_userlevels_new) );
			}
		}
	}
}

function bb_apply_wp_role_map_to_orphans() {
	if ( $wp_table_prefix = bb_get_option( 'wp_table_prefix' ) ) {
		
		if ( $wordpress_mu_primary_blog_id = bb_get_option('wordpress_mu_primary_blog_id') ) {
			$wp_table_prefix .= $wordpress_mu_primary_blog_id . '_';
		}
		
		$role_query = <<<EOQ
			SELECT
				ID
			FROM
				`%1\$s`
			LEFT JOIN `%2\$s` AS bbrole
				ON ID = bbrole.user_id
				AND bbrole.meta_key = '%3\$scapabilities'
			LEFT JOIN `%2\$s` AS wprole
				ON ID = wprole.user_id
				AND wprole.meta_key = '%4\$scapabilities'
			WHERE
				bbrole.meta_key IS NULL OR
				bbrole.meta_value IS NULL OR
				wprole.meta_key IS NULL OR
				wprole.meta_value IS NULL
			ORDER BY
				ID
EOQ;
		global $bbdb;
		
		$role_query = $bbdb->prepare($role_query, $bbdb->users, $bbdb->usermeta, $bbdb->prefix, $wp_table_prefix);
		
		if ( $user_ids = $bbdb->get_col($role_query) ) {
			foreach ( $user_ids as $user_id ) {
				bb_apply_wp_role_map_to_user( $user_id );
			}
		}
		
	}
}



/* Favorites */

function get_user_favorites( $user_id, $topics = false ) {
	$user = bb_get_user( $user_id );
	if ( !empty($user->favorites) ) {
		if ( $topics )
			$query = new BB_Query( 'topic', array('favorites' => $user_id, 'append_meta' => 0), 'get_user_favorites' );
		else
			$query = new BB_Query( 'post', array('favorites' => $user_id), 'get_user_favorites' );
		return $query->results;
	}
}

function is_user_favorite( $user_id = 0, $topic_id = 0 ) {
	if ( $user_id )
		$user = bb_get_user( $user_id );
	else
	 	global $user;
	if ( $topic_id )
		$topic = get_topic( $topic_id );
	else
		global $topic;
	if ( !$user || !$topic )
		return;

	if ( isset($user->favorites) )
	        return in_array($topic->topic_id, explode(',', $user->favorites));
	return false;
}

function bb_add_user_favorite( $user_id, $topic_id ) {
	global $bbdb;
	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;
	$user = bb_get_user( $user_id );
	$topic = get_topic( $topic_id );
	if ( !$user || !$topic )
		return false;

	$fav = $user->favorites ? explode(',', $user->favorites) : array();
	if ( ! in_array( $topic_id, $fav ) ) {
		$fav[] = $topic_id;
		$fav = implode(',', $fav);
		bb_update_usermeta( $user->ID, $bbdb->prefix . 'favorites', $fav);
	}
	do_action('bb_add_user_favorite', $user_id, $topic_id);
	return true;
}

function bb_remove_user_favorite( $user_id, $topic_id ) {
	global $bbdb;
	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;
	$user = bb_get_user( $user_id );
	if ( !$user )
		return false;

	$fav = explode(',', $user->favorites);
	if ( is_int( $pos = array_search($topic_id, $fav) ) ) {
		array_splice($fav, $pos, 1);
		$fav = implode(',', $fav);
		bb_update_usermeta( $user->ID, $bbdb->prefix . 'favorites', $fav);
	}
	do_action('bb_remove_user_favorite', $user_id, $topic_id);
	return true;
}
