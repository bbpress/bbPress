<?php

/**
 * Tests for core action registration.
 *
 * @group core
 * @group actions
 */
class BBP_Tests_Core_Actions extends BBP_UnitTestCase {

	/**
	 * Get the accepted argument count for a registered callback.
	 *
	 * @param string $hook     Hook name.
	 * @param string $callback Callback.
	 * @param int    $priority Priority.
	 * @return int
	 */
	private function get_accepted_args( $hook, $callback, $priority ) {
		global $wp_filter;

		$callback_id = _wp_filter_build_unique_id( $hook, $callback, $priority );

		return $wp_filter[ $hook ]->callbacks[ $priority ][ $callback_id ]['accepted_args'];
	}

	/**
	 * Assert action registrations and their public callback contracts.
	 *
	 * @param array $actions Action registrations.
	 */
	private function assert_actions( $actions ) {
		foreach ( $actions as $action ) {
			$this->assertSame( $action[2], has_action( $action[0], $action[1] ), $action[0] . ' should call ' . $action[1] );
			$this->assertSame( $action[3], $this->get_accepted_args( $action[0], $action[1], $action[2] ), $action[1] . ' should receive the expected arguments' );
		}
	}

	/**
	 * Verify the primary WordPress-to-bbPress action bridges.
	 */
	public function test_wordpress_actions_bridge_to_bbp_sub_actions_at_expected_priorities() {
		$this->assert_actions( array(
			array( 'plugins_loaded',         'bbp_loaded',                 10, 1 ),
			array( 'init',                   'bbp_init',                    0, 1 ),
			array( 'parse_query',            'bbp_parse_query',             2, 1 ),
			array( 'generate_rewrite_rules', 'bbp_generate_rewrite_rules', 10, 1 ),
			array( 'after_setup_theme',      'bbp_after_setup_theme',      10, 1 ),
			array( 'setup_theme',            'bbp_setup_theme',            10, 1 ),
			array( 'set_current_user',       'bbp_setup_current_user',     10, 1 ),
			array( 'profile_update',         'bbp_profile_update',         10, 2 ),
			array( 'user_register',          'bbp_user_register',          10, 1 ),
			array( 'login_form_login',       'bbp_login_form_login',       10, 1 ),
			array( 'template_redirect',      'bbp_template_redirect',       8, 1 ),
			array( 'widgets_init',           'bbp_widgets_init',           10, 1 ),
			array( 'wp_roles_init',          'bbp_roles_init',             10, 1 ),
			array( 'wp_enqueue_scripts',     'bbp_enqueue_scripts',        10, 1 ),
			array( 'wp_head',                'bbp_head',                   10, 1 ),
			array( 'wp_footer',              'bbp_footer',                 10, 1 ),
			array( 'rest_api_init',          'bbp_rest_api_init',          10, 1 ),
			array( 'xmlrpc_call',            'bbp_xmlrpc_call',            10, 3 ),
			array( 'transition_post_status', 'bbp_transition_post_status', 10, 3 ),
			array( 'post_updated',           'bbp_post_updated',           10, 3 ),
		) );
	}

	/**
	 * Verify the documented bbPress loader order.
	 */
	public function test_bbp_loader_actions_run_at_expected_priorities() {
		$this->assert_actions( array(
			array( 'bbp_loaded', 'bbp_constants',                  2, 1 ),
			array( 'bbp_loaded', 'bbp_boot_strap_globals',         4, 1 ),
			array( 'bbp_loaded', 'bbp_includes',                   6, 1 ),
			array( 'bbp_loaded', 'bbp_setup_globals',              8, 1 ),
			array( 'bbp_loaded', 'bbp_setup_option_filters',      10, 1 ),
			array( 'bbp_loaded', 'bbp_setup_user_option_filters', 12, 1 ),
			array( 'bbp_loaded', 'bbp_pre_load_options',          14, 1 ),
			array( 'bbp_init',   'bbp_load_textdomain',            0, 1 ),
			array( 'bbp_init',   'bbp_reply_content_autoembed',    8, 1 ),
			array( 'bbp_init',   'bbp_topic_content_autoembed',    8, 1 ),
			array( 'bbp_init',   'bbp_register',                  10, 1 ),
			array( 'bbp_init',   'bbp_add_rewrite_tags',          20, 1 ),
			array( 'bbp_init',   'bbp_add_rewrite_rules',         30, 1 ),
			array( 'bbp_init',   'bbp_add_permastructs',          40, 1 ),
			array( 'bbp_init',   'bbp_setup_engagements',         50, 1 ),
			array( 'bbp_init',   'bbp_ready',                    999, 1 ),
		) );
	}

