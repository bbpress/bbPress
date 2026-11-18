<?php

/**
 * bbPress Classes.
 *
 * @package bbPress
 * @subpackage Classes
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Component' ) ) :
/**
 * bbPress Component Class.
 *
 * The bbPress component class is responsible for simplifying the creation
 * of components that share similar behaviors and routines. It is used
 * internally by bbPress to create forums, topics and replies, but can be
 * extended to create other really neat things.
 *
 * @package bbPress
 * @subpackage Classes
 *
 * @since 2.0.0 bbPress (r2700)
 */
class BBP_Component {

	/**
	 * Unique name (for internal identification).
	 *
	 * @var string
	 * @internal
	 */
	public $name;

	/**
	 * Unique component ID, usually a post type.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Unique slug (used in query string and permalinks).
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * The loop for this component.
	 *
	 * @var WP_Query
	 */
	public $query;

	/**
	 * The current ID of the queried object.
	 *
	 * @var string
	 */
	public $current_id;


	/** Methods ***************************************************************/

	/**
	 * bbPress Component loader.
	 *
	 * @since 2.1.0 bbPress (r3829)
	 *
	 * @param array<string, mixed> $args Component name, ID, and slug.
	 */
	public function __construct( $args = array() ) {
		if ( empty( $args ) ) {
			return;
		}

		$this->setup_globals( $args );
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Component global variables.
	 *
	 * @since 2.0.0 bbPress (r3376)
	 *
	 * @access private
	 *
	 * @param array<string, mixed> $args Component name, ID, and slug.
	 * @return void
	 */
	private function setup_globals( $args = array() ) {
		$this->name = $args['name'];
		$this->id   = apply_filters( 'bbp_' . $this->name . '_id',   $args['id']   );
		$this->slug = apply_filters( 'bbp_' . $this->name . '_slug', $args['slug'] );
	}

	/**
	 * Include required files.
	 *
	 * @since 2.0.0 bbPress (r3376)
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function includes() {
		do_action( 'bbp_' . $this->name . 'includes' );
	}

	/**
	 * Setup the actions.
	 *
	 * @since 2.0.0 bbPress (r3376)
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function setup_actions() {
		add_action( 'bbp_register_post_types',    array( $this, 'register_post_types'    ), 10, 0 ); // Register post types
		add_action( 'bbp_register_taxonomies',    array( $this, 'register_taxonomies'    ), 10, 0 ); // Register taxonomies
		add_action( 'bbp_add_rewrite_tags',       array( $this, 'add_rewrite_tags'       ), 10, 0 ); // Add the rewrite tags
		add_action( 'bbp_generate_rewrite_rules', array( $this, 'generate_rewrite_rules' ), 10, 1 ); // Generate rewrite rules

		// Additional actions can be attached here
		do_action( 'bbp_' . $this->name . 'setup_actions' );
	}

	/**
	 * Setup the component post types.
	 *
	 * @since 2.0.0 bbPress (r2700)
	 *
	 * @return void
	 */
	public function register_post_types() {
		do_action( 'bbp_' . $this->name . '_register_post_types' );
	}

	/**
	 * Register component specific taxonomies.
	 *
	 * @since 2.0.0 bbPress (r2700)
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		do_action( 'bbp_' . $this->name . '_register_taxonomies' );
	}

	/**
	 * Add any additional rewrite tags.
	 *
	 * @since 2.0.0 bbPress (r2700)
	 *
	 * @return void
	 */
	public function add_rewrite_tags() {
		do_action( 'bbp_' . $this->name . '_add_rewrite_tags' );
	}

	/**
	 * Generate any additional rewrite rules.
	 *
	 * @since 2.0.0 bbPress (r2700)
	 *
	 * @param WP_Rewrite $wp_rewrite WordPress rewrite rules object.
	 * @return void
	 */
	public function generate_rewrite_rules( $wp_rewrite ) {
		do_action_ref_array( 'bbp_' . $this->name . '_generate_rewrite_rules', array( &$wp_rewrite ) );
	}
}
endif; // BBP_Component

if ( class_exists( 'Walker' ) ) :
/**
 * Create HTML dropdown list of bbPress forums/topics.
 *
 * @package bbPress
 * @subpackage Classes
 *
 * @since 2.0.0 bbPress (r2746)
 */
class BBP_Walker_Dropdown extends Walker {

	/**
	 * Post type handled by the forum dropdown walker.
	 *
	 * @see Walker::$tree_type
	 *
	 * @since 2.0.0 bbPress (r2746)
	 *
	 * @var string
	 */
	public $tree_type = 'forum';

	/**
	 * Fields used to identify a post and its parent.
	 *
	 * @see Walker::$db_fields
	 *
	 * @since 2.0.0 bbPress (r2746)
	 *
	 * @var array<string, string>
	 */
	public $db_fields = array(
		'parent' => 'post_parent',
		'id'     => 'ID'
	);

	/** Methods ***************************************************************/

	/**
	 * Set the tree_type.
	 *
	 * @since 2.1.0 bbPress (r3829)
	 */
	public function __construct() {
		$this->tree_type = bbp_get_forum_post_type();
	}

