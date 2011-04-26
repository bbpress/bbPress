<?php

/**
 * bbPress Shortcodes
 *
 * @package bbPress
 * @subpackage Shortcodes
 */

if ( !class_exists( 'BBP_Shortcodes' ) ) :
/**
 * bbPress Shortcode Class
 *
 * @since bbPress (r3031)
 */
class BBP_Shortcodes {

	/**
	 * Add the register_shortcodes action to bbp_init
	 *
	 * @since bbPress (r3031)
	 *
	 * @uses add_action
	 */
	function BBP_Shortcodes() {
		$this->_add_shortcodes();
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

		/** Forums ************************************************************/

		// Forum Index
		add_shortcode( 'bbp-forum-index', array( $this, 'display_forum_index' ) );

		// Specific forum - pass an 'id' attribute
		add_shortcode( 'bbp-forum',       array( $this, 'display_forum'       ) );

		/** Topics ************************************************************/

		// Topic index
		add_shortcode( 'bbp-topic-index', array( $this, 'display_topic_index'  ) );

		// Topic form
		add_shortcode( 'bbp-topic-form',  array( $this, 'display_topic_form'   ) );

		// Specific topic - pass an 'id' attribute
		add_shortcode( 'bbp-topic',       array( $this, 'display_topic'        ) );

		/** Replies ***********************************************************/

		// Reply form
		add_shortcode( 'bbp-reply-form', array( $this, 'display_reply_form' ) );

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
		ob_start();

		// Load the forums index
		if ( bbp_has_forums() )
			bbp_get_template_part( 'bbpress/loop', 'forums' );

		// No forums
		else
			bbp_get_template_part( 'bbpress/no', 'forums' );

		// Put output into usable variable
		$output = ob_get_contents();

		// Unset globals
		$this->_unset_globals();

		// Flush the output buffer
		ob_end_clean();

		return $output;
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
		ob_start();

		// Check forum caps
		if (	bbp_is_forum_public( $forum_id, false )
				|| ( bbp_is_forum_private( $forum_id, false ) && current_user_can( 'read_private_forums' ) )
				|| ( bbp_is_forum_hidden ( $forum_id, false ) && current_user_can( 'read_hidden_forums'  ) ) ) {

			/** Sub forums ****************************************************/

			// Check if forum has subforums first
			if ( bbp_get_forum_subforum_count( $forum_id ) ) {

				// Forum query
				$forum_query = array( 'post_parent' => $forum_id );

				// Load the forum
				if ( bbp_has_forums( $forum_query ) ) {
					bbp_single_forum_description( array( 'forum_id' => $forum_id ) );
					bbp_get_template_part( 'bbpress/loop', 'forums' );
				}
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
					'post_parent'   => $forum_id,
					'post_author'   => 0,
					'show_stickies' => true,
				);

				// Setup a meta_query to remove hidden forums

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
		} elseif ( bbp_is_forum_private( $forum_id, false ) && !current_user_can( 'read_private_forums' ) ) {
			bbp_get_template_part( 'bbpress/no', 'access' );

		// Forum is hidden and user does not have caps
		} elseif ( bbp_is_forum_hidden( $forum_id, false ) && !current_user_can( 'read_hidden_forums' ) ) {
			bbp_get_template_part( 'bbpress/no', 'topics' );
		}

		// Put output into usable variable
		$output = ob_get_contents();

		// Unset globals
		$this->_unset_globals();

		// Flush the output buffer
		ob_end_clean();

		return $output;
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
			'post_author'    => 0,
			'show_stickies'  => true,
			'order'          => 'DESC',
		);

		// Setup a meta_query to remove hidden forums
		if ( $hidden = bbp_get_hidden_forum_ids() ) {

			// Value and compare for meta_query
			$value   = implode( ',', $hidden );
			$compare = ( 1 < count( $hidden ) ) ? 'NOT IN' : '!=';

			// Add meta_query to $replies_query
			$topics_query['meta_query'] = array( array(
				'key'     => '_bbp_forum_id',
				'value'   => $value,
				'compare' => $compare
			) );
		}

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

		// Put output into usable variable
		$output = ob_get_contents();

		// Unset globals
		$this->_unset_globals();

		// Flush the output buffer
		ob_end_clean();

		return $output;
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
		$replies_query['meta_query'] = array(
			array(
				'key'     => '_bbp_topic_id',
				'value'   => $topic_id,
				'compare' => '='
			)
		);

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
		ob_start();

		// Check forum caps
		if (	bbp_is_forum_public( $forum_id, false )
				|| ( bbp_is_forum_private( $forum_id, false ) && current_user_can( 'read_private_forums' ) )
				|| ( bbp_is_forum_hidden ( $forum_id, false ) && current_user_can( 'read_hidden_forums'  ) ) ) {

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
		} elseif ( bbp_is_forum_private( $forum_id, false ) && !current_user_can( 'read_private_forums' ) ) {
			bbp_get_template_part( 'bbpress/no', 'access' );

		// Forum is hidden and user does not have caps
		} elseif ( bbp_is_forum_hidden( $forum_id, false ) && !current_user_can( 'read_hidden_forums' ) ) {
			bbp_get_template_part( 'bbpress/no', 'topics' );
		}

		// Put output into usable variable
		$output = ob_get_contents();

		// Unset globals
		$this->_unset_globals();

		// Flush the output buffer
		ob_end_clean();

		return $output;
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
		ob_start();

		// Output templates
		bbp_get_template_part( 'bbpress/form', 'topic'  );

		// Put output into usable variable
		$output = ob_get_contents();

		// Flush the output buffer
		ob_end_clean();

		return $output;
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
		ob_start();

		// Output templates
		bbp_get_template_part( 'bbpress/form', 'reply'  );

		// Put output into usable variable
		$output = ob_get_contents();

		// Flush the output buffer
		ob_end_clean();

		return $output;
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