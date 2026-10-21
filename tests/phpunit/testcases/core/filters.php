<?php

/**
 * Tests for the core filters.
 *
 * @group core
 * @group filters
 */
class BBP_Tests_Core_Filters extends BBP_UnitTestCase {

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
	 * Assert filter registrations and their public callback contracts.
	 *
	 * @param array $filters Filter registrations.
	 */
	private function assert_filters( $filters ) {
		foreach ( $filters as $filter ) {
			$this->assertSame( $filter[2], has_filter( $filter[0], $filter[1] ), $filter[0] . ' should call ' . $filter[1] );
			$this->assertSame( $filter[3], $this->get_accepted_args( $filter[0], $filter[1], $filter[2] ), $filter[1] . ' should receive the expected arguments' );
		}
	}

	/**
	 * Verify the primary WordPress-to-bbPress filter bridges.
	 */
	public function test_wordpress_filters_bridge_to_bbp_sub_filters() {
		$this->assert_filters( array(
			array( 'request',            'bbp_request',            10, 1 ),
			array( 'template_include',   'bbp_template_include',   10, 1 ),
			array( 'wp_mail',            'bbp_mail',               10, 3 ),
			array( 'wp_title',           'bbp_title',              10, 3 ),
			array( 'body_class',         'bbp_body_class',         10, 2 ),
			array( 'map_meta_cap',       'bbp_map_meta_caps',      10, 4 ),
			array( 'allowed_themes',     'bbp_allowed_themes',     10, 1 ),
			array( 'redirect_canonical', 'bbp_redirect_canonical', 10, 2 ),
			array( 'login_redirect',     'bbp_redirect_login',      2, 3 ),
			array( 'logout_url',         'bbp_logout_url',          2, 2 ),
			array( 'plugin_locale',      'bbp_plugin_locale',      10, 2 ),
		) );
	}

	/**
	 * Verify empty route query variables are removed before other request work.
	 */
	public function test_request_filters_normalize_route_query_vars_first() {
		$this->assert_filters( array(
			array( 'bbp_request', 'bbp_request_remove_empty_query_vars', 5,  1 ),
			array( 'bbp_request', 'bbp_request_feed_trap',               10, 1 ),
		) );
	}

	/**
	 * Verify theme-support template selection runs before theme compatibility.
	 */
	public function test_template_filters_run_in_selection_order() {
		$this->assert_filters( array(
			array( 'bbp_template_include', 'bbp_template_include_theme_supports', 2, 1 ),
			array( 'bbp_template_include', 'bbp_template_include_theme_compat',   4, 2 ),
		) );
	}

	/**
	 * Verify topic and reply display content use the same ordered pipeline.
	 */
	public function test_display_content_filters_run_in_order() {
		foreach ( array( 'bbp_get_topic_content', 'bbp_get_reply_content' ) as $hook ) {
			$this->assert_filters( array(
				array( $hook, 'wptexturize',        6, 1 ),
				array( $hook, 'convert_chars',      8, 1 ),
				array( $hook, 'capital_P_dangit',  10, 1 ),
				array( $hook, 'convert_smilies',   20, 1 ),
				array( $hook, 'force_balance_tags', 30, 1 ),
				array( $hook, 'bbp_make_clickable', 40, 1 ),
				array( $hook, 'wpautop',            50, 1 ),
				array( $hook, 'bbp_rel_ugc',        60, 1 ),
			) );
		}
	}

	/**
	 * Verify numeric counts are clamped before they are formatted.
	 */
	public function test_count_filters_clamp_values_before_formatting() {
		$hooks = array(
			'bbp_get_user_topic_count',
			'bbp_get_user_reply_count',
			'bbp_get_user_post_count',
			'bbp_get_forum_subforum_count',
			'bbp_get_forum_topic_count',
			'bbp_get_forum_reply_count',
			'bbp_get_forum_post_count',
			'bbp_get_topic_voice_count',
			'bbp_get_topic_reply_count',
			'bbp_get_topic_post_count',
			'bbp_get_topic_revision_count',
			'bbp_get_reply_revision_count',
			'bbp_get_forum_topic_count_hidden',
			'bbp_get_topic_reply_count_hidden',
		);

		foreach ( $hooks as $hook ) {
			$this->assert_filters( array(
				array( $hook, 'bbp_number_not_negative', 8,  1 ),
				array( $hook, 'bbp_number_format',       10, 1 ),
			) );
		}
	}

