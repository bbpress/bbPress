<?php

/**
 * Tests for core action registration.
 *
 * @group core
 * @group actions
 */
class BBP_Tests_Core_Actions extends BBP_UnitTestCase {

	/**
	 * Verify an action registration's hook, priority, and accepted arguments.
	 *
	 * @dataProvider action_registration_provider
	 */
	public function test_action_registration_matches_its_public_contract( $hook, $callback, $priority, $accepted_args ) {
		$callback_name = is_array( $callback )
			? implode( '::', $callback )
			: $callback;

		$this->assertSame(
			$priority,
			has_action( $hook, $callback ),
			$hook . ' should call ' . $callback_name . ' at the expected priority'
		);
		$this->assertSame(
			$accepted_args,
			$this->get_accepted_args( $hook, $callback, $priority ),
			$callback_name . ' should receive the expected arguments'
		);
	}

	/**
	 * Verify every source registration has a corresponding data set.
	 */
	public function test_action_registration_provider_covers_the_source_file() {
		$source = file_get_contents( BBP_PLUGIN_DIR . 'includes/core/actions.php' );

		preg_match_all( '/^add_action\(/m', $source, $matches );

		$this->assertSame(
			count( $matches[0] ),
			count( self::action_registration_provider() ),
			'Update action_registration_provider() when core/actions.php changes.'
		);
	}

	/**
	 * Get the accepted argument count for a registered callback.
	 *
	 * @param string         $hook     Hook name.
	 * @param string|array   $callback Callback.
	 * @param int            $priority Priority.
	 * @return int
	 */
	private function get_accepted_args( $hook, $callback, $priority ) {
		global $wp_filter;

		$callback_id = _wp_filter_build_unique_id( $hook, $callback, $priority );

		return $wp_filter[ $hook ]->callbacks[ $priority ][ $callback_id ]['accepted_args'];
	}

