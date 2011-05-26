<?php

/**
 * bbPress needs this class for its usermeta manipulation.
 */
class bbPress_Importer_BB_Auth {
	function update_meta( $args = '' ) {
		$defaults = array( 'id' => 0, 'meta_key' => null, 'meta_value' => null, 'meta_table' => 'usermeta', 'meta_field' => 'user_id', 'cache_group' => 'users' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );

		return update_user_meta( $id, $meta_key, $meta_value );
	}
}

if ( !class_exists( 'BPDB' ) ) :

/**
 * bbPress needs the DB class to be BPDB, but we want to use WPDB, so we can
 * extend it and use this.
 */
class BPDB extends WPDB {
	var $db_servers = array();

	function BPDB( $dbuser, $dbpassword, $dbname, $dbhost ) {
		$this->__construct( $dbuser, $dbpassword, $dbname, $dbhost );
 	}

 	function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
		parent::__construct( $dbuser, $dbpassword, $dbname, $dbhost );

		$args = func_get_args();
		$args = call_user_func_array( array( &$this, '_init' ), $args );

		if ( $args['host'] )
			$this->db_servers['dbh_global'] = $args;
	}

	/**
	 * Determine if a database supports a particular feature.
	 *
	 * Overriden here to work around differences between bbPress', and WordPress', implementation differences.
	 * In particular, when BuddyPress tries to run bbPress' SQL installation script, the collation check always
	 * failed. The capability is long supported by WordPress' minimum required MySQL version, so this is safe.
	 */
	function has_cap( $db_cap, $_table_name='' ) {
		if ( 'collation' == $db_cap )
			return true;

		return parent::has_cap( $db_cap );
	}

	/**
	 * Initialises the class variables based on provided arguments.
	 * Based on, and taken from, the BackPress class in turn taken from the 1.0 branch of bbPress.
	 */
	function _init( $args )
	{
		if ( 4 == func_num_args() ) {
			$args = array(
				'user'     => $args,
				'password' => func_get_arg( 1 ),
				'name'     => func_get_arg( 2 ),
				'host'     => func_get_arg( 3 ),
				'charset'  => defined( 'BBDB_CHARSET' ) ? BBDB_CHARSET : false,
				'collate'  => defined( 'BBDB_COLLATE' ) ? BBDB_COLLATE : false,
			);
		}

		$defaults = array(
			'user'     => false,
			'password' => false,
			'name'     => false,
			'host'     => 'localhost',
			'charset'  => false,
			'collate'  => false,
			'errors'   => false
		);

		return wp_parse_args( $args, $defaults );
	}

	function escape_deep( $data ) {
		if ( is_array( $data ) ) {
			foreach ( (array) $data as $k => $v ) {
				if ( is_array( $v ) ) {
					$data[$k] = $this->_escape( $v );
				} else {
					$data[$k] = $this->_real_escape( $v );
				}
			}
		} else {
			$data = $this->_real_escape( $data );
		}

		return $data;
	}
}

endif; // class_exists

if ( !function_exists( 'bb_cache_users' ) ) :
	function bb_cache_users( $users ) { }
endif;

/**
 * bbPress Standalone Importer
 *
 * Helps in converting your bbPress Standalone into the new bbPress Plugin
 *
 * @package bbPress
 * @subpackage Importer
 *
 * @todo Docs
 * @todo User Mapping (ref. MT Importer)
 * @todo Role Mapping Options
*/
class bbPress_Importer {

	/**
	 * @var string Path to bbPress standalone configuration file (bb-config.php)
	 */
	var $bbconfig = '';

