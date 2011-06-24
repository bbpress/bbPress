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
	 * @uses __construct()
	 */
	public function BBP_Shortcodes() {
		$this->__construct();
	}

	/**
	 * Add the register_shortcodes action to bbp_init
	 *
	 * @since bbPress (r3031)
	 *
	 * @uses _setup_globals()
	 * @uses _add_shortcodes()
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
			'bbp-reply-form'  => array( $this, 'display_reply_form'   ),

			/** Views *********************************************************/

			// Single view
			'bbp-single-view' => array( $this, 'display_view'         ),

			/** Account *******************************************************/

			// Login
			'bbp-login'       => array( $this, 'display_login'        ),

			// Register
			'bbp-register'    => array( $this, 'display_register'     ),

			// LOst Password
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
	 *
	 * @global bbPress $bbp
	 */
	private function unset_globals() {
		global $bbp;

		// Unset global queries
		$bbp->forum_query      = null;
		$bbp->topic_query      = null;
		$bbp->reply_query      = null;

		// Unset global ID's
		$bbp->current_forum_id = null;
		$bbp->current_topic_id = null;
		$bbp->current_reply_id = null;

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
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_forum_index() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_forum_archive' );

		// Breadcrumb
		bbp_breadcrumb();

		// Before forums index
		do_action( 'bbp_template_before_forums_index' );

		// Load the forums index
		if ( bbp_has_forums() )
			bbp_get_template_part( 'bbpress/loop',     'forums'    );

		// No forums
		else
			bbp_get_template_part( 'bbpress/feedback', 'no-forums' );

		// After forums index
		do_action( 'bbp_template_after_forums_index' );

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
	 * @uses bbp_has_topics()
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 * @uses bbp_single_forum_description()
	 * @return string
	 */
	public function display_forum( $attr, $content = '' ) {
		global $bbp;

		// Sanity check required info
		if ( !empty( $content ) || ( empty( $attr['id'] ) || !is_numeric( $attr['id'] ) ) )
			return $content;

		// Set passed attribute to $forum_id for clarity
		$forum_id = $attr['id'];

		// Bail if ID passed is not a forum
		if ( !bbp_is_forum( $forum_id ) )
			return $content;

		// Start output buffer
		$this->start( 'bbp_single_forum' );

		// Check forum caps
		if ( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) ) {

			// Breadcrumb
			bbp_breadcrumb();

			// Before single forum
			do_action( 'bbp_template_before_single_forum' );

			// Password protected
			if ( post_password_required() ) {

				// Output the password form
				bbp_get_template_part( 'bbpress/form', 'protected' );

			// Not password protected, or password is already approved
			} else {

				// Forum description
				bbp_single_forum_description( array( 'forum_id' => $forum_id ) );

				/** Sub forums ****************************************************/

				// Check if forum has subforums first
				if ( bbp_get_forum_subforum_count( $forum_id ) ) {

					// Forum query
					$forum_query = array( 'post_parent' => $forum_id );

					// Load the sub forums
					if ( bbp_has_forums( $forum_query ) )
						bbp_get_template_part( 'bbpress/loop', 'forums' );
				}

				/** Topics ********************************************************/

				// Skip if forum is a category
				if ( !bbp_is_forum_category( $forum_id ) ) {

					// Unset globals
					$this->unset_globals();

					// Reset necessary forum_query attributes for topics loop to function
					$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
					$bbp->forum_query->in_the_loop             = true;
					$bbp->forum_query->post                    = get_post( $forum_id );

					// Query defaults
					$topics_query = array(
						'author'        => 0,
						'post_parent'   => $forum_id,
						'show_stickies' => true,
					);

					// Load the topic index
					if ( bbp_has_topics( $topics_query ) ) {
						bbp_get_template_part( 'bbpress/pagination', 'topics'    );
						bbp_get_template_part( 'bbpress/loop',       'topics'    );
						bbp_get_template_part( 'bbpress/pagination', 'topics'    );
						bbp_get_template_part( 'bbpress/form',       'topic'     );

					// No topics
					} else {
						bbp_get_template_part( 'bbpress/feedback',   'no-topics' );
						bbp_get_template_part( 'bbpress/form',       'topic'     );
					}
				}

				// After single forum
				do_action( 'bbp_template_after_single_forum' );
			}

		// Forum is private and user does not have caps
		} elseif ( bbp_is_forum_private( $forum_id, false ) ) {
			bbp_get_template_part( 'bbpress/feedback', 'no-access' );
		}

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
	 * @uses bbp_has_topics()
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_topic_index() {

		// Query defaults
		$topics_query = array(
			'author'         => 0,
			'show_stickies'  => true,
			'order'          => 'DESC',
		);

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_topic_archive' );

		// Breadcrumb
		bbp_breadcrumb();

		// Before topics index
		do_action( 'bbp_template_before_topics_index' );

		// Load the topic index
		if ( bbp_has_topics( $topics_query ) ) {
			bbp_get_template_part( 'bbpress/pagination', 'topics'    );
			bbp_get_template_part( 'bbpress/loop',       'topics'    );
			bbp_get_template_part( 'bbpress/pagination', 'topics'    );

		// No topics
		} else {
			bbp_get_template_part( 'bbpress/feedback',   'no-topics' );
		}

		// After topics index
		do_action( 'bbp_template_after_topics_index' );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display the contents of a specific topic ID in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @global bbPress $bbp
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_topic( $attr, $content = '' ) {
		global $bbp;

		// Sanity check required info
		if ( !empty( $content ) || ( empty( $attr['id'] ) || !is_numeric( $attr['id'] ) ) )
			return $content;

		// Set passed attribute to $forum_id for clarity
		$topic_id = $attr['id'];
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Bail if ID passed is not a forum
		if ( !bbp_is_topic( $topic_id ) )
			return $content;

		// Setup the meta_query
		$replies_query['meta_query'] = array( array(
			'key'     => '_bbp_topic_id',
			'value'   => $topic_id,
			'compare' => '='
		) );

		// Unset globals
		$this->unset_globals();

		// Reset the queries if not in theme compat
		if ( !bbp_is_theme_compat_active() ) {

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

			// Breadcrumb
			bbp_breadcrumb();

			// Before single topic
			do_action( 'bbp_template_before_single_topic' );

			// Password protected
			if ( post_password_required() ) {

				// Output the password form
				bbp_get_template_part( 'bbpress/form', 'protected' );

			// Not password protected, or password is already approved
			} else {

				// Tags
				bbp_topic_tag_list( $topic_id );

				// Topic description
				bbp_single_topic_description( array( 'topic_id' => $topic_id ) );

				// Template files
				if ( bbp_show_lead_topic() )
					bbp_get_template_part( 'bbpress/content', 'single-topic-lead' );

				// Load the topic
				if ( bbp_has_replies( $replies_query ) ) {
					bbp_get_template_part( 'bbpress/pagination', 'replies' );
					bbp_get_template_part( 'bbpress/loop',       'replies' );
					bbp_get_template_part( 'bbpress/pagination', 'replies' );
				}

				// Reply form
				bbp_get_template_part( 'bbpress/form', 'reply' );
			}

			// After single topic
			do_action( 'bbp_template_after_single_topic' );

		// Forum is private and user does not have caps
		} elseif ( bbp_is_forum_private( $forum_id, false ) ) {
			bbp_get_template_part( 'bbpress/feedback', 'no-access' );
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
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 */
	public function display_topic_form() {

		// Start output buffer
		$this->start( 'bbp_topic_form' );

		// Output templates
		bbp_get_template_part( 'bbpress/form', 'topic' );

		// Return contents of output buffer
		return $this->end();
	}

	/** Replies ***************************************************************/

	/**
	 * Display the reply form in an output buffer and return to ensure
	 * post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 */
	public function display_reply_form() {

		// Start output buffer
		$this->start( 'bbp_reply_form' );

		// Output templates
		bbp_get_template_part( 'bbpress/form', 'reply' );

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
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_topics_of_tag( $attr, $content = '' ) {

		// Sanity check required info
		if ( !empty( $content ) || ( empty( $attr['id'] ) || !is_numeric( $attr['id'] ) ) )
			return $content;

		// Set passed attribute to $ag_id for clarity
		$tag_id = $attr['id'];

		// Setup tax query
		$args = array( 'tax_query' => array( array(
			'taxonomy' => bbp_get_topic_tag_tax_id(),
			'field'    => 'id',
			'terms'    => $tag_id
		) ) );

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_topics_of_tag' );

		// Breadcrumb
		bbp_breadcrumb();

		// Tag description
		bbp_topic_tag_description();

		// Before tag topics
		do_action( 'bbp_template_before_topic_tag' );

		// Load the topics
		if ( bbp_has_topics( $args ) ) {
			bbp_get_template_part( 'bbpress/pagination', 'topics'    );
			bbp_get_template_part( 'bbpress/loop',       'topics'    );
			bbp_get_template_part( 'bbpress/pagination', 'topics'    );

		// No topics
		} else {
			bbp_get_template_part( 'bbpress/feedback',   'no-topics' );
		}

		// After tag topics
		do_action( 'bbp_template_after_topic_tag' );

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
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 * @return string
	 */
	public function display_topic_tag_form() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_topic_tag_edit' );

		// Breadcrumb
		bbp_breadcrumb();

		// Tag description
		bbp_topic_tag_description();

		// Before tag topics
		do_action( 'bbp_template_before_topic_tag_edit' );

		// Tag editing form
		bbp_get_template_part( 'bbpress/form', 'topic-tag' );

		// After tag topics
		do_action( 'bbp_template_after_topic_tag_edit' );

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
	 * @uses bbp_has_topics()
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 * @uses bbp_single_forum_description()
	 * @return string
	 */
	public function display_view( $attr, $content = '' ) {
		global $bbp;

		// Sanity check required info
		if ( empty( $attr['id'] ) )
			return $content;

		// Set passed attribute to $view_id for clarity
		$view_id = $attr['id'];

		// Start output buffer
		$this->start( 'bbp_single_view' );

		// Breadcrumb
		bbp_breadcrumb();

		// Password protected
		if ( post_password_required() ) {

			// Output the password form
			bbp_get_template_part( 'bbpress/form', 'protected' );

		// Not password protected, or password is already approved
		} else {

			/** Topics ********************************************************/

			// Unset globals
			$this->unset_globals();

			// Load the topic index
			if ( bbp_view_query( $view_id ) ) {
				bbp_get_template_part( 'bbpress/pagination', 'topics'    );
				bbp_get_template_part( 'bbpress/loop',       'topics'    );
				bbp_get_template_part( 'bbpress/pagination', 'topics'    );

			// No topics
			} else {
				bbp_get_template_part( 'bbpress/feedback',   'no-topics' );
			}
		}

		// Return contents of output buffer
		return $this->end();
	}

	/** Account ***************************************************************/

	/**
	 * Display a login form
	 *
	 * @since bbPress (r3302)
	 *
	 * @global bbPress $bbp
	 *
	 * @return string
	 */
	public function display_login() {
		global $bbp;

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_login' );

		// Output templates
		if ( !is_user_logged_in() )
			bbp_get_template_part( 'bbpress/form',     'user-login' );
		else
			bbp_get_template_part( 'bbpress/feedback', 'logged-in'  );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display a register form
	 *
	 * @since bbPress (r3302)
	 *
	 * @global bbPress $bbp
	 *
	 * @return string
	 */
	public function display_register() {
		global $bbp;

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_register' );

		// Output templates
		if ( !is_user_logged_in() )
			bbp_get_template_part( 'bbpress/form',     'user-register' );
		else
			bbp_get_template_part( 'bbpress/feedback', 'logged-in'     );

		// Return contents of output buffer
		return $this->end();
	}

	/**
	 * Display a lost password form
	 *
	 * @since bbPress (r3302)
	 *
	 * @global bbPress $bbp
	 *
	 * @return string
	 */
	public function display_lost_pass() {
		global $bbp;

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->start( 'bbp_lost_pass' );

		// Output templates
		if ( !is_user_logged_in() )
			bbp_get_template_part( 'bbpress/form',     'user-lost-pass' );
		else
			bbp_get_template_part( 'bbpress/feedback', 'logged-in'      );
	
		// Return contents of output buffer
		return $this->end();
	}

	/** Other *****************************************************************/

	/**
	 * Display a breadcrumb
	 *
	 * @since bbPress (r3302)
	 *
	 * @global bbPress $bbp
	 *
	 * @return string
	 */
	public function display_breadcrumb() {

		// Unset globals
		$this->unset_globals();

		// Start output buffer
		$this->ob_start();

		// Output breadcrumb
		bbp_breadcrumb();

		// Return contents of output buffer
		return $this->end();
	}
}
endif;

/**
 * Register the bbPress shortcodes
 *
 * @since bbPress (r3031)
 *
 * @global bbPress $bbp
 * @uses BBP_Shortcodes
 */
function bbp_register_shortcodes() {
	global $bbp;

	$bbp->shortcodes = new BBP_Shortcodes();
}

?>