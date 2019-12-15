<?php

/**
 * bbPress BuddyPress Component Class
 *
 * bbPress and BuddyPress are designed to connect together seamlessly and
 * invisibly, and this is the hunk of code necessary to make that happen.
 *
 * The code in this BuddyPress Extension does some pretty complicated stuff,
 * far outside the realm of the simplicity bbPress is traditionally known for.
 *
 * While the rest of bbPress serves as an example of how to write pretty, simple
 * code, what's in these files is pure madness. It should not be used as an
 * example of anything other than successfully juggling chainsaws and puppy-dogs.
 *
 * @package bbPress
 * @subpackage BuddyPress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Forums_Component' ) ) :
/**
 * Loads Forums Component
 *
 * @since 2.1.0 bbPress (r3552)
 *
 * @package bbPress
 * @subpackage BuddyPress
 */
class BBP_Forums_Component extends BP_Component {

	/**
	 * Start the forums component creation process
	 *
	 * @since 2.1.0 bbPress (r3552)
	 */
	public function __construct() {
		parent::start(
			'forums',
			esc_html__( 'Forums', 'bbpress' ),
			bbpress()->includes_dir . 'extend/buddypress/'
		);
		$this->includes();
		$this->setup_globals();
		$this->setup_actions();
		$this->fully_loaded();
	}

	/**
	 * Include BuddyPress classes and functions
	 */
	public function includes( $includes = array() ) {

		// Helper BuddyPress functions
		$includes[] = 'functions.php';

		// Members modifications
		$includes[] = 'members.php';

		// BuddyPress Notfications Extension functions
		if ( bp_is_active( 'notifications' ) ) {
			$includes[] = 'notifications.php';
		}

		// BuddyPress Activity Extension class
		if ( bp_is_active( 'activity' ) ) {
			$includes[] = 'activity.php';
		}

		// BuddyPress Group Extension class
		if ( bbp_is_group_forums_active() && bp_is_active( 'groups' ) ) {
			$includes[] = 'groups.php';
		}

		// Require files if they exist
		foreach ( $includes as $file ) {
			if ( @is_file( $this->path . $file ) ) {
				require $this->path . $file;
			}
		}

		/**
		 * Hook for plugins to include files, if necessary.
		 *
		 * @since 2.6.0 bbPress (r3552)
		 */
		do_action( "bp_{$this->id}_includes" );
	}

	/**
	 * Setup globals
	 *
	 * The BP_FORUMS_SLUG constant is deprecated, and only used here for
	 * backwards compatibility.
	 *
	 * @since 2.1.0 bbPress (r3552)
	 */
	public function setup_globals( $args = array() ) {
		$bp = buddypress();

		// Define the parent forum ID
		if ( ! defined( 'BP_FORUMS_PARENT_FORUM_ID' ) ) {
			define( 'BP_FORUMS_PARENT_FORUM_ID', 1 );
		}

		// Define a slug, if necessary
		if ( ! defined( 'BP_FORUMS_SLUG' ) ) {
			define( 'BP_FORUMS_SLUG', $this->id );
		}

		// All arguments for forums component
		$args = array(
			'path'          => BP_PLUGIN_DIR,
			'slug'          => BP_FORUMS_SLUG,
			'root_slug'     => isset( $bp->pages->forums->slug ) ? $bp->pages->forums->slug : BP_FORUMS_SLUG,
			'has_directory' => false,
			'search_string' => esc_html__( 'Search Forums...', 'bbpress' ),
		);

		parent::setup_globals( $args );
	}

	/**
	 * Setup the actions
	 *
	 * @since 2.0.0 bbPress (r3395)
	 *
	 * @access private
	 * @link https://bbpress.trac.wordpress.org/ticket/2176
	 */
	public function setup_actions() {

		// Setup the components
		add_action( 'bp_init', array( $this, 'setup_components' ), 7 );

		parent::setup_actions();
	}

