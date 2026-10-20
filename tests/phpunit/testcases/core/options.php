<?php

/**
 * Tests for core option functions.
 *
 * @group core
 * @group options
 */
class BBP_Tests_Core_Options extends BBP_UnitTestCase {

	private $saved_options = array();
	private $bbp_options;
	private $bbp_not_options;

	public function setUp(): void {
		parent::setUp();

		$this->bbp_options     = bbpress()->options;
		$this->bbp_not_options = bbpress()->not_options;
		bbpress()->options     = array();
		bbpress()->not_options = array();
	}

	public function tearDown(): void {
		foreach ( $this->saved_options as $option => $saved ) {
			if ( $saved['exists'] ) {
				update_option( $option, $saved['value'] );
			} else {
				delete_option( $option );
			}
		}

		bbpress()->options     = $this->bbp_options;
		bbpress()->not_options = $this->bbp_not_options;

		parent::tearDown();
	}

	private function remember_option( $option ) {
		if ( isset( $this->saved_options[ $option ] ) ) {
			return;
		}

		$missing = new stdClass();
		$value   = get_option( $option, $missing );

		$this->saved_options[ $option ] = array(
			'exists' => ( $missing !== $value ),
			'value'  => $value,
		);
	}

	private function set_option( $option, $value ) {
		$this->remember_option( $option );
		delete_option( $option );
		add_option( $option, $value );
	}

	private function delete_option( $option ) {
		$this->remember_option( $option );
		delete_option( $option );
	}

	/**
	 * @covers ::bbp_get_default_options
	 */
	public function test_bbp_get_default_options() {
		$options = bbp_get_default_options();

		$this->assertSame( 0, $options['_bbp_db_version'] );
		$this->assertSame( bbp_get_participant_role(), $options['_bbp_default_role'] );
		$this->assertSame( 1, $options['_bbp_allow_moderation_links'] );
		$this->assertFalse( $options['_bbp_moderation_links'] );
		$this->assertSame( 15, $options['_bbp_topics_per_page'] );
		$this->assertSame( 'forums', $options['_bbp_root_slug'] );
		$this->assertSame( 'meta', $options['_bbp_engagements_strategy'] );

		$filter = function( $defaults ) {
			$defaults['_bbp_test_option'] = 'filtered';
			return $defaults;
		};
		add_filter( 'bbp_get_default_options', $filter );

		try {
			$this->assertSame( 'filtered', bbp_get_default_options()['_bbp_test_option'] );
		} finally {
			remove_filter( 'bbp_get_default_options', $filter );
		}
	}

	/**
	 * @covers ::bbp_add_options
	 */
	public function test_bbp_add_options_is_non_destructive_and_supports_custom_defaults() {
		foreach ( array_keys( bbp_get_default_options() ) as $option ) {
			$this->remember_option( $option );
		}
		$this->remember_option( '_bbp_test_option' );

		$this->set_option( '_bbp_root_slug', 'existing-root' );
		$this->delete_option( '_bbp_allow_anonymous' );

		$called = 0;
		$action = function() use ( &$called ) {
			++$called;
		};
		$filter = function( $defaults ) {
			$defaults['_bbp_test_option'] = 'custom';
			return $defaults;
		};
		add_action( 'bbp_add_options', $action );
		add_filter( 'bbp_get_default_options', $filter );

		try {
			bbp_add_options();
		} finally {
			remove_action( 'bbp_add_options', $action );
			remove_filter( 'bbp_get_default_options', $filter );
		}

		$this->assertSame( 1, $called );
		$this->assertSame( 'existing-root', get_option( '_bbp_root_slug' ) );
		$this->assertSame( 0, get_option( '_bbp_allow_anonymous' ) );
		$this->assertSame( 'custom', get_option( '_bbp_test_option' ) );
	}

