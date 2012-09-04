<?php

/**
 * bbPress Shortcodes
 *
 * @package bbPress
 * @subpackage Shortcodes
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Shortcodes' ) ) :
/**
 * bbPress Shortcode Class
 *
 * @since bbPress (r3031)
 */
class BBP_Shortcodes {

	/** Vars ******************************************************************/

	/**
	 * @var array Shortcode => function
	 */
	public $codes = array();

	/** Functions *************************************************************/

	/**
	 * Add the register_shortcodes action to bbp_init
	 *
	 * @since bbPress (r3031)
	 *
	 * @uses setup_globals()
	 * @uses add_shortcodes()
	 */
	public function __construct() {
		$this->setup_globals();
		$this->add_shortcodes();
	}

	/**
	 * Shortcode globals
	 *
	 * @since bbPress (r3143)
	 * @access private
	 *
	 * @uses apply_filters()
	 */
	private function setup_globals() {

		// Setup the shortcodes
		$this->codes = apply_filters( 'bbp_shortcodes', array(

			/** Forums ********************************************************/

			// Forum Index
			'bbp-forum-index'  => array( $this, 'display_forum_index' ),

			// Topic form
			'bbp-forum-form'   => array( $this, 'display_forum_form'  ),

			// Specific forum - pass an 'id' attribute
			'bbp-single-forum' => array( $this, 'display_forum'       ),

			/** Topics ********************************************************/

			// Topic index
			'bbp-topic-index'  => array( $this, 'display_topic_index' ),

			// Topic form
			'bbp-topic-form'   => array( $this, 'display_topic_form'  ),

			// Specific topic - pass an 'id' attribute
			'bbp-single-topic' => array( $this, 'display_topic'       ),

			/** Topic Tags ****************************************************/

			// All topic tags in a cloud
			'bbp-topic-tags'       => array( $this, 'display_topic_tags'    ),

			// Topics of tag Tag
			'bbp-single-topic-tag' => array( $this, 'display_topics_of_tag' ),

			/** Replies *******************************************************/

			// Reply form
			'bbp-reply-form'   => array( $this, 'display_reply_form'  ),

			// Specific reply - pass an 'id' attribute
			'bbp-single-reply' => array( $this, 'display_reply'       ),

			/** Views *********************************************************/

			// Single view
			'bbp-single-view' => array( $this, 'display_view'         ),

			/** Account *******************************************************/

			// Login
			'bbp-login'       => array( $this, 'display_login'        ),

			// Register
			'bbp-register'    => array( $this, 'display_register'     ),

			// Lost Password
			'bbp-lost-pass'   => array( $this, 'display_lost_pass'    ),

		) );
	}

	/**
	 * Register the bbPress shortcodes
	 *
	 * @since bbPress (r3031)
	 *
	 * @uses add_shortcode()
	 * @uses do_action()
	 */
	private function add_shortcodes() {

		// Loop through and add the shortcodes
		foreach( $this->codes as $code => $function )
			add_shortcode( $code, $function );

		// Custom shortcodes
		do_action( 'bbp_register_shortcodes' );
	}

	/**
	 * Unset some globals in the $bbp object that hold query related info
	 *
	 * @since bbPress (r3034)
	 */
	private function unset_globals() {
		$bbp = bbpress();

		// Unset global queries
		$bbp->forum_query = new stdClass;
		$bbp->topic_query = new stdClass;
		$bbp->reply_query = new stdClass;

		// Unset global ID's
		$bbp->current_forum_id     = 0;
		$bbp->current_topic_id     = 0;
		$bbp->current_reply_id     = 0;
		$bbp->current_topic_tag_id = 0;

		// Reset the post data
		wp_reset_postdata();
	}

	/** Output Buffers ********************************************************/

