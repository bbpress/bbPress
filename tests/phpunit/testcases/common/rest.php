<?php

/**
 * Tests for bbPress REST API integration.
 *
 * @group common
 * @group rest
 */
class BBP_Tests_Common_REST extends BBP_UnitTestCase {

	/**
	 * Set up REST support for bbPress post types.
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'bbp_register_forum_post_type', array( $this, 'enable_rest_support' ) );
		add_filter( 'bbp_register_topic_post_type', array( $this, 'enable_rest_support' ) );
		add_filter( 'bbp_register_reply_post_type', array( $this, 'enable_rest_support' ) );
		bbp_register_post_types();
	}

	/**
	 * Remove REST post type filters.
	 */
	public function tearDown(): void {
		remove_filter( 'bbp_register_forum_post_type', array( $this, 'enable_rest_support' ) );
		remove_filter( 'bbp_register_topic_post_type', array( $this, 'enable_rest_support' ) );
		remove_filter( 'bbp_register_reply_post_type', array( $this, 'enable_rest_support' ) );

		parent::tearDown();
	}

	/**
	 * Enable REST support for a bbPress post type.
	 *
	 * @param array $args Post type arguments.
	 * @return array Filtered post type arguments.
	 */
	public function enable_rest_support( $args ) {
		$args['show_in_rest'] = true;

		return $args;
	}

