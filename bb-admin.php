<?php

// Attach the bbPress admin init action to the WordPress admin init action.
add_action( 'admin_init',                                      array( 'BBP_Admin', 'init' ) );

// User profile edit/display actions
add_action( 'edit_user_profile',                               array( 'BBP_Admin', 'user_profile_forums' ) );
add_action( 'show_user_profile',                               array( 'BBP_Admin', 'user_profile_forums' ) );

// User profile save actions
add_action( 'personal_options_update',                         array( 'BBP_Admin', 'user_profile_update' ) );
add_action( 'edit_user_profile_update',                        array( 'BBP_Admin', 'user_profile_update' ) );

// Add some general styling to the admin area
add_action( 'admin_head',                                      array( 'BBP_Admin', 'admin_head' ) );

// Topic metabox actions
add_action( 'admin_menu',                                      array( 'BBP_Admin', 'topic_parent_metabox' ) );
add_action( 'save_post',                                       array( 'BBP_Admin', 'topic_parent_metabox_save' ) );

// Topic reply metabox actions
add_action( 'admin_menu',                                      array( 'BBP_Admin', 'topic_reply_parent_metabox' ) );
add_action( 'save_post',                                       array( 'BBP_Admin', 'topic_reply_parent_metabox_save' ) );

add_filter( 'manage_'.BBP_FORUM_POST_TYPE_ID.'_posts_columns', array( 'BBP_Admin', 'filter_manage_forums_columns' ) );
add_filter( 'page_row_actions',                                array( 'BBP_Admin', 'filter_forums_page_row_actions' ), 10, 2 );

// Column handling.
add_action( 'manage_pages_custom_column',                      array( 'BBP_Admin', 'action_manage_forums_custom_column' ), 10, 2 );

/**
 * BBP_Admin
 *
 * Loads plugin admin area
 *
 * @package bbPress
 * @subpackage Admin
 * @since bbPress (1.2-r2464)
 */
class BBP_Admin {

	/**
	 * init()
	 *
	 * bbPress's dedicated admin init action
	 *
	 * @uses do_action
	 */
	function init () {
		do_action ( 'bbp_admin_init' );
	}

	/**
	 * topic_parent_metabox ()
	 *
	 * Add the topic parent metabox
	 *
	 * @uses add_meta_box
	 */
	function topic_parent_metabox () {
		add_meta_box (
			'bbp_topic_parent_id',
			__( 'Forum', 'bbpress' ),
			'bbp_topic_metabox',
			BBP_TOPIC_POST_TYPE_ID,
			'normal'
		);

		do_action( 'bbp_topic_parent_metabox' );
	}

	/**
	 * topic_parent_metabox_save ()
	 *
	 * Pass the topic post parent id for processing
	 *
	 * @param int $post_id
	 * @return int
	 */
	function topic_parent_metabox_save ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = $_POST['parent_id'];

		do_action( 'bbp_topic_parent_metabox_save' );

