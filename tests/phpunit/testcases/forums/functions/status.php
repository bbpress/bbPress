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
	 */
	public function test_bbp_get_forum_statuses() {
		$forum_id = $this->factory->forum->create();
		$statuses = bbp_get_forum_statuses( $forum_id );

		$this->assertSame( array( 'open', 'closed' ), array_keys( $statuses ) );
		$this->assertNotEmpty( $statuses['open'] );
		$this->assertNotEmpty( $statuses['closed'] );

		$filter = function( $statuses, $filtered_forum_id ) use ( $forum_id ) {
			$this->assertSame( $forum_id, $filtered_forum_id );
			$statuses['custom'] = 'Custom status';
			return $statuses;
		};
		add_filter( 'bbp_get_forum_statuses', $filter, 10, 2 );
		try {
			$this->assertArrayHasKey( 'custom', bbp_get_forum_statuses( $forum_id ) );
		} finally {
			remove_filter( 'bbp_get_forum_statuses', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_forum_types
	 */
	public function test_bbp_get_forum_types() {
		$forum_id = $this->factory->forum->create();
		$types    = bbp_get_forum_types( $forum_id );

		$this->assertSame( array( 'forum', 'category' ), array_keys( $types ) );
		$this->assertNotEmpty( $types['forum'] );
		$this->assertNotEmpty( $types['category'] );

		$filter = function( $types, $filtered_forum_id ) use ( $forum_id ) {
			$this->assertSame( $forum_id, $filtered_forum_id );
			$types['custom'] = 'Custom type';
			return $types;
		};
		add_filter( 'bbp_get_forum_types', $filter, 10, 2 );
		try {
			$this->assertArrayHasKey( 'custom', bbp_get_forum_types( $forum_id ) );
		} finally {
			remove_filter( 'bbp_get_forum_types', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_public_forum_statuses
	 */
	public function test_bbp_get_public_forum_statuses() {
		$this->assertSame( array( bbp_get_public_status_id() ), bbp_get_public_forum_statuses() );

		$filter = function( $statuses ) {
			$statuses[] = bbp_get_private_status_id();
			return $statuses;
		};
		add_filter( 'bbp_get_public_forum_statuses', $filter );
		try {
			$this->assertSame( array( bbp_get_public_status_id(), bbp_get_private_status_id() ), bbp_get_public_forum_statuses() );
		} finally {
			remove_filter( 'bbp_get_public_forum_statuses', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_non_public_forum_statuses
	 */
	public function test_bbp_get_non_public_forum_statuses() {
		$this->assertSame( array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ), bbp_get_non_public_forum_statuses() );

		$filter = function( $statuses ) {
			$statuses[] = bbp_get_public_status_id();
			return $statuses;
		};
		add_filter( 'bbp_get_non_public_forum_statuses', $filter );
		try {
			$this->assertSame( array( bbp_get_private_status_id(), bbp_get_hidden_status_id(), bbp_get_public_status_id() ), bbp_get_non_public_forum_statuses() );
		} finally {
			remove_filter( 'bbp_get_non_public_forum_statuses', $filter );
		}
	}

	/**
	 * @covers ::bbp_close_forum
	 */
	public function test_bbp_close_forum() {
		$forum_id = $this->factory->forum->create();
		$events   = array();
		$before   = function( $id ) use ( &$events ) { $events[] = 'before:' . $id; };
		$after    = function( $id ) use ( &$events ) { $events[] = 'after:' . $id; };

		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );
		add_action( 'bbp_close_forum', $before );
		add_action( 'bbp_closed_forum', $after );
		try {
			$this->assertSame( $forum_id, bbp_close_forum( $forum_id ) );
		} finally {
			remove_action( 'bbp_close_forum', $before );
			remove_action( 'bbp_closed_forum', $after );
		}

		$this->assertSame( array( 'before:' . $forum_id, 'after:' . $forum_id ), $events );
		$this->assertSame( 'closed', bbp_get_forum_status( $forum_id ) );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $forum_id ) );
		$this->assertSame( 'forum', bbp_get_forum_type( $forum_id ) );
	}

	/**
	 * @covers ::bbp_open_forum
	 */
	public function test_bbp_open_forum() {
		$forum_id = $this->factory->forum->create();
		bbp_close_forum( $forum_id );
		$events = array();
		$before = function( $id ) use ( &$events ) { $events[] = 'before:' . $id; };
		$after  = function( $id ) use ( &$events ) { $events[] = 'after:' . $id; };

		$this->assertSame( 'closed', bbp_get_forum_status( $forum_id ) );
		add_action( 'bbp_open_forum', $before );
		add_action( 'bbp_opened_forum', $after );
		try {
			$this->assertSame( $forum_id, bbp_open_forum( $forum_id ) );
		} finally {
			remove_action( 'bbp_open_forum', $before );
			remove_action( 'bbp_opened_forum', $after );
		}

		$this->assertSame( array( 'before:' . $forum_id, 'after:' . $forum_id ), $events );
		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $forum_id ) );
		$this->assertSame( 'forum', bbp_get_forum_type( $forum_id ) );
	}

	/**
	 * @covers ::bbp_categorize_forum
	 */
	public function test_bbp_categorize_forum() {
		$forum_id = $this->factory->forum->create();
		$events   = array();
		$before   = function( $id ) use ( &$events ) { $events[] = 'before:' . $id; };
		$after    = function( $id ) use ( &$events ) { $events[] = 'after:' . $id; };

		$this->assertSame( 'forum', bbp_get_forum_type( $forum_id ) );
		add_action( 'bbp_categorize_forum', $before );
		add_action( 'bbp_categorized_forum', $after );
		try {
			$this->assertSame( $forum_id, bbp_categorize_forum( $forum_id ) );
		} finally {
			remove_action( 'bbp_categorize_forum', $before );
			remove_action( 'bbp_categorized_forum', $after );
		}

		$this->assertSame( array( 'before:' . $forum_id, 'after:' . $forum_id ), $events );
		$this->assertSame( 'category', bbp_get_forum_type( $forum_id ) );
		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $forum_id ) );
	}

	/**
	 * @covers ::bbp_normalize_forum
	 */
	public function test_bbp_normalize_forum() {
		$forum_id = $this->factory->forum->create();
		bbp_categorize_forum( $forum_id );
		$events = array();
		$before = function( $id ) use ( &$events ) { $events[] = 'before:' . $id; };
		$after  = function( $id ) use ( &$events ) { $events[] = 'after:' . $id; };

		$this->assertSame( 'category', bbp_get_forum_type( $forum_id ) );
		add_action( 'bbp_normalize_forum', $before );
		add_action( 'bbp_normalized_forum', $after );
		try {
			$this->assertSame( $forum_id, bbp_normalize_forum( $forum_id ) );
		} finally {
			remove_action( 'bbp_normalize_forum', $before );
			remove_action( 'bbp_normalized_forum', $after );
		}

		$this->assertSame( array( 'before:' . $forum_id, 'after:' . $forum_id ), $events );
		$this->assertSame( 'forum', bbp_get_forum_type( $forum_id ) );
		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $forum_id ) );
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
	 * @covers ::bbp_publicize_forum
	 * @covers ::bbp_privatize_forum
	 * @covers ::bbp_hide_forum
	 * @ticket 3794
	 */
	public function test_forum_visibility_changes_without_a_post() {
		$forum_id = 999999999;
		$private  = get_option( '_bbp_private_forums', array() );
		$hidden   = get_option( '_bbp_hidden_forums', array() );
		$this->assertNull( get_post( $forum_id ) );

		$this->assertSame( $forum_id, bbp_publicize_forum( $forum_id, bbp_get_hidden_status_id() ) );
		$this->assertSame( $forum_id, bbp_privatize_forum( $forum_id, bbp_get_public_status_id() ) );
		$this->assertSame( $forum_id, bbp_hide_forum( $forum_id, bbp_get_private_status_id() ) );
		$this->assertSame( $private, get_option( '_bbp_private_forums', array() ) );
		$this->assertSame( $hidden, get_option( '_bbp_hidden_forums', array() ) );
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
