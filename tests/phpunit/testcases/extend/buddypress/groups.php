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
	protected $old_request;
	protected $old_server;
	protected $old_errors;
	protected $old_allow_forum_mods;
	protected $group_extension;

	public function setUp(): void {
		parent::setUp();

		$this->old_post    = $_POST;
		$this->old_request = $_REQUEST;
		$this->old_server  = $_SERVER;
		$this->old_errors  = bbpress()->errors;
		$this->old_allow_forum_mods = get_option( '_bbp_allow_forum_mods', null );
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;

		if ( null === $this->old_allow_forum_mods ) {
			delete_option( '_bbp_allow_forum_mods' );
		} else {
			update_option( '_bbp_allow_forum_mods', $this->old_allow_forum_mods );
		}

		if ( isset( $this->group_extension ) ) {
			remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ), 10 );
			remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ), 99 );
			remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_read_meta_caps' ), 20 );
			remove_filter( 'bbp_get_excluded_forum_ids', array( $this->group_extension, 'exclude_group_forum_ids' ), 20 );
			remove_filter( 'bbp_subscription_user_can_view_forum', array( $this->group_extension, 'subscription_user_can_view_forum' ), 10 );
		}

		unset( buddypress()->groups->current_group );
		buddypress()->current_component = '';
		buddypress()->current_item      = '';
		buddypress()->current_action    = '';

		parent::tearDown();
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
		buddypress()->loggedin_user->id  = $user_id;
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

	protected function attach_forum_to_group( $forum_id, $group_id ) {
		bbp_add_forum_id_to_group( $group_id, $forum_id );
		bbp_add_group_id_to_forum( $forum_id, $group_id );
	}

	protected function submit_forum_edit( $forum_id, $parent_id ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI'] = $home_url['path'];
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