		return $parent_id;
	}

	/**
	 * topic_reply_parent_metabox ()
	 *
	 * Add the topic reply parent metabox
	 */
	function topic_reply_parent_metabox () {
		add_meta_box (
			'bbp_topic_reply_parent_id',
			__( 'Topic', 'bbpress' ),
			'bbp_topic_reply_metabox',
			BBP_TOPIC_REPLY_POST_TYPE_ID,
			'normal'
		);

		do_action( 'bbp_topic_reply_parent_metabox' );
	}

	/**
	 * topic_reply_parent_metabox_save ()
	 *
	 * Pass the topic reply post parent id for processing
	 *
	 * @param int $post_id
	 * @return int
	 */
	function topic_reply_parent_metabox_save ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = $_POST['parent_id'];

		do_action( 'bbp_topic_reply_parent_metabox_save' );

		return $parent_id;
	}

	/**
	 * admin_head ()
	 *
	 * Add some general styling to the admin area
	 */
	function admin_head () {
		// Icons for top level admin menus
		$menu_icon_url	= BBP_URL . '/images/menu.png';

		// Top level menu classes
		$forum_class       = sanitize_html_class( BBP_FORUM_POST_TYPE_ID );
		$topic_class       = sanitize_html_class( BBP_TOPIC_POST_TYPE_ID );
		$topic_reply_class = sanitize_html_class( BBP_TOPIC_REPLY_POST_TYPE_ID );

		// Calculate offset for screen_icon sprite
		$icons32_offset = -90 * array_search( $_GET['post_type'], array( BBP_FORUM_POST_TYPE_ID, BBP_TOPIC_POST_TYPE_ID, BBP_TOPIC_REPLY_POST_TYPE_ID ) );

?>
		<style type="text/css" media="screen">
		/*<![CDATA[*/
			#menu-posts-<?php echo $forum_class; ?> .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat 0px -32px;
			}
			#menu-posts-<?php echo $forum_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $forum_class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat 0px 0px;
			}

			#menu-posts-<?php echo $topic_class; ?> .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -70px -32px;
			}
			#menu-posts-<?php echo $topic_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $topic_class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -70px 0px;
			}

			#menu-posts-<?php echo $topic_reply_class; ?> .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -35px -32px;
			}
			#menu-posts-<?php echo $topic_reply_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $topic_reply_class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -35px 0px;
			}

			<?php if ( in_array ( $_GET['post_type'], array( BBP_FORUM_POST_TYPE_ID, BBP_TOPIC_POST_TYPE_ID, BBP_TOPIC_REPLY_POST_TYPE_ID ) ) ) : ?>
			#icon-edit, #icon-post {
				background: url(<?php echo BBP_URL . '/images/icons32.png'; ?>) no-repeat -4px <?php echo $icons32_offset; ?>px;
			}
			
			.column-bbp_topics { width: 10%; }
			<?php endif; ?>
		/*]]>*/
		</style>
<?php
		// Add extra actions to bbPress admin header area
		do_action( 'bbp_admin_head' );
	}

	/**
	 * user_profile_update ()
	 *
	 * Responsible for showing additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_update ( $user_id ) {
		if ( !bbp_has_access() )
			return false;

		// Add extra actions to bbPress profile update
		do_action( 'bbp_user_profile_update' );
	}

	/**
	 * user_profile_forums ()
	 *
	 * Responsible for saving additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_forums ( $profileuser ) {

		if ( !bbp_has_access() )
			return false;

?>
		<h3><?php _e( 'Forums', 'bbpress' ); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'Forums', 'bbpress' ); ?></th>
				<td>
					
				</td>
			</tr>
		</table>
<?php

		// Add extra actions to bbPress profile update
		do_action( 'bbp_user_profile_forums' );
	}
	
	/**
	 * filter_manage_forums_columns ()
	 *
	 * Manage the column headers for the forums page
	 *
	 * @param array $cols
	 * @return array $cols
	 */
	function filter_manage_forums_columns ( $cols ) {		
		$cols = array(
			'cb'         => '<input type="checkbox" />',
			'title'      => __( 'Forum', 'bbpress' ),
			'bbp_topics' => __( 'Topics', 'bbpress' ),
			'author'     => __( 'Author', 'bbpress' ),
			'date'       => __( 'Date' , 'bbpress' )
		);
		return $cols;
	}
	
	/**
	 * action_manage_forums_custom_column ( $col, $post_id )
	 *
	 * Print extra columns for the forums page
	 *
	 * @param string $col
	 * @param int $post_id
	 */
	function action_manage_forums_custom_column ( $col, $post_id ) {
		switch ( $col ) {
			case 'bbp_topics' :
				bbp_forum_topic_count();
				break;
		}
	}

	/**
	 * filter_forums_page_row_actions ( $actions, $post )
	 *
	 * Remove the quick-edit action link and display the description under the forum title
	 *
	 * @param array $actions
	 * @param array $post	
	 * @return array $actions
	 */	
	function filter_forums_page_row_actions ( $actions, $post ) {
		if ( BBP_FORUM_POST_TYPE_ID == $post->post_type )
			unset( $actions['inline'] );
			
		// simple hack to show the forum description under the title
		the_content();
		return $actions;
	}
}