	/**
	 * Start an output buffer.
	 *
	 * This is used to put the contents of the shortcode into a variable rather
	 * than outputting the HTML at run-time. This allows shortcodes to appear
	 * in the correct location in the_content() instead of when it's created.
	 *
	 * @since bbPress (r3079)
	 *
	 * @param string $query_name
	 *
	 * @uses bbp_set_query_name()
	 * @uses ob_start()
	 */
	private function start( $query_name = '' ) {

		// Set query name
		bbp_set_query_name( $query_name );

		// Remove 'bbp_replace_the_content' filter to prevent infinite loops
		remove_filter( 'the_content', 'bbp_replace_the_content' );

		// Start output buffer
		ob_start();
	}

	/**
	 * Return the contents of the output buffer and flush its contents.
	 *
	 * @since bbPress( r3079)
	 *
	 * @uses BBP_Shortcodes::unset_globals() Cleans up global values
	 * @return string Contents of output buffer.
	 */
	private function end() {

		// Put output into usable variable
		$output = ob_get_contents();

		// Unset globals
		$this->unset_globals();

		// Flush the output buffer
		ob_end_clean();

		// Reset the query name
		bbp_reset_query_name();

		// Add 'bbp_replace_the_content' filter back (@see $this::start())
		add_filter( 'the_content', 'bbp_replace_the_content' );

		return $output;
	}

	/** Forum shortcodes ******************************************************/

	/**
	 * Display an index of all visible root level forums in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses bbp_has_forums()
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_forum_index() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_forum_archive' );

		bbp_get_template_part( 'content', 'archive-forum' );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display the contents of a specific forum ID in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses get_template_part()
	 * @uses bbp_single_forum_description()
	 * @return string
	 */
	public function display_forum( $attr, $content = '' ) {

		// Sanity check required info
		if ( !empty( $content ) || ( empty( $attr['id'] ) || !is_numeric( $attr['id'] ) ) )
			return $content;

		// Set passed attribute to $forum_id for clarity
		$forum_id = bbpress()->current_forum_id = $attr['id'];

		// Bail if ID passed is not a forum
		if ( !bbp_is_forum( $forum_id ) )
			return $content;

		// Start output buffer
		$this->start( 'bbp_single_forum' );

		// Check forum caps
		if ( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) ) {
			bbp_get_template_part( 'content',  'single-forum' );

		// Forum is private and user does not have caps
		} elseif ( bbp_is_forum_private( $forum_id, false ) ) {
			bbp_get_template_part( 'feedback', 'no-access'    );
		}

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display the forum form in an output buffer and return to ensure
	 * post/page contents are displayed first.
	 *
	 * @since bbPress (r3566)
	 *
	 * @uses get_template_part()
	 */
	public function display_forum_form() {

		// Start output buffer
		$this->start( 'bbp_forum_form' );

		// Output templates
		bbp_get_template_part( 'form', 'forum' );

		// Return contents of output buffer
		return $this->end();
	}

	/** Topic shortcodes ******************************************************/

