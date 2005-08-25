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
	$bb_submenu['users.php'][10] = array(__('Staff'), 'moderate', 'users-staff.php');
	$bb_submenu['users.php'][15] = array(__('Blocked'), 'moderate', 'users-blocked.php');
	$bb_submenu['users.php'][20] = array(__('Bozos'), 'moderate', 'users-bozoes.php');
	$bb_submenu['users.php'][25] = array(__('Flagged'), 'moderate', 'users-flagged.php'); // Need little (#) here and elsewhere

	$bb_submenu['content.php'][5] = array(__('Flagged'), 'moderate', 'content.php');
	$bb_submenu['content.php'][10] = array(__('Posts'), 'moderate', 'content-posts.php');
	$bb_submenu['content.php'][15] = array(__('Topics'), 'moderate', 'content-topics.php');
	$bb_submenu['content.php'][20] = array(__('Forums'), 'moderate', 'content-forums.php');

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
			$r .= "\t<li$class><a href='" . bb_get_admin_tab_link($m) . "'>{$m[0]}</a></li>\n";
		endif;
	endforeach;
	$r .= '</ul>';
	if ( $bb_current_submenu ) :
		$r .= "\n\t<ul id='bb-admin-submenu'>\n";
		foreach ( $bb_submenu[$bb_current_menu[2]] as $m ) :
			if ( bb_current_user_can($m[1]) ) :
				$class = ( $m[2] == $bb_current_submenu[2] ) ? " class='current'" : '';
				$r .= "\t\t<li$class><a href='" . bb_get_admin_tab_link($m) . "'>{$m[0]}</a></li>\n";
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

function get_recently_moderated_posts( $num = 10 ) {
	global $bbdb;
	return $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE post_status <> 0 ORDER BY post_time DESC LIMIT $num"); // post_time != moderation_time;
}

?>
