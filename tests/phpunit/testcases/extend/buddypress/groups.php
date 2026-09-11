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
	protected $group_extension;

	public function setUp(): void {
		parent::setUp();

		$this->old_post    = $_POST;
		$this->old_request = $_REQUEST;
		$this->old_server  = $_SERVER;
		$this->old_errors  = bbpress()->errors;
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;

		if ( isset( $this->group_extension ) ) {
			remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ), 10 );
			remove_filter( 'bbp_map_meta_caps', array( $this->group_extension, 'map_group_forum_meta_caps' ), 99 );
		}

		unset( buddypress()->groups->current_group );
		buddypress()->current_component = '';
		buddypress()->current_item      = '';
		buddypress()->current_action    = '';

		parent::tearDown();
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
