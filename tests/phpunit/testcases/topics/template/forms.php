<?php

/**
 * Tests for the `bbp_*_form_topic_*_()` functions.
 *
 * @group topics
 * @group template
 * @group forms
 */
class BBP_Tests_Topics_Template_Forms extends BBP_UnitTestCase {

	/**
	 * Topic tag name used by the topic-tag form test.
	 *
	 * @var string
	 */
	protected $topic_tag_name = '';

	/**
	 * Filters the topic tag name used by the topic-tag form test.
	 *
	 * @return string
	 */
	public function filter_topic_tag_name() {
		return $this->topic_tag_name;
	}

	/**
	 * @coversNothing
	 * @group bbp_xss
	 */
	public function test_topic_tag_name_is_not_included_in_javascript_confirmations() {
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$old_user = get_current_user_id();

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$this->topic_tag_name = 'qa&#092;&#039;+alert(document.domain))//';
		add_filter( 'bbp_get_topic_tag_name', array( $this, 'filter_topic_tag_name' ) );

		ob_start();
		require bbpress()->themes_dir . 'default/bbpress/form-topic-tag.php';
		$output = ob_get_clean();

		remove_filter( 'bbp_get_topic_tag_name', array( $this, 'filter_topic_tag_name' ) );
		$this->set_current_user( $old_user );

		preg_match_all( '/onclick="([^"]+)"/', $output, $matches );

		$this->assertCount( 2, $matches[1] );
		$this->assertStringNotContainsString( $this->topic_tag_name, implode( '', $matches[1] ) );
		$this->assertStringNotContainsString( 'alert(document.domain)', implode( '', $matches[1] ) );
		$this->assertStringContainsString( 'merge this tag', $matches[1][0] );
		$this->assertStringContainsString( 'delete this tag', $matches[1][1] );
	}

	/**
	 * @coversNothing
	 * @group bbp_xss
	 */
	public function test_topic_split_destination_title_is_escaped() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => '<code>" autofocus onfocus="alert(1)</code>',
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$old_user = get_current_user_id();
		$old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_reply_id = isset( $_GET['reply_id'] ) ? $_GET['reply_id'] : null;

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		bbpress()->current_topic_id = $topic_id;
		add_filter( 'bbp_is_topic_edit', '__return_true' );
		$_GET['reply_id'] = 0;
		$GLOBALS['post'] = get_post( $topic_id );
		setup_postdata( $GLOBALS['post'] );

		ob_start();
		require bbpress()->themes_dir . 'default/bbpress/form-topic-split.php';
		$output = ob_get_clean();

		$GLOBALS['post'] = $old_post;
		wp_reset_postdata();
		remove_filter( 'bbp_is_topic_edit', '__return_true' );
		if ( null === $old_reply_id ) {
			unset( $_GET['reply_id'] );
		} else {
			$_GET['reply_id'] = $old_reply_id;
		}
		bbpress()->current_topic_id = 0;
		$this->set_current_user( $old_user );

		$this->assertStringContainsString( 'value="Split: &lt;code&gt;&quot; autofocus onfocus=&quot;alert(1)&lt;/code&gt;"', $output );
		$this->assertStringNotContainsString( 'value="Split: <code>" autofocus', $output );
	}

	/**
	 * @covers ::bbp_form_topic_type_dropdown
	 * @covers ::bbp_get_form_topic_type_dropdown
	 * @todo   Implement test_bbp_get_form_topic_type_dropdown().
	 */
	public function test_bbp_get_form_topic_type_dropdown() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_topic_status_dropdown
	 * @covers ::bbp_get_form_topic_status_dropdown
	 * @todo   Implement test_bbp_get_form_topic_status_dropdown().
	 */
	public function test_bbp_get_form_topic_status_dropdown() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_topic_title
	 * @covers ::bbp_get_form_topic_title
	 * @todo   Implement test_bbp_get_form_topic_title().
	 */
	public function test_bbp_get_form_topic_title() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_topic_content
	 * @covers ::bbp_get_form_topic_content
	 * @todo   Implement test_bbp_get_form_topic_content().
	 */
	public function test_bbp_get_form_topic_content() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_topic_tags
	 * @covers ::bbp_get_form_topic_tags
	 * @todo   Implement test_bbp_get_form_topic_tags().
	 */
	public function test_bbp_get_form_topic_tags() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_topic_forum
	 * @covers ::bbp_get_form_topic_forum
	 * @todo   Implement test_bbp_get_form_topic_forum().
	 */
	public function test_bbp_get_form_topic_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_topic_subscribed
	 * @covers ::bbp_get_form_topic_subscribed
	 * @todo   Implement test_bbp_get_form_topic_subscribed().
	 */
	public function test_bbp_get_form_topic_subscribed() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_topic_log_edit
	 * @covers ::bbp_get_form_topic_log_edit
	 * @todo   Implement test_bbp_get_form_topic_log_edit().
	 */
	public function test_bbp_get_form_topic_log_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_topic_edit_reason
	 * @covers ::bbp_get_form_topic_edit_reason
	 * @todo   Implement test_bbp_get_form_topic_edit_reason().
	 */
	public function test_bbp_get_form_topic_edit_reason() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_topic_form_post_request
	 * @todo   Implement test_bbp_is_topic_form_post_request().
	 */
	public function test_bbp_is_topic_form_post_request() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
