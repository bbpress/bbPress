<?php

/**
 * Tests for theme-side post edit locks.
 *
 * @group common
 * @group locking
 */
class BBP_Tests_Common_Post_Lock extends BBP_UnitTestCase {

	/**
	 * A current lock belongs to its owner, and expires for other users.
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
}
