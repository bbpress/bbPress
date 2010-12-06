<?php

if ( !class_exists( 'BBP_Component' ) ) :
/**
 * BBP_Component
 *
 * The bbPress component class is responsible for simplifying the creation
 * of components that share similar behaviors and routines. It is used internally
 * by bbPress to create forums, topics, and replies, but can be extended to create
 * other really neat things.
 *
 * @since (r2688)
 */
class BBP_Component {

	// Unique name (For internal identification)
	var $name;

	// Unique ID (Normally for custom post type)
	var $id;

	// Unique slug (Used in query string and permalinks)
	var $slug;

	// The loop for this component
	var $query;

	// The current ID of the queried object
	var $current_id;

	function BBP_Component ( $args = '' ) {
		if ( empty( $args ) )
			return;

		$this->_setup_globals( $args );
		$this->_includes();
		$this->_setup_actions();
	}

	/**
	 * _setup_globals ()
	 *
	 * Component global variables
	 */
	function _setup_globals ( $args = '' ) {
		$this->name = $args['name'];
		$this->id   = apply_filters( 'bbp_' . $this->name . '_id',   $args['id']   );
		$this->slug = apply_filters( 'bbp_' . $this->name . '_slug', $args['slug'] );
	}

	/**
	 * _includes ()
	 *
	 * Include required files
	 *
	 * @since bbPress (r2688)
	 */
	function _includes () {
		do_action( 'bbp_' . $this->name . '_includes' );
	}

	/**
	 * _setup_actions ()
	 *
	 * Setup the default hooks and actions
	 *
	 * @since bbPress (r2688)
	 */
	function _setup_actions () {
		// Register content types
		add_action( 'bbp_register_post_types',      array ( $this, 'register_post_types'      ), 10, 2 );

		// Register taxonomies
		add_action( 'bbp_register_taxonomies',      array ( $this, 'register_taxonomies'      ), 10, 2 );

		// Add the rewrite tags
		add_action( 'bbp_add_rewrite_tags',         array ( $this, 'add_rewrite_tags'         ), 10, 2 );

		// Generate rewrite rules
		add_action( 'bbp_generate_rewrite_rules',   array ( $this, 'generate_rewrite_rules'   ), 10, 2 );

		// Additional actions can be attached here
		do_action( 'bbp_' . $this->name . '_setup_actions' );
	}

	/**
	 * register_post_types ()
	 *
	 * Setup the component post types
	 *
	 * @since bbPress (r2688)
	 */
	function register_post_types () {
		do_action( 'bbp_' . $this->name . '_register_post_types' );
	}

	/**
	 * register_taxonomies ()
	 *
	 * Register component specific taxonomies
	 *
	 * @since bbPress (r2688)
	 */
	function register_taxonomies () {
		do_action( 'bbp_' . $this->name . '_register_taxonomies' );
	}

	/**
	 * add_rewrite_tags ()
	 *
	 * Add any additional rewrite tags
	 *
	 * @since bbPress (r2688)
	 */
	function add_rewrite_tags () {
		do_action( 'bbp_' . $this->name . '_add_rewrite_tags' );
	}

	/**
	 * generate_rewrite_rules ()
	 *
	 * Generate any additional rewrite rules
	 *
	 * @since bbPress (r2688)
	 */
	function generate_rewrite_rules ( $wp_rewrite ) {
		do_action( 'bbp_' . $this->name . '_generate_rewrite_rules' );
	}
}
endif; // BBP_Component

if ( class_exists( 'Walker' ) ) :
/**
 * Create HTML list of forums.
 *
 * @package bbPress
 * @since r2514
 * @uses Walker
 */
class BBP_Walker_Forum extends Walker {
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
	 * Set the tree_type
	 *
	 * @global bbPress $bbp
	 */
	function BBP_Walker_Forum () {
		global $bbp;

		$this->tree_type = $bbp->forum_id;
	}

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

		$indent = $depth ? str_repeat( "\t", $depth ) : '';

		extract( $args, EXTR_SKIP );
		$css_class = array( 'bbp-forum-item', 'bbp-forum-item-' . $forum->ID );

		if ( !empty( $current_forum ) ) {
			$_current_page = get_post( $current_forum );

			if ( isset( $_current_page->ancestors ) && in_array( $forum->ID, (array) $_current_page->ancestors ) )
				$css_class[] = 'bbp-current-forum-ancestor';

			if ( $forum->ID == $current_forum )
				$css_class[] = 'bbp_current_forum_item';
			elseif ( $_current_page && $forum->ID == $_current_page->post_parent )
				$css_class[] = 'bbp-current-forum-parent';

		} elseif ( $forum->ID == get_option( 'page_for_posts' ) ) {
			$css_class[] = 'bbp-current-forum-parent';
		}

		$css_class = implode( ' ', apply_filters( 'bbp_forum_css_class', $css_class, $forum ) );
		$output .= $indent . '<li class="' . $css_class . '"><a href="' . bbp_get_forum_permalink( $forum->ID ) . '" title="' . esc_attr( wp_strip_all_tags( apply_filters( 'the_title', $forum->post_title, $forum->ID ) ) ) . '">' . $link_before . apply_filters( 'the_title', $forum->post_title, $forum->ID ) . $link_after . '</a>';

		if ( !empty( $show_date ) ) {
			$time    = ( 'modified' == $show_date ) ? $forum->post_modified : $time = $forum->post_date;
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