	/**
	 * @covers ::bbp_delete_options
	 */
	public function test_bbp_delete_options_removes_defaults_and_custom_defaults_only() {
		foreach ( array_keys( bbp_get_default_options() ) as $option ) {
			$this->remember_option( $option );
		}
		$this->remember_option( '_bbp_test_option' );
		$this->remember_option( 'bbp_test_unrelated' );

		$this->set_option( '_bbp_root_slug', 'delete-me' );
		$this->set_option( '_bbp_test_option', 'delete-me-too' );
		$this->set_option( 'bbp_test_unrelated', 'keep' );

		$called = 0;
		$action = function() use ( &$called ) {
			++$called;
		};
		$filter = function( $defaults ) {
			$defaults['_bbp_test_option'] = 'custom';
			return $defaults;
		};
		add_action( 'bbp_delete_options', $action );
		add_filter( 'bbp_get_default_options', $filter );

		try {
			bbp_delete_options();
		} finally {
			remove_action( 'bbp_delete_options', $action );
			remove_filter( 'bbp_get_default_options', $filter );
		}

		$this->assertSame( 1, $called );
		$this->assertFalse( get_option( '_bbp_root_slug', false ) );
		$this->assertFalse( get_option( '_bbp_test_option', false ) );
		$this->assertSame( 'keep', get_option( 'bbp_test_unrelated' ) );
	}

	/**
	 * @covers ::bbp_setup_option_filters
	 */
	public function test_bbp_setup_option_filters_supports_custom_defaults() {
		$called = 0;
		$action = function() use ( &$called ) {
			++$called;
		};
		$filter = function( $defaults ) {
			$defaults['_bbp_test_option'] = 'default';
			return $defaults;
		};
		add_action( 'bbp_setup_option_filters', $action );
		add_filter( 'bbp_get_default_options', $filter );

		try {
			bbp_setup_option_filters();
			$this->assertSame( 10, has_filter( 'pre_option__bbp_test_option', 'bbp_filter_pre_get_option' ) );
			$this->assertSame( 10, has_filter( 'default_option__bbp_test_option', 'bbp_filter_default_option' ) );
			$this->assertSame( 'default', get_option( '_bbp_test_option' ) );

			bbpress()->options['_bbp_test_option'] = 'overloaded';
			$this->assertSame( 'overloaded', get_option( '_bbp_test_option' ) );
		} finally {
			remove_action( 'bbp_setup_option_filters', $action );
			remove_filter( 'bbp_get_default_options', $filter );
			remove_filter( 'pre_option__bbp_test_option', 'bbp_filter_pre_get_option', 10 );
			remove_filter( 'default_option__bbp_test_option', 'bbp_filter_default_option', 10 );
		}

		$this->assertSame( 1, $called );
	}

	/**
	 * @covers ::bbp_filter_pre_get_option
	 * @covers ::bbp_filter_default_option
	 */
	public function test_option_filters_handle_overloads_defaults_and_unknown_options() {
		bbpress()->options['_bbp_test_option'] = 'overloaded';

		$this->assertSame( 'overloaded', bbp_filter_pre_get_option( false, '_bbp_test_option' ) );
		$this->assertSame( 'original', bbp_filter_pre_get_option( 'original', '_bbp_missing_option' ) );
		$this->assertSame( 'forums', bbp_filter_default_option( false, '_bbp_root_slug', false ) );
		$this->assertSame( 'passed', bbp_filter_default_option( 'passed', '_bbp_root_slug', true ) );
		$this->assertSame( 'original', bbp_filter_default_option( 'original', '_bbp_missing_option', false ) );
	}

	/**
	 * @covers ::bbp_pre_load_options
	 */
	public function test_bbp_pre_load_options_can_cache_missing_defaults_as_options() {
		$option     = '_bbp_test_preloaded_option';
		$old_cached = wp_cache_get( $option, 'options' );
		$filter     = function( $defaults ) use ( $option ) {
			$defaults[ $option ] = 'preloaded';
			return $defaults;
		};
		$strategy   = function() {
			return 'option';
		};

		$this->delete_option( $option );
		wp_cache_delete( $option, 'options' );
		add_filter( 'bbp_get_default_options', $filter );
		add_filter( 'bbp_pre_load_options_strategy', $strategy );

		try {
			bbp_pre_load_options();
			$this->assertSame( 'preloaded', bbpress()->not_options[ $option ] );
			$this->assertSame( 'preloaded', wp_cache_get( $option, 'options' ) );
		} finally {
			remove_filter( 'bbp_get_default_options', $filter );
			remove_filter( 'bbp_pre_load_options_strategy', $strategy );
			if ( false === $old_cached ) {
				wp_cache_delete( $option, 'options' );
			} else {
				wp_cache_set( $option, $old_cached, 'options' );
			}
		}
	}

