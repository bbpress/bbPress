<?php

/**
 * Tests for bbPress forum statuses and types functions.
 *
 * @group forums
 * @group functions
 * @group status
 */
class BBP_Tests_Forums_Functions_Status extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_forum_statuses
	 * @todo   Implement test_bbp_get_forum_statuses().
	 */
	public function test_bbp_get_forum_statuses() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_forum_types
	 * @todo   Implement test_bbp_get_forum_types().
	 */
	public function test_bbp_get_forum_types() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_close_forum
	 * @todo   Implement test_bbp_close_forum().
	 */
	public function test_bbp_close_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_open_forum
	 * @todo   Implement test_bbp_open_forum().
	 */
	public function test_bbp_open_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_categorize_forum
	 * @todo   Implement test_bbp_categorize_forum().
	 */
	public function test_bbp_categorize_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_normalize_forum
	 * @todo   Implement test_bbp_normalize_forum().
	 */
	public function test_bbp_normalize_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_publicize_forum
	 * @covers ::bbp_update_forum_visibility_options
	 */
	public function test_bbp_publicize_forum() {
		$forum_id = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );

		$this->assertContains( $forum_id, bbp_get_hidden_forum_ids() );
		$this->assertSame( $forum_id, bbp_publicize_forum( $forum_id, bbp_get_hidden_status_id() ) );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $forum_id ) );
		$this->assertNotContains( $forum_id, bbp_get_private_forum_ids() );
		$this->assertNotContains( $forum_id, bbp_get_hidden_forum_ids() );
	}

	/**
	 * @covers ::bbp_privatize_forum
	 * @covers ::bbp_update_forum_visibility_options
	 */
	public function test_bbp_privatize_forum() {
		$forum_id = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );

		$this->assertContains( $forum_id, bbp_get_hidden_forum_ids() );
		$this->assertSame( $forum_id, bbp_privatize_forum( $forum_id, bbp_get_hidden_status_id() ) );
		$this->assertSame( bbp_get_private_status_id(), get_post_status( $forum_id ) );
		$this->assertContains( $forum_id, bbp_get_private_forum_ids() );
		$this->assertNotContains( $forum_id, bbp_get_hidden_forum_ids() );
	}

	/**
	 * @covers ::bbp_hide_forum
	 * @covers ::bbp_update_forum_visibility_options
	 */
	public function test_bbp_hide_forum() {
		$forum_id = $this->factory->forum->create( array(
			'post_status' => bbp_get_private_status_id(),
		) );

		$this->assertContains( $forum_id, bbp_get_private_forum_ids() );
		$this->assertSame( $forum_id, bbp_hide_forum( $forum_id, bbp_get_private_status_id() ) );
		$this->assertSame( bbp_get_hidden_status_id(), get_post_status( $forum_id ) );
		$this->assertNotContains( $forum_id, bbp_get_private_forum_ids() );
		$this->assertContains( $forum_id, bbp_get_hidden_forum_ids() );
	}

	/**
	 * @covers ::bbp_update_forum_visibility_on_transition_post_status
	 * @covers ::bbp_update_forum_visibility_options
	 */
	public function test_wp_update_post_synchronizes_forum_visibility_options() {
		$forum_id = $this->factory->forum->create();

		wp_update_post( array(
			'ID'          => $forum_id,
			'post_status' => bbp_get_private_status_id(),
		) );

		$this->assertContains( $forum_id, bbp_get_private_forum_ids() );
		$this->assertNotContains( $forum_id, bbp_get_hidden_forum_ids() );

		wp_update_post( array(
			'ID'          => $forum_id,
			'post_status' => bbp_get_hidden_status_id(),
		) );

		$this->assertNotContains( $forum_id, bbp_get_private_forum_ids() );
		$this->assertContains( $forum_id, bbp_get_hidden_forum_ids() );

		wp_update_post( array(
			'ID'          => $forum_id,
			'post_status' => bbp_get_public_status_id(),
		) );

		$this->assertNotContains( $forum_id, bbp_get_private_forum_ids() );
		$this->assertNotContains( $forum_id, bbp_get_hidden_forum_ids() );
	}

	/**
	 * @covers ::bbp_update_forum_visibility_on_transition_post_status
	 * @covers ::bbp_update_forum_visibility_options
	 */
	public function test_wp_insert_post_synchronizes_forum_visibility_options() {
		$private_id = wp_insert_post( array(
			'post_title'  => 'Private Forum',
			'post_type'   => bbp_get_forum_post_type(),
			'post_status' => bbp_get_private_status_id(),
		) );
		$hidden_id = wp_insert_post( array(
			'post_title'  => 'Hidden Forum',
			'post_type'   => bbp_get_forum_post_type(),
			'post_status' => bbp_get_hidden_status_id(),
		) );

		$this->assertContains( $private_id, bbp_get_private_forum_ids() );
		$this->assertNotContains( $private_id, bbp_get_hidden_forum_ids() );
		$this->assertNotContains( $hidden_id, bbp_get_private_forum_ids() );
		$this->assertContains( $hidden_id, bbp_get_hidden_forum_ids() );
	}

	/**
	 * @covers ::bbp_update_forum_visibility_on_transition_post_status
	 * @covers ::bbp_update_forum_visibility_options
	 */
	public function test_visibility_synchronization_does_not_persist_filtered_ids() {
		$private_id = $this->factory->forum->create( array(
			'post_status' => bbp_get_private_status_id(),
		) );
		$forum_id = $this->factory->forum->create();

		add_filter( 'bbp_get_private_forum_ids', '__return_empty_array' );
		add_filter( 'bbp_get_hidden_forum_ids',  '__return_empty_array' );

		wp_update_post( array(
			'ID'          => $forum_id,
			'post_status' => bbp_get_hidden_status_id(),
		) );

		remove_filter( 'bbp_get_private_forum_ids', '__return_empty_array' );
		remove_filter( 'bbp_get_hidden_forum_ids',  '__return_empty_array' );

		$this->assertContains( $private_id, bbp_get_private_forum_ids() );
		$this->assertContains( $forum_id, bbp_get_hidden_forum_ids() );
	}

	/**
	 * @covers ::bbp_update_forum_visibility_on_transition_post_status
	 * @covers ::bbp_update_forum_visibility_options
	 */
	public function test_wp_update_post_restricts_topics_in_newly_private_forum() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );

		wp_update_post( array(
			'ID'          => $forum_id,
			'post_status' => bbp_get_private_status_id(),
		) );

		$this->set_current_user( 0 );

		$query = new WP_Query( array(
			'post_type'      => bbp_get_topic_post_type(),
			'post_status'    => bbp_get_public_status_id(),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$this->assertNotContains( $topic_id, $query->posts );
		$this->assertFalse( get_oembed_response_data_for_url( home_url( '/?p=' . $topic_id ), array( 'width' => 600 ) ) );
	}

	/**
	 * @dataProvider restored_forum_statuses
	 */
	public function test_restore_preserves_forum_visibility( $status ) {
		$forum_id = $this->factory->forum->create( array( 'post_status' => $status ) );
		wp_trash_post( $forum_id );
		$this->assertSame( 'trash', get_post_status( $forum_id ) );
		wp_untrash_post( $forum_id );
		$this->assertSame( $status, get_post_status( $forum_id ) );
	}

	public static function restored_forum_statuses() {
		return array( array( 'publish' ), array( 'private' ), array( 'hidden' ) );
	}

}
