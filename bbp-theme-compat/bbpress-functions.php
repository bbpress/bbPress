<?php

/**
 * Functions of bbPress's Default theme
 *
 * @package bbPress
 * @subpackage BBP_Theme_Compat
 * @since bbPress (r3732)
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Theme Setup ***************************************************************/

if ( !class_exists( 'BBP_Default' ) ) :

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
 * @since bbPress (r3732)
 *
 * @package bbPress
 * @subpackage BBP_Theme_Compat
 */
class BBP_Default extends BBP_Theme_Compat {

	/** Functions *************************************************************/

	/**
	 * The main bbPress (Default) Loader
	 *
	 * @since bbPress (r3732)
	 *
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
	 * Note that this function is currently commented out in the constructor.
	 * It will only be used if you copy this file into your current theme and
	 * uncomment the line above.
	 * 
	 * You'll want to customize the values in here, so they match whatever your
	 * needs are.
	 *
	 * @since bbPress (r3732)
	 * @access private
	 */
	private function setup_globals() {

		// Use the default theme compat if current theme has not added support
		if ( !current_theme_supports( 'bbpress' ) ) {
			$this->id      = bbp_get_theme_compat_id();
			$this->name    = bbp_get_theme_compat_name();
			$this->version = bbp_get_theme_compat_version();
			$this->dir     = bbp_get_theme_compat_dir();
			$this->url     = bbp_get_theme_compat_url();

		// Theme supports bbPress, so set some smart defaults
		} else {

			// @todo Remove when WordPress 3.5 ships
			if ( function_exists( 'wp_get_theme' ) )
				$theme = wp_get_theme();
			else
				$theme = get_current_theme();

			$this->id      = $theme->stylesheet;
			$this->name    = sprintf( __( '%s (bbPress)', 'bbpress' ), $theme->name ) ;
			$this->version = bbp_get_version();
			$this->dir     = trailingslashit( get_stylesheet_directory() );
			$this->url     = trailingslashit( get_stylesheet_directory_uri() );
		}

		// Conditionally add theme support if needed
		if ( in_array( $this->id, array( get_template(), get_stylesheet() ) ) ) {
			add_theme_support( 'bbpress' );
		}
	}

	/**
	 * Setup the theme hooks
	 *
	 * @since bbPress (r3732)
	 * @access private
	 *
	 * @uses add_filter() To add various filters
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		/** Scripts ***********************************************************/

		add_action( 'bbp_enqueue_scripts',      array( $this, 'enqueue_styles'        ) ); // Enqueue theme CSS
		add_action( 'bbp_enqueue_scripts',      array( $this, 'enqueue_scripts'       ) ); // Enqueue theme JS
		add_filter( 'bbp_enqueue_scripts',      array( $this, 'localize_topic_script' ) ); // Enqueue theme script localization
		add_action( 'bbp_head',                 array( $this, 'head_scripts'          ) ); // Output some extra JS in the <head>
		add_action( 'wp_ajax_dim-favorite',     array( $this, 'ajax_favorite'         ) ); // Handles the ajax favorite/unfavorite
		add_action( 'wp_ajax_dim-subscription', array( $this, 'ajax_subscription'     ) ); // Handles the ajax subscribe/unsubscribe

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
	 * @since bbPress (r3732)
	 */
	public function before_main_content() {
	?>

		<div id="container">
			<div id="content" role="main">

	<?php
	}

	/**
	 * Inserts HTML at the bottom of the main content area to be compatible with
	 * the Twenty Twelve theme.
	 *
	 * @since bbPress (r3732)
	 */
	public function after_main_content() {
	?>

			</div><!-- #content -->
		</div><!-- #container -->

	<?php
	}

	/**
	 * Load the theme CSS
	 *
	 * @since bbPress (r3732)
	 *
	 * @uses wp_enqueue_style() To enqueue the styles
	 */
	public function enqueue_styles() {
		$file = is_rtl() ? 'css/bbpress-rtl.css' : 'css/bbpress.css';
		wp_enqueue_style( 'bbp-default-bbpress', $this->url . $file, array(), $this->version, 'screen' );
	}

	/**
	 * Enqueue the required Javascript files
	 *
	 * @since bbPress (r3732)
	 *
	 * @uses bbp_is_single_topic() To check if it's the topic page
	 * @uses get_stylesheet_directory_uri() To get the stylesheet directory uri
	 * @uses bbp_is_single_user_edit() To check if it's the profile edit page
	 * @uses wp_enqueue_script() To enqueue the scripts
	 */
	public function enqueue_scripts() {

		if ( bbp_is_single_topic() )
			wp_enqueue_script( 'bbpress-topic', $this->url . 'js/topic.js', array( 'wp-lists' ), $this->version, true );

		if ( bbp_is_single_user_edit() )
			wp_enqueue_script( 'user-profile' );
	}

	/**
	 * Put some scripts in the header, like AJAX url for wp-lists
	 *
	 * @since bbPress (r3732)
	 *
	 * @uses bbp_is_single_topic() To check if it's the topic page
	 * @uses admin_url() To get the admin url
	 * @uses bbp_is_single_user_edit() To check if it's the profile edit page
	 */
	public function head_scripts() {
	?>

		<script type="text/javascript" charset="utf-8">
			/* <![CDATA[ */
			var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			
			<?php if ( bbp_is_single_user_edit() ) : ?>
			if ( window.location.hash == '#password' ) {
				document.getElementById('pass1').focus();
			}
			<?php endif; ?>
			/* ]]> */
		</script>

	<?php
	}

	/**
	 * Load localizations for topic script
	 *
	 * These localizations require information that may not be loaded even by init.
	 *
	 * @since bbPress (r3732)
	 *
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

		wp_localize_script( 'bbpress-topic', 'bbpTopicJS', $localizations );
	}

	/**
	 * Add or remove a topic from a user's favorites
	 *
	 * @since bbPress (r3732)
	 *
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

		$topic = bbp_get_topic( $id );

		if ( empty( $topic ) )
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
	 * @since bbPress (r3732)
	 *
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

		$topic = bbp_get_topic( $id );

		if ( empty( $topic ) )
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
new BBP_Default();
endif;

?>
