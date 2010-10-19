<?php

if ( !class_exists( 'BBP_Admin' ) ) :
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

	function bbp_admin () {
		// Attach the bbPress admin init action to the WordPress admin init action.
		add_action( 'admin_init',                                           array( $this, 'init' ) );

		// User profile edit/display actions
		add_action( 'edit_user_profile',                                    array( $this, 'user_profile_forums' ) );
		add_action( 'show_user_profile',                                    array( $this, 'user_profile_forums' ) );

		// User profile save actions
		add_action( 'personal_options_update',                              array( $this, 'user_profile_update' ) );
		add_action( 'edit_user_profile_update',                             array( $this, 'user_profile_update' ) );

		// Add some general styling to the admin area
		add_action( 'admin_head',                                           array( $this, 'admin_head' ) );

		// Forum column headers.
		add_filter( 'manage_' . BBP_FORUM_POST_TYPE_ID . '_posts_columns',  array( $this, 'forums_column_headers' ) );

		// Forum columns (in page row)
		add_action( 'manage_pages_custom_column',                           array( $this, 'forums_column_data' ), 10, 2 );
		add_filter( 'page_row_actions',                                     array( $this, 'forums_post_row_actions' ), 10, 2 );

		// Topic column headers.
		add_filter( 'manage_' . BBP_TOPIC_POST_TYPE_ID . '_posts_columns',  array( $this, 'topics_column_headers' ) );

		// Topic columns (in post row)
		add_action( 'manage_posts_custom_column',                           array( $this, 'topics_column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                                     array( $this, 'post_row_actions' ), 10, 2 );

		// Topic metabox actions
		add_action( 'admin_menu',                                           array( $this, 'topic_parent_metabox' ) );
		add_action( 'save_post',                                            array( $this, 'topic_parent_metabox_save' ) );

		// Topic reply metabox actions
		add_action( 'admin_menu',                                           array( $this, 'topic_reply_parent_metabox' ) );
		add_action( 'save_post',                                            array( $this, 'topic_reply_parent_metabox_save' ) );

		// Register bbPress admin style
		add_action( 'admin_init',                                           array( $this, 'register_admin_style' ) );
	}

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
		$parent_id = isset( $_POST['parent_id'] ) ? $_POST['parent_id'] : 0;

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
			BBP_REPLY_POST_TYPE_ID,
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
		$parent_id = isset( $_POST['parent_id'] ) ? $_POST['parent_id'] : 0;

		do_action( 'bbp_topic_reply_parent_metabox_save' );

		return $parent_id;
	}

	/**
	 * admin_head ()
	 *
	 * Add some general styling to the admin area
	 */
	function admin_head () {
		global $wp_query;

		// Icons for top level admin menus
		$menu_icon_url	= BBP_IMAGES_URL . '/menu.png';

		// Top level menu classes
		$forum_class = sanitize_html_class( BBP_FORUM_POST_TYPE_ID );
		$topic_class = sanitize_html_class( BBP_TOPIC_POST_TYPE_ID );
		$reply_class = sanitize_html_class( BBP_REPLY_POST_TYPE_ID );

		// Calculate offset for screen_icon sprite
		if ( bbp_is_forum() || bbp_is_topic() || bbp_is_reply() )
			$icons32_offset = -90 * array_search( $_GET['post_type'], array( BBP_FORUM_POST_TYPE_ID, BBP_TOPIC_POST_TYPE_ID, BBP_REPLY_POST_TYPE_ID ) );

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

			#menu-posts-<?php echo $reply_class; ?> .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -35px -32px;
			}
			#menu-posts-<?php echo $reply_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $reply_class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -35px 0px;
			}

			<?php if ( in_array ( $_GET['post_type'], array( BBP_FORUM_POST_TYPE_ID, BBP_TOPIC_POST_TYPE_ID, BBP_REPLY_POST_TYPE_ID ) ) ) : ?>
			#icon-edit, #icon-post {
				background: url(<?php echo BBP_IMAGES_URL . '/icons32.png'; ?>) no-repeat -4px <?php echo $icons32_offset; ?>px;
			}
			
			.column-bbp_forum_topic_count, .column-bbp_forum_topic_reply_count, .column-bbp_topic_forum, .column-bbp_topic_reply_count, .column-bbp_topic_freshness { width: 10%; }
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
	 * forums_column_headers ()
	 *
	 * Manage the column headers for the forums page
	 *
	 * @param array $columns
	 * @return array $columns
	 */
	function forums_column_headers ( $columns ) {
		$columns = array (
			'cb'                          => '<input type="checkbox" />',
			'title'                       => __( 'Forum', 'bbpress' ),
			'bbp_forum_topic_count'       => __( 'Topics', 'bbpress' ),
			'bbp_forum_topic_reply_count' => __( 'Replies', 'bbpress' ),
			'author'                      => __( 'Creator', 'bbpress' ),
			'date'                        => __( 'Date' , 'bbpress' )
		);

		return apply_filters( 'bbp_admin_forums_column_headers', $columns );
	}
	
	/**
	 * forums_column_data ( $column, $post_id )
	 *
	 * Print extra columns for the forums page
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	function forums_column_data ( $column, $post_id ) {
		if ( $_GET['post_type'] !== BBP_FORUM_POST_TYPE_ID )
			return $column;

		switch ( $column ) {
			case 'bbp_forum_topic_count' :
				bbp_forum_topic_count();
				break;

			case 'bbp_forum_topic_reply_count' :
				bbp_forum_topic_reply_count();
				break;

			default:
				do_action( 'bbp_admin_forums_column_data', $column, $post_id );
				break;
		}
	}

	/**
	 * forums_post_row_actions ( $actions, $post )
	 *
	 * Remove the quick-edit action link and display the description under the forum title
	 *
	 * @param array $actions
	 * @param array $post	
	 * @return array $actions
	 */	
	function forums_post_row_actions ( $actions, $post ) {
		if ( BBP_FORUM_POST_TYPE_ID == $post->post_type ) {
			unset( $actions['inline'] );

			// simple hack to show the forum description under the title
			the_content();
		}

		return $actions;
	}


	/**
	 * topics_column_headers ()
	 *
	 * Manage the column headers for the topics page
	 *
	 * @param array $columns
	 * @return array $columns
	 */
	function topics_column_headers ( $columns ) {
		$columns = array(
			'cb'                    => '<input type="checkbox" />',
			'title'                 => __( 'Topics', 'bbpress' ),
			'bbp_topic_forum'       => __( 'Forum', 'bbpress' ),
			'bbp_topic_reply_count' => __( 'Replies', 'bbpress' ),
			'author'                => __( 'Author', 'bbpress' ),
			'date'                  => __( 'Date' , 'bbpress' ),
			'bbp_topic_freshness'   => __( 'Freshness', 'bbpress' )
		);

		return apply_filters( 'bbp_admin_topics_column_headers', $columns );
	}

	/**
	 * topics_column_data ( $column, $post_id )
	 *
	 * Print extra columns for the topics page
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	function topics_column_data ( $column, $post_id ) {
		if ( $_GET['post_type'] !== BBP_TOPIC_POST_TYPE_ID )
			return $column;

		switch ( $column ) {
			case 'bbp_topic_forum' :
				// Output forum name
				bbp_topic_forum_title();

				// Link information
				$actions = apply_filters( 'topic_forum_row_actions', array (
					'edit' => '<a href="' . add_query_arg( array( 'post' => bbp_get_topic_forum_ID(), 'action' => 'edit' ), admin_url( '/post.php' ) ) . '">' . __( 'Edit', 'bbpress' ) . '</a>',
					'view' => '<a href="' . bbp_get_topic_permalink() . '">' . __( 'View', 'bbpress' ) . '</a>'
				) );

				// Output forum post row links
				$i = 0;
				echo '<div class="row-actions">';
				foreach ( $actions as $action => $link ) {
					++$i;
					( $i == count( $actions ) ) ? $sep = '' : $sep = ' | ';
					echo '<span class="' . $action . '">' . $link . $sep . '</span>';
				}
				echo '</div>';
				break;

			case 'bbp_topic_reply_count' :
				// Output replies count
				bbp_topic_reply_count();
				break;

			case 'bbp_topic_freshness':
				// Output last activity time and date
				bbp_get_topic_last_active();
				break;

			default :
				do_action( 'bbp_admin_topics_column_data', $column, $post_id );
				break;
		}
	}
	
	/** 
	 * post_row_actions ( $actions, $post ) 
	 * 
	 * Remove the quick-edit action link under the topic/reply title 
	 *
	 * @param array $actions
	 * @param array $post	
	 * @return array $actions
	 */	
	function post_row_actions ( $actions, $post ) {
		if ( in_array( $post->post_type, array( BBP_TOPIC_POST_TYPE_ID, BBP_REPLY_POST_TYPE_ID ) ) )
			unset( $actions['inline hide-if-no-js'] );

		return $actions;
	}

	/**
	 * register_admin_style ()
	 *
	 * Registers the bbPress admin color scheme
	 */
	function register_admin_style () {
		wp_admin_css_color( 'bbpress', __( 'Green', 'bbpress' ), BBP_URL . 'bbp-css/admin.css', array( '#222222', '#006600', '#deece1', '#6eb469' ) );
	}
}
endif; // class_exists check

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

// Setup bbPress Admin
$bbp_admin = new BBP_Admin();

?>
