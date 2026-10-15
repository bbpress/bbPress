<?php

/**
 * Tests for front-end moderation action routing.
 *
 * @group common
 * @group moderation
 */
class BBP_Tests_Common_Moderation_Actions extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_topic_merge_link
	 * @covers ::bbp_get_topic_split_link
	 * @covers ::bbp_get_reply_move_link
	 * @covers ::bbp_merge_topic_form_fields
	 * @covers ::bbp_split_topic_form_fields
	 * @covers ::bbp_move_reply_form_fields
	 * @ticket BBP3365
	 */
	public function test_moderation_link_actions_match_form_actions() {
		$user_id           = $this->factory->user->create();
		$forum_id          = $this->factory->forum->create();
		$topic_id          = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id          = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$old_get           = $_GET;
		$old_topic_id      = bbpress()->current_topic_id;
		$old_reply_id      = bbpress()->current_reply_id;
		$old_current_user  = get_current_user_id();

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->current_topic_id = $topic_id;
		bbpress()->current_reply_id = $reply_id;
		$_GET['reply_id']            = $reply_id;

		try {
			$this->assertStringContainsString( 'action=bbp-merge-topic', bbp_get_topic_merge_link( array( 'id' => $topic_id ) ) );
			$this->assertStringContainsString( 'action=bbp-split-topic', bbp_get_topic_split_link( array( 'id' => $reply_id ) ) );
			$this->assertStringContainsString( 'action=bbp-move-reply', bbp_get_reply_move_link( array( 'id' => $reply_id ) ) );

			foreach (
				array(
					'bbp_merge_topic_form_fields' => 'bbp-merge-topic',
					'bbp_split_topic_form_fields' => 'bbp-split-topic',
					'bbp_move_reply_form_fields'  => 'bbp-move-reply',
				) as $callback => $action
			) {
				ob_start();
				$callback();
				$fields = ob_get_clean();

				$this->assertStringContainsString( 'name="action"', $fields );
				$this->assertStringContainsString( 'value="' . $action . '"', $fields );
			}
		} finally {
			$_GET                       = $old_get;
			bbpress()->current_topic_id = $old_topic_id;
			bbpress()->current_reply_id = $old_reply_id;
			$this->set_current_user( $old_current_user );
		}
	}

	/**
	 * @covers ::bbp_is_topic_merge
	 * @covers ::bbp_is_topic_split
	 * @covers ::bbp_is_reply_move
	 * @covers ::bbp_is_get_action
	 * @ticket BBP3365
	 * @ticket BBP3740
	 */
	public function test_moderation_routes_accept_current_and_legacy_actions_during_get_and_post_requests() {
		$wp_query           = bbp_get_wp_query();
		$old_get            = $_GET;
		$old_request_method = $_SERVER['REQUEST_METHOD'];
		$old_topic_edit     = isset( $wp_query->bbp_is_topic_edit ) ? $wp_query->bbp_is_topic_edit : null;
		$old_reply_edit     = isset( $wp_query->bbp_is_reply_edit ) ? $wp_query->bbp_is_reply_edit : null;

		$wp_query->bbp_is_topic_edit = true;
		$wp_query->bbp_is_reply_edit = true;

		try {
			foreach ( array( 'GET', 'POST' ) as $request_method ) {
				$_SERVER['REQUEST_METHOD'] = $request_method;

				foreach ( array( 'merge', 'bbp-merge-topic' ) as $action ) {
					$_GET['action'] = $action;
					$this->assertTrue( bbp_is_topic_merge() );
				}

				foreach ( array( 'split', 'bbp-split-topic' ) as $action ) {
					$_GET['action'] = $action;
					$this->assertTrue( bbp_is_topic_split() );
				}

				foreach ( array( 'move', 'bbp-move-reply' ) as $action ) {
					$_GET['action'] = $action;
					$this->assertTrue( bbp_is_reply_move() );
				}
			}
		} finally {
			$_GET                         = $old_get;
			$_SERVER['REQUEST_METHOD']     = $old_request_method;
			$wp_query->bbp_is_topic_edit  = $old_topic_edit;
			$wp_query->bbp_is_reply_edit  = $old_reply_edit;
		}
	}

	/**
	 * @covers ::bbp_is_get_action
	 * @ticket BBP3365
	 * @ticket BBP3740
	 */
	public function test_get_action_requires_a_matching_query_string_action() {
		$old_get            = $_GET;
		$old_request_method = $_SERVER['REQUEST_METHOD'];

		try {
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_GET['action']             = 'bbp-Merge_Topic!';

			$this->assertTrue( bbp_is_get_action( 'bbp-merge_topic' ) );
			$this->assertTrue( bbp_is_get_action( array( 'merge', 'bbp-merge_topic' ) ) );
			$this->assertFalse( bbp_is_get_action( 'bbp-split-topic' ) );

			$_GET['action'] = array( 'bbp-merge-topic' );
			$this->assertFalse( bbp_is_get_action( 'bbp-merge-topic' ) );

			$_GET['action']             = 'bbp-merge-topic';
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$this->assertTrue( bbp_is_get_action( 'bbp-merge-topic' ) );
		} finally {
			$_GET                     = $old_get;
			$_SERVER['REQUEST_METHOD'] = $old_request_method;
		}
	}
}
