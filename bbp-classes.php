<?php

/**
 * BBP_Main
 *
 * The main bbPress container class
 *
 * @package bbPress
 * @subpackage Loader
 * @since bbPress (1.2-r2464)
 *
 * @todo Alot ;)
 */
class BBP_Main {

	function init () {
		// Setup globals
		add_action ( 'bbp_setup_globals', array( $this, 'setup_globals' ) );

		// wp_head
		add_action ( 'bbp_head',          array( $this, 'bbp_enqueue_scripts' ) );
	}

	/**
	 * setup_globals ()
	 *
	 * Setup all plugin global
	 *
	 * @global array $bbp
	 * @global object $wpdb
	 */
	function setup_globals () {
		global $bbp, $wpdb;

		// For internal identification
		$bbp->id        = BBP_FORUM_POST_TYPE_ID;
		$bbp->slug      = BBP_SLUG;
		$bbp->settings  = BBP_Main::settings();

		// Register this in the active components array
		$bbp->active_components[$bbp->slug] = $bbp->id;
	}

	/**
	 * settings ()
	 *
	 * Loads up any saved settings and filters each default value
	 *
	 * @return array
	 */
	function settings () {

		// @todo site|network wide forum option? Don't see why not both?
		$settings = get_site_option( 'bbp_settings', false );

		// Set default values and allow them to be filtered
		$defaults = array (
			// the cake is a lie
		);

		// Allow settings array to be filtered and return
		return apply_filters( 'bbp_settings', wp_parse_args( $settings, $defaults ) );
	}

	/**
	 * enqueue_scripts ()
	 *
	 * Hooks into wp_head ()
	 *
	 * @return Only return if no data to display
	 */
	function enqueue_scripts () {
		// Load up the JS
		wp_enqueue_script( 'jquery' );

		do_action( 'bbp_enqueue_scripts' );
	}
}

class BBP_Forum {
	function bbp_forum() {

	}
}

class BBP_Topic {
	function bbp_topic() {

	}
}

class BBP_Post {
	function bbp_post() {

	}
}

class BBP_User {
	function bbp_user() {

	}
}


if ( class_exists( 'Walker' ) ) :
/**
 * Create HTML list of forums.
 *
 * @package bbPress
 * @since 1.2-r2514
 * @uses Walker
 */
class Walker_Forum extends Walker {
	/**
	 * @see Walker::$tree_type
	 * @since 1.2-r2514
	 * @var string
	 */
	var $tree_type = BBP_FORUM_POST_TYPE_ID;

	/**
	 * @see Walker::$db_fields
	 * @since 1.2-r2514
	 * @var array
	 */
	var $db_fields = array ( 'parent' => 'post_parent', 'id' => 'ID' );

	/**
	 * @see Walker::start_lvl()
	 *
	 * @since 1.2-r2514
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of page. Used for padding.
	 */
	function start_lvl( &$output, $depth ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul class='children'>\n";
	}

	/**
	 * @see Walker::end_lvl()
	 *
	 * @since 1.2-r2514
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of page. Used for padding.
	 */
	function end_lvl( &$output, $depth ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	/**
	 * @see Walker::start_el()
	 *
	 * @since 1.2-r2514
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $forum Page data object.
	 * @param int $depth Depth of page. Used for padding.
	 * @param int $current_forum Page ID.
	 * @param array $args
	 */
	function start_el( &$output, $forum, $depth, $args, $current_forum ) {
		if ( $depth )
			$indent = str_repeat( "\t", $depth );
		else
			$indent = '';

		extract( $args, EXTR_SKIP );
		$css_class = array( 'bbp_forum_item', 'bbp-forum-item-' . $forum->ID );

		if ( !empty( $current_forum ) ) {
			$_current_page = get_page( $current_forum );

			if ( isset( $_current_page->ancestors ) && in_array( $forum->ID, (array) $_current_page->ancestors ) )
				$css_class[] = 'bbp_current_forum_ancestor';

			if ( $forum->ID == $current_forum )
				$css_class[] = 'bbp_current_forum_item';
			elseif ( $_current_page && $forum->ID == $_current_page->post_parent )
				$css_class[] = 'bbp_current_forum_parent';

		} elseif ( $forum->ID == get_option( 'page_for_posts' ) ) {
			$css_class[] = 'bbp_current_forum_parent';
		}

		$css_class = implode( ' ', apply_filters( 'bbp_forum_css_class', $css_class, $forum ) );
		$output .= $indent . '<li class="' . $css_class . '"><a href="' . get_page_link( $forum->ID ) . '" title="' . esc_attr( wp_strip_all_tags( apply_filters( 'the_title', $forum->post_title, $forum->ID ) ) ) . '">' . $link_before . apply_filters( 'the_title', $forum->post_title, $forum->ID ) . $link_after . '</a>';

		if ( !empty( $show_date ) ) {
			if ( 'modified' == $show_date )
				$time = $forum->post_modified;
			else
				$time = $forum->post_date;

			$output .= " " . mysql2date( $date_format, $time );
		}
	}

	/**
	 * @see Walker::end_el()
	 *
	 * @since 1.2-r2514
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $forum Page data object. Not used.
	 * @param int $depth Depth of page. Not Used.
	 */
	function end_el( &$output, $forum, $depth ) {
		$output .= "</li>\n";
	}
}

endif; // class_exists check

?>
