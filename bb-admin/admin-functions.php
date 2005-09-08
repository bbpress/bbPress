<?php

function bb_get_admin_header() {
	bb_do_action('bb_admin-header.php', '');
	include('admin-header.php');
	bb_do_action('bb_get_admin_header', '');
}

function bb_get_admin_footer() {
	bb_do_action('bb_admin-footer.php', '');
	include('admin-footer.php');
}

function bb_admin_menu_generator() {
	global $bb_menu, $bb_submenu, $bb_admin_hooks;
	$bb_menu = array();
	$bb_menu[0] = array(__('Dashboard'), 'moderate', 'index.php');
	$bb_menu[5] = array(__('Users'), 'moderate', 'users.php');
	$bb_menu[10] = array(__('Content'), 'moderate', 'content.php');
	$bb_menu[15] = array(__('Site Management'), 'use_keys', 'site.php');

	$bb_submenu = array();
	$bb_submenu['users.php'][5] = array(__('Find'), 'moderate', 'users.php');
	$bb_submenu['users.php'][10] = array(__('Moderators'), 'moderate', 'users-moderators.php');
	$bb_submenu['users.php'][15] = array(__('Blocked'), 'moderate', 'users-blocked.php');

	$bb_submenu['content.php'][5] = array(__('Topics'), 'moderate', 'content.php');
	$bb_submenu['content.php'][10] = array(__('Posts'), 'moderate', 'content-posts.php');
	$bb_submenu['content.php'][15] = array(__('Forums'), 'moderate', 'content-forums.php');

	$bb_submenu['site.php'][5] = array(__('Recount'), 'recount', 'site.php');

	bb_do_action('bb_admin_menu_generator','');
	ksort($bb_menu);
}

function bb_get_current_admin_menu() {
	global $bb_menu, $bb_submenu, $bb_admin_page, $bb_current_menu, $bb_current_submenu;
	foreach ( $bb_submenu as $m => $b ) {
		foreach ( $b as $s ) {
			if ( $s[2] == $bb_admin_page ) {
				$bb_current_submenu = $s;
				$bb_current_menu = $m;
				break;
			}
		}
	}
	if ( !isset($bb_current_menu) ) :
		$bb_current_menu = $bb_menu[0];	// Dashboard is currently the only supported top with no subs
		$bb_current_submenu = false;
	else :
		foreach ( $bb_menu as $m ) {
			if ( $m[2] == $bb_current_menu ) {
				$bb_current_menu = $m;
				break;
			}
		}
	endif;
}

function bb_admin_title() {
	global $bb_current_menu, $bb_current_submenu;
	$title = 'bbPress &#8212; ' . bb_get_option('name') . ' &#8250; ' . $bb_current_menu[0] . ( $bb_current_submenu ? '&raquo; ' . $bb_current_submenu[0] : '' );
	echo $title;
}

function bb_admin_menu() {
	global $bb_menu, $bb_submenu, $bb_current_menu, $bb_current_submenu;
	$r = "<ul id='bb-admin-menu'>\n";
	foreach ( $bb_menu as $m ) :
		if ( bb_current_user_can($m[1]) ) :
			$class = ( $m[2] == $bb_current_menu[2] ) ? " class='current'" : '';
			$r .= "\t<li$class><a href='" . bb_get_option('path') . 'bb-admin/' . bb_get_admin_tab_link($m) . "'>{$m[0]}</a></li>\n";
		endif;
	endforeach;
	$r .= '</ul>';
	if ( $bb_current_submenu ) :
		$r .= "\n\t<ul id='bb-admin-submenu'>\n";
		foreach ( $bb_submenu[$bb_current_menu[2]] as $m ) :
			if ( bb_current_user_can($m[1]) ) :
				$class = ( $m[2] == $bb_current_submenu[2] ) ? " class='current'" : '';
				$r .= "\t\t<li$class><a href='" . bb_get_option('path') . 'bb-admin/' . bb_get_admin_tab_link($m) . "'>{$m[0]}</a></li>\n";
			endif;
		endforeach;
		$r .= "\t</ul>\n";
	endif;
	echo $r;
}

function bb_get_admin_tab_link( $m ) {
	if ( strpos($m[2], '.php') !== false )
		return $m[2];
	else
		return 'admin-base.php?plugin=' . $m[2];
}

function get_recently_moderated_objects( $num = 5 ) {
	global $bbdb;
	$posts = get_deleted_posts( 1, $num ); // post_time != moderation_time;
	$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_status <> 0 ORDER BY topic_time DESC LIMIT $num"); // topic_time == topic_start_time != moderation_time;
	$objects = array();
	foreach ( array_keys($posts) as $key )
		$objects[strtotime($posts[$key]->post_time . ' +0000')] = array('type' => 'post', 'data' => $posts[$key]);
	foreach ( array_keys($topics) as $key )
		$objects[strtotime($topics[$key]->topic_time . ' +0000')] = array('type' => 'topic', 'data' => $topics[$key]);
	krsort($objects);
	return array_slice($objects, 0, 5);
}

function get_ids_by_role( $role = 'moderator' ) {
	global $bbdb, $bb_table_prefix;
	$key = $bb_table_prefix . 'capabilities';
	if ( $ids = $bbdb->get_col("SELECT user_id FROM $bbdb->usermeta WHERE meta_key = '$key' AND meta_value LIKE '%$role%'") )
		bb_cache_users( $ids );
	return $ids;
}

function get_deleted_topics_count() {
	global $bbdb;
	return $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->topics WHERE topic_status <> 0");
}

function get_deleted_posts( $page = 1, $limit = false ) {
	global $bbdb;
	$page = (int) $page;
	if ( !$limit )
		$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	if ( $page )
		return $bbdb->get_results("SELECT $bbdb->posts.* FROM $bbdb->posts LEFT JOIN $bbdb->topics USING (topic_id) WHERE topic_status = 0 AND post_status = 1 ORDER BY post_time DESC LIMIT $limit");
	else	return $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts LEFT JOIN $bbdb->topics USING (topic_id) WHERE topic_status = 0 AND post_status = 1");
}

?>