/**
 * bbp_admin_separator ()
 *
 * Forces a separator between bbPress top level menus, and WordPress content menus
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 * 
 * @todo A better job at rearranging and separating top level menus
 * @global array $menu
 */
function bbp_admin_separator () {
	global $menu;

	$menu[24] = $menu[25];
	$menu[25] = array( '', 'read', 'separator1', '', 'wp-menu-separator' );
}
add_action( 'admin_menu', 'bbp_admin_separator' );

/**
 * bbp_topic_metabox ()
 *
 * The metabox that holds all of the additional topic information
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @todo Alot ;)
 * @global object $post
 */
function bbp_topic_metabox () {
	global $post;

	$args = array(
		'post_type'         => BBP_FORUM_POST_TYPE_ID,
		'exclude_tree'      => $post->ID,
		'selected'          => $post->post_parent,
		'show_option_none'  => __( '(No Forum)', 'bbpress' ),
		'sort_column'       => 'menu_order, post_title',
		'child_of'          => '0',
	);

	$posts = bbp_admin_dropdown (
		__( 'Forum', 'bbpress' ),
		__( 'Forum', 'bbpress' ),
		__( 'There are no forums to reply to.', 'bbpress' ),
		$args
	);

	echo $posts;
?>
		<p><strong><?php _e( 'Topic Order', 'bbpress' ); ?></strong></p>
		<p><label class="screen-reader-text" for="menu_order"><?php _e( 'Topic Order', 'bbpress' ) ?></label><input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr( $post->menu_order ); ?>" /></p>
		<p><?php if ( 'page' == $post->post_type ) _e( 'Need help? Use the Help tab in the upper right of your screen.' ); ?></p>
<?php

	do_action( 'bbp_topic_metabox' );
}

/**
 * bbp_topic_reply_metabox ()
 *
 * The metabox that holds all of the additional topic information
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @todo Alot ;)
 * @global object $post
 */
function bbp_topic_reply_metabox () {
	global $post;

	$args = array(
		'post_type'         => BBP_TOPIC_POST_TYPE_ID,
		'exclude_tree'      => $post->ID,
		'selected'          => $post->post_parent,
		'show_option_none'  => __( '(No Topic)', 'bbpress' ),
		'sort_column'       => 'menu_order, post_title',
		'child_of'          => '0',
	);
	
	$posts = bbp_admin_dropdown(
		__( 'Topic', 'bbpress' ),
		__( 'Topic', 'bbpress' ),
		__( 'There are no topics to reply to.', 'bbpress' ),
		$args
	);

	echo $posts;

	do_action( 'bbp_topic_reply_metabox' );
}

/**
 * bbp_admin_dropdown ()
 *
 * General wrapper for creating a drop down of selectable parents
 * 
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 *
 * @param string $title
 * @param string $sub_title
 * @param mixed $error
 * @param array $args
 */
function bbp_admin_dropdown ( $title, $sub_title, $error, $args = '' ) {

	// The actual fields for data entry
	$posts = get_posts( $args );

	if ( !empty( $posts ) ) {
		$output = '<select name="parent_id" id="parent_id">';
		$output .= '<option value="">' . __( '(No Parent)', 'bbpress' ) . '</option>';
		$output .= walk_page_dropdown_tree( $posts, 0, $args );
		$output .= '</select>';
	}

	$output = apply_filters( 'wp_dropdown_pages', $output );

	if ( !empty( $output ) ) : ?>
		<p><strong><?php echo $title; ?></strong></p>
		<label class="screen-reader-text" for="parent_id"><?php echo $sub_title; ?></label>
<?php
		echo $output;
	else :
?>
		<p><strong><?php echo $error; ?></strong></p>
<?php
	endif;
}

?>
