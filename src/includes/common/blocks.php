<?php

/**
 * bbPress Blocks
 *
 * @package bbPress
 * @subpackage Blocks
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Blocks' ) ) :
/**
 * bbPress Shortcode Class
 *
 * @since 2.0.0 bbPress (r3031)
 */
class BBP_Blocks {

	/** Vars ******************************************************************/

	/**
	 * @var BBP_Shortcodes Most of our blocks are just visual representations of existing shortcodes.
	 */
	public $shortcodes;

	/** Functions *************************************************************/

	/**
	 * Set up the blocks for the Block Editor.
	 */
	public function __construct( BBP_Shortcodes $shortcodes = NULL ) {
		$this->shortcodes = $shortcodes;
		$this->register_blocks();
	}

	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_style(
			'bbp-blocks',
			plugins_url( '../admin/assets/css/blocks.css', __FILE__ )
		);

		wp_register_script(
			'bbp-blocks',
			plugins_url( '../admin/assets/js/blocks.js', __FILE__ ),
			array(
				'wp-blocks',
				'wp-components',
				'wp-i18n',
				'wp-block-editor',
			),
			filemtime( __DIR__ . '/../admin/assets/js/blocks.js' )
		);
		wp_localize_script( 'bbp-blocks', 'bbpBlocks', array(
			'data' => array(
				'forum_post_type' => bbp_get_forum_post_type(),
				'forum_count' => wp_count_posts( 'forums' )->publish,
				'forum_post_type' => bbp_get_forum_post_type(),
			)
		) );

		// Note: While not ideal, we're storing all of our block js in the same file.  Fewer assets to load, but
		// it does mean that if the block is deregistered in php, it will still exist in js in the block editor.

		register_block_type( 'bbpress/forum-index', array(
			'render_callback' => array( $this, 'display_forum_index' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/forum-form', array(
			'render_callback' => array( $this, 'display_forum_form' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/forum', array(
			'render_callback' => array( $this, 'display_forum' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/topic-index', array(
			'render_callback' => array( $this, 'display_topic_index' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/topic-form', array(
			'render_callback' => array( $this, 'display_topic_form' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/topic', array(
			'render_callback' => array( $this, 'display_topic' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/topic-tags', array(
			'render_callback' => array( $this, 'display_topic_tags' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/topics-of-tag', array(
			'render_callback' => array( $this, 'display_topics_of_tag' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/reply-form', array(
			'render_callback' => array( $this, 'display_reply_form' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/reply', array(
			'render_callback' => array( $this, 'display_reply' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/view', array(
			'render_callback' => array( $this, 'display_view' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/search-form', array(
			'render_callback' => array( $this, 'display_search_form' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/search', array(
			'render_callback' => array( $this, 'display_search' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/login', array(
			'render_callback' => array( $this, 'display_login' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/register', array(
			'render_callback' => array( $this, 'display_register' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/lost-pass', array(
			'render_callback' => array( $this, 'display_lost_pass' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );

		register_block_type( 'bbpress/stats', array(
			'render_callback' => array( $this, 'display_stats' ),
			'editor_script'   => 'bbp-blocks',
			'editor_style'    => 'bbp-blocks',
		) );
	}


	/**
	 * Passthrough function for `display_forum_index` -- the forum list view.
	 *
	 * @return string The markup for the forum list view.
	 */
	public function display_forum_index() {
		return $this->shortcodes->display_forum_index();
	}

	/**
	 * Passthrough function for `display_forum_form` -- the new forum form.
	 *
	 * @return string The markup for the new forum form.
	 */
	public function display_forum_form() {
		return $this->shortcodes->display_forum_form();
	}

	/**
	 * Passthrough function for `display_forum` -- the single forum view.
	 *
	 * @param $attributes (array) An array with -- at minimum -- an `id` key set to the forum id.
	 * @return string The markup for the single forum.
	 */
	public function display_forum( $attributes ) {
		// If for some reason there isn't a forum id, just display it all.
		if ( empty( $attributes['id'] ) ) {
			return $this->display_forum_index();
		}

		return $this->shortcodes->display_forum( $attributes );
	}

	public function display_topic_index() {
		return $this->shortcodes->display_topic_index();
	}
	public function display_topic_form( $attributes ) {
		return $this->shortcodes->display_topic_form( $attributes );
	}
	public function display_topic( $attributes ) {
		return $this->shortcodes->display_topic( $attributes );
	}

	public function display_topic_tags() {
		return $this->shortcodes->display_topic_tags();
	}
	public function display_topics_of_tag( $attributes ) {
		return $this->shortcodes->display_topics_of_tag( $attributes );
	}

	public function display_reply_form() {
		return $this->shortcodes->display_reply_form();
	}
	public function display_reply( $attributes ) {
		return $this->shortcodes->display_reply( $attributes );
	}

	public function display_view( $attributes ) {
		return $this->shortcodes->display_view( $attributes );
	}

	public function display_search_form() {
		return $this->shortcodes->display_search_form();
	}
	public function display_search( $attributes ) {
		return $this->shortcodes->display_search( $attributes );
	}

	public function display_login() {
		return $this->shortcodes->display_login();
	}
	public function display_register() {
		return $this->shortcodes->display_register();
	}
	public function display_lost_pass() {
		return $this->shortcodes->display_lost_pass();
	}

	public function display_stats() {
		return $this->shortcodes->display_stats();
	}


}
endif;