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
}
