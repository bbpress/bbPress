<?php

/**
 * Tests for the `bbp_*_form_reply_post_type_*()` functions.
 *
 * @group replies
 * @group template
 * @group post_type
 */
class BBP_Tests_Replies_Template_Post_Type extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_reply_post_type
	 * @covers ::bbp_get_reply_post_type
	 */
	public function test_bbp_reply_post_type() {
		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$robj = get_post_type_object( 'reply' );
		$this->assertInstanceOf( 'stdClass', $robj );
		$this->assertEquals( 'reply', $robj->name );

		// Test some defaults
		$this->assertFalse( is_post_type_hierarchical( 'topic' ) );
		$reply_type = bbp_reply_post_type( $r );
		$this->expectOutputString( 'reply', $reply_type );

		$reply_type = bbp_get_reply_post_type( $r );
		$this->assertSame( 'reply', $reply_type );
	}

	/**
	 * @covers ::bbp_get_reply_post_type_labels
	 * @todo   Implement test_bbp_get_reply_post_type_labels().
	 */
	public function test_bbp_get_reply_post_type_labels() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_reply_post_type_rewrite
	 * @todo   Implement test_bbp_get_reply_post_type_rewrite().
	 */
	public function test_bbp_get_reply_post_type_rewrite() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_reply_post_type_supports
	 * @todo   Implement test_bbp_get_reply_post_type_supports().
	 */
	public function test_bbp_get_reply_post_type_supports() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
