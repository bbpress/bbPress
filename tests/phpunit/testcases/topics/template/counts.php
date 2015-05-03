<?php

/**
 * Tests for the topic component count template functions.
 *
 * @group topics
 * @group template
 * @group counts
 */
class BBP_Tests_Topics_Template_Counts extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_reply_count
	 * @covers ::bbp_get_topic_reply_count
	 */
	public function test_bbp_get_topic_reply_count() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$int_value = 9;
		$formatted_value = bbp_number_format( $int_value );

		$this->factory->reply->create_many( $int_value, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		// @todo Investigate caching issues in bbp_get_public_child_count()
		wp_cache_flush();

		bbp_update_topic_reply_count( $t );

		// Output
		$this->expectOutputString( $formatted_value );
		bbp_topic_reply_count( $t );

		// Formatted string
		$count = bbp_get_topic_reply_count( $t, false );
		$this->assertSame( $formatted_value, $count );

		// Integer
		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( $int_value, $count );
	}

	/**
	 * @covers ::bbp_topic_post_count
	 * @covers ::bbp_get_topic_post_count
	 */
	public function test_bbp_get_topic_post_count() {
		$f = $this->factory->forum->create();

		$int_topics  = 1;
		$int_replies = 9;
		$int_value   = $int_topics + $int_replies;
		$formatted_value = bbp_number_format( $int_value );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$this->factory->reply->create_many( $int_replies, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		// @todo Investigate caching issues in bbp_get_public_child_count()
		wp_cache_flush();

		bbp_update_topic_reply_count( $t );

		// Output
		$this->expectOutputString( $formatted_value );
		bbp_topic_post_count( $t );

		// Formatted string
		$count = bbp_get_topic_post_count( $t, false );
		$this->assertSame( $formatted_value, $count );

		// Integer
		$count = bbp_get_topic_post_count( $t, true );
		$this->assertSame( $int_value, $count );
	}

	/**
	 * @covers ::bbp_topic_reply_count_hidden
	 * @covers ::bbp_get_topic_reply_count_hidden
	 */
	public function test_bbp_get_topic_reply_count_hidden() {
		$f = $this->factory->forum->create();

		$int_value = 9;
		$formatted_value = bbp_number_format( $int_value );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$r = $this->factory->reply->create_many( $int_value, array(
			'post_parent' => $t,
			'post_status' => bbp_get_spam_status_id(),
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		bbp_update_topic_reply_count_hidden( $t );

		bbp_spam_reply( $r[7] );

		// Output
		$this->expectOutputString( $formatted_value );
		bbp_topic_reply_count_hidden( $t );

		// Formatted string
		$count = bbp_get_topic_reply_count_hidden( $t, false );
		$this->assertSame( $formatted_value, $count );

		// Integer
		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( $int_value, $count );
	}

	/**
	 * @covers ::bbp_topic_voice_count
	 * @covers ::bbp_get_topic_voice_count
	 */
	public function test_bbp_get_topic_voice_count() {
		$u = $this->factory->user->create_many( 2 );
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u[0],
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$int_value = 2;
		$formatted_value = bbp_number_format( $int_value );

		$this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'post_author' => $u[0],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		$this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'post_author' => $u[1],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		bbp_update_topic_voice_count( $t );

		// Output
		$this->expectOutputString( $formatted_value );
		bbp_topic_voice_count( $t );

		// Formatted string
		$count = bbp_get_topic_voice_count( $t, false );
		$this->assertSame( $formatted_value, $count );

		// Integer
		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( $int_value, $count );
	}
}