	/**
	 * @covers ::bbp_pre_load_options
	 */
	public function test_bbp_pre_load_options_uses_the_notoptions_cache_by_default() {
		$option          = '_bbp_test_missing_option';
		$old_not_options = wp_cache_get( 'notoptions', 'options' );
		$filter          = function( $defaults ) use ( $option ) {
			$defaults[ $option ] = 'missing-default';
			return $defaults;
		};

		$this->delete_option( $option );
		wp_cache_delete( $option, 'options' );
		add_filter( 'bbp_get_default_options', $filter );

		try {
			bbp_pre_load_options();
			$not_options = wp_cache_get( 'notoptions', 'options' );

			$this->assertSame( 'missing-default', bbpress()->not_options[ $option ] );
			$this->assertArrayHasKey( $option, $not_options );
			$this->assertTrue( $not_options[ $option ] );
		} finally {
			remove_filter( 'bbp_get_default_options', $filter );
			if ( false === $old_not_options ) {
				wp_cache_delete( 'notoptions', 'options' );
			} else {
				wp_cache_set( 'notoptions', $old_not_options, 'options' );
			}
		}
	}

	/**
	 * @covers ::bbp_is_favorites_active
	 * @covers ::bbp_is_subscriptions_active
	 * @covers ::bbp_is_engagements_active
	 * @covers ::bbp_allow_content_edit
	 * @covers ::bbp_allow_content_throttle
	 * @covers ::bbp_allow_topic_tags
	 * @covers ::bbp_allow_forum_mods
	 * @covers ::bbp_allow_super_mods
	 * @covers ::bbp_allow_search
	 * @covers ::bbp_allow_threaded_replies
	 * @covers ::bbp_allow_revisions
	 * @covers ::bbp_allow_anonymous
	 * @covers ::bbp_allow_global_access
	 * @covers ::bbp_use_wp_editor
	 * @covers ::bbp_use_autoembed
	 * @covers ::bbp_is_group_forums_active
	 * @covers ::bbp_is_akismet_active
	 * @covers ::bbp_include_root_slug
	 *
	 * @dataProvider boolean_option_provider
	 */
	public function test_boolean_option_accessors( $function, $option, $filter ) {
		$this->set_option( $option, 0 );
		$this->assertFalse( $function() );

		update_option( $option, '1' );
		$this->assertTrue( $function() );

		$callback = function() {
			return false;
		};
		add_filter( $filter, $callback );
		try {
			$this->assertFalse( $function() );
		} finally {
			remove_filter( $filter, $callback );
		}
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_get_moderation_notification_recipient_types
	 * @covers ::bbp_get_moderation_notification_recipient_type_labels
	 * @covers ::bbp_is_moderation_notifications_active
	 */
	public function test_moderation_notifications_use_filterable_recipient_types() {
		$this->set_option( '_bbp_moderation_notification_recipient_types', array() );
		$this->assertFalse( bbp_is_moderation_notifications_active() );

		update_option( '_bbp_moderation_notification_recipient_types', array( 'keymasters', 'keymasters', 'Bad Type!' ) );
		$this->assertSame( array( 'keymasters' ), bbp_get_moderation_notification_recipient_types() );
		$this->assertTrue( bbp_is_moderation_notifications_active() );
		$this->assertSame(
			array( 'keymasters', 'moderators', 'forum_moderators' ),
			array_keys( bbp_get_moderation_notification_recipient_type_labels() )
		);

		add_filter( 'bbp_is_moderation_notifications_active', '__return_false' );
		try {
			$this->assertFalse( bbp_is_moderation_notifications_active() );
		} finally {
			remove_filter( 'bbp_is_moderation_notifications_active', '__return_false' );
		}
	}

	public static function boolean_option_provider() {
		return array(
			'favorites'        => array( 'bbp_is_favorites_active', '_bbp_enable_favorites', 'bbp_is_favorites_active' ),
			'subscriptions'    => array( 'bbp_is_subscriptions_active', '_bbp_enable_subscriptions', 'bbp_is_subscriptions_active' ),
			'engagements'      => array( 'bbp_is_engagements_active', '_bbp_enable_engagements', 'bbp_is_engagements_active' ),
			'content edit'     => array( 'bbp_allow_content_edit', '_bbp_allow_content_edit', 'bbp_allow_content_edit' ),
			'throttle'         => array( 'bbp_allow_content_throttle', '_bbp_allow_content_throttle', 'bbp_allow_content_throttle' ),
			'link moderation'  => array( 'bbp_allow_moderation_links', '_bbp_allow_moderation_links', 'bbp_allow_moderation_links' ),
			'topic tags'       => array( 'bbp_allow_topic_tags', '_bbp_allow_topic_tags', 'bbp_allow_topic_tags' ),
			'forum moderators' => array( 'bbp_allow_forum_mods', '_bbp_allow_forum_mods', 'bbp_allow_forum_mods' ),
			'super moderators' => array( 'bbp_allow_super_mods', '_bbp_allow_super_mods', 'bbp_allow_super_mods' ),
			'search'           => array( 'bbp_allow_search', '_bbp_allow_search', 'bbp_allow_search' ),
			'threaded replies' => array( 'bbp_allow_threaded_replies', '_bbp_allow_threaded_replies', 'bbp_allow_threaded_replies' ),
			'revisions'        => array( 'bbp_allow_revisions', '_bbp_allow_revisions', 'bbp_allow_revisions' ),
			'anonymous'        => array( 'bbp_allow_anonymous', '_bbp_allow_anonymous', 'bbp_allow_anonymous' ),
			'global access'    => array( 'bbp_allow_global_access', '_bbp_allow_global_access', 'bbp_allow_global_access' ),
			'WordPress editor' => array( 'bbp_use_wp_editor', '_bbp_use_wp_editor', 'bbp_use_wp_editor' ),
			'autoembed'        => array( 'bbp_use_autoembed', '_bbp_use_autoembed', 'bbp_use_autoembed' ),
			'group forums'     => array( 'bbp_is_group_forums_active', '_bbp_enable_group_forums', 'bbp_is_group_forums_active' ),
			'Akismet'          => array( 'bbp_is_akismet_active', '_bbp_enable_akismet', 'bbp_is_akismet_active' ),
			'include root'     => array( 'bbp_include_root_slug', '_bbp_include_root', 'bbp_include_root_slug' ),
		);
	}

	/**
	 * @ticket BBP3707
	 * @covers ::bbp_allow_threaded_replies
	 */
	public function test_bbp_allow_threaded_replies_preserves_the_legacy_filter_before_the_public_filter() {
		$this->set_option( '_bbp_allow_threaded_replies', 1 );

		$order  = array();
		$legacy = function( $allow ) use ( &$order ) {
			$order[] = 'legacy';
			$this->assertTrue( $allow );
			return false;
		};
		$public = function( $allow ) use ( &$order ) {
			$order[] = 'public';
			$this->assertFalse( $allow );
			return true;
		};

		add_filter( '_bbp_allow_threaded_replies', $legacy );
		add_filter( 'bbp_allow_threaded_replies', $public );
		try {
			$this->assertTrue( bbp_allow_threaded_replies() );
		} finally {
			remove_filter( '_bbp_allow_threaded_replies', $legacy );
			remove_filter( 'bbp_allow_threaded_replies', $public );
		}

		$this->assertSame( array( 'legacy', 'public' ), $order );
	}

	/**
	 * @ticket BBP3352
	 * @covers ::bbp_get_moderation_link_limit
	 */
	public function test_bbp_get_moderation_link_limit_inherits_and_filters_the_limit() {
		$this->set_option( 'comment_max_links', 4 );
		$this->set_option( '_bbp_moderation_links', false );
		$this->assertSame( 4, bbp_get_moderation_link_limit() );

		update_option( '_bbp_moderation_links', 7 );
		$this->assertSame( 7, bbp_get_moderation_link_limit() );

		$filter = function( $limit ) {
			return $limit + 2;
		};
		add_filter( 'bbp_get_moderation_link_limit', $filter );

		try {
			$this->assertSame( 9, bbp_get_moderation_link_limit() );
		} finally {
			remove_filter( 'bbp_get_moderation_link_limit', $filter );
		}
	}

	/**
	 * @covers ::bbp_thread_replies_depth
	 * @covers ::bbp_get_title_max_length
	 * @covers ::bbp_get_edit_lock
	 * @covers ::bbp_get_group_forums_root_id
	 * @covers ::bbp_title_max_length
	 * @covers ::bbp_edit_lock
	 * @covers ::bbp_group_forums_root_id
	 */
	public function test_integer_options_and_output_wrappers() {
		$this->set_option( '_bbp_thread_replies_depth', '4' );
		$this->set_option( '_bbp_title_max_length', '120' );
		$this->set_option( '_bbp_edit_lock', '8' );
		$this->set_option( '_bbp_group_forums_root_id', '42' );

		$this->assertSame( 4, bbp_thread_replies_depth() );
		$this->assertSame( 120, bbp_get_title_max_length() );
		$this->assertSame( 8, bbp_get_edit_lock() );
		$this->assertSame( 42, bbp_get_group_forums_root_id() );

		$this->expectOutputString( '120842' );
		bbp_title_max_length();
		bbp_edit_lock();
		bbp_group_forums_root_id();
	}

	/**
	 * @covers ::bbp_settings_integration
	 */
	public function test_bbp_settings_integration_normalizes_legacy_and_invalid_values() {
		$this->set_option( '_bbp_settings_integration', 1 );
		$this->assertSame( 'deep', bbp_settings_integration() );

		update_option( '_bbp_settings_integration', 0 );
		$this->assertSame( 'basic', bbp_settings_integration() );

		update_option( '_bbp_settings_integration', 'compact' );
		$this->assertSame( 'compact', bbp_settings_integration() );

		update_option( '_bbp_settings_integration', 'invalid' );
		$this->assertSame( 'basic', bbp_settings_integration() );

		$filter = function( $integration, $default ) {
			$this->assertSame( 'basic', $integration );
			$this->assertSame( 'fallback', $default );
			return 'filtered';
		};
		add_filter( 'bbp_settings_integration', $filter, 10, 2 );
		try {
			$this->assertSame( 'filtered', bbp_settings_integration( 'fallback' ) );
		} finally {
			remove_filter( 'bbp_settings_integration', $filter, 10 );
		}
	}

	/**
	 * @ticket BBP3739
	 *
	 * @covers ::bbp_is_valid_engagements_strategy
	 */
	public function test_bbp_is_valid_engagements_strategy_requires_a_supported_class() {
		$this->assertTrue( bbp_is_valid_engagements_strategy( 'meta' ) );
		$this->assertTrue( bbp_is_valid_engagements_strategy( 'user' ) );
		$this->assertTrue( bbp_is_valid_engagements_strategy( 'term' ) );
		$this->assertTrue( bbp_is_valid_engagements_strategy( 'standalone' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( 'missing' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( 'base' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( 'BASE' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( '' ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( array( 'meta' ) ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( null ) );
		$this->assertFalse( bbp_is_valid_engagements_strategy( 1 ) );
	}

	/**
	 * @ticket BBP3739
	 *
	 * @covers ::bbp_engagements_strategy
	 */
	public function test_bbp_engagements_strategy_requires_a_supported_class() {
		$this->set_option( '_bbp_engagements_strategy', 'user' );
		$this->assertSame( 'user', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', 'term' );
		$this->assertSame( 'term', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', 'missing' );
		$this->assertSame( 'meta', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', array( 'meta' ) );
		$this->assertSame( 'meta', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', null );
		$this->assertSame( 'meta', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', new stdClass() );
		$this->assertSame( 'meta', bbp_engagements_strategy() );

		update_option( '_bbp_engagements_strategy', 'base' );
		$this->assertSame( 'meta', bbp_engagements_strategy() );
	}

	/**
	 * @ticket BBP3739
	 *
	 * @covers ::bbp_engagements_strategy
	 */
	public function test_bbp_engagements_strategy_requires_a_supported_filtered_class() {
		$this->set_option( '_bbp_engagements_strategy', array( 'meta' ) );

		$filtered = null;
		$filter   = function( $integration, $default ) use ( &$filtered ) {
			$this->assertSame( 'meta', $integration );
			$this->assertSame( 'fallback', $default );

			return $filtered;
		};
		add_filter( 'bbp_engagements_strategy', $filter, 10, 2 );

		try {
			$filtered = array( 'meta' );
			$this->assertSame( 'meta', bbp_engagements_strategy( 'fallback' ) );

			$filtered = 'missing';
			$this->assertSame( 'meta', bbp_engagements_strategy( 'fallback' ) );

			$filtered = 'base';
			$this->assertSame( 'meta', bbp_engagements_strategy( 'fallback' ) );

			$filtered = 'term';
			$this->assertSame( 'term', bbp_engagements_strategy( 'fallback' ) );
		} finally {
			remove_filter( 'bbp_engagements_strategy', $filter, 10 );
		}
	}

	/**
	 * @ticket BBP3739
	 *
	 * @covers bbPress::setup_engagements
	 * @covers ::bbp_engagements_strategy
	 * @covers ::bbp_is_valid_engagements_strategy
	 */
	public function test_setup_engagements_uses_meta_for_an_invalid_strategy() {
		$engagements = bbpress()->engagements;
		$filtered    = null;
		$filter      = function() use ( &$filtered ) {
			return $filtered;
		};

		try {
			foreach ( array( array( 'meta' ), 'base', 'BASE' ) as $strategy ) {
				$this->set_option( '_bbp_engagements_strategy', $strategy );
				bbpress()->setup_engagements();
				$this->assertInstanceOf( 'BBP_User_Engagements_Meta', bbpress()->engagements );
			}

			$this->set_option( '_bbp_engagements_strategy', 'standalone' );
			bbpress()->setup_engagements();
			$this->assertInstanceOf( 'BBP_User_Engagements_Standalone', bbpress()->engagements );

			$this->set_option( '_bbp_engagements_strategy', 'meta' );
			add_filter( 'bbp_engagements_strategy', $filter );

			foreach ( array( new stdClass(), 'missing', 'base', 'BASE' ) as $filtered ) {
				bbpress()->setup_engagements();
				$this->assertInstanceOf( 'BBP_User_Engagements_Meta', bbpress()->engagements );
			}
		} finally {
			remove_filter( 'bbp_engagements_strategy', $filter );
			bbpress()->engagements = $engagements;
		}
	}

	/**
	 * @covers ::bbp_get_root_slug
	 * @covers ::bbp_maybe_get_root_slug
	 * @covers ::bbp_get_forum_slug
	 * @covers ::bbp_get_topic_slug
	 * @covers ::bbp_get_topic_tag_tax_slug
	 * @covers ::bbp_get_reply_slug
	 * @covers ::bbp_get_user_slug
	 * @covers ::bbp_get_view_slug
	 * @covers ::bbp_get_search_slug
	 */
	public function test_rooted_slugs_honor_the_include_root_option() {
		$this->set_option( '_bbp_root_slug', 'community' );
		$this->set_option( '_bbp_include_root', 1 );
		$this->set_option( '_bbp_forum_slug', 'board' );
		$this->set_option( '_bbp_topic_slug', 'discussion' );
		$this->set_option( '_bbp_topic_tag_slug', 'label' );
		$this->set_option( '_bbp_reply_slug', 'response' );
		$this->set_option( '_bbp_user_slug', 'member' );
		$this->set_option( '_bbp_view_slug', 'collection' );
		$this->set_option( '_bbp_search_slug', 'find' );

		$this->assertSame( 'community', bbp_get_root_slug() );
		$this->assertSame( 'community/', bbp_maybe_get_root_slug() );
		$this->assertSame( 'community/board', bbp_get_forum_slug() );
		$this->assertSame( 'community/discussion', bbp_get_topic_slug() );
		$this->assertSame( 'community/label', bbp_get_topic_tag_tax_slug() );
		$this->assertSame( 'community/response', bbp_get_reply_slug() );
		$this->assertSame( 'community/member', bbp_get_user_slug() );
		$this->assertSame( 'community/collection', bbp_get_view_slug() );
		$this->assertSame( 'community/find', bbp_get_search_slug() );

		update_option( '_bbp_include_root', 0 );
		$this->assertSame( '', bbp_maybe_get_root_slug() );
		$this->assertSame( 'board', bbp_get_forum_slug() );
	}

	/**
	 * @covers ::bbp_get_default_role
	 * @covers ::bbp_get_theme_package_id
	 * @covers ::bbp_show_on_root
	 * @covers ::bbp_get_topic_archive_slug
	 * @covers ::bbp_get_reply_archive_slug
	 * @covers ::bbp_get_user_favorites_slug
	 * @covers ::bbp_get_user_subscriptions_slug
	 * @covers ::bbp_get_user_engagements_slug
	 * @covers ::bbp_get_edit_slug
	 * @covers ::bbp_get_config_location
	 *
	 * @dataProvider unrooted_slug_provider
	 */
	public function test_unrooted_string_options( $function, $option, $value, $filter ) {
		$this->set_option( $option, $value );
		$this->assertSame( $value, $function() );

		$callback = function( $filtered ) {
			return 'filtered-' . $filtered;
		};
		add_filter( $filter, $callback );
		try {
			$this->assertSame( 'filtered-' . $value, $function() );
		} finally {
			remove_filter( $filter, $callback );
		}
	}

	public static function unrooted_slug_provider() {
		return array(
			'default role'       => array( 'bbp_get_default_role', '_bbp_default_role', 'bbp_test_role', 'bbp_get_default_role' ),
			'theme package'      => array( 'bbp_get_theme_package_id', '_bbp_theme_package_id', 'test-package', 'bbp_get_theme_package_id' ),
			'show on root'       => array( 'bbp_show_on_root', '_bbp_show_on_root', 'topics', 'bbp_show_on_root' ),
			'topic archive'      => array( 'bbp_get_topic_archive_slug', '_bbp_topic_archive_slug', 'all-topics', 'bbp_get_topic_archive_slug' ),
			'reply archive'      => array( 'bbp_get_reply_archive_slug', '_bbp_reply_archive_slug', 'all-replies', 'bbp_get_reply_archive_slug' ),
			'user favorites'     => array( 'bbp_get_user_favorites_slug', '_bbp_user_favs_slug', 'likes', 'bbp_get_user_favorites_slug' ),
			'user subscriptions' => array( 'bbp_get_user_subscriptions_slug', '_bbp_user_subs_slug', 'following', 'bbp_get_user_subscriptions_slug' ),
			'user engagements'   => array( 'bbp_get_user_engagements_slug', '_bbp_user_engs_slug', 'activity', 'bbp_get_user_engagements_slug' ),
			'edit'               => array( 'bbp_get_edit_slug', '_bbp_edit_slug', 'change', 'bbp_get_edit_slug' ),
			'legacy config'      => array( 'bbp_get_config_location', 'bb-config-location', '/tmp/bb-config.php', 'bbp_get_config_location' ),
		);
	}
}

/**
 * Standalone test strategy for legacy duck-typing compatibility.
 */
class BBP_User_Engagements_Standalone {
}
