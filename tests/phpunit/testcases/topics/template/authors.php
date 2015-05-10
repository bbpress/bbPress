<?php

/**
 * Tests for the `bbp_*_form_topic_author_*()` template functions.
 *
 * @group topics
 * @group template
 * @group authors
 */
class BBP_Tests_Topics_Template_Authors extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_author
	 * @covers ::bbp_get_topic_author
	 * @todo   Implement test_bbp_get_topic_author().
	 */
	public function test_bbp_get_topic_author() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_author_id
	 * @covers ::bbp_get_topic_author_id
	 */
	public function test_bbp_get_topic_author_id() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		$topic = bbp_get_topic_author_id( $t );
		$this->assertSame( $u, $topic );
	}

	/**
	 * @covers ::bbp_topic_author_display_name
	 * @covers ::bbp_get_topic_author_display_name
	 */
	public function test_bbp_get_topic_author_display_name() {
		$u = $this->factory->user->create( array(
			'display_name' => 'Barry B. Benson',
		) );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		$topic = bbp_get_topic_author_display_name( $t );
		$this->assertSame( 'Barry B. Benson', $topic );
	}

	/**
	 * @covers ::bbp_topic_author_avatar
	 * @covers ::bbp_get_topic_author_avatar
	 * @todo   Implement test_bbp_get_topic_author_avatar().
	 */
	public function test_bbp_get_topic_author_avatar() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_author_link
	 * @covers ::bbp_get_topic_author_link
	 * @todo   Implement test_bbp_get_topic_author_link().
	 */
	public function test_bbp_get_topic_author_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_author_url
	 * @covers ::bbp_get_topic_author_url
	 * @todo   Implement test_bbp_get_topic_author_url().
	 */
	public function test_bbp_get_topic_author_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_author_email
	 * @covers ::bbp_get_topic_author_email
	 * @todo   Implement test_bbp_get_topic_author_email().
	 */
	public function test_bbp_get_topic_author_email() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_author_role
	 * @covers ::bbp_get_topic_author_role
	 * @todo   Implement test_bbp_get_topic_author_role().
	 */
	public function test_bbp_get_topic_author_role() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
