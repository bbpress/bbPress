<?php

/**
 * The bbPress Plugin.
 *
 * bbPress is forum software with a twist from the creators of WordPress.
 *
 * $Id$
 *
 * @package bbPress
 * @subpackage Main
 */

/**
 * Plugin Name:       bbPress
 * Plugin URI:        https://bbpress.org
 * Description:       bbPress is forum software with a twist from the creators of WordPress.
 * Author:            The bbPress Contributors
 * Author URI:        https://bbpress.org
 * License:           GNU General Public License v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bbpress
 * Domain Path:       /languages/
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Version:           2.7.0-alpha-4
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'bbPress' ) ) :
/**
 * Main bbPress Class.
 *
 * "Word hard. Stay bumble."
 *
 * @since 2.0.0 bbPress (r2594)
 * @property string $version Plugin version.
 * @property string $db_version Database version.
 * @property string $file Main plugin file.
 * @property string $basename Plugin basename.
 * @property string $basepath Path relative to the plugins directory.
 * @property string $plugin_dir Plugin directory.
 * @property string $plugin_url Plugin URL.
 * @property string $includes_dir Directory containing bbPress includes.
 * @property string $includes_url URL for bbPress includes.
 * @property string $lang_base Relative language path.
 * @property string $lang_dir Language directory.
 * @property string $themes_dir Template directory.
 * @property string $themes_url Template URL.
 * @property string $forum_post_type Forum post type.
 * @property string $topic_post_type Topic post type.
 * @property string $reply_post_type Reply post type.
 * @property string $topic_tag_tax_id Topic tag taxonomy.
 * @property string $spam_status_id Spam status.
 * @property string $closed_status_id Closed status.
 * @property string $orphan_status_id Orphan status.
 * @property string $public_status_id Public status.
 * @property string $pending_status_id Pending status.
 * @property string $private_status_id Private status.
 * @property string $hidden_status_id Hidden status.
 * @property string $trash_status_id Trash status.
 * @property string $user_id User identifier.
 * @property string $tops_id Topic identifier.
 * @property string $reps_id Reply identifier.
 * @property string $favs_id Favorites identifier.
 * @property string $subs_id Subscriptions identifier.
 * @property string $view_id View identifier.
 * @property string $edit_id Edit identifier.
 * @property string $paged_id Pagination identifier.
 * @property string $search_id Search identifier.
 * @property string $engagements_id Engagements identifier.
 * @property string $mentions_id Mentions identifier.
 * @property string $unsubscribe_id Unsubscribe identifier.
 * @property int $current_forum_id Current forum ID.
 * @property int $current_topic_id Current topic ID.
 * @property int $current_reply_id Current reply ID.
 * @property int $current_user_id Current user ID.
 * @property WP_Query $forum_query Main forum query.
 * @property WP_Query $topic_query Main topic query.
 * @property WP_Query $reply_query Main reply query.
 * @property WP_Query $search_query Main search query.
 * @property BBP_User_Query $user_query Main user query.
 * @property BBP_Term_Query $term_query Main term query.
 * @property stdClass $theme_compat Theme compatibility data.
 * @property stdClass $filters Registered filter data.
 * @property string $domain Text domain.
 * @property WP_Error $errors Error queue.
 * @property int $tab_index Default tab index.
 * @property BBP_Shortcodes $shortcodes Shortcode handler.
 * @property BBP_Blocks $blocks Block handler.
 */
final class bbPress {

	/** Magic *****************************************************************/

	/**
	 * bbPress uses many variables, several of which can be filtered to
	 * customize the way it operates. Most of those variables are stored in this
	 * private array via PHP magic methods.
	 *
	 * This is a precautionary measure, to avoid potential errors produced by
	 * unanticipated direct manipulation of critical run-time data.
	 *
	 * @var array<string, mixed> All run-time data.
	 */
	private $data = array();

	/** Not Magic *************************************************************/

	/**
	 * Current user after setup, or false before setup.
	 *
	 * @var WP_User|false
	 */
	public $current_user = false;

	/**
	 * Instances attached by integrations such as Akismet and BuddyPress.
	 *
	 * @var stdClass
	 */
	public $extend;

	/**
	 * Topic views.
	 *
	 * @var array<string, array{title: string, query: array<string, mixed>, feed: bool}>
	 */
	public $views = array();

	/**
	 * Registered engagement types.
	 *
	 * @var array<string, BBP_User_Engagements_Type>
	 */
	public $engagement_types = array();

	/**
	 * Option overrides used by get_option().
	 *
	 * @var array<string, mixed>
	 */
	public $options = array();

	/**
	 * Options unavailable from the database.
	 *
	 * @var array<string, mixed>
	 */
	public $not_options = array();

	/**
	 * User metadata overrides used by get_user_meta().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $user_options = array();

	/**
	 * Dynamically initialized user roles.
	 *
	 * @var array<string, WP_Role>
	 */
	public $roles = array();

	/** Singleton *************************************************************/

	/**
	 * Main bbPress Instance.
	 *
	 * bbPress is fun.
	 * Please load it only one time.
	 * For this, we thank you.
	 *
	 * Insures that only one instance of bbPress exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since 2.1.0 bbPress (r3758)
	 *
	 * @staticvar bbPress|null $instance Singleton instance, created on first use.
	 * @see bbpress()
	 * @return bbPress The one true bbPress.
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication
		static $instance = null;

		// Only run these methods if they haven't been ran previously
		if ( null === $instance ) {
			$instance = new bbPress();
			$instance->setup_environment();
			$instance->includes();
			$instance->setup_variables();
			$instance->setup_actions();
		}

		// Always return the instance
		return $instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent bbPress from being loaded more than once.
	 *
	 * @since 2.0.0 bbPress (r3071)
	 *
	 * @see bbPress::instance()
	 * @see bbpress();
	 */
	private function __construct() {
		/* Do nothing here */
	}

