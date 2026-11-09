<?php

/**
 * Tests for theme-side post edit locks.
 *
 * @group common
 * @group locking
 */
class BBP_Tests_Common_Post_Lock extends BBP_UnitTestCase {
	/**
	 * @covers ::bbp_check_post_lock
	 */
	public function test_check_post_lock_returns_false_for_missing_post_and_lock() {
		$this->assertFalse( bbp_check_post_lock( 0 ) );
		$this->assertFalse( bbp_check_post_lock( $this->nonexistent_post_id() ) );

		$topic_id = $this->factory->topic->create();
		$this->assertFalse( bbp_check_post_lock( $topic_id ) );
	}

	/**
	 * @covers ::bbp_set_post_lock
	 */
	public function test_set_post_lock_requires_post_and_current_user() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$this->assertFalse( bbp_set_post_lock( 0 ) );
		$this->assertFalse( bbp_set_post_lock( $this->nonexistent_post_id() ) );

		$topic_id = $this->factory->topic->create();
		wp_set_current_user( 0 );

		$this->assertFalse( bbp_set_post_lock( $topic_id ) );
		$this->assertSame( '', get_post_meta( $topic_id, '_edit_lock', true ) );
	}

	/**
	 * @covers ::bbp_set_post_lock
	 */
	public function test_set_post_lock_persists_timestamp_and_user() {
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();
		$before   = time();

		wp_set_current_user( $user_id );
		$lock  = bbp_set_post_lock( $topic_id );
		$after = time();

		$this->assertIsArray( $lock );
		$this->assertCount( 2, $lock );
		$this->assertGreaterThanOrEqual( $before, $lock[0] );
		$this->assertLessThanOrEqual( $after, $lock[0] );
		$this->assertSame( $user_id, $lock[1] );
		$this->assertSame( $lock[0] . ':' . $user_id, get_post_meta( $topic_id, '_edit_lock', true ) );
	}

	/**
	 * @covers ::bbp_set_post_lock
	 */
	public function test_set_post_lock_replaces_existing_lock() {
		$old_user_id = $this->factory->user->create();
		$new_user_id = $this->factory->user->create();
		$topic_id    = $this->factory->topic->create();

		update_post_meta( $topic_id, '_edit_lock', ( time() - MINUTE_IN_SECONDS ) . ':' . $old_user_id );
		wp_set_current_user( $new_user_id );

		$lock = bbp_set_post_lock( $topic_id );

		$this->assertSame( $new_user_id, $lock[1] );
		$this->assertSame( $lock[0] . ':' . $new_user_id, get_post_meta( $topic_id, '_edit_lock', true ) );
	}

	/**
	 * A current lock belongs to its owner, and expires for other users.
	 *
	 * @covers ::bbp_check_post_lock
	 * @covers ::bbp_set_post_lock
	 */
	public function test_check_post_lock_compares_integer_user_ids() {
		$owner_id = $this->factory->user->create();
		$other_id = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		wp_set_current_user( $owner_id );
		$this->assertIsArray( bbp_set_post_lock( $topic_id ) );
		$this->assertFalse( bbp_check_post_lock( $topic_id ) );

		wp_set_current_user( $other_id );
		$this->assertSame( $owner_id, bbp_check_post_lock( $topic_id ) );

		update_post_meta( $topic_id, '_edit_lock', ( time() - ( 10 * MINUTE_IN_SECONDS ) ) . ':' . $owner_id );
		$this->assertFalse( bbp_check_post_lock( $topic_id ) );
	}

	/**
	 * Legacy locks use the last editor when no user ID is stored in the lock.
	 *
	 * @covers ::bbp_check_post_lock
	 */
	public function test_check_post_lock_uses_integer_last_editor_id() {
		$owner_id = $this->factory->user->create();
		$other_id = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		update_post_meta( $topic_id, '_edit_lock', (string) time() );
		update_post_meta( $topic_id, '_edit_last', $owner_id );

		wp_set_current_user( $owner_id );
		$this->assertFalse( bbp_check_post_lock( $topic_id ) );

		wp_set_current_user( $other_id );
		$this->assertSame( $owner_id, bbp_check_post_lock( $topic_id ) );
	}

	/**
	 * @covers ::bbp_check_post_lock
	 */
	public function test_check_post_lock_returns_false_for_zero_timestamp() {
		$owner_id = $this->factory->user->create();
		$other_id = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		update_post_meta( $topic_id, '_edit_lock', '0:' . $owner_id );
		wp_set_current_user( $other_id );

		$this->assertFalse( bbp_check_post_lock( $topic_id ) );
	}

	/**
	 * @covers ::bbp_check_post_lock
	 */
	public function test_check_post_lock_applies_window_filter() {
		$owner_id = $this->factory->user->create();
		$other_id = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();
		$default_window = null;
		$filter         = function( $window ) use ( &$default_window ) {
			$default_window = $window;
			return 15 * MINUTE_IN_SECONDS;
		};

		update_post_meta( $topic_id, '_edit_lock', ( time() - ( 10 * MINUTE_IN_SECONDS ) ) . ':' . $owner_id );
		wp_set_current_user( $other_id );
		add_filter( 'bbp_check_post_lock_window', $filter );

		try {
			$this->assertSame( $owner_id, bbp_check_post_lock( $topic_id ) );
			$this->assertSame( 3 * MINUTE_IN_SECONDS, $default_window );
		} finally {
			remove_filter( 'bbp_check_post_lock_window', $filter );
		}
	}

	/**
	 * @covers ::bbp_check_post_lock
	 */
	public function test_check_post_lock_filter_can_shorten_window() {
		$owner_id = $this->factory->user->create();
		$other_id = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();
		$filter   = function() {
			return MINUTE_IN_SECONDS;
		};

		update_post_meta( $topic_id, '_edit_lock', ( time() - ( 2 * MINUTE_IN_SECONDS ) ) . ':' . $owner_id );
		wp_set_current_user( $other_id );
		add_filter( 'bbp_check_post_lock_window', $filter );

		try {
			$this->assertFalse( bbp_check_post_lock( $topic_id ) );
		} finally {
			remove_filter( 'bbp_check_post_lock_window', $filter );
		}
	}
}
