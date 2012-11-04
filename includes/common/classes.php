<?php

/**
 * bbPress Classes
 *
 * @package bbPress
 * @subpackage Classes
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Component' ) ) :
/**
 * bbPress Component Class
 *
 * The bbPress component class is responsible for simplifying the creation
 * of components that share similar behaviors and routines. It is used
 * internally by bbPress to create forums, topics and replies, but can be
 * extended to create other really neat things.
 *
 * @package bbPress
 * @subpackage Classes
 *
 * @since bbPress (r2688)
 */
class BBP_Component {

	/**
	 * @var string Unique name (for internal identification)
	 * @internal
	 */
	var $name;

	/**
	 * @var Unique ID (normally for custom post type)
	 */
	var $id;

	/**
	 * @var string Unique slug (used in query string and permalinks)
	 */
	var $slug;

	/**
	 * @var WP_Query The loop for this component
	 */
	var $query;

	/**
	 * @var string The current ID of the queried object
	 */
	var $current_id;


	/** Methods ***************************************************************/

	/**
	 * bbPress Component loader
	 *
	 * @since bbPress (r2700)
	 *
	 * @param mixed $args Required. Supports these args:
	 *  - name: Unique name (for internal identification)
	 *  - id: Unique ID (normally for custom post type)
	 *  - slug: Unique slug (used in query string and permalinks)
	 *  - query: The loop for this component (WP_Query)
	 *  - current_id: The current ID of the queried object
	 * @uses BBP_Component::setup_globals() Setup the globals needed
	 * @uses BBP_Component::includes() Include the required files
	 * @uses BBP_Component::setup_actions() Setup the hooks and actions
	 */
	public function __construct( $args = '' ) {
		if ( empty( $args ) )
			return;

		$this->setup_globals( $args );
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Component global variables
	 *
	 * @since bbPress (r2700)
	 * @access private
	 *
	 * @uses apply_filters() Calls 'bbp_{@link BBP_Component::name}_id'
	 * @uses apply_filters() Calls 'bbp_{@link BBP_Component::name}_slug'
	 */
	private function setup_globals( $args = '' ) {
		$this->name = $args['name'];
		$this->id   = apply_filters( 'bbp_' . $this->name . '_id',   $args['id']   );
		$this->slug = apply_filters( 'bbp_' . $this->name . '_slug', $args['slug'] );
	}

	/**
	 * Include required files
	 *
	 * @since bbPress (r2700)
	 * @access private
	 *
	 * @uses do_action() Calls 'bbp_{@link BBP_Component::name}includes'
	 */
	private function includes() {
		do_action( 'bbp_' . $this->name . 'includes' );
	}

	/**
	 * Setup the actions
	 *
	 * @since bbPress (r2700)
	 * @access private
	 *
	 * @uses add_action() To add various actions
	 * @uses do_action() Calls
	 *                    'bbp_{@link BBP_Component::name}setup_actions'
	 */
	private function setup_actions() {
		add_action( 'bbp_register_post_types',    array( $this, 'register_post_types'    ), 10, 2 ); // Register post types
		add_action( 'bbp_register_taxonomies',    array( $this, 'register_taxonomies'    ), 10, 2 ); // Register taxonomies
		add_action( 'bbp_add_rewrite_tags',       array( $this, 'add_rewrite_tags'       ), 10, 2 ); // Add the rewrite tags
		add_action( 'bbp_generate_rewrite_rules', array( $this, 'generate_rewrite_rules' ), 10, 2 ); // Generate rewrite rules

		// Additional actions can be attached here
		do_action( 'bbp_' . $this->name . 'setup_actions' );
	}

	/**
	 * Setup the component post types
	 *
	 * @since bbPress (r2700)
	 *
	 * @uses do_action() Calls 'bbp_{@link BBP_Component::name}_register_post_types'
	 */
	public function register_post_types() {
		do_action( 'bbp_' . $this->name . '_register_post_types' );
	}

	/**
	 * Register component specific taxonomies
	 *
	 * @since bbPress (r2700)
	 *
	 * @uses do_action() Calls 'bbp_{@link BBP_Component::name}_register_taxonomies'
	 */
	public function register_taxonomies() {
		do_action( 'bbp_' . $this->name . '_register_taxonomies' );
	}

	/**
	 * Add any additional rewrite tags
	 *
	 * @since bbPress (r2700)
	 *
	 * @uses do_action() Calls 'bbp_{@link BBP_Component::name}_add_rewrite_tags'
	 */
	public function add_rewrite_tags() {
		do_action( 'bbp_' . $this->name . '_add_rewrite_tags' );
	}

	/**
	 * Generate any additional rewrite rules
	 *
	 * @since bbPress (r2700)
	 *
	 * @uses do_action() Calls 'bbp_{@link BBP_Component::name}_generate_rewrite_rules'
	 */
	public function generate_rewrite_rules( $wp_rewrite ) {
		do_action_ref_array( 'bbp_' . $this->name . '_generate_rewrite_rules', $wp_rewrite );
	}
}
endif; // BBP_Component

if ( class_exists( 'Walker' ) ) :
/**
 * Create HTML list of forums.
 *
 * @package bbPress
 * @subpackage Classes
 *
 * @since bbPress (r2514)
 *
 * @uses Walker
 */
class BBP_Walker_Forum extends Walker {