	/**
	 * Verify the object-registration order within bbp_register.
	 */
	public function test_bbp_register_actions_run_at_expected_priorities() {
		$this->assert_actions( array(
			array( 'bbp_register', 'bbp_register_post_types',     2, 1 ),
			array( 'bbp_register', 'bbp_register_post_statuses',  4, 1 ),
			array( 'bbp_register', 'bbp_register_taxonomies',     6, 1 ),
			array( 'bbp_register', 'bbp_register_views',          8, 1 ),
			array( 'bbp_register', 'bbp_register_shortcodes',    10, 1 ),
			array( 'bbp_register', 'bbp_register_blocks',        10, 1 ),
			array( 'bbp_register', 'bbp_register_meta',          12, 1 ),
		) );
	}

	/**
	 * Verify secondary integration bridges.
	 */
	public function test_secondary_bbp_action_bridges_run_at_expected_priorities() {
		$this->assert_actions( array(
			array( 'bbp_rest_api_init',      'bbp_register_rest_attachment_controller', 5,  1 ),
			array( 'bbp_xmlrpc_call',        'bbp_validate_xmlrpc_post',               10, 2 ),
			array( 'bbp_setup_current_user', 'bbp_set_current_user_default_role',      10, 1 ),
			array( 'bbp_roles_init',         'bbp_add_forums_roles',                    8,  1 ),
			array( 'bbp_setup_theme',        'bbp_register_theme_packages',             2,  1 ),
			array( 'bbp_ready',              'bbp_setup_akismet',                       2,  1 ),
			array( 'bbp_after_setup_theme',  'bbp_load_theme_functions',               10, 1 ),
			array( 'bp_init',                'bbp_setup_buddypress',                    0,  1 ),
		) );
	}

	/**
	 * Verify subscription notifications run before deferral.
	 */
	public function test_subscription_notifications_run_before_deferral() {
		$this->assert_actions( array(
			array( 'bbp_new_reply',                'bbp_notify_topic_subscribers',                      11, 5 ),
			array( 'bbp_new_reply',                'bbp_defer_subscription_notification',              12, 1 ),
			array( 'bbp_new_topic',                'bbp_notify_forum_subscribers',                      11, 4 ),
			array( 'bbp_new_topic',                'bbp_defer_subscription_notification',              12, 1 ),
			array( 'bbp_transition_post_status',   'bbp_notify_forum_subscribers_on_topic_publication', 11, 3 ),
			array( 'bbp_transition_post_status',   'bbp_notify_topic_subscribers_on_reply_publication', 11, 3 ),
			array( 'bbp_deferred_reply_published', 'bbp_notify_topic_subscribers',                      11, 5 ),
			array( 'bbp_deferred_topic_published', 'bbp_notify_forum_subscribers',                      11, 4 ),
		) );
	}

	/**
	 * Verify count and engagement updates retain their intended order.
	 */
	public function test_counts_and_engagements_update_at_expected_priorities() {
		$this->assert_actions( array(
			array( 'bbp_transition_post_status', 'bbp_update_forum_visibility_on_transition_post_status', 5,  3 ),
			array( 'bbp_transition_post_status', 'bbp_update_counts_on_transition_post_status',            10, 3 ),
			array( 'bbp_transition_post_status', 'bbp_update_forum_subforum_count_on_transition_post_status', 10, 3 ),
			array( 'bbp_deleted_forum',         'bbp_reparent_forum_subforums',                             9,  2 ),
			array( 'bbp_deleted_forum',         'bbp_update_parent_forum_subforum_count',                  10, 2 ),
			array( 'bbp_post_updated',          'bbp_update_forum_subforum_counts_on_post_updated',        10, 3 ),
			array( 'bbp_post_updated',          'bbp_update_counts_on_post_author_change',                  10, 3 ),
			array( 'bbp_post_updated',          'bbp_recalculate_engagements_on_post_author_change',        20, 3 ),
			array( 'bbp_new_topic',             'bbp_update_topic_engagements',                             20, 1 ),
			array( 'bbp_new_topic',             'bbp_update_topic_voice_count',                             30, 1 ),
			array( 'bbp_new_reply',             'bbp_update_topic_engagements',                             20, 1 ),
			array( 'bbp_new_reply',             'bbp_update_topic_voice_count',                             30, 1 ),
		) );
	}

