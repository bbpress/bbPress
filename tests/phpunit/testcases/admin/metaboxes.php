<?php

/**
 * Tests for admin metabox output.
 *
 * @group admin
 * @group metaboxes
 */
class BBP_Tests_Admin_Metaboxes extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}

		bbp_admin();
	}

	private function capture_callback( $callback, ...$args ) {
		$level = ob_get_level();
		ob_start();

		try {
			call_user_func_array( $callback, $args );
			return ob_get_clean();
		} catch ( Throwable $throwable ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			throw $throwable;
		}
	}

	private function capture_metabox( $callback, $post ) {
		$had_post = array_key_exists( 'post', $GLOBALS );
		$old_post = $had_post ? $GLOBALS['post'] : null;

		try {
			$GLOBALS['post'] = $post;
			return $this->capture_callback( $callback, $post );
		} finally {
			if ( $had_post ) {
				$GLOBALS['post'] = $old_post;
			} else {
				unset( $GLOBALS['post'] );
			}
		}
	}

	private function create_capable_user( $capabilities, $role = 'administrator' ) {
		$user_id = $this->factory->user->create( array( 'role' => $role ) );
		$user    = get_userdata( $user_id );

		foreach ( $capabilities as $capability ) {
			$user->add_cap( $capability );
		}

		$this->set_current_user( 0 );
		$this->set_current_user( $user_id );

		return $user_id;
	}

	public function filter_statistics() {
		return array(
			'user_count'                => '2',
			'user_count_int'            => 2,
			'forum_count'               => '3',
			'forum_count_int'           => 3,
			'topic_count'               => '4',
			'topic_count_int'           => 4,
			'reply_count'               => '5',
			'reply_count_int'           => 5,
			'topic_tag_count'           => '6',
			'topic_tag_count_int'       => 6,
			'topic_count_hidden'        => '7',
			'topic_count_hidden_int'    => 7,
			'hidden_topic_title'        => 'Hidden topics',
			'reply_count_hidden'        => '8',
			'reply_count_hidden_int'    => 8,
			'hidden_reply_title'        => 'Hidden replies',
			'empty_topic_tag_count'     => '9',
			'empty_topic_tag_count_int' => 9,
		);
	}

	/**
	 * @covers ::bbp_filter_dashboard_glance_items
	 */
	public function test_dashboard_glance_requires_access_and_outputs_filtered_statistics() {
		$seed = array( '<span>Existing</span>' );
		$this->set_current_user( 0 );
		$this->assertSame( $seed, bbp_filter_dashboard_glance_items( $seed ) );

		$statistics = array( $this, 'filter_statistics' );
		$observed   = null;
		$glance     = function ( $elements, $stats ) use ( &$observed ) {
			$observed = $stats;
			$elements[] = '<span>Filtered</span>';
			return $elements;
		};
		$grant_all  = function ( $allcaps, $caps ) {
			foreach ( $caps as $capability ) {
				$allcaps[ $capability ] = true;
			}

			return $allcaps;
		};
		add_filter( 'bbp_get_statistics', $statistics );
		add_filter( 'bbp_allow_topic_tags', '__return_true' );
		add_filter( 'bbp_dashboard_at_a_glance', $glance, 10, 2 );

		try {
			$restricted_id = $this->create_capable_user( array( 'spectate' ), 'subscriber' );
			bbp_set_user_role( $restricted_id, bbp_get_spectator_role() );
			$this->set_current_user( 0 );
			$this->set_current_user( $restricted_id );
			$restricted = implode( '', bbp_filter_dashboard_glance_items( $seed ) );
			$this->assertStringContainsString( '2 Users', $restricted );
			$this->assertStringContainsString( '3 Forums', $restricted );
			$this->assertStringContainsString( '4 Topics', $restricted );
			$this->assertStringContainsString( '5 Replies', $restricted );
			$this->assertStringContainsString( '6 Topic Tags', $restricted );
			$this->assertStringNotContainsString( '<a href=', $restricted );

			add_filter( 'user_has_cap', $grant_all, 10, 2 );
			$this->create_capable_user(
				array(
					'spectate',
					'edit_users',
					'manage_network_users',
					'publish_forums',
					'publish_topics',
					'publish_replies',
					'manage_topic_tags',
				)
			);
			$output = implode( '', bbp_filter_dashboard_glance_items( $seed ) );
			$this->assertStringContainsString( 'bbp-glance-users', $output );
			$this->assertStringContainsString( 'bbp-glance-forums', $output );
			$this->assertStringContainsString( 'bbp-glance-topics', $output );
			$this->assertStringContainsString( 'bbp-glance-replies', $output );
			$this->assertStringContainsString( 'bbp-glance-topic-tags', $output );
			$this->assertStringContainsString( 'post_type=forum', $output );
			$this->assertStringContainsString( '3 Forums', $output );
			$this->assertStringContainsString( '<span>Filtered</span>', $output );
			$this->assertSame( 3, $observed['forum_count_int'] );
		} finally {
			remove_filter( 'bbp_get_statistics', $statistics );
			remove_filter( 'bbp_allow_topic_tags', '__return_true' );
			remove_filter( 'bbp_dashboard_at_a_glance', $glance, 10 );
			remove_filter( 'user_has_cap', $grant_all, 10 );
		}
	}

	/**
	 * @covers ::bbp_dashboard_widget_right_now
	 */
	public function test_deprecated_dashboard_widget_outputs_statistics_and_extension_points() {
		$this->create_capable_user(
			array(
				'edit_users',
				'manage_network_users',
				'publish_forums',
				'publish_topics',
				'publish_replies',
				'manage_topic_tags',
			)
		);

		$statistics = array( $this, 'filter_statistics' );
		$content_end = function () {
			echo '<tr id="content-end"></tr>';
		};
		$discussion_end = function () {
			echo '<tr id="discussion-end"></tr>';
		};
		$widget_end = function () {
			echo '<span id="widget-end"></span>';
		};
		$table_end = function () {
			echo '<span id="table-end"></span>';
		};
		$zero_hidden = function ( $statistics ) {
			$statistics['topic_count_hidden']     = '0';
			$statistics['reply_count_hidden']     = '0';
			$statistics['empty_topic_tag_count']  = '0';
			return $statistics;
		};
		add_filter( 'bbp_get_statistics', $statistics );
		add_filter( 'bbp_allow_topic_tags', '__return_true' );
		add_action( 'bbp_dashboard_widget_right_now_content_table_end', $content_end );
		add_action( 'bbp_dashboard_widget_right_now_discussion_table_end', $discussion_end );
		add_action( 'bbp_dashboard_widget_right_now_table_end', $table_end );
		add_action( 'bbp_dashboard_widget_right_now_end', $widget_end );

		try {
			$output = $this->capture_callback( 'bbp_dashboard_widget_right_now' );
			$this->assertStringContainsString( 'class="first b b-forums"', $output );
			$this->assertStringContainsString( 'class="first b b-topics"', $output );
			$this->assertStringContainsString( 'class="first b b-replies"', $output );
			$this->assertStringContainsString( 'class="first b b-topic_tags"', $output );
			$this->assertStringContainsString( 'class="b b-users"', $output );
			$this->assertStringContainsString( 'class="b b-hidden-topics"', $output );
			$this->assertStringContainsString( 'class="b b-hidden-replies"', $output );
			$this->assertStringContainsString( 'class="b b-hidden-topic-tags"', $output );
			$this->assertStringContainsString( 'post_status=spam', $output );
			$this->assertStringContainsString( '>7</a>', $output );
			$this->assertStringContainsString( '>8</a>', $output );
			$this->assertStringContainsString( 'id="content-end"', $output );
			$this->assertStringContainsString( 'id="discussion-end"', $output );
			$this->assertStringContainsString( 'id="table-end"', $output );
			$this->assertStringContainsString( 'id="widget-end"', $output );
			$this->assertStringContainsString( 'bbPress ' . bbp_get_version(), $output );

			add_filter( 'bbp_get_statistics', $zero_hidden, 20 );
			$zero_output = $this->capture_callback( 'bbp_dashboard_widget_right_now' );
			$this->assertStringNotContainsString( 'post_status=spam', $zero_output );
		} finally {
			remove_filter( 'bbp_get_statistics', $statistics );
			remove_filter( 'bbp_allow_topic_tags', '__return_true' );
			remove_action( 'bbp_dashboard_widget_right_now_content_table_end', $content_end );
			remove_action( 'bbp_dashboard_widget_right_now_discussion_table_end', $discussion_end );
			remove_action( 'bbp_dashboard_widget_right_now_table_end', $table_end );
			remove_action( 'bbp_dashboard_widget_right_now_end', $widget_end );
			remove_filter( 'bbp_get_statistics', $zero_hidden, 20 );
		}
	}

	/**
	 * @covers ::bbp_forum_metabox
	 */
	public function test_forum_metabox_outputs_attributes_parent_order_and_nonce() {
		$parent_id = $this->factory->forum->create( array( 'post_title' => 'Parent Forum' ) );
		$forum_id  = $this->factory->forum->create(
			array(
				'post_parent' => $parent_id,
				'menu_order'  => 12,
			)
		);
		$output = $this->capture_metabox( 'bbp_forum_metabox', get_post( $forum_id ) );

		$this->assertStringContainsString( 'id="bbp_forum_type_select"', $output );
		$this->assertStringContainsString( 'id="bbp_forum_status_select"', $output );
		$this->assertStringContainsString( 'id="bbp_forum_visibility_select"', $output );
		$this->assertStringContainsString( 'id="parent_id"', $output );
		$this->assertMatchesRegularExpression( '/value="' . $parent_id . '"[^>]+selected=\'selected\'/', $output );
		$this->assertStringContainsString( 'id="menu_order" value="12"', $output );
		$this->assertStringContainsString( 'name="ping_status"', $output );
		$this->assertSame( 1, preg_match( '/name="bbp_forum_metabox" value="([^"]+)"/', $output, $matches ) );
		$this->assertNotFalse( wp_verify_nonce( $matches[1], 'bbp_forum_metabox_save' ) );
	}

	/**
	 * @covers ::bbp_topic_metabox
	 */
	public function test_topic_metabox_outputs_type_status_forum_and_nonce() {
		$forum_id = $this->factory->forum->create( array( 'post_title' => 'Topic Forum' ) );
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => 'auto-draft',
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$output = $this->capture_metabox( 'bbp_topic_metabox', get_post( $topic_id ) );

		$this->assertStringContainsString( 'id="bbp_stick_topic_select"', $output );
		$this->assertStringContainsString( 'name="hidden_post_status" id="hidden_post_status" value="draft"', $output );
		$this->assertStringContainsString( 'id="post_status_select"', $output );
		$this->assertStringContainsString( 'id="parent_id"', $output );
		$this->assertMatchesRegularExpression( '/value="' . $forum_id . '"[^>]+selected=\'selected\'/', $output );
		$this->assertSame( 1, preg_match( '/name="bbp_topic_metabox" value="([^"]+)"/', $output, $matches ) );
		$this->assertNotFalse( wp_verify_nonce( $matches[1], 'bbp_topic_metabox_save' ) );
	}

	/**
	 * @covers ::bbp_reply_metabox
	 */
	public function test_reply_metabox_outputs_status_topic_and_nonce() {
		$this->create_capable_user( array( 'edit_others_replies', 'moderate', 'edit_forums' ) );
		$topic_forum_id = $this->factory->forum->create( array( 'post_title' => 'Topic Forum' ) );
		$topic_id       = $this->factory->topic->create(
			array(
				'post_parent' => $topic_forum_id,
				'topic_meta'  => array( 'forum_id' => $topic_forum_id ),
			)
		);
		$reply_id       = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $topic_forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$output = $this->capture_metabox( 'bbp_reply_metabox', get_post( $reply_id ) );

		$this->assertStringContainsString( 'id="post_status_select"', $output );
		$this->assertStringContainsString( 'id="bbp_forum_id" class="bbp_dropdown" disabled="disabled"', $output );
		$this->assertStringContainsString( 'value="' . $topic_forum_id . '" selected', $output );
		$this->assertStringContainsString( 'Topic Forum', $output );
		$this->assertLessThan( strpos( $output, 'id="bbp_topic_id"' ), strpos( $output, 'id="bbp_forum_id"' ) );
		$this->assertStringContainsString( 'id="bbp_topic_id" type="text" value="' . $topic_id . '"', $output );
		$this->assertStringContainsString( 'id="bbp_reply_to"', $output );
		$this->assertSame( 1, preg_match( '/name="bbp_reply_metabox" value="([^"]+)"/', $output, $matches ) );
		$this->assertNotFalse( wp_verify_nonce( $matches[1], 'bbp_reply_metabox_save' ) );
	}

	/**
	 * @ticket BBP3724
	 * @covers ::bbp_reply_metabox
	 */
	public function test_reply_metabox_selects_topic_forum_without_writing_mismatched_meta() {
		$user_id = $this->create_capable_user( array( 'edit_others_replies', 'moderate', 'edit_forums' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( 0 );
		$this->set_current_user( $user_id );
		$topic_forum_id = $this->factory->forum->create( array( 'post_title' => 'Topic Forum' ) );
		$reply_forum_id = $this->factory->forum->create( array( 'post_title' => 'Reply Forum' ) );
		$topic_id       = $this->factory->topic->create(
			array(
				'post_parent' => $topic_forum_id,
				'topic_meta'  => array( 'forum_id' => $topic_forum_id ),
			)
		);
		$reply_id       = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $reply_forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		$this->assertSame( $reply_forum_id, (int) get_post_meta( $reply_id, '_bbp_forum_id', true ) );
		$this->assertSame( $topic_forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( $topic_forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );

		$output = $this->capture_metabox( 'bbp_reply_metabox', get_post( $reply_id ) );

		$this->assertStringContainsString( 'id="bbp_forum_id" class="bbp_dropdown" disabled="disabled"', $output );
		$this->assertStringContainsString( 'value="' . $topic_forum_id . '" selected', $output );
		$this->assertStringContainsString( 'id="bbp_topic_id" type="text" value="' . $topic_id . '"', $output );
		$this->assertStringContainsString( 'Topic Forum', $output );
		$this->assertStringNotContainsString( '>Fix</button>', $output );
		$this->assertStringNotContainsString( 'Should be:', $output );
		$this->assertSame( $reply_forum_id, (int) get_post_meta( $reply_id, '_bbp_forum_id', true ) );

		$nonforum_id = $this->factory->post->create( array( 'post_status' => 'private', 'post_title' => 'Private parent title' ) );
		$topic_id    = $this->factory->topic->create( array( 'post_parent' => $nonforum_id ) );
		$reply_id    = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$output      = $this->capture_metabox( 'bbp_reply_metabox', get_post( $reply_id ) );

		$this->assertSame( $nonforum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertStringContainsString( 'data-forum-title="' . $nonforum_id . '"', $output );
		$this->assertStringNotContainsString( 'Private parent title', $output );

		$this->set_current_user( 0 );
	}

	/**
	 * @covers ::bbp_topic_replies_metabox
	 */
	public function test_topic_replies_metabox_handles_empty_and_populated_topics() {
		$this->assertSame( '', $this->capture_callback( 'bbp_topic_replies_metabox' ) );

		$old_screen  = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		$hook_suffix = isset( $GLOBALS['hook_suffix'] ) ? $GLOBALS['hook_suffix'] : null;
		$had_typenow = array_key_exists( 'typenow', $GLOBALS );
		$old_typenow = $had_typenow ? $GLOBALS['typenow'] : null;
		$had_taxnow  = array_key_exists( 'taxnow', $GLOBALS );
		$old_taxnow  = $had_taxnow ? $GLOBALS['taxnow'] : null;
		$had_page    = array_key_exists( 'page', $_REQUEST );
		$old_page    = $had_page ? $_REQUEST['page'] : null;
		$user_id     = $this->create_capable_user( array( 'edit_replies', 'edit_others_replies', 'moderate' ) );
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id    = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $topic_id,
				'post_content' => 'Metabox reply content',
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		try {
			$GLOBALS['hook_suffix'] = 'edit-topic';
			set_current_screen( 'edit-topic' );
			$_REQUEST['page'] = '3"><script>alert(1)</script>';
			$output = $this->capture_callback( 'bbp_topic_replies_metabox', get_post( $topic_id ) );
			$this->assertStringContainsString( 'id="bbp-topic-replies"', $output );
			$this->assertStringContainsString( 'name="page" value="3"', $output );
			$this->assertStringNotContainsString( '<script>', $output );
			$this->assertStringNotContainsString( 'alert(1)', $output );
			$this->assertStringContainsString( 'Metabox reply content', $output );
			$this->assertStringContainsString( 'id="post-' . $reply_id . '"', $output );
		} finally {
			$GLOBALS['current_screen'] = $old_screen;
			if ( null === $hook_suffix ) {
				unset( $GLOBALS['hook_suffix'] );
			} else {
				$GLOBALS['hook_suffix'] = $hook_suffix;
			}
			if ( $had_typenow ) {
				$GLOBALS['typenow'] = $old_typenow;
			} else {
				unset( $GLOBALS['typenow'] );
			}
			if ( $had_taxnow ) {
				$GLOBALS['taxnow'] = $old_taxnow;
			} else {
				unset( $GLOBALS['taxnow'] );
			}
			if ( $had_page ) {
				$_REQUEST['page'] = $old_page;
			} else {
				unset( $_REQUEST['page'] );
			}
		}
	}

	/**
	 * @covers ::bbp_author_metabox
	 */
	public function test_author_metabox_outputs_anonymous_and_registered_author_fields() {
		$anonymous_id = $this->factory->topic->create( array( 'post_author' => 0 ) );
		update_post_meta( $anonymous_id, '_bbp_anonymous_name', 'Guest" onfocus="alert(1)' );
		update_post_meta( $anonymous_id, '_bbp_anonymous_email', 'guest@example.org' );
		update_post_meta( $anonymous_id, '_bbp_anonymous_website', 'https://example.org/?a=1&b=2' );
		update_post_meta( $anonymous_id, '_bbp_author_ip', '192.0.2.1' );
		$output = $this->capture_metabox( 'bbp_author_metabox', get_post( $anonymous_id ) );
		$this->assertStringContainsString( 'id="bbp_anonymous_name"', $output );
		$this->assertStringContainsString( 'value="Guest&quot; onfocus=&quot;alert(1)"', $output );
		$this->assertStringNotContainsString( 'onfocus="alert(1)', $output );
		$this->assertStringContainsString( 'id="bbp_anonymous_email" name="bbp_anonymous_email" value="guest@example.org"', $output );
		$this->assertStringContainsString( 'id="bbp_anonymous_website" name="bbp_anonymous_website" value="https://example.org/?a=1&amp;b=2"', $output );
		$this->assertStringContainsString( 'value="192.0.2.1" disabled="disabled"', $output );

		$reply_forum_id     = $this->factory->forum->create();
		$reply_topic_id     = $this->factory->topic->create(
			array(
				'post_parent' => $reply_forum_id,
				'topic_meta'  => array( 'forum_id' => $reply_forum_id ),
			)
		);
		$anonymous_reply_id = $this->factory->reply->create(
			array(
				'post_author' => 0,
				'post_parent' => $reply_topic_id,
				'reply_meta'  => array(
					'forum_id' => $reply_forum_id,
					'topic_id' => $reply_topic_id,
				),
			)
		);
		update_post_meta( $anonymous_reply_id, '_bbp_anonymous_name', 'Reply Guest' );
		$output = $this->capture_metabox( 'bbp_author_metabox', get_post( $anonymous_reply_id ) );
		$this->assertStringContainsString( 'id="bbp_anonymous_name"', $output );
		$this->assertStringContainsString( 'value="Reply Guest"', $output );

		$user_id    = $this->factory->user->create();
		$registered = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$output     = $this->capture_metabox( 'bbp_author_metabox', get_post( $registered ) );
		$this->assertStringContainsString( 'id="bbp_author_id" name="post_author_override" value="' . $user_id . '"', $output );
		$this->assertStringNotContainsString( 'id="bbp_anonymous_name"', $output );
		$this->assertStringContainsString( 'action=bbp_suggest_user', $output );
	}

	/**
	 * @covers ::bbp_moderator_assignment_metabox
	 */
	public function test_moderator_assignment_metabox_outputs_assigned_nicenames() {
		$forum_id = $this->factory->forum->create();
		$first_id = $this->factory->user->create( array( 'user_nicename' => 'first-moderator' ) );
		$second_id = $this->factory->user->create( array( 'user_nicename' => 'second-moderator' ) );
		bbp_add_moderator( $forum_id, $first_id );
		bbp_add_moderator( $forum_id, $second_id );

		$output = $this->capture_callback( 'bbp_moderator_assignment_metabox', get_post( $forum_id ) );
		$this->assertStringContainsString( 'id="bbp_moderators"', $output );
		$this->assertStringContainsString( 'first-moderator', $output );
		$this->assertStringContainsString( 'second-moderator', $output );
		$this->assertStringContainsString( 'Separate user-names with commas', $output );
	}

	/**
	 * @covers ::bbp_topic_engagements_metabox
	 * @covers ::bbp_topic_favorites_metabox
	 * @covers ::bbp_topic_subscriptions_metabox
	 * @covers ::bbp_forum_subscriptions_metabox
	 * @covers ::bbp_metabox_user_links
	 */
	public function test_relationship_metaboxes_output_users_and_empty_messages() {
		$had_user_query = isset( bbpress()->user_query );
		$old_user_query = $had_user_query ? bbpress()->user_query : null;
		$user_id  = $this->factory->user->create( array( 'display_name' => 'Relationship User' ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		bbp_add_user_engagement( $user_id, $topic_id );
		bbp_add_user_favorite( $user_id, $topic_id );
		bbp_add_user_topic_subscription( $user_id, $topic_id );
		bbp_add_user_forum_subscription( $user_id, $forum_id );

		try {
			foreach ( array(
				'bbp_topic_engagements_metabox' => get_post( $topic_id ),
				'bbp_topic_favorites_metabox' => get_post( $topic_id ),
				'bbp_topic_subscriptions_metabox' => get_post( $topic_id ),
				'bbp_forum_subscriptions_metabox' => get_post( $forum_id ),
			) as $callback => $post ) {
				$output = $this->capture_callback( $callback, $post );
				$this->assertStringContainsString( '<a href=', $output );
				$this->assertStringContainsString( 'avatar', $output );
				$this->assertStringContainsString( esc_url( bbp_get_user_profile_url( $user_id ) ), $output );
			}

			$this->set_current_user( 0 );
			$empty_topic = get_post( $this->factory->topic->create( array( 'post_author' => 0 ) ) );
			$empty_forum = get_post( $this->factory->forum->create( array( 'post_author' => 0 ) ) );
			$this->assertStringContainsString( 'No users have engaged', $this->capture_callback( 'bbp_topic_engagements_metabox', $empty_topic ) );
			$this->assertStringContainsString( 'No users have favorited', $this->capture_callback( 'bbp_topic_favorites_metabox', $empty_topic ) );
			$this->assertStringContainsString( 'No users have subscribed to this topic', $this->capture_callback( 'bbp_topic_subscriptions_metabox', $empty_topic ) );
			$this->assertStringContainsString( 'No users have subscribed to this forum', $this->capture_callback( 'bbp_forum_subscriptions_metabox', $empty_forum ) );
		} finally {
			if ( $had_user_query ) {
				bbpress()->user_query = $old_user_query;
			} else {
				unset( bbpress()->user_query );
			}
		}
	}
}