	/**
	 * Get every action registered by core/actions.php.
	 *
	 * @return array
	 */
	public static function action_registration_provider() {
		$registrations = array(
			array( 'plugins_loaded', 'bbp_loaded', 10, 1 ),
			array( 'init', 'bbp_init', 0, 1 ),
			array( 'parse_query', 'bbp_parse_query', 2, 1 ),
			array( 'generate_rewrite_rules', 'bbp_generate_rewrite_rules', 10, 1 ),
			array( 'after_setup_theme', 'bbp_after_setup_theme', 10, 1 ),
			array( 'setup_theme', 'bbp_setup_theme', 10, 1 ),
			array( 'set_current_user', 'bbp_setup_current_user', 10, 1 ),
			array( 'profile_update', 'bbp_profile_update', 10, 2 ),
			array( 'user_register', 'bbp_user_register', 10, 1 ),
			array( 'login_form_login', 'bbp_login_form_login', 10, 1 ),
			array( 'template_redirect', 'bbp_template_redirect', 8, 1 ),
			array( 'widgets_init', 'bbp_widgets_init', 10, 1 ),
			array( 'wp_roles_init', 'bbp_roles_init', 10, 1 ),
			array( 'wp_enqueue_scripts', 'bbp_enqueue_scripts', 10, 1 ),
			array( 'wp_head', 'bbp_head', 10, 1 ),
			array( 'wp_footer', 'bbp_footer', 10, 1 ),
			array( 'rest_api_init', 'bbp_rest_api_init', 10, 1 ),
			array( 'xmlrpc_call', 'bbp_xmlrpc_call', 10, 3 ),
			array( 'transition_post_status', 'bbp_transition_post_status', 10, 3 ),
			array( 'post_updated', 'bbp_post_updated', 10, 3 ),
			array( 'bbp_loaded', 'bbp_constants', 2, 1 ),
			array( 'bbp_loaded', 'bbp_boot_strap_globals', 4, 1 ),
			array( 'bbp_loaded', 'bbp_includes', 6, 1 ),
			array( 'bbp_loaded', 'bbp_setup_globals', 8, 1 ),
			array( 'bbp_loaded', 'bbp_setup_option_filters', 10, 1 ),
			array( 'bbp_loaded', 'bbp_setup_user_option_filters', 12, 1 ),
			array( 'bbp_loaded', 'bbp_pre_load_options', 14, 1 ),
			array( 'bbp_init', 'bbp_load_textdomain', 0, 1 ),
			array( 'bbp_init', 'bbp_register', 10, 1 ),
			array( 'bbp_init', 'bbp_add_rewrite_tags', 20, 1 ),
			array( 'bbp_init', 'bbp_add_rewrite_rules', 30, 1 ),
			array( 'bbp_init', 'bbp_add_permastructs', 40, 1 ),
			array( 'bbp_init', 'bbp_setup_engagements', 50, 1 ),
			array( 'bbp_init', 'bbp_ready', 999, 1 ),
			array( 'bbp_rest_api_init', 'bbp_register_rest_attachment_controller', 5, 1 ),
			array( 'bbp_xmlrpc_call', 'bbp_validate_xmlrpc_post', 10, 2 ),
			array( 'bbp_setup_theme', 'bbp_register_theme_packages', 2, 1 ),
			array( 'bbp_roles_init', 'bbp_add_forums_roles', 8, 1 ),
			array( 'bbp_setup_current_user', 'bbp_set_current_user_default_role', 10, 1 ),
			array( 'bbp_register', 'bbp_register_post_types', 2, 1 ),
			array( 'bbp_register', 'bbp_register_post_statuses', 4, 1 ),
			array( 'bbp_register', 'bbp_register_taxonomies', 6, 1 ),
			array( 'bbp_register', 'bbp_register_views', 8, 1 ),
			array( 'bbp_register', 'bbp_register_shortcodes', 10, 1 ),
			array( 'bbp_register', 'bbp_register_blocks', 10, 1 ),
			array( 'bbp_register', 'bbp_register_meta', 12, 1 ),
			array( 'bbp_init', 'bbp_reply_content_autoembed', 8, 1 ),
			array( 'bbp_init', 'bbp_topic_content_autoembed', 8, 1 ),
			array( 'wp_body_open', 'bbp_swap_no_js_body_class', 10, 1 ),
			array( 'bbp_footer', 'bbp_swap_no_js_body_class', 10, 1 ),
			array( 'bbp_ready', 'bbp_setup_akismet', 2, 1 ),
			array( 'bp_init', 'bbp_setup_buddypress', 0, 1 ),
			array( 'bbp_after_setup_theme', 'bbp_load_theme_functions', 10, 1 ),
			array( 'bbp_widgets_init', array( 'BBP_Login_Widget', 'register_widget' ), 10, 1 ),
			array( 'bbp_widgets_init', array( 'BBP_Views_Widget', 'register_widget' ), 10, 1 ),
			array( 'bbp_widgets_init', array( 'BBP_Search_Widget', 'register_widget' ), 10, 1 ),
			array( 'bbp_widgets_init', array( 'BBP_Forums_Widget', 'register_widget' ), 10, 1 ),
			array( 'bbp_widgets_init', array( 'BBP_Topics_Widget', 'register_widget' ), 10, 1 ),
			array( 'bbp_widgets_init', array( 'BBP_Replies_Widget', 'register_widget' ), 10, 1 ),
			array( 'bbp_widgets_init', array( 'BBP_Stats_Widget', 'register_widget' ), 10, 1 ),
			array( 'bbp_template_notices', 'bbp_template_notices', 20, 1 ),
			array( 'bbp_template_notices', 'bbp_login_notices', 10, 1 ),
			array( 'bbp_template_notices', 'bbp_topic_notices', 10, 1 ),
			array( 'bbp_template_notices', 'bbp_notice_edit_user_success', 10, 1 ),
			array( 'bbp_template_notices', 'bbp_notice_edit_user_pending_email', 10, 1 ),
			array( 'bbp_template_notices', 'bbp_notice_edit_user_is_super_admin', 2, 1 ),
			array( 'pre_get_posts', 'bbp_pre_get_posts_normalize_forum_visibility', 4, 1 ),
			array( 'wp_trash_post', 'bbp_trash_forum', 10, 1 ),
			array( 'trash_post', 'bbp_trash_forum', 10, 1 ),
			array( 'untrash_post', 'bbp_untrash_forum', 10, 1 ),
			array( 'before_delete_post', 'bbp_delete_forum', 10, 1 ),
			array( 'trashed_post', 'bbp_trashed_forum', 10, 1 ),
			array( 'untrashed_post', 'bbp_untrashed_forum', 10, 1 ),
			array( 'deleted_post', 'bbp_deleted_forum', 10, 2 ),
			array( 'bbp_delete_forum', 'bbp_delete_forum_topics', 10, 1 ),
			array( 'bbp_trash_forum', 'bbp_trash_forum_topics', 10, 1 ),
			array( 'bbp_untrash_forum', 'bbp_untrash_forum_topics', 10, 1 ),
			array( 'bbp_new_forum', 'bbp_update_forum', 10, 1 ),
			array( 'bbp_edit_forum', 'bbp_update_forum', 10, 1 ),
			array( 'bbp_new_forum_post_extras', 'bbp_save_forum_extras', 2, 1 ),
			array( 'bbp_edit_forum_post_extras', 'bbp_save_forum_extras', 2, 1 ),
			array( 'bbp_forum_attributes_metabox_save', 'bbp_save_forum_extras', 2, 1 ),
			array( 'bbp_new_reply', 'bbp_update_reply', 10, 7 ),
			array( 'bbp_edit_reply', 'bbp_update_reply', 10, 7 ),
			array( 'bbp_new_reply', 'bbp_update_reply_subscription', 10, 5 ),
			array( 'bbp_edit_reply', 'bbp_update_reply_subscription', 10, 5 ),
			array( 'wp_trash_post', 'bbp_trash_reply', 10, 1 ),
			array( 'trash_post', 'bbp_trash_reply', 10, 1 ),
			array( 'untrash_post', 'bbp_untrash_reply', 10, 1 ),
			array( 'before_delete_post', 'bbp_delete_reply', 10, 1 ),
			array( 'trashed_post', 'bbp_trashed_reply', 10, 1 ),
			array( 'untrashed_post', 'bbp_untrashed_reply', 10, 1 ),
			array( 'deleted_post', 'bbp_deleted_reply', 10, 1 ),
			array( 'bbp_new_topic', 'bbp_update_topic', 10, 5 ),
			array( 'bbp_edit_topic', 'bbp_update_topic', 10, 5 ),
			array( 'bbp_new_topic', 'bbp_update_topic_subscription', 10, 4 ),
			array( 'bbp_edit_topic', 'bbp_update_topic_subscription', 10, 4 ),
			array( 'bbp_merged_topic', 'bbp_merge_topic_count', 1, 3 ),
			array( 'bbp_post_split_topic', 'bbp_split_topic_count', 1, 3 ),
			array( 'bbp_post_move_reply', 'bbp_move_reply_count', 1, 3 ),
			array( 'wp_trash_post', 'bbp_trash_topic', 10, 1 ),
			array( 'trash_post', 'bbp_trash_topic', 10, 1 ),
			array( 'untrash_post', 'bbp_untrash_topic', 10, 1 ),
			array( 'before_delete_post', 'bbp_delete_topic', 10, 1 ),
			array( 'trashed_post', 'bbp_trashed_topic', 10, 1 ),
			array( 'untrashed_post', 'bbp_untrashed_topic', 10, 1 ),
			array( 'deleted_post', 'bbp_deleted_topic', 10, 1 ),
			array( 'bbp_spam_topic', 'bbp_remove_topic_from_all_favorites', 10, 1 ),
			array( 'bbp_trash_topic', 'bbp_remove_topic_from_all_favorites', 10, 1 ),
			array( 'bbp_delete_topic', 'bbp_remove_topic_from_all_favorites', 10, 1 ),
			array( 'bbp_spam_topic', 'bbp_remove_topic_from_all_subscriptions', 10, 1 ),
			array( 'bbp_trash_topic', 'bbp_remove_topic_from_all_subscriptions', 10, 1 ),
			array( 'bbp_delete_topic', 'bbp_remove_topic_from_all_subscriptions', 10, 1 ),
			array( 'bbp_trash_forum', 'bbp_remove_forum_from_all_subscriptions', 10, 1 ),
			array( 'bbp_delete_forum', 'bbp_remove_forum_from_all_subscriptions', 10, 1 ),
			array( 'bbp_new_reply', 'bbp_notify_topic_subscribers', 11, 5 ),
			array( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11, 4 ),
			array( 'bbp_new_reply', 'bbp_defer_subscription_notification', 12, 1 ),
			array( 'bbp_new_topic', 'bbp_defer_subscription_notification', 12, 1 ),
			array( 'bbp_transition_post_status', 'bbp_notify_forum_subscribers_on_topic_publication', 11, 3 ),
			array( 'bbp_transition_post_status', 'bbp_notify_topic_subscribers_on_reply_publication', 11, 3 ),
			array( 'bbp_deferred_reply_published', 'bbp_notify_topic_subscribers', 11, 5 ),
			array( 'bbp_deferred_topic_published', 'bbp_notify_forum_subscribers', 11, 4 ),
			array( 'bbp_stick_topic', 'bbp_unstick_topic', 10, 1 ),
			array( 'bbp_unapprove_topic', 'bbp_unstick_topic', 10, 1 ),
			array( 'bbp_spam_topic', 'bbp_unstick_topic', 10, 1 ),
			array( 'bbp_trash_topic', 'bbp_unstick_topic', 10, 1 ),
			array( 'bbp_delete_topic', 'bbp_unstick_topic', 10, 1 ),
			array( 'bbp_trashed_topic', 'bbp_update_topic_walker', 10, 1 ),
			array( 'bbp_untrashed_topic', 'bbp_update_topic_walker', 10, 1 ),
			array( 'bbp_deleted_topic', 'bbp_update_topic_walker', 10, 1 ),
			array( 'bbp_spammed_topic', 'bbp_update_topic_walker', 10, 1 ),
			array( 'bbp_unspammed_topic', 'bbp_update_topic_walker', 10, 1 ),
			array( 'bbp_approved_topic', 'bbp_update_topic_walker', 10, 1 ),
			array( 'bbp_unapproved_topic', 'bbp_update_topic_walker', 10, 1 ),
			array( 'bbp_trashed_reply', 'bbp_update_reply_walker', 10, 1 ),
			array( 'bbp_untrashed_reply', 'bbp_update_reply_walker', 10, 1 ),
			array( 'bbp_deleted_reply', 'bbp_update_reply_walker', 10, 1 ),
			array( 'bbp_spammed_reply', 'bbp_update_reply_walker', 10, 1 ),
			array( 'bbp_unspammed_reply', 'bbp_update_reply_walker', 10, 1 ),
			array( 'bbp_approved_reply', 'bbp_update_reply_walker', 10, 1 ),
			array( 'bbp_unapproved_reply', 'bbp_update_reply_walker', 10, 1 ),
			array( 'bbp_transition_post_status', 'bbp_update_forum_visibility_on_transition_post_status', 5, 3 ),
			array( 'bbp_transition_post_status', 'bbp_update_counts_on_transition_post_status', 10, 3 ),
			array( 'bbp_transition_post_status', 'bbp_update_forum_subforum_count_on_transition_post_status', 10, 3 ),
			array( 'bbp_deleted_forum', 'bbp_reparent_forum_subforums', 9, 2 ),
			array( 'bbp_deleted_forum', 'bbp_update_parent_forum_subforum_count', 10, 2 ),
			array( 'bbp_post_updated', 'bbp_update_forum_subforum_counts_on_post_updated', 10, 3 ),
			array( 'bbp_deleted_topic', 'bbp_decrease_user_topic_count', 10, 1 ),
			array( 'bbp_deleted_reply', 'bbp_decrease_user_reply_count', 10, 1 ),
			array( 'bbp_post_updated', 'bbp_update_counts_on_post_author_change', 10, 3 ),
			array( 'bbp_post_updated', 'bbp_recalculate_engagements_on_post_author_change', 20, 3 ),
			array( 'delete_user', 'bbp_update_counts_on_user_reassignment', 10, 2 ),
			array( 'deleted_user', 'bbp_update_counts_on_user_reassignment', 10, 2 ),
			array( 'bbp_trash_topic', 'bbp_trash_topic_replies', 10, 1 ),
			array( 'bbp_untrash_topic', 'bbp_untrash_topic_replies', 10, 1 ),
			array( 'bbp_delete_topic', 'bbp_delete_topic_replies', 10, 1 ),
			array( 'bbp_spam_topic', 'bbp_spam_topic_replies', 10, 1 ),
			array( 'bbp_unspam_topic', 'bbp_unspam_topic_replies', 10, 1 ),
			array( 'bbp_new_topic', 'bbp_update_topic_engagements', 20, 1 ),
			array( 'bbp_new_reply', 'bbp_update_topic_engagements', 20, 1 ),
			array( 'bbp_new_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_new_topic', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_insert_topic', 'bbp_update_topic_engagements', 20, 1 ),
			array( 'bbp_insert_reply', 'bbp_update_topic_engagements', 20, 1 ),
			array( 'bbp_insert_topic', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_insert_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_trashed_reply', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_untrashed_reply', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_spammed_reply', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_unspammed_reply', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_approved_reply', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_unapproved_reply', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_deleted_reply', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_trashed_topic', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_untrashed_topic', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_spammed_topic', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_unspammed_topic', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_approved_topic', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_unapproved_topic', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_deleted_topic', 'bbp_recalculate_topic_engagements', 10, 1 ),
			array( 'bbp_trashed_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_untrashed_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_spammed_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_unspammed_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_approved_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_unapproved_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_deleted_reply', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_trashed_topic', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_untrashed_topic', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_spammed_topic', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_unspammed_topic', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_approved_topic', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'bbp_unapproved_topic', 'bbp_update_topic_voice_count', 30, 1 ),
			array( 'make_ham_user', 'bbp_make_ham_user', 10, 1 ),
			array( 'make_spam_user', 'bbp_make_spam_user', 10, 1 ),
			array( 'bbp_profile_update', 'bbp_profile_update_role', 10, 1 ),
			array( 'bbp_user_edit_after', 'bbp_user_edit_after', 10, 1 ),
			array( 'clean_post_cache', 'bbp_clean_post_cache', 10, 2 ),
			array( 'clean_user_cache', 'bbp_clean_user_count_cache', 10, 1 ),
			array( 'added_user_meta', 'bbp_clean_user_count_cache_on_meta_change', 10, 3 ),
			array( 'updated_user_meta', 'bbp_clean_user_count_cache_on_meta_change', 10, 3 ),
			array( 'deleted_user_meta', 'bbp_clean_user_count_cache_on_meta_change', 10, 3 ),
			array( 'added_existing_user', 'bbp_user_add_role_on_register', 10, 1 ),
			array( 'bbp_user_register', 'bbp_user_add_role_on_register', 10, 1 ),
			array( 'invite_user', 'bbp_user_add_role_on_invite', 10, 3 ),
			array( 'wpmu_activate_user', 'bbp_user_add_role_on_activate', 10, 3 ),
			array( 'bbp_template_redirect', 'bbp_forum_enforce_blocked', 1, 1 ),
			array( 'bbp_template_redirect', 'bbp_forum_enforce_hidden', 1, 1 ),
			array( 'bbp_template_redirect', 'bbp_forum_enforce_private', 1, 1 ),
			array( 'bbp_template_redirect', 'bbp_post_request', 10, 1 ),
			array( 'bbp_template_redirect', 'bbp_get_request', 10, 1 ),
			array( 'bbp_template_redirect', 'bbp_check_user_edit', 10, 1 ),
			array( 'bbp_template_redirect', 'bbp_check_forum_edit', 10, 1 ),
			array( 'bbp_template_redirect', 'bbp_check_topic_edit', 10, 1 ),
			array( 'bbp_template_redirect', 'bbp_check_reply_edit', 10, 1 ),
			array( 'bbp_template_redirect', 'bbp_check_topic_tag_edit', 10, 1 ),
			array( 'bbp_template_redirect', 'bbp_remove_adjacent_posts', 10, 1 ),
			array( 'bbp_post_request', 'bbp_do_ajax', 1, 1 ),
			array( 'bbp_post_request', 'bbp_edit_topic_tag_handler', 1, 1 ),
			array( 'bbp_post_request', 'bbp_edit_user_handler', 1, 1 ),
			array( 'bbp_post_request', 'bbp_edit_forum_handler', 1, 1 ),
			array( 'bbp_post_request', 'bbp_edit_reply_handler', 1, 1 ),
			array( 'bbp_post_request', 'bbp_edit_topic_handler', 1, 1 ),
			array( 'bbp_post_request', 'bbp_merge_topic_handler', 1, 1 ),
			array( 'bbp_post_request', 'bbp_split_topic_handler', 1, 1 ),
			array( 'bbp_post_request', 'bbp_move_reply_handler', 1, 1 ),
			array( 'bbp_post_request', 'bbp_new_forum_handler', 10, 1 ),
			array( 'bbp_post_request', 'bbp_new_reply_handler', 10, 1 ),
			array( 'bbp_post_request', 'bbp_new_topic_handler', 10, 1 ),
			array( 'bbp_get_request', 'bbp_toggle_topic_handler', 1, 1 ),
			array( 'bbp_get_request', 'bbp_toggle_reply_handler', 1, 1 ),
			array( 'bbp_get_request', 'bbp_favorites_handler', 1, 1 ),
			array( 'bbp_get_request', 'bbp_subscriptions_handler', 1, 1 ),
			array( 'bbp_get_request', 'bbp_user_email_change_handler', 1, 1 ),
			array( 'bbp_get_request', 'bbp_search_results_redirect', 10, 1 ),
			array( 'bbp_login_form_login', 'bbp_user_maybe_convert_pass', 10, 1 ),
		);
		$datasets = array();

		foreach ( $registrations as $registration ) {
			$callback_name = is_array( $registration[1] )
				? implode( '::', $registration[1] )
				: $registration[1];

			$datasets[ $registration[0] . ' ' . $callback_name ] = $registration;
		}

		return $datasets;
	}
}