	/**
	 * Instantiate classes for BuddyPress integration
	 *
	 * @since 2.0.0 bbPress (r3395)
	 */
	public function setup_components() {

		// Always load the members component
		bbpress()->extend->buddypress->members = new BBP_BuddyPress_Members();

		// Create new activity class
		if ( bp_is_active( 'activity' ) ) {
			bbpress()->extend->buddypress->activity = new BBP_BuddyPress_Activity();
		}

		// Register the group extension only if groups are active
		if ( bbp_is_group_forums_active() && bp_is_active( 'groups' ) ) {
			bp_register_group_extension( 'BBP_Forums_Group_Extension' );
		}
	}

	/**
	 * Allow the variables, actions, and filters to be modified by third party
	 * plugins and themes.
	 *
	 * @since 2.1.0 bbPress (r3902)
	 */
	private function fully_loaded() {
		do_action_ref_array( 'bbp_buddypress_loaded', array( $this ) );
	}

	/**
	 * Setup BuddyBar navigation
	 *
	 * @since 2.1.0 bbPress (r3552)
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {

		// Stop if there is no user displayed or logged in
		if ( ! is_user_logged_in() && !bp_displayed_user_id() ) {
			return;
		}

		// Define local variable(s)
		$user_domain = '';

		// Add 'Forums' to the main navigation
		$main_nav = array(
			'name'                => esc_html__( 'Forums', 'bbpress' ),
			'slug'                => $this->slug,
			'position'            => 80,
			'screen_function'     => 'bbp_member_forums_screen_topics',
			'default_subnav_slug' => bbp_get_topic_archive_slug(),
			'item_css_id'         => $this->id
		);

		// Determine user to use
		if ( bp_displayed_user_id() ) {
			$user_domain = bp_displayed_user_domain();
		} elseif ( bp_loggedin_user_domain() ) {
			$user_domain = bp_loggedin_user_domain();
		} else {
			return;
		}

		// User link
		$forums_link = trailingslashit( $user_domain . $this->slug );

		// Topics started
		$sub_nav[] = array(
			'name'            => esc_html__( 'Topics Started', 'bbpress' ),
			'slug'            => bbp_get_topic_archive_slug(),
			'parent_url'      => $forums_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'bbp_member_forums_screen_topics',
			'position'        => 20,
			'item_css_id'     => 'topics'
		);

		// Replies to topics
		$sub_nav[] = array(
			'name'            => esc_html__( 'Replies Created', 'bbpress' ),
			'slug'            => bbp_get_reply_archive_slug(),
			'parent_url'      => $forums_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'bbp_member_forums_screen_replies',
			'position'        => 40,
			'item_css_id'     => 'replies'
		);

		// Engagements
		if ( bbp_is_engagements_active() ) {
			$sub_nav[] = array(
				'name'            => esc_html__( 'Engagements', 'bbpress' ),
				'slug'            => bbp_get_user_engagements_slug(),
				'parent_url'      => $forums_link,
				'parent_slug'     => $this->slug,
				'screen_function' => 'bbp_member_forums_screen_engagements',
				'position'        => 60,
				'item_css_id'     => 'engagements'
			);
		}

		// Favorite topics
		if ( bbp_is_favorites_active() ){
			$sub_nav[] = array(
				'name'            => esc_html__( 'Favorites', 'bbpress' ),
				'slug'            => bbp_get_user_favorites_slug(),
				'parent_url'      => $forums_link,
				'parent_slug'     => $this->slug,
				'screen_function' => 'bbp_member_forums_screen_favorites',
				'position'        => 80,
				'item_css_id'     => 'favorites'
			);
		}

		// Subscribed topics (my profile only)
		if ( bp_is_my_profile() && bbp_is_subscriptions_active() ) {
			$sub_nav[] = array(
				'name'            => esc_html__( 'Subscriptions', 'bbpress' ),
				'slug'            => bbp_get_user_subscriptions_slug(),
				'parent_url'      => $forums_link,
				'parent_slug'     => $this->slug,
				'screen_function' => 'bbp_member_forums_screen_subscriptions',
				'position'        => 100,
				'item_css_id'     => 'subscriptions'
			);
		}

		parent::setup_nav( $main_nav, $sub_nav );
	}

	/**
	 * Set up the admin bar
	 *
	 * @since 2.1.0 bbPress (r3552)
	 */
	public function setup_admin_bar( $wp_admin_nav = array() ) {

		// Menus for logged in user
		if ( is_user_logged_in() ) {

			// If BuddyPress is network activated and bbPress is
			// not activated on a the root blog but on any child one
			if ( ! bp_is_root_blog() ) {
				$user_id               = bbp_get_current_user_id();
				$my_account_link       = bbp_get_user_profile_url( $user_id );
				$my_topics_link        = bbp_get_user_topics_created_url( $user_id );
				$my_replies_link       = bbp_get_user_replies_created_url( $user_id );
				$my_engagements_link   = bbp_get_user_engagements_url( $user_id );
				$my_favorites_link     = bbp_get_favorites_permalink( $user_id );
				$my_subscriptions_link = bbp_get_subscriptions_permalink( $user_id );
			} else {

				// Setup the logged in user variables
				$user_domain = bp_loggedin_user_domain();
				$forums_link = trailingslashit( $user_domain . $this->slug );

				$my_account_link       = trailingslashit( $forums_link );
				$my_topics_link        = trailingslashit( $forums_link . bbp_get_topic_archive_slug() );
				$my_replies_link       = trailingslashit( $forums_link . bbp_get_reply_archive_slug() );
				$my_engagements_link   = trailingslashit( $forums_link . bbp_get_user_engagements_slug() );
				$my_favorites_link     = trailingslashit( $forums_link . bbp_get_user_favorites_slug() );
				$my_subscriptions_link = trailingslashit( $forums_link . bbp_get_user_subscriptions_slug() );
			}

			// Add the "My Account" sub menus
			$wp_admin_nav[] = array(
				'parent' => buddypress()->my_account_menu_id,
				'id'     => 'my-account-' . $this->id,
				'title'  => esc_html__( 'Forums', 'bbpress' ),
				'href'   => $my_account_link
			);

			// Topics
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-topics',
				'title'  => esc_html__( 'Topics Started', 'bbpress' ),
				'href'   => $my_topics_link
			);

			// Replies
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-replies',
				'title'  => esc_html__( 'Replies Created', 'bbpress' ),
				'href'   => $my_replies_link
			);

