<?php

/**
 * Tests that widgets only show content visible to the current viewer.
 *
 * @group common
 * @group widgets
 * @group visibility
 */
class BBP_Tests_Common_Widget_Visibility extends BBP_UnitTestCase {
	private $old_errors;

	public function setUp(): void {
		parent::setUp();
		$this->old_errors = bbpress()->errors;
		bbpress()->errors = new WP_Error();
	}

	public function tearDown(): void {
		$this->set_current_user( 0 );
		bbpress()->errors = $this->old_errors;

		parent::tearDown();
	}

	/**
	 * @covers BBP_Replies_Widget::widget
	 */
	public function test_replies_widget_hides_reply_in_pending_topic() {
		$forum_id = $this->factory->forum->create();
		$pending_topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => 'Private topic sentinel 7134',
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$pending_topic_reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $pending_topic_id,
				'post_content' => 'Private reply sentinel 7135',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $pending_topic_id ),
			)
		);
		bbp_unapprove_topic( $pending_topic_id );
		$public_topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => 'Public topic sentinel 7136',
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$this->factory->reply->create(
			array(
				'post_parent'  => $public_topic_id,
				'post_content' => 'Public reply sentinel 7137',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $public_topic_id ),
			)
		);

		$this->set_current_user( 0 );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $pending_topic_reply_id ) );
		$widget = new BBP_Replies_Widget();
		ob_start();
		$widget->widget( array( 'before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => '' ), array( 'max_shown' => 10 ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Public topic sentinel 7136', $html );
		$this->assertStringNotContainsString( 'Private topic sentinel 7134', $html );
		$this->assertStringNotContainsString( 'Private reply sentinel 7135', $html );

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		$this->set_current_user( $moderator_id );
		ob_start();
		$widget->widget( array( 'before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => '' ), array( 'max_shown' => 10 ) );
		$moderator_html = ob_get_clean();
		$this->assertStringContainsString( 'Private topic sentinel 7134', $moderator_html );
	}
}
