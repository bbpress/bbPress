<?php

/**
 * bbPress Admin Settings
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Sections ******************************************************************/

/**
 * Get the Forums settings sections.
 *
 * @since 2.1.0 bbPress (r4001)
 *
 * @return array
 */
function bbp_admin_get_settings_sections() {

	// Filter & return
	return (array) apply_filters( 'bbp_admin_get_settings_sections', array(

		// Settings
		'bbp_settings_users' => array(
			'title'    => esc_html__( 'Forum User Settings', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_user_section',
			'page'     => 'discussion'
		),
		'bbp_settings_features' => array(
			'title'    => esc_html__( 'Forum Features', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_features_section',
			'page'     => 'discussion'
		),
		'bbp_settings_theme_compat' => array(
			'title'    => esc_html__( 'Forum Theme Packages', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_subtheme_section',
			'page'     => 'general'
		),
		'bbp_settings_per_page' => array(
			'title'    => esc_html__( 'Topics and Replies Per Page', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_per_page_section',
			'page'     => 'reading'
		),
		'bbp_settings_per_rss_page' => array(
			'title'    => esc_html__( 'Topics and Replies Per RSS Page', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_per_rss_page_section',
			'page'     => 'reading',
		),
		'bbp_settings_root_slugs' => array(
			'title'    => esc_html__( 'Forum Root Slug', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_root_slug_section',
			'page'     => 'permalink'
		),
		'bbp_settings_single_slugs' => array(
			'title'    => esc_html__( 'Forum Single Slugs', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_single_slug_section',
			'page'     => 'permalink',
		),
		'bbp_settings_user_slugs' => array(
			'title'    => esc_html__( 'Forum User Slugs', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_user_slug_section',
			'page'     => 'permalink',
		),

		// Extend
		'bbp_settings_buddypress' => array(
			'title'    => esc_html__( 'Forum Integration for BuddyPress', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_buddypress_section',
			'page'     => 'buddypress',
		),
		'bbp_settings_akismet' => array(
			'title'    => esc_html__( 'Forum Integration for Akismet', 'bbpress' ),
			'callback' => 'bbp_admin_setting_callback_akismet_section',
			'page'     => 'discussion'
		),

		// Converter
		'bbp_converter_connection' => array(
			'title'    => esc_html__( 'Database Settings', 'bbpress' ),
			'callback' => 'bbp_converter_setting_callback_main_section',
			'page'     => 'converter'
		),
		'bbp_converter_options' => array(
			'title'    => esc_html__( 'Options', 'bbpress' ),
			'callback' => 'bbp_converter_setting_callback_options_section',
			'page'     => 'converter'
		)
	) );
}

/**
 * Get all of the settings fields.
 *
 * @since 2.1.0 bbPress (r4001)
 *
 * @return array
 */
function bbp_admin_get_settings_fields() {

	// Filter & return
	return (array) apply_filters( 'bbp_admin_get_settings_fields', array(

		/** User Section ******************************************************/

		'bbp_settings_users' => array(

			// Default role setting
			'_bbp_default_role' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'args'              => array()
			),

			// Allow global access
			'_bbp_allow_global_access' => array(
				'title'             => esc_html__( 'Roles', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_global_access',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow content throttling
			'_bbp_allow_content_throttle' => array(
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Throttle setting
			'_bbp_throttle_time' => array(
				'title'             => esc_html__( 'Flooding', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_throttle',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow content editing
			'_bbp_allow_content_edit' => array(
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Edit lock setting
			'_bbp_edit_lock' => array(
				'title'             => esc_html__( 'Editing', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_editlock',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow anonymous posting setting
			'_bbp_allow_anonymous' => array(
				'title'             => esc_html__( 'Anonymous', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_anonymous',
				'sanitize_callback' => 'intval',
				'args'              => array()
			)
		),

		/** Features Section **************************************************/

		'bbp_settings_features' => array(

			// Allow auto embedding setting
			'_bbp_use_autoembed' => array(
				'title'             => esc_html__( 'Auto-embed links', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_use_autoembed',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Set reply threading level
			'_bbp_thread_replies_depth' => array(
				'title'             => esc_html__( 'Reply Threading', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_thread_replies_depth',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow threaded replies
			'_bbp_allow_threaded_replies' => array(
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow topic and reply revisions
			'_bbp_allow_revisions' => array(
				'title'             => esc_html__( 'Revisions', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_revisions',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow favorites setting
			'_bbp_enable_favorites' => array(
				'title'             => esc_html__( 'Favorites', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_favorites',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow subscriptions setting
			'_bbp_enable_subscriptions' => array(
				'title'             => esc_html__( 'Subscriptions', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_subscriptions',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow engagements setting
			'_bbp_enable_engagements' => array(
				'title'             => esc_html__( 'Engagements', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_engagements',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow topic tags
			'_bbp_allow_topic_tags' => array(
				'title'             => esc_html__( 'Topic tags', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_topic_tags',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow topic tags
			'_bbp_allow_search' => array(
				'title'             => esc_html__( 'Search', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_search',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow fancy editor setting
			'_bbp_use_wp_editor' => array(
				'title'             => esc_html__( 'Post Formatting', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_use_wp_editor',
				'args'              => array(),
				'sanitize_callback' => 'intval'
			),

			// Allow per-forum moderators
			'_bbp_allow_forum_mods' => array(
				'title'             => esc_html__( 'Forum Moderators', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_forum_mods',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Allow moderators to edit users
			'_bbp_allow_super_mods' => array(
				'title'             => esc_html__( 'Super Moderators', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_super_mods',
				'sanitize_callback' => 'intval',
				'capability'        => 'edit_users',
				'args'              => array()
			)
		),

		/** Theme Packages ****************************************************/

		'bbp_settings_theme_compat' => array(

			// Theme package setting
			'_bbp_theme_package_id' => array(
				'title'             => esc_html__( 'Current Package', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_subtheme_id',
				'sanitize_callback' => 'esc_sql',
				'args'              => array()
			)
		),

		/** Per Page Section **************************************************/

		'bbp_settings_per_page' => array(

			// Topics per page setting
			'_bbp_topics_per_page' => array(
				'title'             => esc_html__( 'Topics', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_topics_per_page',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for' => '_bbp_topics_per_page' )
			),

			// Replies per page setting
			'_bbp_replies_per_page' => array(
				'title'             => esc_html__( 'Replies', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_replies_per_page',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for' => '_bbp_replies_per_page' )
			)
		),

		/** Per RSS Page Section **********************************************/

		'bbp_settings_per_rss_page' => array(

			// Topics per page setting
			'_bbp_topics_per_rss_page' => array(
				'title'             => esc_html__( 'Topics', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_topics_per_rss_page',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for' => '_bbp_topics_per_rss_page' )
			),

			// Replies per page setting
			'_bbp_replies_per_rss_page' => array(
				'title'             => esc_html__( 'Replies', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_replies_per_rss_page',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for' => '_bbp_replies_per_rss_page' )
			)
		),

		/** Front Slugs *******************************************************/

		'bbp_settings_root_slugs' => array(

			// Root slug setting
			'_bbp_root_slug' => array(
				'title'             => esc_html__( 'Forum Root', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_root_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for' => '_bbp_root_slug' )
			),

			// Include root setting
			'_bbp_include_root' => array(
				'title'             => esc_html__( 'Forum Prefix', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_include_root',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// What to show on Forum Root
			'_bbp_show_on_root' => array(
				'title'             => esc_html__( 'Forum root should show', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_show_on_root',
				'sanitize_callback' => 'sanitize_text_field',
				'args'              => array( 'label_for'=>'_bbp_show_on_root' )
			),
		),

		/** Single Slugs ******************************************************/

		'bbp_settings_single_slugs' => array(

			// Forum slug setting
			'_bbp_forum_slug' => array(
				'title'             => esc_html__( 'Forum', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_forum_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_forum_slug' )
			),

			// Topic slug setting
			'_bbp_topic_slug' => array(
				'title'             => esc_html__( 'Topic', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_topic_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_topic_slug' )
			),

			// Topic tag slug setting
			'_bbp_topic_tag_slug' => array(
				'title'             => esc_html__( 'Topic Tag', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_topic_tag_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_topic_tag_slug' )
			),

			// View slug setting
			'_bbp_view_slug' => array(
				'title'             => esc_html__( 'Topic View', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_view_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_view_slug' )
			),

			// Reply slug setting
			'_bbp_reply_slug' => array(
				'title'             => _x( 'Reply', 'noun', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_reply_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_reply_slug' )
			),

			// Edit slug setting
			'_bbp_edit_slug' => array(
				'title'             => esc_html__( 'Edit', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_edit_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_edit_slug' )
			),

			// Search slug setting
			'_bbp_search_slug' => array(
				'title'             => esc_html__( 'Search', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_search_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_search_slug' )
			)
		),

		/** User Slugs ********************************************************/

		'bbp_settings_user_slugs' => array(

			// User slug setting
			'_bbp_user_slug' => array(
				'title'             => esc_html__( 'User Base', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_user_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_user_slug' )
			),

			// Topics slug setting
			'_bbp_topic_archive_slug' => array(
				'title'             => esc_html__( 'Topics Started', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_topic_archive_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_topic_archive_slug' )
			),

			// Replies slug setting
			'_bbp_reply_archive_slug' => array(
				'title'             => esc_html__( 'Replies Created', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_reply_archive_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_reply_archive_slug' )
			),

			// Favorites slug setting
			'_bbp_user_favs_slug' => array(
				'title'             => esc_html__( 'Favorite Topics', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_user_favs_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_user_favs_slug' )
			),

			// Subscriptions slug setting
			'_bbp_user_subs_slug' => array(
				'title'             => esc_html__( 'Subscriptions', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_user_subs_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_user_subs_slug' )
			),

			// Engagements slug setting
			'_bbp_user_engs_slug' => array(
				'title'             => esc_html__( 'Engagements', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_user_engagements_slug',
				'sanitize_callback' => 'bbp_sanitize_slug',
				'args'              => array( 'label_for'=>'_bbp_user_engs_slug' )
			)
		),

		/** BuddyPress ********************************************************/

		'bbp_settings_buddypress' => array(

			// Are group forums enabled?
			'_bbp_enable_group_forums' => array(
				'title'             => esc_html__( 'Group Forums', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_group_forums',
				'sanitize_callback' => 'intval',
				'args'              => array()
			),

			// Group forums parent forum ID
			'_bbp_group_forums_root_id' => array(
				'title'             => esc_html__( 'Primary Forum', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_group_forums_root_id',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for'=>'_bbp_group_forums_root_id' )
			)
		),

		/** Akismet ***********************************************************/

		'bbp_settings_akismet' => array(

			// Should we use Akismet
			'_bbp_enable_akismet' => array(
				'title'             => esc_html__( 'Use Akismet', 'bbpress' ),
				'callback'          => 'bbp_admin_setting_callback_akismet',
				'sanitize_callback' => 'intval',
				'args'              => array()
			)
		),

		/** Converter Page ****************************************************/

		// Connection
		'bbp_converter_connection' => array(

			// System Select
			'_bbp_converter_platform' => array(
				'title'             => esc_html__( 'Select Platform', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_platform',
				'sanitize_callback' => 'sanitize_text_field',
				'args'              => array( 'label_for'=> '_bbp_converter_platform' )
			),

			// Database Server
			'_bbp_converter_db_server' => array(
				'title'             => esc_html__( 'Database Server', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_dbserver',
				'sanitize_callback' => 'sanitize_text_field',
				'args'              => array( 'label_for'=> '_bbp_converter_db_server' )
			),

			// Database Server Port
			'_bbp_converter_db_port' => array(
				'title'             => esc_html__( 'Database Port', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_dbport',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for'=> '_bbp_converter_db_port' )
			),

			// Database Name
			'_bbp_converter_db_name' => array(
				'title'             => esc_html__( 'Database Name', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_dbname',
				'sanitize_callback' => 'sanitize_text_field',
				'args'              => array( 'label_for'=> '_bbp_converter_db_name' )
			),

			// Database User
			'_bbp_converter_db_user' => array(
				'title'             => esc_html__( 'Database User', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_dbuser',
				'sanitize_callback' => 'sanitize_text_field',
				'args'              => array( 'label_for'=> '_bbp_converter_db_user' )
			),

			// Database Password
			'_bbp_converter_db_pass' => array(
				'title'             => esc_html__( 'Database Password', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_dbpass',
				'sanitize_callback' => 'sanitize_text_field',
				'args'              => array( 'label_for'=> '_bbp_converter_db_pass' )
			),

			// Database Prefix
			'_bbp_converter_db_prefix' => array(
				'title'             => esc_html__( 'Table Prefix', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_dbprefix',
				'sanitize_callback' => 'sanitize_text_field',
				'args'              => array( 'label_for'=> '_bbp_converter_db_prefix' )
			)
		),

		// Options
		'bbp_converter_options' => array(

			// Rows Limit
			'_bbp_converter_rows' => array(
				'title'             => esc_html__( 'Rows Limit', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_rows',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for'=> '_bbp_converter_rows' )
			),

			// Delay Time
			'_bbp_converter_delay_time' => array(
				'title'             => esc_html__( 'Delay Time', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_delay_time',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for'=> '_bbp_converter_delay_time' )
			),

			// Convert Users
			'_bbp_converter_convert_users' => array(
				'title'             => esc_html__( 'Convert Users', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_convert_users',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for'=> '_bbp_converter_convert_users' )
			),

			// Halt
			'_bbp_converter_halt' => array(
				'title'             => esc_html__( 'Stop on Error', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_halt',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for'=> '_bbp_converter_halt' )
			),

			// Restart
			'_bbp_converter_restart' => array(
				'title'             => esc_html__( 'Start Over', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_restart',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for'=> '_bbp_converter_restart' )
			),

			// Clean
			'_bbp_converter_clean' => array(
				'title'             => esc_html__( 'Helper Data', 'bbpress' ),
				'callback'          => 'bbp_converter_setting_callback_clean',
				'sanitize_callback' => 'intval',
				'args'              => array( 'label_for'=> '_bbp_converter_clean' )
			)
		)
	) );
}

/**
 * Get settings fields by section.
 *
 * @since 2.1.0 bbPress (r4001)
 *
 * @param string $section_id ID of the section to get fields for
 * @staticvar array $fields All of the available fields
 * @return mixed False if section is invalid, array of fields otherwise.
 */
function bbp_admin_get_settings_fields_for_section( $section_id = '' ) {
	static $fields = array();

	// Default return value
	$retval = array();

	// Bail if section is empty
	if ( empty( $section_id ) ) {
		return false;
	}

	// Get all of the fields (so we can snag one section of them)
	if ( empty( $fields ) ) {
		$fields = bbp_admin_get_settings_fields();
	}

	// Get the field by section
	if ( isset( $fields[ $section_id ] ) ) {
		$retval = $fields[ $section_id ];
	}

	// Filter & return
	return (array) apply_filters( 'bbp_admin_get_settings_fields_for_section', $retval, $section_id );
}

/** User Section **************************************************************/

/**
 * User settings section description for the settings page
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_user_section() {
?>

	<p><?php esc_html_e( 'Setting time limits and other user posting capabilities', 'bbpress' ); ?></p>

<?php
}


/**
 * Edit lock setting field
 *
 * @since 2.0.0 bbPress (r2737)
 */
function bbp_admin_setting_callback_editlock() {

	// Start the output buffer for the second option
	ob_start(); ?>

	</label>
	<label for="_bbp_edit_lock">
		<input name="_bbp_edit_lock" id="_bbp_edit_lock" type="number" min="0" step="1" value="<?php bbp_form_option( '_bbp_edit_lock', '5' ); ?>" class="small-text"<?php bbp_maybe_admin_setting_disabled( '_bbp_edit_lock' ); ?> />

	<?php $select = ob_get_clean(); ?>

	<label for="_bbp_allow_content_edit">
		<input name="_bbp_allow_content_edit" id="_bbp_allow_content_edit" type="checkbox" value="1" <?php checked( bbp_allow_content_edit( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_content_edit' ); ?> />
		<?php printf( esc_html__( 'Allow users to edit their content for %s minutes after posting', 'bbpress' ), $select ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'If checked, setting to "0 minutes" allows editing forever.', 'bbpress' ); ?></p>

<?php
}

/**
 * Throttle setting field
 *
 * @since 2.0.0 bbPress (r2737)
 */
function bbp_admin_setting_callback_throttle() {

	// Start the output buffer for the second option
	ob_start(); ?>

	</label>
	<label for="_bbp_throttle_time">
		<input name="_bbp_throttle_time" id="_bbp_throttle_time" type="number" min="0" step="1" value="<?php bbp_form_option( '_bbp_throttle_time', '10' ); ?>" class="small-text"<?php bbp_maybe_admin_setting_disabled( '_bbp_throttle_time' ); ?> />

	<?php $select = ob_get_clean(); ?>

	<label for="_bbp_allow_content_throttle">
		<input name="_bbp_allow_content_throttle" id="_bbp_allow_content_throttle" type="checkbox" value="1" <?php checked( bbp_allow_content_throttle( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_content_throttle' ); ?> />
		<?php printf( esc_html__( 'Allow flood protection by throttling users for %s seconds after posting', 'bbpress' ), $select ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'Use this to discourage users from spamming your forums.', 'bbpress' ); ?></p>

<?php
}

/**
 * Allow anonymous posting setting field
 *
 * @since 2.0.0 bbPress (r2737)
 */
function bbp_admin_setting_callback_anonymous() {
?>

	<input name="_bbp_allow_anonymous" id="_bbp_allow_anonymous" type="checkbox" value="1" <?php checked( bbp_allow_anonymous( false ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_anonymous' ); ?> />
	<label for="_bbp_allow_anonymous"><?php esc_html_e( 'Allow guest users without accounts to create topics and replies', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'Works best on intranets or paired with antispam measures like Akismet.', 'bbpress' ); ?></p>

<?php
}

/**
 * Allow global access setting field
 *
 * @since 2.0.0 bbPress (r3378)
 */
function bbp_admin_setting_callback_global_access() {

	// Get the default role once rather than loop repeatedly below
	$default_role = bbp_get_default_role();
	$roles        = bbp_get_dynamic_roles();

	// Start the output buffer for the select dropdown
	ob_start(); ?>

	</label>
	<label for="_bbp_default_role">
		<select name="_bbp_default_role" id="_bbp_default_role" <?php bbp_maybe_admin_setting_disabled( '_bbp_default_role' ); ?>>
		<?php foreach ( $roles as $role => $details ) : ?>

			<option <?php selected( $default_role, $role ); ?> value="<?php echo esc_attr( $role ); ?>"><?php echo bbp_translate_user_role( $details['name'] ); ?></option>

		<?php endforeach; ?>
		</select>

	<?php $select = ob_get_clean(); ?>

	<label for="_bbp_allow_global_access">
		<input name="_bbp_allow_global_access" id="_bbp_allow_global_access" type="checkbox" value="1" <?php checked( bbp_allow_global_access( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_global_access' ); ?> />
		<?php printf( esc_html__( 'Automatically give registered visitors the %s forum role', 'bbpress' ), $select ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'Uncheck this to manually assign all user access to your forums.', 'bbpress' ); ?></p>

<?php
}

/** Features Section **********************************************************/

/**
 * Features settings section description for the settings page
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_features_section() {
?>

	<p><?php esc_html_e( 'Forum features that can be toggled on and off', 'bbpress' ); ?></p>

<?php
}

/**
 * Allow favorites setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_favorites() {
?>

	<input name="_bbp_enable_favorites" id="_bbp_enable_favorites" type="checkbox" value="1" <?php checked( bbp_is_favorites_active( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_enable_favorites' ); ?> />
	<label for="_bbp_enable_favorites"><?php esc_html_e( 'Allow users to mark topics as favorites', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow subscriptions setting field
 *
 * @since 2.0.0 bbPress (r2737)
 */
function bbp_admin_setting_callback_subscriptions() {
?>

	<input name="_bbp_enable_subscriptions" id="_bbp_enable_subscriptions" type="checkbox" value="1" <?php checked( bbp_is_subscriptions_active( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_enable_subscriptions' ); ?> />
	<label for="_bbp_enable_subscriptions"><?php esc_html_e( 'Allow users to subscribe to forums and topics', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow engagements setting field
 *
 * @since 2.0.0 bbPress (r2737)
 */
function bbp_admin_setting_callback_engagements() {
?>

	<input name="_bbp_enable_engagements" id="_bbp_enable_engagements" type="checkbox" value="1" <?php checked( bbp_is_engagements_active( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_enable_engagements' ); ?> />
	<label for="_bbp_enable_engagements"><?php esc_html_e( 'Allow tracking of topics each user engages in', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow topic tags setting field
 *
 * @since 2.4.0 bbPress (r4944)
 */
function bbp_admin_setting_callback_topic_tags() {
?>

	<input name="_bbp_allow_topic_tags" id="_bbp_allow_topic_tags" type="checkbox" value="1" <?php checked( bbp_allow_topic_tags( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_topic_tags' ); ?> />
	<label for="_bbp_allow_topic_tags"><?php esc_html_e( 'Allow topics to have tags', 'bbpress' ); ?></label>

<?php
}

/**
 * Allow forum-mods setting field
 *
 * @since 2.6.0 bbPress (r5834)
 */
function bbp_admin_setting_callback_forum_mods() {
?>

	<input name="_bbp_allow_forum_mods" id="_bbp_allow_forum_mods" type="checkbox" value="1" <?php checked( bbp_allow_forum_mods( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_forum_mods' ); ?> />
	<label for="_bbp_allow_forum_mods"><?php esc_html_e( 'Allow forums to have dedicated moderators', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'This does not include the ability to edit users.', 'bbpress' ); ?></p>

<?php
}

/**
 * Allow super-mods setting field
 *
 * @since 2.6.0 bbPress (r6562)
 */
function bbp_admin_setting_callback_super_mods() {
?>

	<input name="_bbp_allow_super_mods" id="_bbp_allow_super_mods" type="checkbox" value="1" <?php checked( bbp_allow_super_mods( false ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_super_mods' ); ?> />
	<label for="_bbp_allow_super_mods"><?php esc_html_e( 'Allow Moderators and Keymasters to edit users', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'This includes roles, passwords, and email addresses.', 'bbpress' ); ?></p>

<?php
}

/**
 * Allow forum wide search
 *
 * @since 2.4.0 bbPress (r4970)
 */
function bbp_admin_setting_callback_search() {
?>

	<input name="_bbp_allow_search" id="_bbp_allow_search" type="checkbox" value="1" <?php checked( bbp_allow_search( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_search' ); ?> />
	<label for="_bbp_allow_search"><?php esc_html_e( 'Allow forum wide search', 'bbpress' ); ?></label>

<?php
}

/**
 * Hierarchical reply maximum depth level setting field
 *
 * Replies will be threaded if depth is 2 or greater
 *
 * @since 2.4.0 bbPress (r4944)
 */
function bbp_admin_setting_callback_thread_replies_depth() {

	// Set maximum depth for dropdown
	$max_depth     = (int) apply_filters( 'bbp_thread_replies_depth_max', 10 );
	$current_depth = bbp_thread_replies_depth();

	// Start an output buffer for the select dropdown
	ob_start(); ?>

	</label>
	<label for="_bbp_thread_replies_depth">
		<select name="_bbp_thread_replies_depth" id="_bbp_thread_replies_depth" <?php bbp_maybe_admin_setting_disabled( '_bbp_thread_replies_depth' ); ?>>
		<?php for ( $i = 2; $i <= $max_depth; $i++ ) : ?>

			<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, $current_depth ); ?>><?php echo esc_html( $i ); ?></option>

		<?php endfor; ?>
		</select>

	<?php $select = ob_get_clean(); ?>

	<label for="_bbp_allow_threaded_replies">
		<input name="_bbp_allow_threaded_replies" id="_bbp_allow_threaded_replies" type="checkbox" value="1" <?php checked( '1', bbp_allow_threaded_replies( false ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_threaded_replies' ); ?> />
		<?php printf( esc_html__( 'Enable threaded (nested) replies %s levels deep', 'bbpress' ), $select ); ?>
	</label>

<?php
}

/**
 * Allow topic and reply revisions
 *
 * @since 2.0.0 bbPress (r3412)
 */
function bbp_admin_setting_callback_revisions() {
?>

	<input name="_bbp_allow_revisions" id="_bbp_allow_revisions" type="checkbox" value="1" <?php checked( bbp_allow_revisions( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_allow_revisions' ); ?> />
	<label for="_bbp_allow_revisions"><?php esc_html_e( 'Allow topic and reply revision logging', 'bbpress' ); ?></label>

<?php
}

/**
 * Use the WordPress editor setting field
 *
 * @since 2.1.0 bbPress (r3586)
 */
function bbp_admin_setting_callback_use_wp_editor() {
?>

	<input name="_bbp_use_wp_editor" id="_bbp_use_wp_editor" type="checkbox" value="1" <?php checked( bbp_use_wp_editor( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_use_wp_editor' ); ?> />
	<label for="_bbp_use_wp_editor"><?php esc_html_e( 'Add toolbar & buttons to textareas to help with HTML formatting', 'bbpress' ); ?></label>

<?php
}

/**
 * Main subtheme section
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_subtheme_section() {
?>

	<p><?php esc_html_e( 'How your forum content is displayed within your existing theme.', 'bbpress' ); ?></p>

<?php
}

/**
 * Use the WordPress editor setting field
 *
 * @since 2.1.0 bbPress (r3586)
 */
function bbp_admin_setting_callback_subtheme_id() {

	// Declare locale variable
	$theme_options   = '';
	$current_package = bbp_get_theme_package_id( 'default' );

	// Note: This should never be empty. /templates/ is the
	// canonical backup if no other packages exist. If there's an error here,
	// something else is wrong.
	//
	// @see bbPress::register_theme_packages()
	foreach ( (array) bbpress()->theme_compat->packages as $id => $theme ) {
		$theme_options .= '<option value="' . esc_attr( $id ) . '"' . selected( $theme->id, $current_package, false ) . '>' . esc_html( $theme->name ) . '</option>';
	}

	if ( ! empty( $theme_options ) ) : ?>

		<select name="_bbp_theme_package_id" id="_bbp_theme_package_id" <?php bbp_maybe_admin_setting_disabled( '_bbp_theme_package_id' ); ?>><?php echo $theme_options; ?></select>
		<label for="_bbp_theme_package_id"><?php esc_html_e( 'will serve all bbPress templates', 'bbpress' ); ?></label>

	<?php else : ?>

		<p><?php esc_html_e( 'No template packages available.', 'bbpress' ); ?></p>

	<?php endif;
}

/**
 * Allow oEmbed in replies
 *
 * @since 2.1.0 bbPress (r3752)
 */
function bbp_admin_setting_callback_use_autoembed() {
?>

	<input name="_bbp_use_autoembed" id="_bbp_use_autoembed" type="checkbox" value="1" <?php checked( bbp_use_autoembed( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_use_autoembed' ); ?> />
	<label for="_bbp_use_autoembed"><?php esc_html_e( 'Embed media (YouTube, Twitter, Flickr, etc...) directly into topics and replies', 'bbpress' ); ?></label>

<?php
}

/** Per Page Section **********************************************************/

/**
 * Per page settings section description for the settings page
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_per_page_section() {
?>

	<p><?php esc_html_e( 'How many topics and replies to show per page', 'bbpress' ); ?></p>

<?php
}

/**
 * Topics per page setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_topics_per_page() {
?>

	<input name="_bbp_topics_per_page" id="_bbp_topics_per_page" type="number" min="1" step="1" value="<?php bbp_form_option( '_bbp_topics_per_page', '15' ); ?>" class="small-text"<?php bbp_maybe_admin_setting_disabled( '_bbp_topics_per_page' ); ?> />
	<?php esc_html_e( 'per page', 'bbpress' ); ?>

<?php
}

/**
 * Replies per page setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_replies_per_page() {
?>

	<input name="_bbp_replies_per_page" id="_bbp_replies_per_page" type="number" min="1" step="1" value="<?php bbp_form_option( '_bbp_replies_per_page', '15' ); ?>" class="small-text"<?php bbp_maybe_admin_setting_disabled( '_bbp_replies_per_page' ); ?> />
	<?php esc_html_e( 'per page', 'bbpress' ); ?>

<?php
}

/** Per RSS Page Section ******************************************************/

/**
 * Per page settings section description for the settings page
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_per_rss_page_section() {
?>

	<p><?php esc_html_e( 'How many topics and replies to show per RSS page', 'bbpress' ); ?></p>

<?php
}

/**
 * Topics per RSS page setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_topics_per_rss_page() {
?>

	<input name="_bbp_topics_per_rss_page" id="_bbp_topics_per_rss_page" type="number" min="1" step="1" value="<?php bbp_form_option( '_bbp_topics_per_rss_page', '25' ); ?>" class="small-text"<?php bbp_maybe_admin_setting_disabled( '_bbp_topics_per_rss_page' ); ?> />
	<?php esc_html_e( 'per page', 'bbpress' ); ?>

<?php
}

/**
 * Replies per RSS page setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_replies_per_rss_page() {
?>

	<input name="_bbp_replies_per_rss_page" id="_bbp_replies_per_rss_page" type="number" min="1" step="1" value="<?php bbp_form_option( '_bbp_replies_per_rss_page', '25' ); ?>" class="small-text"<?php bbp_maybe_admin_setting_disabled( '_bbp_replies_per_rss_page' ); ?> />
	<?php esc_html_e( 'per page', 'bbpress' ); ?>

<?php
}

/** Slug Section **************************************************************/

/**
 * Slugs settings section description for the settings page
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_root_slug_section() {

	// Flush rewrite rules when this section is saved
	if ( isset( $_GET['settings-updated'] ) && isset( $_GET['page'] ) ) {
		flush_rewrite_rules();
	} ?>

	<p><?php esc_html_e( 'Customize your Forums root. Partner with a WordPress Page and use Shortcodes for more flexibility.', 'bbpress' ); ?></p>

<?php
}

/**
 * Root slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_root_slug() {
?>

	<input name="_bbp_root_slug" id="_bbp_root_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_root_slug', 'forums', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_root_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_root_slug', 'forums' );
}

/**
 * Include root slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_include_root() {
?>

	<input name="_bbp_include_root" id="_bbp_include_root" type="checkbox" value="1" <?php checked( bbp_include_root_slug() ); bbp_maybe_admin_setting_disabled( '_bbp_include_root' ); ?> />
	<label for="_bbp_include_root"><?php esc_html_e( 'Prefix all forum content with the Forum Root slug (Recommended)', 'bbpress' ); ?></label>

<?php
}

/**
 * Include root slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_show_on_root() {

	// Current setting
	$show_on_root = bbp_show_on_root();

	// Options for forum root output
	$root_options = array(
		'forums' => array(
			'name' => esc_attr__( 'Forum Index', 'bbpress' )
		),
		'topics' => array(
			'name' => esc_attr__( 'Topics by Last Post', 'bbpress' )
		)
	); ?>

	<select name="_bbp_show_on_root" id="_bbp_show_on_root" <?php bbp_maybe_admin_setting_disabled( '_bbp_show_on_root' ); ?>>

		<?php foreach ( $root_options as $option_id => $details ) : ?>

			<option <?php selected( $show_on_root, $option_id ); ?> value="<?php echo esc_attr( $option_id ); ?>"><?php echo esc_html( $details['name'] ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php

	// Look for theme support
	$forum_archive = basename( bbp_get_forum_archive_template() );

	// This setting doesn't work if the theme has an archive-forum.php template.
	if ( ! empty( $forum_archive ) ) : ?>

		<p class="description"><?php printf( esc_html__( 'This setting will be ignored because %s was found in your theme.', 'bbpress' ), '<code>' . $forum_archive . '</code>' ); ?></p>

	<?php endif;
}

/** User Slug Section *********************************************************/

/**
 * Slugs settings section description for the settings page
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_user_slug_section() {
?>

	<p><?php esc_html_e( 'Customize your user profile slugs.', 'bbpress' ); ?></p>

<?php
}

/**
 * User slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_user_slug() {
?>

	<input name="_bbp_user_slug" id="_bbp_user_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_user_slug', 'users', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_user_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_user_slug', 'users' );
}

/**
 * Topic archive slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_topic_archive_slug() {
?>

	<input name="_bbp_topic_archive_slug" id="_bbp_topic_archive_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_topic_archive_slug', 'topics', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_topic_archive_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_topic_archive_slug', 'topics' );
}

/**
 * Reply archive slug setting field
 *
 * @since 2.4.0 bbPress (r4932)
 */
function bbp_admin_setting_callback_reply_archive_slug() {
?>

	<input name="_bbp_reply_archive_slug" id="_bbp_reply_archive_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_reply_archive_slug', 'replies', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_reply_archive_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_reply_archive_slug', 'replies' );
}

/**
 * Favorites slug setting field
 *
 * @since 2.4.0 bbPress (r4932)
 */
function bbp_admin_setting_callback_user_favs_slug() {
?>

	<input name="_bbp_user_favs_slug" id="_bbp_user_favs_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_user_favs_slug', 'favorites', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_user_favs_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_user_favs_slug', 'favorites' );
}

/**
 * Subscriptions slug setting field
 *
 * @since 2.4.0 bbPress (r4932)
 */
function bbp_admin_setting_callback_user_subs_slug() {
?>

	<input name="_bbp_user_subs_slug" id="_bbp_user_subs_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_user_subs_slug', 'subscriptions', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_user_subs_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_user_subs_slug', 'subscriptions' );
}

/**
 * Engagements slug setting field
 *
 * @since 2.6.0 bbPress (r6320)
 */
function bbp_admin_setting_callback_user_engagements_slug() {
?>

	<input name="_bbp_user_engs_slug" id="_bbp_user_engs_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_user_engs_slug', 'engagements', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_user_engs_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_user_engs_slug', 'engagements' );
}

/** Single Slugs **************************************************************/

/**
 * Slugs settings section description for the settings page
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_single_slug_section() {
?>

	<p><?php printf( esc_html__( 'Custom slugs for single forums, topics, replies, tags, views, edit, and search.', 'bbpress' ), get_admin_url( null, 'options-permalink.php' ) ); ?></p>

<?php
}

/**
 * Forum slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_forum_slug() {
?>

	<input name="_bbp_forum_slug" id="_bbp_forum_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_forum_slug', 'forum', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_forum_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_forum_slug', 'forum' );
}

/**
 * Topic slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_topic_slug() {
?>

	<input name="_bbp_topic_slug" id="_bbp_topic_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_topic_slug', 'topic', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_topic_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_topic_slug', 'topic' );
}

/**
 * Reply slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_reply_slug() {
?>

	<input name="_bbp_reply_slug" id="_bbp_reply_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_reply_slug', 'reply', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_reply_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_reply_slug', 'reply' );
}

/**
 * Topic tag slug setting field
 *
 * @since 2.0.0 bbPress (r2786)
 */
function bbp_admin_setting_callback_topic_tag_slug() {
?>

	<input name="_bbp_topic_tag_slug" id="_bbp_topic_tag_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_topic_tag_slug', 'topic-tag', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_topic_tag_slug' ); ?> />

<?php

	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_topic_tag_slug', 'topic-tag' );
}

/**
 * View slug setting field
 *
 * @since 2.0.0 bbPress (r2789)
 */
function bbp_admin_setting_callback_view_slug() {
?>

	<input name="_bbp_view_slug" id="_bbp_view_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_view_slug', 'view', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_view_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_view_slug', 'view' );
}

/**
 * Search slug setting field
 *
 * @since 2.3.0 bbPress (r4579)
 */
function bbp_admin_setting_callback_search_slug() {
?>

	<input name="_bbp_search_slug" id="_bbp_search_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_search_slug', 'search', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_search_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_search_slug', 'search' );
}

/**
 * Edit slug setting field
 *
 * @since 2.6.2 bbPress (r6965)
 */
function bbp_admin_setting_callback_edit_slug() {
?>

	<input name="_bbp_edit_slug" id="_bbp_edit_slug" type="text" class="regular-text code" value="<?php bbp_form_option( '_bbp_edit_slug', 'edit', true ); ?>"<?php bbp_maybe_admin_setting_disabled( '_bbp_edit_slug' ); ?> />

<?php
	// Slug Check
	bbp_form_slug_conflict_check( '_bbp_edit_slug', 'edit' );
}

/** BuddyPress ****************************************************************/

/**
 * Extension settings section description for the settings page
 *
 * @since 2.1.0 bbPress (r3575)
 */
function bbp_admin_setting_callback_buddypress_section() {
?>

	<p><?php esc_html_e( 'Forum settings for BuddyPress', 'bbpress' ); ?></p>

<?php
}

/**
 * Allow BuddyPress group forums setting field
 *
 * @since 2.1.0 bbPress (r3575)
 */
function bbp_admin_setting_callback_group_forums() {
?>

	<input name="_bbp_enable_group_forums" id="_bbp_enable_group_forums" type="checkbox" value="1" <?php checked( bbp_is_group_forums_active( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_enable_group_forums' ); ?> />
	<label for="_bbp_enable_group_forums"><?php esc_html_e( 'Allow BuddyPress Groups to have their own forums', 'bbpress' ); ?></label>

<?php
}

/**
 * Replies per page setting field
 *
 * @since 2.1.0 bbPress (r3575)
 */
function bbp_admin_setting_callback_group_forums_root_id() {

	// Group root ID
	$group_root = bbp_get_group_forums_root_id();
	if ( ! bbp_get_forum( $group_root ) ) {
		delete_option( '_bbp_group_forums_root_id' );
		$group_root = 0;
	}

	// Output the dropdown for all forums
	$select = bbp_get_dropdown( array(
		'selected'           => $group_root,
		'show_none'          => esc_html__( '&mdash; No parent &mdash;', 'bbpress' ),
		'orderby'            => 'title',
		'order'              => 'ASC',
		'select_id'          => '_bbp_group_forums_root_id',
		'disable_categories' => false,
		'disabled'           => '_bbp_group_forums_root_id'
	) );

	// Check cap one time
	$can_add_new = current_user_can( 'publish_forums' );
	$button = '';

	// Text variations based on configuration
	if ( empty( $group_root ) && ( true === $can_add_new ) ) {

		// New URL
		$new_url = wp_nonce_url( add_query_arg( array(
			'page'   => 'bbpress',
			'create' => 'bbp-group-forum-root'
		), admin_url( 'options-general.php' ) ), '_bbp_group_forums_root_id' );

		// Button & text
		$button = '<a href="' . esc_url( $new_url ) . '">' . esc_html__( 'create a new one', 'bbpress' ) . '</a>';
		$text   = esc_html__( 'Use %s to contain your group forums, or %s', 'bbpress' ); //phpcs:ignore
	} else {
		$text = esc_html__( 'Use %s to contain your group forums', 'bbpress' );
	}

	// Output
	printf( $text, $select, $button ); ?>
	<p class="description"><?php esc_html_e( 'Changing this will not move existing forums.', 'bbpress' ); ?></p>

<?php
}

/** Akismet *******************************************************************/

/**
 * Extension settings section description for the settings page
 *
 * @since 2.1.0 bbPress (r3575)
 */
function bbp_admin_setting_callback_akismet_section() {
?>

	<p><?php esc_html_e( 'Forum settings for Akismet', 'bbpress' ); ?></p>

<?php
}


/**
 * Allow Akismet setting field
 *
 * @since 2.1.0 bbPress (r3575)
 */
function bbp_admin_setting_callback_akismet() {
?>

	<input name="_bbp_enable_akismet" id="_bbp_enable_akismet" type="checkbox" value="1" <?php checked( bbp_is_akismet_active( true ) ); bbp_maybe_admin_setting_disabled( '_bbp_enable_akismet' ); ?> />
	<label for="_bbp_enable_akismet"><?php esc_html_e( 'Allow Akismet to actively prevent forum spam.', 'bbpress' ); ?></label>

<?php
}

/** Settings Page *************************************************************/

/**
 * The main settings page
 *
 * @since 2.0.0 bbPress (r2643)
 */
function bbp_admin_settings() {
?>

	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Forums Settings', 'bbpress' ); ?></h1>
		<hr class="wp-header-end">

		<form action="options.php" method="post">

			<?php settings_fields( 'bbpress' ); ?>

			<?php do_settings_sections( 'bbpress' ); ?>

			<p class="submit">
				<input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'bbpress' ); ?>" />
			</p>
		</form>
	</div>

<?php
}

/** Converter Section *********************************************************/

/**
 * Main settings section description for the settings page
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_main_section() {
?>

	<p><?php _e( 'Information about the database for your previous forums so they can be converted.', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Platform setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_platform() {

	// Converters
	$current    = get_option( '_bbp_converter_platform' );
	$converters = bbp_get_converters();
	$options    = '';

	// Put options together
	foreach ( $converters as $name => $file ) {
		$options .= '<option value="' . esc_attr( $name ) . '"' . selected( $name, $current, false ) . '>' . esc_html( $name ) . '</option>';
	} ?>

	<select name="_bbp_converter_platform" id="_bbp_converter_platform"><?php echo $options; ?></select>
	<p class="description"><?php esc_html_e( 'The previous forum software', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Database Server setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_dbserver() {
?>

	<input name="_bbp_converter_db_server" id="_bbp_converter_db_server" type="text" class="code" value="<?php bbp_form_option( '_bbp_converter_db_server', 'localhost' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_db_server' ); ?> />
	<p class="description"><?php printf( esc_html__( 'Use default %s if same server, or IP or hostname', 'bbpress' ), '<code>localhost</code>' ); ?></p>

<?php
}

/**
 * Edit Database Server Port setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_dbport() {
?>

	<input name="_bbp_converter_db_port" id="_bbp_converter_db_port" type="text" class="code" value="<?php bbp_form_option( '_bbp_converter_db_port', '3306' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_db_port' ); ?> />
	<p class="description"><?php printf( esc_html__( 'Use default %s if unsure', 'bbpress' ), '<code>3306</code>' ); ?></p>

<?php
}

/**
 * Edit Database User setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_dbuser() {
?>

	<input name="_bbp_converter_db_user" id="_bbp_converter_db_user" type="text" class="code" value="<?php bbp_form_option( '_bbp_converter_db_user' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_db_user' ); ?> />
	<p class="description"><?php esc_html_e( 'User to access the database', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Database Pass setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_dbpass() {
?>

	<span class="bbp-converter-db-password-wrapper">
		<input name="_bbp_converter_db_pass" id="_bbp_converter_db_pass" class="bbp-converter-db-pass code" type="password" value="<?php bbp_form_option( '_bbp_converter_db_pass' ); ?>" autocomplete="off" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_db_pass' ); ?> />
		<button type="button" class="bbp-db-pass-toggle password">
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle', 'bbpress' ); ?></span>
			<span class="toggle-indicator" aria-hidden="true"></span>
		</button>
	</span>
	<p class="description"><?php esc_html_e( 'Password for the above database user', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Database Name setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_dbname() {
?>

	<input name="_bbp_converter_db_name" id="_bbp_converter_db_name" type="text" class="code" value="<?php bbp_form_option( '_bbp_converter_db_name' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_db_name' ); ?> />
	<p class="description"><?php esc_html_e( 'Name of the database with your old forum data', 'bbpress' ); ?></p>

<?php
}

/**
 * Main settings section description for the settings page
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_options_section() {
?>

	<p><?php esc_html_e( 'Some optional parameters to help tune the conversion process.', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Table Prefix setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_dbprefix() {
?>

	<input name="_bbp_converter_db_prefix" id="_bbp_converter_db_prefix" type="text" class="code" value="<?php bbp_form_option( '_bbp_converter_db_prefix' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_db_prefix' ); ?> />
	<p class="description"><?php printf( esc_html__( 'Use %s if converting from BuddyPress Legacy', 'bbpress' ), '<code>wp_bb_</code>' ); ?></p>

<?php
}

/**
 * Edit Rows Limit setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_rows() {
?>

	<input name="_bbp_converter_rows" id="_bbp_converter_rows" type="number" min="1" max="5000" value="<?php bbp_form_option( '_bbp_converter_rows', '100' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_rows' ); ?> />
	<label for="_bbp_converter_rows"><?php esc_html_e( 'entry maximum when querying for data to convert', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'Keep this low if you experience out-of-memory issues.', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Delay Time setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_delay_time() {
?>

	<input name="_bbp_converter_delay_time" id="_bbp_converter_delay_time" type="number" min="2" max="3600" value="<?php bbp_form_option( '_bbp_converter_delay_time', '2' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_delay_time' ); ?> />
	<label for="_bbp_converter_delay_time"><?php esc_html_e( 'second delay between each query of rows above', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'Keep this high to prevent too-many-connection issues.', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Halt setting field
 *
 * @since 2.6.0 bbPress (r6599)
 */
function bbp_converter_setting_callback_halt() {
?>

	<input name="_bbp_converter_halt" id="_bbp_converter_halt" type="checkbox" value="1" <?php checked( get_option( '_bbp_converter_halt', false ) ); ?> />
	<label for="_bbp_converter_halt"><?php esc_html_e( 'Halt the conversion if an error occurs', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'This is helpful if you want to debug problems.', 'bbpress' ); ?></p>

<?php
}


/**
 * Edit Restart setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_restart() {
?>

	<input name="_bbp_converter_restart" id="_bbp_converter_restart" type="checkbox" value="1" <?php checked( get_option( '_bbp_converter_restart', false ) ); ?> />
	<label for="_bbp_converter_restart"><?php esc_html_e( 'Restart the converter from the beginning', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'This forces all steps back to 0. Avoid duplicate data by purging or resetting first.', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Clean setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_clean() {
?>

	<input name="_bbp_converter_clean" id="_bbp_converter_clean" type="checkbox" value="1" <?php checked( get_option( '_bbp_converter_clean', false ) ); ?> />
	<label for="_bbp_converter_clean"><?php esc_html_e( 'Purge all meta-data from a previous import', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'Use this if an import failed, or you just want to remove the relationship data.', 'bbpress' ); ?></p>

<?php
}

/**
 * Edit Convert Users setting field
 *
 * @since 2.1.0 bbPress (r3813)
 */
function bbp_converter_setting_callback_convert_users() {
?>

	<input name="_bbp_converter_convert_users" id="_bbp_converter_convert_users" type="checkbox" value="1" <?php checked( get_option( '_bbp_converter_convert_users', false ) ); ?> <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_convert_users' ); ?> />
	<label for="_bbp_converter_convert_users"><?php esc_html_e( 'Import user accounts from previous forums', 'bbpress' ); ?></label>
	<p class="description"><?php esc_html_e( 'Passwords remain encrypted, and are converted as individual users log in.', 'bbpress' ); ?></p>

<?php
}

/** Converter Page ************************************************************/

/**
 * The main settings page
 *
 * @since 2.1.0 bbPress (r3186)
 */
function bbp_converter_settings_page() {

	// Status
	$step = (int) get_option( '_bbp_converter_step', 0 );
	$max  = (int) bbp_admin()->converter->max_steps;

	// Starting or continuing?
	$status_text = ! empty( $step )
		? sprintf( esc_html__( 'Up next: step %s', 'bbpress' ), $step )
		: esc_html__( 'Ready', 'bbpress' );

	// Starting or continuing?
	$start_text = ! empty( $step )
		? esc_html__( 'Resume', 'bbpress' )
		: esc_html__( 'Start',  'bbpress' );

	// Starting or continuing?
	$progress_text = ! empty( $step )
		? sprintf( esc_html__( 'Previously stopped at step %1$d of %2$d', 'bbpress' ), $step, $max )
		: esc_html__( 'Ready to go.', 'bbpress' ); ?>

	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Forum Tools', 'bbpress' ); ?></h1>
		<hr class="wp-header-end">
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( 'bbp-converter' ); ?></h2>

		<div class="bbp-converter-wrap">
			<div id="poststuff" class="bbp-converter-monitor-wrap">
				<div id="post-body" class="metabox-holder columns-1">
					<div id="postbox-container-1" class="postbox-container">
						<div id="normal-sortables" class="meta-box-sortables ui-sortable">
							<div id="bbp-converter-monitor" class="postbox">
								<button type="button" class="handlediv" aria-expanded="true">
									<span class="screen-reader-text"><?php esc_html_e( 'Toggle panel: Import Status', 'bbpress' ); ?></span>
									<span class="toggle-indicator" aria-hidden="true"></span>
								</button>
								<h2 class="hndle ui-sortable-handle">
									<span><?php esc_html_e( 'Import Monitor', 'bbpress' ); ?></span>
									<span id="bbp-converter-status"><?php echo esc_html( $status_text ); ?></span>
									<span id="bbp-converter-step-percentage" class="bbp-progress-bar"></span>
									<span id="bbp-converter-total-percentage" class="bbp-progress-bar"></span>
								</h2>
								<div class="inside">
									<div id="bbp-converter-message" class="bbp-converter-log">
										<p><?php echo esc_html( $progress_text ); ?></p>
									</div>
								</div>
								<div class="actions">
									<input type="button" name="submit" class="button-primary" id="bbp-converter-start" value="<?php echo esc_attr( $start_text ); ?>" />
									<input type="button" name="submit" class="button-primary" id="bbp-converter-stop" value="<?php esc_attr_e( 'Pause', 'bbpress' ); ?>" />
									<span class="spinner" id="bbp-converter-spinner"></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<form action="#" method="post" id="bbp-converter-settings" class="bbp-converter-settings-wrap"><?php

				// Fields
				settings_fields( 'converter' );

				// Sections
				do_settings_sections( 'converter' );

			?></form>
		</div>
	</div>
<?php
}

/** Helpers *******************************************************************/

/**
 * Contextual help for Forums settings page
 *
 * @since 2.0.0 bbPress (r3119)
 */
function bbp_admin_settings_help() {

	$current_screen = get_current_screen();

	// Bail if current screen could not be found
	if ( empty( $current_screen ) ) {
		return;
	}

	// Overview
	$current_screen->add_help_tab( array(
		'id'      => 'overview',
		'title'   => esc_html__( 'Overview', 'bbpress' ),
		'content' => '<p>' . esc_html__( 'This screen provides access to all of the Forums settings.',                          'bbpress' ) . '</p>' .
					 '<p>' . esc_html__( 'Please see the additional help tabs for more information on each individual section.', 'bbpress' ) . '</p>'
	) );

	// Main Settings
	$current_screen->add_help_tab( array(
		'id'      => 'main_settings',
		'title'   => esc_html__( 'Main Settings', 'bbpress' ),
		'content' => '<p>' . esc_html__( 'The "Main Settings" section includes a number of options:', 'bbpress' ) . '</p>' .
					 '<p>' .
						'<ul>' .
							'<li>' . esc_html__( 'You can choose to lock a post after a certain number of minutes. "Locking post editing" will prevent the author from editing some amount of time after saving a post.',              'bbpress' ) . '</li>' .
							'<li>' . esc_html__( '"Throttle time" is the amount of time required between posts from a single author. The higher the throttle time, the longer a user will need to wait between posting to the forum.', 'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'Favorites are a way for users to save and later return to topics they favor. This is enabled by default.',                                                                           'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'Subscriptions allow users to subscribe for notifications to topics that interest them. This is enabled by default.',                                                                 'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'Topic-Tags allow users to filter topics between forums. This is enabled by default.',                                                                                                'bbpress' ) . '</li>' .
							'<li>' . esc_html__( '"Anonymous Posting" allows guest users who do not have accounts on your site to both create topics as well as replies.',                                                             'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'The Fancy Editor brings the luxury of the Visual editor and HTML editor from the traditional WordPress dashboard into your theme.',                                                  'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'Auto-embed will embed the media content from a URL directly into the replies. For example: links to Flickr and YouTube.',                                                            'bbpress' ) . '</li>' .
						'</ul>' .
					'</p>' .
					'<p>' . esc_html__( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.', 'bbpress' ) . '</p>'
	) );

	// Theme Package
	$current_screen->add_help_tab( array(
		'id'      => 'theme_packages',
		'title'   => esc_html__( 'Theme Packages', 'bbpress' ),
		'content' => '<p>' . esc_html__( 'The "Theme Packages" section allows you to choose which theme package should be used.', 'bbpress' ) . '</p>' .
					 '<p>' .
						'<ul>' .
							'<li>' . esc_html__( 'The "bbPress Default" package is installed by default.',      'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'Some themes may choose to ignore this setting entirely.',     'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'Packages can be stacked to allow for intelligent fallbacks.', 'bbpress' ) . '</li>' .
						'</ul>' .
					'</p>'
	) );

	// Per Page
	$current_screen->add_help_tab( array(
		'id'      => 'per_page',
		'title'   => esc_html__( 'Per Page', 'bbpress' ),
		'content' => '<p>' . esc_html__( 'The "Per Page" section allows you to control the number of topics and replies appear on each page.',                                                    'bbpress' ) . '</p>' .
						'<ul>' .
							'<li>' . esc_html__( 'This is comparable to the WordPress "Reading Settings" page, where you can set the number of posts that should show on blog pages and in feeds.', 'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'These are broken up into two separate groups: one for what appears in your theme, another for RSS feeds.',                                        'bbpress' ) . '</li>' .
						'</ul>' .
					 '<p>'
	) );

	// Slugs
	$current_screen->add_help_tab( array(
		'id'      => 'slugs',
		'title'   => esc_html__( 'Slugs', 'bbpress' ),
		'content' => '<p>' . esc_html__( 'The "Slugs" section allows you to control the permalink structure for your forums.',                                                                                                            'bbpress' ) . '</p>' .
						'<ul>' .
							'<li>' . esc_html__( '"Archive Slugs" are used as the "root" for your forums and topics. If you combine these values with existing page slugs, bbPress will attempt to output the most correct title and content.', 'bbpress' ) . '</li>' .
							'<li>' . esc_html__( '"Single Slugs" are used as a prefix when viewing an individual forum, topic, reply, user, or view.',                                                                                          'bbpress' ) . '</li>' .
							'<li>' . esc_html__( 'In the event of a slug collision with WordPress or BuddyPress, a warning will appear next to the problem slug(s).', 'bbpress' ) . '</li>' .
						'</ul>' .
					 '<p>'
	) );

	// Help Sidebar
	$current_screen->set_help_sidebar(
		'<p><strong>' . esc_html__( 'For more information:', 'bbpress' ) . '</strong></p>' .
		'<p>' . __( '<a href="https://codex.bbpress.org" target="_blank">bbPress Documentation</a>',    'bbpress' ) . '</p>' .
		'<p>' . __( '<a href="https://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>', 'bbpress' ) . '</p>'
	);
}

/**
 * Disable a settings field if it is forcibly set in the global options array.
 *
 * @since 2.2.0 bbPress (r4347)
 *
 * @param string $option_key
 */
function bbp_maybe_admin_setting_disabled( $option_key = '' ) {
	disabled( isset( bbpress()->options[ $option_key ] ) );
}

/**
 * Output settings API option
 *
 * @since 2.0.0 bbPress (r3203)
 *
 * @param string $option
 * @param string $default
 * @param bool $slug
 */
function bbp_form_option( $option, $default = '', $slug = false ) {
	echo bbp_get_form_option( $option, $default, $slug );
}
	/**
	 * Return settings API option
	 *
	 * @since 2.0.0 bbPress (r3203)
	 *
	 * @param string $option
	 * @param string $default
	 * @param bool   $is_slug
	 *
	 * @return mixed
	 */
	function bbp_get_form_option( $option, $default = '', $is_slug = false ) {

		// Get the option and sanitize it
		$value = get_option( $option, $default );

		// Slug?
		if ( true === $is_slug ) {
			$value = esc_attr( apply_filters( 'editable_slug', $value ) );

		// Not a slug
		} else {
			$value = esc_attr( $value );
		}

		// Fallback to default, unless numeric (allow zero)
		if ( empty( $value ) && ! is_numeric( $value ) ) {
			$value = $default;
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_option', $value, $option, $default, $is_slug );
	}

/**
 * Used to check if a bbPress slug conflicts with an existing known slug.
 *
 * @since 2.0.0 bbPress (r3306)
 *
 * @param string $slug
 * @param string $default
 */
function bbp_form_slug_conflict_check( $slug, $default ) {

	// Only set the slugs once ver page load
	static $the_core_slugs = array();

	// Get the form value
	$this_slug = bbp_get_form_option( $slug, $default, true );

	if ( empty( $the_core_slugs ) ) {

		// Slugs to check
		$core_slugs = apply_filters( 'bbp_slug_conflict_check', array(

			/** WordPress Core ****************************************************/

			// Core Post Types
			'post_base'       => array( 'name' => esc_html__( 'Posts',         'bbpress' ), 'default' => 'post',          'context' => 'WordPress' ),
			'page_base'       => array( 'name' => esc_html__( 'Pages',         'bbpress' ), 'default' => 'page',          'context' => 'WordPress' ),
			'revision_base'   => array( 'name' => esc_html__( 'Revisions',     'bbpress' ), 'default' => 'revision',      'context' => 'WordPress' ),
			'attachment_base' => array( 'name' => esc_html__( 'Attachments',   'bbpress' ), 'default' => 'attachment',    'context' => 'WordPress' ),
			'nav_menu_base'   => array( 'name' => esc_html__( 'Menus',         'bbpress' ), 'default' => 'nav_menu_item', 'context' => 'WordPress' ),

			// Post Tags
			'tag_base'        => array( 'name' => esc_html__( 'Tag base',      'bbpress' ), 'default' => 'tag',           'context' => 'WordPress' ),

			// Post Categories
			'category_base'   => array( 'name' => esc_html__( 'Category base', 'bbpress' ), 'default' => 'category',      'context' => 'WordPress' ),

			/** bbPress Core ******************************************************/

			// Forum archive slug
			'_bbp_root_slug'          => array( 'name' => esc_html__( 'Forums base', 'bbpress' ), 'default' => 'forums', 'context' => 'bbPress' ),

			// Topic archive slug
			'_bbp_topic_archive_slug' => array( 'name' => esc_html__( 'Topics base', 'bbpress' ), 'default' => 'topics', 'context' => 'bbPress' ),

			// Forum slug
			'_bbp_forum_slug'         => array( 'name' => esc_html__( 'Forum slug',  'bbpress' ), 'default' => 'forum',  'context' => 'bbPress' ),

			// Topic slug
			'_bbp_topic_slug'         => array( 'name' => esc_html__( 'Topic slug',  'bbpress' ), 'default' => 'topic',  'context' => 'bbPress' ),

			// Reply slug
			'_bbp_reply_slug'         => array( 'name' => esc_html__( 'Reply slug',  'bbpress' ), 'default' => 'reply',  'context' => 'bbPress' ),

			// Edit slug
			'_bbp_edit_slug'          => array( 'name' => esc_html__( 'Edit slug',   'bbpress' ), 'default' => 'edit',   'context' => 'bbPress' ),

			// User profile slug
			'_bbp_user_slug'          => array( 'name' => esc_html__( 'User base',   'bbpress' ), 'default' => 'users',  'context' => 'bbPress' ),

			// View slug
			'_bbp_view_slug'          => array( 'name' => esc_html__( 'View base',   'bbpress' ), 'default' => 'view',   'context' => 'bbPress' ),

			// Topic tag slug
			'_bbp_topic_tag_slug'     => array( 'name' => esc_html__( 'Topic tag slug', 'bbpress' ), 'default' => 'topic-tag', 'context' => 'bbPress' ),
		) );

		/** BuddyPress Core *******************************************************/

		if ( defined( 'BP_VERSION' ) ) {
			$bp = buddypress();

			// Loop through root slugs and check for conflict
			if ( ! empty( $bp->pages ) ) {
				foreach ( $bp->pages as $page => $page_data ) {
					$page_base    = $page . '_base';
					$page_title   = sprintf( esc_html__( '%s page', 'bbpress' ), $page_data->title );
					$core_slugs[ $page_base ] = array(
						'name'    => $page_title,
						'default' => $page_data->slug,
						'context' => 'BuddyPress'
					);
				}
			}
		}

		// Set the static
		$the_core_slugs = apply_filters( 'bbp_slug_conflict', $core_slugs );
	}

	// Loop through slugs to check
	foreach ( $the_core_slugs as $key => $value ) {

		// Get the slug
		$slug_check = bbp_get_form_option( $key, $value['default'], true );

		// Compare
		if ( ( $slug !== $key ) && ( $slug_check === $this_slug ) ) : ?>

			<span class="attention"><?php printf( esc_html__( 'Possible %1$s conflict: %2$s', 'bbpress' ), $value['context'], '<strong>' . $value['name'] . '</strong>' ); ?></span>

		<?php endif;
	}
}
