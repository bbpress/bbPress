<?php

/**
 * Functions of bbPress's Twenty Ten theme
 *
 * @package bbPress
 * @subpackage BBP_Twenty_Ten
 * @since Twenty Ten 1.1
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Theme Setup ***************************************************************/

if ( !class_exists( 'BBP_Twenty_Ten' ) ) :

/**
 * Loads bbPress Twenty Ten Theme functionality
 *
 * Usually functions.php contains a few functions wrapped in function_exisits()
 * checks. Since bbp-twenty-ten is intended to be used both as a child theme and
 * for Theme Compatibility, we've moved everything into one convenient class
 * that can be copied or extended.
 *
 * See @link BBP_Theme_Compat() for more.
 *
 * @since bbPress (r3277)
 * @package bbPress
 * @subpackage BBP_Twenty_Ten
 */
class BBP_Twenty_Ten extends BBP_Theme_Compat {

	/** Functions *************************************************************/

	/**
	 * The main bbPress (Twenty Ten) Loader
	 *
	 * @since bbPress (r3277)
	 * @uses BBP_Twenty_Ten::setup_globals()
	 * @uses BBP_Twenty_Ten::setup_actions()
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Component global variables
	 *
	 * @since bbPress (r2626)
	 * @access private
	 * @uses bbp_get_version() To get the bbPress version
	 * @uses get_stylesheet_directory() To get the stylesheet path
	 * @uses get_stylesheet_directory_uri() To get the stylesheet uri
	 */
	private function setup_globals() {
		$bbp           = bbpress();
		$this->id      = 'bbp-twentyten';
		$this->name    = __( 'Twenty Ten (bbPress)', 'bbpress' ) ;
		$this->version = bbp_get_version();
		$this->dir     = trailingslashit( $bbp->themes_dir . 'bbp-twentyten' );
		$this->url     = trailingslashit( $bbp->themes_url . 'bbp-twentyten' );
	}

	/**
	 * Setup the theme hooks
	 *
	 * @since bbPress (r3277)
	 * @access private
	 * @uses add_filter() To add various filters
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {
		add_action( 'bbp_enqueue_scripts',      array( $this, 'enqueue_styles'        ) ); // Enqueue theme CSS
		add_action( 'bbp_enqueue_scripts',      array( $this, 'enqueue_scripts'       ) ); // Enqueue theme JS
		add_filter( 'bbp_enqueue_scripts',      array( $this, 'localize_topic_script' ) ); // Enqueue theme script localization
		add_action( 'bbp_head',                 array( $this, 'head_scripts'          ) ); // Output some extra JS in the <head>
		add_action( 'wp_ajax_dim-favorite',     array( $this, 'ajax_favorite'         ) ); // Handles the ajax favorite/unfavorite
		add_action( 'wp_ajax_dim-subscription', array( $this, 'ajax_subscription'     ) ); // Handles the ajax subscribe/unsubscribe
	}

	/**
	 * Load the theme CSS
	 *
	 * @since bbPress (r2652)
	 * @uses wp_enqueue_style() To enqueue the styles
	 */
	public function enqueue_styles() {

		// Right to left
		if ( is_rtl() ) {

			// TwentyTen
			if ( 'twentyten' == get_template() ) {
				wp_enqueue_style( 'twentyten',     get_template_directory_uri() . '/style.css', '',          $this->version, 'screen' );
				wp_enqueue_style( 'twentyten-rtl', get_template_directory_uri() . '/rtl.css',   'twentyten', $this->version, 'screen' );
			}

			// bbPress specific
			wp_enqueue_style( 'bbp-twentyten-bbpress', $this->url . 'css/bbpress-rtl.css', 'twentyten-rtl', $this->version, 'screen' );

		// Left to right
		} else {

			// TwentyTen
			if ( 'twentyten' == get_template() ) {
				wp_enqueue_style( 'twentyten',     get_template_directory_uri() . '/style.css', '',          $this->version, 'screen' );
			}

			// bbPress specific
			wp_enqueue_style( 'bbp-twentyten-bbpress', $this->url . 'css/bbpress.css', 'twentyten', $this->version, 'screen' );
		}
	}

	/**
	 * Enqueue the required Javascript files
	 *
	 * @since bbPress (r2652)
	 * @uses bbp_is_single_topic() To check if it's the topic page
	 * @uses get_stylesheet_directory_uri() To get the stylesheet directory uri
	 * @uses bbp_is_single_user_edit() To check if it's the profile edit page
	 * @uses wp_enqueue_script() To enqueue the scripts
	 */
	public function enqueue_scripts() {

		if ( bbp_is_single_topic() )
			wp_enqueue_script( 'bbp_topic', $this->url . 'js/topic.js', array( 'wp-lists' ), $this->version, true );

		if ( bbp_is_single_user_edit() )
			wp_enqueue_script( 'user-profile' );
	}

	/**
	 * Put some scripts in the header, like AJAX url for wp-lists
	 *
	 * @since bbPress (r2652)
	 * @uses bbp_is_single_topic() To check if it's the topic page
	 * @uses admin_url() To get the admin url
	 * @uses bbp_is_single_user_edit() To check if it's the profile edit page
	 */
	public function head_scripts() {
		if ( bbp_is_single_topic() ) : ?>

		<script type='text/javascript'>
			/* <![CDATA[ */
			var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			/* ]]> */
		</script>

		<?php elseif ( bbp_is_single_user_edit() ) : ?>

		<script type="text/javascript" charset="utf-8">
			if ( window.location.hash == '#password' ) {
				document.getElementById('pass1').focus();
			}
		</script>

		<?php
		endif;
	}

