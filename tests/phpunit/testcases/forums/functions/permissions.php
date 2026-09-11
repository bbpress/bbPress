<?php

/**
 * Tests for forum handler permissions.
 *
 * @group forums
 * @group functions
 * @group capabilities
 */
class BBP_Tests_Forums_Functions_Permissions extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_request;
	protected $old_server;
	protected $old_errors;

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

		parent::tearDown();
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
	 * @covers ::bbp_save_forum_extras
	 */
	public function test_per_forum_moderator_cannot_change_forum_attributes() {
		$forum_id = $this->factory->forum->create(
			array( 'post_status' => bbp_get_private_status_id() )
		);
		$user_id  = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $user_id );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'edit_forum', $forum_id ) );
		$this->assertFalse( current_user_can( 'manage_forum_attributes', $forum_id ) );

		$_POST = array(
			'bbp_forum_status'     => 'closed',
			'bbp_forum_type'       => 'category',
			'bbp_forum_visibility' => bbp_get_public_status_id(),
		);

		bbp_save_forum_extras( $forum_id );

		$this->assertTrue( bbp_is_forum_open( $forum_id ) );
		$this->assertFalse( bbp_is_forum_category( $forum_id ) );
		$this->assertTrue( bbp_is_forum_private( $forum_id, false ) );
	}

	/**
	 * @covers ::bbp_edit_forum_handler
	 * @covers ::bbp_save_forum_extras
	 */
	public function test_per_forum_moderator_cannot_change_forum_parent_or_attributes_theme_side() {
		$parent_id = $this->factory->forum->create(
			array( 'post_status' => bbp_get_private_status_id() )
		);
		$forum_id  = $this->factory->forum->create(
			array(
				'post_parent' => $parent_id,
				'post_status' => bbp_get_private_status_id(),
			)
		);
		$user_id   = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $user_id );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->submit_forum_edit( $forum_id, 0 );

		$this->assertSame( $parent_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_open( $forum_id ) );
		$this->assertFalse( bbp_is_forum_category( $forum_id ) );
		$this->assertTrue( bbp_is_forum_private( $forum_id, false ) );
	}

	/**
	 * @covers ::bbp_save_forum_extras
	 */
	public function test_per_forum_moderator_cannot_remove_forum_moderators() {
		$forum_id = $this->factory->forum->create();
		$user_id  = $this->factory->user->create();
		$other_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $user_id );
		bbp_add_moderator( $forum_id, $other_id );
		$this->set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'assign_moderators' ) );

		$_POST = array( 'bbp_moderators' => '' );

		bbp_save_forum_extras( $forum_id );

		$this->assertEqualSets( array( $user_id, $other_id ), bbp_get_moderator_ids( $forum_id ) );
	}

	/**
	 * @covers ::bbp_save_forum_extras
	 */
	public function test_global_moderator_can_change_forum_attributes_and_moderators() {
		$forum_id = $this->factory->forum->create();
		$user_id  = $this->factory->user->create();
		$other_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		bbp_add_moderator( $forum_id, $other_id );
		$this->set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'manage_forum_attributes' ) );
		$this->assertTrue( current_user_can( 'manage_forum_attributes', $forum_id ) );
		$this->assertTrue( current_user_can( 'assign_moderators' ) );

		$_POST = array(
			'bbp_forum_status'     => 'closed',
			'bbp_forum_type'       => 'category',
			'bbp_forum_visibility' => bbp_get_hidden_status_id(),
			'bbp_moderators'       => '',
		);

		bbp_save_forum_extras( $forum_id );

		$this->assertTrue( bbp_is_forum_closed( $forum_id ) );
		$this->assertTrue( bbp_is_forum_category( $forum_id ) );
		$this->assertTrue( bbp_is_forum_hidden( $forum_id, false ) );
		$this->assertEmpty( bbp_get_moderator_ids( $forum_id ) );
	}

	/**
	 * @covers ::bbp_filter_admin_forum_post_data
	 */
	public function test_per_forum_moderator_cannot_change_forum_structure_through_wp_admin() {
		$old_parent_id = $this->factory->forum->create();
		$new_parent_id = $this->factory->forum->create();
		$forum_id      = $this->factory->forum->create(
			array(
				'post_parent' => $old_parent_id,
				'menu_order'  => 1,
			)
		);
		$user_id       = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $user_id );
		$this->set_current_user( $user_id );
		set_current_screen( 'post.php' );

		wp_update_post(
			array(
				'ID'          => $forum_id,
				'post_parent' => $new_parent_id,
				'post_status' => bbp_get_private_status_id(),
				'menu_order'  => 99,
			)
		);

		set_current_screen( 'front' );

		$this->assertSame( $old_parent_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_public( $forum_id, false ) );
		$this->assertSame( 1, (int) get_post_field( 'menu_order', $forum_id ) );
	}

	/**
	 * @covers ::bbp_filter_admin_forum_post_data
	 */
	public function test_global_moderator_can_change_forum_structure_through_wp_admin() {
		$old_parent_id = $this->factory->forum->create();
		$new_parent_id = $this->factory->forum->create();
		$forum_id      = $this->factory->forum->create(
			array(
				'post_parent' => $old_parent_id,
				'menu_order'  => 1,
			)
		);
		$user_id       = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		set_current_screen( 'post.php' );

		wp_update_post(
			array(
				'ID'          => $forum_id,
				'post_parent' => $new_parent_id,
				'post_status' => bbp_get_private_status_id(),
				'menu_order'  => 99,
			)
		);

		set_current_screen( 'front' );

		$this->assertSame( $new_parent_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_private( $forum_id, false ) );
		$this->assertSame( 99, (int) get_post_field( 'menu_order', $forum_id ) );
	}
}
