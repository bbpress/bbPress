<?php

/**
 * Internal link search must not disclose content in restricted forums.
 *
 * @group forums
 * @group functions
 * @group visibility
 */
class BBP_Tests_Forums_Functions_Link_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_link_query_post_status_request_does_not_reveal_hidden_topic() {
		$forum_id = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_title'  => 'Restricted internal link marker',
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$old_request = $_REQUEST;
		$_REQUEST['post_status'] = 'publish';
		set_current_screen( 'dashboard' );
		add_filter( 'wp_doing_ajax', '__return_true' );
		require_once ABSPATH . WPINC . '/class-wp-editor.php';

		try {
			$this->assertTrue( is_admin() );
			$this->assertTrue( wp_doing_ajax() );
			$results = _WP_Editors::wp_link_query( array( 's' => 'Restricted internal link marker' ) );
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			set_current_screen( 'front' );
			$_REQUEST = $old_request;
		}

		$ids = is_array( $results ) ? wp_list_pluck( $results, 'ID' ) : array();
		$this->assertNotContains( $topic_id, $ids );
	}
}
