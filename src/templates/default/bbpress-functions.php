<?php

/**
 * Functions of the Default template-pack
 *
 * @package bbPress
 * @subpackage BBP_Theme_Compat
 * @since 2.1.0 bbPress (r3732)
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Theme Setup ***************************************************************/

if ( ! class_exists( 'BBP_Default' ) ) :

/**
 * Loads bbPress Default Theme functionality
 *
 * This is not a real theme by WordPress standards, and is instead used as the
 * fallback for any WordPress theme that does not have bbPress templates in it.
 *
 * To make your custom theme bbPress compatible and customize the templates, you
 * can copy these files into your theme without needing to merge anything
 * together; bbPress should safely handle the rest.
 *
 * See @link BBP_Theme_Compat() for more.
 *
 * @since 2.1.0 bbPress (r3732)
 *
 * @package bbPress
 * @subpackage BBP_Theme_Compat
 */
class BBP_Default extends BBP_Theme_Compat {

	/** Functions *************************************************************/

	/**
	 * The main bbPress (Default) Loader
	 *
	 * @since 2.1.0 bbPress (r3732)
	 */
	public function __construct( $properties = array() ) {

		parent::__construct( bbp_parse_args( $properties, array(
			'id'      => 'default',
			'name'    => 'bbPress Default',
			'version' => bbp_get_asset_version(),
			'dir'     => trailingslashit( bbpress()->themes_dir . 'default' ),
			'url'     => trailingslashit( bbpress()->themes_url . 'default' ),
		), 'default_theme' ) );

		$this->setup_actions();
	}

	/**
	 * Setup the theme hooks
	 *
	 * @since 2.1.0 bbPress (r3732)
	 *
	 * @access private
	 */
	private function setup_actions() {

		/** Scripts ***********************************************************/

		add_action( 'bbp_enqueue_scripts',         array( $this, 'enqueue_styles'        ) ); // Enqueue theme CSS
		add_action( 'bbp_enqueue_scripts',         array( $this, 'enqueue_scripts'       ) ); // Enqueue theme JS
		add_filter( 'bbp_enqueue_scripts',         array( $this, 'localize_topic_script' ) ); // Enqueue theme script localization
		add_action( 'bbp_ajax_favorite',           array( $this, 'ajax_favorite'         ) ); // Handles the topic ajax favorite/unfavorite
		add_action( 'bbp_ajax_subscription',       array( $this, 'ajax_subscription'     ) ); // Handles the topic ajax subscribe/unsubscribe

		/** Template Wrappers *************************************************/

		add_action( 'bbp_before_main_content',  array( $this, 'before_main_content'   ) ); // Top wrapper HTML
		add_action( 'bbp_after_main_content',   array( $this, 'after_main_content'    ) ); // Bottom wrapper HTML

		/** Override **********************************************************/

		do_action_ref_array( 'bbp_theme_compat_actions', array( &$this ) );
	}

	/**
	 * Inserts HTML at the top of the main content area to be compatible with
	 * the Twenty Twelve theme.
	 *
	 * @since 2.1.0 bbPress (r3732)
	 */
	public function before_main_content() {
	?>

		<div id="bbp-container">
			<div id="bbp-content" role="main">

	<?php
	}

	/**
	 * Inserts HTML at the bottom of the main content area to be compatible with
	 * the Twenty Twelve theme.
	 *
	 * @since 2.1.0 bbPress (r3732)
	 */
	public function after_main_content() {
	?>

			</div><!-- #bbp-content -->
		</div><!-- #bbp-container -->

	<?php
	}

	/**
	 * Load the theme CSS
	 *
	 * @since 2.1.0 bbPress (r3732)
	 */
	public function enqueue_styles() {

		// Setup the default styling
		$defaults = array(
			'bbp-default' => array(
				'file'         => 'css/bbpress.css',
				'dependencies' => array()
			)
		);

		// Optionally support an RTL variant
		if ( is_rtl() ) {
			$defaults['bbp-default-rtl'] = array(
				'file'         => 'css/bbpress-rtl.css',
				'dependencies' => array()
			);
		}

		// Get and filter the bbp-default style
		$styles = apply_filters( 'bbp_default_styles', $defaults );

		// Enqueue the styles
		foreach ( $styles as $handle => $attributes ) {
			bbp_enqueue_style( $handle, $attributes['file'], $attributes['dependencies'], $this->version );
		}
	}

	/**
	 * Enqueue the required JavaScript files
	 *
	 * @since 2.1.0 bbPress (r3732)
	 */
	public function enqueue_scripts() {

		// Setup scripts array
		$scripts = array();

		// Editor scripts
		// @see https://bbpress.trac.wordpress.org/ticket/2930
		if ( bbp_use_wp_editor() && is_bbpress() ) {
			$scripts['bbpress-editor'] = array(
				'file'         => 'js/editor.js',
				'dependencies' => array( 'jquery' )
			);
		}

		// Forum-specific scripts
		if ( bbp_is_single_forum() ) {
			$scripts['bbpress-engagements'] = array(
				'file'         => 'js/engagements.js',
				'dependencies' => array( 'jquery' )
			);
		}

		// Topic-specific scripts
		if ( bbp_is_single_topic() || bbp_is_topic_edit() ) {

			// Engagements
			$scripts['bbpress-engagements'] = array(
				'file'         => 'js/engagements.js',
				'dependencies' => array( 'jquery' )
			);

			// Hierarchical replies
			if ( bbp_thread_replies() ) {
				$scripts['bbpress-reply'] = array(
					'file'         => 'js/reply.js',
					'dependencies' => array( 'jquery' )
				);
			}
		}

		// User Profile edit
		if ( bbp_is_single_user_edit() ) {
			wp_enqueue_script( 'user-profile' );
		}

		// Filter the scripts
		$scripts = apply_filters( 'bbp_default_scripts', $scripts );

		// Enqueue the scripts
		foreach ( $scripts as $handle => $attributes ) {
			bbp_enqueue_script( $handle, $attributes['file'], $attributes['dependencies'], $this->version, true );
		}
	}

