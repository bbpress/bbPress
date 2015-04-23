<?php

/**
 * Tests for the user component count template functions.
 *
 * @group users
 * @group template
 * @group counts
 */
class BBP_Tests_Users_Template_Counts extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_user_id
	 * @covers ::bbp_get_user_id
	 */
	public function test_bbp_get_user_id() {
		$u = $this->factory->user->create();

		$user_id = bbp_get_user_id( $u );
		$this->assertSame( $u, $user_id );
	}
}