	/**
	 * Load localizations for topic script
	 *
	 * These localizations require information that may not be loaded even by init.
	 *
	 * @since bbPress (r2652)
	 * @uses bbp_is_single_topic() To check if it's the topic page
	 * @uses is_user_logged_in() To check if user is logged in
	 * @uses bbp_get_current_user_id() To get the current user id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses bbp_get_favorites_permalink() To get the favorites permalink
	 * @uses bbp_is_user_favorite() To check if the topic is in user's favorites
	 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
	 * @uses bbp_is_user_subscribed() To check if the user is subscribed to topic
	 * @uses bbp_get_topic_permalink() To get the topic permalink
	 * @uses wp_localize_script() To localize the script
	 */
	public function localize_topic_script() {

		// Bail if not viewing a single topic
		if ( !bbp_is_single_topic() )
			return;

		// Bail if user is not logged in
		if ( !is_user_logged_in() )
			return;

		$user_id = bbp_get_current_user_id();

		$localizations = array(
			'currentUserId' => $user_id,
			'topicId'       => bbp_get_topic_id(),
		);

		// Favorites
		if ( bbp_is_favorites_active() ) {
			$localizations['favoritesActive'] = 1;
			$localizations['favoritesLink']   = bbp_get_favorites_permalink( $user_id );
			$localizations['isFav']           = (int) bbp_is_user_favorite( $user_id );
			$localizations['favLinkYes']      = __( 'favorites',                                         'bbpress' );
			$localizations['favLinkNo']       = __( '?',                                                 'bbpress' );
			$localizations['favYes']          = __( 'This topic is one of your %favLinkYes% [%favDel%]', 'bbpress' );
			$localizations['favNo']           = __( '%favAdd% (%favLinkNo%)',                            'bbpress' );
			$localizations['favDel']          = __( '&times;',                                           'bbpress' );
			$localizations['favAdd']          = __( 'Add this topic to your favorites',                  'bbpress' );
		} else {
			$localizations['favoritesActive'] = 0;
		}

		// Subscriptions
		if ( bbp_is_subscriptions_active() ) {
			$localizations['subsActive']   = 1;
			$localizations['isSubscribed'] = (int) bbp_is_user_subscribed( $user_id );
			$localizations['subsSub']      = __( 'Subscribe',   'bbpress' );
			$localizations['subsUns']      = __( 'Unsubscribe', 'bbpress' );
			$localizations['subsLink']     = bbp_get_topic_permalink();
		} else {
			$localizations['subsActive'] = 0;
		}

		wp_localize_script( 'bbp_topic', 'bbpTopicJS', $localizations );
	}

	/**
	 * Add or remove a topic from a user's favorites
	 *
	 * @since bbPress (r2652)
	 * @uses bbp_get_current_user_id() To get the current user id
	 * @uses current_user_can() To check if the current user can edit the user
	 * @uses bbp_get_topic() To get the topic
	 * @uses check_ajax_referer() To verify the nonce & check the referer
	 * @uses bbp_is_user_favorite() To check if the topic is user's favorite
	 * @uses bbp_remove_user_favorite() To remove the topic from user's favorites
	 * @uses bbp_add_user_favorite() To add the topic from user's favorites
	 */
	public function ajax_favorite() {
		$user_id = bbp_get_current_user_id();
		$id      = intval( $_POST['id'] );

		if ( !current_user_can( 'edit_user', $user_id ) )
			die( '-1' );

		if ( !$topic = bbp_get_topic( $id ) )
			die( '0' );

		check_ajax_referer( 'toggle-favorite_' . $topic->ID );

		if ( bbp_is_user_favorite( $user_id, $topic->ID ) ) {
			if ( bbp_remove_user_favorite( $user_id, $topic->ID ) ) {
				die( '1' );
			}
		} else {
			if ( bbp_add_user_favorite( $user_id, $topic->ID ) ) {
				die( '1' );
			}
		}

		die( '0' );
	}

	/**
	 * Subscribe/Unsubscribe a user from a topic
	 *
	 * @since bbPress (r2668)
	 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
	 * @uses bbp_get_current_user_id() To get the current user id
	 * @uses current_user_can() To check if the current user can edit the user
	 * @uses bbp_get_topic() To get the topic
	 * @uses check_ajax_referer() To verify the nonce & check the referer
	 * @uses bbp_is_user_subscribed() To check if the topic is in user's
	 *                                 subscriptions
	 * @uses bbp_remove_user_subscriptions() To remove the topic from user's
	 *                                        subscriptions
	 * @uses bbp_add_user_subscriptions() To add the topic from user's subscriptions
	 */
	public function ajax_subscription() {
		if ( !bbp_is_subscriptions_active() )
			return;

		$user_id = bbp_get_current_user_id();
		$id      = intval( $_POST['id'] );

		if ( !current_user_can( 'edit_user', $user_id ) )
			die( '-1' );

		if ( !$topic = bbp_get_topic( $id ) )
			die( '0' );

		check_ajax_referer( 'toggle-subscription_' . $topic->ID );

		if ( bbp_is_user_subscribed( $user_id, $topic->ID ) ) {
			if ( bbp_remove_user_subscription( $user_id, $topic->ID ) ) {
				die( '1' );
			}
		} else {
			if ( bbp_add_user_subscription( $user_id, $topic->ID ) ) {
				die( '1' );
			}
		}

		die( '0' );
	}
}
new BBP_Twenty_Ten();
endif;
