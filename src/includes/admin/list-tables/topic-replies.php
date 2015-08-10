<?php

/**
 * Topic Replies List Table class.
 *
 * @package    bbPress
 * @subpackage List_Table
 * @since      2.6.0
 * @access     private
 *
 * @see WP_Posts_List_Table
 */

// Include the main list table class if it's not included yet
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if ( class_exists( 'WP_List_Table' ) ) :
/**
 * Topic replies list table
 *
 * This list table is responsible for showing the replies to a topic in a
 * metabox, similar to comments in posts and pages.
 *
 * @since bbPress (r5886)
 */
class BBP_Topic_Replies_List_Table extends WP_List_Table {

	/**
	 * The main constructor method
	 *
	 * @since bbPress (r5886)
	 */
	public function __construct( $args = array() ) {
		$args = array(
			'singular' => esc_html__( 'Reply',   'bbpress' ),
			'plural'   => esc_html__( 'Replies', 'bbpress' ),
			'ajax'     => true
		);
		parent::__construct( $args );
	}

	/**
	 * Setup the list-table's columns
	 *
	 * @since bbPress (r5886)
	 *
	 * @see WP_List_Table::::single_row_columns()
	 *
	 * @return array An associative array containing column information
	 */
	public function get_columns() {
		return array(
			//'cb'                 => '<input type="checkbox" />',
			'bbp_topic_reply_author' => esc_html__( 'Author',  'bbpress' ),
			'bbp_reply_content'      => esc_html__( 'Content', 'bbpress' ),
			'bbp_reply_created'      => esc_html__( 'Replied',  'bbpress' ),
		);
	}

	/**
	 * Allow `bbp_reply_created` to be sortable
	 *
	 * @since bbPress (r5886)
	 *
	 * @return array An associative array containing the `bbp_reply_created` column
	 */
	public function get_sortable_columns() {
		return array(
			'bbp_reply_created' => array( 'bbp_reply_created', false )
		);
	}

	/**
	 * Setup the bulk actions
	 *
	 * @since bbPress (r5886)
	 *
	 * @return array An associative array containing all the bulk actions
	 */
	public function get_bulk_actions() {
		return array();

		// @todo cap checks
		return array(
			'unapprove' => esc_html__( 'Unapprove', 'bbpress' ),
			'spam'      => esc_html__( 'Spam',      'bbpress' ),
			'trash'     => esc_html__( 'Trash',     'bbpress' )
		);
	}

	/**
	 * Output the `cb` column for bulk actions (if we implement them)
	 *
	 * @since bbPress (r5886)
	 */
	public function column_cb( $item = '' ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item->ID
		);
	}

	/**
	 * Output the contents of the `bbp_topic_reply_author` column
	 *
	 * @since bbPress (r5886)
	 */
	public function column_bbp_topic_reply_author( $item = '' ) {
		bbp_reply_author_avatar( $item->ID, 50 );
		bbp_reply_author_display_name( $item->ID );
		echo '<br>';
		bbp_reply_author_email( $item->ID );
		echo '<br>';
		bbp_author_ip( array( 'post_id' => $item->ID ) );
	}

	/**
	 * Output the contents of the `bbp_reply_created` column
	 *
	 * @since bbPress (r5886)
	 */
	public function column_bbp_reply_created( $item = '' ) {
		return sprintf( '%1$s <br /> %2$s',
			esc_attr( get_the_date( '', $item ) ),
			esc_attr( get_the_time( '', $item ) )
		);
	}

	/**
	 * Output the contents of the `bbp_reply_content` column
	 *
	 * @since bbPress (r5886)
	 */
   public function column_bbp_reply_content( $item = '' ) {

		// Define actions array
		$actions = array(
			'view' => '<a href="' . bbp_get_reply_url( $item->ID )  . '">' . esc_html__( 'View', 'bbpress' ) . '</a>'
		);

		// Prepend `edit` link
		if ( current_user_can( 'edit_reply', $item->ID ) ) {
			$actions['edit'] = '<a href="' . get_edit_post_link( $item->ID ) . '">' . esc_html__( 'Edit', 'bbpress' ) . '</a>';
			$actions         = array_reverse( $actions );
		}

		// Filter the reply content
		$reply_content = apply_filters( 'bbp_get_reply_content', $item->post_content, $item->ID );
		$reply_actions = $this->row_actions( $actions );

		// Return content & actions
		return $reply_content . $reply_actions;
	}

	/**
	 * Handle bulk action requests
	 *
	 * @since bbPress (r5886)
	 */
	public function process_bulk_action() {
		switch ( $this->current_action() ) {
			case 'trash' :
				break;
			case 'unapprove' :
				break;
			case 'spam' :
				break;
		}
	}

	/**
	 * Prepare the list-table items for display
	 *
	 * @since bbPress (r5886)
	 *
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 */
	public function prepare_items( $topic_id = 0 ) {

		// Sanitize the topic ID
		$topic_id = bbp_get_topic_id( $topic_id );

		// Set column headers
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns()
		);

		// Handle bulk actions
		$this->process_bulk_action();

		// Query parameters
		$per_page     = 5;
		$current_page = $this->get_pagenum();
		$orderby      = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'date';
		$order        = ( ! empty( $_REQUEST['order']   ) ) ? $_REQUEST['order']   : 'asc';

		// Query for replies
		$reply_query  = new WP_Query( array(
			'post_type'           => bbp_get_reply_post_type(),
			'post_parent'         => $topic_id,
			'posts_per_page'      => $per_page,
			'paged'               => $current_page,
			'orderby'             => $orderby,
			'order'               => ucwords( $order ),
			'hierarchical'        => false,
			'ignore_sticky_posts' => true,
		) );

		// Get the total number of replies, for pagination
		$total_items = bbp_get_topic_reply_count( $topic_id );

		// Set list table items to queried posts
		$this->items = $reply_query->posts;

		// Set the pagination arguments
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
	}
}
endif;
