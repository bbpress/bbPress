<?php

/**
 * bbPress Shortcodes
 *
 * @package bbPress
 * @subpackage Shortcodes
 */

// Redirect if accessed directly
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
	var $codes;

	/** Functions *************************************************************/

	/**
	 * Add the register_shortcodes action to bbp_init
	 *
	 * @since bbPress (r3031)
	 *
	 * @uses __construct()
	 */
	function BBP_Shortcodes() {
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
	function __construct() {
		$this->_setup_globals();
		$this->_add_shortcodes();
	}

	/**
	 * Shortcode globals
	 *
	 * @since bbPress (r3143)
	 * @access private
	 *
	 * @uses apply_filters()
	 */
	function _setup_globals() {

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
			'bbp-topic-tags' => array( $this, 'display_topic_tags'    ),

			// Topics of tag Tag
			'bbp-topic-tag'  => array( $this, 'display_topics_of_tag' ),

			/** Replies *******************************************************/

			// Reply form
			'bbp-reply-form' => array( $this, 'display_reply_form'    )
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
	function _add_shortcodes() {

		// Loop through the shortcodes
		foreach( $this->codes as $code => $function )

			// Add each shortcode
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
	function _unset_globals() {
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
	 * @uses ob_start()
	 */
	function _ob_start() {
		ob_start();
	}

	/**
	 * Return the contents of the output buffer and flush its contents.
	 *
	 * @since bbPress( r3079)
	 *
	 * @uses BBP_Shortcodes::_unset_globals() Cleans up global values
	 * @return string Contents of output buffer.
	 */
	function _ob_end() {

		// Put output into usable variable
		$output = ob_get_contents();

		// Unset globals
		$this->_unset_globals();

		// Flush the output buffer
		ob_end_clean();

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
	function display_forum_index() {

		// Start output buffer
		$this->_ob_start();

		// Load the forums index
		if ( bbp_has_forums() )
			bbp_get_template_part( 'bbpress/loop', 'forums' );

		// No forums
		else
			bbp_get_template_part( 'bbpress/no', 'forums' );

		// Return contents of output buffer
		return $this->_ob_end();
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
	function display_forum( $attr, $content = '' ) {
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
		$this->_ob_start();

		// Display breadcrumb if a subforum
		bbp_get_template_part( 'bbpress/nav', 'breadcrumb' );

		// Password protected
		if ( post_password_required() ) {

			// Output the password form
			bbp_get_template_part( 'bbpress/form', 'protected' );

		// Not password protected, or password is already approved
		} else {

			// Check forum caps
			if ( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) ) {

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
					$this->_unset_globals();

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
						bbp_get_template_part( 'bbpress/pagination', 'topics' );
						bbp_get_template_part( 'bbpress/loop',       'topics' );
						bbp_get_template_part( 'bbpress/pagination', 'topics' );
						bbp_get_template_part( 'bbpress/form',       'topic'  );

					// No topics
					} else {
						bbp_get_template_part( 'bbpress/no',   'topics' );
						bbp_get_template_part( 'bbpress/form', 'topic'  );
					}
				}

			// Forum is private and user does not have caps
			} elseif ( bbp_is_forum_private( $forum_id, false ) ) {
				bbp_get_template_part( 'bbpress/no', 'access' );
			}
		}

		// Return contents of output buffer
		return $this->_ob_end();
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
	function display_topic_index() {

		// Query defaults
		$topics_query = array(
			'author'         => 0,
			'show_stickies'  => true,
			'order'          => 'DESC',
		);

		// Remove any topics from hidden forums
		$topics_query = bbp_exclude_forum_ids( $topics_query );

		// Unset globals
		$this->_unset_globals();

		// Start output buffer
		ob_start();

		// Load the topic index
		if ( bbp_has_topics( $topics_query ) ) {
			bbp_get_template_part( 'bbpress/pagination', 'topics' );
			bbp_get_template_part( 'bbpress/loop',       'topics' );
			bbp_get_template_part( 'bbpress/pagination', 'topics' );

		// No topics
		} else {
			bbp_get_template_part( 'bbpress/no', 'topics' );
		}

		// Return contents of output buffer
		return $this->_ob_end();
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
	function display_topic( $attr, $content = '' ) {
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
		$this->_unset_globals();

		// Reset necessary forum_query attributes for topics loop to function
		$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
		$bbp->forum_query->in_the_loop             = true;
		$bbp->forum_query->post                    = get_post( $forum_id );

		// Reset necessary topic_query attributes for topics loop to function
		$bbp->topic_query->query_vars['post_type'] = bbp_get_topic_post_type();
		$bbp->topic_query->in_the_loop             = true;
		$bbp->topic_query->post                    = get_post( $topic_id );

		// Start output buffer
		$this->_ob_start();

		// Breadcrumb
		bbp_get_template_part( 'bbpress/nav', 'breadcrumb' );

		// Password protected
		if ( post_password_required() ) {

			// Output the password form
			bbp_get_template_part( 'bbpress/form', 'protected' );

		// Not password protected, or password is already approved
		} else {

			// Check forum caps
			if ( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) ) {

				// Load the topic
				if ( bbp_has_replies( $replies_query ) ) {

					// Tags
					bbp_topic_tag_list( $topic_id );

					// Topic description
					bbp_single_topic_description( array( 'topic_id' => $topic_id ) );

					// Template files
					bbp_get_template_part( 'bbpress/single',     'topic'   );
					bbp_get_template_part( 'bbpress/pagination', 'replies' );
					bbp_get_template_part( 'bbpress/loop',       'replies' );
					bbp_get_template_part( 'bbpress/pagination', 'replies' );
					bbp_get_template_part( 'bbpress/form',       'reply'   );

				// No replies
				} else {
					bbp_get_template_part( 'bbpress/single', 'topic' );
					bbp_get_template_part( 'bbpress/form',   'reply' );
				}

			// Forum is private and user does not have caps
			} elseif ( bbp_is_forum_private( $forum_id, false ) ) {
				bbp_get_template_part( 'bbpress/no', 'access' );
			}
		}

		// Return contents of output buffer
		return $this->_ob_end();
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
	function display_topic_form() {

		// Start output buffer
		$this->_ob_start();

		// Output templates
		bbp_get_template_part( 'bbpress/form', 'topic'  );

		// Return contents of output buffer
		return $this->_ob_end();
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
	function display_reply_form() {

		// Start output buffer
		$this->_ob_start();

		// Output templates
		bbp_get_template_part( 'bbpress/form', 'reply'  );

		// Return contents of output buffer
		return $this->_ob_end();
	}

	/** Topic Tags ************************************************************/

	/**
	 * Display a tag cloud of all topic tags in an output buffer and return to
	 * ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3110)
	 *
	 * @global bbPress $bbp
	 *
	 * @return string
	 */
	function display_topic_tags() {
		global $bbp;

		// Unset globals
		$this->_unset_globals();

		// Start output buffer
		$this->_ob_start();

		// Output the topic tags
		wp_tag_cloud( array(
			'smallest' => 9,
			'largest'  => 38,
			'number'   => 80,
			'taxonomy' => $bbp->topic_tag_id
		) );

		// Return contents of output buffer
		return $this->_ob_end();
	}

	/**
	 * Display the contents of a specific topic tag in an output buffer
	 * and return to ensure that post/page contents are displayed first.
	 *
	 * @since bbPress (r3110)
	 *
	 * @global bbPress $bbp
	 *
	 * @param array $attr
	 * @param string $content
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 * @return string
	 */
	function display_topics_of_tag( $attr, $content = '' ) {
		global $bbp;

		// Sanity check required info
		if ( !empty( $content ) || ( empty( $attr['id'] ) || !is_numeric( $attr['id'] ) ) )
			return $content;

		// Set passed attribute to $ag_id for clarity
		$tag_id = $attr['id'];

		// Setup tax query
		$args = array( 'tax_query' => array( array(
			'taxonomy' => $bbp->topic_tag_id,
			'field'    => 'id',
			'terms'    => $tag_id
		) ) );

		// Unset globals
		$this->_unset_globals();

		// Start output buffer
		$this->_ob_start();

		// Tag description
		bbp_topic_tag_description();

		// Load the topics
		if ( bbp_has_topics( $args ) ) {

			// Template files
			bbp_get_template_part( 'bbpress/pagination', 'topics'    );
			bbp_get_template_part( 'bbpress/loop',       'topics'    );
			bbp_get_template_part( 'bbpress/pagination', 'topics'    );
			bbp_get_template_part( 'bbpress/form',       'topic-tag' );

		// No topics
		} else {
			bbp_get_template_part( 'bbpress/no', 'topics' );
		}

		// Return contents of output buffer
		return $this->_ob_end();
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