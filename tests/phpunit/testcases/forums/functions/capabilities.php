<?php

/**
 * Tests for forum capabilities.
 *
 * @group forums
 * @group functions
 * @group capabilities
 *
 * @ticket 3706
 */
class BBP_Tests_Forums_Functions_Capabilities extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_moderator_primitive_capabilities_map_to_moderate() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );

		foreach ( array( 'read_private_forums', 'read_hidden_forums', 'publish_forums', 'edit_forums', 'edit_others_forums' ) as $capability ) {
			$this->assertSame(
				array( 'moderate' ),
				bbp_map_forum_meta_caps( array( $capability ), $capability, $user_id )
			);
		}
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_participant_cannot_edit_forums_generally() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array( 'edit_forums' ), 'edit_forums', $user_id ) );
		$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array( 'edit_others_forums' ), 'edit_others_forums', $user_id ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_non_moderator_primitive_capabilities_keep_their_original_mapping() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		foreach ( array( 'read_private_forums', 'read_hidden_forums', 'publish_forums' ) as $capability ) {
			$this->assertSame(
				array( $capability ),
				bbp_map_forum_meta_caps( array( $capability ), $capability, $user_id )
			);
		}
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_manage_forum_attributes_requires_a_forum() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$post_id  = $this->factory->post->create();

		$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array(), 'manage_forum_attributes', $user_id ) );
		$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array(), 'manage_forum_attributes', $user_id, array( 999999 ) ) );
		$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array(), 'manage_forum_attributes', $user_id, array( $post_id ) ) );
		$this->assertSame( array( 'assign_moderators' ), bbp_map_forum_meta_caps( array(), 'manage_forum_attributes', $user_id, array( $forum_id ) ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_read_forum_maps_public_forums_for_anonymous_and_registered_users() {
		$forum_id = $this->factory->forum->create( array( 'post_author' => 0 ) );
		$user_id  = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->set_current_user( 0 );
		add_filter( 'bbp_allow_anonymous', '__return_true' );
		try {
			$this->assertSame( array( 'exist' ), bbp_map_forum_meta_caps( array(), 'read_forum', 0, array( $forum_id ) ) );
		} finally {
			remove_filter( 'bbp_allow_anonymous', '__return_true' );
		}

		$this->set_current_user( $user_id );
		$this->assertSame( array( 'spectate' ), bbp_map_forum_meta_caps( array(), 'read_forum', $user_id, array( $forum_id ) ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_read_forum_maps_restricted_and_unknown_statuses() {
		$user_id   = $this->factory->user->create();
		$author_id = $this->factory->user->create();
		$private   = $this->factory->forum->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_private_status_id() ) );
		$hidden    = $this->factory->forum->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_hidden_status_id() ) );
		$unknown   = $this->factory->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'draft',
				'post_type'   => bbp_get_forum_post_type(),
			)
		);
		$post_type = get_post_type_object( bbp_get_forum_post_type() );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assertSame( array( $post_type->cap->read_private_posts ), bbp_map_forum_meta_caps( array(), 'read_forum', $user_id, array( $private ) ) );
		$this->assertSame( array( $post_type->cap->read_hidden_posts ), bbp_map_forum_meta_caps( array(), 'read_forum', $user_id, array( $hidden ) ) );
		$this->assertSame( array( $post_type->cap->read_private_posts ), bbp_map_forum_meta_caps( array(), 'read_forum', $user_id, array( $unknown ) ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_read_forum_allows_authors_and_moderators_to_read_restricted_forums() {
		$author_id = $this->factory->user->create();
		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		$forum_id  = $this->factory->forum->create(
			array(
				'post_author' => $author_id,
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$mod_id    = $this->factory->user->create();
		bbp_set_user_role( $mod_id, bbp_get_moderator_role() );

		$this->assertSame( array( 'spectate' ), bbp_map_forum_meta_caps( array(), 'read_forum', $author_id, array( $forum_id ) ) );
		$this->assertSame( array( 'spectate' ), bbp_map_forum_meta_caps( array(), 'read_forum', $mod_id, array( $forum_id ) ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_read_without_spectate_filter_reaches_post_mapping() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create( array( 'post_author' => 0 ) );
		$received = null;
		$filter   = static function( $allowed, $check_user_id, $check_forum_id, $capability ) use ( &$received ) {
			$received = compact( 'allowed', 'check_user_id', 'check_forum_id', 'capability' );

			return true;
		};

		bbp_remove_user_role( $user_id );
		add_filter( 'bbp_allow_read_without_spectate', $filter, 10, 4 );
		try {
			$this->assertSame( array( 'spectate' ), bbp_map_forum_meta_caps( array(), 'read_forum', $user_id, array( $forum_id ) ) );
			$this->assertFalse( user_can( $user_id, 'read_forum', $forum_id ) );
		} finally {
			remove_filter( 'bbp_allow_read_without_spectate', $filter, 10 );
		}

		$this->assertSame(
			array(
				'allowed'        => false,
				'check_user_id'  => $user_id,
				'check_forum_id' => $forum_id,
				'capability'     => 'read_forum',
			),
			$received
		);
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_edit_forum_maps_author_and_other_user_capabilities() {
		$author_id = $this->factory->user->create();
		$other_id  = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create( array( 'post_author' => $author_id ) );
		$post_type = get_post_type_object( bbp_get_forum_post_type() );
		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		bbp_set_user_role( $other_id, bbp_get_participant_role() );

		$this->assertSame( array( $post_type->cap->edit_posts ), bbp_map_forum_meta_caps( array(), 'edit_forum', $author_id, array( $forum_id ) ) );
		$this->assertSame( array( $post_type->cap->edit_others_posts ), bbp_map_forum_meta_caps( array(), 'edit_forum', $other_id, array( $forum_id ) ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_edit_forum_maps_frozen_and_inactive_users() {
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$forum_id       = $this->factory->forum->create( array( 'post_author' => $participant_id ) );
		$auto_draft_id  = $this->factory->post->create(
			array(
				'post_author' => $participant_id,
				'post_status' => 'auto-draft',
				'post_type'   => bbp_get_forum_post_type(),
			)
		);
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_forums_status', 'frozen' );

		$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array(), 'edit_forum', $participant_id, array( $auto_draft_id ) ) );
		$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array(), 'edit_forum', $participant_id, array( $forum_id ) ) );
		$this->assertSame( array( 'spectate' ), bbp_map_forum_meta_caps( array(), 'edit_forum', $moderator_id, array( $forum_id ) ) );

		update_option( '_bbp_forums_status', 'open' );
		add_filter( 'bbp_core_is_user_spammer', '__return_true' );
		try {
			$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array(), 'edit_forum', $participant_id, array( $forum_id ) ) );
		} finally {
			remove_filter( 'bbp_core_is_user_spammer', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_edit_forum_maps_authors_to_edit_others_in_admin() {
		$author_id = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create( array( 'post_author' => $author_id ) );
		$post_type = get_post_type_object( bbp_get_forum_post_type() );
		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		set_current_screen( 'edit-forum' );

		try {
			$this->assertSame( array( $post_type->cap->edit_others_posts ), bbp_map_forum_meta_caps( array(), 'edit_forum', $author_id, array( $forum_id ) ) );
		} finally {
			set_current_screen( 'front' );
		}
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_delete_forum_maps_author_other_and_inactive_users() {
		$author_id   = $this->factory->user->create();
		$other_id    = $this->factory->user->create();
		$forum_id    = $this->factory->forum->create( array( 'post_author' => $author_id ) );
		$post_type   = get_post_type_object( bbp_get_forum_post_type() );
		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		bbp_set_user_role( $other_id, bbp_get_participant_role() );

		$this->assertSame( array( $post_type->cap->delete_posts ), bbp_map_forum_meta_caps( array(), 'delete_forum', $author_id, array( $forum_id ) ) );
		$this->assertSame( array( $post_type->cap->delete_others_posts ), bbp_map_forum_meta_caps( array(), 'delete_forum', $other_id, array( $forum_id ) ) );

		add_filter( 'bbp_core_is_user_spammer', '__return_true' );
		try {
			$this->assertSame( array( 'do_not_allow' ), bbp_map_forum_meta_caps( array(), 'delete_forum', $author_id, array( $forum_id ) ) );
		} finally {
			remove_filter( 'bbp_core_is_user_spammer', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_forum_admin_maps_to_edit_forums() {
		$user_id = $this->factory->user->create();

		$this->assertSame( array( 'edit_forums' ), bbp_map_forum_meta_caps( array( 'original' ), 'bbp_forums_admin', $user_id ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_forum_meta_cap_filter_receives_mapped_values() {
		$user_id  = $this->factory->user->create();
		$received = null;
		$filter   = static function( $caps, $capability, $check_user_id, $args ) use ( &$received ) {
			$received = compact( 'caps', 'capability', 'check_user_id', 'args' );

			return array( 'custom_forum_capability' );
		};

		add_filter( 'bbp_map_forum_meta_caps', $filter, 10, 4 );
		try {
			$this->assertSame(
				array( 'custom_forum_capability' ),
				bbp_map_forum_meta_caps( array( 'original' ), 'bbp_forums_admin', $user_id, array( 123 ) )
			);
		} finally {
			remove_filter( 'bbp_map_forum_meta_caps', $filter, 10 );
		}

		$this->assertSame(
			array(
				'caps'          => array( 'edit_forums' ),
				'capability'    => 'bbp_forums_admin',
				'check_user_id' => $user_id,
				'args'          => array( 123 ),
			),
			$received
		);
	}

	/**
	 * @covers ::bbp_is_user_forum_moderator
	 */
	public function test_is_user_forum_moderator_normalizes_values_and_applies_filter() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$received = null;
		$filter   = static function( $is_moderator, $check_user_id, $check_forum_id ) use ( &$received ) {
			$received = compact( 'is_moderator', 'check_user_id', 'check_forum_id' );

			return true;
		};
		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assertFalse( bbp_is_user_forum_moderator( $user_id, $forum_id ) );
		bbp_add_moderator( $forum_id, $user_id );
		$this->assertTrue( bbp_is_user_forum_moderator( $user_id, $forum_id ) );
		bbp_remove_moderator( $forum_id, $user_id );

		add_filter( 'bbp_is_user_forum_moderator', $filter, 10, 3 );
		try {
			$this->assertTrue( bbp_is_user_forum_moderator( (string) $user_id, (string) $forum_id ) );
		} finally {
			remove_filter( 'bbp_is_user_forum_moderator', $filter, 10 );
		}

		$this->assertSame(
			array(
				'is_moderator'  => false,
				'check_user_id' => $user_id,
				'check_forum_id' => $forum_id,
			),
			$received
		);
	}

	/**
	 * @covers ::bbp_allow_forums_of_user
	 */
	public function test_allow_forums_of_user_respects_setting_and_access() {
		$user_id       = $this->factory->user->create();
		$author_id     = $this->factory->user->create();
		$moderated_id  = $this->factory->forum->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_hidden_status_id() ) );
		$restricted_id = $this->factory->forum->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_hidden_status_id() ) );
		$readable_id   = $this->factory->forum->create( array( 'post_author' => $user_id, 'post_status' => bbp_get_hidden_status_id() ) );
		$forum_ids     = array( $moderated_id, $restricted_id, $readable_id );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $moderated_id, $user_id );

		update_option( '_bbp_allow_forum_mods', false );
		$this->assertSame( $forum_ids, bbp_allow_forums_of_user( $forum_ids, $user_id ) );

		update_option( '_bbp_allow_forum_mods', true );
		$this->assertSame( array( 1 => $restricted_id ), bbp_allow_forums_of_user( $forum_ids, $user_id ) );
	}

	/**
	 * @covers ::bbp_allow_forums_of_user
	 */
	public function test_allow_forums_of_user_filter_receives_original_ids() {
		$user_id       = $this->factory->user->create();
		$author_id     = $this->factory->user->create();
		$moderated_id  = $this->factory->forum->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_hidden_status_id() ) );
		$restricted_id = $this->factory->forum->create( array( 'post_author' => $author_id, 'post_status' => bbp_get_hidden_status_id() ) );
		$forum_ids     = array( $moderated_id, $restricted_id );
		$received      = null;
		$filter   = static function( $allowed_forum_ids, $check_user_id, $original_forum_ids ) use ( &$received ) {
			$received = compact( 'allowed_forum_ids', 'check_user_id', 'original_forum_ids' );

			return array( 56 );
		};

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $moderated_id, $user_id );
		update_option( '_bbp_allow_forum_mods', true );
		add_filter( 'bbp_allow_forums_of_user', $filter, 10, 3 );
		try {
			$this->assertSame( array( 56 ), bbp_allow_forums_of_user( $forum_ids, $user_id ) );
		} finally {
			remove_filter( 'bbp_allow_forums_of_user', $filter, 10 );
		}

		$this->assertSame(
			array(
				'allowed_forum_ids'  => array( 1 => $restricted_id ),
				'check_user_id'      => $user_id,
				'original_forum_ids' => $forum_ids,
			),
			$received
		);
	}
}
