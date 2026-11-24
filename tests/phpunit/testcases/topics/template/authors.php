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
	 */
	public function test_bbp_get_topic_author() {
		$user_id  = $this->factory->user->create( array( 'display_name' => 'Topic Author' ) );
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );

		$this->assertSame( 'Topic Author', bbp_get_topic_author( $topic_id ) );
		$this->expectOutputString( 'Topic Author' );
		bbp_topic_author( $topic_id );
	}

	/**
	 * @covers ::bbp_get_topic_author
	 */
	public function test_bbp_get_topic_author_uses_anonymous_name() {
		$topic_id = $this->factory->topic->create( array( 'post_author' => 0 ) );
		update_post_meta( $topic_id, '_bbp_anonymous_name', 'Guest Author' );

		$this->assertSame( 'Guest Author', bbp_get_topic_author( $topic_id ) );
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
	 */
	public function test_bbp_get_topic_author_avatar() {
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$avatar   = bbp_get_topic_author_avatar( $topic_id, 64 );

		$this->assertSame( get_avatar( $user_id, 64 ), $avatar );
		$this->expectOutputString( $avatar );
		bbp_topic_author_avatar( $topic_id, 64 );
	}

	/**
	 * @covers ::bbp_get_topic_author_avatar
	 */
	public function test_bbp_get_topic_author_avatar_uses_anonymous_email() {
		$topic_id = $this->factory->topic->create( array( 'post_author' => 0 ) );
		update_post_meta( $topic_id, '_bbp_anonymous_email', 'guest@example.org' );

		$this->assertSame( get_avatar( 'guest@example.org', 64 ), bbp_get_topic_author_avatar( $topic_id, 64 ) );
	}

	/**
	 * @covers ::bbp_topic_author_link
	 * @covers ::bbp_get_topic_author_link
	 */
	public function test_bbp_get_topic_author_link() {
		$user_id  = $this->factory->user->create( array( 'display_name' => 'Topic Author' ) );
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$args     = array( 'post_id' => $topic_id, 'type' => 'name' );
		$link     = bbp_get_topic_author_link( $args );

		$this->assertStringContainsString( 'Topic Author', $link );
		$this->assertStringContainsString( 'bbp-author-name', $link );
		$this->assertStringContainsString( esc_url( bbp_get_user_profile_url( $user_id ) ), $link );
		$this->expectOutputString( $link );
		bbp_topic_author_link( $args );
	}

	/**
	 * @covers ::bbp_get_topic_author_link
	 */
	public function test_bbp_get_topic_author_link_does_not_link_anonymous_name() {
		$topic_id = $this->factory->topic->create( array( 'post_author' => 0 ) );
		update_post_meta( $topic_id, '_bbp_anonymous_name', 'Guest Author' );
		update_post_meta( $topic_id, '_bbp_anonymous_website', 'https://example.org/guest' );
		$link     = bbp_get_topic_author_link( array( 'post_id' => $topic_id, 'type' => 'name' ) );

		$this->assertStringContainsString( 'Guest Author', $link );
		$this->assertStringNotContainsString( '<a ', $link );
	}

	/**
	 * @covers ::bbp_get_topic_author_link
	 */
	public function test_bbp_get_topic_author_link_skips_invalid_urls() {
		$user_id  = $this->factory->user->create( array( 'display_name' => 'Topic Author' ) );
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$args     = array( 'post_id' => $topic_id, 'type' => 'name' );

		foreach ( array( false, '/relative-profile/', 'javascript:alert(1)' ) as $invalid ) {
			$filter = static function() use ( $invalid ) {
				return $invalid;
			};
			add_filter( 'bbp_get_topic_author_url', $filter );
			try {
				$link = bbp_get_topic_author_link( $args );
				$this->assertStringContainsString( 'Topic Author', $link );
				$this->assertStringNotContainsString( '<a ', $link );
			} finally {
				remove_filter( 'bbp_get_topic_author_url', $filter );
			}
		}
	}

	/**
	 * @covers ::bbp_topic_author_url
	 * @covers ::bbp_get_topic_author_url
	 */
	public function test_bbp_get_topic_author_url() {
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );

		$this->assertSame( bbp_get_user_profile_url( $user_id ), bbp_get_topic_author_url( $topic_id ) );
		$this->expectOutputString( esc_url( bbp_get_user_profile_url( $user_id ) ) );
		bbp_topic_author_url( $topic_id );
	}

	/**
	 * @covers ::bbp_get_topic_author_url
	 */
	public function test_bbp_get_topic_author_url_uses_anonymous_website() {
		$topic_id = $this->factory->topic->create( array( 'post_author' => 0 ) );

		$this->assertSame( '', bbp_get_topic_author_url( $topic_id ) );
		update_post_meta( $topic_id, '_bbp_anonymous_website', 'https://example.org/guest' );
		$this->assertSame( 'https://example.org/guest', bbp_get_topic_author_url( $topic_id ) );
	}

	/**
	 * @covers ::bbp_topic_author_email
	 * @covers ::bbp_get_topic_author_email
	 */
	public function test_bbp_get_topic_author_email() {
		$user_id  = $this->factory->user->create( array( 'user_email' => 'topic-author@example.org' ) );
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );

		$this->assertSame( 'topic-author@example.org', bbp_get_topic_author_email( $topic_id ) );
		$this->expectOutputString( 'topic-author@example.org' );
		bbp_topic_author_email( $topic_id );
	}

	/**
	 * @covers ::bbp_get_topic_author_email
	 */
	public function test_bbp_get_topic_author_email_uses_anonymous_meta() {
		$topic_id = $this->factory->topic->create( array( 'post_author' => 0 ) );

		$this->assertSame( '', bbp_get_topic_author_email( $topic_id ) );
		update_post_meta( $topic_id, '_bbp_anonymous_email', 'guest@example.org' );
		$this->assertSame( 'guest@example.org', bbp_get_topic_author_email( $topic_id ) );
	}

	/**
	 * @covers ::bbp_topic_author_role
	 * @covers ::bbp_get_topic_author_role
	 */
	public function test_bbp_get_topic_author_role() {
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$args     = array( 'topic_id' => $topic_id );
		$role     = bbp_get_user_display_role( $user_id );
		$expected = '<div class="bbp-author-role bbp-role-' . sanitize_key( $role ) . '">' . $role . '</div>';

		$this->assertSame( $expected, bbp_get_topic_author_role( $args ) );
		$this->expectOutputString( $expected );
		bbp_topic_author_role( $args );
	}

	/**
	 * @covers ::bbp_get_topic_author_role
	 */
	public function test_topic_author_role_skips_non_string_wrappers() {
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$args     = array( 'topic_id' => $topic_id, 'before' => array( 'invalid' ), 'after' => array( 'invalid' ) );

		$this->assertSame( bbp_get_user_display_role( $user_id ), bbp_get_topic_author_role( $args ) );
	}
}