	/**
	 * Load the bbPress environment
	 */
	function load_bbpress() {

		// BuddyPress Install
		if ( defined( 'BP_VERSION' ) && bp_is_active( 'forums' ) && bp_forums_is_installed_correctly() ) {
			global $bp;

			if ( !empty( $bp->forums->bbconfig ) && ( $bp->forums->bbconfig == $this->bbconfig ) )
				bp_forums_load_bbpress();
		}

		global $wpdb, $wp_roles, $current_user, $wp_users_object, $wp_taxonomy_object;
		global $bb, $bbdb, $bb_table_prefix, $bb_current_user, $bb_roles, $bb_queries;

		// Return if we've already run this function or it is BuddyPress
		if ( is_object( $bbdb ) )
			return;

		// Config file does not exist
		if ( !file_exists( $this->bbconfig ) )
			return false;

		// Set the path constants
		define( 'BB_PATH',        trailingslashit( dirname( $this->bbconfig ) ) );
		define( 'BACKPRESS_PATH', BB_PATH . 'bb-includes/backpress/'            );
		define( 'BB_INC',         'bb-includes/'                                );

		require_once( BB_PATH . BB_INC . 'class.bb-query.php'            );
		require_once( BB_PATH . BB_INC . 'class.bb-walker.php'           );
		require_once( BB_PATH . BB_INC . 'functions.bb-core.php'         );
		require_once( BB_PATH . BB_INC . 'functions.bb-forums.php'       );
		require_once( BB_PATH . BB_INC . 'functions.bb-topics.php'       );
		require_once( BB_PATH . BB_INC . 'functions.bb-posts.php'        );
		require_once( BB_PATH . BB_INC . 'functions.bb-topic-tags.php'   );
		require_once( BB_PATH . BB_INC . 'functions.bb-capabilities.php' );
		require_once( BB_PATH . BB_INC . 'functions.bb-meta.php'         );
		require_once( BB_PATH . BB_INC . 'functions.bb-pluggable.php'    );
		require_once( BB_PATH . BB_INC . 'functions.bb-formatting.php'   );
		require_once( BB_PATH . BB_INC . 'functions.bb-template.php'     );

		require_once( BACKPRESS_PATH   . 'class.wp-taxonomy.php'         );
		require_once( BB_PATH . BB_INC . 'class.bb-taxonomy.php'         );

		$bb = new stdClass();
		require_once( $this->bbconfig );

		// Setup the global database connection
		$bbdb = new BPDB( BBDB_USER, BBDB_PASSWORD, BBDB_NAME, BBDB_HOST );

		// Set the table names
		$bbdb->forums             = $bb_table_prefix . 'forums';
		$bbdb->meta               = $bb_table_prefix . 'meta';
		$bbdb->posts              = $bb_table_prefix . 'posts';
		$bbdb->terms              = $bb_table_prefix . 'terms';
		$bbdb->term_relationships = $bb_table_prefix . 'term_relationships';
		$bbdb->term_taxonomy      = $bb_table_prefix . 'term_taxonomy';
		$bbdb->topics             = $bb_table_prefix . 'topics';

		// Users table
		if ( isset( $bb->custom_user_table ) )
			$bbdb->users    = $bb->custom_user_table;
		else
			$bbdb->users    = $bb_table_prefix . 'users';

		// Users meta table
		if ( isset( $bb->custom_user_meta_table ) )
			$bbdb->usermeta = $bb->custom_user_meta_table;
		else
			$bbdb->usermeta = $bb_table_prefix . 'usermeta';

		// Table prefix
		$bbdb->prefix = $bb_table_prefix;

		// Not installing
		define( 'BB_INSTALLING', false );

		// Ghetto role map
		if ( is_object( $wp_roles ) ) {
			$bb_roles = $wp_roles;
			bb_init_roles( $bb_roles );
		}

		// Call the standard bbPress actions
		do_action( 'bb_got_roles' );
		do_action( 'bb_init'      );
		do_action( 'init_roles'   );

		// Setup required objects
		$bb_current_user = $current_user;
		$wp_users_object = new bbPress_Importer_BB_Auth;

		// Taxonomy object
		if ( !isset( $wp_taxonomy_object ) )
			$wp_taxonomy_object = new BB_Taxonomy( $bbdb );

		$wp_taxonomy_object->register_taxonomy( 'bb_topic_tag', 'bb_topic' );
	}

	/**
	 * Returns the tag name from tag object
	 *
	 * @param object $tag Tag Object
	 * @return string Tag name
	 */
	function get_tag_name( $tag ) {
		return $tag->name;
	}

	/**
	 * Simple check to check if the WP and bb user tables are integrated or not
	 *
	 * @return bool True if integrated, false if not
	 */
	function is_integrated() {
		global $bbdb, $wpdb;

		return ( $wpdb->users == $bbdb->users && $wpdb->usermeta == $bbdb->usermeta );
	}

	/**
	 * Tries to automatically locate the bbPress standalone install path
	 *
	 * @return string Path, if found
	 */
	function autolocate_bbconfig() {

		// BuddyPress Install
		if ( defined( 'BP_VERSION' ) && bp_is_active( 'forums' ) && bp_forums_is_installed_correctly() ) {
			global $bp;

			if ( !empty( $bp->forums->bbconfig ) )
				return $bp->forums->bbconfig;
		}

		// Normal install
		$dirs      = array( 'forum', 'forums', 'board', 'discussion', 'bbpress', 'bb', '' );
		$base      = trailingslashit( ABSPATH );
		$base_dirs = array( $base, dirname( $base ) );

		// Loop through possible directories
		foreach ( $dirs as $dir ) {

			// Loop through base dir
			foreach ( $base_dirs as $base_dir ) {

				// Path to try
				$test_path = $base_dir . $dir . '/bb-config.php';

				// File exists
				if ( file_exists( $test_path ) ) {
					return realpath( $test_path );
				}
			}
		}

		// Nothing found
		return '';
	}

	/**
	 * Get the bbPress standalone topic favoriters from topic id
	 *
	 * @param int $topic_id Topic id
	 * @return array Topic Favoriters' IDs
	 */
	function bb_get_topic_favoriters( $topic_id = 0 ) {
		if ( empty( $topic_id ) )
			return array();

		global $bbdb;

		return (array) $bbdb->get_col( $bbdb->prepare( "SELECT user_id
				FROM $bbdb->usermeta
				WHERE meta_key = '{$bbdb->prefix}favorites'
				AND FIND_IN_SET( %d, meta_value ) > 0",
				$topic_id ) );
	}

