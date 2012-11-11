<?php

/**
 * bbPress BuddyPress Component Class
 *
 * @package bbPress
 * @subpackage BuddyPress
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Forums_Component' ) ) :
/**
 * Loads Forums Component
 *
 * @since bbPress (r3552)
 *
 * @package bbPress
 * @subpackage BuddyPress
 */
class BBP_Forums_Component extends BP_Component {

	/**
	 * Start the forums component creation process
	 *
	 * @since bbPress (r3552)
	 */
	public function __construct() {
		parent::start(
			'forums',
			__( 'Forums', 'bbpress' ),
			BP_PLUGIN_DIR
		);
		$this->setup_globals();
		$this->setup_nav();
	}

	/**
	 * Setup globals
	 *
	 * The BP_FORUMS_SLUG constant is deprecated, and only used here for
	 * backwards compatibility.
	 *
	 * @since bbPress (r3552)
	 * @global BuddyPress $bp
	 */
	public function setup_globals() {
		global $bp;

		// Define the parent forum ID
		if ( !defined( 'BP_FORUMS_PARENT_FORUM_ID' ) )
			define( 'BP_FORUMS_PARENT_FORUM_ID', 1 );

		// Define a slug, if necessary
		if ( !defined( 'BP_FORUMS_SLUG' ) )
			define( 'BP_FORUMS_SLUG', $this->id );

		// All globals for messaging component.
		$globals = array(
			'path'                  => BP_PLUGIN_DIR,
			'slug'                  => BP_FORUMS_SLUG,
			'root_slug'             => isset( $bp->pages->forums->slug ) ? $bp->pages->forums->slug : BP_FORUMS_SLUG,
			'has_directory'         => false,
			'notification_callback' => 'messages_format_notifications',
			'search_string'         => __( 'Search Forums...', 'bbpress' ),
		);

		parent::setup_globals( $globals );
	}

	/**
	 * Setup BuddyBar navigation
	 *
	 * @since bbPress (r3552)
	 */
	public function setup_nav() {

		// Stop if there is no user displayed or logged in
		if ( !is_user_logged_in() && !bp_displayed_user_id() )
			return;

		// Define local variable(s)
		$sub_nav     = array();
		$user_domain = '';

		// Add 'Forums' to the main navigation
		$main_nav = array(
			'name'                => __( 'Forums', 'bbpress' ),
			'slug'                => $this->slug,
			'position'            => 80,
			'screen_function'     => 'bbp_member_forums_screen_topics',
			'default_subnav_slug' => 'topics',
			'item_css_id'         => $this->id
		);

		// Determine user to use
		if ( bp_displayed_user_id() )
			$user_domain = bp_displayed_user_domain();
		elseif ( bp_loggedin_user_domain() )
			$user_domain = bp_loggedin_user_domain();
		else
			return;

		// User link
		$forums_link = trailingslashit( $user_domain . $this->slug );

		// Topics started
		$sub_nav[] = array(
			'name'            => __( 'Topics Started', 'bbpress' ),
			'slug'            => 'topics',
			'parent_url'      => $forums_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'bbp_member_forums_screen_topics',
			'position'        => 20,
			'item_css_id'     => 'topics'
		);

		// Replies to topics
		$sub_nav[] = array(
			'name'            => __( 'Topics Replied To', 'bbpress' ),
			'slug'            => 'replies',
			'parent_url'      => $forums_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'bbp_member_forums_screen_replies',
			'position'        => 40,
			'item_css_id'     => 'replies'
		);

		// Favorite topics
		$sub_nav[] = array(
			'name'            => __( 'Favorites', 'bbpress' ),
			'slug'            => 'favorites',
			'parent_url'      => $forums_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'bbp_member_forums_screen_favorites',
			'position'        => 60,
			'item_css_id'     => 'favorites'
		);

		// Subscribed topics (my profile only)
		if ( bp_is_my_profile() ) {
			$sub_nav[] = array(
				'name'            => __( 'Subscriptions', 'bbpress' ),
				'slug'            => 'subscriptions',
				'parent_url'      => $forums_link,
				'parent_slug'     => $this->slug,
				'screen_function' => 'bbp_member_forums_screen_subscriptions',
				'position'        => 60,
				'item_css_id'     => 'subscriptions'
			);
		}

		parent::setup_nav( $main_nav, $sub_nav );
	}

	/**
	 * Set up the admin bar
	 *
	 * @since bbPress (r3552)
	 * @global BuddyPress $bp
	 */
	public function setup_admin_bar() {
		global $bp;

		// Prevent debug notices
		$wp_admin_nav = array();

		// Menus for logged in user
		if ( is_user_logged_in() ) {

			// Setup the logged in user variables
			$user_domain = bp_loggedin_user_domain();
			$forums_link = trailingslashit( $user_domain . $this->slug );

			// Add the "My Account" sub menus
			$wp_admin_nav[] = array(
				'parent' => $bp->my_account_menu_id,
				'id'     => 'my-account-' . $this->id,
				'title'  => __( 'Forums', 'bbpress' ),
				'href'   => trailingslashit( $forums_link )
			);

			// Topics
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-topics',
				'title'  => __( 'Topics Started', 'bbpress' ),
				'href'   => trailingslashit( $forums_link . 'topics' )
			);

			// Replies
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-replies',
				'title'  => __( 'Topics Replied To', 'bbpress' ),
				'href'   => trailingslashit( $forums_link . 'replies' )
			);

			// Favorites
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-favorites',
				'title'  => __( 'Favorite Topics', 'bbpress' ),
				'href'   => trailingslashit( $forums_link . 'favorites' )
			);

			// Subscriptions
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-subscriptions',
				'title'  => __( 'Subscribed Topics', 'bbpress' ),
				'href'   => trailingslashit( $forums_link . 'subscriptions' )
			);
		}

		parent::setup_admin_bar( $wp_admin_nav );
	}

	/**
	 * Sets up the title for pages and <title>
	 *
	 * @since bbPress (r3552)
	 *
	 * @global BuddyPress $bp
	 */
	public function setup_title() {
		global $bp;

		// Adjust title based on view
		if ( bp_is_forums_component() ) {
			if ( bp_is_my_profile() ) {
				$bp->bp_options_title = __( 'Forums', 'bbpress' );
			} else {
				$bp->bp_options_avatar = bp_core_fetch_avatar( array(
					'item_id' => $bp->displayed_user->id,
					'type'    => 'thumb'
				) );
				$bp->bp_options_title = $bp->displayed_user->fullname;
			}
		}

		parent::setup_title();
	}
}
endif;