	/**
	 * Load localizations for topic script
	 *
	 * These localizations require information that may not be loaded even by init.
	 *
	 * @since 2.1.0 bbPress (r3732)
	 */
	public function localize_topic_script() {

		// Single forum or topic
		if ( bbp_is_single_forum() || bbp_is_single_topic() ) {
			wp_localize_script( 'bbpress-engagements', 'bbpEngagementJS', array(
				'object_id'          => get_the_ID(),
				'bbp_ajaxurl'        => bbp_get_ajax_url(),
				'generic_ajax_error' => esc_html__( 'Something went wrong. Refresh your browser and try again.', 'bbpress' ),
			) );
		}
	}

	/**
	 * AJAX handler to add or remove a topic from a user's favorites
	 *
	 * @since 2.1.0 bbPress (r3732)
	 */
	public function ajax_favorite() {

		// Bail if favorites are not active
		if ( ! bbp_is_favorites_active() ) {
			bbp_ajax_response( false, esc_html__( 'Favorites are no longer active.', 'bbpress' ), 300 );
		}

		// Bail if user is not logged in
		if ( ! is_user_logged_in() ) {
			bbp_ajax_response( false, esc_html__( 'Please login to favorite.', 'bbpress' ), 301 );
		}

		// Get user and topic data
		$user_id = bbp_get_current_user_id();
		$id      = ! empty( $_POST['id']   ) ? intval( $_POST['id'] )         : 0;
		$type    = ! empty( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'post';

		// Bail if user cannot add favorites for this user
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			bbp_ajax_response( false, esc_html__( 'You do not have permission to do this.', 'bbpress' ), 302 );
		}

		// Get the object
		if ( 'post' === $type ) {
			$object = get_post( $id );
		}

		// Bail if topic cannot be found
		if ( empty( $object ) ) {
			bbp_ajax_response( false, esc_html__( 'Favorite failed.', 'bbpress' ), 303 );
		}

		// Bail if user did not take this action
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'toggle-favorite_' . $object->ID ) ) {
			bbp_ajax_response( false, esc_html__( 'Are you sure you meant to do that?', 'bbpress' ), 304 );
		}

		// Take action
		$status = bbp_is_user_favorite( $user_id, $object->ID )
			? bbp_remove_user_favorite( $user_id, $object->ID )
			:    bbp_add_user_favorite( $user_id, $object->ID );

		// Bail if action failed
		if ( empty( $status ) ) {
			bbp_ajax_response( false, esc_html__( 'The request was unsuccessful. Please try again.', 'bbpress' ), 305 );
		}

		// Put subscription attributes in convenient array
		$attrs = array(
			'object_id'   => $object->ID,
			'object_type' => $type,
			'user_id'     => $user_id
		);

		// Action succeeded
		bbp_ajax_response( true, bbp_get_user_favorites_link( $attrs, $user_id, false ), 200 );
	}

	/**
	 * AJAX handler to Subscribe/Unsubscribe a user from a topic
	 *
	 * @since 2.1.0 bbPress (r3732)
	 */
	public function ajax_subscription() {

		// Bail if subscriptions are not active
		if ( ! bbp_is_subscriptions_active() ) {
			bbp_ajax_response( false, esc_html__( 'Subscriptions are no longer active.', 'bbpress' ), 300 );
		}

		// Bail if user is not logged in
		if ( ! is_user_logged_in() ) {
			bbp_ajax_response( false, esc_html__( 'Please login to subscribe.', 'bbpress' ), 301 );
		}

		// Get user and topic data
		$user_id = bbp_get_current_user_id();
		$id      = ! empty( $_POST['id']   ) ? intval( $_POST['id'] )         : 0;
		$type    = ! empty( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'post';

		// Bail if user cannot add favorites for this user
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			bbp_ajax_response( false, esc_html__( 'You do not have permission to do this.', 'bbpress' ), 302 );
		}

		// Get the object
		if ( 'post' === $type ) {
			$object = get_post( $id );
		}

		// Bail if topic cannot be found
		if ( empty( $object ) ) {
			bbp_ajax_response( false, esc_html__( 'Subscription failed.', 'bbpress' ), 303 );
		}

		// Bail if user did not take this action
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'toggle-subscription_' . $object->ID ) ) {
			bbp_ajax_response( false, esc_html__( 'Are you sure you meant to do that?', 'bbpress' ), 304 );
		}

		// Take action
		$status = bbp_is_user_subscribed( $user_id, $object->ID )
			? bbp_remove_user_subscription( $user_id, $object->ID )
			:    bbp_add_user_subscription( $user_id, $object->ID );

		// Bail if action failed
		if ( empty( $status ) ) {
			bbp_ajax_response( false, esc_html__( 'The request was unsuccessful. Please try again.', 'bbpress' ), 305 );
		}

		// Put subscription attributes in convenient array
		$attrs = array(
			'object_id'   => $object->ID,
			'object_type' => $type,
			'user_id'     => $user_id
		);

		// Add separator to topic if favorites is active
		if ( ( 'post' === $type ) && ( bbp_get_topic_post_type() === get_post_type( $object ) ) && bbp_is_favorites_active() ) {
			$attrs['before'] = '&nbsp;|&nbsp;';
		}

		// Action succeeded
		bbp_ajax_response( true, bbp_get_user_subscribe_link( $attrs, $user_id, false ), 200 );
	}
}
new BBP_Default();
endif;