	/**
	 * Get the bbPress standalone topic subscribers from topic id
	 *
	 * If the Subscribe to Topic bbPress plugin is active, then subscription
	 * info is taken from that. Otherwise, if the the user is using
	 * bbPress >= 1.1 alpha, then get the info from there.
	 *
	 * @param int $topic_id Topic id
	 * @return array Topic Subscribers' IDs
	 */
	function bb_get_topic_subscribers( $topic_id = 0 ) {
		if ( empty( $topic_id ) )
			return array();

		global $bbdb, $subscribe_to_topic;

		$users = array();

		// The user is using Subscribe to Topic plugin by _ck_, get the subscribers from there
		if ( !empty( $subscribe_to_topic ) && !empty( $subscribe_to_topic['db'] ) ) {
			$users = $bbdb->get_col( $bbdb->prepare( "SELECT user
				FROM {$subscribe_to_topic['db']}
				WHERE topic = %d
				AND type = 2", $topic_id ) );

		// The user is using alpha, get the subscribers from built-in functionality
		} elseif ( function_exists( 'bb_notify_subscribers' ) ) {
			$users = $bbdb->get_col( $bbdb->prepare( "SELECT `$bbdb->term_relationships`.`object_id`
				FROM $bbdb->term_relationships, $bbdb->term_taxonomy, $bbdb->terms
				WHERE `$bbdb->term_relationships`.`term_taxonomy_id` = `$bbdb->term_taxonomy`.`term_taxonomy_id`
				AND `$bbdb->term_taxonomy`.`term_id` = `$bbdb->terms`.`term_id`
				AND `$bbdb->term_taxonomy`.`taxonomy` = 'bb_subscribe'
				AND `$bbdb->terms`.`slug` = 'topic-%d'",
				$topic_id ) );
		}

		return (array) $users;
	}

	function header() { ?>

		<div class="wrap">

			<?php screen_icon(); ?>

			<h2><?php _e( 'bbPress Standalone Importer', 'bbpress' ); ?></h2>

			<?php
	}

	/**
	 * Output an error message with a button to try again.
	 *
	 * @param type $error
	 * @param type $step
	 */
	function throw_error( $error, $step ) {
		echo '<p><strong>' . $error->get_error_message() . '</strong></p>';
		echo $this->next_step( $step, __( 'Try Again', 'bbpress' ) );
	}

	/**
	 * Returns the HTML for a link to the next page
	 *
	 * @param type $next_step
	 * @param type $label
	 * @param type $id
	 * @return string
	 */
	function next_step( $next_step, $label, $id = 'bbpress-import-next-form' ) {
		$str  = '<form action="admin.php?import=bbpress" method="post" id="' . $id . '">';
		$str .= wp_nonce_field( 'bbp-bbpress-import', '_wpnonce', true, false );
		$str .= wp_referer_field( false );
		$str .= '<input type="hidden" name="step" id="step" value="' . esc_attr( $next_step ) . '" />';
		$str .= '<p><input type="submit" class="button" value="' . esc_attr( $label ) . '" /> <span id="auto-message"></span></p>';
		$str .= '</form>';

		return $str;
	}

	/**
	 * Footer
	 */
	function footer() { ?>

		</div>

		<?php
	}

	/**
	 * Dispatch
	 */
	function dispatch() {
		if ( empty( $_REQUEST['step'] ) )
			$step = 0;
		else
			$step = (int) $_REQUEST['step'];

		$this->header();

		switch ( $step ) {
			case -1 :
				$this->cleanup();
				// Intentional no break

			case 0 :
				$this->greet();
				break;

			case 1 :
			case 2 :
			case 3 :

				check_admin_referer( 'bbp-bbpress-import' );
				$result = $this->{ 'step' . $step }();

				break;
		}

		$this->footer();
	}

	/**
	 * Greet message
	 */
	function greet() {
		global $wpdb, $bbdb;

		// Attempt to autolocate config file
		$autolocate = $this->autolocate_bbconfig(); ?>

		<div class="narrow">

			<form action="admin.php?import=bbpress" method="post">

				<?php wp_nonce_field( 'bbp-bbpress-import' ); ?>

				<?php if ( get_option( 'bbp_bbpress_path' ) ) : ?>

					<input type="hidden" name="step" value="<?php echo esc_attr( get_option( 'bbp_bbpress_step' ) ); ?>" />

					<p><?php _e( 'It looks like you attempted to convert your bbPress standalone previously and got interrupted.', 'bbpress' ); ?></p>

					<p class="submit">
						<a href="<?php echo esc_url( add_query_arg( array( 'import' => 'bbpress', 'step' => '-1', '_wpnonce' => wp_create_nonce( 'bbp-bbpress-import' ), '_wp_http_referer' => esc_attr( $_SERVER['REQUEST_URI'] ) ) ) ); ?>" class="button"><?php _e( 'Cancel &amp; start a new import', 'bbpress' ); ?></a>
						<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Continue previous import', 'bbpress' ); ?>" />
					</p>

				<?php else : ?>

					<?php if ( !empty( $autolocate ) ) : ?>

						<div id="message" class="updated">
							<p><?php _e( 'Existing bbPress standalone installation found. See configuration section for details.', 'bbpress' ); ?></p>
						</div>

					<?php endif; ?>

					<input type="hidden" name="bbp_bbpress_path" value="true" />

					<p><?php _e( 'This importer allows you to convert your bbPress Standalone into the bbPress Plugin.', 'bbpress' ); ?></p>

					<h3><?php _e( 'Instructions', 'bbpress' ); ?></h3>
					<ol>
						<li><?php printf( __( 'Create a <a href="%s">backup</a> of your database and files. If the import process is interrupted for any reason, restore from that backup and re-run the import.', 'bbpress' ), 'http://codex.wordpress.org/WordPress_Backups' ); ?></li>
						<li><?php _e( 'Seriously... Go back everything up, and don\'t come back until that\'s done. If things go awry, it\'s possible this importer will not be able to complete and your forums will be lost in limbo forever. This is serious business. No, we\'re not kidding.', 'bbpress' ); ?></li>
						<li><?php _e( 'To reduce memory overhead and avoid possible conflicts please:', 'bbpress' ); ?>
							<ol>
								<li>
									<?php _e( 'Disable all plugins (except bbPress) on both your WordPress and bbPress standalone installations.', 'bbpress' ); ?>
								</li>
								<li>
									<?php _e( 'Switch to a default WordPress theme.', 'bbpress' ); ?>
								</li>
							</ol>
						</li>
						<li><?php _e( 'Notes on compatibility:', 'bbpress' ); ?>
							<ol>
								<li>
									<?php _e( 'If you are using the alpha version of bbPress 1.1, subscriptions will be ported automatically.', 'bbpress' ); ?>
								</li>
								<li>
									<?php printf( __( 'If you have the <a href="%s">Subscribe to Topic</a> plugin active, then this script will migrate user subscriptions from that plugin.', 'bbpress' ), 'http://bbpress.org/plugins/topic/subscribe-to-topic/' ); ?>
								</li>
								<li>
									<?php printf( __( 'If you are importing an existing BuddyPress Forums installation, we should have found your previous configuration file.', 'bbpress' ), 'http://bbpress.org/plugins/topic/subscribe-to-topic/' ); ?>
								</li>
							</ol>
						</li>
						<li><?php _e( 'This converter can be a drag on large forums with lots of existing topics and replies. If possible, do this import in a safe place (like a local installation.)', 'bbpress' ); ?></li>
					</ol>

					<h3><?php _e( 'Configuration', 'bbpress' ); ?></h3>
					<p><?php _e( 'Enter the full path to your bbPress configuration file i.e. <code>bb-config.php</code>:', 'bbpress' ); ?></p>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="bbp_bbpress_path"><?php _e( 'bbPress Standalone Path:', 'bbpress' ); ?></label></th>
							<td><input type="text" name="bbp_bbpress_path" id="bbp_bbpress_path" class="regular-text" value="<?php echo !empty( $autolocate ) ? $autolocate : trailingslashit( ABSPATH ) . 'bb-config.php'; ?>" /></td>
						</tr>
					</table>

					<p class="submit">
						<input type="hidden" name="step" value="1" />
						<input type="submit" class="button" value="<?php esc_attr_e( 'Proceed', 'bbpress' ); ?>" />
					</p>

				<?php endif; ?>

			</form>

		</div>
		<?php
	}

	/**
	 * Cleanups all the options used by the importer
	 */
	function cleanup() {
		delete_option( 'bbp_bbpress_path' );
		delete_option( 'bbp_bbpress_step' );

		do_action( 'import_end' );
	}

	/**
	 * Technically the first half of step 1, this is separated to allow for AJAX
	 * calls. Sets up some variables and options and confirms authentication.
	 *
	 * @return type
	 */
	function setup() {

		// Get details from _POST
		if ( !empty( $_POST['bbp_bbpress_path'] ) ) {

			// Store details for later
			$this->bbconfig = realpath( $_POST['bbp_bbpress_path'] );

			// Update path
			update_option( 'bbp_bbpress_path', $this->bbconfig );

		// Get details from DB
		} else {
			$this->bbconfig = get_option( 'bbp_bbpress_path' );
		}

		// No config file found
		if ( empty( $this->bbconfig ) ) { ?>

			<p><?php _e( 'Please enter the path to your bbPress configuration file - <code>bb-config.php</code>.', 'bbpress' ); ?></p>
			<p><a href="<?php echo esc_url( add_query_arg( array( 'import' => 'bbpress', 'step' => '-1', '_wpnonce' => wp_create_nonce( 'bbp-bbpress-import' ), '_wp_http_referer' => esc_attr( remove_query_arg( 'step', $_SERVER['REQUEST_URI'] ) ) ) ) ); ?>" class="button"><?php _e( 'Go Back', 'bbpress' ); ?></a></p>

			<?php

			return false;
		}

		// Check if the user submitted a directory as the path by mistake
		if ( is_dir( $this->bbconfig ) && file_exists( trailingslashit( $this->bbconfig ) . 'bb-config.php' ) ) {
			$this->bbconfig = trailingslashit( $this->bbconfig ) . 'bb-config.php';
		}

		// Check if the file exists
		if ( !file_exists( $this->bbconfig ) || is_dir( $this->bbconfig ) ) {

			delete_option( 'bbp_bbpress_path' ); ?>

			<p><?php _e( 'bbPress configuration file <code>bb-config.php</code> doesn\'t exist in the path specified! Please check the path and try again.', 'bbpress' ); ?></p>
			<p><a href="<?php echo esc_url( add_query_arg( array( 'import' => 'bbpress', 'step' => '-1', '_wpnonce' => wp_create_nonce( 'bbp-bbpress-import' ), '_wp_http_referer' => esc_attr( remove_query_arg( 'step', $_SERVER['REQUEST_URI'] ) ) ) ) ); ?>" class="button"><?php _e( 'Go Back', 'bbpress' ); ?></a></p>

			<?php

			return false;
		}

		$this->load_bbpress();

		remove_filter( 'pre_post_status', 'bb_bozo_pre_post_status', 5, 3 );

		return true;
	}

	/**
	 * Notes & User Options
	 */
	function step1() {

		update_option( 'bbp_bbpress_step', 1 );

		$setup = $this->setup();
		if ( empty( $setup ) ) {
			return false;
		} elseif ( is_wp_error( $setup ) ) {
			$this->throw_error( $setup, 1 );
			return false;
		}

		$radio = 'user';

		global $wpdb, $bbdb; ?>

		<h3><?php _e( 'Configuration Options', 'bbpress' ); ?></h3>

		<form action="admin.php?import=bbpress" method="post">

			<?php if ( $this->is_integrated() ) : $radio = 'board'; ?>

				<p><?php _e( '<strong>Auto-detected</strong>: Your WordPress and bbPress user tables are integrated. Proceed to <label for="step_board">importing forums, topics and posts</label>.', 'bbpress' ); ?></p>

			<?php else : ?>

				<ol>

					<li>
						<?php _e( 'Your WordPress blog is <strong>new</strong> and you don\'t have the fear of losing WordPress users:', 'bbpress' ); ?>
						<label for="step_user"><?php _e( 'Go to migrating users section.', 'bbpress' ); ?></label>
						<?php printf( __( '<strong>Note</strong>: The WordPress %1$s and %2$s tables will be renamed (not deleted) so that you can restore them if something goes wrong.', 'bbpress' ), '<code>' . $wpdb->users . '</code>', '<code>' . $wpdb->usermeta . '</code>' ); ?>
						<?php printf( __( 'Please ensure there are no tables named %1$s and %2$s to avoid renaming conflicts.', 'bbpress' ), '<code>' . $bbdb->prefix . $wpdb->users . '_tmp' . '</code>', '<code>' . $bbdb->prefix . $wpdb->usermeta . '_tmp' . '</code>' ); ?>
						<?php _e( 'Also, your WordPress and bbPress installs must be in the same database.', 'bbpress' ); ?>
					</li>

					<li>
						<?php _e( 'You\'re done with user migration or have the user tables <strong>integrated</strong>:', 'bbpress' ); ?>
						<label for="step_board"><?php _e( 'Proceed to importing forums, topics and posts section.', 'bbpress' ); ?></label>
					</li>

					<li>
						<?php _e( 'You have a well <strong>established</strong> WordPress user base, the user tables are not integrated and you can\'t lose your users:', 'bbpress' ); ?>
						<?php _e( 'This is currently not yet supported, and will likely not be in the future also as it is highly complex to merge two user sets (which might even conflict).', 'bbpress' ); ?>
						<?php printf( __( 'Patches are always <a href="%s" title="The last revision containing the custom user migration code">welcome</a>. :)', 'bbpress' ), 'http://bbpress.trac.wordpress.org/ticket/1523' ); ?>
					</li>

				</ol>

			<?php endif; ?>

			<input type="radio" name="step"<?php disabled( $this->is_integrated() ); ?> value="2"<?php checked( $radio, 'user' ); ?> id="step_user" />
			<label for="step_user"><?php _e( 'Migrate Users', 'bbpress' ); ?></label>

			<input type="radio" name="step" value="3"<?php checked( $radio, 'board' ); ?> id="step_board" />
			<label for="step_board"><?php _e( 'Import Forums, Topics & Posts', 'bbpress' ); ?></label>

			<p class="submit">
				<?php wp_nonce_field( 'bbp-bbpress-import' ); ?>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Start!', 'bbpress' ); ?>" />
			</p>

		</form>

		<?php
	}

	/**
	 * Check form inputs and start importing users
	 *
	 * @return type
	 */
	function step2() {
		do_action( 'import_start' );

		// Set time limit to 0 to avoid time out errors
		set_time_limit( 0 );

		if ( is_callable( 'ob_implicit_flush' ) )
			ob_implicit_flush( true );

		update_option( 'bbp_bbpress_step', 2 );

		$setup = $this->setup();
		if ( empty( $setup ) ) {
			return false;
		} elseif ( is_wp_error( $setup ) ) {
			$this->throw_error( $setup, 2 );
			return false;
		}

		global $wpdb, $bbdb; ?>

		<div id="bbpress-import-status">

			<h3><?php _e( 'Importing Users', 'bbpress' ); ?></h3>
			<p><?php _e( 'We&#8217;re in the process of migrating your users...', 'bbpress' ); ?></p>

			<ol>

				<?php /* Rename the WordPress users and usermeta table */ ?>

				<li>
					<?php

					if ( $wpdb->query( "RENAME TABLE $wpdb->users TO {$bbdb->prefix}{$wpdb->users}_tmp, $wpdb->usermeta TO {$bbdb->prefix}{$wpdb->usermeta}_tmp" ) !== false ) :
						printf( __( 'Renamed the <code>%1$s</code> and <code>%2$s</code> tables to <code>%3$s</code> and <code>%4$s</code> respectively.', 'bbpress' ), $wpdb->users, $wpdb->usermeta, $bbdb->prefix . $wpdb->users . '_tmp', $bbdb->prefix . $wpdb->usermeta . '_tmp' );
					else :
						printf( __( 'There was a problem dropping the <code>%1$s</code> and <code>%2$s</code> tables. Please check and re-run the script or rename or drop the tables yourself.', 'bbpress' ), $wpdb->users, $wpdb->usermeta ); ?>

						</li></ol>

					<?php
						return;
					endif; ?>

				</li>

				<?php /* Duplicate the WordPress users and usermeta table */ ?>

				<li>
					<?php

					if ( $wpdb->query( "CREATE TABLE $wpdb->users    ( `ID` BIGINT( 20 )       UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `user_activation_key` VARCHAR( 60 ) NOT NULL DEFAULT '', KEY ( `user_login` ), KEY( `user_nicename` ) ) SELECT * FROM $bbdb->users    ORDER BY `ID`"       ) !== false ) :
						printf( __( 'Created the <code>%s</code> table and copied the users from bbPress.', 'bbpress' ), $wpdb->users );
					else :
						printf( __( 'There was a problem duplicating the table <code>%1$s</code> to <code>%2$s</code>. Please check and re-run the script or duplicate the table yourself.', 'bbpress' ), $bbdb->users, $wpdb->users ); ?>

						</li></ol>

					<?php

						return;
					endif; ?>

				</li>

				<li>
					<?php

					if ( $wpdb->query( "CREATE TABLE $wpdb->usermeta ( `umeta_id` BIGINT( 20 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,                                                          KEY ( `user_id` ),    KEY( `meta_key` )      ) SELECT * FROM $bbdb->usermeta ORDER BY `umeta_id`" ) !== false ) :
						printf( __( 'Created the <code>%s</code> table and copied the user information from bbPress.', 'bbpress' ), $wpdb->usermeta );
					else :
						printf( __( 'There was a problem duplicating the table <code>%1$s</code> to <code>%2$s</code>. Please check and re-run the script or duplicate the table yourself.', 'bbpress' ), $bbdb->usermeta, $wpdb->usermeta ); ?>

						</li></ol>

					<?php

						return;
					endif; ?>

				</li>

				<?php

				// Map the user roles by our wish
				$roles_map = array(
					'keymaster'     => 'administrator',
					'administrator' => 'bbp_moderator',
					'moderator'     => 'bbp_moderator',
					'member'        => get_option( 'default_role' ),
					'inactive'      => get_option( 'default_role' ),
					'blocked'       => get_option( 'default_role' ),
					'throttle'      => 'throttle'
				);

				$wp_user_level_map = array(
					'administrator' => 10,
					'editor'        => 7,
					'author'        => 2,
					'contributor'   => 1,
					'subscriber'    => 0,
					'throttle'      => 0
				);

				// Apply the WordPress roles to the new users based on their bbPress roles
				wp_cache_flush();
				$users = get_users( array( 'fields' => 'all_with_meta', 'orderby' => 'ID' ) );

				foreach ( $users as $user ) {

					// Get the bbPress roles
					$bb_roles        =& $user->{ $bbdb->prefix . 'capabilities' };
					$converted_roles = $converted_level = array();

					// Loop through each role the user has
					if ( !empty( $bb_roles ) ) {
						foreach ( $bb_roles as $bb_role => $bb_role_value ) {

							// If we have one of those in our roles map, add the WP counterpart in the new roles array
							if ( $roles_map[strtolower( $bb_role )] && !empty( $bb_role_value ) ) {
								$converted_roles[$roles_map[strtolower( $bb_role )]] = true;

								// Have support for deprecated levels too
								$converted_level[] = $wp_user_level_map[$roles_map[strtolower( $bb_role )]];

								// We need an admin for future use
								if ( empty( $admin_user ) && 'administrator' == $roles_map[strtolower( $bb_role )] )
									$admin_user = $user;
							}

						}
					}

					// If we have new roles, then update the user meta
					if ( count( $converted_roles ) ) {
						update_user_meta( $user->ID, $wpdb->prefix . 'capabilities', $converted_roles        );
						update_user_meta( $user->ID, $wpdb->prefix . 'user_level',   max( $converted_level ) );
					}

				}

				if ( empty( $admin_user ) || is_wp_error( $admin_user ) ) : /* I ask why */ ?>

					<li>
						<?php _e( 'There was a problem in getting an administrator of the blog. Now, please go to your blog, set a user as administrator, login as that user and directly go to importing forums, topics and replies section. Note that old logins won\'t work, you would have to edit your database (you can still try logging in as the bbPress keymaster).', 'bbpress' ); ?>
					</li>

				</ol>

				<?php

				return;

				endif;

				// Logout the user as it won't have any good privileges for us to do the work
				// wp_clear_auth_cookie();

				// Login the admin so that we have permissions for conversion etc
				// wp_set_auth_cookie( $admin_user->ID );

				// Set the current user
				wp_set_current_user( $admin_user->ID, $admin_user->user_login );

				?>

				<li>
					<?php printf( __( 'User roles have been successfully mapped based. The bbPress keymaster is WordPress administrator, bbPress administrator and moderators are moderators and rest all WordPress roles are %1$s. Now, you can only login into your WordPress blog by the bbPress credentials. For the time being, you have been logged in as the first available administrator (Username: %2$s, User ID: %3$s).', 'bbpress' ), get_option( 'default_role' ), $admin_user->user_login, $admin_user->ID ); ?>
				</li>

			</ol>

			<p><?php _e( 'Your users have all been imported, but wait &#8211; there&#8217;s more! Now we need to import your forums, topics and posts!', 'bbpress' ); ?></p>
			<?php

			echo $this->next_step( 3, __( 'Import forums, topics and posts &raquo;', 'bbpress' ) ); ?>

		</div>

		<?php
	}

	/**
	 * Import forums, topics and posts
	 */
	function step3() {
		do_action( 'import_start' );

		set_time_limit( 0 );
		update_option( 'bbp_bbpress_step', 3 );

		$setup = $this->setup();
		if ( empty( $setup ) ) {
			return false;
		} elseif ( is_wp_error( $setup ) ) {
			$this->throw_error( $setup, 3 );
			return false;
		}

		global $wpdb, $bbdb, $bbp; ?>

			<div id="bbpress-import-status">

			<h3><?php _e( 'Importing Forums, Topics And Posts', 'bbpress' ); ?></h3>
			<p><?php  _e( 'We&#8217;re importing your bbPress standalone forums, topics and replies...', 'bbpress' ); ?></p>

			<ol>

				<?php

				if ( !$forums = bb_get_forums() ) {
					echo "<li><strong>" . __( 'No forums were found!', 'bbpress' ) . "</strong></li></ol>\n";
					return;
				}

				echo "<li>" . sprintf( __( 'Total number of forums: %s', 'bbpress' ), count( $forums ) ) . "</li>\n";

				$forum_map     = array();
				$post_statuses = array( 'publish', $bbp->trash_status_id, $bbp->spam_status_id );

				foreach ( (array) $forums as $forum ) {
					echo "<li>" . sprintf( __( 'Processing forum #%1$s (<a href="%2$s">%3$s</a>)', 'bbpress' ), $forum->forum_id, get_forum_link( $forum->forum_id ), esc_html( $forum->forum_name ) ) . "\n<ul>\n";

					// Insert the forum and add it to the map.
					$inserted_forum      =  wp_insert_post( array(
						'post_author'    => get_current_user_id(),
						'post_content'   => $forum->forum_desc,
						'post_title'     => $forum->forum_name,
						'post_excerpt'   => '',
						'post_status'    => 'publish',
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
						'post_name'      => $forum->forum_slug,
						'post_parent'    => !empty( $forum->forum_parent ) ? $forum_map[$forum->forum_parent] : 0,
						'post_type'      => bbp_get_forum_post_type(),
						'menu_order'     => $forum->forum_order
					) );

					$forum_map[$forum->forum_id] = $inserted_forum;

					if ( !empty( $inserted_forum ) && !is_wp_error( $inserted_forum ) ) {
						echo "<li>" . sprintf( __( 'Added the forum as forum #<a href="%1$s">%2$s</a>', 'bbpress' ), get_permalink( $inserted_forum ), $inserted_forum ) . "</li>\n";
					} else {
						echo "<li><em>" . __( 'There was a problem in adding the forum.', 'bbpress' ) . "</em></li></ul></li>\n";
						continue;
					}

					$topics_query = new BB_Query( 'topic', array(
						'forum_id'     => $forum->forum_id,
						'per_page'     => -1,
						'topic_status' => 'all'
					) );

					$topics = $topics_query->results;

					// In standalone, categories can have topics, but this is not the case in plugin
					// So make the forum category if it doesn't have topics
					// Else close it if it's a category and has topics
					if ( bb_get_forum_is_category( $forum->forum_id ) ) {

						if ( count( $topics ) == 0 ) {
							bbp_categorize_forum( $inserted_forum );
							echo "<li>" . __( 'The forum is a category and has no topics.', 'bbpress' ) . "</li>\n</ul>\n</li>";

							continue;
						} else {
							bbp_close_forum( $inserted_forum );
							echo "<li>" . __( 'The forum is a category but has topics, so it has been set as closed on the new board.', 'bbpress' ) . "</li>\n";
						}

					}

					bb_cache_first_posts( $topics );

					echo "<li>" . sprintf( __( 'Total number of topics in the forum: %s', 'bbpress' ), count( $topics ) ) . "</li>\n";

					foreach ( (array) $topics as $topic ) {
						$first_post         =  bb_get_first_post( $topic->topic_id );

						// If the topic is public, check if it's open and set the status accordingly
						$topic_status       =  $topic->topic_status == 0 ? ( $topic->topic_open == 0 ? $bbp->closed_status_id : $post_statuses[$topic->topic_status] ) : $post_statuses[$topic->topic_status];

						$inserted_topic     =  wp_insert_post( array(
							'post_parent'   => $inserted_forum,
							'post_author'   => $topic->topic_poster,
							'post_content'  => $first_post->post_text,
							'post_title'    => $topic->topic_title,
							'post_name'     => $topic->topic_slug,
							'post_status'   => $topic_status,
							'post_date_gmt' => $topic->topic_start_time,
							'post_date'     => get_date_from_gmt( $topic->topic_start_time ),
							'post_type'     => bbp_get_topic_post_type(),
							'tax_input'     => array( 'topic-tag' => array_map( array( $this, 'get_tag_name' ), bb_get_public_tags( $topic->topic_id ) ) )
						) );

						if ( !empty( $inserted_topic ) && !is_wp_error( $inserted_topic ) ) {

							// Loginless Posting
							if ( $topic->topic_poster == 0 ) {
								update_post_meta( $inserted_topic, '_bbp_anonymous_name',    bb_get_post_meta( 'post_author', $first_post->post_id ) );
								update_post_meta( $inserted_topic, '_bbp_anonymous_email',   bb_get_post_meta( 'post_email',  $first_post->post_id ) );
								update_post_meta( $inserted_topic, '_bbp_anonymous_website', bb_get_post_meta( 'post_url',    $first_post->post_id ) );
							}

							// Author IP
							update_post_meta( $inserted_topic, '_bbp_author_ip', $first_post->poster_ip );

							// Forum topic meta
							update_post_meta( $inserted_topic, '_bbp_forum_id', $inserted_forum );
							update_post_meta( $inserted_topic, '_bbp_topic_id', $inserted_topic );

							$posts = bb_cache_posts( $bbdb->prepare( 'SELECT * FROM ' . $bbdb->posts . ' WHERE topic_id = %d AND post_id != %d ORDER BY post_time', $topic->topic_id, $first_post->post_id ) );

							$replies        = 0;
							$hidden_replies = 0;
							$last_reply     = 0;
							$post           = null;

							foreach ( (array) $posts as $post ) {

								// Pingback
								if ( $post->poster_id == 0 && $pingback_uri = bb_get_post_meta( 'pingback_uri', $post->post_id ) ) {
									$pingback = wp_insert_comment( wp_filter_comment( array(
										'comment_post_ID'    => $inserted_topic,
										'comment_author'     => bb_get_post_meta( 'pingback_title', $post->post_id ),
										'comment_author_url' => $pingback_uri,
										'comment_author_IP'  => $post->poster_ip,
										'comment_date_gmt'   => $post->post_time,
										'comment_date'       => get_date_from_gmt( $post->post_time ),
										'comment_content'    => $post->post_text,
										'comment_approved'   => $post->post_status == 0 ? 1 : ( $post->post_status == 2 ? 'spam' : 0 ),
										'comment_type'       => 'pingback'
									) ) );

								// Normal post
								} else {
									$reply_title        =  sprintf( __( 'Reply To: %s', 'bbpress' ), $topic->topic_title );

									$last_reply         =  wp_insert_post( array(
										'post_parent'   => $inserted_topic,
										'post_author'   => $post->poster_id,
										'post_date_gmt' => $post->post_time,
										'post_date'     => get_date_from_gmt( $post->post_time ),
										'post_title'    => $reply_title,
										'post_name'     => sanitize_title_with_dashes( $reply_title ),
										'post_status'   => $post_statuses[$post->post_status],
										'post_type'     => bbp_get_reply_post_type(),
										'post_content'  => $post->post_text
									) );

									// Loginless
									if ( $post->poster_id == 0 ) {
										update_post_meta( $last_reply, '_bbp_anonymous_name',    bb_get_post_meta( 'post_author', $post->post_id ) );
										update_post_meta( $last_reply, '_bbp_anonymous_email',   bb_get_post_meta( 'post_email',  $post->post_id ) );
										update_post_meta( $last_reply, '_bbp_anonymous_website', bb_get_post_meta( 'post_url',    $post->post_id ) );
									}

									// Author IP
									update_post_meta( $last_reply, '_bbp_author_ip', $post->poster_ip );

									// Reply Parents
									update_post_meta( $last_reply, '_bbp_forum_id', $inserted_forum );
									update_post_meta( $last_reply, '_bbp_topic_id', $inserted_topic );

									bbp_update_reply_walker( $last_reply );
								}

								if ( $post->post_status != 0 )
									$hidden_replies++;
								else
									$replies++;
							}

							// Only add favorites and subscriptions if the topic is public
							if ( in_array( $topic_status, array( 'publish', $bbp->closed_status_id ) ) ) {

								// Favorites
								foreach ( (array) $this->bb_get_topic_favoriters( $topic->topic_id )  as $favoriter  )
									bbp_add_user_favorite    ( $favoriter,  $inserted_topic );

								// Subscriptions
								foreach ( (array) $this->bb_get_topic_subscribers( $topic->topic_id ) as $subscriber )
									bbp_add_user_subscription( $subscriber, $inserted_topic );
							}

							// Topic stickiness
							switch ( $topic->topic_sticky ) {

								// Forum
								case 1 :
									bbp_stick_topic( $inserted_topic );
									break;

								// Front
								case 2 :
									bbp_stick_topic( $inserted_topic, true );
									break;
							}

							// Last active
							$last_active_id   = !empty( $last_reply ) ? $last_reply      : $inserted_topic;
							$last_active_time = !empty( $post       ) ? $post->post_time : $first_post->post_time;

							// Reply topic meta
							update_post_meta( $inserted_topic, '_bbp_last_reply_id',      $last_reply       );
							update_post_meta( $inserted_topic, '_bbp_last_active_id',     $last_active_id   );
							update_post_meta( $inserted_topic, '_bbp_last_active_time',   $last_active_time );
							update_post_meta( $inserted_topic, '_bbp_reply_count',        $replies          );
							update_post_meta( $inserted_topic, '_bbp_hidden_reply_count', $hidden_replies   );

							// Voices will be done by recount

							bbp_update_topic_walker( $inserted_topic );

							echo "<li>"     . sprintf( __( 'Added topic #%1$s (<a href="%2$s">%3$s</a>) as topic #<a href="%4$s">%5$s</a> with %6$s replies.', 'bbpress' ), $topic->topic_id, get_topic_link( $topic->topic_id ), esc_html( $topic->topic_title ), get_permalink( $inserted_topic ), $inserted_topic, $replies ) . "</li>\n";
						} else {
							echo "<li><em>" . sprintf( __( 'There was a problem in adding topic #1$s (<a href="%2$s">%3$s</a>).', 'bbpress' ), $topic->topic_id, get_topic_link( $topic->topic_id ), esc_html( $topic->topic_title ) ) . "</em></li></ul></li>\n";
							continue;
						}
					}

					echo "</ul>\n</li>\n";

				} ?>

			</ol>

			<?php

			// Clean up database and we're out
			$this->cleanup();
			do_action( 'import_done', 'bbpress' );

			?>

			<p><strong><?php printf( __( 'Your forums, topics and posts have all been imported, but wait &#8211; there&#8217;s more! Now we need to do a <a href="%s">recount</a> to get the counts in sync! Yes, we&#8217;re bad at Math.', 'bbpress' ), add_query_arg( array( 'page' => 'bbp-recount' ), get_admin_url( 0, 'tools.php' ) ) ); ?></strong></p>

			<h4><?php printf( __( 'After that it\'s all done. <a href="%s">Have fun!</a> :)', 'bbpress' ), home_url() ); ?></h4>

		</div>

		<?php
	}

} // class bbPress_Importer

$bbpress_import = new bbPress_Importer();

register_importer( 'bbpress', __( 'bbPress Standalone', 'bbpress' ), __( 'Import your bbPress standalone board.', 'bbpress' ), array( $bbpress_import, 'dispatch' ) );

?>