	/**
	 * Verify content updates receive the complete mutation context.
	 */
	public function test_content_update_actions_receive_expected_arguments() {
		$this->assert_actions( array(
			array( 'bbp_new_reply',        'bbp_update_reply',              10, 7 ),
			array( 'bbp_edit_reply',       'bbp_update_reply',              10, 7 ),
			array( 'bbp_new_reply',        'bbp_update_reply_subscription', 10, 5 ),
			array( 'bbp_edit_reply',       'bbp_update_reply_subscription', 10, 5 ),
			array( 'bbp_new_topic',        'bbp_update_topic',              10, 5 ),
			array( 'bbp_edit_topic',       'bbp_update_topic',              10, 5 ),
			array( 'bbp_new_topic',        'bbp_update_topic_subscription', 10, 4 ),
			array( 'bbp_edit_topic',       'bbp_update_topic_subscription', 10, 4 ),
			array( 'bbp_merged_topic',     'bbp_merge_topic_count',          1, 3 ),
			array( 'bbp_post_split_topic', 'bbp_split_topic_count',          1, 3 ),
			array( 'bbp_post_move_reply',  'bbp_move_reply_count',           1, 3 ),
		) );
	}

	/**
	 * Verify cache invalidation receives the identifiers it needs.
	 */
	public function test_cache_invalidation_actions_receive_expected_arguments() {
		$this->assert_actions( array(
			array( 'clean_post_cache',  'bbp_clean_post_cache',                      10, 2 ),
			array( 'clean_user_cache',  'bbp_clean_user_count_cache',               10, 1 ),
			array( 'added_user_meta',   'bbp_clean_user_count_cache_on_meta_change', 10, 3 ),
			array( 'updated_user_meta', 'bbp_clean_user_count_cache_on_meta_change', 10, 3 ),
			array( 'deleted_user_meta', 'bbp_clean_user_count_cache_on_meta_change', 10, 3 ),
		) );
	}

	/**
	 * Verify user-role callbacks receive all registration data.
	 */
	public function test_user_role_actions_receive_expected_arguments() {
		$this->assert_actions( array(
			array( 'added_existing_user', 'bbp_user_add_role_on_register', 10, 1 ),
			array( 'bbp_user_register',   'bbp_user_add_role_on_register', 10, 1 ),
			array( 'invite_user',         'bbp_user_add_role_on_invite',   10, 3 ),
			array( 'wpmu_activate_user',  'bbp_user_add_role_on_activate', 10, 3 ),
		) );
	}

	/**
	 * Verify access enforcement runs before request handlers.
	 */
	public function test_template_redirect_enforces_forum_access_before_request_handlers() {
		$this->assert_actions( array(
			array( 'bbp_template_redirect', 'bbp_forum_enforce_blocked', 1,  1 ),
			array( 'bbp_template_redirect', 'bbp_forum_enforce_hidden',  1,  1 ),
			array( 'bbp_template_redirect', 'bbp_forum_enforce_private', 1,  1 ),
			array( 'bbp_template_redirect', 'bbp_post_request',          10, 1 ),
			array( 'bbp_template_redirect', 'bbp_get_request',           10, 1 ),
		) );
	}

	/**
	 * Verify POST and GET request handlers retain their routing priorities.
	 */
	public function test_request_handlers_run_at_expected_priorities() {
		$this->assert_actions( array(
			array( 'bbp_post_request', 'bbp_do_ajax',                       1,  1 ),
			array( 'bbp_post_request', 'bbp_edit_topic_tag_handler',        1,  1 ),
			array( 'bbp_post_request', 'bbp_edit_user_handler',             1,  1 ),
			array( 'bbp_post_request', 'bbp_edit_forum_handler',            1,  1 ),
			array( 'bbp_post_request', 'bbp_edit_reply_handler',            1,  1 ),
			array( 'bbp_post_request', 'bbp_edit_topic_handler',            1,  1 ),
			array( 'bbp_post_request', 'bbp_merge_topic_handler',           1,  1 ),
			array( 'bbp_post_request', 'bbp_split_topic_handler',           1,  1 ),
			array( 'bbp_post_request', 'bbp_move_reply_handler',            1,  1 ),
			array( 'bbp_post_request', 'bbp_new_forum_handler',            10, 1 ),
			array( 'bbp_post_request', 'bbp_new_reply_handler',            10, 1 ),
			array( 'bbp_post_request', 'bbp_new_topic_handler',            10, 1 ),
			array( 'bbp_get_request',  'bbp_toggle_topic_handler',          1,  1 ),
			array( 'bbp_get_request',  'bbp_toggle_reply_handler',          1,  1 ),
			array( 'bbp_get_request',  'bbp_favorites_handler',             1,  1 ),
			array( 'bbp_get_request',  'bbp_subscriptions_handler',         1,  1 ),
			array( 'bbp_get_request',  'bbp_user_email_change_handler',     1,  1 ),
			array( 'bbp_get_request',  'bbp_search_results_redirect',      10, 1 ),
		) );
	}
}
