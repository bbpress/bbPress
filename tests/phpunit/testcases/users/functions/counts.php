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
		$int_value = 3;

		bbp_update_user_topic_count( $u, $int_value );

		$count = bbp_get_user_topic_count( $u, true );
		$this->assertSame( $int_value, $count );
	}

	/**
	 * @covers ::bbp_update_user_reply_count
	 */
	function test_bbp_update_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;

		bbp_update_user_reply_count( $u, $int_value );

		$count = bbp_get_user_reply_count( $u, true );
		$this->assertSame( $int_value, $count );
	}

	/**
	 * @covers ::bbp_user_topic_count
	 * @covers ::bbp_get_user_topic_count
	 */
	function test_bbp_get_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$formatted_value = bbp_number_format( $int_value );

		bbp_update_user_topic_count( $u, $int_value );

		$this->expectOutputString( $formatted_value );
		bbp_user_topic_count( $u );

		$count = bbp_get_user_topic_count( $u, false );
		$this->assertSame( $formatted_value, $count );

		$count = bbp_get_user_topic_count( $u, true );
		$this->assertSame( (int) $int_value, $count );
	}

	/**
	 * @covers ::bbp_user_reply_count
	 * @covers ::bbp_get_user_reply_count
	 */
	function test_bbp_get_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$formatted_value = bbp_number_format( $int_value );

		bbp_update_user_reply_count( $u, $int_value );

		$this->expectOutputString( $formatted_value );
		bbp_user_reply_count( $u );

		$count = bbp_get_user_reply_count( $u, false );
		$this->assertSame( $formatted_value, $count );

		$count = bbp_get_user_reply_count( $u, true );
		$this->assertSame( (int) $int_value, $count );
	}

	/**
	 * @covers ::bbp_user_post_count
	 * @covers ::bbp_get_user_post_count
	 */
	function test_bbp_get_user_post_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		// Add reply count
		bbp_update_user_reply_count( $u, $int_value );

		// Count
		$count = bbp_get_user_post_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		// Add topic count
		bbp_update_user_topic_count( $u, $int_value );
		$double_value = $int_value * 2;

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

		$t = $this->factory->topic->create_many( 3, array(
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

		$t = $this->factory->topic->create();

		$has_replies = bbp_get_user_replies_created( $u );
		$this->assertFalse( $has_replies );

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$has_replies = bbp_get_user_replies_created( $u );
		$this->assertTrue( $has_replies );
	}

	/**
	 * @covers ::bbp_get_total_users
	 */
	public function test_bbp_get_total_users() {
		$this->factory->user->create_many( 3 );

		$users = (int) bbp_get_total_users();

		// 15 + 1, the + 1 is the default admin user
		$this->assertSame( 4, $users );
	}

	/**
	 * @covers ::bbp_get_user_topic_count_raw
	 */
	public function test_bbp_get_user_topic_count_raw() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create_many( 3, array(
			'post_author' => $u,
		) );

		$count = bbp_get_user_topic_count_raw( $u );
		$this->assertSame( 3, $count );

		$t = $this->factory->topic->create_many( 3, array(
			'post_author' => $u,
		) );

		$count = bbp_get_user_topic_count_raw( $u );
		$this->assertSame( 6, $count );
	}

	/**
	 * @covers ::bbp_get_user_reply_count_raw
	 */
	public function test_bbp_get_user_reply_count_raw() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_user_reply_count_raw( $u );
		$this->assertSame( 3, $count );

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_user_reply_count_raw( $u );
		$this->assertSame( 6, $count );
	}

	/**
	 * @covers ::bbp_bump_user_topic_count
	 */
	public function test_bbp_bump_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_topic_count( $u, $int_value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		bbp_bump_user_topic_count( $u );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value + 1, $count );
	}

	/**
	 * @covers ::bbp_bump_user_reply_count
	 */
	public function test_bbp_bump_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_reply_count( $u, $int_value );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		bbp_bump_user_reply_count( $u );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value + 1, $count );
	}

	/**
	 * @covers ::bbp_increase_user_topic_count
	 */
	public function test_bbp_increase_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_topic_count( $u, $int_value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		bbp_increase_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value + 1, $count );
	}

	/**
	 * @covers ::bbp_increase_user_reply_count
	 */
	public function test_bbp_increase_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_reply_count( $u, $int_value );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create_many( $int_value, array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		bbp_increase_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value, $count );
	}

	/**
	 * @covers ::bbp_decrease_user_topic_count
	 */
	public function test_bbp_decrease_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_topic_count( $u, $int_value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		// Minus 1
		bbp_decrease_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value - 1, $count );

		// Minus 2
		bbp_decrease_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value - 2, $count );
	}

	/**
	 * @covers ::bbp_decrease_user_reply_count
	 */
	public function test_bbp_decrease_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_reply_count( $u, $int_value );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		// Minus 1
		bbp_decrease_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value - 1, $count );

		// Minus 2
		bbp_decrease_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value - 2, $count );
	}
}