	/**
	 * Display an index of all visible root level topics in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses bbp_get_hidden_forum_ids()
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_topic_index() {

		// Unset globals
		$this->unset_globals();

		// Filter the query
		if ( ! bbp_is_topic_archive() ) {
			add_filter( 'bbp_before_has_topics_parse_args', array( $this, 'display_topic_index_query' ) );
		}

		// Start output buffer
		$this->start( 'bbp_topic_archive' );

		// Output template
		bbp_get_template_part( 'content', 'archive-topic' );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display the contents of a specific topic ID in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_topic( $attr, $content = '' ) {

		// Sanity check required info
		if ( !empty( $content ) || ( empty( $attr['id'] ) || !is_numeric( $attr['id'] ) ) )
			return $content;

		// Unset globals
		$this->unset_globals();

		// Set passed attribute to $forum_id for clarity
		$topic_id = bbpress()->current_topic_id = $attr['id'];
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Bail if ID passed is not a forum
		if ( !bbp_is_topic( $topic_id ) )
			return $content;

		// Reset the queries if not in theme compat
		if ( !bbp_is_theme_compat_active() ) {

			$bbp = bbpress();

			// Reset necessary forum_query attributes for topics loop to function
			$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
			$bbp->forum_query->in_the_loop             = true;
			$bbp->forum_query->post                    = get_post( $forum_id );

			// Reset necessary topic_query attributes for topics loop to function
			$bbp->topic_query->query_vars['post_type'] = bbp_get_topic_post_type();
			$bbp->topic_query->in_the_loop             = true;
			$bbp->topic_query->post                    = get_post( $topic_id );
		}

		// Start output buffer
		$this->start( 'bbp_single_topic' );

		// Check forum caps
		if ( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) ) {
			bbp_get_template_part( 'content', 'single-topic' );

		// Forum is private and user does not have caps
		} elseif ( bbp_is_forum_private( $forum_id, false ) ) {
			bbp_get_template_part( 'feedback', 'no-access'    );
		}

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display the topic form in an output buffer and return to ensure
	 * post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @uses get_template_part()
	 */
	public function display_topic_form() {

		// Start output buffer
		$this->start( 'bbp_topic_form' );

		// Output templates
		bbp_get_template_part( 'form', 'topic' );

		// Return contents of output buffer
		return $this->end();
	}

	/** Replies ***************************************************************/

	/**
	 * Display the contents of a specific reply ID in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_reply( $attr, $content = '' ) {

		// Sanity check required info
		if ( !empty( $content ) || ( empty( $attr['id'] ) || !is_numeric( $attr['id'] ) ) )
			return $content;

		// Unset globals
		$this->unset_globals();

		// Set passed attribute to $reply_id for clarity
		$reply_id = bbpress()->current_reply_id = $attr['id'];
		$forum_id = bbp_get_reply_forum_id( $reply_id );

		// Bail if ID passed is not a forum
		if ( !bbp_is_reply( $reply_id ) )
			return $content;

		// Reset the queries if not in theme compat
		if ( !bbp_is_theme_compat_active() ) {

			$bbp = bbpress();

			// Reset necessary forum_query attributes for replys loop to function
			$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
			$bbp->forum_query->in_the_loop             = true;
			$bbp->forum_query->post                    = get_post( $forum_id );

			// Reset necessary reply_query attributes for replys loop to function
			$bbp->reply_query->query_vars['post_type'] = bbp_get_reply_post_type();
			$bbp->reply_query->in_the_loop             = true;
			$bbp->reply_query->post                    = get_post( $reply_id );
		}

		// Start output buffer
		$this->start( 'bbp_single_reply' );

		// Check forum caps
		if ( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) ) {
			bbp_get_template_part( 'content',  'single-reply' );

		// Forum is private and user does not have caps
		} elseif ( bbp_is_forum_private( $forum_id, false ) ) {
			bbp_get_template_part( 'feedback', 'no-access'    );
		}

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display the reply form in an output buffer and return to ensure
	 * post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @uses get_template_part()
	 */
	public function display_reply_form() {

		// Start output buffer
		$this->start( 'bbp_reply_form' );

		// Output templates
		bbp_get_template_part( 'form', 'reply' );

		// Return contents of output buffer
		return $this->end();
	}

	/** Topic Tags ************************************************************/

	/**
	 * Display a tag cloud of all topic tags in an output buffer and return to
	 * ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3110)
	 *
	 * @return string
	 */
	public function display_topic_tags() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_topic_tags' );