	/**
	 * Render one option in the forum dropdown.
	 *
	 * @see Walker::start_el()
	 *
	 * @since 2.0.0 bbPress (r2746)
	 *
	 * @param string $output Passed by reference. Used to append additional
	 *                       content.
	 * @param object $object Post data object.
	 * @param int    $depth  Depth of post in reference to parent posts. Used
	 *                       for padding.
	 * @param array<string, mixed> $args Uses 'selected' to mark the current option.
	 * @param int    $current_object_id ID of the current object.
	 * @return void
	 */
	public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$pad     = str_repeat( '&nbsp;', (int) $depth * 3 );
		$output .= '<option class="level-' . (int) $depth . '"';

		// Disable the <option> if:
		// - we're told to do so
		// - the post type is a forum
		// - the forum is a category
		// - forum is closed
		if ( ( true === $args['disable_categories'] )
				&& ( bbp_get_forum_post_type() === $object->post_type )
				&& ( bbp_is_forum_category( $object->ID )
					|| ( ! current_user_can( 'edit_forum', $object->ID )
						&& bbp_is_forum_closed( $object->ID )
						&& ( ! bbp_is_topic_edit() || ( (int) bbp_get_topic_forum_id() !== (int) $object->ID ) )
				)
			) ) {
			$output .= ' disabled="disabled" value=""';
		} else {
			$output .= ' value="' . (int) $object->ID .'"' . selected( $args['selected'], $object->ID, false );
		}

		$output .= '>';
		$title   = apply_filters( 'bbp_walker_dropdown_post_title', $object->post_title, $output, $object, $depth, $args );
		$output .= $pad . esc_html( $title );
		$output .= "</option>\n";
	}
}

/**
 * Create hierarchical list of bbPress replies.
 *
 * @package bbPress
 * @subpackage Classes
 *
 * @since 2.4.0 bbPress (r4944)
 */
class BBP_Walker_Reply extends Walker {

	/**
	 * Reply post type handled by the reply walker.
	 *
	 * @see Walker::$tree_type
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @var string
	 */
	public $tree_type = 'reply';

	/**
	 * Fields used to identify a reply and its parent.
	 *
	 * @see Walker::$db_fields
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @var array<string, string>
	 */
	public $db_fields = array(
		'parent' => 'reply_to',
		'id'     => 'ID'
	);

	/**
	 * Confirm the tree_type.
	 *
	 * @since 2.6.0 bbPress (r5389)
	 */
	public function __construct() {
		$this->tree_type = bbp_get_reply_post_type();
	}

	/**
	 * Open a nested reply list.
	 *
	 * @see Walker::start_lvl()
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int    $depth  Depth of reply.
	 * @param array<string, mixed> $args Uses 'style' argument for type of HTML list.
	 * @return void
	 */
	public function start_lvl( &$output = '', $depth = 0, $args = array() ) {
		bbpress()->reply_query->reply_depth = (int) $depth + 1;

		switch ( $args['style'] ) {
			case 'div':
				break;
			case 'ol':
				$output .= "<ol class='bbp-threaded-replies'>\n";
				break;
			case 'ul':
			default:
				$output .= "<ul class='bbp-threaded-replies'>\n";
				break;
		}
	}

	/**
	 * Close a nested reply list.
	 *
	 * @see Walker::end_lvl()
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int    $depth  Depth of reply.
	 * @param array<string, mixed> $args Will only append content if style argument value is 'ol' or 'ul'.
	 * @return void
	 */
	public function end_lvl( &$output = '', $depth = 0, $args = array() ) {
		bbpress()->reply_query->reply_depth = (int) $depth + 1;

		switch ( $args['style'] ) {
			case 'div':
				break;
			case 'ol':
				$output .= "</ol>\n";
				break;
			case 'ul':
			default:
				$output .= "</ul>\n";
				break;
		}
	}

	/**
	 * Display a reply and its child replies.
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param WP_Post|false $element           Reply to display.
	 * @param array<array-key, mixed> $children_elements Child replies, passed by reference.
	 * @param int           $max_depth         Maximum walker depth.
	 * @param int           $depth             Current walker depth.
	 * @param array<array-key, mixed> $args Walker arguments.
	 * @param string        $output            Output markup, passed by reference.
	 */
	public function display_element( $element = false, &$children_elements = array(), $max_depth = 0, $depth = 0, $args = array(), &$output = '' ) {

		if ( empty( $element ) ) {
			return;
		}

		// Get element's id
		$id_field = $this->db_fields['id'];
		$id       = $element->$id_field;

		// Display element
		parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );

		// If we're at the max depth and the current element still has children, loop over those
		// and display them at this level to prevent them being orphaned to the end of the list.
		if ( ( $max_depth <= (int) $depth + 1 ) && isset( $children_elements[ $id ] ) ) {
			foreach ( $children_elements[ $id ] as $child ) {
				$this->display_element( $child, $children_elements, $max_depth, $depth, $args, $output );
			}
			unset( $children_elements[ $id ] );
		}
	}

	/**
	 * Render one reply in the tree.
	 *
	 * @see Walker::start_el()
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param string  $output            Output markup, passed by reference.
	 * @param WP_Post $object            Reply to display.
	 * @param int     $depth             Current walker depth.
	 * @param array<string, mixed> $args Walker arguments.
	 * @param int     $current_object_id ID of the current object.
	 * @return void
	 */
	public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {

		// Set up reply
		++$depth;
		bbpress()->reply_query->reply_depth = (int) $depth;
		bbpress()->reply_query->post        = $object;
		bbpress()->current_reply_id         = $object->ID;

		// Check for a callback and use it if specified
		if ( ! empty( $args['callback'] ) ) {
			ob_start();
			call_user_func( $args['callback'], $object, $args, $depth );
			$output .= ob_get_clean();
			return;
		}

		// Style for div or list element
		if ( ! empty( $args['style'] ) && ( 'div' === $args['style'] ) ) {
			$output .= "<div>\n";
		} else {
			$output .= "<li>\n";
		}

		$output .= bbp_buffer_template_part( 'loop', 'single-reply', false );
	}

	/**
	 * Close a reply element.
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param string        $output Output markup, passed by reference.
	 * @param WP_Post|false $object Reply being closed.
	 * @param int           $depth  Current walker depth.
	 * @param array<string, mixed> $args Walker arguments.
	 * @return void
	 */
	public function end_el( &$output = '', $object = false, $depth = 0, $args = array() ) {

		// Check for a callback and use it if specified
		if ( ! empty( $args['end-callback'] ) ) {
			ob_start();
			call_user_func( $args['end-callback'], $object, $args, $depth );
			$output .= ob_get_clean();
			return;
		}

		// Style for div or list element
		if ( ! empty( $args['style'] ) && ( 'div' === $args['style'] ) ) {
			$output .= "</div>\n";
		} else {
			$output .= "</li>\n";
		}
	}
}

/**
 * Create HTML dropdown list of bbPress replies.
 *
 * @package bbPress
 * @subpackage Classes
 *
 * @since 2.6.0 bbPress (r5389)
 */
class BBP_Walker_Reply_Dropdown extends Walker {

	/**
	 * Reply post type handled by the reply dropdown walker.
	 *
	 * @see Walker::$tree_type
	 *
	 * @since 2.6.0 bbPress (r5389)
	 *
	 * @var string
	 */
	public $tree_type = 'reply';

	/**
	 * Fields used to identify a reply and its parent.
	 *
	 * @see Walker::$db_fields
	 *
	 * @since 2.6.0 bbPress (r5389)
	 *
	 * @var array<string, string>
	 */
	public $db_fields = array(
		'parent' => 'reply_to',
		'id'     => 'ID'
	);

	/** Methods ***************************************************************/

	/**
	 * Confirm the tree_type.
	 *
	 * @since 2.6.0 bbPress (r5389)
	 */
	public function __construct() {
		$this->tree_type = bbp_get_reply_post_type();
	}

	/**
	 * Render one option in the reply dropdown.
	 *
	 * @see Walker::start_el()
	 *
	 * @since 2.6.0 bbPress (r5389)
	 *
	 * @param string $output Passed by reference. Used to append additional
	 *                       content.
	 *
	 * @param object $object Post data object.
	 *
	 * @param int    $depth  Depth of post in reference to parent posts. Used
	 *                       for padding.
	 *
	 * @param array<string, mixed> $args Uses 'selected' to mark the current option.
	 *
	 * @param int    $current_object_id Not Used.
	 * @return void
	 */
	public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {

		// Set up reply
		++$depth;

		// Get the reply ID
		if ( isset( $args['exclude'][0] ) ) {
			$reply_id = (int) $args['exclude'][0];
		} else {
			$reply_id = bbp_get_reply_id();
		}

		// Get ancestors to determine which items to disable
		$ancestors = bbp_get_reply_ancestors( $object->ID );
		array_push( $ancestors, $object->ID );

		// Determine the indentation
		$pad = str_repeat( '&nbsp;', (int) $depth * 3 );

		// Determine reply title (either post_title, or excerpt of post_content)
		$title = ! empty( $object->post_title ) ? $object->post_title : wp_html_excerpt( $object->post_content, 10 );
		/* translators: 1: Reply ID, 2: Reply title */
		$title   = sprintf( esc_html__( '%1$s - %2$s', 'bbpress' ), (int) $object->ID, $title );
		$title   = apply_filters( 'bbp_walker_dropdown_post_title', $title, $output, $object, $depth, $args );

		// Attributes
		$class = 'level-' . (int) $depth;
		$value = (int) $object->ID;

		// Start an output buffer to make late escaping easier
		ob_start(); ?>

		<option class="<?php echo esc_attr( $class ); ?>" value="<?php echo esc_attr( (string) $value ); ?>"<?php selected( $args['selected'], $object->ID ); ?> <?php disabled( in_array( $reply_id, $ancestors, true ), true ); ?>><?php echo $pad . esc_html( $title ); ?></option>

		<?php

		// Append the output buffer to the $output variable
		$output .= ob_get_clean();
	}
}
endif; // class_exists check
