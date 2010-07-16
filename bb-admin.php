<?php


/**
 * BBP_Admin
 *
 * Loads plugin admin area
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (1.2-r2464)
 */
class BBP_Admin {

	/**
	 * init()
	 *
	 * Load up the plugin
	 *
	 * @uses do_action
	 */
	function init () {
		add_action( 'edit_user_profile',        array( 'BBP_Admin', 'user_profile_forums' ) );
		add_action( 'show_user_profile',        array( 'BBP_Admin', 'user_profile_forums' ) );

		add_action( 'personal_options_update',  array( 'BBP_Admin', 'user_profile_update' ) );
		add_action( 'edit_user_profile_update', array( 'BBP_Admin', 'user_profile_update' ) );

		add_action( 'admin_head',               array( 'BBP_Admin', 'admin_head' ) );

		add_action( 'admin_menu',               array( 'BBP_Admin', 'topic_parent_metabox' ) );
		add_action( 'save_post',                array( 'BBP_Admin', 'topic_parent_metabox_save' ) );

		add_action( 'admin_menu',               array( 'BBP_Admin', 'reply_parent_metabox' ) );
		add_action( 'save_post',                array( 'BBP_Admin', 'reply_parent_metabox_save' ) );

		/**
		 * For developers:
		 * ---------------------
		 * If you want to make sure your code is loaded after this plugin
		 * have your code load on this action
		 */
		do_action ( 'bbp_admin_init' );
	}

	function topic_parent_metabox () {
		add_meta_box(
			'bbp_topic_parent_id',
			__( 'Forum', 'bbpress' ),
			'bbp_topic_metabox',
			BBP_TOPIC_REPLY_POST_TYPE_ID,
			'normal'
		);
	}

	function topic_parent_metabox_save ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = $_POST['parent_id'];

		return $parent_id;
	}

	function admin_head () {
		$forum_icon_url	= BBP_URL . '/images/admin-forum.png';
		$topic_icon_url	= BBP_URL . '/images/admin-topic.png';
		$reply_icon_url	= BBP_URL . '/images/admin-reply.png';
?>
		<style type="text/css" media="screen">
		/*<![CDATA[*/
			#menu-posts-forum .wp-menu-image {
				background: url(<?php echo $forum_icon_url; ?>) no-repeat 0px -32px;
			}
			#menu-posts-forum:hover .wp-menu-image,
			#menu-posts-forum.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $forum_icon_url; ?>) no-repeat 0px 0px;
			}

			#menu-posts-topic .wp-menu-image {
				background: url(<?php echo $topic_icon_url; ?>) no-repeat 0px -32px;
			}
			#menu-posts-topic:hover .wp-menu-image,
			#menu-posts-topic.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $topic_icon_url; ?>) no-repeat 0px 0px;
			}

			#menu-posts-topicreply .wp-menu-image {
				background: url(<?php echo $reply_icon_url; ?>) no-repeat 0px -32px;
			}
			#menu-posts-topicreply:hover .wp-menu-image,
			#menu-posts-topicreply.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $reply_icon_url; ?>) no-repeat 0px 0px;
			}
		/*]]>*/
		</style>
<?php
	}

	function reply_parent_metabox () {
		add_meta_box (
			'bbp_reply_parent_id',
			__( 'Topic', 'bbpress' ),
			'bbp_reply_metabox',
			'topic_reply',
			'normal'
		);
	}

	function reply_parent_metabox_save ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = $_POST['parent_id'];

		return $parent_id;
	}

	function user_profile_update( $user_id ) {
		if ( !bbp_has_access() )
			return false;

	}

	function user_profile_forums( $profileuser ) {

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
