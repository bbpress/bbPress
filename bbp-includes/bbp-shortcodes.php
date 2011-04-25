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
		add_shortcode( 'bbpress-forum-index', array( $this, 'display_forum_index' ) );

		// Specific forum - pass an 'id' attribute
		add_shortcode( 'bbpress-forum',       array( $this, 'display_forum'       ) );

		/** Topics ************************************************************/

		// Topic index
		add_shortcode( 'bbpress-topic-index',  array( $this, 'display_topic_index'  ) );

		// New topic form
		add_shortcode( 'bbpress-create-topic', array( $this, 'display_create_topic' ) );

		// Specific topic - pass an 'id' attribute
		add_shortcode( 'bbpress-topic',        array( $this, 'display_topic'        ) );

		// Custom shortcodes
		do_action( 'bbp_register_shortcodes' );
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

		// Load the forums index
		if ( bbp_has_forums() ) {

			// Start output buffer
			ob_start();

			// Output templates
			bbp_get_template_part( 'bbpress/loop', 'forums' );
			bbp_get_template_part( 'bbpress/form', 'topic'  );

			// Put output into usable variable
			$output = ob_get_contents();

			// Flush the output buffer
			ob_end_clean();

			return $output;
		}
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
		if ( bbp_is_forum_public( $forum_id, false ) || current_user_can( 'read_private_forums' ) ) {

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

				// Clear global forum_query
				unset( $bbp->forum_query );

				// Reset necessary forum_query attributes for topics loop to function
				$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
				$bbp->forum_query->in_the_loop             = true;
				$bbp->forum_query->post                    = get_post( $forum_id );

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
					$topics_query['post_parent'] = $forum_id;
					$topics_query['meta_key']    = '';
					$topics_query['meta_value']  = '';
				}

				// Load the topic index
				if ( bbp_has_topics( $topics_query ) ) {
					bbp_get_template_part( 'bbpress/pagination', 'topics' );
					bbp_get_template_part( 'bbpress/loop',       'topics' );
					bbp_get_template_part( 'bbpress/pagination', 'topics' );
					bbp_get_template_part( 'bbpress/form',       'topic'  );
				}
			}

		// Forum is private and user does not have caps
		} else {
			bbp_get_template_part( 'bbpress/no', 'access' );
		}

		// Put output into usable variable
		$output = ob_get_contents();

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
			$topics_query['post_parent'] = 'any';
			$topics_query['meta_key'] = '';
			$topics_query['meta_value'] = '';
		}

		// Load the topic index
		if ( bbp_has_topics( $topics_query ) ) {

			// Start output buffer
			ob_start();

			// Output templates
			bbp_get_template_part( 'bbpress/pagination', 'topics' );
			bbp_get_template_part( 'bbpress/loop',       'topics' );
			bbp_get_template_part( 'bbpress/pagination', 'topics' );
			bbp_get_template_part( 'bbpress/form',       'topic'  );

			// Put output into usable variable
			$output = ob_get_contents();

			// Flush the output buffer
			ob_end_clean();

			return $output;
		}
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

		// Reset necessary forum_query attributes for topics loop to function
		$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
		$bbp->forum_query->in_the_loop             = true;
		$bbp->forum_query->post                    = get_post( $forum_id );

		// Reset necessary topic_query attributes for topics loop to function
		$bbp->topic_query->query_vars['post_type'] = bbp_get_topic_post_type();
		$bbp->topic_query->in_the_loop             = true;
		$bbp->topic_query->post                    = get_post( $topic_id );

		// Load the topic
		if ( bbp_has_replies( $replies_query ) ) {

			// Start output buffer
			ob_start();

			// Output templates
			bbp_get_template_part( 'bbpress/pagination', 'replies' );
			bbp_get_template_part( 'bbpress/loop',       'replies' );
			bbp_get_template_part( 'bbpress/pagination', 'replies' );
			bbp_get_template_part( 'bbpress/form',       'reply'   );

			// Put output into usable variable
			$output = ob_get_contents();

			// Flush the output buffer
			ob_end_clean();

			return $output;
		}
	}

	/**
	 * Display the new topic form in an output buffer and return to ensure
	 * that post/page contents are displayed first.
	 *
	 * @since bbPress (r3031)
	 *
	 * @global bbPress $bbp
	 *
	 * @uses current_theme_supports()
	 * @uses get_template_part()
	 */
	function display_create_topic() {
		global $bbp;

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