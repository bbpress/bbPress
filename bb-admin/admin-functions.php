<?php

function bb_get_admin_header() {
	do_action('bb_admin-header.php', '');
	include('admin-header.php');
	do_action('bb_get_admin_header', '');
}

function bb_get_admin_footer() {
	do_action('bb_admin-footer.php', '');
	include('admin-footer.php');
}

function bb_admin_menu_generator() {
	global $bb_menu, $bb_submenu;
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

	do_action('bb_admin_menu_generator','');
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
	$posts = (array) get_deleted_posts( 1, $num, -1 ); // post_time != moderation_time;
	$topics = (array) $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_status <> 0 ORDER BY topic_time DESC LIMIT $num"); // topic_time == topic_start_time != moderation_time;
	$objects = array();
	foreach ( array_keys($posts) as $key )
		$objects[strtotime($posts[$key]->post_time . ' +0000')] = array('type' => 'post', 'data' => $posts[$key]);
	foreach ( array_keys($topics) as $key )
		$objects[strtotime($topics[$key]->topic_time . ' +0000')] = array('type' => 'topic', 'data' => $topics[$key]);
	krsort($objects);
	return array_slice($objects, 0, $num);
}

function get_ids_by_role( $role = 'moderator', $sort = 0 ) {
	global $bbdb, $bb_table_prefix;
	$sort = $sort ? 'DESC' : 'ASC';
	$key = $bb_table_prefix . 'capabilities';
	if ( $ids = (array) $bbdb->get_col("SELECT user_id FROM $bbdb->usermeta WHERE meta_key = '$key' AND meta_value LIKE '%$role%' ORDER BY user_id $sort") )
		bb_cache_users( $ids );
	return $ids;
}

function get_deleted_topics_count() {
	global $bbdb;
	return $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->topics WHERE topic_status <> 0");
}

function get_deleted_posts( $page = 1, $limit = false, $status = 1, $topic_status = 0 ) {
	global $bbdb;
	$page = (int) $page;
	$status = (int) $status;
	if ( !$limit )
		$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	if ( false === $topic_status )
		$where = '';
	else {
		$topic_status = (int) $topic_status;
		$where = "topic_status = '$topic_status' AND";
	}
	$status = ( 0 < $status ) ? "= '$status'" : "> '0'";
	if ( $page )
		return $bbdb->get_results("SELECT $bbdb->posts.* FROM $bbdb->posts LEFT JOIN $bbdb->topics USING (topic_id) WHERE $where post_status $status ORDER BY post_time DESC LIMIT $limit");
	else	return $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts LEFT JOIN $bbdb->topics USING (topic_id) WHERE $where post_status $status");
}

function bb_recount_list() {
	global $recount_list;
	$recount_list = array();
	$recount_list[5] = array('topic-posts', __('Count posts of every topic'));
	$recount_list[10] = array('topic-deleted-posts', __('Count deleted posts on every topic'));
	$recount_list[15] = array('forums', __('Count topics and posts in every forum (relies on the above)'));
	$recount_list[20] = array('topics-replied', __('Count topics to which each user has replied'));
	$recount_list[25] = array('topic-tag-count', __('Count tags for every topic'));
	$recount_list[30] = array('tags-tag-count', __('Count topics for every tag'));
	$recount_list[35] = array('zap-tags', __('DELETE tags with no topics.  Only functions if the above checked'));
	do_action('bb_recount_list');
	ksort($recount_list);
	return $recount_list;
}

function bb_admin_list_posts() {
	global $bb_posts, $bb_post;
	if ( $bb_posts ) : foreach ( $bb_posts as $bb_post ) : ?>
	<li<?php alt_class('post'); ?>>
		<div class="threadauthor">
			<p><strong><?php post_author_link(); ?></strong><br />
				<small><?php post_author_type(); ?></small></p>
		</div>
		<div class="threadpost">
			<div class="post"><?php post_text(); ?></div>
			<div class="poststuff">
				<?php printf(__('Posted: %1$s in <a href="%2$s">%3$s</a>'), bb_get_post_time(), get_topic_link( $bb_post->topic_id ), get_topic_title( $bb_post->topic_id ));?> IP: <?php post_ip_link(); ?> <?php post_edit_link(); ?> <?php post_delete_link();?></div>
			</div>
	</li><?php endforeach; endif;
}

?>
