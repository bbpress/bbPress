<?php

/**
 * Tests for the `bbp_*_form_forum_*` status template functions.
 *
 * @group forums
 * @group template
 * @group status
 */
class BBP_Tests_Forums_Template_Status extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_status
	 * @covers ::bbp_get_forum_status
	 */
	public function test_bbp_get_forum_status() {
		$f = $this->factory->forum->create();

		$forum = bbp_get_forum_status( $f );
		$this->assertSame( 'open', $forum );

		bbp_normalize_forum( $f );
		$this->assertSame( 'open', $forum );
	}

	/**
	 * @covers ::bbp_forum_type
	 * @covers ::bbp_get_forum_type
	 */
	public function test_bbp_get_forum_type() {
		$f = $this->factory->forum->create();

		$forum = bbp_get_forum_type( $f );
		$this->assertSame( 'forum', $forum );
	}

	/**
	 * @covers ::bbp_is_forum_category
	 */
	public function test_bbp_is_forum_category() {
		$f = $this->factory->forum->create_many( 2 );

		bbp_normalize_forum( $f[0] );

		$categorize_meta = get_post_meta( $f[0], '_bbp_forum_type', true );
		$this->assertSame( 'forum', $categorize_meta );

		$forum = bbp_is_forum_category( $f[0] );
		$this->assertFalse( $forum );

		bbp_categorize_forum( $f[1] );

		$categorize_meta = get_post_meta( $f[1], '_bbp_forum_type', true );
		$this->assertSame( 'category', $categorize_meta );

		$forum = bbp_is_forum_category( $f[1] );
		$this->assertTrue( $forum );

		$f = $this->factory->forum->create( array(
			'forum_meta' => array(
				'forum_type' => 'category',
			)
		) );

		$forum = bbp_is_forum_category( $f );
		$this->assertTrue( $forum );
	}

	/**
	 * @covers ::bbp_is_forum_open
	 */
	public function test_bbp_is_forum_open() {
		$f = $this->factory->forum->create();

		$forum = bbp_is_forum_open( $f );
		$this->assertTrue( $forum );
	}

	/**
	 * @covers ::bbp_is_forum_closed
	 */
	public function test_bbp_is_forum_closed() {
		$f = $this->factory->forum->create();

		$forum = bbp_is_forum_closed( $f );
		$this->assertFalse( $forum );

		bbp_close_forum( $f );

		$forum = bbp_is_forum_closed( $f );
		$this->assertTrue( $forum );
	}

	/**
	 * @covers ::bbp_is_forum_status
	 * @todo   Implement test_bbp_is_forum_status().
	 */
	public function test_bbp_is_forum_status() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_forum_public
	 */
	public function test_bbp_is_forum_public() {
		$f = $this->factory->forum->create();

		$forum = bbp_get_forum_visibility( $f );
		$this->assertSame( 'publish', $forum );

		$forum_status_id = bbp_get_public_status_id( $f );
		$this->assertSame( 'publish', $forum_status_id );

		$forum = bbp_is_forum_public( $f );
		$this->assertTrue( bbp_is_forum_public( $f ) );
	}

	/**
	 * @covers ::bbp_is_forum_private
	 */
	public function test_bbp_is_forum_private() {
		$f = $this->factory->forum->create( array(
			'post_status' => 'private',
		) );

		$forum = bbp_get_forum_visibility( $f );
		$this->assertSame( 'private', $forum );

		$forum_status_id = bbp_get_private_status_id( $f );
		$this->assertSame( 'private', $forum_status_id );

		$this->assertTrue( bbp_is_forum_private( $f ) );
	}

	/**
	 * @covers ::bbp_is_forum_hidden
	 */
	public function test_bbp_is_forum_hidden() {
		$f = $this->factory->forum->create( array(
			'post_status' => 'hidden',
		) );

		$forum = bbp_get_forum_visibility( $f );
		$this->assertSame( 'hidden', $forum );

		$forum_status_id = bbp_get_hidden_status_id( $f );
		$this->assertSame( 'hidden', $forum_status_id );

		$this->assertTrue( bbp_is_forum_hidden( $f ) );
	}
}
