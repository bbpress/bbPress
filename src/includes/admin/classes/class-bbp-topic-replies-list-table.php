<?php

/**
 * Topic Replies List Table class.
 *
 * @package    bbPress
 * @subpackage Administration
 * @since      2.6.0 bbPress (r5886)
 * @access     private
 *
 * @see WP_Posts_List_Table
 */

// Include the main list table class if it's not included yet
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( class_exists( 'WP_List_Table' ) ) :
/**
 * Topic replies list table.
 *
 * This list table is responsible for showing the replies to a topic in a
 * meta-box, similar to comments in posts and pages.
 *
 * @since 2.6.0 bbPress (r5886)
 */
class BBP_Topic_Replies_List_Table extends WP_List_Table {

	/**
	 * The main constructor method.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @param array<string, mixed> $args Optional list table arguments.
	 */
	public function __construct( $args = array() ) {

		// Parse arguments
		$args = bbp_parse_args(
			$args,
			array(
				'singular' => 'reply',
				'plural'   => 'replies',
				'ajax'     => false
			),
			'topic_replies_list_table'
		);

		// Construct the list table
		parent::__construct( $args );
	}

	/**
	 * Setup the list-table columns.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @see WP_List_Table::single_row_columns()
	 *
	 * @return array<string, string> An associative array containing column information.
	 */
	public function get_columns() {
		return array(
			//'cb'                 => '<input type="checkbox" />',
			'bbp_topic_reply_author' => esc_html__( 'Author',  'bbpress' ),
			'bbp_reply_content'      => esc_html__( 'Content', 'bbpress' ),
			'bbp_reply_created'      => esc_html__( 'Replied', 'bbpress' ),
		);
	}

	/**
	 * Allow `bbp_reply_created` to be sortable.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @return array<string, array{string, bool}> An associative array containing the `bbp_reply_created` column.
	 */
	public function get_sortable_columns() {
		return array(
			'bbp_reply_created' => array( 'bbp_reply_created', false )
		);
	}

	/**
	 * Setup the bulk actions.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @return array<string, string> An associative array containing all the bulk actions.
	 */
	public function get_bulk_actions() {

		// Default bulk actions
		$retval = array(
			'unapprove' => esc_html__( 'Unapprove', 'bbpress' ),
			'spam'      => esc_html__( 'Spam',      'bbpress' ),
			'trash'     => esc_html__( 'Trash',     'bbpress' )
		);

		// Override to empty
		$retval = array();

		// Return
		return $retval;
	}

