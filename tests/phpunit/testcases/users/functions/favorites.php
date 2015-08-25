<?php

/**
 * Tests for the user component favorite functions.
 *
 * @group users
 * @group functions
 * @group favorites
 */
class BBP_Tests_Users_Functions_Favorites extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_topic_favoriters
	 */
	public function test_bbp_get_topic_favoriters() {
		$u = $this->factory->user->create_many( 3 );
		$t = $this->factory->topic->create();

		// Add topic favorites.
		bbp_add_user_favorite( $u[0], $t );
		bbp_add_user_favorite( $u[1], $t );

		$expected = array( $u[0], $u[1] );
		$favoriters = bbp_get_topic_favoriters( $t );

		$this->assertEquals( $expected, $favoriters );

		// Add topic favorites.
		bbp_add_user_favorite( $u[2], $t );

		$expected = array( $u[0], $u[1], $u[2] );
		$favoriters = bbp_get_topic_favoriters( $t );

		$this->assertEquals( $expected, $favoriters );

		// Remove user favorite.
		bbp_remove_user_favorite( $u[1], $t );

		$expected = array( $u[0], $u[2] );
		$favoriters = bbp_get_topic_favoriters( $t );

		$this->assertEquals( $expected, $favoriters );
	}

	/**
	 * @covers ::bbp_get_user_favorites
	 */
	public function test_bbp_get_user_favorites() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic favorites.
		bbp_add_user_favorite( $u, $t[0] );
		bbp_add_user_favorite( $u, $t[1] );
		bbp_add_user_favorite( $u, $t[2] );

		$expected = bbp_has_topics( array( 'post__in' => array( $t[0], $t[1], $t[2] ) ) );
		$favorites = bbp_get_user_favorites( $u );

		$this->assertEquals( $expected, $favorites );

		// Remove user favorite.
		bbp_remove_user_favorite( $u, $t[1] );

		$expected = bbp_has_topics( array( 'post__in' => array( $t[0], $t[2] ) ) );
		$favorites = bbp_get_user_favorites( $u );

		$this->assertEquals( $expected, $favorites );
	}

	/**
	 * @covers ::bbp_get_user_favorites_topic_ids
	 */
	public function test_bbp_get_user_favorites_topic_ids() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic favorites.
		bbp_add_user_favorite( $u, $t[0] );
		bbp_add_user_favorite( $u, $t[1] );
		bbp_add_user_favorite( $u, $t[2] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEquals( array( $t[0], $t[1], $t[2] ), $favorites );

		// Remove user favorite.
		bbp_remove_user_favorite( $u, $t[1] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEquals( array( $t[0], $t[2] ), $favorites );
	}

	/**
	 * @covers ::bbp_is_user_favorite
	 */
	public function test_bbp_is_user_favorite() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();

		$favorite = bbp_is_user_favorite( $u, $t );

		$this->assertFalse( $favorite );

		// Add topic favorite.
		bbp_add_user_favorite( $u, $t );

		$favorite = bbp_is_user_favorite( $u, $t );

		$this->assertTrue( $favorite );
	}

	/**
	 * @covers ::bbp_add_user_favorite
	 */
	public function test_bbp_add_user_favorite() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic favorites.
		update_user_option( $u, '_bbp_favorites', $t[0] );

		// Add user favorite.
		bbp_add_user_favorite( $u, $t[1] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEquals( array( $t[0], $t[1] ), $favorites );

		// Add user favorite.
		bbp_add_user_favorite( $u, $t[2] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEquals( array( $t[0], $t[1], $t[2] ), $favorites );
	}

	/**
	 * @covers ::bbp_remove_user_favorite
	 */
	public function test_bbp_remove_user_favorite() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic favorites.
		update_user_option( $u, '_bbp_favorites', implode( ',', $t ) );

		// Remove user favorite.
		bbp_remove_user_favorite( $u, $t[2] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEquals( array( $t[0], $t[1] ), $favorites );

		// Remove user favorite.
		bbp_remove_user_favorite( $u, $t[1] );

		$favorites = bbp_get_user_favorites_topic_ids( $u );

		$this->assertEquals( array( $t[0] ), $favorites );
	}

	/**
	 * @covers ::bbp_favorites_handler
	 * @todo   Implement test_bbp_favorites_handler().
	 */
	public function test_bbp_favorites_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