			// Engagements
			if ( bbp_is_engagements_active() ) {
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-engagements',
					'title'  => esc_html__( 'Engagements', 'bbpress' ),
					'href'   => $my_engagements_link
				);
			}

			// Favorites
			if ( bbp_is_favorites_active() ) {
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-favorites',
					'title'  => esc_html__( 'Favorite Topics', 'bbpress' ),
					'href'   => $my_favorites_link
				);
			}

			// Subscriptions
			if ( bbp_is_subscriptions_active() ) {
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-subscriptions',
					'title'  => esc_html__( 'Subscribed Topics', 'bbpress' ),
					'href'   => $my_subscriptions_link
				);
			}
		}

		parent::setup_admin_bar( $wp_admin_nav );
	}

	/**
	 * Sets up the title for pages and <title>
	 *
	 * @since 2.1.0 bbPress (r3552)
	 */
	public function setup_title() {
		$bp = buddypress();

		// Adjust title based on view
		if ( bp_is_forums_component() ) {
			if ( bp_is_my_profile() ) {
				$bp->bp_options_title = esc_html__( 'Forums', 'bbpress' );
			} elseif ( bp_is_user() ) {
				$bp->bp_options_avatar = bp_core_fetch_avatar( array(
					'item_id' => bp_displayed_user_id(),
					'type'    => 'thumb'
				) );
				$bp->bp_options_title = bp_get_displayed_user_fullname();
			}
		}

		parent::setup_title();
	}
}
endif;
