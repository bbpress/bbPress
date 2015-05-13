<?php

/**
 * Tests for the `bbp_*_form_forum_post_type_*()` functions.
 *
 * @group forums
 * @group template
 * @group post_type
 */
class BBP_Tests_Forums_Template_Post_Type extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_post_type
	 * @covers ::bbp_get_forum_post_type
	 */
	public function test_bbp_get_forum_post_type() {
		$f = $this->factory->forum->create();

		$fobj = get_post_type_object( 'forum' );
		$this->assertInstanceOf( 'stdClass', $fobj );
		$this->assertEquals( 'forum', $fobj->name );

		// Test some defaults
		$this->assertTrue( is_post_type_hierarchical( 'forum' ) );

		$forum_type = bbp_forum_post_type( $f );
		$this->expectOutputString( 'forum', $forum_type );

		$forum_type = bbp_get_forum_post_type( $f );
		$this->assertSame( 'forum', $forum_type );
	}

	/**
	 * @covers ::bbp_get_forum_post_type_labels
	 * @todo   Implement test_bbp_get_forum_post_type_labels().
	 */
	public function test_bbp_get_forum_post_type_labels() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_forum_post_type_rewrite
	 * @todo   Implement test_bbp_get_forum_post_type_rewrite().
	 */
	public function test_bbp_get_forum_post_type_rewrite() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_forum_post_type_supports
	 * @todo   Implement test_bbp_get_forum_post_type_supports().
	 */
	public function test_bbp_get_forum_post_type_supports() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