	/**
	 * Return the checkbox column for bulk actions.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @param WP_Post|string $item Reply post, or an empty string when omitted.
	 * @return string Bulk action checkbox HTML.
	 */
	public function column_cb( $item = '' ) {
		if ( ! ( $item instanceof WP_Post ) ) {
			return '';
		}

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item->ID
		);
	}

	/**
	 * Output the contents of the `bbp_topic_reply_author` column.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @param WP_Post|string $item Reply post, or an empty string when omitted.
	 * @return void
	 */
	public function column_bbp_topic_reply_author( $item = '' ) {
		if ( ! ( $item instanceof WP_Post ) ) {
			return;
		}

		bbp_reply_author_avatar( $item->ID, 50 );
		echo esc_html( bbp_get_reply_author_display_name( $item->ID ) );
		echo '<br>';
		echo esc_html( bbp_get_reply_author_email( $item->ID ) );
		echo '<br>';
		bbp_author_ip( array( 'post_id' => $item->ID ) );
	}

	/**
	 * Return the contents of the `bbp_reply_created` column.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @param WP_Post|string $item Reply post, or an empty string when omitted.
	 * @return string Reply creation date and time HTML.
	 */
	public function column_bbp_reply_created( $item = '' ) {
		if ( ! ( $item instanceof WP_Post ) ) {
			return '';
		}

		return sprintf( '%1$s <br /> %2$s',
			esc_attr( get_the_date( '', $item ) ),
			esc_attr( get_the_time( '', $item ) )
		);
	}

	/**
	 * Return the contents of the `bbp_reply_content` column.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @param WP_Post|string $item Reply post, or an empty string when omitted.
	 * @return string Reply content and action links.
	 */
	public function column_bbp_reply_content( $item = '' ) {
		if ( ! ( $item instanceof WP_Post ) ) {
			return '';
		}

		// Define actions array
		$actions = array(
			'view' => '<a href="' . esc_url( bbp_get_reply_url( $item->ID ) ) . '">' . esc_html__( 'View', 'bbpress' ) . '</a>'
		);

		// Prepend `edit` link
		if ( current_user_can( 'edit_reply', $item->ID ) ) {
			$edit_url = get_edit_post_link( $item->ID );
			if ( is_string( $edit_url ) && ( '' !== $edit_url ) ) {
				$actions['edit'] = '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'bbpress' ) . '</a>';
				$actions         = array_reverse( $actions );
			}
		}

		// Filter the reply content
		$reply_content = apply_filters( 'bbp_get_reply_content', $item->post_content, $item->ID );
		$reply_actions = $this->row_actions( $actions );

		// Return content & actions
		return $reply_content . $reply_actions;
	}

	/**
	 * Handle bulk action requests.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @return void
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
	 * Prepare the list-table items for display.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 *
	 * @param int $topic_id Topic whose replies are listed.
	 * @return void
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
		$orderby      = ! empty( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'date';
		$order        = ! empty( $_REQUEST['order']   ) ? sanitize_key( $_REQUEST['order']   ) : 'asc';
		$statuses     = bbp_get_public_reply_statuses();

		// Maybe add private statuses to query
		if ( current_user_can( 'edit_others_replies' ) ) {

			// Default view=all statuses
			$statuses = array_keys( bbp_get_topic_statuses() );

			// Add support for private status
			if ( current_user_can( 'read_private_replies' ) ) {
				$statuses[] = bbp_get_private_status_id();
			}
		}

		// Query for replies
		$reply_query  = new WP_Query(
			array(
				'post_type'           => bbp_get_reply_post_type(),
				'post_status'         => $statuses,
				'post_parent'         => $topic_id,
				'posts_per_page'      => $per_page,
				'paged'               => $current_page,
				'orderby'             => $orderby,
				'order'               => ucwords( $order ),
				'hierarchical'        => false,
				'ignore_sticky_posts' => true
			)
		);

		// Set list table items to queried posts
		$this->items = is_array( $reply_query->posts ) ? $reply_query->posts : array();

		// Set the pagination arguments
		$this->set_pagination_args(
			array(
				'total_items' => (int) $reply_query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => (int) $reply_query->max_num_pages
			)
		);
	}

	/**
	 * Message to be displayed when there are no items.
	 *
	 * @since 2.6.0 bbPress (r5930)
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No replies to this topic.', 'bbpress' );
	}

	/**
	 * Display the list table.
	 *
	 * This custom method is necessary because the one in `WP_List_Table` comes
	 * with a nonce and check that we do not need.
	 *
	 * @since 2.6.0 bbPress (r5930)
	 *
	 * @return void
	 */
	public function display() {

		// Top
		$this->display_tablenav( 'top' ); ?>

		<table id="bbp-reply-list" class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
			<thead>
				<tr>
					<?php $this->print_column_headers(); ?>
				</tr>
			</thead>

			<tbody data-wp-lists='list:<?php echo $this->_args['singular']; ?>'>
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>

			<tfoot>
				<tr>
					<?php $this->print_column_headers( false ); ?>
				</tr>
			</tfoot>
		</table>

		<?php

		// Bottom
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Generate the table navigation above or below the table.
	 *
	 * This custom method is necessary because the one in `WP_List_Table` comes
	 * with a nonce and check that we do not need.
	 *
	 * @since 2.6.0 bbPress (r5930)
	 *
	 * @param 'top'|'bottom' $which Whether to display top or bottom navigation.
	 */
	protected function display_tablenav( $which ) {
		?>

		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php
				$this->extra_tablenav( $which );
				$this->pagination( $which );
			?>
			<br class="clear" />
		</div>

		<?php
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @since 2.6.0 bbPress (r6546)
	 * @access public
	 *
	 * @param object $item The current item.
	 * @return void
	 */
	public function single_row( $item ) {
		if ( ! ( $item instanceof WP_Post ) ) {
			return;
		}

		// Author
		$classes = 'author-' . ( get_current_user_id() === (int) $item->post_author
			? 'self'
			: 'other' );

		// Locked
		if ( wp_check_post_lock( $item->ID ) ) {
			$classes .= ' wp-locked';
		}

		// Hierarchy
		if ( ! empty( $item->post_parent ) ) {
			$count    = count( get_post_ancestors( $item->ID ) );
			$classes .= ' level-'. $count;
		} else {
			$classes .= ' level-0';
		} ?>

		<tr id="post-<?php echo esc_attr( $item->ID ); ?>" class="<?php echo implode( ' ', get_post_class( $classes, $item->ID ) ); ?>">
			<?php $this->single_row_columns( $item ); ?>
		</tr>

		<?php
	}
}
endif;
