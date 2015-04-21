<?php

/**
 * Tests for the `bbp_*_form_forum_author_*()` template functions.
 *
 * @group forums
 * @group template
 * @group authors
 */
class BBP_Tests_Forums_Template_Authors extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_author_id
	 * @covers ::bbp_get_forum_author_id
	 */
	public function test_bbp_get_forum_author_id() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create( array(
			'post_author' => $u,
		) );

		$forum = bbp_get_forum_author_id( $f );
		$this->assertSame( $u, $forum );
	}

	/**
	 * @covers ::bbp_forum_author_display_name
	 * @covers ::bbp_get_forum_author_display_name
	 */
	public function test_bbp_get_forum_author_display_name() {
		$u = $this->factory->user->create( array(
			'display_name' => 'Barry B. Benson',
		) );

		$f = $this->factory->forum->create( array(
			'post_author' => $u,
		) );

		$forum = bbp_get_forum_author_display_name( $f );
		$this->assertSame( 'Barry B. Benson', $forum );
	}
}