	/**
	 * Verify capability mapping keeps XML-RPC last.
	 */
	public function test_meta_cap_filters_run_in_order() {
		$this->assert_filters( array(
			array( 'bbp_map_meta_caps', 'bbp_map_primary_meta_caps',   10,  4 ),
			array( 'bbp_map_meta_caps', 'bbp_map_forum_meta_caps',     10,  4 ),
			array( 'bbp_map_meta_caps', 'bbp_map_topic_meta_caps',     10,  4 ),
			array( 'bbp_map_meta_caps', 'bbp_map_topic_tag_meta_caps', 10,  4 ),
			array( 'bbp_map_meta_caps', 'bbp_map_reply_meta_caps',     10,  4 ),
			array( 'bbp_map_meta_caps', 'bbp_map_xmlrpc_meta_caps',   999,  4 ),
		) );
	}

	/**
	 * Verify anonymous author fields retain their sanitization callbacks.
	 */
	public function test_anonymous_author_sanitization_filters_are_registered() {
		$this->assert_filters( array(
			array( 'bbp_pre_anonymous_post_author_name', 'trim',                10, 1 ),
			array( 'bbp_pre_anonymous_post_author_name', 'sanitize_text_field', 10, 1 ),
			array( 'bbp_pre_anonymous_post_author_name', 'wp_filter_kses',      10, 1 ),
			array( 'bbp_pre_anonymous_post_author_name', '_wp_specialchars',    30, 1 ),
			array( 'bbp_pre_anonymous_post_author_email', 'trim',               10, 1 ),
			array( 'bbp_pre_anonymous_post_author_email', 'sanitize_email',     10, 1 ),
			array( 'bbp_pre_anonymous_post_author_email', 'wp_filter_kses',     10, 1 ),
			array( 'bbp_pre_anonymous_post_author_website', 'trim',             10, 1 ),
			array( 'bbp_pre_anonymous_post_author_website', 'wp_strip_all_tags', 10, 1 ),
			array( 'bbp_pre_anonymous_post_author_website', 'esc_url_raw',       10, 1 ),
			array( 'bbp_pre_anonymous_post_author_website', 'wp_filter_kses',    10, 1 ),
		) );
	}

	/**
	 * Verify security and privacy filters receive the context they need.
	 */
	public function test_security_and_privacy_filters_receive_expected_arguments() {
		$this->assert_filters( array(
			array( 'pre_do_shortcode_tag',      'bbp_pre_do_shortcode_tag',               10, 4 ),
			array( 'bbp_pre_do_shortcode_tag',  'bbp_prevent_content_shortcodes',         10, 4 ),
			array( 'rest_user_query',           'bbp_exclude_forum_posts_from_user_query', 10, 2 ),
			array( 'rest_request_before_callbacks', 'bbp_filter_rest_user_discovery',       10, 3 ),
			array( 'wp_insert_post_data',       'bbp_filter_admin_forum_post_data',        20, 2 ),
			array( 'wp_insert_post_data',       'bbp_fix_post_author',                     30, 2 ),
			array( 'wp_untrash_post_status',    'bbp_fix_untrash_post_status',             10, 3 ),
			array( 'bbp_redirect_canonical',    'bbp_do_not_redirect_restricted_posts',    10, 2 ),
			array( 'bbp_redirect_canonical',    'bbp_do_not_redirect_restricted_attachments', 10, 2 ),
			array( 'oembed_request_post_id',    'bbp_filter_oembed_request_attachment_id', 10, 1 ),
			array( 'bbp_get_forum_freshness_link', 'bbp_suppress_private_forum_meta',      10, 6 ),
			array( 'bbp_get_author_link',       'bbp_suppress_private_author_link',         10, 3 ),
			array( 'bbp_get_topic_author_link', 'bbp_suppress_private_author_link',         10, 3 ),
			array( 'bbp_get_reply_author_link', 'bbp_suppress_private_author_link',         10, 3 ),
		) );
	}

	/**
	 * Verify clickable types are processed in their documented order.
	 */
	public function test_clickable_filters_run_in_order() {
		$this->assert_filters( array(
			array( 'bbp_make_clickable', 'bbp_make_urls_clickable',     2, 1 ),
			array( 'bbp_make_clickable', 'bbp_make_ftps_clickable',     4, 1 ),
			array( 'bbp_make_clickable', 'bbp_make_emails_clickable',   6, 1 ),
			array( 'bbp_make_clickable', 'bbp_make_mentions_clickable', 8, 1 ),
		) );
	}