	/**
	 * A dummy magic method to prevent bbPress from being cloned.
	 *
	 * @since 2.1.0 bbPress (r3758)
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'bbpress' ), '2.1' );
	}

	/**
	 * A dummy magic method to prevent bbPress from being unserialized.
	 *
	 * @since 2.1.0 bbPress (r3758)
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'bbpress' ), '2.1' );
	}

	/**
	 * Magic method for checking the existence of a certain custom field.
	 *
	 * @since 2.1.0 bbPress (r3951)
	 *
	 * @param string $key Runtime data key.
	 * @return bool Whether the key has a non-null value.
	 */
	public function __isset( $key ) {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Magic method for getting bbPress variables.
	 *
	 * @since 2.1.0 bbPress (r3951)
	 *
	 * @param string $key Runtime data key.
	 * @return mixed Stored value, or null if the key is not set.
	 */
	public function __get( $key ) {
		return isset( $this->data[ $key ] )
			? $this->data[ $key ]
			: null;
	}

	/**
	 * Magic method for setting bbPress variables.
	 *
	 * @since 2.1.0 bbPress (r3951)
	 *
	 * @param string $key   Runtime data key.
	 * @param mixed  $value Value to store.
	 */
	public function __set( $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * Magic method for unsetting bbPress variables.
	 *
	 * @since 2.3.0 bbPress (r4629)
	 *
	 * @param string $key Runtime data key to remove.
	 */
	public function __unset( $key ) {
		if ( isset( $this->data[ $key ] ) ) {
			unset( $this->data[ $key ] );
		}
	}

	/**
	 * Magic method to prevent notices and errors from invalid method calls.
	 *
	 * @since 2.2.0 bbPress (r4253)
	 *
	 * @param string $name Name of the inaccessible method.
	 * @param array<int, mixed> $args Arguments passed to the method.
	 * @return null
	 */
	public function __call( $name = '', $args = array() ) {
		unset( $name, $args );
		return null;
	}

	/** Private Methods *******************************************************/

	/**
	 * Setup the environment variables to allow the rest of bbPress to function
	 * more easily.
	 *
	 * @since 2.6.0 bbPress (r6331)
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function setup_environment() {

		/** Versions **********************************************************/

		$this->version    = '2.7.0-alpha-4';
		$this->db_version = '271';

		/** Paths *************************************************************/

		// File & base
		$this->file         = __FILE__;

		/**
		 * Filters the bbPress plugin basename.
		 *
		 * @since 2.5.13 bbPress (r5316)
		 *
		 * @param string $value The bbPress plugin basename.
		 */
		$this->basename     = apply_filters( 'bbp_plugin_basename', str_replace( array( 'build/', 'src/' ), '', plugin_basename( $this->file ) ) );

		/**
		 * Filters the relative bbPress plugin base path.
		 *
		 * @since 2.6.0 bbPress (r6508)
		 *
		 * @param string $value The relative bbPress plugin base path.
		 */
		$this->basepath     = apply_filters( 'bbp_plugin_basepath', trailingslashit( dirname( $this->basename ) ) );

		// Path and URL

		/**
		 * Filters the bbPress plugin directory path.
		 *
		 * @since 2.1.0 bbPress (r3905)
		 *
		 * @param string $value The bbPress plugin directory path.
		 */
		$this->plugin_dir   = apply_filters( 'bbp_plugin_dir_path', plugin_dir_path( $this->file ) );

		/**
		 * Filters the bbPress plugin directory URL.
		 *
		 * @since 2.1.0 bbPress (r3905)
		 *
		 * @param string $value The bbPress plugin directory URL.
		 */
		$this->plugin_url   = apply_filters( 'bbp_plugin_dir_url',  plugin_dir_url ( $this->file ) );

		// Includes

		/**
		 * Filters the path to the bbPress includes directory.
		 *
		 * @since 2.2.0 bbPress (r4248)
		 *
		 * @param string $value The path to the bbPress includes directory.
		 */
		$this->includes_dir = apply_filters( 'bbp_includes_dir', trailingslashit( $this->plugin_dir . 'includes'  ) );

		/**
		 * Filters the URL of the bbPress includes directory.
		 *
		 * @since 2.2.0 bbPress (r4248)
		 *
		 * @param string $value The URL of the bbPress includes directory.
		 */
		$this->includes_url = apply_filters( 'bbp_includes_url', trailingslashit( $this->plugin_url . 'includes'  ) );

		// Languages

		/**
		 * Filters the relative path to bbPress languages.
		 *
		 * @since 2.6.0 bbPress (r6507)
		 *
		 * @param string $value The relative path to bbPress languages.
		 */
		$this->lang_base    = apply_filters( 'bbp_lang_base',    trailingslashit( $this->basepath   . 'languages' ) );

		/**
		 * Filters the path to the bbPress languages directory.
		 *
		 * @since 2.1.0 bbPress (r3905)
		 *
		 * @param string $value The path to the bbPress languages directory.
		 */
		$this->lang_dir     = apply_filters( 'bbp_lang_dir',     trailingslashit( $this->plugin_dir . 'languages' ) );

		// Templates

		/**
		 * Filters the path to the bbPress templates directory.
		 *
		 * @since 2.1.0 bbPress (r3905)
		 *
		 * @param string $value The path to the bbPress templates directory.
		 */
		$this->themes_dir   = apply_filters( 'bbp_themes_dir',   trailingslashit( $this->plugin_dir . 'templates' ) );

		/**
		 * Filters the URL of the bbPress templates directory.
		 *
		 * @since 2.1.0 bbPress (r3905)
		 *
		 * @param string $value The URL of the bbPress templates directory.
		 */
		$this->themes_url   = apply_filters( 'bbp_themes_url',   trailingslashit( $this->plugin_url . 'templates' ) );
	}

	/**
	 * Smart defaults to many bbPress specific class variables.
	 *
	 * @since 2.6.0 bbPress (r6331)
	 *
	 * @return void
	 */
	private function setup_variables() {

		/** Identifiers *******************************************************/

		// Post type identifiers

		/**
		 * Filters the forum post type identifier.
		 *
		 * @since 2.0.0 bbPress (r2464)
		 *
		 * @param string $post_type The forum post type identifier.
		 */
		$this->forum_post_type   = apply_filters( 'bbp_forum_post_type',  'forum'     );

		/**
		 * Filters the topic post type identifier.
		 *
		 * @since 2.0.0 bbPress (r2464)
		 *
		 * @param string $post_type The topic post type identifier.
		 */
		$this->topic_post_type   = apply_filters( 'bbp_topic_post_type',  'topic'     );

		/**
		 * Filters the topic tag tax query variable name.
		 *
		 * @since 2.0.0 bbPress (r3348)
		 *
		 * @param string $query_var The topic tag tax query variable name.
		 */
		$this->topic_tag_tax_id  = apply_filters( 'bbp_topic_tag_tax_id', 'topic-tag' );

		/**
		 * Filters the reply post type identifier.
		 *
		 * @since 2.0.0 bbPress (r2557)
		 *
		 * @param string $post_type The reply post type identifier.
		 */
		$this->reply_post_type   = apply_filters( 'bbp_reply_post_type',  'reply'     );

		// Status identifiers

		/**
		 * Filters the spam post status identifier.
		 *
		 * @since 2.0.0 bbPress (r2727)
		 *
		 * @param string $status The spam post status identifier.
		 */
		$this->spam_status_id    = apply_filters( 'bbp_spam_post_status',    'spam'    );

		/**
		 * Filters the closed post status identifier.
		 *
		 * @since 2.0.0 bbPress (r2727)
		 *
		 * @param string $status The closed post status identifier.
		 */
		$this->closed_status_id  = apply_filters( 'bbp_closed_post_status',  'closed'  );

		/**
		 * Filters the orphan post status identifier.
		 *
		 * @since 2.0.0 bbPress (r3018)
		 *
		 * @param string $status The orphan post status identifier.
		 */
		$this->orphan_status_id  = apply_filters( 'bbp_orphan_post_status',  'orphan'  );

		/**
		 * Filters the public post status identifier.
		 *
		 * @since 2.0.0 bbPress (r3505)
		 *
		 * @param string $status The public post status identifier.
		 */
		$this->public_status_id  = apply_filters( 'bbp_public_post_status',  'publish' );

		/**
		 * Filters the pending post status identifier.
		 *
		 * @since 2.1.0 bbPress (r3581)
		 *
		 * @param string $status The pending post status identifier.
		 */
		$this->pending_status_id = apply_filters( 'bbp_pending_post_status', 'pending' );

		/**
		 * Filters the private post status identifier.
		 *
		 * @since 2.0.0 bbPress (r3505)
		 *
		 * @param string $status The private post status identifier.
		 */
		$this->private_status_id = apply_filters( 'bbp_private_post_status', 'private' );

		/**
		 * Filters the hidden post status identifier.
		 *
		 * @since 2.0.0 bbPress (r3082)
		 *
		 * @param string $status The hidden post status identifier.
		 */
		$this->hidden_status_id  = apply_filters( 'bbp_hidden_post_status',  'hidden'  );

		/**
		 * Filters the trash post status identifier.
		 *
		 * @since 2.0.0 bbPress (r3505)
		 *
		 * @param string $status The trash post status identifier.
		 */
		$this->trash_status_id   = apply_filters( 'bbp_trash_post_status',   'trash'   );

		// Other identifiers

		/**
		 * Filters the user query variable name.
		 *
		 * @since 2.0.0 bbPress (r2688)
		 *
		 * @param string $query_var The user query variable name.
		 */
		$this->user_id           = apply_filters( 'bbp_user_id',        'bbp_user'        );

		/**
		 * Filters the tops query variable name.
		 *
		 * @since 2.2.0 bbPress (r4321)
		 *
		 * @param string $query_var The tops query variable name.
		 */
		$this->tops_id           = apply_filters( 'bbp_tops_id',        'bbp_tops'        );

		/**
		 * Filters the reps query variable name.
		 *
		 * @since 2.2.0 bbPress (r4321)
		 *
		 * @param string $query_var The reps query variable name.
		 */
		$this->reps_id           = apply_filters( 'bbp_reps_id',        'bbp_reps'        );

		/**
		 * Filters the favs query variable name.
		 *
		 * @since 2.2.0 bbPress (r4190)
		 *
		 * @param string $query_var The favs query variable name.
		 */
		$this->favs_id           = apply_filters( 'bbp_favs_id',        'bbp_favs'        );

		/**
		 * Filters the subs query variable name.
		 *
		 * @since 2.2.0 bbPress (r4190)
		 *
		 * @param string $query_var The subs query variable name.
		 */
		$this->subs_id           = apply_filters( 'bbp_subs_id',        'bbp_subs'        );

		/**
		 * Filters the view query variable name.
		 *
		 * @since 2.0.0 bbPress (r2789)
		 *
		 * @param string $query_var The view query variable name.
		 */
		$this->view_id           = apply_filters( 'bbp_view_id',        'bbp_view'        );

		/**
		 * Filters the edit query variable name.
		 *
		 * @since 2.0.0 bbPress (r3333)
		 *
		 * @param string $query_var The edit query variable name.
		 */
		$this->edit_id           = apply_filters( 'bbp_edit_id',        'edit'            );

		/**
		 * Filters the pagination query variable name.
		 *
		 * @since 2.4.0 bbPress (r4930)
		 *
		 * @param string $query_var The pagination query variable name.
		 */
		$this->paged_id          = apply_filters( 'bbp_paged_id',       'paged'           );

		/**
		 * Filters the search query variable name.
		 *
		 * @since 2.3.0 bbPress (r4579)
		 *
		 * @param string $query_var The search query variable name.
		 */
		$this->search_id         = apply_filters( 'bbp_search_id',      'bbp_search'      );

		/**
		 * Filters the engagements query variable name.
		 *
		 * @since 2.6.0 bbPress (r6320)
		 *
		 * @param string $query_var The engagements query variable name.
		 */
		$this->engagements_id    = apply_filters( 'bbp_engagements_id', 'bbp_engagements' );

		/**
		 * Filters the mentions query variable name.
		 *
		 * @since 2.7.0 bbPress (r8264)
		 *
		 * @param string $query_var The mentions query variable name.
		 */
		$this->mentions_id       = apply_filters( 'bbp_mentions_id',    'bbp_mentions'    );

		/**
		 * Filters the unsubscribe query variable name.
		 *
		 * @since 2.7.0 bbPress (r8349)
		 *
		 * @param string $query_var The unsubscribe query variable name.
		 */
		$this->unsubscribe_id    = apply_filters( 'bbp_unsubscribe_id', 'bbp_unsubscribe' );

		/** Queries ***********************************************************/

		$this->current_view_id      = 0; // Current view id
		$this->current_forum_id     = 0; // Current forum id
		$this->current_topic_id     = 0; // Current topic id
		$this->current_reply_id     = 0; // Current reply id
		$this->current_topic_tag_id = 0; // Current topic tag id
		$this->current_user_id      = 0; // Current topic tag id

		$this->forum_query    = new WP_Query();           // Main forum query
		$this->topic_query    = new WP_Query();           // Main topic query
		$this->reply_query    = new WP_Query();           // Main reply query
		$this->search_query   = new WP_Query();           // Main search query
		$this->user_query     = new BBP_User_Query();     // Main user query
		$this->term_query     = new BBP_Term_Query();     // Main term query
		$this->taxonomy_query = new BBP_Taxonomy_Query(); // Main taxonomy query

		/** Theme Compat ******************************************************/

		$this->theme_compat   = new stdClass(); // Base theme compatibility class
		$this->filters        = new stdClass(); // Used when adding/removing filters

		/** Users *************************************************************/

		$this->current_user   = new WP_User(); // Currently logged in user
		$this->displayed_user = new WP_User(); // Currently displayed user

		/** Misc **************************************************************/

		$this->domain         = 'bbpress';      // Unique identifier for retrieving translated strings
		$this->extend         = new stdClass(); // Plugins add data here
		$this->errors         = new WP_Error(); // Feedback

		/** Deprecated ********************************************************/

		/**
		 * Filters the starting tab index for bbPress forms.
		 *
		 * @since 2.0.0 bbPress (r2811)
		 *
		 * @param int $tab_index The starting tab index for bbPress forms.
		 */
		$this->tab_index      = apply_filters( 'bbp_default_tab_index', 100 );
	}

	/**
	 * Include required files.
	 *
	 * @since 2.0.0 bbPress (r3334)
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function includes() {

		/** Core **************************************************************/

		require $this->includes_dir . 'core/abstraction.php';
		require $this->includes_dir . 'core/sub-actions.php';
		require $this->includes_dir . 'core/functions.php';
		require $this->includes_dir . 'core/cache.php';
		require $this->includes_dir . 'core/options.php';
		require $this->includes_dir . 'core/capabilities.php';
		require $this->includes_dir . 'core/update.php';
		require $this->includes_dir . 'core/template-functions.php';
		require $this->includes_dir . 'core/template-loader.php';
		require $this->includes_dir . 'core/theme-compat.php';

		/** Components ********************************************************/

		// Common
		require $this->includes_dir . 'common/ajax.php';
		require $this->includes_dir . 'common/blocks.php';
		require $this->includes_dir . 'common/classes.php';
		require $this->includes_dir . 'common/functions.php';
		require $this->includes_dir . 'common/formatting.php';
		require $this->includes_dir . 'common/locale.php';
		require $this->includes_dir . 'common/locks.php';
		require $this->includes_dir . 'common/rest.php';
		require $this->includes_dir . 'common/attachments.php';
		require $this->includes_dir . 'common/xmlrpc.php';
		require $this->includes_dir . 'common/template.php';
		require $this->includes_dir . 'common/widgets.php';
		require $this->includes_dir . 'common/shortcodes.php';

		// Forums
		require $this->includes_dir . 'forums/capabilities.php';
		require $this->includes_dir . 'forums/functions.php';
		require $this->includes_dir . 'forums/template.php';

		// Topics
		require $this->includes_dir . 'topics/capabilities.php';
		require $this->includes_dir . 'topics/functions.php';
		require $this->includes_dir . 'topics/template.php';

		// Replies
		require $this->includes_dir . 'replies/capabilities.php';
		require $this->includes_dir . 'replies/functions.php';
		require $this->includes_dir . 'replies/template.php';

		// Search
		require $this->includes_dir . 'search/functions.php';
		require $this->includes_dir . 'search/template.php';

		// Users
		require $this->includes_dir . 'users/capabilities.php';
		require $this->includes_dir . 'users/classes/class-bbp-user-engagements-strategy-base.php';
		require $this->includes_dir . 'users/classes/class-bbp-user-engagements-strategy-meta.php';
		require $this->includes_dir . 'users/classes/class-bbp-user-engagements-strategy-term.php';
		require $this->includes_dir . 'users/classes/class-bbp-user-engagements-strategy-user.php';
		require $this->includes_dir . 'users/classes/class-bbp-user-engagements-type.php';
		require $this->includes_dir . 'users/classes/class-bbp-user-engagements-type-post.php';
		require $this->includes_dir . 'users/classes/class-bbp-user-engagements-type-term.php';
		require $this->includes_dir . 'users/engagements.php';
		require $this->includes_dir . 'users/favorites.php';
		require $this->includes_dir . 'users/subscriptions.php';
		require $this->includes_dir . 'users/notifications.php';
		require $this->includes_dir . 'users/mentions.php';
		require $this->includes_dir . 'users/functions.php';
		require $this->includes_dir . 'users/template.php';
		require $this->includes_dir . 'users/options.php';
		require $this->includes_dir . 'users/signups.php';

		/** Hooks *************************************************************/

		require $this->includes_dir . 'core/extend.php';
		require $this->includes_dir . 'core/actions.php';
		require $this->includes_dir . 'core/filters.php';

		/** Admin *************************************************************/

		// Quick admin check and load if needed
		if ( is_admin() ) {
			require $this->includes_dir . 'admin/icons.php';
			require $this->includes_dir . 'admin/actions.php';
		}

		// Command-line tools
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require $this->includes_dir . 'cli.php';
		}
	}

	/**
	 * Setup the default hooks and actions.
	 *
	 * @since 2.0.0 bbPress (r3334)
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function setup_actions() {

		// Add actions to plugin activation and deactivation hooks
		add_action( 'activate_'   . $this->basename, 'bbp_activation'   );
		add_action( 'deactivate_' . $this->basename, 'bbp_deactivation' );

		// If bbPress is being deactivated, do not add any actions
		if ( bbp_is_deactivation( $this->basename ) ) {
			return;
		}

		// Array of bbPress core actions
		$actions = array(
			'setup_theme',              // Setup the default theme compat
			'setup_current_user',       // Setup currently logged in user
			'setup_engagements',        // Setup user engagements strategy
			'roles_init',               // User roles init
			'register_meta',            // Register meta (forum|topic|reply|user)
			'register_post_types',      // Register post types (forum|topic|reply)
			'register_post_statuses',   // Register post statuses (closed|spam|orphan|hidden)
			'register_taxonomies',      // Register taxonomies (topic-tag)
			'register_shortcodes',      // Register shortcodes (bbp-login)
			'register_blocks',          // Register blocks (bbp-login)
			'register_views',           // Register the views (no-replies)
			'register_engagements',     // Register engagements (types)
			'register_theme_packages',  // Register bundled theme packages (bbp-theme-compat/bbp-themes)
			'load_textdomain',          // Load textdomain (bbpress)
			'add_rewrite_tags',         // Add rewrite tags (view|user|edit|search)
			'add_rewrite_rules',        // Generate rewrite rules (view|edit|paged|search)
			'add_permastructs'          // Add permalink structures (view|user|search)
		);

		// Add the actions
		foreach ( $actions as $class_action ) {
			add_action( 'bbp_' . $class_action, array( $this, $class_action ), 5 );
		}

		// All bbPress actions are setup (includes bbp-core-hooks.php)
		/**
		 * Fires after bbPress actions are set up.
		 *
		 * @since 2.1.0 bbPress (r3692)
		 *
		 * @param object $instance The component instance.
		 */
		do_action_ref_array( 'bbp_after_setup_actions', array( &$this ) );
	}

	/** Public Methods ********************************************************/

	/**
	 * Register bundled theme packages.
	 *
	 * Note that since we currently have complete control over bbp-themes and
	 * the bbp-theme-compat folders, it's fine to hardcode these here. If at a
	 * later date we need to automate this, and API will need to be built.
	 *
	 * @since 2.1.0 bbPress (r3830)
	 *
	 * @return void
	 */
	public function register_theme_packages() {

		// Register the basic theme stack. This is really dope.
		bbp_register_template_stack( 'get_stylesheet_directory', 6 );
		bbp_register_template_stack( 'get_template_directory',   8 );

		// Register the default theme compatibility package
		bbp_register_theme_package(
			array(
				'id'      => 'default',
				'name'    => 'bbPress Default',
				'version' => bbp_get_version(),
				'dir'     => trailingslashit( $this->themes_dir . 'default' ),
				'url'     => trailingslashit( $this->themes_url . 'default' )
			)
		);
	}

	/**
	 * Setup the default bbPress theme compatibility location.
	 *
	 * @since 2.1.0 bbPress (r3779)
	 *
	 * @return void
	 */
	public function setup_theme() {
		bbp_setup_theme_compat( bbp_get_theme_package_id() );
	}

	/**
	 * Load the translation file for current language. Checks the deprecated
	 * languages folder inside the bbPress plugin first, and then the default
	 * WordPress languages folder.
	 *
	 * Note that custom translation files inside the bbPress plugin folder
	 * will be removed on bbPress updates. If you're creating custom
	 * translation files, please use the global language folder.
	 *
	 * @since 2.0.0 bbPress (r3333)
	 *
	 * @return void
	 */
	public function load_textdomain() {

		// Define the old directory
		$old_dir = WP_LANG_DIR . '/bbpress/';

		// Old location, deprecated in 2.6.0
		if ( is_dir( $old_dir ) ) {

			// Get locale & file-name
			$type   = is_admin() ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $type, $this->domain );
			$mofile = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

			// Look in global /wp-content/languages/bbpress/ folder
			load_textdomain( $this->domain, $old_dir . $mofile );
		}

		// Look in global /wp-content/languages/plugins/
		load_plugin_textdomain( $this->domain, false, $this->lang_base );
	}

	/**
	 * Setup the post types for forums, topics and replies.
	 *
	 * @since 2.0.0 bbPress (r2597)
	 *
	 * @return void
	 */
	public static function register_post_types() {
		// Keep Dashicons on WordPress versions without SVG admin menu support.
		$menu_icon_prefix = is_admin() && bbp_use_svg_admin_icons() ? 'bbpress/' : '';

		/** Forums ************************************************************/

		// Register Forum content type
		register_post_type(
			bbp_get_forum_post_type(),
			/**
			 * Filters the arguments used to register the forum post type.
			 *
			 * @since 2.0.0 bbPress (r2464)
			 *
			 * @param array $args The arguments used to register the forum post type.
			 */
			apply_filters(
				'bbp_register_forum_post_type',
				array(
					'labels'                => bbp_get_forum_post_type_labels(),
					'rewrite'               => bbp_get_forum_post_type_rewrite(),
					'supports'              => bbp_get_forum_post_type_supports(),
					'description'           => esc_html__( 'bbPress Forums', 'bbpress' ),
					'capabilities'          => bbp_get_forum_caps(),
					'capability_type'       => array( 'forum', 'forums' ),
					'menu_position'         => 555555,
					'has_archive'           => bbp_get_root_slug(),
					'exclude_from_search'   => true,
					'show_in_nav_menus'     => true,
					'public'                => true,
					'show_ui'               => current_user_can( 'bbp_forums_admin' ),
					'show_in_rest'          => true,
					'rest_controller_class' => 'BBP_REST_Posts_Controller',
					'can_export'            => true,
					'hierarchical'          => true,
					'query_var'             => true,
					'menu_icon'             => $menu_icon_prefix ? $menu_icon_prefix . 'forum' : '',

					// bbPress
					'source'                => 'bbpress',
					'bbp_subscriptions'     => true
				)
			)
		);

		/** Topics ************************************************************/

		// Register Topic content type
		register_post_type(
			bbp_get_topic_post_type(),
			/**
			 * Filters the arguments used to register the topic post type.
			 *
			 * @since 2.0.0 bbPress (r2464)
			 *
			 * @param array $args The arguments used to register the topic post type.
			 */
			apply_filters(
				'bbp_register_topic_post_type',
				array(
					'labels'                => bbp_get_topic_post_type_labels(),
					'rewrite'               => bbp_get_topic_post_type_rewrite(),
					'supports'              => bbp_get_topic_post_type_supports(),
					'description'           => esc_html__( 'bbPress Topics', 'bbpress' ),
					'capabilities'          => bbp_get_topic_caps(),
					'capability_type'       => array( 'topic', 'topics' ),
					'menu_position'         => 555555,
					'has_archive'           => ( 'forums' === bbp_show_on_root() ) ? bbp_get_topic_archive_slug() : false,
					'exclude_from_search'   => true,
					'show_in_nav_menus'     => false,
					'public'                => true,
					'show_ui'               => current_user_can( 'bbp_topics_admin' ),
					'show_in_rest'          => true,
					'rest_controller_class' => 'BBP_REST_Posts_Controller',
					'can_export'            => true,
					'hierarchical'          => false,
					'query_var'             => true,
					'menu_icon'             => $menu_icon_prefix ? $menu_icon_prefix . 'topic' : '',

					// bbPress
					'source'                => 'bbpress',
					'bbp_subscriptions'     => true
				)
			)
		);

		/** Replies ***********************************************************/

		// Register reply content type
		register_post_type(
			bbp_get_reply_post_type(),
			/**
			 * Filters the arguments used to register the reply post type.
			 *
			 * @since 2.0.0 bbPress (r2597)
			 *
			 * @param array $args The arguments used to register the reply post type.
			 */
			apply_filters(
				'bbp_register_reply_post_type',
				array(
					'labels'                => bbp_get_reply_post_type_labels(),
					'rewrite'               => bbp_get_reply_post_type_rewrite(),
					'supports'              => bbp_get_reply_post_type_supports(),
					'description'           => esc_html__( 'bbPress Replies', 'bbpress' ),
					'capabilities'          => bbp_get_reply_caps(),
					'capability_type'       => array( 'reply', 'replies' ),
					'menu_position'         => 555555,
					'exclude_from_search'   => true,
					'has_archive'           => false,
					'show_in_nav_menus'     => false,
					'public'                => true,
					'show_ui'               => current_user_can( 'bbp_replies_admin' ),
					'show_in_rest'          => true,
					'rest_controller_class' => 'BBP_REST_Posts_Controller',
					'can_export'            => true,
					'hierarchical'          => false,
					'query_var'             => true,
					'menu_icon'             => $menu_icon_prefix ? $menu_icon_prefix . 'reply' : '',

					// bbPress
					'source'                => 'bbpress',
				)
			)
		);
	}

	/**
	 * Register the post statuses used by bbPress.
	 *
	 * We do some manipulation of the 'trash' status so trashed topics and
	 * replies can be viewed from within the theme.
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @global stdClass[] $wp_post_statuses Registered post status objects, keyed by status name.
	 *
	 * @return void
	 */
	public static function register_post_statuses() {

		// Closed
		register_post_status(
			bbp_get_closed_status_id(),
			/**
			 * Filters the arguments used to register the closed post status.
			 *
			 * @since 2.0.0 bbPress (r2727)
			 *
			 * @param array $args The arguments used to register the closed post status.
			 */
			apply_filters(
				'bbp_register_closed_post_status',
				array(
					'label'                     => _x( 'Closed', 'post', 'bbpress' ),
					/* translators: %s: Number of closed items */
					'label_count'               => _nx_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'post', 'bbpress' ),
					'public'                    => true,
					'show_in_admin_status_list' => true,
					'show_in_admin_all_list'    => true,
					'source'                    => 'bbpress'
				)
			)
		);

		// Spam
		register_post_status(
			bbp_get_spam_status_id(),
			/**
			 * Filters the arguments used to register the spam post status.
			 *
			 * @since 2.0.0 bbPress (r2727)
			 *
			 * @param array $args The arguments used to register the spam post status.
			 */
			apply_filters(
				'bbp_register_spam_post_status',
				array(
					'label'                     => _x( 'Spam', 'post', 'bbpress' ),
					/* translators: %s: Number of spammed items */
					'label_count'               => _nx_noop( 'Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>', 'post', 'bbpress' ),
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_status_list' => true,
					'show_in_admin_all_list'    => false,
					'source'                    => 'bbpress'
				)
			)
		);

		// Orphan
		register_post_status(
			bbp_get_orphan_status_id(),
			/**
			 * Filters the arguments used to register the orphan post status.
			 *
			 * @since 2.0.0 bbPress (r3018)
			 *
			 * @param array $args The arguments used to register the orphan post status.
			 */
			apply_filters(
				'bbp_register_orphan_post_status',
				array(
					'label'                     => _x( 'Orphan', 'post', 'bbpress' ),
					/* translators: %s: Number of orphaned items */
					'label_count'               => _nx_noop( 'Orphan <span class="count">(%s)</span>', 'Orphans <span class="count">(%s)</span>', 'post', 'bbpress' ),
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_status_list' => true,
					'show_in_admin_all_list'    => false,
					'source'                    => 'bbpress'
				)
			)
		);

		// Hidden
		register_post_status(
			bbp_get_hidden_status_id(),
			/**
			 * Filters the arguments used to register the hidden post status.
			 *
			 * @since 2.0.0 bbPress (r3079)
			 *
			 * @param array $args The arguments used to register the hidden post status.
			 */
			apply_filters(
				'bbp_register_hidden_post_status',
				array(
					'label'                     => _x( 'Hidden', 'post', 'bbpress' ),
					/* translators: %s: Number of hidden items */
					'label_count'               => _nx_noop( 'Hidden <span class="count">(%s)</span>', 'Hidden <span class="count">(%s)</span>', 'post', 'bbpress' ),
					'private'                   => true,
					'exclude_from_search'       => true,
					'show_in_admin_status_list' => true,
					'show_in_admin_all_list'    => true,
					'source'                    => 'bbpress'
				)
			)
		);

		/**
		 * Trash fix.
		 *
		 * We need to remove the internal arg and change that to
		 * protected so that the users with 'view_trash' cap can view
		 * single trashed topics/replies in the front-end as wp_query
		 * doesn't allow any hack for the trashed topics to be viewed.
		 */
		global $wp_post_statuses;

		if ( ! empty( $wp_post_statuses['trash'] ) ) {

			// User can view trash so set internal to false
			if ( current_user_can( 'view_trash' ) ) {
				$wp_post_statuses['trash']->internal  = false;
				$wp_post_statuses['trash']->protected = true;

			// User cannot view trash so set internal to true
			} else {
				$wp_post_statuses['trash']->internal = true;
			}
		}
	}

	/**
	 * Register the topic tag taxonomy.
	 *
	 * @since 2.0.0 bbPress (r2753) Added bbp_get_topic_tag_tax_id() taxonomy
	 *
	 * @return void
	 */
	public static function register_taxonomies() {

		// Register the topic-tag taxonomy.
		register_taxonomy(
			bbp_get_topic_tag_tax_id(),
			bbp_get_topic_post_type(),
			/**
			 * Filters the arguments used to register the topic taxonomy.
			 *
			 * @since 2.0.0 bbPress (r2597)
			 *
			 * @param array $args The arguments used to register the topic taxonomy.
			 */
			apply_filters(
				'bbp_register_topic_taxonomy',
				array(
					'labels'                => bbp_get_topic_tag_tax_labels(),
					'rewrite'               => bbp_get_topic_tag_tax_rewrite(),
					'capabilities'          => bbp_get_topic_tag_caps(),
					'update_count_callback' => 'bbp_update_topic_tag_count',
					'query_var'             => true,
					'show_tagcloud'         => true,
					'hierarchical'          => false,
					'show_in_nav_menus'     => false,
					'public'                => true,
					'show_ui'               => bbp_allow_topic_tags() && current_user_can( 'bbp_topic_tags_admin' ),

					// bbPress
					'source'                => 'bbpress',
					'bbp_subscriptions'     => bbp_allow_topic_tags()
				)
			)
		);
	}

	/**
	 * Register the bbPress engagement types.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 * @since 2.7.0 bbPress (r8323) Register terms.
	 *
	 * @return void
	 */
	public static function register_engagements() {
		bbp_register_engagement_type(
			'post',
			array(
				'class' => 'BBP_User_Engagements_Type_Post'
			)
		);

		bbp_register_engagement_type(
			'term',
			array(
				'class' => 'BBP_User_Engagements_Type_Term'
			)
		);
	}

	/**
	 * Register the bbPress views.
	 *
	 * @since 2.0.0 bbPress (r2789)
	 *
	 * @return void
	 */
	public static function register_views() {

		// Popular topics
		bbp_register_view(
			'popular',
			esc_html__( 'Most popular topics', 'bbpress' ),
			/**
			 * Filters the query arguments for the popular view.
			 *
			 * @since 2.3.0 bbPress (r4803)
			 *
			 * @param array $args The query arguments for the popular view.
			 */
			apply_filters(
				'bbp_register_view_popular',
				array(
					'meta_key'      => '_bbp_reply_count',
					'meta_type'     => 'NUMERIC',
					'max_num_pages' => 1,
					'orderby'       => 'meta_value_num',
					'show_stickies' => false
				)
			)
		);

		// Topics with no replies
		bbp_register_view(
			'no-replies',
			esc_html__( 'Topics with no replies', 'bbpress' ),
			/**
			 * Filters the query arguments for the no-replies view.
			 *
			 * @since 2.0.0 bbPress (r2789)
			 *
			 * @param array $args The query arguments for the no-replies view.
			 */
			apply_filters(
				'bbp_register_view_no_replies',
				array(
					'meta_key'      => '_bbp_reply_count',
					'meta_type'     => 'NUMERIC',
					'meta_value'    => 1,
					'meta_compare'  => '<',
					'orderby'       => '',
					'show_stickies' => false
				)
			)
		);
	}

	/**
	 * Register the bbPress shortcodes.
	 *
	 * @since 2.2.0 bbPress (r4212)
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		$this->shortcodes = new BBP_Shortcodes();
	}

	/**
	 * Register the bbPress blocks.
	 *
	 * @since 2.7.0 bbPress (r7383)
	 *
	 * @return void
	 */
	public function register_blocks() {
		$this->blocks = new BBP_Blocks();
	}

	/**
	 * Register bbPress meta-data
	 *
	 * Counts added in 2.6.0 to avoid negative values.
	 *
	 * @since 2.6.0 bbPress (r6302)
	 *
	 * @return void
	 */
	public function register_meta() {

		// Define "count" meta-type array
		$count = array(

			// Counts are always integers
			'type'              => 'integer',

			// Generic count description
			'description'       => esc_html__( 'bbPress Item Count', 'bbpress' ),

			// Counts are single values
			'single'            => true,

			// Public counts should be made available in REST
			'show_in_rest'      => true,

			// Never allow counts to go negative
			'sanitize_callback' => 'bbp_number_not_negative',

			// Derived counts cannot be edited through metadata endpoints
			'auth_callback'     => '__return_false'
		);

		/** Post **************************************************************/

		// Forum
		$count['object_subtype'] = bbp_get_forum_post_type();
		register_meta( 'post', '_bbp_topic_count',              $count );
		register_meta( 'post', '_bbp_reply_count',              $count );
		register_meta( 'post', '_bbp_total_topic_count',        $count );
		register_meta( 'post', '_bbp_total_reply_count',        $count );
		// Hidden counts must stay private if custom fields are exposed in REST.
		$hidden_count = $count;
		$hidden_count['show_in_rest'] = false;
		register_meta( 'post', '_bbp_topic_count_hidden',       $hidden_count );
		register_meta( 'post', '_bbp_reply_count_hidden',       $hidden_count );
		register_meta( 'post', '_bbp_total_topic_count_hidden', $hidden_count );
		register_meta( 'post', '_bbp_total_reply_count_hidden', $hidden_count );
		register_meta( 'post', '_bbp_forum_subforum_count',     $count );

		// Topic
		$count['object_subtype'] = bbp_get_topic_post_type();
		$hidden_count['object_subtype'] = bbp_get_topic_post_type();
		register_meta( 'post', '_bbp_reply_count',           $count );
		register_meta( 'post', '_bbp_voice_count',           $count );
		register_meta( 'post', '_bbp_anonymous_reply_count', $count );
		register_meta( 'post', '_bbp_reply_count_hidden',    $hidden_count );

		/* User ***************************************************************/

		// Counts
		$count['object_subtype'] = '';
		register_meta( 'user', '_bbp_topic_count', $count );
		register_meta( 'user', '_bbp_reply_count', $count );

		// Activity
		register_meta(
			'user',
			'_bbp_last_posted',
			array(
				'type'              => 'integer',
				'description'       => esc_html__( 'bbPress User Activity', 'bbpress' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'bbp_number_not_negative',
				'auth_callback'     => '__return_false'
			)
		);
	}

	/**
	 * Setup the currently logged-in user.
	 *
	 * @since 2.0.0 bbPress (r2697)
	 *
	 * @return void
	 */
	public function setup_current_user() {
		$this->current_user = wp_get_current_user();
	}

	/**
	 * Setup the user engagements strategy.
	 *
	 * @since 2.6.0 bbPress (r6876)
	 *
	 * @return void
	 */
	public function setup_engagements() {

		// Setup the class name
		$strategy   = ucwords( bbp_engagements_strategy() );
		$class_name = "BBP_User_Engagements_{$strategy}";

		// Setup the engagements interface
		$this->engagements = new $class_name();
	}

	/**
	 * Initialize forum-specific roles.
	 *
	 * @since 2.6.0 bbPress (r6118)
	 *
	 * @return void
	 */
	public function roles_init() {

		// Get role IDs
		$keymaster   = bbp_get_keymaster_role();
		$moderator   = bbp_get_moderator_role();
		$participant = bbp_get_participant_role();
		$spectator   = bbp_get_spectator_role();
		$blocked     = bbp_get_blocked_role();

		// Build the roles into one useful array
		// phpcs:disable WordPress.Arrays.ArrayKeySpacingRestrictions.TooMuchSpaceAfterKey
		$this->roles[ $keymaster   ] = new WP_Role( 'Keymaster',   bbp_get_caps_for_role( $keymaster   ) );
		$this->roles[ $moderator   ] = new WP_Role( 'Moderator',   bbp_get_caps_for_role( $moderator   ) );
		$this->roles[ $participant ] = new WP_Role( 'Participant', bbp_get_caps_for_role( $participant ) );
		$this->roles[ $spectator   ] = new WP_Role( 'Spectator',   bbp_get_caps_for_role( $spectator   ) );
		$this->roles[ $blocked     ] = new WP_Role( 'Blocked',     bbp_get_caps_for_role( $blocked     ) );
		// phpcs:enable
	}

	/** Custom Rewrite Rules **************************************************/

	/**
	 * Add the bbPress-specific rewrite tags.
	 *
	 * @since 2.0.0 bbPress (r2753)
	 *
	 * @return void
	 */
	public static function add_rewrite_tags() {
		add_rewrite_tag( '%' . bbp_get_view_rewrite_id()               . '%', '([^/]+)'   ); // View Page tag
		add_rewrite_tag( '%' . bbp_get_edit_rewrite_id()               . '%', '([1]{1,})' ); // Edit Page tag
		add_rewrite_tag( '%' . bbp_get_search_rewrite_id()             . '%', '([^/]+)'   ); // Search Results tag
		add_rewrite_tag( '%' . bbp_get_user_rewrite_id()               . '%', '([^/]+)'   ); // User Profile tag
		add_rewrite_tag( '%' . bbp_get_user_favorites_rewrite_id()     . '%', '([1]{1,})' ); // User Favorites tag
		add_rewrite_tag( '%' . bbp_get_user_subscriptions_rewrite_id() . '%', '([1]{1,})' ); // User Subscriptions tag
		add_rewrite_tag( '%' . bbp_get_user_engagements_rewrite_id()   . '%', '([1]{1,})' ); // User Engagements tag
		add_rewrite_tag( '%' . bbp_get_user_mentions_rewrite_id()      . '%', '([1]{1,})' ); // User Mentions tag
		add_rewrite_tag( '%' . bbp_get_user_topics_rewrite_id()        . '%', '([1]{1,})' ); // User Topics Tag
		add_rewrite_tag( '%' . bbp_get_user_replies_rewrite_id()       . '%', '([1]{1,})' ); // User Replies Tag
		add_rewrite_tag( '%' . bbp_get_subscription_unsubscribe_rewrite_id() . '%', '([1]{1,})' ); // Subscription Unsubscribe tag
	}

	/**
	 * Add bbPress-specific rewrite rules for uri's that are not
	 * setup for us by way of custom post types or taxonomies. This includes:
	 * - Front-end editing
	 * - Topic views
	 * - User profiles
	 *
	 * @since 2.4.0 bbPress (r4930)
	 *
	 * @todo Extract into an API
	 *
	 * @return void
	 */
	public static function add_rewrite_rules() {

		/** Setup *************************************************************/

		// Add rules to top or bottom?
		$priority           = 'top';

		// Single Slugs
		$forum_slug         = bbp_get_forum_slug();
		$topic_slug         = bbp_get_topic_slug();
		$reply_slug         = bbp_get_reply_slug();
		$ttag_slug          = bbp_get_topic_tag_tax_slug();

		// Archive Slugs
		$user_slug          = bbp_get_user_slug();
		$view_slug          = bbp_get_view_slug();
		$search_slug        = bbp_get_search_slug();
		$unsubscribe_slug   = bbp_get_subscription_unsubscribe_slug();
		$topic_archive_slug = bbp_get_topic_archive_slug();
		$reply_archive_slug = bbp_get_reply_archive_slug();

		// Tertiary Slugs
		$feed_slug          = 'feed';
		$edit_slug          = bbp_get_edit_slug();
		$paged_slug         = bbp_get_paged_slug();
		$user_favs_slug     = bbp_get_user_favorites_slug();
		$user_subs_slug     = bbp_get_user_subscriptions_slug();
		$user_engs_slug     = bbp_get_user_engagements_slug();
		$user_ment_slug     = bbp_get_user_mentions_slug();

		// Unique rewrite ID's
		$feed_id            = 'feed';
		$edit_id            = bbp_get_edit_rewrite_id();
		$view_id            = bbp_get_view_rewrite_id();
		$paged_id           = bbp_get_paged_rewrite_id();
		$search_id          = bbp_get_search_rewrite_id();
		$user_id            = bbp_get_user_rewrite_id();
		$user_favs_id       = bbp_get_user_favorites_rewrite_id();
		$user_subs_id       = bbp_get_user_subscriptions_rewrite_id();
		$user_tops_id       = bbp_get_user_topics_rewrite_id();
		$user_reps_id       = bbp_get_user_replies_rewrite_id();
		$user_engs_id       = bbp_get_user_engagements_rewrite_id();
		$user_ment_id       = bbp_get_user_mentions_rewrite_id();
		$unsubscribe_id     = bbp_get_subscription_unsubscribe_rewrite_id();

		// Rewrite rule matches used repeatedly below
		$root_rule    = '/([^/]+)/?$';
		$feed_rule    = '/([^/]+)/' . $feed_slug  . '/?$';
		$edit_rule    = '/([^/]+)/' . $edit_slug  . '/?$';
		$paged_rule   = '/([^/]+)/' . $paged_slug . '/?([0-9]{1,})/?$';

		// Search rules (without slug check)
		$search_root_rule  = '/?$';
		$search_paged_rule = '/' . $paged_slug . '/?([0-9]{1,})/?$';

		/** Add ***************************************************************/

		// User profile rules
		$tops_rule       = '/([^/]+)/' . $topic_archive_slug . '/?$';
		$reps_rule       = '/([^/]+)/' . $reply_archive_slug . '/?$';
		$favs_rule       = '/([^/]+)/' . $user_favs_slug     . '/?$';
		$subs_rule       = '/([^/]+)/' . $user_subs_slug     . '/?$';
		$engs_rule       = '/([^/]+)/' . $user_engs_slug     . '/?$';
		$ment_rule       = '/([^/]+)/' . $user_ment_slug     . '/?$';
		$tops_paged_rule = '/([^/]+)/' . $topic_archive_slug . '/' . $paged_slug . '/?([0-9]{1,})/?$';
		$reps_paged_rule = '/([^/]+)/' . $reply_archive_slug . '/' . $paged_slug . '/?([0-9]{1,})/?$';
		$favs_paged_rule = '/([^/]+)/' . $user_favs_slug     . '/' . $paged_slug . '/?([0-9]{1,})/?$';
		$subs_paged_rule = '/([^/]+)/' . $user_subs_slug     . '/' . $paged_slug . '/?([0-9]{1,})/?$';
		$engs_paged_rule = '/([^/]+)/' . $user_engs_slug     . '/' . $paged_slug . '/?([0-9]{1,})/?$';
		$ment_paged_rule = '/([^/]+)/' . $user_ment_slug     . '/' . $paged_slug . '/?([0-9]{1,})/?$';
		$tops_feed_rule  = '/([^/]+)/' . $topic_archive_slug . '/' . $feed_slug  . '/?$';
		$reps_feed_rule  = '/([^/]+)/' . $reply_archive_slug . '/' . $feed_slug  . '/?$';

		// Edit Forum|Topic|Reply|Topic-tag
		add_rewrite_rule( $forum_slug . $edit_rule, 'index.php?' . bbp_get_forum_post_type()  . '=$matches[1]&' . $edit_id . '=1', $priority );
		add_rewrite_rule( $topic_slug . $edit_rule, 'index.php?' . bbp_get_topic_post_type()  . '=$matches[1]&' . $edit_id . '=1', $priority );
		add_rewrite_rule( $reply_slug . $edit_rule, 'index.php?' . bbp_get_reply_post_type()  . '=$matches[1]&' . $edit_id . '=1', $priority );
		add_rewrite_rule( $ttag_slug  . $edit_rule, 'index.php?' . bbp_get_topic_tag_tax_id() . '=$matches[1]&' . $edit_id . '=1', $priority );

		// Subscription unsubscribe confirmation
		add_rewrite_rule( $unsubscribe_slug . '/?$', 'index.php?' . $unsubscribe_id . '=1', $priority );

		// User Pagination|Feed|Edit|View
		add_rewrite_rule( $user_slug . $tops_feed_rule,  'index.php?' . $user_id  . '=$matches[1]&' . $user_tops_id . '=1&' . $feed_id  . '=' . $feed_slug,  $priority );
		add_rewrite_rule( $user_slug . $reps_feed_rule,  'index.php?' . $user_id  . '=$matches[1]&' . $user_reps_id . '=1&' . $feed_id  . '=' . $feed_slug,  $priority );
		add_rewrite_rule( $user_slug . $tops_paged_rule, 'index.php?' . $user_id  . '=$matches[1]&' . $user_tops_id . '=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_slug . $reps_paged_rule, 'index.php?' . $user_id  . '=$matches[1]&' . $user_reps_id . '=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_slug . $favs_paged_rule, 'index.php?' . $user_id  . '=$matches[1]&' . $user_favs_id . '=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_slug . $subs_paged_rule, 'index.php?' . $user_id  . '=$matches[1]&' . $user_subs_id . '=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_slug . $engs_paged_rule, 'index.php?' . $user_id  . '=$matches[1]&' . $user_engs_id . '=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_slug . $ment_paged_rule, 'index.php?' . $user_id  . '=$matches[1]&' . $user_ment_id . '=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_slug . $tops_rule,       'index.php?' . $user_id  . '=$matches[1]&' . $user_tops_id . '=1',                               $priority );
		add_rewrite_rule( $user_slug . $reps_rule,       'index.php?' . $user_id  . '=$matches[1]&' . $user_reps_id . '=1',                               $priority );
		add_rewrite_rule( $user_slug . $favs_rule,       'index.php?' . $user_id  . '=$matches[1]&' . $user_favs_id . '=1',                               $priority );
		add_rewrite_rule( $user_slug . $subs_rule,       'index.php?' . $user_id  . '=$matches[1]&' . $user_subs_id . '=1',                               $priority );
		add_rewrite_rule( $user_slug . $engs_rule,       'index.php?' . $user_id  . '=$matches[1]&' . $user_engs_id . '=1',                               $priority );
		add_rewrite_rule( $user_slug . $ment_rule,       'index.php?' . $user_id  . '=$matches[1]&' . $user_ment_id . '=1',                               $priority );
		add_rewrite_rule( $user_slug . $edit_rule,       'index.php?' . $user_id  . '=$matches[1]&' . $edit_id      . '=1',                               $priority );
		add_rewrite_rule( $user_slug . $root_rule,       'index.php?' . $user_id  . '=$matches[1]',                                                       $priority );

		// Topic-View Pagination|Feed|View
		add_rewrite_rule( $view_slug . $paged_rule, 'index.php?' . $view_id . '=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $view_slug . $feed_rule,  'index.php?' . $view_id . '=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );
		add_rewrite_rule( $view_slug . $root_rule,  'index.php?' . $view_id . '=$matches[1]',                               $priority );

		// Search All
		add_rewrite_rule( $search_slug . $search_paged_rule, 'index.php?' . $paged_id .'=$matches[1]', $priority );
		add_rewrite_rule( $search_slug . $search_root_rule,  'index.php?' . $search_id,                $priority );
	}

	/**
	 * Add permalink structures for new archive-style destinations.
	 *
	 * - Users
	 * - Topic Views
	 * - Search
	 *
	 * @since 2.4.0 bbPress (r4930)
	 *
	 * @return void
	 */
	public static function add_permastructs() {

		// Get unique ID's
		$user_id     = bbp_get_user_rewrite_id();
		$view_id     = bbp_get_view_rewrite_id();
		$search_id   = bbp_get_search_rewrite_id();

		// Get root slugs
		$user_slug   = bbp_get_user_slug();
		$view_slug   = bbp_get_view_slug();
		$search_slug = bbp_get_search_slug();

		// User Permastruct
		add_permastruct(
			$user_id,
			$user_slug . '/%' . $user_id . '%',
			array(
				'with_front'  => false,
				'ep_mask'     => EP_NONE,
				'paged'       => false,
				'feed'        => false,
				'forcomments' => false,
				'walk_dirs'   => true,
				'endpoints'   => false,
			)
		);

		// Topic View Permastruct
		add_permastruct(
			$view_id,
			$view_slug . '/%' . $view_id . '%',
			array(
				'with_front'  => false,
				'ep_mask'     => EP_NONE,
				'paged'       => false,
				'feed'        => false,
				'forcomments' => false,
				'walk_dirs'   => true,
				'endpoints'   => false,
			)
		);

		// Search Permastruct
		add_permastruct(
			$search_id,
			$search_slug . '/%' . $search_id . '%',
			array(
				'with_front'  => false,
				'ep_mask'     => EP_NONE,
				'paged'       => true,
				'feed'        => false,
				'forcomments' => false,
				'walk_dirs'   => true,
				'endpoints'   => false,
			)
		);
	}
}

/**
 * The main function responsible for returning the one true bbPress Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $bbp = bbpress(); ?>
 *
 * @since 2.1.0 bbPress (r3758)
 *
 * @return bbPress The one true bbPress Instance
 */
function bbpress() {
	return bbPress::instance();
}

/**
 * Hook bbPress early onto the 'plugins_loaded' action.
 *
 * This gives all other plugins the chance to load before bbPress, to get their
 * actions, filters, and overrides setup without bbPress being in the way.
 */
if ( defined( 'BBPRESS_LATE_LOAD' ) ) {
	add_action( 'plugins_loaded', 'bbpress', (int) BBPRESS_LATE_LOAD );

// "And now here's something we hope you'll really like!"
} else {
	bbpress();
}

endif; // class_exists check
