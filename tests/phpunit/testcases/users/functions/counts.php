<?php

/**
 * Tests for the user component count functions.
 *
 * @group users
 * @group functions
 * @group counts
 */
class BBP_Tests_Users_Functions_Counts extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_update_user_reply_count
	 */
	function test_bbp_update_user_topic_count() {
		$u = $this->factory->user->create();
		$value = 9;

		bbp_update_user_topic_count( $u, $value );

		$count = bbp_get_user_topic_count( $u, true );
		$this->assertSame( $value, $count );
	}

	/**
	 * @covers ::bbp_update_user_reply_count
	 */
	function test_bbp_update_user_reply_count() {
		$u = $this->factory->user->create();
		$value = 9;

		bbp_update_user_reply_count( $u, $value );

		$count = bbp_get_user_reply_count( $u, true );
		$this->assertSame( $value, $count );
	}

	/**
	 * @covers ::bbp_user_topic_count
	 * @covers ::bbp_get_user_topic_count
	 */
	function test_bbp_get_user_topic_count() {
		$u = $this->factory->user->create();
		$value = 9;
		$formatted_value = bbp_number_format( $value );

		bbp_update_user_topic_count( $u, $value );

		$this->expectOutputString( $formatted_value );
		bbp_user_topic_count( $u );

		$count = bbp_get_user_topic_count( $u, false );
		$this->assertSame( $formatted_value, $count );

		$count = bbp_get_user_topic_count( $u, true );
		$this->assertSame( (int) $value, $count );
	}

	/**
	 * @covers ::bbp_user_reply_count
	 * @covers ::bbp_get_user_reply_count
	 */
	function test_bbp_get_user_reply_count() {
		$u = $this->factory->user->create();
		$value = 9;
		$formatted_value = bbp_number_format( $value );

		bbp_update_user_reply_count( $u, $value );

		$this->expectOutputString( $formatted_value );
		bbp_user_reply_count( $u );

		$count = bbp_get_user_reply_count( $u, false );
		$this->assertSame( $formatted_value, $count );

		$count = bbp_get_user_reply_count( $u, true );
		$this->assertSame( (int) $value, $count );
	}

	/**
	 * @covers ::bbp_user_post_count
	 * @covers ::bbp_get_user_post_count
	 */
	function test_bbp_get_user_post_count() {
		$u = $this->factory->user->create();
		$value = 9;
		$integer = true;

		// Add reply count
		bbp_update_user_reply_count( $u, $value );

		// Count
		$count = bbp_get_user_post_count( $u, $integer );
		$this->assertSame( $value, $count );

		// Add topic count
		bbp_update_user_topic_count( $u, $value );
		$double_value = $value * 2;

		// Count + Count
		$double_count = bbp_get_user_post_count( $u, true );
		$this->assertSame( $double_value, $double_count );

		// Output
		$double_formatted_value = bbp_number_format( $double_value );
		$this->expectOutputString( $double_formatted_value );
		bbp_user_post_count( $u );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 */
	public function test_bbp_get_user_topics_started() {
		$u = $this->factory->user->create();

		$has_topics = bbp_get_user_topics_started( $u );
		$this->assertFalse( $has_topics );

		$t = $this->factory->topic->create_many( 15, array(
			'post_author' => $u,
		) );

		bbp_update_topic( array(
			'topic_id' => $t,
		) );

		$has_topics = bbp_get_user_topics_started( $u );
		$this->assertTrue( $has_topics );
	}

	/**
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_bbp_get_user_replies_created() {
		$u = $this->factory->user->create();

		$has_replies = bbp_get_user_replies_created( $u );
		$this->assertFalse( $has_replies );

		$r = $this->factory->reply->create_many( 15, array(
			'post_author' => $u,
		) );

		bbp_update_reply( array(
			'reply_id' => $r,
		) );

		$has_replies = bbp_get_user_replies_created( $u );
		$this->assertTrue( $has_replies );
	}

	/**
	 * @covers ::bbp_get_total_users
	 */
	public function test_bbp_get_total_users() {
		$this->factory->user->create_many( 15 );

		$users = (int) bbp_get_total_users();

		// 15 + 1, the + 1 is the default admin user
		$this->assertSame( 16, $users );
	}

	/**
	 * @covers ::bbp_get_user_topic_count_raw
	 */
	public function test_bbp_get_user_topic_count_raw() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create_many( 15, array(
			'post_author' => $u,
		) );

		$count = bbp_get_user_topic_count_raw( $u );
		$this->assertSame( 15, $count );

		$t = $this->factory->topic->create_many( 15, array(
			'post_author' => $u,
		) );

		$count = bbp_get_user_topic_count_raw( $u );
		$this->assertSame( 30, $count );
	}

	/**
	 * @covers ::bbp_get_user_reply_count_raw
	 */
	public function test_bbp_get_user_reply_count_raw() {
		$u = $this->factory->user->create();

		$r = $this->factory->reply->create_many( 15, array(
			'post_author' => $u,
		) );

		$count = bbp_get_user_reply_count_raw( $u );
		$this->assertSame( 15, $count );

		$r = $this->factory->reply->create_many( 15, array(
			'post_author' => $u,
		) );

		$count = bbp_get_user_reply_count_raw( $u );
		$this->assertSame( 30, $count );
	}

	/**
	 * @covers ::bbp_bump_user_topic_count
	 */
	public function test_bbp_bump_user_topic_count() {
		$u = $this->factory->user->create();
		$value = 9;
		$integer = true;

		bbp_update_user_topic_count( $u, $value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $value, $count );

		bbp_bump_user_topic_count( $u );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $value + 1, $count );
	}

	/**
	 * @covers ::bbp_bump_user_reply_count
	 */
	public function test_bbp_bump_user_reply_count() {
		$u = $this->factory->user->create();
		$value = 9;
		$integer = true;

		bbp_update_user_reply_count( $u, $value );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $value, $count );

		bbp_bump_user_reply_count( $u );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $value + 1, $count );
	}

	/**
	 * @covers ::bbp_increase_user_topic_count
	 */
	public function test_bbp_increase_user_topic_count() {
		$u = $this->factory->user->create();
		$value = 9;
		$integer = true;

		bbp_update_user_topic_count( $u, $value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $value, $count );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		bbp_increase_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $value + 1, $count );
	}

	/**
	 * @covers ::bbp_increase_user_reply_count
	 */
	public function test_bbp_increase_user_reply_count() {
		$u = $this->factory->user->create();
		$value = 9;
		$integer = true;

		bbp_update_user_reply_count( $u, $value );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $value, $count );

		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create_many( $value, array(
			'post_parent' => $t,
		) );

		bbp_increase_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $value, $count );
	}

	/**
	 * @covers ::bbp_decrease_user_topic_count
	 */
	public function test_bbp_decrease_user_topic_count() {
		$u = $this->factory->user->create();
		$value = 9;
		$integer = true;

		bbp_bump_user_topic_count( $u, $value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $value, $count );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		// Minus 1
		bbp_decrease_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $value - 1, $count );

		// Minus 2
		bbp_decrease_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $value - 2, $count );
	}

	/**
	 * @covers ::bbp_decrease_user_reply_count
	 */
	public function test_bbp_decrease_user_reply_count() {
		$u = $this->factory->user->create();

		bbp_bump_user_reply_count( $u, 15 );

		$count = bbp_get_user_reply_count( $u, true );
		$this->assertSame( 15, $count );

		$r = $this->factory->reply->create( array(
			'post_author' => $u,
		) );

		bbp_decrease_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u );
		$this->assertSame( '14', $count );

		bbp_decrease_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u, true );
		$this->assertSame( 13, $count );
	}
}