	/**
	 * @see Walker::$tree_type
	 *
	 * @since bbPress (r2514)
	 *
	 * @var string
	 */
	var $tree_type;

	/**
	 * @see Walker::$db_fields
	 *
	 * @since bbPress (r2514)
	 *
	 * @var array
	 */
	var $db_fields = array( 'parent' => 'post_parent', 'id' => 'ID' );

	/** Methods ***************************************************************/

	/**
	 * Set the tree_type
	 *
	 * @since bbPress (r2514)
	 */
	public function __construct() {
		$this->tree_type = bbp_get_forum_post_type();
	}

	/**
	 * @see Walker::start_lvl()
	 *
	 * @since bbPress (r2514)
	 *
	 * @param string $output Passed by reference. Used to append additional
	 *                        content.
	 * @param int $depth Depth of page. Used for padding.
	 */
	public function start_lvl( &$output, $depth ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul class='children'>\n";
	}

	/**
	 * @see Walker::end_lvl()
	 *
	 * @since bbPress (r2514)
	 *
	 * @param string $output Passed by reference. Used to append additional
	 *                        content.
	 * @param int $depth Depth of page. Used for padding.
	 */
	public function end_lvl( &$output, $depth ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	/**
	 * @see Walker::start_el()
	 *
	 * @since bbPress (r2514)
	 *
	 * @param string $output Passed by reference. Used to append additional
	 *                        content.
	 * @param object $forum Page data object.
	 * @param int $depth Depth of page. Used for padding.
	 * @param int $current_forum Page ID.
	 * @param array $args
	 */
	public function start_el( &$output, $forum, $depth, $args, $current_forum ) {

		$indent = $depth ? str_repeat( "\t", $depth ) : '';

		extract( $args, EXTR_SKIP );
		$css_class = array( 'bbp-forum-item', 'bbp-forum-item-' . $forum->ID );

		if ( !empty( $current_forum ) ) {
			$_current_page = bbp_get_forum( $current_forum );

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
	 * @since bbPress (r2514)
	 *
	 * @param string $output Passed by reference. Used to append additional
	 *                        content.
	 * @param object $forum Page data object. Not used.
	 * @param int $depth Depth of page. Not Used.
	 */
	public function end_el( &$output, $forum, $depth ) {
		$output .= "</li>\n";
	}
}

/**
 * Create HTML dropdown list of bbPress forums/topics.
 *
 * @package bbPress
 * @subpackage Classes
 *
 * @since bbPress (r2746)
 * @uses Walker
 */
class BBP_Walker_Dropdown extends Walker {

	/**
	 * @see Walker::$tree_type
	 *
	 * @since bbPress (r2746)
	 *
	 * @var string
	 */
	var $tree_type;

	/**
	 * @see Walker::$db_fields
	 *
	 * @since bbPress (r2746)
	 *
	 * @var array
	 */
	var $db_fields = array( 'parent' => 'post_parent', 'id' => 'ID' );

	/** Methods ***************************************************************/

	/**
	 * Set the tree_type
	 *
	 * @since bbPress (r2746)
	 */
	public function __construct() {
		$this->tree_type = bbp_get_forum_post_type();
	}

	/**
	 * @see Walker::start_el()
	 *
	 * @since bbPress (r2746)
	 *
	 * @param string $output Passed by reference. Used to append additional
	 *                        content.
	 * @param object $_post Post data object.
	 * @param int $depth Depth of post in reference to parent posts. Used
	 *                    for padding.
	 * @param array $args Uses 'selected' argument for selected post to set
	 *                     selected HTML attribute for option element.
	 * @uses bbp_is_forum_category() To check if the forum is a category
	 * @uses current_user_can() To check if the current user can post in
	 *                           closed forums
	 * @uses bbp_is_forum_closed() To check if the forum is closed
	 * @uses apply_filters() Calls 'bbp_walker_dropdown_post_title' with the
	 *                        title, output, post, depth and args
	 */
	public function start_el( &$output, $_post, $depth, $args ) {
		$pad     = str_repeat( '&nbsp;', $depth * 3 );
		$output .= '<option class="level-' . $depth . '"';

		// Disable the <option> if:
		// - we're told to do so
		// - the post type is a forum
		// - the forum is a category
		// - forum is closed
		if (	( true == $args['disable_categories'] )
				&& ( bbp_get_forum_post_type() == $_post->post_type )
				&& ( bbp_is_forum_category( $_post->ID )
					|| ( !current_user_can( 'edit_forum', $_post->ID ) && bbp_is_forum_closed( $_post->ID )
				)
			) ) {
			$output .= ' disabled="disabled" value=""';
		} else {
			$output .= ' value="' . $_post->ID .'"' . selected( $args['selected'], $_post->ID, false );
		}

		$output .= '>';
		$title   = apply_filters( 'bbp_walker_dropdown_post_title', $_post->post_title, $output, $_post, $depth, $args );
		$output .= $pad . esc_html( $title );
		$output .= "</option>\n";
	}
}

endif; // class_exists check