	/**
	 * @covers ::_bbp_filter_locale
	 * @covers ::bbp_plugin_locale
	 */
	public function test_deprecated_locale_filter_runs_only_for_the_bbpress_domain() {
		$filtered = array();
		$filter   = function ( $locale, $domain ) use ( &$filtered ) {
			$filtered[] = array( $locale, $domain );

			return 'bbpress-' . $locale;
		};

		add_filter( 'bbpress_locale', $filter, 10, 2 );
		try {
			$this->assertSame( 'en_US', apply_filters( 'plugin_locale', 'en_US', 'other-domain' ) );
			$this->assertSame( 'bbpress-en_US', apply_filters( 'plugin_locale', 'en_US', 'bbpress' ) );
			$this->assertSame( array( array( 'en_US', 'bbpress' ) ), $filtered );
		} finally {
			remove_filter( 'bbpress_locale', $filter, 10 );
		}
	}

	/**
	 * @covers ::_bbp_has_forums_query
	 * @covers ::_bbp_has_topics_query
	 * @covers ::_bbp_has_replies_query
	 * @dataProvider get_deprecated_query_filters
	 */
	public function test_deprecated_query_filters_preserve_the_legacy_hooks( $parse_args_filter, $legacy_filter, $callback ) {
		$args     = array( 'post_type' => 'test' );
		$received = null;
		$filter   = function( $filtered_args ) use ( &$received ) {
			$received = $filtered_args;

			return 'filtered';
		};

		$this->assertSame( 10, has_filter( $parse_args_filter, $callback ) );

		add_filter( $legacy_filter, $filter );
		try {
			$this->assertSame( array( 'filtered' ), apply_filters( $parse_args_filter, $args ) );
			$this->assertSame( $args, $received );
		} finally {
			remove_filter( $legacy_filter, $filter, 10 );
		}
	}

	/**
	 * Data provider for deprecated query filters.
	 *
	 * @return array
	 */
	public function get_deprecated_query_filters() {
		return array(
			'forums' => array( 'bbp_after_has_forums_parse_args', 'bbp_has_forums_query', '_bbp_has_forums_query' ),
			'topics' => array( 'bbp_after_has_topics_parse_args', 'bbp_has_topics_query', '_bbp_has_topics_query' ),
			'replies' => array( 'bbp_after_has_replies_parse_args', 'bbp_has_replies_query', '_bbp_has_replies_query' ),
		);
	}

	/**
	 * Exact priorities ensure unfiltered-html requests can remove the intended callbacks.
	 *
	 * @dataProvider get_content_sanitization_filters
	 */
	public function test_content_sanitization_filters_are_registered_in_order( $filter ) {
		$this->assertSame( 10, has_filter( $filter, 'bbp_encode_bad' ) );
		$this->assertSame( 20, has_filter( $filter, 'bbp_code_trick' ) );
		$this->assertSame( 30, has_filter( $filter, 'bbp_filter_kses' ) );
		$this->assertSame( 40, has_filter( $filter, 'balanceTags' ) );
	}

	/**
	 * Test the content filter chain without depending on version-specific KSES output.
	 */
	public function test_content_sanitization_filters_process_content() {
		$content = apply_filters( 'bbp_new_topic_pre_content', '<script>x</script><strong>ok</strong> `<b>`' );

		$this->assertStringNotContainsString( '<script', $content );
		$this->assertStringContainsString( '<strong>ok</strong>', $content );
		$this->assertStringContainsString( '&lt;b&gt;', $content );
	}

	/**
	 * Data provider for forum, topic, and reply content filters.
	 *
	 * @return array
	 */
	public function get_content_sanitization_filters() {
		return array(
			'new forum'  => array( 'bbp_new_forum_pre_content' ),
			'new topic'  => array( 'bbp_new_topic_pre_content' ),
			'new reply'  => array( 'bbp_new_reply_pre_content' ),
			'edit forum' => array( 'bbp_edit_forum_pre_content' ),
			'edit topic' => array( 'bbp_edit_topic_pre_content' ),
			'edit reply' => array( 'bbp_edit_reply_pre_content' ),
		);
	}

	/**
	 * @dataProvider get_title_sanitization_filters
	 */
	public function test_title_sanitization_filters_use_wordpress_kses( $filter ) {
		$this->assertSame( 10, has_filter( $filter, 'wp_filter_kses' ) );
	}

	/**
	 * Data provider for forum, topic, and reply title filters.
	 *
	 * @return array
	 */
	public function get_title_sanitization_filters() {
		return array(
			'new forum'  => array( 'bbp_new_forum_pre_title' ),
			'new topic'  => array( 'bbp_new_topic_pre_title' ),
			'new reply'  => array( 'bbp_new_reply_pre_title' ),
			'edit forum' => array( 'bbp_edit_forum_pre_title' ),
			'edit topic' => array( 'bbp_edit_topic_pre_title' ),
			'edit reply' => array( 'bbp_edit_reply_pre_title' ),
		);
	}
}
