<?php

/**
 * Tests for the `bbp_*_form_topic_post_type_*()` functions.
 *
 * @group topics
 * @group template
 * @group post_type
 */
class BBP_Tests_Topics_Template_Post_Type extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_post_type
	 * @covers ::bbp_get_topic_post_type
	 */
	public function test_bbp_topic_post_type() {
		$t = $this->factory->topic->create();

		$tobj = get_post_type_object( 'topic' );
		$this->assertInstanceOf( 'stdClass', $tobj );
		$this->assertEquals( 'topic', $tobj->name );

		// Test some defaults
		$this->assertFalse( is_post_type_hierarchical( 'topic' ) );

		$topic_type = bbp_topic_post_type( $t );
		$this->expectOutputString( 'topic', $topic_type );

		$topic_type = bbp_get_topic_post_type( $t );
		$this->assertSame( 'topic', $topic_type );
	}

	/**
	 * @covers ::bbp_get_topic_post_type_labels
	 * @todo   Implement test_bbp_get_topic_post_type_labels().
	 */
	public function test_bbp_get_topic_post_type_labels() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topic_post_type_rewrite
	 * @todo   Implement test_bbp_get_topic_post_type_rewrite().
	 */
	public function test_bbp_get_topic_post_type_rewrite() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topic_post_type_supports
	 * @todo   Implement test_bbp_get_topic_post_type_supports().
	 */
	public function test_bbp_get_topic_post_type_supports() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
