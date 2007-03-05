<?php

function bb_get_admin_header() {
	do_action('bb_admin-header.php');
	include('admin-header.php');
	do_action('bb_get_admin_header');
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
	$bb_menu[13] = array(__('Presentation'), 'use_keys', 'themes.php');
	$bb_menu[15] = array(__('Site Management'), 'use_keys', 'plugins.php');

	$bb_submenu = array();
	$bb_submenu['users.php'][5] = array(__('Find'), 'moderate', 'users.php');
	$bb_submenu['users.php'][10] = array(__('Moderators'), 'moderate', 'users-moderators.php');
	$bb_submenu['users.php'][15] = array(__('Blocked'), 'moderate', 'users-blocked.php');

	$bb_submenu['content.php'][5] = array(__('Topics'), 'moderate', 'content.php');
	$bb_submenu['content.php'][10] = array(__('Posts'), 'moderate', 'content-posts.php');
	$bb_submenu['content.php'][15] = array(__('Forums'), 'moderate', 'content-forums.php');

	$bb_submenu['themes.php'][5] = array(__('Themes'), 'use_keys', 'themes.php');

	$bb_submenu['plugins.php'][5] = array(__('Plugins'), 'use_keys', 'plugins.php');
	$bb_submenu['plugins.php'][10] = array(__('Recount'), 'recount', 'site.php');

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
	if ( $bb_current_submenu && !bb_current_user_can( $bb_current_submenu[1] ) || !bb_current_user_can( $bb_current_menu[1] ) ) {
		wp_redirect( bb_get_option( 'uri' ) );
		exit();
	}
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

function bb_get_admin_tab_link( $tab ) {
	if ( is_array($tab) )
		$tab = $tab[2];
	if ( strpos($tab, '.php') !== false )
		return $tab;
	else
		return 'admin-base.php?plugin=' . $tab;
}

function get_recently_moderated_objects( $num = 5 ) {
	global $bbdb;
	$posts = (array) get_deleted_posts( 1, $num, -1 ); // post_time != moderation_time;
	$topics = (array) $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_status <> 0 ORDER BY topic_time DESC LIMIT $num"); // topic_time == topic_start_time != moderation_time;
	$objects = array();
	foreach ( array_keys($posts) as $key )
		$objects[bb_gmtstrtotime($posts[$key]->post_time)] = array('type' => 'post', 'data' => $posts[$key]);
	foreach ( array_keys($topics) as $key )
		$objects[bb_gmtstrtotime($topics[$key]->topic_time)] = array('type' => 'topic', 'data' => $topics[$key]);
	krsort($objects);
	return array_slice($objects, 0, $num);
}

function get_ids_by_role( $role = 'moderator', $sort = 0, $limit_str = '' ) {
	global $bbdb, $bb_table_prefix, $bb_last_countable_query;
	$sort = $sort ? 'DESC' : 'ASC';
	$key = $bb_table_prefix . 'capabilities';
	if ( is_array($role) )
		$and_where = "( meta_value LIKE '%" . join("%' OR meta_value LIKE '%", $role) . "%' )";
	else
		$and_where = "meta_value LIKE '%$role%'";
	$bb_last_countable_query = "SELECT user_id FROM $bbdb->usermeta WHERE meta_key = '$key' AND $and_where ORDER BY user_id $sort" . $limit_str;
	if ( $ids = (array) $bbdb->get_col($bb_last_countable_query) )
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
	return $bbdb->get_results("SELECT $bbdb->posts.* FROM $bbdb->posts LEFT JOIN $bbdb->topics USING (topic_id) WHERE $where post_status $status ORDER BY post_time DESC LIMIT $limit");
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

function bb_user_row( $user_id, $role = '', $email = false ) {
	$user = bb_get_user( $user_id );
	$r  = "\t<tr id='user-$user->ID'" . get_alt_class("user-$role") . ">\n";
	$r .= "\t\t<td>$user->ID</td>\n";
	$r .= "\t\t<td><a href='" . get_user_profile_link( $user->ID ) . "'>" . get_user_name( $user->ID ) . "</a></td>\n";
	if ( $email ) {
		$email = bb_get_user_email( $user->ID );
		$r .= "\t\t<td><a href='mailto:$email'>$email</a></td>\n";
	}
	$r .= "\t\t<td>$user->user_registered</td>\n";
	$r .= "\t\t<td><a href='" . get_profile_tab_link( $user->ID, 'edit' ) . "'>" . __('Edit') . "</a></td>\n\t</tr>";
	return $r;
}

// BB_User_Search class
// by Mark Jaquith

class BB_User_Search {
	var $results;
	var $search_term;
	var $page;
	var $raw_page;
	var $users_per_page = 50;
	var $first_user;
	var $last_user;
	var $query_limit;
	var $total_users_for_query = 0;
	var $search_errors;

	function BB_User_Search ($search_term = false, $page = 1 ) { // constructor
		$this->search_term = $search_term ? stripslashes($search_term) : false;
		$this->raw_page = ( '' == $page ) ? false : (int) $page;
		$page = (int) $page;
		$this->page = $page < 2 ? 1 : $page;

		$this->prepare_query();
		$this->query();
		$this->prepare_vars_for_template_usage();
		$this->do_paging();
	}

	function prepare_query() {
		global $bbdb;
		$this->first_user = ($this->page - 1) * $this->users_per_page;
	}

	function query() {
		global $bbdb;
		$users = bb_user_search( array(
				'query' => $this->search_term,
				'user_email' => true,
				'users_per_page' => $this->users_per_page,
				'page' => $this->page
		) );

		if ( is_wp_error($users) )
			$this->search_errors = $users;
		else if ( $users )
			foreach ( (array) $users as $user )
				$this->results[] = $user->ID;

		if ( $this->results )
			$this->total_users_for_query = bb_count_last_query();
		elseif ( !is_wp_error($this->search_errors) )
			$this->search_errors = new WP_Error('no_matching_users_found', __('No matching users were found!'));

		if ( is_wp_error( $this->search_errors ) )
			bb_admin_notice( $this->search_errors );
	}

	function prepare_vars_for_template_usage() {
		$this->search_term = stripslashes($this->search_term); // done with DB, from now on we want slashes gone
	}

	function do_paging() {
		global $bb_current_submenu;
		if ( $this->total_users_for_query > $this->users_per_page ) { // have to page the results
		$pagenow = bb_get_admin_tab_link($bb_current_submenu);
			$this->paging_text = paginate_links( array(
				'total' => ceil($this->total_users_for_query / $this->users_per_page),
				'current' => $this->page,
				'prev_text' => '&laquo; Previous Page',
				'next_text' => 'Next Page &raquo;',
				'base' => $pagenow . ( false === strpos($pagenow, '?') ? '?%_%' : '&amp;%_%' ),
				'format' => 'userspage=%#%',
				'add_args' => array( 'usersearch' => urlencode($this->search_term) )
			) );
		}
	}

	function get_results() {
		return (array) $this->results;
	}

	function page_links() {
		echo $this->paging_text;
	}

	function results_are_paged() {
		if ( $this->paging_text )
			return true;
		return false;
	}

	function is_search() {
		if ( $this->search_term )
			return true;
		return false;
	}

	function display( $show_search = true, $show_email = false ) {
		global $bb_roles;
		$r = '';
		// Make the user objects
		foreach ( $this->get_results() as $user_id ) {
			$tmp_user = new BB_User($user_id);
			$roles = $tmp_user->roles;
			$role = array_shift($roles);
			$roleclasses[$role][$tmp_user->data->user_login] = $tmp_user;
		}

		if ( isset($this->title) )
			$title = $this->title;
		elseif ( $this->is_search() )
			$title = sprintf(__('Users Matching "%s" by Role'), wp_specialchars( $this->search_term ));
		else
			$title = __('User List by Role');
		$r .= "<h2>$title</h2>\n";

		if ( $show_search ) {
			$r .= "<form action='' method='get' name='search' id='search'>\n\t<p>";
			$r .= "\t\t<input type='text' name='usersearch' id='usersearch' value='" . wp_specialchars( $this->search_term, 1) . "' />\n";
			$r .= "\t\t<input type='submit' value='" . __('Search for users &raquo;') . "' />\n\t</p>\n";
			$r .= "</form>\n\n";
		}

		if ( $this->get_results() ) {
			$colspan = $show_email ? 5 : 4;
			if ( $this->is_search() )
				$r .= "<p>\n\t<a href='users.php'>" . __('&laquo; Back to All Users') . "</a>\n</p>\n\n";

			$r .= '<h3>' . sprintf(__('%1$s &#8211; %2$s of %3$s shown below'), $this->first_user + 1, min($this->first_user + $this->users_per_page, $this->total_users_for_query), $this->total_users_for_query) . "</h3>\n";

			if ( $this->results_are_paged() )
				$r .= "<div class='user-paging-text'>\n" . $this->paging_text . "</div>\n\n";

			$r .= "<table class='widefat'>\n";
			foreach($roleclasses as $role => $roleclass) {
				ksort($roleclass);
				$r .= "\t<tr>\n";
				if ( !empty($role) )
					$r .= "\t\t<th colspan='$colspan'><h3>{$bb_roles->role_names[$role]}</h3></th>\n";
				else
					$r .= "\t\t<th colspan='$colspan'><h3><em>" . __('Users with no role in these forums') . "</h3></th>\n";
				$r .= "\t</tr>\n";
				$r .= "\t<tr class='thead'>\n";
				$r .= "\t\t<th>" . __('ID') . "</th>\n";
				$r .= "\t\t<th>" . __('Username') . "</th>\n";
				if ( $show_email )
					$r .= "\t\t<th>" . __('Email') . "</th>\n";
				$r .= "\t\t<th>" . __('Registered Since') . "</th>\n";
				$r .= "\t\t<th>" . __('Actions') . "</th>\n";
				$r .= "\t</tr>\n\n";

				$r .= "<tbody id='role-$role'>\n";
				foreach ( (array) $roleclass as $user_object )
				$r .= bb_user_row($user_object->ID, $role, $show_email);
				$r .= "</tbody>\n";
			}
			$r .= "</table>\n\n";

		 	if ( $this->results_are_paged() )
				$r .= "<div class='user-paging-text'>\n" . $this->paging_text . "</div>\n\n";
		}
		echo $r;
	}

}

class BB_Users_By_Role extends BB_User_Search {
	var $role = '';
	var $title = '';

	function BB_Users_By_Role($role = '', $page = '') { // constructor
		$this->role = $role ? $role : 'member';
		$this->raw_page = ( '' == $page ) ? false : (int) $page;
		$this->page = (int) ( '' == $page ) ? 1 : $page;

		$this->prepare_query();
		$this->query();
		$this->do_paging();
	}

	function prepare_query() {
		$this->first_user = ($this->page - 1) * $this->users_per_page;
		$this->query_limit = ' LIMIT ' . $this->first_user . ',' . $this->users_per_page;
	}

	function query() {
		global $bbdb;
		$this->results = get_ids_by_role( $this->role, 0, $this->query_limit );

		if ( $this->results )
			$this->total_users_for_query = bb_count_last_query();
		else
			$this->search_errors = new WP_Error('no_matching_users_found', __('No matching users were found!'));

		if ( is_wp_error( $this->search_errors ) )
			bb_admin_notice( $this->search_errors );
	}

}

function bb_get_plugins( $plugin_dir = false ) {
	$plugins = array();
	if ( !$plugin_dir )
		$plugin_dir = BBPLUGINDIR;

	$plugin_dir = rtrim($plugin_dir, '/\\');

	if ( 0 < func_num_args() && dirname($plugin_dir) !== rtrim(BBPLUGINDIR, '/\\') ) // only go one level deep;
		return $plugins;
	else
		$plugin_dir = dir($plugin_dir);

	while ( false !== $file = $plugin_dir->read() ) {
		if ( '.' == $file{0} )
			continue;
		if ( is_dir($plugin_dir->path . "/$file") )
			$plugins = array_merge($plugins, bb_get_plugins( $plugin_dir->path . "/$file" ));
		if ( $data = bb_get_plugin_data( $plugin_dir->path . "/$file" ) )
			$plugins[ltrim(substr($plugin_dir->path, strlen(BBPLUGINDIR)) . "/$file", '/\\')] = $data;
	}
	return $plugins;
}

function bb_get_plugin_data($plugin_file) {
	$plugin_data = implode('', file($plugin_file));
	if ( !preg_match("|Plugin Name:(.*)|i", $plugin_data, $plugin_name) )
		return false;
	preg_match("|Plugin URI:(.*)|i", $plugin_data, $plugin_uri);
	preg_match("|Description:(.*)|i", $plugin_data, $description);
	preg_match("|Author:(.*)|i", $plugin_data, $author_name);
	preg_match("|Author URI:(.*)|i", $plugin_data, $author_uri);
	if ( preg_match("|Requires at least:(.*)|i", $plugin_data, $requires) )
		$requires = trim($requires[1]);
	else
		$requires = '';
	if ( preg_match("|Tested up to:(.*)|i", $plugin_data, $tested) )
		$tested = trim($tested[1]);
	else
		$tested = '';
	if ( preg_match("|Version:(.*)|i", $plugin_data, $version) )
		$version = trim($version[1]);
	else
		$version = '';

	$plugin_name = trim($plugin_name[1]);
	$plugin_uri = trim($plugin_uri[1]);
	$description = trim($description[1]);
	$author_name = trim($author_name[1]);
	$author_uri = trim($author_uri[1]);

	$r = array(
		'name' => $plugin_name,
		'uri' => $plugin_uri,
		'description' => $description,
		'author' => $author_name,
		'author_uri' => $author_uri,
		'requires' => $requires,
		'tested' => $tested,
		'version' => $version
	);

	$r['plugin_link'] = ( $plugin_uri ) ?
		"<a href='$plugin_uri' title='" . __('Visit plugin homepage') . "'>$plugin_name</a>" :
		$plugin_name;
	$r['author_link'] = ( $author_name && $author_uri ) ?
		"<a href='$author_uri' title='" . __('Visit author homepage') . "'>$author_name</a>" :
		$author_name;

	return $r;
}

function bb_admin_notice( $message, $class = false ) {
	if ( is_string($message) ) {
		$message = "<p>$message</p>";
		$class = $class ? $class : 'updated';
	} elseif ( is_wp_error($message) ) {
		$errors = $message->get_error_messages();
		switch ( count($errors) ) :
		case 0 :
			return false;
			break;
		case 1 :
			$message = "<p>{$errors[0]}</p>";
			break;
		default :
			$message = "<ul>\n\t<li>" . join( "</li>\n\t<li>", $errors ) . "</li>\n</ul>";
			break;
		endswitch;
		$class = $class ? $class : 'error';
	} else {
		return false;
	}

	$message = "<div class='$class'>$message</div>";
	$message = str_replace("'", "\'", $message);
	$lambda = create_function( '', "echo '$message';" );
	add_action( 'bb_admin_notices', $lambda );
	return $lambda;
}

function bb_get_theme_data( $theme_file ) {
	$theme_data = implode( '', file( $theme_file ) );
	$theme_data = str_replace ( '\r', '\n', $theme_data ); 
	preg_match( '|Theme Name:(.*)|i', $theme_data, $theme_name );
	preg_match( '|Theme URI:(.*)|i', $theme_data, $theme_uri );
	preg_match( '|Description:(.*)|i', $theme_data, $description );
	preg_match( '|Author:(.*)|i', $theme_data, $author_name );
	preg_match( '|Author URI:(.*)|i', $theme_data, $author_uri );
	preg_match( '|Ported By:(.*)|i', $theme_data, $porter_name );
	preg_match( '|Porter URI:(.*)|i', $theme_data, $porter_uri );
//	preg_match( '|Template:(.*)|i', $theme_data, $template );
	if ( preg_match( '|Version:(.*)|i', $theme_data, $version ) )
		$version = trim( $version[1] );
	else
		$version ='';
	if ( preg_match('|Status:(.*)|i', $theme_data, $status) )
		$status = trim($status[1]);
	else
		$status = 'publish';

	$description = wp_specialchars( trim($description[1]) );

	$name = $theme_name[1];
	$name = trim( $name );
	$theme = $name;

	if ( '' == $author_uri[1] ) {
		$author = trim( $author_name[1] );
	} else {
		$author = '<a href="' . trim( $author_uri[1] ) . '" title="' . __('Visit author homepage') . '">' . trim( $author_name[1] ) . '</a>';
	}

	if ( '' == $porter_uri[1] ) {
		$porter = trim( $porter_name[1] );
	} else {
		$porter = '<a href="' . trim( $porter_uri[1] ) . '" title="' . __('Visit porter homepage') . '">' . trim( $porter_name[1] ) . '</a>';
	}

	return array(
		'Name' => $name,
		'Title' => $theme,
		'Description' => $description,
		'Author' => $author,
		'Porter' => $porter,
		'Version' => $version,
//		'Template' => $template[1],
		'Status' => $status
	);
}

function bb_forum_row( $forum_id = 0, $echo = true, $close = true, $class = 'forum' ) {
	global $forum, $forums_count;
	if ( $forum_id )
		$_forum = get_forum( $forum_id );
	else
		$_forum =& $forum;

	if ( !$_forum )
		return;

	$r  = "\t<li id='forum-$_forum->forum_id'" . get_alt_class( 'forum', "$class clear list-block" ) . ">\n";
	$r .= "\t\t<div class='list-block posrel'>";
	$r .= "\t\t\t<div class='alignright'>\n";
	if ( bb_current_user_can( 'manage_forums' ) )
		$r .= "\t\t\t\t<a class='edit' href='" . attribute_escape( bb_get_option('uri') . "bb-admin/content-forums.php?action=edit&id=$_forum->forum_id" ) . "'>" . __('Edit') . "</a>\n";
	if ( bb_current_user_can( 'delete_forum', $_forum->forum_id ) && 1 < $forums_count )
		$r .= "\t\t\t\t<a class='delete' href='" . attribute_escape( bb_get_option('uri') . "bb-admin/content-forums.php?action=delete&id=$_forum->forum_id" ) . "'>" . __('Delete') . "</a>\n";
	$r .= "\t\t\t</div>\n";
	$r .= "\t\t\t" . get_forum_name( $_forum->forum_id ) . ' &#8212; ' . get_forum_description( $_forum->forum_id ) . "\n\t\t</div>\n";
	if ( $close )
		$r .= "\t</li>\n";

	if ( $echo )
		echo $r;
	return $r;
}

function bb_forum_form( $forum_id = 0 ) {
	$forum_id = (int) $forum_id;
	if ( $forum_id && !$forum = get_forum( $forum_id ) )
		return;
	$action = $forum_id ? 'update' : 'add';
?>
<form method="post" id="<?php echo $action; ?>-forum" action="<?php bb_option('uri'); ?>bb-admin/bb-forum.php">
	<fieldset>
	<table><col /><col style="width: 80%" />
		<tr><th scope="row"><?php _e('Forum Name:'); ?></th>
			<td><input type="text" name="forum_name" id="forum-name" value="<?php if ( $forum_id ) echo attribute_escape( get_forum_name( $forum_id ) ); ?>" tabindex="10" class="widefat" /></td>
		</tr>
		<tr><th scope="row"><?php _e('Forum Description:'); ?></th>
			<td><input type="text" name="forum_desc" id="forum-desc" value="<?php if ( $forum_id ) echo attribute_escape( get_forum_description( $forum_id ) ); ?>" tabindex="11" class="widefat" /></td>
		</tr>
		<tr><th scope="row"><?php _e('Forum Parent:'); ?></th>
			<td><?php bb_forum_dropdown( array('callback' => 'strcmp', 'callback_args' => array($forum_id), 'id' => 'forum_parent', 'none' => true, 'selected' => $forum_id ? get_forum_parent( $forum_id ) : 0) ); ?></td>
		</tr>
		<tr><th scope="row"><?php _e('Position:'); ?></th>
			<td><input type="text" name="forum_order" id="forum-order" value="<?php if ( $forum_id ) echo get_forum_position( $forum_id ); ?>" tabindex="12" maxlength="10" class="widefat" /></td>
		</tr>
	</table>
	<p class="submit">
<?php if ( $forum_id ) : ?>
		<input type="hidden" name="forum_id" value="<?php echo $forum_id; ?>" />
<?php endif; ?>
		<?php bb_nonce_field( "$action-forum" ); ?>

		<input type="hidden" name="action" value="<?php echo $action; ?>" />
		<input name="Submit" type="submit" value="<?php if ( $forum_id ) _e('Update Forum &#187;'); else _e('Add Forum &#187;'); ?>" tabindex="13" />
	</p>
	</fieldset> 
</form>
<?php
}

?>
