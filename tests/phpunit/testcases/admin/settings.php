<?php

/**
 * Tests for admin settings helpers.
 *
 * @group admin
 * @group settings
 */
class BBP_Tests_Admin_Settings extends BBP_UnitTestCase {

	private $saved_options = array();
	private $bbp_options;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}

		bbp_admin();

		$this->bbp_options = bbpress()->options;
		bbpress()->options = array();
	}

	public function tearDown(): void {
		foreach ( $this->saved_options as $option => $saved ) {
			if ( $saved['exists'] ) {
				update_option( $option, $saved['value'] );
			} else {
				delete_option( $option );
			}
		}

		bbpress()->options = $this->bbp_options;

		parent::tearDown();
	}

	private function set_option( $option, $value ) {
		if ( ! isset( $this->saved_options[ $option ] ) ) {
			$missing = new stdClass();
			$current = get_option( $option, $missing );

			$this->saved_options[ $option ] = array(
				'exists' => ( $missing !== $current ),
				'value'  => $current,
			);
		}

		delete_option( $option );
		add_option( $option, $value );
	}

	/**
	 * @covers ::bbp_admin_get_settings_sections
	 */
	public function test_settings_sections_have_pages_callbacks_and_filters() {
		$filter = function ( $sections ) {
			$sections['bbp_test_section'] = array(
				'title'    => 'Test',
				'callback' => '__return_null',
				'page'     => 'test',
			);
			return $sections;
		};

		add_filter( 'bbp_admin_get_settings_sections', $filter );

		try {
			$sections = bbp_admin_get_settings_sections();
			$this->assertArrayHasKey( 'bbp_settings_status', $sections );
			$this->assertArrayHasKey( 'bbp_settings_users', $sections );
			$this->assertArrayHasKey( 'bbp_converter_connection', $sections );
			$this->assertSame( 'discussion', $sections['bbp_settings_status']['page'] );
			$this->assertSame( 'bbp_admin_setting_callback_status_section', $sections['bbp_settings_status']['callback'] );
			$this->assertSame( 'test', $sections['bbp_test_section']['page'] );
		} finally {
			remove_filter( 'bbp_admin_get_settings_sections', $filter );
		}
	}

	/**
	 * @covers ::bbp_admin_get_settings_fields
	 */
	public function test_settings_fields_define_callbacks_sanitizers_and_labels() {
		$fields = bbp_admin_get_settings_fields();

		$this->assertSame( 'bbp_admin_setting_callback_forums_status', $fields['bbp_settings_status']['_bbp_forums_status']['callback'] );
		$this->assertSame( 'bbp_admin_sanitize_forums_status', $fields['bbp_settings_status']['_bbp_forums_status']['sanitize_callback'] );
		$this->assertSame( array( 'label_for' => '_bbp_forums_status' ), $fields['bbp_settings_status']['_bbp_forums_status']['args'] );
		$this->assertSame( 'bbp_admin_setting_callback_moderation_links', $fields['bbp_settings_users']['_bbp_moderation_links']['callback'] );
		$this->assertSame( array(), $fields['bbp_settings_users']['_bbp_moderation_links']['args'] );
		$this->assertSame( 'intval', $fields['bbp_settings_users']['_bbp_allow_moderation_links']['sanitize_callback'] );
		$this->assertSame( 'bbp_admin_setting_callback_user_block_actions', $fields['bbp_settings_users']['_bbp_user_block_actions']['callback'] );
		$this->assertSame( 'bbp_admin_sanitize_user_block_actions', $fields['bbp_settings_users']['_bbp_user_block_actions']['sanitize_callback'] );
		$this->assertGreaterThan(
			array_search( '_bbp_allow_anonymous', array_keys( $fields['bbp_settings_users'] ), true ),
			array_search( '_bbp_user_block_actions', array_keys( $fields['bbp_settings_users'] ), true )
		);
		$this->assertSame( 'bbp_admin_setting_callback_moderation_notification_recipient_types', $fields['bbp_settings_moderation_notifications']['_bbp_moderation_notification_recipient_types']['callback'] );
		$this->assertSame( 'intval', $fields['bbp_converter_options']['_bbp_converter_clean']['sanitize_callback'] );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_admin_setting_callback_user_block_actions
	 */
	public function test_user_block_actions_callback_shows_actions_available_to_installation() {
		$old_user = get_current_user_id();
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		wp_set_current_user( $admin_id );

		try {
			$output = $this->capture_callback( 'bbp_admin_setting_callback_user_block_actions' );
		} finally {
			wp_set_current_user( $old_user );
			if ( is_multisite() ) {
				revoke_super_admin( $admin_id );
			}
		}

		$this->assertStringContainsString( 'Sign the user out everywhere', $output );

		if ( is_multisite() ) {
			$this->assertStringContainsString( 'Block the user on every other site', $output );
			$this->assertStringContainsString( 'Globally mark the user as a spammer', $output );
		} else {
			$this->assertStringNotContainsString( 'Block the user on every other site', $output );
			$this->assertStringNotContainsString( 'Globally mark the user as a spammer', $output );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_admin_setting_callback_user_block_actions
	 */
	public function test_user_block_actions_callback_handles_no_visible_actions() {
		$old_user = get_current_user_id();
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$filter   = function () {
			return array();
		};

		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'manage_options' ) );
		add_filter( 'bbp_get_user_block_actions', $filter );

		try {
			$output = $this->capture_callback( 'bbp_admin_setting_callback_user_block_actions' );
			$this->assertStringContainsString( 'No additional blocking actions are available.', $output );
			$this->assertStringNotContainsString( 'name="_bbp_user_block_actions[]"', $output );
		} finally {
			remove_filter( 'bbp_get_user_block_actions', $filter );
			wp_set_current_user( $old_user );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_admin_setting_callback_user_block_actions
	 */
	public function test_user_block_actions_callback_handles_all_actions_unavailable_by_capability() {
		$old_user = get_current_user_id();
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$filter   = function () {
			return array(
				'restricted' => array(
					'name'       => 'Restricted',
					'callback'   => '__return_true',
					'capability' => 'do_not_allow',
				),
			);
		};

		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'manage_options' ) );
		add_filter( 'bbp_get_user_block_actions', $filter );

		try {
			$output = $this->capture_callback( 'bbp_admin_setting_callback_user_block_actions' );
			$this->assertStringContainsString( 'No additional blocking actions are available.', $output );
			$this->assertStringNotContainsString( 'name="_bbp_user_block_actions[]"', $output );
			$this->assertStringNotContainsString( 'Restricted', $output );
		} finally {
			remove_filter( 'bbp_get_user_block_actions', $filter );
			wp_set_current_user( $old_user );
		}
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_admin_setting_callback_moderation_notification_recipient_types
	 */
	public function test_moderation_notification_recipient_types_disables_every_input_when_overridden() {
		$output = $this->capture_callback( 'bbp_admin_setting_callback_moderation_notification_recipient_types' );
		$this->assertSame( 0, substr_count( $output, "disabled='disabled'" ) );

		bbpress()->options['_bbp_moderation_notification_recipient_types'] = array( 'keymasters' );
		$output = $this->capture_callback( 'bbp_admin_setting_callback_moderation_notification_recipient_types' );
		$this->assertSame( 4, substr_count( $output, "disabled='disabled'" ) );
	}

	/**
	 * @covers ::bbp_admin_get_settings_fields_for_section
	 */
	public function test_settings_fields_for_section_handles_empty_known_and_unknown_sections() {
		$observed = null;
		$filter   = function ( $fields, $section_id ) use ( &$observed ) {
			$observed = array( $fields, $section_id );
			return $fields;
		};

		$this->assertFalse( bbp_admin_get_settings_fields_for_section() );
		add_filter( 'bbp_admin_get_settings_fields_for_section', $filter, 10, 2 );

		try {
			$fields = bbp_admin_get_settings_fields_for_section( 'bbp_settings_status' );
			$this->assertArrayHasKey( '_bbp_forums_status', $fields );
			$this->assertSame( 'bbp_settings_status', $observed[1] );

			$this->assertSame( array(), bbp_admin_get_settings_fields_for_section( 'bbp_missing_section' ) );
			$this->assertSame( array( array(), 'bbp_missing_section' ), $observed );
		} finally {
			remove_filter( 'bbp_admin_get_settings_fields_for_section', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_admin_settings
	 */
	public function test_admin_settings_outputs_the_settings_form() {
		$had_request_uri = array_key_exists( 'REQUEST_URI', $_SERVER );
		$old_request_uri = $had_request_uri ? $_SERVER['REQUEST_URI'] : null;
		$had_sections    = isset( $GLOBALS['wp_settings_sections'] );
		$old_sections    = $had_sections ? $GLOBALS['wp_settings_sections'] : null;
		$had_fields      = isset( $GLOBALS['wp_settings_fields'] );
		$old_fields      = $had_fields ? $GLOBALS['wp_settings_fields'] : null;
		$section         = function () {
			echo '<p id="bbp-test-section">Section output</p>';
		};
		$field           = function () {
			echo '<input id="bbp-test-field" />';
		};

		$_SERVER['REQUEST_URI'] = '/wp-admin/options-general.php?page=bbpress';
		add_settings_section( 'bbp_test_section', 'Test Section', $section, 'bbpress' );
		add_settings_field( 'bbp_test_field', 'Test Field', $field, 'bbpress', 'bbp_test_section' );

		try {
			ob_start();
			bbp_admin_settings();
			$output = ob_get_clean();
		} finally {
			if ( $had_request_uri ) {
				$_SERVER['REQUEST_URI'] = $old_request_uri;
			} else {
				unset( $_SERVER['REQUEST_URI'] );
			}

			if ( $had_sections ) {
				$GLOBALS['wp_settings_sections'] = $old_sections;
			} else {
				unset( $GLOBALS['wp_settings_sections'] );
			}

			if ( $had_fields ) {
				$GLOBALS['wp_settings_fields'] = $old_fields;
			} else {
				unset( $GLOBALS['wp_settings_fields'] );
			}
		}

		$this->assertStringContainsString( '<h1 class="wp-heading-inline">Forums Settings</h1>', $output );
		$this->assertStringContainsString( '<form action="options.php" method="post">', $output );
		$this->assertStringContainsString( "name='option_page' value='bbpress'", $output );
		$this->assertStringContainsString( '<p id="bbp-test-section">Section output</p>', $output );
		$this->assertStringContainsString( '<input id="bbp-test-field" />', $output );
		$this->assertStringContainsString( 'value="Save Changes"', $output );
		$this->assertSame( 1, preg_match( '/name="_wpnonce" value="([^"]+)"/', $output, $matches ) );
		$this->assertNotFalse( wp_verify_nonce( $matches[1], 'bbpress-options' ) );
	}

	/**
	 * @covers ::bbp_admin_settings_help
	 */
	public function test_admin_settings_help_adds_tabs_and_sidebar() {
		$old_screen = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;

		try {
			$GLOBALS['current_screen'] = null;
			$this->assertNull( bbp_admin_settings_help() );

			$GLOBALS['current_screen'] = WP_Screen::get( 'settings_page_bbpress' );
			bbp_admin_settings_help();
			$tabs = get_current_screen()->get_help_tabs();

			$this->assertSame( array( 'overview', 'forum_status', 'main_settings', 'theme_packages', 'per_page', 'slugs' ), array_keys( $tabs ) );
			$this->assertStringContainsString( 'selected settings sections', $tabs['overview']['content'] );
			$this->assertStringContainsString( 'Frozen stops new topics and replies', $tabs['forum_status']['content'] );
			$this->assertSame( 'Forum User Settings', $tabs['main_settings']['title'] );
			$this->assertStringContainsString( '<ul><li>Automatic forum roles', $tabs['main_settings']['content'] );
			$this->assertStringContainsString( '<li>Blocking removes forum access.', $tabs['main_settings']['content'] );
			$this->assertStringContainsString( '<li>Plugins may add blocking actions', $tabs['main_settings']['content'] );
			$this->assertStringContainsString( '</ul><p>Click Save Changes', $tabs['main_settings']['content'] );
			$this->assertStringNotContainsString( 'Favorites are a way', $tabs['main_settings']['content'] );

			if ( is_multisite() ) {
				$this->assertSame( 8, substr_count( $tabs['main_settings']['content'], '<li>' ) );
				$this->assertStringContainsString( 'When a network administrator unblocks a user', $tabs['main_settings']['content'] );
				$this->assertStringContainsString( '<li>Previous forum roles are not restored;', $tabs['main_settings']['content'] );
				$this->assertStringContainsString( 'A pre-existing spam status is left alone.', $tabs['main_settings']['content'] );
			} else {
				$this->assertSame( 4, substr_count( $tabs['main_settings']['content'], '<li>' ) );
				$this->assertStringNotContainsString( 'network administrator', $tabs['main_settings']['content'] );
			}

			$this->assertStringContainsString( 'bbPress Documentation', get_current_screen()->get_help_sidebar() );
		} finally {
			$GLOBALS['current_screen'] = $old_screen;
		}
	}

	/**
	 * @covers ::bbp_maybe_admin_setting_disabled
	 */
	public function test_admin_setting_is_disabled_only_when_globally_overridden() {
		ob_start();
		bbp_maybe_admin_setting_disabled( '_bbp_test_option' );
		$this->assertSame( '', ob_get_clean() );

		bbpress()->options['_bbp_test_option'] = true;
		ob_start();
		bbp_maybe_admin_setting_disabled( '_bbp_test_option' );
		$this->assertSame( " disabled='disabled'", ob_get_clean() );
	}

	/**
	 * @covers ::bbp_form_option
	 * @covers ::bbp_get_form_option
	 */
	public function test_form_option_escapes_filters_falls_back_and_preserves_zero() {
		$this->set_option( '_bbp_test_option', 'value"quoted' );
		$this->set_option( '_bbp_test_zero', 0 );
		$this->set_option( '_bbp_test_empty', '' );

		$this->assertSame( 'value&quot;quoted', bbp_get_form_option( '_bbp_test_option' ) );
		$this->assertSame( '0', bbp_get_form_option( '_bbp_test_zero', 'fallback' ) );
		$this->assertSame( 'fallback', bbp_get_form_option( '_bbp_test_empty', 'fallback' ) );
		$this->assertSame( 'missing-default', bbp_get_form_option( '_bbp_missing_form_option', 'missing-default' ) );

		$slug_filter = function ( $value ) {
			return '<filtered-"slug">';
		};
		add_filter( 'editable_slug', $slug_filter );

		try {
			$this->assertSame( '&lt;filtered-&quot;slug&quot;&gt;', bbp_get_form_option( '_bbp_test_option', '', true ) );
		} finally {
			remove_filter( 'editable_slug', $slug_filter );
		}

		$observed = null;
		$filter   = function ( $value, $option, $default, $is_slug ) use ( &$observed ) {
			$observed = array( $value, $option, $default, $is_slug );
			return 'filtered-return';
		};
		add_filter( 'bbp_get_form_option', $filter, 10, 4 );

		try {
			$this->assertSame( 'filtered-return', bbp_get_form_option( '_bbp_test_option', 'default', false ) );
			$this->assertSame( array( 'value&quot;quoted', '_bbp_test_option', 'default', false ), $observed );
		} finally {
			remove_filter( 'bbp_get_form_option', $filter, 10 );
		}

		ob_start();
		bbp_form_option( '_bbp_test_option' );
		$this->assertSame( 'value&quot;quoted', ob_get_clean() );
	}

	/**
	 * @covers ::bbp_form_slug_conflict_check
	 */
	public function test_slug_conflict_check_reports_other_matching_slugs() {
		$this->set_option( '_bbp_forum_slug', 'shared-slug' );
		$this->set_option( '_bbp_topic_slug', 'shared-slug' );

		ob_start();
		bbp_form_slug_conflict_check( '_bbp_forum_slug', 'forum' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Possible bbPress conflict', $output );
		$this->assertStringContainsString( '<strong>Topic slug</strong>', $output );
		$this->assertStringNotContainsString( '<strong>Forum slug</strong>', $output );
	}

	private function capture_callback( $callback ) {
		$level = ob_get_level();
		ob_start();

		try {
			call_user_func( $callback );
			return ob_get_clean();
		} catch ( Throwable $throwable ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			throw $throwable;
		}
	}

	/**
	 * @covers ::bbp_admin_setting_callback_status_section
	 * @covers ::bbp_admin_setting_callback_user_section
	 * @covers ::bbp_admin_setting_callback_features_section
	 * @covers ::bbp_admin_setting_callback_subtheme_section
	 * @covers ::bbp_admin_setting_callback_per_page_section
	 * @covers ::bbp_admin_setting_callback_per_rss_page_section
	 * @covers ::bbp_admin_setting_callback_root_slug_section
	 * @covers ::bbp_admin_setting_callback_user_slug_section
	 * @covers ::bbp_admin_setting_callback_single_slug_section
	 * @covers ::bbp_admin_setting_callback_buddypress_section
	 * @covers ::bbp_admin_setting_callback_akismet_section
	 *
	 * @dataProvider settings_section_callback_provider
	 */
	public function test_settings_section_callbacks_output_descriptions( $callback, $description ) {
		$this->assertStringContainsString( $description, $this->capture_callback( $callback ) );
	}

	public static function settings_section_callback_provider() {
		return array(
			'forum status'  => array( 'bbp_admin_setting_callback_status_section', 'Control posting and editing across all forums.' ),
			'users'         => array( 'bbp_admin_setting_callback_user_section', 'Setting time limits and other user posting capabilities' ),
			'features'      => array( 'bbp_admin_setting_callback_features_section', 'Forum features that can be toggled on and off' ),
			'theme package' => array( 'bbp_admin_setting_callback_subtheme_section', 'How your forum content is displayed' ),
			'per page'      => array( 'bbp_admin_setting_callback_per_page_section', 'How many topics and replies to show per page' ),
			'per RSS page'  => array( 'bbp_admin_setting_callback_per_rss_page_section', 'How many topics and replies to show per RSS page' ),
			'root slug'     => array( 'bbp_admin_setting_callback_root_slug_section', 'Customize your Forums root.' ),
			'user slugs'    => array( 'bbp_admin_setting_callback_user_slug_section', 'Customize your user profile slugs.' ),
			'single slugs'  => array( 'bbp_admin_setting_callback_single_slug_section', 'Custom slugs for single forums' ),
			'BuddyPress'    => array( 'bbp_admin_setting_callback_buddypress_section', 'Forum settings for BuddyPress' ),
			'Akismet'       => array( 'bbp_admin_setting_callback_akismet_section', 'Forum settings for Akismet' ),
		);
	}

	/**
	 * @covers ::bbp_admin_setting_callback_anonymous
	 * @covers ::bbp_admin_setting_callback_favorites
	 * @covers ::bbp_admin_setting_callback_subscriptions
	 * @covers ::bbp_admin_setting_callback_engagements
	 * @covers ::bbp_admin_setting_callback_topic_tags
	 * @covers ::bbp_admin_setting_callback_forum_mods
	 * @covers ::bbp_admin_setting_callback_super_mods
	 * @covers ::bbp_admin_setting_callback_search
	 * @covers ::bbp_admin_setting_callback_revisions
	 * @covers ::bbp_admin_setting_callback_use_wp_editor
	 * @covers ::bbp_admin_setting_callback_use_autoembed
	 * @covers ::bbp_admin_setting_callback_include_root
	 * @covers ::bbp_admin_setting_callback_group_forums
	 * @covers ::bbp_admin_setting_callback_akismet
	 *
	 * @dataProvider checkbox_setting_callback_provider
	 */
	public function test_checkbox_setting_callbacks_output_inputs_and_labels( $callback, $option, $label ) {
		$this->set_option( $option, 1 );
		$output = $this->capture_callback( $callback );

		$this->assertStringContainsString( 'name="' . $option . '"', $output );
		$this->assertStringContainsString( 'id="' . $option . '"', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( " checked='checked'", $output );
		$this->assertStringNotContainsString( " disabled='disabled'", $output );
		$this->assertStringContainsString( $label, $output );

		$this->set_option( $option, 0 );
		$this->assertStringNotContainsString( " checked='checked'", $this->capture_callback( $callback ) );

		bbpress()->options[ $option ] = 1;
		$this->assertStringContainsString( " disabled='disabled'", $this->capture_callback( $callback ) );
	}

	public static function checkbox_setting_callback_provider() {
		return array(
			'anonymous'     => array( 'bbp_admin_setting_callback_anonymous', '_bbp_allow_anonymous', 'Allow guest users' ),
			'favorites'     => array( 'bbp_admin_setting_callback_favorites', '_bbp_enable_favorites', 'mark topics as favorites' ),
			'subscriptions' => array( 'bbp_admin_setting_callback_subscriptions', '_bbp_enable_subscriptions', 'subscribe to forums and topics' ),
			'engagements'   => array( 'bbp_admin_setting_callback_engagements', '_bbp_enable_engagements', 'tracking of topics' ),
			'topic tags'    => array( 'bbp_admin_setting_callback_topic_tags', '_bbp_allow_topic_tags', 'Allow topics to have tags' ),
			'forum mods'    => array( 'bbp_admin_setting_callback_forum_mods', '_bbp_allow_forum_mods', 'dedicated moderators' ),
			'super mods'    => array( 'bbp_admin_setting_callback_super_mods', '_bbp_allow_super_mods', 'edit users' ),
			'search'        => array( 'bbp_admin_setting_callback_search', '_bbp_allow_search', 'forum wide search' ),
			'revisions'     => array( 'bbp_admin_setting_callback_revisions', '_bbp_allow_revisions', 'revision logging' ),
			'editor'        => array( 'bbp_admin_setting_callback_use_wp_editor', '_bbp_use_wp_editor', 'toolbar &amp; buttons' ),
			'autoembed'     => array( 'bbp_admin_setting_callback_use_autoembed', '_bbp_use_autoembed', 'Embed media' ),
			'include root'  => array( 'bbp_admin_setting_callback_include_root', '_bbp_include_root', 'Prefix all forum content' ),
			'group forums'  => array( 'bbp_admin_setting_callback_group_forums', '_bbp_enable_group_forums', 'BuddyPress Groups' ),
			'Akismet'       => array( 'bbp_admin_setting_callback_akismet', '_bbp_enable_akismet', 'prevent forum spam' ),
		);
	}

	/**
	 * @covers ::bbp_admin_setting_callback_topics_per_page
	 * @covers ::bbp_admin_setting_callback_replies_per_page
	 * @covers ::bbp_admin_setting_callback_topics_per_rss_page
	 * @covers ::bbp_admin_setting_callback_replies_per_rss_page
	 *
	 * @dataProvider numeric_setting_callback_provider
	 */
	public function test_numeric_setting_callbacks_output_minimum_and_value( $callback, $option ) {
		$this->set_option( $option, 37 );
		$output = $this->capture_callback( $callback );

		$this->assertStringContainsString( 'name="' . $option . '"', $output );
		$this->assertStringContainsString( 'type="number" min="1" step="1"', $output );
		$this->assertStringContainsString( 'value="37"', $output );
		$this->assertStringNotContainsString( " disabled='disabled'", $output );

		bbpress()->options[ $option ] = 37;
		$this->assertStringContainsString( " disabled='disabled'", $this->capture_callback( $callback ) );
	}

	public static function numeric_setting_callback_provider() {
		return array(
			'topics per page'      => array( 'bbp_admin_setting_callback_topics_per_page', '_bbp_topics_per_page' ),
			'replies per page'     => array( 'bbp_admin_setting_callback_replies_per_page', '_bbp_replies_per_page' ),
			'topics per RSS page'  => array( 'bbp_admin_setting_callback_topics_per_rss_page', '_bbp_topics_per_rss_page' ),
			'replies per RSS page' => array( 'bbp_admin_setting_callback_replies_per_rss_page', '_bbp_replies_per_rss_page' ),
		);
	}

	/**
	 * @covers ::bbp_admin_setting_callback_root_slug
	 * @covers ::bbp_admin_setting_callback_user_slug
	 * @covers ::bbp_admin_setting_callback_topic_archive_slug
	 * @covers ::bbp_admin_setting_callback_reply_archive_slug
	 * @covers ::bbp_admin_setting_callback_user_favs_slug
	 * @covers ::bbp_admin_setting_callback_user_subs_slug
	 * @covers ::bbp_admin_setting_callback_user_engagements_slug
	 * @covers ::bbp_admin_setting_callback_forum_slug
	 * @covers ::bbp_admin_setting_callback_topic_slug
	 * @covers ::bbp_admin_setting_callback_reply_slug
	 * @covers ::bbp_admin_setting_callback_topic_tag_slug
	 * @covers ::bbp_admin_setting_callback_view_slug
	 * @covers ::bbp_admin_setting_callback_search_slug
	 * @covers ::bbp_admin_setting_callback_edit_slug
	 *
	 * @dataProvider slug_setting_callback_provider
	 */
	public function test_slug_setting_callbacks_output_editable_values( $callback, $option ) {
		$value = 'custom-' . trim( $option, '_' );
		$this->set_option( $option, $value );
		$output = $this->capture_callback( $callback );

		$this->assertStringContainsString( 'name="' . $option . '"', $output );
		$this->assertStringContainsString( 'class="regular-text code"', $output );
		$this->assertStringContainsString( 'value="' . $value . '"', $output );
		$this->assertStringNotContainsString( " disabled='disabled'", $output );

		bbpress()->options[ $option ] = $value;
		$this->assertStringContainsString( " disabled='disabled'", $this->capture_callback( $callback ) );
	}

	public static function slug_setting_callback_provider() {
		return array(
			'root'          => array( 'bbp_admin_setting_callback_root_slug', '_bbp_root_slug' ),
			'user'          => array( 'bbp_admin_setting_callback_user_slug', '_bbp_user_slug' ),
			'topic archive' => array( 'bbp_admin_setting_callback_topic_archive_slug', '_bbp_topic_archive_slug' ),
			'reply archive' => array( 'bbp_admin_setting_callback_reply_archive_slug', '_bbp_reply_archive_slug' ),
			'favorites'     => array( 'bbp_admin_setting_callback_user_favs_slug', '_bbp_user_favs_slug' ),
			'subscriptions' => array( 'bbp_admin_setting_callback_user_subs_slug', '_bbp_user_subs_slug' ),
			'engagements'   => array( 'bbp_admin_setting_callback_user_engagements_slug', '_bbp_user_engs_slug' ),
			'forum'         => array( 'bbp_admin_setting_callback_forum_slug', '_bbp_forum_slug' ),
			'topic'         => array( 'bbp_admin_setting_callback_topic_slug', '_bbp_topic_slug' ),
			'reply'         => array( 'bbp_admin_setting_callback_reply_slug', '_bbp_reply_slug' ),
			'topic tag'     => array( 'bbp_admin_setting_callback_topic_tag_slug', '_bbp_topic_tag_slug' ),
			'view'          => array( 'bbp_admin_setting_callback_view_slug', '_bbp_view_slug' ),
			'search'        => array( 'bbp_admin_setting_callback_search_slug', '_bbp_search_slug' ),
			'edit'          => array( 'bbp_admin_setting_callback_edit_slug', '_bbp_edit_slug' ),
		);
	}

	/**
	 * @covers ::bbp_admin_setting_callback_forums_status
	 */
	public function test_forums_status_callback_outputs_each_supported_status() {
		$this->set_option( '_bbp_forums_status', 'frozen' );
		$output = $this->capture_callback( 'bbp_admin_setting_callback_forums_status' );

		foreach ( bbp_get_forums_statuses() as $status => $label ) {
			$this->assertStringContainsString( 'value="' . $status . '"', $output );
			$this->assertStringContainsString( '>' . $label . '</option>', $output );
		}
		$this->assertMatchesRegularExpression( '/value="frozen"[^>]+selected=\'selected\'/', $output );
		$this->assertStringNotContainsString( " disabled='disabled'", $output );

		bbpress()->options['_bbp_forums_status'] = 'frozen';
		$this->assertStringContainsString( " disabled='disabled'", $this->capture_callback( 'bbp_admin_setting_callback_forums_status' ) );
	}

	/**
	 * @covers ::bbp_admin_setting_callback_editlock
	 * @covers ::bbp_admin_setting_callback_throttle
	 */
	public function test_edit_lock_and_throttle_callbacks_output_toggle_and_duration_fields() {
		$this->set_option( '_bbp_allow_content_edit', 1 );
		$this->set_option( '_bbp_edit_lock', 17 );
		$this->set_option( '_bbp_allow_content_throttle', 1 );
		$this->set_option( '_bbp_throttle_time', 23 );
		$edit_lock = $this->capture_callback( 'bbp_admin_setting_callback_editlock' );
		$throttle  = $this->capture_callback( 'bbp_admin_setting_callback_throttle' );

		$this->assertStringContainsString( 'name="_bbp_allow_content_edit"', $edit_lock );
		$this->assertStringContainsString( 'name="_bbp_edit_lock"', $edit_lock );
		$this->assertStringContainsString( 'value="17"', $edit_lock );
		$this->assertStringContainsString( " checked='checked'", $edit_lock );
		$this->assertStringContainsString( 'name="_bbp_allow_content_throttle"', $throttle );
		$this->assertStringContainsString( 'name="_bbp_throttle_time"', $throttle );
		$this->assertStringContainsString( 'value="23"', $throttle );
		$this->assertStringContainsString( " checked='checked'", $throttle );
		$this->assertSame( 0, substr_count( $edit_lock, "disabled='disabled'" ) );
		$this->assertSame( 0, substr_count( $throttle, "disabled='disabled'" ) );

		$this->set_option( '_bbp_allow_content_edit', 0 );
		$this->set_option( '_bbp_allow_content_throttle', 0 );
		$this->assertStringNotContainsString( " checked='checked'", $this->capture_callback( 'bbp_admin_setting_callback_editlock' ) );
		$this->assertStringNotContainsString( " checked='checked'", $this->capture_callback( 'bbp_admin_setting_callback_throttle' ) );

		bbpress()->options['_bbp_allow_content_edit']     = 1;
		bbpress()->options['_bbp_edit_lock']              = 17;
		bbpress()->options['_bbp_allow_content_throttle'] = 1;
		bbpress()->options['_bbp_throttle_time']          = 23;
		$edit_lock = $this->capture_callback( 'bbp_admin_setting_callback_editlock' );
		$throttle  = $this->capture_callback( 'bbp_admin_setting_callback_throttle' );
		$this->assertSame( 2, substr_count( $edit_lock, "disabled='disabled'" ) );
		$this->assertSame( 2, substr_count( $throttle, "disabled='disabled'" ) );
	}

	/**
	 * @ticket BBP3352
	 * @covers ::bbp_admin_setting_callback_moderation_links
	 */
	public function test_moderation_links_callback_outputs_inherited_and_explicit_limits() {
		$this->set_option( 'comment_max_links', 4 );
		$this->set_option( '_bbp_allow_moderation_links', 1 );
		$this->set_option( '_bbp_moderation_links', false );
		$output = $this->capture_callback( 'bbp_admin_setting_callback_moderation_links' );

		$this->assertStringContainsString( 'name="_bbp_allow_moderation_links"', $output );
		$this->assertStringContainsString( 'name="_bbp_moderation_links"', $output );
		$this->assertStringContainsString( 'value=""', $output );
		$this->assertStringContainsString( 'placeholder="4"', $output );
		$this->assertStringContainsString( 'currently 4.', $output );
		$this->assertStringContainsString( "checked='checked'", $output );

		$this->set_option( 'comment_max_links', 0 );
		$output = $this->capture_callback( 'bbp_admin_setting_callback_moderation_links' );
		$this->assertStringContainsString( 'placeholder=""', $output );
		$this->assertStringContainsString( 'currently has no link limit.', $output );

		$this->set_option( '_bbp_moderation_links', 7 );
		$this->assertStringContainsString( 'value="7"', $this->capture_callback( 'bbp_admin_setting_callback_moderation_links' ) );

		$this->set_option( '_bbp_allow_moderation_links', 0 );
		$this->assertStringNotContainsString( "checked='checked'", $this->capture_callback( 'bbp_admin_setting_callback_moderation_links' ) );

		bbpress()->options['_bbp_allow_moderation_links'] = 1;
		bbpress()->options['_bbp_moderation_links'] = 7;
		$this->assertSame( 2, substr_count( $this->capture_callback( 'bbp_admin_setting_callback_moderation_links' ), "disabled='disabled'" ) );
	}

	/**
	 * @covers ::bbp_admin_setting_callback_global_access
	 */
	public function test_global_access_callback_outputs_default_role_choices() {
		$this->set_option( '_bbp_allow_global_access', 1 );
		$this->set_option( '_bbp_default_role', bbp_get_moderator_role() );
		$output = $this->capture_callback( 'bbp_admin_setting_callback_global_access' );

		$this->assertStringContainsString( 'name="_bbp_allow_global_access"', $output );
		$this->assertStringContainsString( 'name="_bbp_default_role"', $output );
		foreach ( array_keys( bbp_get_dynamic_roles() ) as $role ) {
			$this->assertStringContainsString( 'value="' . $role . '"', $output );
		}
		$this->assertMatchesRegularExpression( '/selected=\'selected\' value="' . bbp_get_moderator_role() . '"/', $output );
		$this->assertStringContainsString( " checked='checked'", $output );
		$this->assertSame( 0, substr_count( $output, "disabled='disabled'" ) );

		$this->set_option( '_bbp_allow_global_access', 0 );
		$this->assertStringNotContainsString( " checked='checked'", $this->capture_callback( 'bbp_admin_setting_callback_global_access' ) );

		bbpress()->options['_bbp_allow_global_access'] = 1;
		bbpress()->options['_bbp_default_role']        = bbp_get_moderator_role();
		$this->assertSame( 2, substr_count( $this->capture_callback( 'bbp_admin_setting_callback_global_access' ), "disabled='disabled'" ) );
	}

	/**
	 * @covers ::bbp_admin_setting_callback_thread_replies_depth
	 */
	public function test_threaded_replies_callback_honors_the_maximum_depth_filter() {
		$this->set_option( '_bbp_allow_threaded_replies', 1 );
		$this->set_option( '_bbp_thread_replies_depth', 3 );
		$filter = function () {
			return 3;
		};
		add_filter( 'bbp_thread_replies_depth_max', $filter );

		try {
			$output = $this->capture_callback( 'bbp_admin_setting_callback_thread_replies_depth' );
			$this->assertStringContainsString( 'name="_bbp_allow_threaded_replies"', $output );
			$this->assertStringContainsString( 'name="_bbp_thread_replies_depth"', $output );
			$this->assertStringContainsString( 'value="2"', $output );
			$this->assertStringContainsString( 'value="3"', $output );
			$this->assertStringNotContainsString( 'value="4"', $output );
			$this->assertMatchesRegularExpression( '/value="3"[^>]+selected=\'selected\'/', $output );
			$this->assertStringContainsString( " checked='checked'", $output );
			$this->assertSame( 0, substr_count( $output, "disabled='disabled'" ) );

			bbpress()->options['_bbp_allow_threaded_replies'] = 1;
			bbpress()->options['_bbp_thread_replies_depth']   = 3;
			$this->assertSame( 2, substr_count( $this->capture_callback( 'bbp_admin_setting_callback_thread_replies_depth' ), "disabled='disabled'" ) );
		} finally {
			remove_filter( 'bbp_thread_replies_depth_max', $filter );
		}
	}

	/**
	 * @covers ::bbp_admin_setting_callback_subtheme_id
	 */
	public function test_theme_package_callback_lists_packages_and_handles_an_empty_registry() {
		$packages = bbpress()->theme_compat->packages;

		try {
			$output = $this->capture_callback( 'bbp_admin_setting_callback_subtheme_id' );
			$this->assertStringContainsString( 'name="_bbp_theme_package_id"', $output );
			$this->assertStringContainsString( 'value="default"', $output );

			bbpress()->theme_compat->packages = array();
			$output = $this->capture_callback( 'bbp_admin_setting_callback_subtheme_id' );
			$this->assertStringContainsString( 'No template packages available.', $output );
		} finally {
			bbpress()->theme_compat->packages = $packages;
		}
	}

	/**
	 * @covers ::bbp_admin_setting_callback_show_on_root
	 */
	public function test_show_on_root_callback_outputs_both_supported_choices() {
		$this->set_option( '_bbp_show_on_root', 'topics' );
		$output = $this->capture_callback( 'bbp_admin_setting_callback_show_on_root' );

		$this->assertStringContainsString( 'name="_bbp_show_on_root"', $output );
		$this->assertStringContainsString( 'value="forums"', $output );
		$this->assertStringContainsString( 'value="topics"', $output );
		$this->assertMatchesRegularExpression( '/selected=\'selected\' value="topics"/', $output );
		$this->assertStringNotContainsString( " disabled='disabled'", $output );

		bbpress()->options['_bbp_show_on_root'] = 'topics';
		$this->assertStringContainsString( " disabled='disabled'", $this->capture_callback( 'bbp_admin_setting_callback_show_on_root' ) );
	}

	/**
	 * @covers ::bbp_admin_setting_callback_group_forums_root_id
	 */
	public function test_group_forums_root_callback_removes_an_invalid_root_and_outputs_the_forum_selector() {
		$this->set_option( '_bbp_group_forums_root_id', 999999 );

		$output = $this->capture_callback( 'bbp_admin_setting_callback_group_forums_root_id' );

		$this->assertStringContainsString( 'id="_bbp_group_forums_root_id"', $output );
		$this->assertStringContainsString( '&mdash; No parent &mdash;', $output );
		$this->assertStringContainsString( 'Changing this will not move existing forums.', $output );
		$this->assertStringNotContainsString( 'create=bbp-group-forum-root', $output );
		$this->assertStringNotContainsString( "disabled='disabled'", $output );
		$this->assertFalse( get_option( '_bbp_group_forums_root_id', false ) );
	}

	/**
	 * @covers ::bbp_admin_setting_callback_group_forums_root_id
	 */
	public function test_group_forums_root_callback_preserves_a_valid_root_and_offers_creation_to_keymasters() {
		$old_user = get_current_user_id();
		$forum_id = $this->factory->forum->create( array( 'post_title' => 'Group Forums' ) );
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		try {
			$this->set_option( '_bbp_group_forums_root_id', $forum_id );
			$output = $this->capture_callback( 'bbp_admin_setting_callback_group_forums_root_id' );
			$this->assertSame( $forum_id, (int) get_option( '_bbp_group_forums_root_id' ) );
			$this->assertMatchesRegularExpression( '/value="' . $forum_id . '"[^>]+selected=\'selected\'/', $output );
			$this->assertStringNotContainsString( 'create=bbp-group-forum-root', $output );
			$this->assertStringNotContainsString( "disabled='disabled'", $output );

			bbpress()->options['_bbp_group_forums_root_id'] = $forum_id;
			$this->assertStringContainsString( "disabled='disabled'", $this->capture_callback( 'bbp_admin_setting_callback_group_forums_root_id' ) );
			unset( bbpress()->options['_bbp_group_forums_root_id'] );

			$this->set_option( '_bbp_group_forums_root_id', 0 );
			bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
			$this->set_current_user( $admin_id );
			$output = $this->capture_callback( 'bbp_admin_setting_callback_group_forums_root_id' );
			$this->assertStringContainsString( 'create=bbp-group-forum-root', $output );
			$this->assertStringContainsString( '_wpnonce=', $output );
		} finally {
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_converter_setting_callback_main_section
	 * @covers ::bbp_converter_setting_callback_options_section
	 *
	 * @dataProvider converter_section_callback_provider
	 */
	public function test_converter_section_callbacks_output_descriptions( $callback, $description ) {
		$this->assertStringContainsString( $description, $this->capture_callback( $callback ) );
	}

	public static function converter_section_callback_provider() {
		return array(
			'connection' => array( 'bbp_converter_setting_callback_main_section', 'database for your previous forums' ),
			'options'    => array( 'bbp_converter_setting_callback_options_section', 'parameters to help tune the conversion process' ),
		);
	}

	/**
	 * @covers ::bbp_converter_setting_callback_platform
	 */
	public function test_converter_platform_callback_lists_and_selects_filtered_converters() {
		$this->set_option( '_bbp_converter_platform', 'Test Two' );
		$filter = function () {
			return array(
				'Test One' => '/tmp/TestOne.php',
				'Test Two' => '/tmp/TestTwo.php',
			);
		};
		add_filter( 'bbp_get_converters', $filter );

		try {
			$output = $this->capture_callback( 'bbp_converter_setting_callback_platform' );
			$this->assertStringContainsString( 'name="_bbp_converter_platform"', $output );
			$this->assertStringContainsString( '<option value="Test One">Test One</option>', $output );
			$this->assertStringContainsString( '<option value="Test Two" selected=\'selected\'>Test Two</option>', $output );
			$this->assertStringContainsString( 'The previous forum software', $output );
		} finally {
			remove_filter( 'bbp_get_converters', $filter );
		}
	}

	/**
	 * @covers ::bbp_converter_setting_callback_dbserver
	 * @covers ::bbp_converter_setting_callback_dbport
	 * @covers ::bbp_converter_setting_callback_dbuser
	 * @covers ::bbp_converter_setting_callback_dbname
	 * @covers ::bbp_converter_setting_callback_dbprefix
	 *
	 * @dataProvider converter_text_setting_callback_provider
	 */
	public function test_converter_text_setting_callbacks_output_stored_values( $callback, $option, $value, $description ) {
		$this->set_option( $option, $value );
		$output = $this->capture_callback( $callback );

		$this->assertStringContainsString( 'name="' . $option . '"', $output );
		$this->assertStringContainsString( 'id="' . $option . '"', $output );
		$this->assertStringContainsString( 'class="code"', $output );
		$this->assertStringContainsString( 'value="' . $value . '"', $output );
		$this->assertStringContainsString( $description, $output );
		$this->assertStringNotContainsString( "disabled='disabled'", $output );

		bbpress()->options[ $option ] = $value;
		$this->assertStringContainsString( "disabled='disabled'", $this->capture_callback( $callback ) );
	}

	public static function converter_text_setting_callback_provider() {
		return array(
			'database server' => array( 'bbp_converter_setting_callback_dbserver', '_bbp_converter_db_server', 'db.example.test', 'localhost' ),
			'database port'   => array( 'bbp_converter_setting_callback_dbport', '_bbp_converter_db_port', '4410', '3306' ),
			'database user'   => array( 'bbp_converter_setting_callback_dbuser', '_bbp_converter_db_user', 'legacy-user', 'User to access the database' ),
			'database name'   => array( 'bbp_converter_setting_callback_dbname', '_bbp_converter_db_name', 'legacy-db', 'Name of the database' ),
			'table prefix'    => array( 'bbp_converter_setting_callback_dbprefix', '_bbp_converter_db_prefix', 'legacy_', 'BuddyPress Legacy' ),
		);
	}

	/**
	 * @covers ::bbp_converter_setting_callback_dbpass
	 */
	public function test_converter_password_callback_never_outputs_the_saved_password() {
		$this->set_option( '_bbp_converter_db_pass', 'saved-secret' );
		$output = $this->capture_callback( 'bbp_converter_setting_callback_dbpass' );

		$this->assertStringContainsString( 'name="_bbp_converter_db_pass"', $output );
		$this->assertStringContainsString( 'type="password" value="" autocomplete="off"', $output );
		$this->assertStringContainsString( 'name="_bbp_converter_db_pass_clear"', $output );
		$this->assertStringContainsString( 'Leave blank to keep the saved password.', $output );
		$this->assertStringNotContainsString( 'saved-secret', $output );
		$this->assertStringNotContainsString( "disabled='disabled'", $output );

		bbpress()->options['_bbp_converter_db_pass'] = 'forced-secret';
		$this->assertStringContainsString( "disabled='disabled'", $this->capture_callback( 'bbp_converter_setting_callback_dbpass' ) );
	}

	/**
	 * @covers ::bbp_converter_setting_callback_rows
	 * @covers ::bbp_converter_setting_callback_delay_time
	 *
	 * @dataProvider converter_numeric_setting_callback_provider
	 */
	public function test_converter_numeric_setting_callbacks_output_limits_and_values( $callback, $option, $value, $minimum, $maximum ) {
		$this->set_option( $option, $value );
		$output = $this->capture_callback( $callback );

		$this->assertStringContainsString( 'name="' . $option . '"', $output );
		$this->assertStringContainsString( 'type="number" min="' . $minimum . '" max="' . $maximum . '"', $output );
		$this->assertStringContainsString( 'value="' . $value . '"', $output );
		$this->assertStringNotContainsString( "disabled='disabled'", $output );

		bbpress()->options[ $option ] = $value;
		$this->assertStringContainsString( "disabled='disabled'", $this->capture_callback( $callback ) );
	}

	public static function converter_numeric_setting_callback_provider() {
		return array(
			'rows'  => array( 'bbp_converter_setting_callback_rows', '_bbp_converter_rows', '321', '1', '5000' ),
			'delay' => array( 'bbp_converter_setting_callback_delay_time', '_bbp_converter_delay_time', '17', '2', '3600' ),
		);
	}

	/**
	 * @covers ::bbp_converter_setting_callback_halt
	 * @covers ::bbp_converter_setting_callback_restart
	 * @covers ::bbp_converter_setting_callback_clean
	 *
	 * @dataProvider converter_checkbox_setting_callback_provider
	 */
	public function test_converter_checkbox_setting_callbacks_honor_stored_values( $callback, $option, $label ) {
		$this->set_option( $option, 1 );
		$output = $this->capture_callback( $callback );
		$this->assertStringContainsString( 'name="' . $option . '"', $output );
		$this->assertStringContainsString( "checked='checked'", $output );
		$this->assertStringContainsString( $label, $output );

		$this->set_option( $option, 0 );
		$this->assertStringNotContainsString( "checked='checked'", $this->capture_callback( $callback ) );
	}

	public static function converter_checkbox_setting_callback_provider() {
		return array(
			'halt'    => array( 'bbp_converter_setting_callback_halt', '_bbp_converter_halt', 'Halt the conversion' ),
			'restart' => array( 'bbp_converter_setting_callback_restart', '_bbp_converter_restart', 'Restart the converter' ),
			'clean'   => array( 'bbp_converter_setting_callback_clean', '_bbp_converter_clean', 'Purge all meta-data' ),
		);
	}

	/**
	 * @covers ::bbp_converter_setting_callback_convert_users
	 */
	public function test_converter_users_callback_reflects_capability_and_stored_value() {
		$old_user      = get_current_user_id();
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$admin         = get_userdata( $admin_id );

		try {
			$this->set_option( '_bbp_converter_convert_users', 1 );
			$this->set_current_user( $subscriber_id );
			$output = $this->capture_callback( 'bbp_converter_setting_callback_convert_users' );
			$this->assertStringNotContainsString( "checked='checked'", $output );
			$this->assertStringContainsString( "disabled='disabled'", $output );
			$this->assertStringContainsString( 'A network administrator is required', $output );

			$admin->add_cap( 'bbp_tools_import_users' );
			$admin->add_cap( bbp_admin()->minimum_capability );
			$this->set_current_user( 0 );
			$this->set_current_user( $admin_id );
			$output = $this->capture_callback( 'bbp_converter_setting_callback_convert_users' );
			$this->assertStringContainsString( "checked='checked'", $output );
			$this->assertStringNotContainsString( "disabled='disabled'", $output );
			$this->assertStringContainsString( 'Passwords remain encrypted', $output );

			$this->set_option( '_bbp_converter_convert_users', 0 );
			$output = $this->capture_callback( 'bbp_converter_setting_callback_convert_users' );
			$this->assertStringNotContainsString( "checked='checked'", $output );
			$this->assertStringNotContainsString( "disabled='disabled'", $output );

			bbpress()->options['_bbp_converter_convert_users'] = 1;
			$this->assertStringContainsString( "disabled='disabled'", $this->capture_callback( 'bbp_converter_setting_callback_convert_users' ) );
		} finally {
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_converter_settings_page
	 */
	public function test_converter_settings_page_outputs_ready_and_resume_states() {
		$converter = bbp_admin()->converter;

		try {
			bbp_admin()->converter            = new stdClass();
			bbp_admin()->converter->max_steps = 9;
			$this->set_option( '_bbp_converter_step', 0 );
			$output = $this->capture_callback( 'bbp_converter_settings_page' );
			$this->assertStringContainsString( 'id="bbp-converter-status">Ready', $output );
			$this->assertStringContainsString( 'id="bbp-converter-start" value="Start"', $output );
			$this->assertStringContainsString( '<p>Ready to go.</p>', $output );

			$this->set_option( '_bbp_converter_step', 4 );
			$output = $this->capture_callback( 'bbp_converter_settings_page' );
			$this->assertStringContainsString( 'id="bbp-converter-status">Up next: step 4', $output );
			$this->assertStringContainsString( 'id="bbp-converter-start" value="Resume"', $output );
			$this->assertStringContainsString( 'Previously stopped at step 4 of 9', $output );
		} finally {
			bbp_admin()->converter = $converter;
		}
	}
}
