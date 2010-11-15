<?php

if ( class_exists( 'Walker' ) ) :
/**
 * Create HTML list of forums.
 *
 * @package bbPress
 * @since r2514
 * @uses Walker
 */
class Walker_Forum extends Walker {
	/**
	 * @see Walker::$tree_type
	 * @since r2514
	 * @var string
	 */
	var $tree_type;

	/**
	 * @see Walker::$db_fields
	 * @since r2514
	 * @var array
	 */
	var $db_fields = array ( 'parent' => 'post_parent', 'id' => 'ID' );

	/**
	 * @see Walker::start_lvl()
	 *
	 * @since r2514
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
	 * @since r2514
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
	 * @since r2514
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
	 * @since r2514
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
