<?php

/**
 * Tests for the `bbp_*_form_forum_*` visibility template functions.
 *
 * @group forums
 * @group template
 * @group visibility
 */
class BBP_Tests_Forums_Template_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_visibility
	 * @covers ::bbp_get_forum_visibility
	 */
	public function test_bbp_get_forum_visibility() {
		$f = $this->factory->forum->create();

		$forum = bbp_get_forum_visibility( $f, bbp_get_public_status_id(), false );
		$this->assertSame( 'publish', $forum );

		$f = $this->factory->forum->create();

		bbp_privatize_forum( $f );
		$forum = bbp_get_forum_visibility( $f, bbp_get_private_status_id(), false );
		$this->assertSame( 'private', $forum );

		$f = $this->factory->forum->create();

		bbp_hide_forum( $f );
		$forum = bbp_get_forum_visibility( $f, bbp_get_hidden_status_id(), false );
		$this->assertSame( 'hidden', $forum );
	}

	/**
	 * @covers ::bbp_is_forum_visibility
	 */
	public function test_bbp_is_forum_visibility() {
		$f = $this->factory->forum->create();

		bbp_normalize_forum( $f );

		$forum = bbp_is_forum_visibility( $f, bbp_get_public_status_id(), false );
		$this->assertTrue( $forum );

		$f = $this->factory->forum->create();

		bbp_privatize_forum( $f );
		$forum = bbp_is_forum_visibility( $f, bbp_get_private_status_id(), false );
		$this->assertTrue( $forum );

		$f = $this->factory->forum->create();

		bbp_hide_forum( $f );
		$forum = bbp_is_forum_visibility( $f, bbp_get_hidden_status_id(), false );
		$this->assertTrue( $forum );
	}

	/**
	 * @covers ::bbp_suppress_private_forum_meta
	 * @todo   Implement test_bbp_suppress_private_forum_meta().
	 */
	public function test_bbp_suppress_private_forum_meta() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_suppress_private_author_link
	 * @todo   Implement test_bbp_suppress_private_author_link().
	 */
	public function test_bbp_suppress_private_author_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