		// Output the topic tags
		wp_tag_cloud( array(
			'smallest' => 9,
			'largest'  => 38,
			'number'   => 80,
			'taxonomy' => bbp_get_topic_tag_tax_id()
		) );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display the contents of a specific topic tag in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3110)
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_topics_of_tag( $attr, $content = '' ) {

		// Sanity check required info
		if ( !empty( $content ) || ( empty( $attr['id'] ) || !is_numeric( $attr['id'] ) ) )
			return $content;

		// Unset globals
		$this->unset_globals();

		// Filter the query
		if ( ! bbp_is_topic_tag() ) {
			add_filter( 'bbp_before_has_topics_parse_args', array( $this, 'display_topics_of_tag_query' ) );
		}

		// Start output buffer
		$this->start( 'bbp_topic_tag' );

		// Set passed attribute to $ag_id for clarity
		bbpress()->current_topic_tag_id = $tag_id = $attr['id'];

		// Output template
		bbp_get_template_part( 'content', 'archive-topic' );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display the contents of a specific topic tag in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3346)
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_topic_tag_form() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_topic_tag_edit' );

		// Output template
		bbp_get_template_part( 'content', 'topic-tag-edit' );

		// Return contents of output buffer
		return $this->end();
	}

	/** Views *****************************************************************/

	/**
	 * Display the contents of a specific view in an output buffer and return to
	 * ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses get_template_part()
	 * @uses bbp_single_forum_description()
	 * @return string
	 */
	public function display_view( $attr, $content = '' ) {

		// Sanity check required info
		if ( empty( $attr['id'] ) )
			return $content;

		// Set passed attribute to $view_id for clarity
		$view_id = $attr['id'];

		// Start output buffer
		$this->start( 'bbp_single_view' );

		// Unset globals
		$this->unset_globals();

		// Load the view
		bbp_view_query( $view_id );

		// Output template
		bbp_get_template_part( 'content', 'single-view' );

		// Return contents of output buffer
		return $this->end();
	}

	/** Account ***************************************************************/

	/**
	 * Display a login form
	 *
	 * @since bbPress (r3302)
	 *
	 * @return string
	 */
	public function display_login() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_login' );

		// Output templates
		if ( !is_user_logged_in() )
			bbp_get_template_part( 'form',     'user-login' );
		else
			bbp_get_template_part( 'feedback', 'logged-in'  );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display a register form
	 *
	 * @since bbPress (r3302)
	 *
	 * @return string
	 */
	public function display_register() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_register' );

		// Output templates
		if ( !is_user_logged_in() )
			bbp_get_template_part( 'form',     'user-register' );
		else
			bbp_get_template_part( 'feedback', 'logged-in'     );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display a lost password form
	 *
	 * @since bbPress (r3302)
	 *
	 * @return string
	 */
	public function display_lost_pass() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_lost_pass' );

		// Output templates
		if ( !is_user_logged_in() )
			bbp_get_template_part( 'form',     'user-lost-pass' );
		else
			bbp_get_template_part( 'feedback', 'logged-in'      );
	
		// Return contents of output buffer
		return $this->end();
	}

	/** Other *****************************************************************/

	/**
	 * Display a breadcrumb
	 *
	 * @since bbPress (r3302)
	 *
	 * @return string
	 */
	public function display_breadcrumb() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start();

		// Output breadcrumb
		bbp_breadcrumb();

		// Return contents of output buffer
		return $this->end();
	}

	/** Query Filters *********************************************************/

	/**
	 * Filter the query for the topic index
	 *
	 * @since bbPress (r3637)
	 *
	 * @param array $args
	 * @return array
	 */
	public function display_topic_index_query( $args = array() ) {
		$args['author']        = 0;
		$args['show_stickies'] = true;
		$args['order']         = 'DESC';
		return $args;
	}

	/**
	 * Filter the query for topic tags
	 *
	 * @since bbPress (r3637)
	 *
	 * @param array $args
	 * @return array
	 */
	public function display_topics_of_tag_query( $args = array() ) {
		$args['tax_query'] = array( array(
			'taxonomy' => bbp_get_topic_tag_tax_id(),
			'field'    => 'id',
			'terms'    => bbpress()->current_topic_tag_id
		) );

		return $args;
	}
}
endif;

/**
 * Register the bbPress shortcodes
 *
 * @since bbPress (r3031)
 *
 * @uses BBP_Shortcodes
 */
function bbp_register_shortcodes() {
	bbpress()->shortcodes = new BBP_Shortcodes();
}
