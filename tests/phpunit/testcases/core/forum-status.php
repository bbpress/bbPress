<?php

/**
 * Tests for the site-wide forums status.
 *
 * @group forums
 * @group options
 */
class BBP_Tests_Core_Forum_Status extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_forums_statuses
	 * @covers ::bbp_get_forums_status
	 * @covers ::bbp_is_forums_status
	 */
	public function test_status_defaults_to_open_and_rejects_invalid_values() {
		$this->assertSame( array( 'open', 'closed', 'frozen' ), array_keys( bbp_get_forums_statuses() ) );
		$this->assertSame( 'open', bbp_get_forums_status() );
		$this->assertTrue( bbp_is_forums_status( 'open' ) );
		$this->assertFalse( bbp_is_forums_status( 'frozen' ) );
		$this->assertFalse( bbp_is_forums_status( array( 'open' ) ) );

		update_option( '_bbp_forums_status', 'unknown' );
		$this->assertSame( 'open', bbp_get_forums_status() );

		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';
		$fields = bbp_admin_get_settings_fields();
		$this->assertSame( 'bbp_admin_sanitize_forums_status', $fields['bbp_settings_status']['_bbp_forums_status']['sanitize_callback'] );
		$this->assertSame( 'closed', bbp_admin_sanitize_forums_status( 'closed' ) );
		$this->assertSame( 'frozen', bbp_admin_sanitize_forums_status( 'frozen' ) );
		$this->assertSame( 'open', bbp_admin_sanitize_forums_status( array( 'frozen' ) ) );
	}

	public function test_forums_statuses_can_be_filtered() {
		$filter = function( $statuses ) {
			$statuses['limited'] = 'Limited posting';
			return $statuses;
		};

		add_filter( 'bbp_get_forums_statuses', $filter );
		$this->assertSame( 'Limited posting', bbp_get_forums_statuses()['limited'] );

		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';
		$this->assertSame( 'limited', bbp_admin_sanitize_forums_status( 'limited' ) );
		update_option( '_bbp_forums_status', 'limited' );
		$this->assertSame( 'limited', bbp_get_forums_status() );
		$this->assertTrue( bbp_is_forums_status( 'limited' ) );

		remove_filter( 'bbp_get_forums_statuses', $filter );
		$this->assertSame( 'open', bbp_get_forums_status() );
	}

	/**
	 * @covers ::bbp_user_is_keymaster_or_moderator
	 * @ticket 3706
	 */
	public function test_site_wide_moderator_check_ignores_scoped_moderation() {
		$forum_id       = $this->factory->forum->create();
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$keymaster_id   = $this->factory->user->create();

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $participant_id );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		$this->assertFalse( bbp_user_is_keymaster_or_moderator( 0 ) );
		$this->assertFalse( bbp_user_is_keymaster_or_moderator( $participant_id ) );
		$this->assertTrue( bbp_user_is_keymaster_or_moderator( $moderator_id ) );
		$this->assertTrue( bbp_user_is_keymaster_or_moderator( $keymaster_id ) );
	}

	public function test_policy_helpers_can_be_filtered_for_custom_statuses() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$status_filter = function( $statuses ) {
			$statuses['limited'] = 'Limited posting';
			return $statuses;
		};
		$post_filter = function( $retval, $filtered_user_id, $status ) use ( $user_id ) {
			$this->assertFalse( $retval );
			$this->assertSame( $user_id, $filtered_user_id );
			$this->assertSame( 'limited', $status );
			return true;
		};
		$edit_filter = function( $retval, $filtered_user_id, $status ) use ( $user_id ) {
			$this->assertTrue( $retval );
			$this->assertSame( $user_id, $filtered_user_id );
			$this->assertSame( 'limited', $status );
			return false;
		};

		add_filter( 'bbp_get_forums_statuses', $status_filter );
		add_filter( 'bbp_user_can_post_in_forums', $post_filter, 10, 3 );
		add_filter( 'bbp_user_can_edit_in_forums', $edit_filter, 10, 3 );
		update_option( '_bbp_forums_status', 'limited' );

		try {
			$this->assertTrue( bbp_user_can_post_in_forums( $user_id ) );
			$this->assertFalse( bbp_user_can_edit_in_forums( $user_id ) );
		} finally {
			remove_filter( 'bbp_get_forums_statuses', $status_filter );
			remove_filter( 'bbp_user_can_post_in_forums', $post_filter );
			remove_filter( 'bbp_user_can_edit_in_forums', $edit_filter );
		}
	}

	public function test_closed_blocks_creation_capabilities_but_allows_forums() {
		$forum_id = $this->factory->forum->create();
		$user_id  = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );
		update_option( '_bbp_forums_status', 'closed' );

		$this->assertFalse( current_user_can( 'publish_topics' ) );
		$this->assertFalse( current_user_can( 'publish_replies' ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_topic_post_type() )->cap->create_posts ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_reply_post_type() )->cap->create_posts ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_forum_post_type() )->cap->create_posts ) );
		$this->assertFalse( bbp_current_user_can_publish_topics() );
		$this->assertFalse( bbp_current_user_can_publish_replies() );
		$this->assertGreaterThan( 0, bbp_insert_forum( array( 'post_title' => 'New forum' ) ) );
		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );
	}

	/**
	 * @covers ::bbp_current_user_can_post_in_forums
	 */
	public function test_closed_allows_keymasters_but_not_scoped_moderators() {
		$forum_id = $this->factory->forum->create();
		$user_id  = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $user_id );
		$this->set_current_user( $user_id );
		update_option( '_bbp_forums_status', 'closed' );

		$this->assertTrue( current_user_can( 'moderate', $forum_id ) );
		$this->assertFalse( current_user_can( 'moderate' ) );
		$this->assertFalse( bbp_current_user_can_post_in_forums() );
		$this->assertFalse( current_user_can( 'publish_topics' ) );
		$this->assertFalse( current_user_can( 'publish_replies' ) );

		$keymaster_id = $this->factory->user->create();
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->set_current_user( $keymaster_id );
		$this->assertTrue( bbp_current_user_can_post_in_forums() );
		$this->assertTrue( current_user_can( get_post_type_object( bbp_get_forum_post_type() )->cap->create_posts ) );
		$this->assertGreaterThan( 0, bbp_insert_topic( array( 'post_parent' => 0, 'post_title' => 'Keymaster topic' ) ) );
	}

	public function test_frozen_blocks_new_content_but_allows_moderator_edits() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$user_id  = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		update_option( '_bbp_forums_status', 'frozen' );

		$this->assertFalse( current_user_can( 'publish_topics' ) );
		$this->assertFalse( current_user_can( 'publish_replies' ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_topic_post_type() )->cap->create_posts ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_reply_post_type() )->cap->create_posts ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_forum_post_type() )->cap->create_posts ) );
		$this->assertFalse( bbp_current_user_can_publish_topics() );
		$this->assertFalse( bbp_current_user_can_publish_replies() );
		$this->assertFalse( bbp_current_user_can_publish_forums() );
		$this->assertFalse( bbp_current_user_can_access_create_forum_form() );
		$this->assertSame( $topic_id, wp_update_post( array( 'ID' => $topic_id, 'post_title' => 'Edited topic' ) ) );
		$this->assertGreaterThan( 0, wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Ordinary post' ) ) );
		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );

		update_option( '_bbp_forums_status', 'open' );
		$this->assertTrue( current_user_can( 'publish_topics' ) );
		$this->assertTrue( current_user_can( 'publish_replies' ) );
		$this->assertGreaterThan( 0, bbp_insert_topic( array( 'post_parent' => $forum_id, 'post_title' => 'Reopened topic' ) ) );
	}

	public function test_frozen_limits_default_form_access_but_preserves_filters() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		update_option( '_bbp_forums_status', 'frozen' );

		$this->assertFalse( bbp_current_user_can_publish_topics() );
		$this->assertFalse( bbp_current_user_can_publish_replies() );
		$this->assertFalse( bbp_current_user_can_access_create_topic_form() );
		$this->assertFalse( bbp_current_user_can_access_create_reply_form() );

		$allow = '__return_true';
		add_filter( 'bbp_current_user_can_publish_topics', $allow );
		add_filter( 'bbp_current_user_can_publish_replies', $allow );
		add_filter( 'bbp_current_user_can_access_create_topic_form', $allow );
		add_filter( 'bbp_current_user_can_access_create_reply_form', $allow );

		$this->assertTrue( bbp_current_user_can_publish_topics() );
		$this->assertTrue( bbp_current_user_can_publish_replies() );
		$this->assertTrue( bbp_current_user_can_access_create_topic_form() );
		$this->assertTrue( bbp_current_user_can_access_create_reply_form() );

		remove_filter( 'bbp_current_user_can_publish_topics', $allow );
		remove_filter( 'bbp_current_user_can_publish_replies', $allow );
		remove_filter( 'bbp_current_user_can_access_create_topic_form', $allow );
		remove_filter( 'bbp_current_user_can_access_create_reply_form', $allow );
	}

	public function test_anonymous_form_notices_match_site_wide_status() {
		$this->set_current_user( 0 );

		foreach ( array( 'topic', 'reply' ) as $post_type ) {
			foreach ( array( 'open', 'closed', 'frozen' ) as $status ) {
				update_option( '_bbp_forums_status', $status );
				$login_requested = false;
				$capture_login   = function( $located, $template_name, $template_names ) use ( &$login_requested ) {
					if ( in_array( 'form-user-login.php', (array) $template_names, true ) ) {
						$login_requested = true;
					}
				};
				add_action( 'bbp_locate_template', $capture_login, 10, 3 );

				ob_start();
				include BBP_PLUGIN_DIR . 'templates/default/bbpress/form-' . $post_type . '.php';
				$html = ob_get_clean();
				remove_action( 'bbp_locate_template', $capture_login, 10 );

				$this->assertStringContainsString( ( 'open' === $status ) ? 'You must be logged in' : 'You cannot', $html );
				$this->assertSame( ( 'frozen' !== $status ), $login_requested, $post_type . ': ' . $status );
			}
		}
	}

	public function test_frozen_denies_participant_edits_but_allows_moderators() {
		$participant_id = $this->factory->user->create();
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_author' => $participant_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => $participant_id ) );
		$this->set_current_user( $participant_id );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );

		update_option( '_bbp_forums_status', 'frozen' );
		bbp_add_moderator( $forum_id, $participant_id );
		$this->assertTrue( current_user_can( 'moderate', $topic_id ) );
		$this->assertFalse( current_user_can( 'moderate' ) );
		$this->assertFalse( bbp_user_can_edit_in_forums( $participant_id ) );
		$this->assertFalse( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertFalse( current_user_can( 'edit_reply', $reply_id ) );

		// Trusted updates can still keep synchronized forum data current.
		$this->set_current_user( 0 );
		$this->assertSame( $forum_id, wp_update_post( array( 'ID' => $forum_id, 'post_title' => 'Synchronized forum' ) ) );
		$this->assertSame( $topic_id, wp_update_post( array( 'ID' => $topic_id, 'post_title' => 'Synchronized topic' ) ) );
		$this->assertSame( $reply_id, wp_update_post( array( 'ID' => $reply_id, 'post_content' => 'Synchronized reply' ) ) );

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		$this->set_current_user( $moderator_id );
		$this->assertTrue( bbp_user_can_edit_in_forums( $moderator_id ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_forum_post_type() )->cap->create_posts ) );
		$this->assertTrue( current_user_can( 'edit_forum', $forum_id ) );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );
		$this->assertSame( $forum_id, wp_update_post( array( 'ID' => $forum_id, 'post_title' => 'Moderator forum edit' ) ) );
		$this->assertSame( $topic_id, wp_update_post( array( 'ID' => $topic_id, 'post_title' => 'Moderator edit' ) ) );
		$this->assertSame( $reply_id, wp_update_post( array( 'ID' => $reply_id, 'post_content' => 'Moderator edit' ) ) );
	}

	public function test_auto_drafts_require_creation_permission_on_first_save() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$topic_id = wp_insert_post( array( 'post_type' => bbp_get_topic_post_type(), 'post_status' => 'auto-draft' ) );
		$this->assertGreaterThan( 0, $topic_id );

		update_option( '_bbp_forums_status', 'closed' );
		$this->assertFalse( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_topic_post_type() )->cap->create_posts ) );

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		$this->set_current_user( $moderator_id );
		$this->assertTrue( current_user_can( get_post_type_object( bbp_get_topic_post_type() )->cap->create_posts ) );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );

		update_option( '_bbp_forums_status', 'frozen' );
		$this->assertFalse( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertFalse( current_user_can( get_post_type_object( bbp_get_topic_post_type() )->cap->create_posts ) );
	}

	public function test_closed_allows_site_wide_moderator_without_a_forum() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		update_option( '_bbp_forums_status', 'closed' );

		$this->assertTrue( bbp_current_user_can_post_in_forums() );
		$this->assertGreaterThan( 0, bbp_insert_topic( array( 'post_parent' => 0, 'post_title' => 'Moderator topic' ) ) );
	}
}
