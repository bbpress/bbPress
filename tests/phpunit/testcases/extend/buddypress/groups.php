<?php

/**
 * BuddyPress Group Forum permission tests.
 *
 * @group extend
 * @group buddypress
 * @group groups
 */
class BBP_Tests_Extend_BuddyPress_Groups extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_get;
	protected $old_request;
	protected $old_server;
	protected $old_errors;
	protected $old_allow_forum_mods;
	protected $old_community_visibility;
	protected $old_moderation_notification_recipient_types;
	protected $old_allow_anonymous;
	protected $old_allow_content_throttle;
	protected $old_moderation_keys;
	protected $old_action_variables;
	protected $old_forum_query;
	protected $old_topic_query;
	protected $old_reply_query;
	protected $old_current_forum_id;
	protected $old_current_topic_id;
	protected $old_query_name;
	protected $old_paged;
	protected $group_extension;
	protected $template_parts = array();

	/**
	 * @covers ::BBP_Forums_Group_Extension::remove_forum
	 * @covers ::BBP_Forums_Group_Extension::disconnect_forum_from_group
	 */
	public function test_disconnected_private_group_forums_stay_restricted() {
		$creator_id = $this->factory->user->create();
		$outsider_id = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_ids = $this->factory->forum->create_many( 2 );

		bbp_set_user_role( $outsider_id, bbp_get_participant_role() );
		foreach ( $forum_ids as $forum_id ) {
			$this->attach_forum_to_group( $forum_id, $group_id );
			bbp_privatize_forum( $forum_id );
		}

		$this->group_extension = new BBP_Forums_Group_Extension();
		$this->assertFalse( user_can( $outsider_id, 'read_forum', $forum_ids[0] ) );
		$this->group_extension->disconnect_forum_from_group( $group_id );

		foreach ( $forum_ids as $forum_id ) {
			$this->assertSame( array(), bbp_get_forum_group_ids( $forum_id ) );
			$this->assertSame( bbp_get_hidden_status_id(), get_post_status( $forum_id ) );
			$this->assertFalse( user_can( $outsider_id, 'read_forum', $forum_id ) );
		}
	}

	public function setUp(): void {
		parent::setUp();

		$this->old_post    = $_POST;
		$this->old_get     = $_GET;
		$this->old_request = $_REQUEST;
		$this->old_server  = $_SERVER;
		$this->old_errors  = bbpress()->errors;
		$this->old_allow_forum_mods = get_option( '_bbp_allow_forum_mods', null );
		$this->old_community_visibility = get_option( '_bp_community_visibility', null );
		$this->old_moderation_notification_recipient_types = get_option( '_bbp_moderation_notification_recipient_types', null );
		$this->old_allow_anonymous = get_option( '_bbp_allow_anonymous', null );
		$this->old_allow_content_throttle = get_option( '_bbp_allow_content_throttle' );
		$this->old_moderation_keys = get_option( 'moderation_keys' );
		$this->old_action_variables = buddypress()->action_variables;
		$this->old_forum_query       = bbpress()->forum_query;
		$this->old_topic_query       = bbpress()->topic_query;
		$this->old_reply_query       = bbpress()->reply_query;
		$this->old_current_forum_id  = bbpress()->current_forum_id;
		$this->old_current_topic_id  = bbpress()->current_topic_id;
		$this->old_query_name        = bbp_get_query_name();
		$this->old_paged             = bbp_get_wp_query()->get( 'paged' );
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_GET             = $this->old_get;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;
		buddypress()->action_variables = $this->old_action_variables;
		update_option( '_bbp_allow_content_throttle', $this->old_allow_content_throttle );
		update_option( 'moderation_keys', $this->old_moderation_keys );
		bbpress()->forum_query      = $this->old_forum_query;
		bbpress()->topic_query      = $this->old_topic_query;
		bbpress()->reply_query      = $this->old_reply_query;
		bbpress()->current_forum_id = $this->old_current_forum_id;
		bbpress()->current_topic_id = $this->old_current_topic_id;
		bbp_set_query_name( $this->old_query_name );
		bbp_get_wp_query()->set( 'paged', $this->old_paged );

		if ( null === $this->old_allow_forum_mods ) {
			delete_option( '_bbp_allow_forum_mods' );
		} else {
			update_option( '_bbp_allow_forum_mods', $this->old_allow_forum_mods );
		}

		if ( null === $this->old_community_visibility ) {
			delete_option( '_bp_community_visibility' );
		} else {
			update_option( '_bp_community_visibility', $this->old_community_visibility );
		}

		if ( null === $this->old_moderation_notification_recipient_types ) {
			delete_option( '_bbp_moderation_notification_recipient_types' );
		} else {
			update_option( '_bbp_moderation_notification_recipient_types', $this->old_moderation_notification_recipient_types );
		}

		if ( null === $this->old_allow_anonymous ) {
			delete_option( '_bbp_allow_anonymous' );
		} else {
			update_option( '_bbp_allow_anonymous', $this->old_allow_anonymous );
		}

		if ( isset( $this->group_extension ) ) {
			remove_action( 'bbp_new_topic_pre_extras', array( $this->group_extension, 'validate_new_post' ), 10 );
			remove_action( 'bbp_new_reply_pre_extras', array( $this->group_extension, 'validate_new_post' ), 10 );
			remove_filter( 'bbp_allow_read_without_spectate', array( $this->group_extension, 'allow_group_forum_read_without_role' ), 10 );
			remove_filter( 'bbp_current_user_can_access_create_topic_form', array( $this->group_extension, 'form_permissions' ) );
			remove_filter( 'bbp_current_user_can_access_create_reply_form', array( $this->group_extension, 'form_permissions' ) );
			remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ), 10 );
			remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ), 99 );
			remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_read_meta_caps' ), 20 );
			remove_filter( 'bbp_get_excluded_forum_ids', array( $this->group_extension, 'exclude_group_forum_ids' ), 20 );
			remove_filter( 'bbp_is_forum_restricted_for_user', array( $this->group_extension, 'restrict_inaccessible_group_forums' ), 10 );
			remove_filter( 'bbp_user_can_view_forum', array( $this->group_extension, 'filter_user_can_view_forum' ), 10 );
			remove_filter( 'bbp_subscription_user_can_view_forum', array( $this->group_extension, 'subscription_user_can_view_forum' ), 10 );
		}

		unset( buddypress()->groups->current_group );
		buddypress()->current_component = '';
		buddypress()->current_item      = '';
		buddypress()->current_action    = '';
		unset( buddypress()->is_single_item );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_maybe_delete_group_forum_root
	 */
	public function test_group_forum_root_cleanup_uses_explicit_forum_id() {
		$root_id = $this->factory->forum->create();

		update_option( '_bbp_group_forums_root_id', $root_id );
		$this->assertNotFalse( wp_delete_post( $root_id, true ) );
		$this->assertSame( 0, (int) get_option( '_bbp_group_forums_root_id' ) );
	}

	/**
	 * @covers ::bbp_remove_group_id_from_all_forums
	 */
	public function test_remove_group_id_from_all_forums_uses_forum_then_group_ids() {
		$group_id = $this->bp_factory->group->create();
		$forum_ids = $this->factory->forum->create_many( 2 );

		foreach ( $forum_ids as $forum_id ) {
			bbp_add_forum_id_to_group( $group_id, $forum_id );
			bbp_add_group_id_to_forum( $forum_id, $group_id );
			$this->assertSame( array( $group_id ), bbp_get_forum_group_ids( $forum_id ) );
		}

		bbp_remove_group_id_from_all_forums( $group_id );

		foreach ( $forum_ids as $forum_id ) {
			$this->assertSame( array(), bbp_get_forum_group_ids( $forum_id ) );
		}
	}

	/**
	 * @covers ::bbp_remove_forum_id_from_all_groups
	 */
	public function test_deleting_forum_removes_it_from_all_groups() {
		$group_ids = $this->bp_factory->group->create_many( 2 );
		$forum_id  = $this->factory->forum->create();

		foreach ( $group_ids as $group_id ) {
			bbp_add_forum_id_to_group( $group_id, $forum_id );
			bbp_add_group_id_to_forum( $forum_id, $group_id );
			$this->assertSame( array( $forum_id ), bbp_get_group_forum_ids( $group_id ) );
		}

		$this->assertNotFalse( wp_delete_post( $forum_id, true ) );

		foreach ( $group_ids as $group_id ) {
			$this->assertSame( array(), bbp_get_group_forum_ids( $group_id ) );
		}
	}

	/**
	 * @covers ::bbp_new_topic_handler
	 * @covers ::BBP_Forums_Group_Extension::new_topic_redirect_to
	 */
	public function test_moderated_group_topic_shows_notice_without_redirecting() {
		$creator_id = $this->factory->user->create();
		$member_id  = $this->factory->user->create();
		$group_id   = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id   = $this->factory->forum->create();
		$home_url   = wp_parse_url( home_url( '/' ) );
		$redirected = false;

		bbp_set_user_role( $member_id, bbp_get_participant_role() );
		groups_join_group( $group_id, $member_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->set_group_context( $group_id, $member_id );
		buddypress()->is_single_item = true;
		update_option( 'moderation_keys', 'review phrase' );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_HOST']      = $home_url['host'];
		$_SERVER['SERVER_PORT']    = isset( $home_url['port'] ) ? $home_url['port'] : 80;
		$_SERVER['REQUEST_URI']    = $home_url['path'];
		$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
		$_POST                     = array(
			'bbp_forum_id'     => $forum_id,
			'bbp_topic_title'   => 'Moderated group topic',
			'bbp_topic_content' => 'Contains the review phrase.',
		);
		$_REQUEST                  = array(
			'_wpnonce' => wp_create_nonce( 'bbp-new-topic' ),
		);
		bbpress()->errors          = new WP_Error();

		$prevent_redirect = function() use ( &$redirected ) {
			$redirected = true;
			throw new RuntimeException( 'Group topic redirect.' );
		};
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_new_topic_handler( 'bbp-new-topic' );
		} catch ( RuntimeException $exception ) {
			if ( 'Group topic redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			remove_filter( 'wp_redirect', $prevent_redirect );
		}

		$topic_ids = get_posts(
			array(
				'fields'           => 'ids',
				'posts_per_page'   => -1,
				'post_parent'      => $forum_id,
				'post_status'      => 'any',
				'post_type'        => bbp_get_topic_post_type(),
				'suppress_filters' => true,
			)
		);

		$this->assertCount( 1, $topic_ids, implode( ', ', bbpress()->errors->get_error_codes() ) );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_ids[0] ) );
		$this->assertSame( 'Your topic is pending moderation.', bbpress()->errors->get_error_message( 'bbp_topic_moderated' ) );
		$this->assertFalse( $redirected );
		$this->assertSame( '', bbp_get_form_topic_content() );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::validate_new_post
	 * @covers ::bbp_new_topic_handler
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_group_forum_creation_requires_current_membership_outside_group_context() {
		$creator_id   = $this->factory->user->create();
		$member_id    = $this->factory->user->create();
		$banned_id    = $this->factory->user->create();
		$outsider_id  = $this->factory->user->create();
		$forum_mod_id = $this->factory->user->create();
		$moderator_id = $this->factory->user->create();
		$keymaster_id = $this->factory->user->create();
		$group_id     = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id     = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		foreach ( array( $creator_id, $member_id, $banned_id, $outsider_id, $forum_mod_id ) as $user_id ) {
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
		}
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		groups_join_group( $group_id, $member_id );
		groups_join_group( $group_id, $banned_id );
		groups_ban_member( $banned_id, $group_id, $creator_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		bbp_add_moderator( $forum_id, $forum_mod_id );
		update_option( '_bbp_allow_content_throttle', false );

		buddypress()->current_component = '';
		buddypress()->current_item      = '';
		buddypress()->current_action    = '';
		$this->group_extension          = new BBP_Forums_Group_Extension();

		$cases = array(
			'anonymous'       => array( 0, false, false ),
			'non-member'      => array( $outsider_id, false, true ),
			'banned member'   => array( $banned_id, false, true ),
			'current member'  => array( $member_id, true, false ),
			'forum moderator' => array( $forum_mod_id, true, false ),
			'moderator'       => array( $moderator_id, true, false ),
			'keymaster'       => array( $keymaster_id, true, false ),
		);

		foreach ( $cases as $label => $case ) {
			list( $user_id, $allowed, $group_error ) = $case;
			$this->set_current_user( $user_id );
			bbpress()->errors = new WP_Error();

			$this->assertSame( $allowed, (bool) $this->submit_group_topic( $forum_id, $label ), $label . ' topic.' );
			if ( $group_error ) {
				$this->assertContains( 'bbp_group_forum_permission', bbpress()->errors->get_error_codes(), $label . ' topic error.' );
			}

			bbpress()->errors = new WP_Error();
			$this->assertSame( $allowed, (bool) $this->submit_group_reply( $topic_id, $forum_id, $label ), $label . ' reply.' );
			if ( $group_error ) {
				$this->assertContains( 'bbp_group_forum_permission', bbpress()->errors->get_error_codes(), $label . ' reply error.' );
			}
		}

		$this->set_current_user( 0 );
		update_option( '_bbp_allow_anonymous', true );
		bbpress()->errors = new WP_Error();
		$this->group_extension->validate_new_post( $forum_id );
		$this->assertNotContains( 'bbp_group_forum_permission', bbpress()->errors->get_error_codes() );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::remove_group_forum_meta_cap_map
	 */
	public function test_group_forum_cap_map_is_removed_after_display() {
		$user_id  = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		buddypress()->is_single_item = true;
		$this->set_group_context( $group_id, $user_id );
		remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ), 99 );

		$this->assertSame( 10, has_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ) ) );
		$this->assertTrue( current_user_can( 'moderate' ) );

		do_action( 'bbp_after_group_forum_display' );

		$this->assertFalse( has_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ) ) );
		$this->assertFalse( current_user_can( 'moderate' ) );
	}

	/**
	 * @covers ::bbp_user_can_post_in_forums
	 * @covers ::bbp_user_can_edit_in_forums
	 */
	public function test_group_moderator_does_not_gain_site_wide_status_exception() {
		$user_id  = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->attach_forum_to_group( $forum_id, $group_id );
		buddypress()->is_single_item = true;
		$this->set_group_context( $group_id, $user_id );
		$this->assertTrue( current_user_can( 'moderate' ) );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );

		update_option( '_bbp_forums_status', 'closed' );
		$this->assertFalse( bbp_current_user_can_post_in_forums() );
		$this->assertFalse( current_user_can( 'publish_topics' ) );
		$this->assertFalse( current_user_can( 'publish_replies' ) );

		update_option( '_bbp_forums_status', 'frozen' );
		$this->assertFalse( bbp_user_can_edit_in_forums( $user_id ) );
		$this->assertFalse( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertFalse( current_user_can( 'edit_reply', $reply_id ) );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_filter_group_moderation_notification_recipient_type_labels
	 * @covers ::bbp_filter_group_moderation_notification_recipient_type_user_ids
	 */
	public function test_group_staff_receive_forum_moderation_notifications() {
		update_option( '_bbp_moderation_notification_recipient_types', array( 'group_moderators' ) );

		$creator_id         = $this->factory->user->create();
		$group_mod_id       = $this->factory->user->create();
		$blocked_group_id   = $this->factory->user->create();
		$other_group_mod_id = $this->factory->user->create();
		$group_id           = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$other_group_id     = $this->bp_factory->group->create();
		$forum_id           = $this->factory->forum->create();
		$topic_id           = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		groups_join_group( $group_id, $group_mod_id );
		groups_promote_member( $group_mod_id, $group_id, 'mod', $creator_id );
		groups_join_group( $group_id, $blocked_group_id );
		groups_promote_member( $blocked_group_id, $group_id, 'mod', $creator_id );
		bbp_set_user_role( $blocked_group_id, bbp_get_blocked_role() );
		groups_join_group( $other_group_id, $other_group_mod_id );
		groups_promote_member( $other_group_mod_id, $other_group_id, 'mod' );
		$this->attach_forum_to_group( $forum_id, $group_id );

		$user_ids = bbp_get_moderation_notification_user_ids( $topic_id, $forum_id );

		$this->assertArrayHasKey( 'group_moderators', bbp_get_moderation_notification_recipient_type_labels() );
		$this->assertContains( $creator_id, $user_ids );
		$this->assertContains( $group_mod_id, $user_ids );
		$this->assertNotContains( $blocked_group_id, $user_ids );
		$this->assertNotContains( $other_group_mod_id, $user_ids );

		bbpress()->current_forum_id = $forum_id;
		try {
			$this->assertSame( array(), bbp_filter_group_moderation_notification_recipient_type_user_ids( array(), 'group_moderators', $topic_id, 0 ) );
		} finally {
			unset( bbpress()->current_forum_id );
		}
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_filter_group_moderation_notification_recipient_type_user_ids
	 */
	public function test_group_staff_moderation_notifications_require_forum_access() {
		$creator_id      = $this->factory->user->create();
		$group_mod_id    = $this->factory->user->create();
		$group_id        = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$hidden_forum_id = $this->factory->forum->create();
		$forum_id        = $this->factory->forum->create( array( 'post_parent' => $hidden_forum_id ) );
		$topic_id        = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		bbp_set_user_role( $group_mod_id, bbp_get_participant_role() );
		groups_join_group( $group_id, $group_mod_id );
		groups_promote_member( $group_mod_id, $group_id, 'mod', $creator_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		bbp_hide_forum( $hidden_forum_id );

		$this->assertFalse( bbp_user_can_view_forum( array( 'user_id' => $creator_id, 'forum_id' => $forum_id ) ) );
		$this->assertFalse( bbp_user_can_view_forum( array( 'user_id' => $group_mod_id, 'forum_id' => $forum_id ) ) );
		$this->assertSame(
			array(),
			bbp_filter_group_moderation_notification_recipient_type_user_ids( array(), 'group_moderators', $topic_id, $forum_id )
		);
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::edit_screen_save
	 */
	public function test_group_admin_can_create_group_forum_while_frozen() {
		$user_id  = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_group_context( $group_id, $user_id );
		update_option( '_bbp_forums_status', 'frozen' );
		$this->assertFalse( current_user_can( 'create_forums' ) );

		$_SERVER['REQUEST_METHOD']     = 'POST';
		$_SERVER['HTTP_HOST']          = wp_parse_url( home_url(), PHP_URL_HOST );
		$_SERVER['SERVER_PORT']        = wp_parse_url( home_url(), PHP_URL_PORT ) ?: 80;
		$_SERVER['REQUEST_URI']        = '/';
		$_REQUEST['_wpnonce']          = wp_create_nonce( 'groups_edit_save_forum' );
		$_POST['bbp-edit-group-forum'] = '1';
		$this->assertTrue( (bool) bbp_verify_nonce_request( 'groups_edit_save_forum' ) );

		$this->group_extension->edit_screen_save( $group_id );

		$forum_ids = bbp_get_group_forum_ids( $group_id );
		$this->assertCount( 1, $forum_ids );
		$this->assertTrue( bbp_is_forum( $forum_ids[0] ) );
		$this->assertSame( array( $group_id ), bbp_get_forum_group_ids( $forum_ids[0] ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::form_permissions
	 */
	public function test_frozen_group_forum_hides_keymaster_creation_forms() {
		$user_id  = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );
		$forum_id = $this->factory->forum->create();

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->attach_forum_to_group( $forum_id, $group_id );
		buddypress()->is_single_item = true;
		$this->set_group_context( $group_id, $user_id );
		update_option( '_bbp_forums_status', 'frozen' );

		$this->assertFalse( bbp_current_user_can_access_create_topic_form() );
		$this->assertFalse( bbp_current_user_can_access_create_reply_form() );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::edit_screen_save
	 */
	public function test_group_forum_save_discards_missing_forum_ids() {
		$user_id  = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );
		$forum_id = $this->factory->forum->create();
		$missing  = 999999999;

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_update_group_forum_ids( $group_id, array( $missing, $forum_id ) );
		bbp_update_forum_group_ids( $forum_id, array( $group_id ) );
		$this->set_group_context( $group_id, $user_id );

		$_SERVER['REQUEST_METHOD']     = 'POST';
		$this->set_request_url();
		$_REQUEST['_wpnonce']          = wp_create_nonce( 'groups_edit_save_forum' );
		$_POST['bbp-edit-group-forum'] = '1';

		$this->group_extension->edit_screen_save( $group_id );

		$this->assertSame( array( $forum_id ), bbp_get_group_forum_ids( $group_id ) );
		$this->assertSame( array( $group_id ), bbp_get_forum_group_ids( $forum_id ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::edit_screen_save
	 */
	public function test_group_forum_save_replaces_only_missing_forum() {
		$user_id  = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );
		$missing  = 999999999;

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_update_group_forum_ids( $group_id, array( $missing ) );
		$this->set_group_context( $group_id, $user_id );

		$_SERVER['REQUEST_METHOD']     = 'POST';
		$this->set_request_url();
		$_REQUEST['_wpnonce']          = wp_create_nonce( 'groups_edit_save_forum' );
		$_POST['bbp-edit-group-forum'] = '1';

		$this->group_extension->edit_screen_save( $group_id );

		$forum_ids = bbp_get_group_forum_ids( $group_id );
		$this->assertCount( 1, $forum_ids );
		$this->assertNotContains( $missing, $forum_ids );
		$this->assertTrue( bbp_is_forum( $forum_ids[0] ) );
		$this->assertSame( array( $group_id ), bbp_get_forum_group_ids( $forum_ids[0] ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::user_can_view_group_forum
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_read_meta_caps
	 * @covers ::BBP_Forums_Group_Extension::exclude_group_forum_ids
	 */
	public function test_restricted_group_forum_access_outside_group_context() {
		$creator_id        = $this->factory->user->create();
		$member_id         = $this->factory->user->create();
		$banned_id         = $this->factory->user->create();
		$outsider_id       = $this->factory->user->create();
		$moderator_id      = $this->factory->user->create();
		$forum_mod_id      = $this->factory->user->create();
		$group_mod_id      = $this->factory->user->create();
		$former_id         = $this->factory->user->create();
		$other_member_id   = $this->factory->user->create();
		$group_id          = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$other_group_id    = $this->bp_factory->group->create();
		$private_forum_id  = $this->factory->forum->create();
		$hidden_forum_id   = $this->factory->forum->create();
		$normal_forum_id   = $this->factory->forum->create();
		$private_normal_id = $this->factory->forum->create();

		foreach ( array( $creator_id, $member_id, $banned_id, $outsider_id, $forum_mod_id, $group_mod_id, $former_id, $other_member_id ) as $user_id ) {
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
		}
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

		groups_join_group( $group_id, $member_id );
		groups_join_group( $group_id, $banned_id );
		groups_join_group( $group_id, $group_mod_id );
		groups_promote_member( $group_mod_id, $group_id, 'mod', $creator_id );
		groups_join_group( $group_id, $former_id );
		groups_leave_group( $group_id, $former_id );
		groups_join_group( $other_group_id, $other_member_id );
		groups_ban_member( $banned_id, $group_id, $creator_id );
		$this->attach_forum_to_group( $private_forum_id, $group_id );
		$this->attach_forum_to_group( $private_forum_id, $other_group_id );
		$this->attach_forum_to_group( $hidden_forum_id, $group_id );
		bbp_privatize_forum( $private_forum_id );
		bbp_hide_forum( $hidden_forum_id );
		bbp_privatize_forum( $private_normal_id );
		bbp_add_moderator( $private_forum_id, $forum_mod_id );

		$this->group_extension = new BBP_Forums_Group_Extension();

		$this->assertTrue( user_can( $creator_id, 'read_forum', $private_forum_id ) );
		$this->assertTrue( user_can( $member_id, 'read_forum', $private_forum_id ) );
		$this->assertTrue( user_can( $member_id, 'read_forum', $hidden_forum_id ) );
		$this->assertTrue( user_can( $moderator_id, 'read_forum', $private_forum_id ) );
		$this->assertTrue( user_can( $forum_mod_id, 'read_forum', $private_forum_id ) );
		$this->assertTrue( user_can( $group_mod_id, 'read_forum', $private_forum_id ) );
		$this->assertTrue( user_can( $other_member_id, 'read_forum', $private_forum_id ) );
		$this->assertFalse( user_can( $banned_id, 'read_forum', $private_forum_id ) );
		$this->assertFalse( user_can( $former_id, 'read_forum', $private_forum_id ) );
		$this->assertFalse( user_can( $outsider_id, 'read_forum', $private_forum_id ) );
		$this->assertFalse( user_can( 0, 'read_forum', $private_forum_id ) );
		$this->assertTrue( user_can( $outsider_id, 'read_forum', $normal_forum_id ) );
		$this->assertTrue( user_can( $outsider_id, 'read_forum', $private_normal_id ) );

		$this->assertTrue( bbp_user_can_view_forum( array( 'user_id' => $member_id, 'forum_id' => $private_forum_id ) ) );
		$this->assertFalse( bbp_user_can_view_forum( array( 'user_id' => $banned_id, 'forum_id' => $private_forum_id ) ) );
		$this->assertFalse( bbp_user_can_view_forum( array( 'user_id' => $outsider_id, 'forum_id' => $private_forum_id ) ) );

		$this->set_current_user( $member_id );
		$this->assertNotContains( $private_forum_id, bbp_get_excluded_forum_ids() );
		$this->assertNotContains( $hidden_forum_id, bbp_get_excluded_forum_ids() );

		$this->set_current_user( $outsider_id );
		$this->assertContains( $private_forum_id, bbp_get_excluded_forum_ids() );
		$this->assertContains( $hidden_forum_id, bbp_get_excluded_forum_ids() );
		$this->assertNotContains( $private_normal_id, bbp_get_excluded_forum_ids() );

		$this->set_current_user( $banned_id );
		$this->assertContains( $private_forum_id, bbp_get_excluded_forum_ids() );
		$this->assertContains( $hidden_forum_id, bbp_get_excluded_forum_ids() );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::user_can_view_group_forum
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_read_meta_caps
	 * @covers ::BBP_Forums_Group_Extension::exclude_group_forum_ids
	 */
	public function test_members_only_community_excludes_public_group_forums_from_anonymous_users() {
		$creator_id     = $this->factory->user->create();
		$no_role_id     = $this->factory->user->create();
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$keymaster_id   = $this->factory->user->create();
		$group_id       = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id       = $this->factory->forum->create();
		$normal_id      = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id       = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->attach_forum_to_group( $forum_id, $group_id );
		update_option( '_bp_community_visibility', array( 'global' => 'members' ) );
		$this->group_extension = new BBP_Forums_Group_Extension();

		$this->assertFalse( $this->group_extension->user_can_view_group_forum( 0, $forum_id ) );
		$this->assertFalse( user_can( 0, 'read_forum', $forum_id ) );
		$this->assertFalse( user_can( 0, 'read_topic', $topic_id ) );
		$this->assertFalse( user_can( 0, 'read_reply', $reply_id ) );

		$this->set_current_user( 0 );
		$group_forum_queries = 0;
		$count_group_forum_queries = function( $query ) use ( &$group_forum_queries ) {
			if ( ( false !== strpos( $query, 'SELECT DISTINCT p.ID' ) ) && ( false !== strpos( $query, '_bbp_group_ids' ) ) ) {
				$group_forum_queries++;
			}

			return $query;
		};
		add_filter( 'query', $count_group_forum_queries );
		$excluded_forums       = $this->group_extension->exclude_group_forum_ids( array() );
		$excluded_forums_again = $this->group_extension->exclude_group_forum_ids( array() );
		remove_filter( 'query', $count_group_forum_queries );
		$this->assertContains( $forum_id, $excluded_forums );
		$this->assertNotContains( $normal_id, $excluded_forums_again );
		$this->assertSame( 1, $group_forum_queries );

		bbp_has_topics( array( 'post_parent' => 'any', 'posts_per_page' => -1, 'show_stickies' => false ) );
		$this->assertNotContains( $topic_id, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		bbp_has_replies( array( 'post_parent' => 'any', 'posts_per_page' => -1 ) );
		$this->assertNotContains( $reply_id, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );

		foreach ( array( $no_role_id, $participant_id, $moderator_id, $keymaster_id ) as $user_id ) {
			$this->assertNull( $this->group_extension->user_can_view_group_forum( $user_id, $forum_id ) );
			$this->assertTrue( user_can( $user_id, 'read_forum', $forum_id ) );
			$this->assertTrue( user_can( $user_id, 'read_topic', $topic_id ) );
			$this->assertTrue( user_can( $user_id, 'read_reply', $reply_id ) );
		}

		update_option( '_bp_community_visibility', array( 'global' => 'anyone' ) );
		$this->assertNull( $this->group_extension->user_can_view_group_forum( 0, $forum_id ) );
		$this->assertNotContains( $forum_id, bbp_get_excluded_forum_ids() );
		bbp_has_topics( array( 'post_parent' => 'any', 'posts_per_page' => -1, 'show_stickies' => false ) );
		$this->assertContains( $topic_id, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		bbp_has_replies( array( 'post_parent' => 'any', 'posts_per_page' => -1 ) );
		$this->assertContains( $reply_id, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::restrict_inaccessible_group_forums
	 * @covers ::BBP_Forums_Group_Extension::filter_user_can_view_forum
	 */
	public function test_members_only_public_group_forum_direct_reads_respect_community_visibility() {
		$creator_id = $this->factory->user->create();
		$no_role_id = $this->factory->user->create();
		$outsider_id = $this->factory->user->create();
		$group_id   = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id   = $this->factory->forum->create();
		$child_id   = $this->factory->forum->create( array( 'post_parent' => $forum_id ) );
		$normal_id  = $this->factory->forum->create();
		$topic_id   = $this->factory->topic->create( array(
			'post_parent' => $child_id,
			'post_title'  => 'Members only fixture topic',
			'topic_meta'  => array( 'forum_id' => $child_id ),
		) );
		$reply_id   = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $child_id, 'topic_id' => $topic_id ),
		) );
		$media_id   = wp_insert_attachment( array(
			'post_status'    => 'inherit',
			'post_parent'    => $topic_id,
			'post_mime_type' => 'image/jpeg',
			'guid'           => 'https://example.org/group-forum-attachment.jpg',
		) );

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		bbp_set_user_role( $outsider_id, bbp_get_participant_role() );
		groups_join_group( $group_id, $no_role_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		update_option( '_bp_community_visibility', array( 'global' => 'members' ) );
		$this->group_extension = new BBP_Forums_Group_Extension();
		$items = array(
			bbp_get_forum_post_type() => array( $forum_id, $child_id ),
			bbp_get_topic_post_type() => array( $topic_id ),
			bbp_get_reply_post_type() => array( $reply_id ),
			'media' => array( $media_id ),
		);
		$controllers = array(
			bbp_get_forum_post_type() => new BBP_REST_Posts_Controller( bbp_get_forum_post_type() ),
			bbp_get_topic_post_type() => new BBP_REST_Posts_Controller( bbp_get_topic_post_type() ),
			bbp_get_reply_post_type() => new BBP_REST_Posts_Controller( bbp_get_reply_post_type() ),
		);
		$check_rest_read = function( $controller, $post_id ) {
			$post = get_post( $post_id );
			$show_in_rest = array();
			foreach ( bbp_get_post_types() as $post_type ) {
				$object = get_post_type_object( $post_type );
				$show_in_rest[ $post_type ] = $object->show_in_rest;
				$object->show_in_rest = true;
			}
			try {
				return $controller->check_read_permission( $post );
			} finally {
				foreach ( $show_in_rest as $post_type => $enabled ) {
					get_post_type_object( $post_type )->show_in_rest = $enabled;
				}
			}
		};

		$this->set_current_user( 0 );
		$this->assertFalse( bp_user_can( 0, 'bp_view', array( 'bp_component' => 'groups' ) ) );
		$this->assertTrue( bbp_is_forum_restricted_for_user( $child_id, 0 ) );
		$this->assertFalse( bbp_user_can_view_forum( array( 'forum_id' => $child_id ) ) );
		$this->assertContains( $forum_id, bbp_get_excluded_forum_ids() );
		$this->assertContains( $child_id, bbp_get_excluded_forum_ids() );
		foreach ( $items as $post_type => $post_ids ) {
			foreach ( $post_ids as $post_id ) {
				if ( isset( $controllers[ $post_type ] ) ) {
					$this->assertFalse( $check_rest_read( $controllers[ $post_type ], $post_id ) );
				}
				if ( 'media' === $post_type || get_post_type_object( $post_type )->show_in_rest ) {
					$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/' . $post_type . '/' . $post_id ) );
					$this->assertSame( 401, $response->get_status() );
				}
			}
		}
		$this->assertTrue( $check_rest_read( $controllers[ bbp_get_forum_post_type() ], $normal_id ) );
		if ( get_post_type_object( bbp_get_forum_post_type() )->show_in_rest ) {
			$this->assertSame( 200, rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/forum/' . $normal_id ) )->get_status() );
		}
		foreach ( $controllers as $post_type => $controller ) {
			$post_ids = $items[ $post_type ];
			$posts = get_posts( array( 'post_type' => $post_type, 'numberposts' => -1, 'suppress_filters' => false ) );
			$this->assertSame( array(), array_intersect( $post_ids, wp_list_pluck( $posts, 'ID' ) ) );
			if ( get_post_type_object( $post_type )->show_in_rest ) {
				$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/' . $post_type ) );
				$this->assertSame( 200, $response->get_status() );
				$this->assertSame( array(), array_intersect( $post_ids, wp_list_pluck( $response->get_data(), 'id' ) ) );
			}
		}
		if ( get_post_type_object( bbp_get_topic_post_type() )->show_in_rest ) {
			$search = new WP_REST_Request( 'GET', '/wp/v2/search' );
			$search->set_param( 'search', 'Members only fixture topic' );
			$search->set_param( 'subtype', bbp_get_topic_post_type() );
			$response = rest_get_server()->dispatch( $search );
			$this->assertSame( 200, $response->get_status() );
			$this->assertNotContains( $topic_id, wp_list_pluck( $response->get_data(), 'id' ) );
		}
		$this->assertSame( 0, bbp_filter_oembed_request_post_id( $topic_id ) );
		$this->assertSame( 0, bbp_filter_oembed_request_attachment_id( $media_id ) );
		$this->assertFalse( get_oembed_response_data_for_url( home_url( '/?p=' . $topic_id ), array( 'width' => 600 ) ) );
		$this->assertSame( 0, bbp_filter_old_slug_redirect_post_id( $topic_id ) );
		$this->assertEmpty( apply_filters( 'redirect_canonical', get_permalink( $topic_id ), home_url( '/?p=' . $topic_id ) ) );
		$this->assertSame( array(), $this->get_shortcode_template_parts( 'display_forum', $child_id ) );
		$this->assertSame( array(), $this->get_shortcode_template_parts( 'display_topic', $topic_id ) );
		$this->assertSame( array(), $this->get_shortcode_template_parts( 'display_reply', $reply_id ) );
		$this->set_current_user( $creator_id );
		$this->assertTrue( bbp_is_forum_restricted_for_user( $child_id, 0 ) );
		$this->set_current_user( 0 );
		$this->assertFalse( bbp_is_forum_restricted_for_user( $child_id, $creator_id ) );

		foreach ( array( $creator_id, $no_role_id, $outsider_id ) as $user_id ) {
			$this->set_current_user( $user_id );
			$this->assertTrue( bp_user_can( $user_id, 'bp_view', array( 'bp_component' => 'groups' ) ) );
			$this->assertFalse( bbp_is_forum_restricted_for_user( $child_id, $user_id ) );
			foreach ( $items as $post_type => $post_ids ) {
				foreach ( $post_ids as $post_id ) {
					if ( isset( $controllers[ $post_type ] ) ) {
						$this->assertTrue( $check_rest_read( $controllers[ $post_type ], $post_id ), $post_type . ' for user ' . $user_id );
					}
					if ( 'media' === $post_type || get_post_type_object( $post_type )->show_in_rest ) {
						$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/' . $post_type . '/' . $post_id ) );
						$this->assertSame( 200, $response->get_status() );
					}
				}
			}
			$this->assertSame( $topic_id, bbp_filter_oembed_request_post_id( $topic_id ) );
			$this->assertSame( $media_id, bbp_filter_oembed_request_attachment_id( $media_id ) );
			$this->assertNotFalse( get_oembed_response_data_for_url( home_url( '/?p=' . $topic_id ), array( 'width' => 600 ) ) );
			$this->assertSame( $topic_id, bbp_filter_old_slug_redirect_post_id( $topic_id ) );
			$this->assertSame( array( 'content-single-topic' ), $this->get_shortcode_template_parts( 'display_topic', $topic_id ) );
		}

		update_option( '_bp_community_visibility', array( 'global' => 'anyone' ) );
		$this->set_current_user( 0 );
		$this->assertFalse( bbp_is_forum_restricted_for_user( $child_id, 0 ) );
		$this->assertTrue( $check_rest_read( $controllers[ bbp_get_topic_post_type() ], $topic_id ) );
		if ( get_post_type_object( bbp_get_topic_post_type() )->show_in_rest ) {
			$this->assertSame( 200, rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/topic/' . $topic_id ) )->get_status() );
		}
		$this->assertSame( array( 'content-single-topic' ), $this->get_shortcode_template_parts( 'display_topic', $topic_id ) );
		$this->set_current_user( $outsider_id );
		bbp_privatize_forum( $forum_id );
		$this->assertTrue( bbp_is_forum_restricted_for_user( $child_id, $outsider_id ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_read_meta_caps
	 * @covers ::BBP_Forums_Group_Extension::exclude_group_forum_ids
	 */
	public function test_group_member_without_forum_role_can_read_only_their_group_forum() {
		$creator_id       = $this->factory->user->create();
		$member_id        = $this->factory->user->create();
		$outsider_id      = $this->factory->user->create();
		$group_id         = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$other_group_id   = $this->bp_factory->group->create();
		$forum_id         = $this->factory->forum->create();
		$other_forum_id   = $this->factory->forum->create();
		$hidden_parent_id = $this->factory->forum->create();
		$nested_forum_id  = $this->factory->forum->create( array( 'post_parent' => $hidden_parent_id ) );
		$topic_id         = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id         = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );

		groups_join_group( $group_id, $member_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->attach_forum_to_group( $other_forum_id, $other_group_id );
		$this->attach_forum_to_group( $nested_forum_id, $group_id );
		bbp_privatize_forum( $forum_id );
		bbp_privatize_forum( $other_forum_id );
		bbp_privatize_forum( $nested_forum_id );
		bbp_hide_forum( $hidden_parent_id );
		$this->group_extension = new BBP_Forums_Group_Extension();
		get_userdata( $member_id )->remove_role( bbp_get_user_role( $member_id ) );

		$this->assertFalse( bbp_get_user_role( $member_id ) );
		$this->assertTrue( $this->group_extension->user_can_view_group_forum( $member_id, $forum_id ) );
		$this->assertSame( array( 'exist' ), map_meta_cap( 'read_forum', $member_id, $forum_id ) );
		$this->assertTrue( user_can( $member_id, 'read_forum', $forum_id ) );
		$this->assertTrue( user_can( $member_id, 'read_topic', $topic_id ) );
		$this->assertTrue( user_can( $member_id, 'read_reply', $reply_id ) );
		$this->assertFalse( user_can( $member_id, 'read_forum', $other_forum_id ) );
		$this->assertFalse( user_can( $member_id, 'read_forum', $nested_forum_id ) );
		$this->assertFalse( user_can( $outsider_id, 'read_forum', $forum_id ) );
		$this->assertFalse( user_can( $outsider_id, 'read_topic', $topic_id ) );
		$this->assertFalse( user_can( $outsider_id, 'read_reply', $reply_id ) );
		bbp_spam_topic( $topic_id );
		$this->assertFalse( user_can( $member_id, 'read_topic', $topic_id ) );
		$this->assertFalse( user_can( $member_id, 'read_reply', $reply_id ) );

		$this->set_current_user( $member_id );
		$this->assertNotContains( $forum_id, bbp_get_excluded_forum_ids() );
		$this->assertContains( $other_forum_id, bbp_get_excluded_forum_ids() );
		$this->assertContains( $nested_forum_id, bbp_get_excluded_forum_ids() );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::on_group_forum_page
	 */
	public function test_rewrite_group_forum_filters_wait_for_parsed_group_context() {
		if ( 'rewrites' !== bp_core_get_query_parser() ) {
			$this->markTestSkipped( 'BuddyPress rewrites are not active.' );
		}

		$group_id              = $this->bp_factory->group->create();
		$group                 = groups_get_group( $group_id );
		$this->group_extension = new BBP_Forums_Group_Extension();

		$this->assertNotFalse( has_action( 'bp_parse_query', array( $this->group_extension, 'on_group_forum_page' ) ) );
		$this->assertFalse( has_filter( 'bbp_is_single_forum', array( $this->group_extension, 'is_single_forum' ) ) );
		$this->assertTrue( $this->group_extension->enable_nav_item );

		$rewrite_ids = buddypress()->groups->rewrite_ids;
		buddypress()->pages->groups->id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$query       = new WP_Query();
		$query->set( $rewrite_ids['directory'], 1 );
		$query->set( $rewrite_ids['single_item'], $group->slug );
		$query->set( $rewrite_ids['single_item_action'], 'forum' );
		$nav_state = array();
		$check_nav = function() use ( &$nav_state ) {
			$nav_state['forum_filter'] = has_filter( 'bbp_is_single_forum', array( $this->group_extension, 'is_single_forum' ) );
			$nav_state['single_forum'] = bbp_is_single_forum();
			$nav_state['menu']         = $this->group_extension->enable_nav_item;
		};
		add_action( 'bp_setup_nav', $check_nav );
		do_action( 'bp_parse_query', $query );
		remove_action( 'bp_setup_nav', $check_nav );

		$this->assertTrue( bp_is_group() );
		$this->assertNotFalse( $nav_state['forum_filter'] );
		$this->assertTrue( $nav_state['single_forum'] );
		$this->assertTrue( $nav_state['menu'] );
		$this->assertTrue( bbp_is_single_forum() );
		$this->assertNotFalse( has_filter( 'bbp_is_single_forum', array( $this->group_extension, 'is_single_forum' ) ) );
		$this->assertNotFalse( has_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ) ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::maybe_unset_forum_menu
	 */
	public function test_rewrite_group_without_forum_hides_forum_menu_after_parsing() {
		if ( 'rewrites' !== bp_core_get_query_parser() ) {
			$this->markTestSkipped( 'BuddyPress rewrites are not active.' );
		}

		$group_id              = $this->bp_factory->group->create( array( 'enable_forum' => false ) );
		$group                 = groups_get_group( $group_id );
		$this->group_extension = new BBP_Forums_Group_Extension();
		$this->assertTrue( $this->group_extension->enable_nav_item );

		$rewrite_ids = buddypress()->groups->rewrite_ids;
		buddypress()->pages->groups->id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$query       = new WP_Query();
		$query->set( $rewrite_ids['directory'], 1 );
		$query->set( $rewrite_ids['single_item'], $group->slug );
		do_action( 'bp_parse_query', $query );

		$this->assertTrue( bp_is_group() );
		$this->assertFalse( $this->group_extension->enable_nav_item );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::exclude_group_forum_ids
	 */
	public function test_private_group_forum_excludes_descendants_from_non_members() {
		$creator_id   = $this->factory->user->create();
		$member_id    = $this->factory->user->create();
		$outsider_id  = $this->factory->user->create();
		$moderator_id = $this->factory->user->create();
		$group_id     = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id    = $this->factory->forum->create();
		$child_id    = $this->factory->forum->create( array( 'post_parent' => $forum_id ) );
		$grandchild_id = $this->factory->forum->create( array( 'post_parent' => $child_id ) );
		$topic_id    = $this->factory->topic->create(
			array(
				'post_parent' => $grandchild_id,
				'topic_meta'  => array( 'forum_id' => $grandchild_id ),
			)
		);

		foreach ( array( $creator_id, $member_id, $outsider_id, $moderator_id ) as $user_id ) {
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
		}
		groups_join_group( $group_id, $member_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		bbp_privatize_forum( $forum_id );
		bbp_add_moderator( $grandchild_id, $moderator_id );
		$this->group_extension = new BBP_Forums_Group_Extension();

		$this->set_current_user( $outsider_id );
		$this->assertFalse( user_can( $outsider_id, 'read_forum', $grandchild_id ) );
		$excluded = bbp_get_excluded_forum_ids();
		$this->assertContains( $forum_id, $excluded );
		$this->assertContains( $child_id, $excluded );
		$this->assertContains( $grandchild_id, $excluded );

		bbp_has_topics( array( 'post_parent' => 'any', 'posts_per_page' => -1, 'show_stickies' => false ) );
		$this->assertNotContains( $topic_id, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );

		$this->set_current_user( $member_id );
		$this->assertTrue( user_can( $member_id, 'read_forum', $grandchild_id ) );
		$this->assertNotContains( $grandchild_id, bbp_get_excluded_forum_ids() );

		$this->set_current_user( $moderator_id );
		$this->assertTrue( user_can( $moderator_id, 'read_forum', $grandchild_id ) );
		$this->assertNotContains( $grandchild_id, bbp_get_excluded_forum_ids() );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::exclude_group_forum_ids
	 */
	public function test_private_group_forum_excludes_descendants_below_trashed_forum() {
		$creator_id   = $this->factory->user->create();
		$outsider_id  = $this->factory->user->create();
		$group_id     = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id     = $this->factory->forum->create();
		$child_id     = $this->factory->forum->create( array( 'post_parent' => $forum_id ) );
		$grandchild_id = $this->factory->forum->create( array( 'post_parent' => $child_id ) );

		bbp_set_user_role( $outsider_id, bbp_get_participant_role() );
		get_user_by( 'id', $outsider_id )->add_cap( 'read_private_forums' );
		$this->attach_forum_to_group( $forum_id, $group_id );
		bbp_privatize_forum( $forum_id );
		wp_trash_post( $child_id );
		$this->group_extension = new BBP_Forums_Group_Extension();
		$this->set_current_user( $outsider_id );

		$this->assertTrue( current_user_can( 'read_private_forums' ) );
		$this->assertFalse( current_user_can( 'read_forum', $forum_id ) );
		$this->assertContains( $grandchild_id, bbp_get_excluded_forum_ids() );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_read_meta_caps
	 * @covers ::BBP_Forums_Group_Extension::exclude_group_forum_ids
	 * @covers ::BBP_Forums_Group_Extension::subscription_user_can_view_forum
	 */
	public function test_group_forum_access_preserves_ancestor_restrictions() {
		$creator_id       = $this->factory->user->create();
		$member_id        = $this->factory->user->create();
		$group_id         = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$other_group_id   = $this->bp_factory->group->create();
		$hidden_parent_id = $this->factory->forum->create();
		$denied_forum_id  = $this->factory->forum->create( array( 'post_parent' => $hidden_parent_id ) );
		$group_forum_id   = $this->factory->forum->create();
		$other_forum_id   = $this->factory->forum->create();
		$public_child_id  = $this->factory->forum->create( array( 'post_parent' => $group_forum_id ) );
		$public_grandchild_id = $this->factory->forum->create( array( 'post_parent' => $public_child_id ) );
		$denied_topic_id  = $this->factory->topic->create( array( 'post_parent' => $denied_forum_id ) );
		$allowed_topic_id = $this->factory->topic->create( array( 'post_parent' => $public_grandchild_id ) );

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		bbp_set_user_role( $member_id, bbp_get_participant_role() );
		groups_join_group( $group_id, $member_id );
		$this->attach_forum_to_group( $denied_forum_id, $group_id );
		$this->attach_forum_to_group( $group_forum_id, $group_id );
		$this->attach_forum_to_group( $other_forum_id, $other_group_id );
		bbp_hide_forum( $hidden_parent_id );
		bbp_privatize_forum( $denied_forum_id );
		bbp_privatize_forum( $group_forum_id );
		bbp_privatize_forum( $other_forum_id );

		$this->set_group_context( $group_id, $member_id );

		$this->assertNotContains( $hidden_parent_id, bbp_get_group_forum_ids() );
		$this->assertFalse( user_can( $member_id, 'read_forum', $hidden_parent_id ) );
		$this->assertFalse( user_can( $member_id, 'read_forum', $denied_forum_id ) );
		$this->assertFalse( user_can( $member_id, 'read_forum', $other_forum_id ) );
		$this->assertTrue( user_can( $member_id, 'read_forum', $group_forum_id ) );
		$this->assertTrue( user_can( $member_id, 'read_forum', $public_child_id ) );
		$this->assertTrue( user_can( $member_id, 'read_forum', $public_grandchild_id ) );
		$this->assertSame(
			array(),
			array_values( bbp_filter_subscription_user_ids( array( $member_id ), $denied_forum_id, $denied_topic_id ) )
		);

		$this->set_current_user( $member_id );
		$excluded = bbp_get_excluded_forum_ids();
		$this->assertContains( $hidden_parent_id, $excluded );
		$this->assertContains( $denied_forum_id, $excluded );
		$this->assertNotContains( $group_forum_id, $excluded );
		$this->assertNotContains( $public_child_id, $excluded );
		$this->assertNotContains( $public_grandchild_id, $excluded );

		bbp_has_topics(
			array(
				'post_parent'    => 'any',
				'posts_per_page' => -1,
				'show_stickies'  => false,
			)
		);
		$topic_ids = wp_list_pluck( bbpress()->topic_query->posts, 'ID' );

		$this->assertContains( $allowed_topic_id, $topic_ids );
		$this->assertNotContains( $denied_topic_id, $topic_ids );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_meta_caps
	 */
	public function test_group_context_does_not_map_caps_for_another_group_forum() {
		$creator_id       = $this->factory->user->create();
		$group_mod_id     = $this->factory->user->create();
		$group_id         = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$other_group_id   = $this->bp_factory->group->create();
		$group_forum_id   = $this->factory->forum->create();
		$other_forum_id   = $this->factory->forum->create();
		$group_topic_id   = $this->factory->topic->create( array( 'post_parent' => $group_forum_id ) );
		$other_topic_id   = $this->factory->topic->create( array( 'post_parent' => $other_forum_id ) );
		$group_reply_id   = $this->factory->reply->create(
			array(
				'post_parent' => $group_topic_id,
				'reply_meta'  => array(
					'_bbp_forum_id' => $group_forum_id,
					'_bbp_topic_id' => $group_topic_id,
				),
			)
		);
		$other_reply_id   = $this->factory->reply->create(
			array(
				'post_parent' => $other_topic_id,
				'reply_meta'  => array(
					'_bbp_forum_id' => $other_forum_id,
					'_bbp_topic_id' => $other_topic_id,
				),
			)
		);

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		bbp_set_user_role( $group_mod_id, bbp_get_participant_role() );
		groups_join_group( $group_id, $group_mod_id );
		groups_promote_member( $group_mod_id, $group_id, 'mod', $creator_id );
		$this->attach_forum_to_group( $group_forum_id, $group_id );
		$this->attach_forum_to_group( $other_forum_id, $other_group_id );
		bbp_privatize_forum( $group_forum_id );
		bbp_privatize_forum( $other_forum_id );
		$this->set_group_context( $group_id, $group_mod_id );

		$this->assertTrue( bbp_group_is_mod() );
		$this->assertTrue( user_can( $group_mod_id, 'read_forum', $group_forum_id ) );
		$this->assertFalse( user_can( $group_mod_id, 'read_forum', $other_forum_id ) );
		$this->assertFalse( user_can( $group_mod_id, 'moderate', $other_forum_id ) );
		$this->assertTrue( user_can( $group_mod_id, 'edit_topic', $group_topic_id ) );
		$this->assertTrue( user_can( $group_mod_id, 'edit_reply', $group_reply_id ) );
		$this->assertFalse( user_can( $group_mod_id, 'edit_topic', $other_topic_id ) );
		$this->assertFalse( user_can( $group_mod_id, 'edit_reply', $other_reply_id ) );
		$this->assertFalse( user_can( $group_mod_id, 'delete_topic', $other_topic_id ) );
		$this->assertFalse( user_can( $group_mod_id, 'delete_reply', $other_reply_id ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_meta_caps
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_read_meta_caps
	 * @covers ::BBP_Shortcodes::display_forum
	 * @covers ::BBP_Shortcodes::display_topic
	 * @covers ::BBP_Shortcodes::display_reply
	 */
	public function test_group_context_does_not_expose_another_group_forum_via_shortcodes() {
		$creator_id     = $this->factory->user->create();
		$member_id      = $this->factory->user->create();
		$group_id       = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$other_group_id = $this->bp_factory->group->create();
		$group_forum_id = $this->factory->forum->create();
		$group_topic_id = $this->factory->topic->create( array( 'post_parent' => $group_forum_id ) );
		$group_reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $group_topic_id,
				'reply_meta'  => array(
					'_bbp_forum_id' => $group_forum_id,
					'_bbp_topic_id' => $group_topic_id,
				),
			)
		);
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id       = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'_bbp_forum_id' => $forum_id,
					'_bbp_topic_id' => $topic_id,
				),
			)
		);

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		bbp_set_user_role( $member_id, bbp_get_participant_role() );
		groups_join_group( $group_id, $member_id );
		$this->attach_forum_to_group( $group_forum_id, $group_id );
		$this->attach_forum_to_group( $forum_id, $other_group_id );
		bbp_privatize_forum( $group_forum_id );
		bbp_privatize_forum( $forum_id );
		$this->set_group_context( $group_id, $member_id );

		$this->assertSame(
			array(
				'forum' => array( 'feedback-no-access' ),
				'topic' => array( 'feedback-no-access' ),
				'reply' => array( 'feedback-no-access' ),
			),
			array(
				'forum' => $this->get_shortcode_template_parts( 'display_forum', $forum_id ),
				'topic' => $this->get_shortcode_template_parts( 'display_topic', $topic_id ),
				'reply' => $this->get_shortcode_template_parts( 'display_reply', $reply_id ),
			)
		);

		$this->assertSame(
			array(
				'forum' => array( 'content-single-forum' ),
				'topic' => array( 'content-single-topic' ),
				'reply' => array( 'content-single-reply' ),
			),
			array(
				'forum' => $this->get_shortcode_template_parts( 'display_forum', $group_forum_id ),
				'topic' => $this->get_shortcode_template_parts( 'display_topic', $group_topic_id ),
				'reply' => $this->get_shortcode_template_parts( 'display_reply', $group_reply_id ),
			)
		);
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::user_can_view_group_forum
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_read_meta_caps
	 */
	public function test_group_forum_access_preserves_moderator_and_inactive_user_semantics() {
		$creator_id       = $this->factory->user->create();
		$stale_mod_id     = $this->factory->user->create();
		$filtered_mod_id  = $this->factory->user->create();
		$custom_mod_id    = $this->factory->user->create();
		$inactive_user_id = $this->factory->user->create();
		$group_id         = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id         = $this->factory->forum->create();

		foreach ( array( $creator_id, $stale_mod_id, $filtered_mod_id, $custom_mod_id ) as $user_id ) {
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
		}
		bbp_set_user_role( $inactive_user_id, bbp_get_blocked_role() );
		groups_join_group( $group_id, $inactive_user_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		bbp_privatize_forum( $forum_id );
		bbp_add_moderator( $forum_id, $stale_mod_id );
		get_user_by( 'id', $custom_mod_id )->add_cap( 'moderate' );
		update_option( '_bbp_allow_forum_mods', 0 );

		$filter_moderator = function( $retval, $user_id, $forum_id ) use ( $filtered_mod_id ) {
			return ( $filtered_mod_id === $user_id ) ? true : $retval;
		};
		add_filter( 'bbp_is_user_forum_moderator', $filter_moderator, 10, 3 );
		$this->group_extension = new BBP_Forums_Group_Extension();

		$this->assertFalse( user_can( $stale_mod_id, 'read_forum', $forum_id ) );
		$this->assertTrue( user_can( $filtered_mod_id, 'read_forum', $forum_id ) );
		$this->assertTrue( user_can( $custom_mod_id, 'read_forum', $forum_id ) );
		$this->assertFalse( user_can( $inactive_user_id, 'read_forum', $forum_id ) );

		remove_filter( 'bbp_is_user_forum_moderator', $filter_moderator, 10 );
	}

	protected function set_group_context( $group_id, $user_id ) {
		$group = groups_get_group( $group_id );

		$this->set_current_user( $user_id );
		buddypress()->groups->current_group = $group;
		buddypress()->current_component  = 'groups';
		buddypress()->current_item       = $group->slug;
		buddypress()->current_action     = 'forum';

		unset( bbpress()->current_user->is_group_admin );
		unset( bbpress()->current_user->is_group_mod );
		unset( bbpress()->current_user->is_group_banned );

		$this->group_extension = new BBP_Forums_Group_Extension();
		add_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ), 99, 4 );
	}

	/**
	 * @covers ::bbp_is_group_pending_topic
	 * @covers ::bbp_get_group_pending_topic_id
	 */
	public function test_pending_group_topic_route_respects_topic_and_group_permissions() {
		$creator_id    = $this->factory->user->create();
		$author_id     = $this->factory->user->create();
		$member_id     = $this->factory->user->create();
		$group_mod_id  = $this->factory->user->create();
		$moderator_id  = $this->factory->user->create();
		$keymaster_id  = $this->factory->user->create();
		$group_id       = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$other_group_id = $this->bp_factory->group->create();
		$forum_id       = $this->factory->forum->create();
		$other_forum_id = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create(
			array(
				'post_author' => $author_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$published_topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$other_topic_id     = $this->factory->topic->create(
			array(
				'post_parent' => $other_forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $other_forum_id ),
			)
		);

		foreach ( array( $creator_id, $author_id, $member_id, $group_mod_id ) as $user_id ) {
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
		}
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		groups_join_group( $group_id, $author_id );
		groups_join_group( $group_id, $member_id );
		groups_join_group( $group_id, $group_mod_id );
		groups_promote_member( $group_mod_id, $group_id, 'mod', $creator_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->attach_forum_to_group( $other_forum_id, $other_group_id );
		$this->set_group_context( $group_id, $author_id );

		buddypress()->is_single_item   = true;
		buddypress()->action_variables = array( $this->group_extension->topic_slug, bbp_get_pending_status_id() );
		$_GET['bbp_topic_id']          = $topic_id;

		$this->assertTrue( bbp_is_group_pending_topic() );
		$this->assertSame( $topic_id, bbp_get_group_pending_topic_id() );

		foreach ( array( $creator_id, $group_mod_id, $moderator_id, $keymaster_id ) as $user_id ) {
			$this->set_current_user( $user_id );
			unset( bbpress()->current_user->is_group_admin, bbpress()->current_user->is_group_mod, bbpress()->current_user->is_group_banned );
			$this->assertSame( $topic_id, bbp_get_group_pending_topic_id() );
		}

		foreach ( array( $member_id, 0 ) as $user_id ) {
			$this->set_current_user( $user_id );
			unset( bbpress()->current_user->is_group_admin, bbpress()->current_user->is_group_mod, bbpress()->current_user->is_group_banned );
			$this->assertSame( 0, bbp_get_group_pending_topic_id() );
		}

		$this->set_current_user( $keymaster_id );
		$_GET['bbp_topic_id'] = $other_topic_id;
		$this->assertSame( 0, bbp_get_group_pending_topic_id() );

		$_GET['bbp_topic_id'] = $published_topic_id;
		$this->assertSame( 0, bbp_get_group_pending_topic_id() );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::map_topic_permalink_to_group
	 * @covers ::BBP_Forums_Group_Extension::map_topic_edit_url_to_group
	 */
	public function test_pending_topic_uses_id_route_until_published() {
		$author_id = $this->factory->user->create();
		$group_id  = $this->bp_factory->group->create( array( 'creator_id' => $author_id ) );
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create(
			array(
				'post_author' => $author_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'post_title'  => 'Pending group route',
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->set_group_context( $group_id, $author_id );

		$topic_url = wp_parse_url( html_entity_decode( bbp_get_topic_permalink( $topic_id ) ) );
		$edit_url  = wp_parse_url( html_entity_decode( bbp_get_topic_edit_url( $topic_id ) ) );
		parse_str( $topic_url['query'], $topic_query );
		parse_str( $edit_url['query'], $edit_query );

		$topic_route = isset( $topic_query['bp_group_action_variables'] )
			? '/' . $topic_query['bp_group_action'] . '/' . implode( '/', $topic_query['bp_group_action_variables'] ) . '/'
			: $topic_url['path'];
		$edit_route  = isset( $edit_query['bp_group_action_variables'] )
			? '/' . $edit_query['bp_group_action'] . '/' . implode( '/', $edit_query['bp_group_action_variables'] ) . '/'
			: $edit_url['path'];

		$this->assertStringEndsWith( '/forum/topic/pending/', $topic_route );
		$this->assertStringEndsWith( '/forum/topic/pending/edit/', $edit_route );
		$this->assertSame( (string) $topic_id, $topic_query['bbp_topic_id'] );
		$this->assertSame( 'all', $topic_query['view'] );
		$this->assertSame( (string) $topic_id, $edit_query['bbp_topic_id'] );
		$this->assertSame( 'all', $edit_query['view'] );

		$filter_topic_id = function() use ( $topic_id ) {
			return $topic_id;
		};
		add_filter( 'bbp_get_topic_id', $filter_topic_id );
		$pagination = $this->group_extension->replies_pagination( array() );
		remove_filter( 'bbp_get_topic_id', $filter_topic_id );
		$pagination_base = html_entity_decode( $pagination['base'] );
		$pagination_url  = wp_parse_url( $pagination_base );
		parse_str( $pagination_url['query'], $pagination_query );

		$this->assertStringContainsString( 'paged=%#%', $pagination_base );
		$this->assertSame( (string) $topic_id, $pagination_query['bbp_topic_id'] );
		$this->assertSame( 'all', $pagination_query['view'] );

		bbp_approve_topic( $topic_id );
		$published_url = wp_parse_url( html_entity_decode( bbp_get_topic_permalink( $topic_id ) ) );
		parse_str( $published_url['query'], $published_query );
		$published_route = isset( $published_query['bp_group'] ) ? $published_query['bp_group'] : $published_url['path'];

		$this->assertStringEndsWith( '/forum/topic/pending-group-route/', $published_route );
		$this->assertArrayNotHasKey( 'bbp_topic_id', $published_query );
	}

	/**
	 * @covers ::bbp_filter_modify_page_title
	 */
	public function test_group_topic_page_title_only_uses_visible_topics_in_current_group() {
		$creator_id    = $this->factory->user->create();
		$group_id      = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id      = $this->factory->forum->create();
		$other_forum   = $this->factory->forum->create();
		$visible_topic = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Visible group topic', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$other_topic   = $this->factory->topic->create( array( 'post_parent' => $other_forum, 'post_title' => 'Other forum topic', 'topic_meta' => array( 'forum_id' => $other_forum ) ) );
		$spam_topic    = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Spam group topic', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$trash_topic   = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Trash group topic', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$pending_topic = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Pending group topic', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->set_group_context( $group_id, $creator_id );
		$this->set_current_user( 0 );
		buddypress()->is_single_item = true;
		buddypress()->action_variables = array( $this->group_extension->topic_slug, get_post_field( 'post_name', $visible_topic ) );
		$this->assertContains( $forum_id, bbp_get_group_forum_ids() );
		$this->assertTrue( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) );
		$this->assertTrue( bbp_is_topic_public( $visible_topic ) );

		$this->assertSame( 'GroupVisible group topic | ', bbp_filter_modify_page_title( 'Group', '', '|' ) );

		wp_update_post( array( 'ID' => $spam_topic, 'post_status' => bbp_get_spam_status_id() ) );
		wp_trash_post( $trash_topic );
		wp_update_post( array( 'ID' => $pending_topic, 'post_status' => bbp_get_pending_status_id() ) );

		foreach ( array( $other_topic, $spam_topic, $trash_topic, $pending_topic ) as $topic_id ) {
			buddypress()->action_variables = array( $this->group_extension->topic_slug, get_post_field( 'post_name', $topic_id ) );
			$this->assertSame( 'Group', bbp_filter_modify_page_title( 'Group', '', '|' ) );
		}

		buddypress()->action_variables = array( $this->group_extension->topic_slug, 'missing-group-topic' );
		$this->assertSame( 'Group', bbp_filter_modify_page_title( 'Group', '', '|' ) );

		foreach ( array( array( $this->group_extension->topic_slug ), array( $this->group_extension->topic_slug, '' ) ) as $action_variables ) {
			buddypress()->action_variables = $action_variables;
			$this->assertSame( 'Group', bbp_filter_modify_page_title( 'Group', '', '|' ) );
		}

		bbp_privatize_forum( $forum_id );
		buddypress()->action_variables = array( $this->group_extension->topic_slug, get_post_field( 'post_name', $visible_topic ) );
		$this->assertSame( 'Group', bbp_filter_modify_page_title( 'Group', '', '|' ) );

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		$this->set_current_user( $creator_id );
		$this->assertSame( 'GroupVisible group topic | ', bbp_filter_modify_page_title( 'Group', '', '|' ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::display_forums
	 * @ticket BBP3743
	 * @dataProvider invalid_group_topic_routes
	 */
	public function test_group_topic_route_requires_topic_slug( $route_variables ) {
		$creator_id = $this->factory->user->create();
		$group_id   = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id   = $this->factory->forum->create();
		$this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Missing route sentinel 8026', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->set_group_context( $group_id, $creator_id );
		$this->set_current_user( 0 );
		buddypress()->action_variables = array_merge( array( $this->group_extension->topic_slug ), $route_variables );
		$this->group_extension->rewrite_pagination( bbp_get_wp_query(), 'wp_query' );
		$this->template_parts = array();
		add_filter( 'bbp_get_template_part', array( $this, 'capture_template_part' ), 10, 3 );
		ob_start();
		$this->group_extension->display_forums();
		$html = ob_get_clean();
		remove_filter( 'bbp_get_template_part', array( $this, 'capture_template_part' ), 10 );

		$this->assertContains( 'feedback-no-topics', $this->template_parts );
		$this->assertStringNotContainsString( 'Missing route sentinel 8026', $html );
		$this->assertSame( 1, bbpress()->topic_query->get( 'paged' ) );
		$this->assertSame( 0, bbpress()->topic_query->post_count );
	}

	public function invalid_group_topic_routes() {
		return array(
			'missing slug'               => array( array() ),
			'empty slug'                 => array( array( '' ) ),
			'unknown slug on reply page' => array( array( 'missing-group-topic', 'page', '2' ) ),
		);
	}

	/**
	 * @covers ::bbp_has_replies
	 * @covers ::BBP_Forums_Group_Extension::display_forums
	 * @covers ::BBP_Forums_Group_Extension::rewrite_pagination
	 * @ticket BBP3750
	 * @dataProvider group_topic_reply_pages
	 */
	public function test_group_topic_route_loads_requested_reply_page( $page_number, $expected_result ) {
		$creator_id = $this->factory->user->create();
		$group_id   = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id   = $this->factory->forum->create();
		$topic_id   = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => 'Paginated group topic 3750',
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_ids = array();

		foreach ( array( 'First group reply 3750', 'Second group reply 3750' ) as $reply_content ) {
			$reply_ids[] = $this->factory->reply->create(
				array(
					'post_content' => $reply_content,
					'post_parent'  => $topic_id,
					'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
				)
			);
		}

		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->set_group_context( $group_id, $creator_id );
		$this->set_current_user( 0 );
		buddypress()->action_variables = array( $this->group_extension->topic_slug, get_post_field( 'post_name', $topic_id ), 'page', (string) $page_number );
		$this->group_extension->rewrite_pagination( bbp_get_wp_query(), 'wp_query' );

		$one_reply_per_page = function() {
			return 1;
		};
		add_filter( 'bbp_get_replies_per_page', $one_reply_per_page );
		try {
			$this->template_parts = array();
			add_filter( 'bbp_get_template_part', array( $this, 'capture_template_part' ), 10, 3 );
			ob_start();
			$this->group_extension->display_forums();
			$html = ob_get_clean();
			bbp_has_replies();
		} finally {
			remove_filter( 'bbp_get_template_part', array( $this, 'capture_template_part' ), 10 );
			remove_filter( 'bbp_get_replies_per_page', $one_reply_per_page );
		}

		$this->assertNotContains( 'feedback-no-topics', $this->template_parts );
		$this->assertStringContainsString( 'Paginated group topic 3750', $html );
		$this->assertSame( 1, bbpress()->topic_query->get( 'paged' ) );
		$this->assertSame( array( $topic_id ), wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertSame( $page_number, bbpress()->reply_query->paged );

		if ( null === $expected_result ) {
			$this->assertSame( array(), bbpress()->reply_query->posts );
		} elseif ( 'topic' === $expected_result ) {
			$this->assertSame( array( $topic_id ), wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
			$this->assertSame( bbp_get_topic_post_type(), bbpress()->reply_query->posts[0]->post_type );
		} else {
			$this->assertSame( array( $reply_ids[ $expected_result ] ), wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );
			$this->assertSame( get_post_field( 'post_content', $reply_ids[ $expected_result ] ), bbpress()->reply_query->posts[0]->post_content );
		}
	}

	public function group_topic_reply_pages() {
		return array(
			'first reply page'        => array( 1, 'topic' ),
			'second reply page'       => array( 2, 0 ),
			'third reply page'        => array( 3, 1 ),
			'out-of-range reply page' => array( 99, null ),
		);
	}

	/**
	 * @covers ::bbp_filter_modify_page_title
	 */
	public function test_private_group_topic_page_title_requires_group_access() {
		$creator_id = $this->factory->user->create();
		$group_id   = $this->bp_factory->group->create( array( 'creator_id' => $creator_id, 'status' => 'private' ) );
		$forum_id   = $this->factory->forum->create();
		$topic_id   = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Private group topic', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->set_group_context( $group_id, $creator_id );
		buddypress()->is_single_item   = true;
		buddypress()->action_variables = array( $this->group_extension->topic_slug, get_post_field( 'post_name', $topic_id ) );

		$this->set_current_user( 0 );
		$this->assertTrue( bbp_user_can_view_forum( array( 'forum_id' => $forum_id ) ) );
		$this->assertSame( 'Group', bbp_filter_modify_page_title( 'Group', '', '|' ) );

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		$this->set_current_user( $creator_id );
		$this->assertSame( 'GroupPrivate group topic | ', bbp_filter_modify_page_title( 'Group', '', '|' ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::display_forums
	 */
	public function test_group_reply_route_hides_reply_in_unapproved_topic() {
		$creator_id = $this->factory->user->create();
		$group_id   = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$forum_id   = $this->factory->forum->create();
		$topic_id   = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id   = $this->factory->reply->create( array( 'post_title' => 'Group reply sentinel 7140', 'post_parent' => $topic_id, 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );

		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->set_group_context( $group_id, $creator_id );
		$this->set_current_user( 0 );
		buddypress()->action_variables = array( $this->group_extension->reply_slug, get_post_field( 'post_name', $reply_id ) );
		ob_start();
		$this->group_extension->display_forums();
		$public_html = ob_get_clean();
		$this->assertStringContainsString( 'Group reply sentinel 7140', $public_html );

		bbp_unapprove_topic( $topic_id );

		$this->assertSame( bbp_get_public_status_id(), get_post_status( $reply_id ) );
		$this->template_parts = array();
		add_filter( 'bbp_get_template_part', array( $this, 'capture_template_part' ), 10, 3 );
		ob_start();
		$this->group_extension->display_forums();
		$private_html = ob_get_clean();
		remove_filter( 'bbp_get_template_part', array( $this, 'capture_template_part' ), 10 );

		$this->assertContains( 'feedback-no-replies', $this->template_parts );
		$this->assertStringNotContainsString( 'Group reply sentinel 7140', $private_html );
	}

	protected function attach_forum_to_group( $forum_id, $group_id ) {
		bbp_add_forum_id_to_group( $group_id, $forum_id );
		bbp_add_group_id_to_forum( $forum_id, $group_id );
	}

	protected function get_shortcode_template_parts( $method, $post_id ) {
		$this->template_parts = array();
		add_filter( 'bbp_get_template_part', array( $this, 'capture_template_part' ), 10, 3 );

		bbpress()->shortcodes->{$method}( array( 'id' => $post_id ) );

		remove_filter( 'bbp_get_template_part', array( $this, 'capture_template_part' ), 10 );

		return $this->template_parts;
	}

	public function capture_template_part( $templates, $slug, $name ) {
		$this->template_parts[] = $slug . '-' . $name;

		return $templates;
	}

	protected function submit_forum_edit( $forum_id, $parent_id ) {
		$this->set_request_url();
		$_POST                  = array(
			'bbp_forum_id'         => $forum_id,
			'bbp_forum_parent_id'  => $parent_id,
			'bbp_forum_title'      => bbp_get_forum_title( $forum_id ),
			'bbp_forum_content'    => bbp_get_forum_content( $forum_id ),
			'bbp_forum_status'     => 'closed',
			'bbp_forum_type'       => 'category',
			'bbp_forum_visibility' => bbp_get_public_status_id(),
			'bbp_moderators'       => '',
		);
		$_REQUEST['_wpnonce']    = wp_create_nonce( 'bbp-edit-forum_' . $forum_id );

		$prevent_redirect = function() {
			throw new RuntimeException( 'Forum edit redirect.' );
		};

		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_edit_forum_handler( 'bbp-edit-forum' );
		} catch ( RuntimeException $exception ) {
			if ( 'Forum edit redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		}

		remove_filter( 'wp_redirect', $prevent_redirect );
	}

	protected function submit_group_topic( $forum_id, $label ) {
		$this->set_request_url();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
		$_POST                     = array(
			'bbp_forum_id'     => $forum_id,
			'bbp_topic_title'   => 'Group permission ' . $label,
			'bbp_topic_content' => 'Group permission topic ' . $label,
		);
		$_REQUEST                  = array(
			'_wpnonce' => wp_create_nonce( 'bbp-new-topic' ),
		);

		$topic_id        = 0;
		$capture_topic   = function( $created_topic_id ) use ( &$topic_id ) {
			$topic_id = $created_topic_id;
		};
		$prevent_redirect = function() {
			throw new RuntimeException( 'Group topic redirect.' );
		};

		add_action( 'bbp_new_topic', $capture_topic, 10, 1 );
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_new_topic_handler( 'bbp-new-topic' );
		} catch ( RuntimeException $exception ) {
			if ( 'Group topic redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			remove_action( 'bbp_new_topic', $capture_topic, 10 );
			remove_filter( 'wp_redirect', $prevent_redirect );
		}

		return $topic_id;
	}

	protected function submit_group_reply( $topic_id, $forum_id, $label ) {
		$this->set_request_url();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
		$_POST                     = array(
			'bbp_forum_id'     => $forum_id,
			'bbp_topic_id'     => $topic_id,
			'bbp_reply_content' => 'Group permission reply ' . $label,
		);
		$_REQUEST                  = array(
			'_wpnonce' => wp_create_nonce( 'bbp-new-reply' ),
		);

		$reply_id         = 0;
		$capture_reply    = function( $created_reply_id ) use ( &$reply_id ) {
			$reply_id = $created_reply_id;
		};
		$prevent_redirect = function() {
			throw new RuntimeException( 'Group reply redirect.' );
		};

		add_action( 'bbp_new_reply', $capture_reply, 10, 1 );
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_new_reply_handler( 'bbp-new-reply' );
		} catch ( RuntimeException $exception ) {
			if ( 'Group reply redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			remove_action( 'bbp_new_reply', $capture_reply, 10 );
			remove_filter( 'wp_redirect', $prevent_redirect );
		}

		return $reply_id;
	}

	protected function set_request_url() {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI'] = $home_url['path'];
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::subscription_user_can_view_forum
	 * @covers ::bbp_filter_subscription_user_ids
	 */
	public function test_group_forum_subscription_users_require_current_access() {
		$creator_id       = $this->factory->user->create();
		$member_id        = $this->factory->user->create();
		$banned_id        = $this->factory->user->create();
		$former_id        = $this->factory->user->create();
		$moderator_id     = $this->factory->user->create();
		$spectator_id     = $this->factory->user->create();
		$other_creator_id = $this->factory->user->create();
		$other_member_id  = $this->factory->user->create();
		$group_id         = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$other_group_id   = $this->bp_factory->group->create( array( 'creator_id' => $other_creator_id ) );
		$forum_id         = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );
		$other_forum_id   = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		bbp_set_user_role( $member_id, bbp_get_participant_role() );
		bbp_set_user_role( $banned_id, bbp_get_participant_role() );
		bbp_set_user_role( $former_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $spectator_id, bbp_get_spectator_role() );
		bbp_set_user_role( $other_creator_id, bbp_get_participant_role() );
		bbp_set_user_role( $other_member_id, bbp_get_participant_role() );

		groups_join_group( $group_id, $member_id );
		groups_join_group( $group_id, $banned_id );
		groups_join_group( $group_id, $spectator_id );
		groups_ban_member( $banned_id, $group_id, $creator_id );
		groups_join_group( $other_group_id, $other_member_id );
		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->attach_forum_to_group( $other_forum_id, $other_group_id );
		$this->set_group_context( $group_id, $creator_id );

		$this->assertFalse( $this->group_extension->user_can_view_group_forum( $member_id, $other_forum_id ) );
		$this->assertFalse( user_can( $member_id, 'read_forum', $other_forum_id ) );

		$this->assertSame(
			array( $other_member_id ),
			array_values( bbp_filter_subscription_user_ids( array( $member_id, $other_member_id ), $other_forum_id ) )
		);

		$this->assertSame(
			array( $creator_id, $member_id, $moderator_id ),
			array_values( bbp_filter_subscription_user_ids( array( $creator_id, $member_id, $banned_id, $former_id, $moderator_id, $spectator_id ), $forum_id ) )
		);

		unset( buddypress()->groups->current_group );
		buddypress()->current_component = '';
		buddypress()->current_item      = '';
		buddypress()->current_action    = '';

		$this->assertSame(
			array( $creator_id, $member_id, $moderator_id ),
			array_values( bbp_filter_subscription_user_ids( array( $creator_id, $member_id, $banned_id, $former_id, $moderator_id, $spectator_id ), $forum_id ) )
		);
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_meta_caps
	 * @covers ::bbp_edit_forum_handler
	 * @covers ::bbp_save_forum_extras
	 */
	public function test_group_admin_can_change_forum_attributes_but_not_parent() {
		$user_id      = $this->factory->user->create();
		$group_id     = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );
		$root_id      = $this->factory->forum->create();
		$post_id      = $this->factory->post->create();
		$moderator_id = $this->factory->user->create();
		$forum_id     = $this->factory->forum->create(
			array(
				'post_parent' => $root_id,
				'post_status' => bbp_get_private_status_id(),
			)
		);

		$this->attach_forum_to_group( $forum_id, $group_id );
		bbp_add_moderator( $forum_id, $moderator_id );
		bbp_update_group_forum_ids( $group_id, array( $forum_id, $post_id ) );
		buddypress()->is_single_item = true;
		$this->set_group_context( $group_id, $user_id );

		$this->assertTrue( bbp_group_is_admin() );
		$this->assertTrue( current_user_can( 'moderate' ) );
		$this->assertFalse( current_user_can( 'assign_moderators' ) );
		$this->assertTrue( current_user_can( 'manage_forum_attributes', $forum_id ) );
		$this->assertFalse( current_user_can( 'manage_forum_attributes', $root_id ) );
		$this->assertFalse( current_user_can( 'manage_forum_attributes', $post_id ) );

		$this->submit_forum_edit( $forum_id, 0 );

		$this->assertSame( $root_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_closed( $forum_id ) );
		$this->assertTrue( bbp_is_forum_category( $forum_id ) );
		$this->assertTrue( bbp_is_forum_public( $forum_id, false ) );
		$this->assertSame( array( $moderator_id ), bbp_get_moderator_ids( $forum_id ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_meta_caps
	 * @covers ::bbp_edit_forum_handler
	 * @covers ::bbp_save_forum_extras
	 */
	public function test_group_admin_cannot_change_unattached_forum_attributes() {
		$user_id  = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );
		$root_id  = $this->factory->forum->create();
		$forum_id = $this->factory->forum->create(
			array(
				'post_parent' => $root_id,
				'post_status' => bbp_get_private_status_id(),
			)
		);

		$this->set_group_context( $group_id, $user_id );

		$this->assertFalse( current_user_can( 'manage_forum_attributes', $forum_id ) );

		$this->submit_forum_edit( $forum_id, 0 );

		$this->assertSame( $root_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_open( $forum_id ) );
		$this->assertFalse( bbp_is_forum_category( $forum_id ) );
		$this->assertTrue( bbp_is_forum_private( $forum_id, false ) );
	}

	/**
	 * @covers ::BBP_Forums_Group_Extension::map_group_forum_meta_caps
	 * @covers ::bbp_edit_forum_handler
	 * @covers ::bbp_save_forum_extras
	 */
	public function test_group_moderator_cannot_change_forum_parent_or_attributes() {
		$creator_id = $this->factory->user->create();
		$user_id    = $this->factory->user->create();
		$group_id   = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$root_id    = $this->factory->forum->create();
		$forum_id   = $this->factory->forum->create(
			array(
				'post_parent' => $root_id,
				'post_status' => bbp_get_private_status_id(),
			)
		);

		$member               = new BP_Groups_Member( $user_id, $group_id );
		$member->is_confirmed = 1;
		$member->is_mod       = 1;
		$member->save();
		$this->attach_forum_to_group( $forum_id, $group_id );
		$this->set_group_context( $group_id, $user_id );

		$this->assertTrue( bbp_group_is_mod() );
		$this->assertFalse( bbp_group_is_admin() );
		$this->assertFalse( current_user_can( 'manage_forum_attributes', $forum_id ) );

		$this->submit_forum_edit( $forum_id, 0 );

		$this->assertSame( $root_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_open( $forum_id ) );
		$this->assertFalse( bbp_is_forum_category( $forum_id ) );
		$this->assertTrue( bbp_is_forum_private( $forum_id, false ) );
	}
}
