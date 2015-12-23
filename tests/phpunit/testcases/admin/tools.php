<?php

/**
 * Tests for the admin tools functions.
 *
 * @group tools
 */
class BBP_Tests_Admin_Tools extends BBP_UnitTestCase {
	protected $old_current_user = 0;

	public function setUp() {
		parent::setUp();
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->keymaster_id = get_current_user_id();
		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );

		if ( ! function_exists( 'bbp_admin_repair' ) ) {
			require_once( BBP_PLUGIN_DIR . 'includes/admin/tools.php' );
		}
	}

	public function tearDown() {
		parent::tearDown();
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_admin_repair
	 * @todo   Implement test_bbp_admin_repair().
	 */
	public function test_bbp_admin_repair() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 * @todo   Implement test_bbp_admin_repair_handler().
	 */
	public function test_bbp_admin_repair_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_tools_repair_help
	 * @todo   Implement test_bbp_admin_tools_repair_help().
	 */
	public function test_bbp_admin_tools_repair_help() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_tools_reset_help
	 * @todo   Implement test_bbp_admin_tools_reset_help().
	 */
	public function test_bbp_admin_tools_reset_help() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_tools_converter_help
	 * @todo   Implement test_bbp_admin_tools_converter_help().
	 */
	public function test_bbp_admin_tools_converter_help() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_tools_feedback
	 * @todo   Implement test_bbp_admin_tools_feedback().
	 */
	public function test_bbp_admin_tools_feedback() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_list
	 * @todo   Implement test_bbp_admin_repair_list().
	 */
	public function test_bbp_admin_repair_list() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_topic_reply_count
	 * @todo   Implement test_bbp_admin_repair_topic_reply_count().
	 */
	public function test_bbp_admin_repair_topic_reply_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count
	 */
	public function test_bbp_admin_repair_topic_voice_count() {
		$u = $this->factory->user->create_many( 2 );

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_author' => $u[0],
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_author' => $u[1],
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '3', $count );

		// Delete the topic _bbp_voice_count meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_voice_count' ) );

		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '0', $count );

		// Repair the topic voice count meta.
		bbp_admin_repair_topic_voice_count();

		bbp_clean_post_cache( $t );

		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '3', $count );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count
	 */
	public function test_bbp_admin_repair_topic_hidden_reply_count() {

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 1, $count );

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_spam_reply( $r[0] );
		bbp_unapprove_reply( $r[2] );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 2, $count );

		// Delete the topic _bbp_reply_count_hidden meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_reply_count_hidden' ) );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 0, $count );

		// Repair the topic hidden reply count meta.
		bbp_admin_repair_topic_hidden_reply_count();

		bbp_clean_post_cache( $t );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * @covers ::bbp_admin_repair_group_forum_relationship
	 * @todo   Implement test_bbp_admin_repair_group_forum_relationship().
	 */
	public function test_bbp_admin_repair_group_forum_relationship() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_forum_topic_count
	 * @todo   Implement test_bbp_admin_repair_forum_topic_count().
	 */
	public function test_bbp_admin_repair_forum_topic_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_forum_reply_count
	 * @todo   Implement test_bbp_admin_repair_forum_reply_count().
	 */
	public function test_bbp_admin_repair_forum_reply_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count
	 * @todo   Implement test_bbp_admin_repair_user_topic_count().
	 */
	public function test_bbp_admin_repair_user_topic_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_reply_count
	 * @todo   Implement test_bbp_admin_repair_user_reply_count().
	 */
	public function test_bbp_admin_repair_user_reply_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites
	 * @todo   Implement test_bbp_admin_repair_user_favorites().
	 */
	public function test_bbp_admin_repair_user_favorites() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions
	 * @todo   Implement test_bbp_admin_repair_user_topic_subscriptions().
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions
	 * @todo   Implement test_bbp_admin_repair_user_forum_subscriptions().
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_roles
	 * @todo   Implement test_bbp_admin_repair_user_roles().
	 */
	public function test_bbp_admin_repair_user_roles() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_freshness
	 * @todo   Implement test_bbp_admin_repair_freshness().
	 */
	public function test_bbp_admin_repair_freshness() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_sticky
	 * @todo   Implement test_bbp_admin_repair_sticky().
	 */
	public function test_bbp_admin_repair_sticky() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_closed_topics
	 * @todo   Implement test_bbp_admin_repair_closed_topics().
	 */
	public function test_bbp_admin_repair_closed_topics() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_forum_visibility
	 * @todo   Implement test_bbp_admin_repair_forum_visibility().
	 */
	public function test_bbp_admin_repair_forum_visibility() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_forum_meta
	 */
	public function test_bbp_admin_repair_forum_meta() {

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Forums should NOT have a _bbp_forum_id meta key
		$this->assertEquals( array(), get_post_meta( $f, '_bbp_forum_id', false ) );

		// Topics should have a _bbp_forum_id meta key
		$this->assertSame( $f, bbp_get_topic_forum_id( $t ) );

		// Replies should have a _bbp_forum_id meta key
		$this->assertSame( $f, bbp_get_reply_forum_id( $r ) );

		// Delete the topic and reply _bbp_forum_id meta key
		$this->assertTrue( delete_post_meta_by_key( '_bbp_forum_id' ) );

		// Check the _bbp_forum_id meta key is deleted
		$this->assertEquals( array(), get_post_meta( $f, '_bbp_forum_id', false ) );
		$this->assertEquals( array(), get_post_meta( $t, '_bbp_forum_id', false ) );
		$this->assertEquals( array(), get_post_meta( $r, '_bbp_forum_id', false ) );

		// Repair the forum meta
		bbp_admin_repair_forum_meta();

		bbp_clean_post_cache( $f );
		bbp_clean_post_cache( $t );
		bbp_clean_post_cache( $r );

		// Forums should NOT have a _bbp_forum_id meta key
		$this->assertEquals( array(), get_post_meta( $f, '_bbp_forum_id', false ) );

		// Topics should have a _bbp_forum_id meta key
		$this->assertEquals( array( $f ), get_post_meta( $t, '_bbp_forum_id', false ) );
		$this->assertSame( $f, bbp_get_topic_forum_id( $t ) );

		// Replies should have a _bbp_forum_id meta key
		$this->assertEquals( array( $f ), get_post_meta( $r, '_bbp_forum_id', false ) );
		$this->assertSame( $f, bbp_get_reply_forum_id( $r ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_meta
	 * @todo   Implement test_bbp_admin_repair_topic_meta().
	 */
	public function test_bbp_admin_repair_topic_meta() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_repair_reply_menu_order
	 * @todo   Implement test_bbp_admin_repair_reply_menu_order().
	 */
	public function test_bbp_admin_repair_reply_menu_order() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_reset
	 * @todo   Implement test_bbp_admin_reset().
	 */
	public function test_bbp_admin_reset() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_admin_reset_handler
	 * @todo   Implement test_bbp_admin_reset_handler().
	 */
	public function test_bbp_admin_reset_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