	/**
	 * Dispatch a REST request for a bbPress post.
	 *
	 * @param string $post_type Post type route base.
	 * @param int    $post_id   Post ID.
	 * @param int    $user_id   User ID.
	 * @return WP_REST_Response REST response.
	 */
	protected function get_item( $post_type, $post_id, $user_id = 0 ) {
		$this->set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/' . $post_type . '/' . $post_id );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Dispatch a REST collection request for a bbPress post type.
	 *
	 * @param string $post_type Post type route base.
	 * @param int    $user_id   User ID.
	 * @return WP_REST_Response REST response.
	 */
	protected function get_items( $post_type, $user_id = 0 ) {
		$this->set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/' . $post_type );
		$request->set_param( 'per_page', 100 );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Dispatch a REST update request for a bbPress post.
	 *
	 * @param string $post_type Post type route base.
	 * @param int    $post_id   Post ID.
	 * @param int    $user_id   User ID.
	 * @param array  $params    Request parameters.
	 * @return WP_REST_Response REST response.
	 */
	protected function update_item( $post_type, $post_id, $user_id, $params ) {
		$this->set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/' . $post_type . '/' . $post_id );
		$request->set_body_params( $params );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Create a forum, topic, and reply with the requested forum status.
	 *
	 * @param string $status    Forum status.
	 * @param int    $parent_id Parent forum ID.
	 * @return int[] Forum, topic, and reply IDs.
	 */
	protected function create_forum_content( $status = 'publish', $parent_id = 0 ) {
		$forum_id = $this->factory->forum->create(
			array(
				'post_parent' => $parent_id,
				'post_status' => $status,
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_content' => 'Restricted topic content.',
				'topic_meta'   => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => 'Restricted reply content.',
				'reply_meta'   => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		return compact( 'forum_id', 'topic_id', 'reply_id' );
	}

	/**
	 * Create an attachment for a bbPress post.
	 *
	 * @param int $parent_id Parent post ID.
	 * @return int Attachment ID.
	 */
	protected function create_attachment( $parent_id ) {
		return wp_insert_attachment(
			array(
				'post_title'     => 'Restricted attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $parent_id,
				'post_mime_type' => 'image/jpeg',
				'guid'           => 'https://example.org/restricted.jpg',
			)
		);
	}

	/**
	 * Assert all three bbPress REST item routes return a status.
	 *
	 * @param int   $status  Expected HTTP status.
	 * @param int[] $posts   Forum, topic, and reply IDs.
	 * @param int   $user_id User ID.
	 */
	protected function assert_item_status( $status, $posts, $user_id = 0 ) {
		$this->assertSame( $status, $this->get_item( bbp_get_forum_post_type(), $posts['forum_id'], $user_id )->get_status() );
		$this->assertSame( $status, $this->get_item( bbp_get_topic_post_type(), $posts['topic_id'], $user_id )->get_status() );
		$this->assertSame( $status, $this->get_item( bbp_get_reply_post_type(), $posts['reply_id'], $user_id )->get_status() );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_anonymous_user_can_read_public_forum_content() {
		$posts = $this->create_forum_content();

		$this->assert_item_status( 200, $posts );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_anonymous_user_cannot_read_private_forum_content() {
		$posts = $this->create_forum_content( bbp_get_private_status_id() );

		$this->assert_item_status( 401, $posts );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_anonymous_user_cannot_read_hidden_forum_content() {
		$posts = $this->create_forum_content( bbp_get_hidden_status_id() );

		$this->assert_item_status( 401, $posts );
	}

	/**
	 * @covers BBP_REST_Attachments_Controller::check_read_permission
	 */
	public function test_anonymous_user_cannot_read_attachment_in_hidden_forum() {
		$posts = $this->create_forum_content( bbp_get_hidden_status_id() );

		foreach ( $posts as $parent_id ) {
			$attachment_id = $this->create_attachment( $parent_id );

			$this->assertSame( 401, $this->get_item( 'media', $attachment_id )->get_status() );
		}
	}

	/**
	 * @covers BBP_REST_Attachments_Controller::check_read_permission
	 */
	public function test_anonymous_user_can_read_attachments_in_public_forum() {
		$posts = $this->create_forum_content();

		foreach ( $posts as $parent_id ) {
			$attachment_id = $this->create_attachment( $parent_id );

			$this->assertSame( 200, $this->get_item( 'media', $attachment_id )->get_status() );
		}
	}

	/**
	 * @covers BBP_REST_Attachments_Controller::check_read_permission
	 */
	public function test_anonymous_user_can_read_public_attachment_when_parent_post_type_is_not_in_rest() {
		$posts     = $this->create_forum_content();
		$post_type = get_post_type_object( bbp_get_topic_post_type() );
		$show_rest = $post_type->show_in_rest;

		$post_type->show_in_rest = false;

		try {
			$attachment_id = $this->create_attachment( $posts['topic_id'] );

			$this->assertSame( 200, $this->get_item( 'media', $attachment_id )->get_status() );
		} finally {
			$post_type->show_in_rest = $show_rest;
		}
	}

	/**
	 * @covers BBP_REST_Attachments_Controller::check_read_permission
	 */
	public function test_media_collection_excludes_attachments_in_hidden_forum() {
		$public     = $this->create_forum_content();
		$restricted = $this->create_forum_content( bbp_get_hidden_status_id() );
		$public_id  = $this->create_attachment( $public['topic_id'] );
		$hidden_id  = $this->create_attachment( $restricted['topic_id'] );
		$media      = $this->get_items( 'media' );
		$media_ids  = wp_list_pluck( $media->get_data(), 'id' );

		$this->assertSame( 200, $media->get_status() );
		$this->assertContains( $public_id, $media_ids );
		$this->assertNotContains( $hidden_id, $media_ids );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_anonymous_user_cannot_read_public_descendants_of_hidden_forum() {
		$parent_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$posts = $this->create_forum_content( bbp_get_public_status_id(), $parent_id );

		$this->assert_item_status( 401, $posts );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_participant_cannot_read_public_descendants_of_hidden_forum() {
		$parent_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$posts   = $this->create_forum_content( bbp_get_public_status_id(), $parent_id );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assert_item_status( 403, $posts, $user_id );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_participant_can_read_public_descendants_of_private_forum() {
		$parent_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_private_status_id(),
			)
		);
		$posts   = $this->create_forum_content( bbp_get_public_status_id(), $parent_id );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assert_item_status( 200, $posts, $user_id );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_participant_can_read_private_forum_content() {
		$posts   = $this->create_forum_content( bbp_get_private_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assert_item_status( 200, $posts, $user_id );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_participant_cannot_read_hidden_forum_content() {
		$posts   = $this->create_forum_content( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assert_item_status( 403, $posts, $user_id );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_keymaster_can_read_hidden_forum_content() {
		$posts   = $this->create_forum_content( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );

		$this->assert_item_status( 200, $posts, $user_id );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_forum_moderator_can_read_hidden_forum_content() {
		$posts   = $this->create_forum_content( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $posts['forum_id'], $user_id );

		$this->assert_item_status( 200, $posts, $user_id );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_filtered_forum_moderator_can_read_hidden_forum_content() {
		$posts   = $this->create_forum_content( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create();
		$filter  = function( $retval, $check_user_id, $forum_id ) use ( $posts, $user_id ) {
			return ( ( $user_id === $check_user_id ) && ( $posts['forum_id'] === $forum_id ) )
				? true
				: $retval;
		};

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		add_filter( 'bbp_is_user_forum_moderator', $filter, 10, 3 );

		try {
			$this->assert_item_status( 200, $posts, $user_id );
			wp_update_post(
				array(
					'ID'          => $posts['topic_id'],
					'post_status' => bbp_get_spam_status_id(),
				)
			);
			wp_update_post(
				array(
					'ID'          => $posts['reply_id'],
					'post_status' => bbp_get_spam_status_id(),
				)
			);
			$this->assertSame( 403, $this->get_item( bbp_get_topic_post_type(), $posts['topic_id'], $user_id )->get_status() );
			$this->assertSame( 403, $this->get_item( bbp_get_reply_post_type(), $posts['reply_id'], $user_id )->get_status() );
		} finally {
			remove_filter( 'bbp_is_user_forum_moderator', $filter, 10 );
		}
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_parent_forum_moderator_can_read_public_descendants_of_hidden_forum() {
		$parent_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$posts   = $this->create_forum_content( bbp_get_public_status_id(), $parent_id );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $parent_id, $user_id );

		$this->assert_item_status( 200, $posts, $user_id );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_collections_exclude_public_descendants_of_hidden_forum() {
		$public    = $this->create_forum_content();
		$parent_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$restricted = $this->create_forum_content( bbp_get_public_status_id(), $parent_id );
		$forums     = $this->get_items( bbp_get_forum_post_type() );
		$topics     = $this->get_items( bbp_get_topic_post_type() );
		$replies    = $this->get_items( bbp_get_reply_post_type() );
		$forum_ids  = wp_list_pluck( $forums->get_data(), 'id' );
		$topic_ids  = wp_list_pluck( $topics->get_data(), 'id' );
		$reply_ids  = wp_list_pluck( $replies->get_data(), 'id' );

		$this->assertSame( 200, $forums->get_status() );
		$this->assertSame( 200, $topics->get_status() );
		$this->assertSame( 200, $replies->get_status() );
		$this->assertSame( 1, (int) $forums->get_headers()['X-WP-Total'] );
		$this->assertSame( 1, (int) $topics->get_headers()['X-WP-Total'] );
		$this->assertSame( 1, (int) $replies->get_headers()['X-WP-Total'] );
		$this->assertContains( $public['forum_id'], $forum_ids );
		$this->assertContains( $public['topic_id'], $topic_ids );
		$this->assertContains( $public['reply_id'], $reply_ids );
		$this->assertNotContains( $restricted['forum_id'], $forum_ids );
		$this->assertNotContains( $restricted['topic_id'], $topic_ids );
		$this->assertNotContains( $restricted['reply_id'], $reply_ids );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_search_excludes_restricted_forum_content() {
		$posts = $this->create_forum_content( bbp_get_hidden_status_id() );
		wp_update_post(
			array(
				'ID'         => $posts['topic_id'],
				'post_title' => 'Unique restricted REST topic',
			)
		);

		$this->set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/search' );
		$request->set_param( 'search', 'Unique restricted REST topic' );
		$request->set_param( 'subtype', bbp_get_topic_post_type() );
		$response = rest_get_server()->dispatch( $request );
		$ids      = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotContains( $posts['topic_id'], $ids );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 * @dataProvider data_forum_attribute_fields
	 *
	 * @param string $field Field to update.
	 */
	public function test_per_forum_moderator_cannot_change_forum_structure_through_rest( $field ) {
		$old_parent_id = $this->factory->forum->create();
		$new_parent_id = $this->factory->forum->create();
		$forum_id      = $this->factory->forum->create(
			array(
				'post_parent' => $old_parent_id,
				'menu_order'  => 1,
			)
		);
		$user_id       = $this->factory->user->create();
		$values        = array(
			'parent'     => $new_parent_id,
			'status'     => bbp_get_private_status_id(),
			'menu_order' => 99,
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $user_id );

		$response = $this->update_item(
			bbp_get_forum_post_type(),
			$forum_id,
			$user_id,
			array( $field => $values[ $field ] )
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( $old_parent_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_public( $forum_id, false ) );
		$this->assertSame( 1, (int) get_post_field( 'menu_order', $forum_id ) );
	}

	/**
	 * Data provider for forum attribute fields exposed by REST.
	 *
	 * @return array[] Field names.
	 */
	public function data_forum_attribute_fields() {
		return array(
			array( 'parent' ),
			array( 'status' ),
			array( 'menu_order' ),
		);
	}

	/**
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_global_moderator_can_change_forum_structure_through_rest() {
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

		$response = $this->update_item(
			bbp_get_forum_post_type(),
			$forum_id,
			$user_id,
			array(
				'parent'     => $new_parent_id,
				'status'     => bbp_get_private_status_id(),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $new_parent_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_private( $forum_id, false ) );
	}
}
