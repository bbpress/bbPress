<?php

/**
 * Tests for visibility of content rendered by single-item shortcodes.
 *
 * @group common
 * @group shortcodes
 * @group visibility
 */
class BBP_Tests_Common_Shortcode_Visibility extends BBP_UnitTestCase {

	private $template_loader;
	private $old_errors;

	public function setUp(): void {
		parent::setUp();
		$this->old_errors = bbpress()->errors;
		bbpress()->errors = new WP_Error();

		// The PHPUnit bootstrap leaves WP_USE_THEMES off; load located templates.
		$this->template_loader = function( $located, $template_name, $template_names, $template_locations, $load ) {
			if ( $load && $located ) {
				load_template( $located, false );
			}
		};
		add_action( 'bbp_locate_template', $this->template_loader, 10, 5 );
	}

	public function tearDown(): void {
		remove_action( 'bbp_locate_template', $this->template_loader, 10 );
		remove_filter( 'bbp_show_lead_topic', '__return_true' );
		$this->set_current_user( 0 );
		$this->go_to_without_reregistering_blocks( '/' );
		bbpress()->errors = $this->old_errors;

		parent::tearDown();
	}

	/**
	 * @covers BBP_Shortcodes::display_reply
	 */
	public function test_single_reply_shortcode_hides_pending_reply_from_anonymous_user() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_status'  => bbp_get_pending_status_id(),
				'post_content' => 'Private audit sentinel 7129',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);
		$public_reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => 'Public audit sentinel 7130',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		$this->set_current_user( 0 );
		$this->assertTrue( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) );

		$public_html = do_shortcode( '[bbp-single-reply id="' . $public_reply_id . '"]' );
		$private_html = do_shortcode( '[bbp-single-reply id="' . $reply_id . '"]' );

		$this->assertStringContainsString( 'Public audit sentinel 7130', $public_html );
		$this->assertStringNotContainsString( 'Private audit sentinel 7129', $private_html );

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		$this->set_current_user( $moderator_id );
		$this->assertStringContainsString( 'Private audit sentinel 7129', do_shortcode( '[bbp-single-reply id="' . $reply_id . '"]' ) );
	}

	/**
	 * @covers BBP_Shortcodes::display_topic
	 */
	public function test_single_topic_shortcode_hides_pending_topic_from_anonymous_user() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_status'  => bbp_get_pending_status_id(),
				'post_content' => 'Private audit sentinel 7131',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);
		$public_topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_content' => 'Public audit sentinel 7132',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);

		$this->set_current_user( 0 );
		add_filter( 'bbp_show_lead_topic', '__return_true' );

		$public_html = do_shortcode( '[bbp-single-topic id="' . $public_topic_id . '"]' );
		$private_html = do_shortcode( '[bbp-single-topic id="' . $topic_id . '"]' );

		$this->assertStringContainsString( 'Public audit sentinel 7132', $public_html );
		$this->assertStringNotContainsString( 'Private audit sentinel 7131', $private_html );

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		$this->set_current_user( $moderator_id );
		$this->assertStringContainsString( 'Private audit sentinel 7131', do_shortcode( '[bbp-single-topic id="' . $topic_id . '"]' ) );
	}

	/**
	 * @covers BBP_Shortcodes::display_reply
	 */
	public function test_single_reply_shortcode_hides_reply_in_pending_topic_from_anonymous_user() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => 'Private audit sentinel 7133',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		$this->set_current_user( 0 );
		$this->assertStringNotContainsString( 'Private audit sentinel 7133', do_shortcode( '[bbp-single-reply id="' . $reply_id . '"]' ) );

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		$this->set_current_user( $moderator_id );
		$this->assertStringContainsString( 'Private audit sentinel 7133', do_shortcode( '[bbp-single-reply id="' . $reply_id . '"]' ) );
	}

	/**
	 * @covers BBP_Shortcodes::display_forum
	 * @covers BBP_Shortcodes::display_topic
	 * @covers BBP_Shortcodes::display_reply
	 */
	public function test_single_item_shortcodes_respect_hidden_forum_ancestor() {
		$parent_id = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$forum_id  = $this->factory->forum->create( array( 'post_parent' => $parent_id ) );
		$topic_id  = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_content' => 'Ancestor topic sentinel',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id  = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => 'Ancestor reply sentinel',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);
		$shortcodes = array(
			'[bbp-single-forum id="' . $forum_id . '"]',
			'[bbp-single-topic id="' . $topic_id . '"]',
			'[bbp-single-reply id="' . $reply_id . '"]',
		);

		$this->set_current_user( 0 );
		$this->assertFalse( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) );
		$this->assertTrue( bbp_user_can_view_forum( array( 'forum_id' => $forum_id, 'check_ancestors' => false ) ) );

		foreach ( $shortcodes as $shortcode ) {
			$this->assertSame( '', trim( do_shortcode( $shortcode ) ) );
		}

		$keymaster_id = $this->factory->user->create();
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->set_current_user( $keymaster_id );
		add_filter( 'bbp_show_lead_topic', '__return_true' );

		$this->assertStringContainsString( 'Ancestor topic sentinel', do_shortcode( $shortcodes[1] ) );
		$this->assertStringContainsString( 'Ancestor reply sentinel', do_shortcode( $shortcodes[2] ) );

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $moderator_id );
		$this->set_current_user( $moderator_id );

		$this->assertTrue( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) );
		$this->assertStringContainsString( 'Ancestor topic sentinel', do_shortcode( $shortcodes[1] ) );
	}

	/**
	 * @covers BBP_Shortcodes::display_forum
	 * @covers BBP_Shortcodes::display_topic
	 * @covers BBP_Shortcodes::display_reply
	 */
	public function test_single_item_shortcodes_use_embedded_password_context() {
		$forum_id = $this->factory->forum->create(
			array(
				'post_password' => 'forum-secret',
				'post_title'    => 'Protected forum marker',
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_title'   => 'Protected topic marker',
				'post_content' => 'Protected topic content marker',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => 'Protected reply marker',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);
		$host_id = $this->factory->post->create(
			array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_title'    => 'Public shortcode host',
				'post_password' => '',
			)
		);
		$objects = array(
			'forum' => $forum_id,
			'topic' => $topic_id,
			'reply' => $reply_id,
		);
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;

		$this->set_current_user( 0 );
		$this->go_to_without_reregistering_blocks( '/?page_id=' . $host_id );
		add_filter( 'bbp_show_lead_topic', '__return_true' );

		$this->assertFalse( post_password_required() );

		try {
			foreach ( $objects as $type => $object_id ) {
				$this->assertTrue( post_password_required( $object_id ) );
				$this->assertStringContainsString( 'id="bbp-protected"', do_shortcode( '[bbp-single-' . $type . ' id="' . $object_id . '"]' ) );
			}

			require_once ABSPATH . WPINC . '/class-phpass.php';
			$hasher            = new PasswordHash( 8, true );
			$_COOKIE[ $cookie ] = $hasher->HashPassword( 'forum-secret' );

			foreach ( $objects as $type => $object_id ) {
				$this->assertFalse( post_password_required( $object_id ) );
				$this->assertStringNotContainsString( 'id="bbp-protected"', do_shortcode( '[bbp-single-' . $type . ' id="' . $object_id . '"]' ) );
			}
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	private function go_to_without_reregistering_blocks( $url ) {
		remove_action( 'bbp_register', 'bbp_register_blocks', 10 );
		try {
			$this->go_to( home_url( $url ) );
		} finally {
			add_action( 'bbp_register', 'bbp_register_blocks', 10 );
		}
	}
}
