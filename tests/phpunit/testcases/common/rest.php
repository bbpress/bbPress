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
	 * Dispatch a REST request.
	 *
	 * @param string $method  HTTP method.
	 * @param string $route   REST route.
	 * @param int    $user_id User ID.
	 * @param array  $params  Request parameters.
	 * @return WP_REST_Response REST response.
	 */
	protected function dispatch_request( $method, $route, $user_id = 0, $params = array() ) {
		$this->set_current_user( $user_id );

		$request = new WP_REST_Request( $method, $route );
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

	/**
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 * @covers ::bbp_map_topic_meta_caps
	 * @covers ::bbp_map_reply_meta_caps
	 */
	public function test_participant_cannot_access_rest_edit_surfaces_after_topic_moves_to_hidden_forum() {
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$public_forum   = $this->factory->forum->create();
		$hidden_forum   = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$topic_id      = $this->factory->topic->create(
			array(
				'post_author'  => $participant_id,
				'post_parent'  => $public_forum,
				'post_content' => 'Original topic content.',
				'topic_meta'   => array( 'forum_id' => $public_forum ),
			)
		);
		$reply_id      = $this->factory->reply->create(
			array(
				'post_author'  => $participant_id,
				'post_parent'  => $topic_id,
				'post_content' => 'Original reply content.',
				'reply_meta'   => array(
					'forum_id' => $public_forum,
					'topic_id' => $topic_id,
				),
			)
		);

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_keymaster_role() );
		$this->set_current_user( $participant_id );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );

		$this->set_current_user( $moderator_id );
		bbp_move_topic_handler( $topic_id, $public_forum, $hidden_forum );

		$posts = array(
			bbp_get_topic_post_type() => $topic_id,
			bbp_get_reply_post_type() => $reply_id,
		);

		foreach ( $posts as $post_type => $post_id ) {
			$this->set_current_user( $moderator_id );
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => 'Moderator hidden content.',
				)
			);
			$revision_ids = array_keys( wp_get_post_revisions( $post_id ) );
			$autosave_id  = wp_create_post_autosave(
				array(
					'post_ID'      => $post_id,
					'post_type'    => $post_type,
					'post_title'   => get_the_title( $post_id ),
					'content'      => 'Moderator unpublished autosave.',
				)
			);

			$this->assertNotEmpty( $revision_ids );
			$this->assertIsInt( $autosave_id );
			$this->set_current_user( $participant_id );
			$this->assertFalse( current_user_can( "edit_{$post_type}", $post_id ) );
			$this->assertSame( 403, $this->get_item( $post_type, $post_id, $participant_id )->get_status() );
			$this->assertSame( 403, $this->update_item( $post_type, $post_id, $participant_id, array() )->get_status() );
			$this->assertSame( 403, $this->update_item( $post_type, $post_id, $participant_id, array( 'content' => 'Participant overwrite.' ) )->get_status() );
			$this->assertSame( 'Moderator hidden content.', get_post_field( 'post_content', $post_id ) );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/revisions", $participant_id )->get_status() );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/revisions/{$revision_ids[0]}", $participant_id )->get_status() );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/autosaves", $participant_id )->get_status() );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/autosaves/{$autosave_id}", $participant_id )->get_status() );
			$this->assertSame( 403, $this->dispatch_request( 'POST', "/wp/v2/{$post_type}/{$post_id}/autosaves", $participant_id, array( 'content' => 'Participant autosave.' ) )->get_status() );

			$this->assertSame( 200, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/revisions", $moderator_id )->get_status() );
			$this->assertSame( 200, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/autosaves", $moderator_id )->get_status() );
		}
	}

	/**
	 * A participant cannot approve their own topic through REST.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_cannot_publish_pending_topic_through_rest() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'status' => bbp_get_public_status_id() ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'bbp_rest_cannot_change_status', $response->get_data()['code'] );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_id ) );
	}

	/**
	 * REST edits must honor the topic and reply edit lock.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_cannot_edit_topic_or_reply_after_lock_through_rest() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'   => $user_id,
				'post_parent'   => $forum_id,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
				'topic_meta'    => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author'   => $user_id,
				'post_parent'   => $topic_id,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
				'reply_meta'    => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		foreach ( array( bbp_get_topic_post_type() => $topic_id, bbp_get_reply_post_type() => $reply_id ) as $post_type => $post_id ) {
			$response = $this->update_item( $post_type, $post_id, $user_id, array( 'content' => 'Late edit.' ) );

			$this->assertSame( 403, $response->get_status() );
			$this->assertSame( 'bbp_rest_edit_lock', $response->get_data()['code'] );
			$this->assertNotSame( 'Late edit.', get_post_field( 'post_content', $post_id ) );
		}
	}

	/**
	 * REST edits must use the bbPress disallowed words check.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_cannot_add_disallowed_content_through_rest() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'  => $user_id,
				'post_parent'  => $forum_id,
				'post_content' => 'Original content.',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		update_option( 'disallowed_keys', 'blocked phrase' );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'content' => 'A blocked phrase.' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Original content.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * REST edits matching moderation keys must be held for review.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item
	 */
	public function test_participant_rest_edit_with_moderation_key_becomes_pending() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'  => $user_id,
				'post_parent'  => $forum_id,
				'post_content' => 'Original content.',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		update_option( 'moderation_keys', 'review phrase' );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'content' => 'A review phrase.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_id ) );
		$this->assertSame( 'A review phrase.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * Participants can still make ordinary REST edits within the edit window.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_can_edit_topic_within_lock_through_rest() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'  => $user_id,
				'post_parent'  => $forum_id,
				'post_content' => 'Original content.',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'content' => 'Updated content.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated content.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * A moderator can approve pending topics through REST.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_moderator_can_publish_pending_topic_through_rest() {
		$moderator_id = $this->factory->user->create();
		$forum_id     = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $moderator_id, array( 'status' => bbp_get_public_status_id() ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $topic_id ) );
	}

	/**
	 * REST object fields must be checked using the raw text WordPress saves.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_cannot_add_disallowed_rest_object_fields() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'post_title' => 'Original title', 'post_content' => 'Original content.', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		update_option( 'disallowed_keys', 'blocked phrase' );

		foreach ( array( 'title', 'content' ) as $field ) {
			$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( $field => array( 'raw' => 'A blocked phrase.' ) ) );

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( 'bbp_rest_disallowed_content', $response->get_data()['code'] );
		}

		$this->assertSame( 'Original title', get_post_field( 'post_title', $topic_id ) );
		$this->assertSame( 'Original content.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * A pending topic's zero GMT date must not lock out its recent author.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_can_edit_recent_pending_topic_through_rest() {
		update_option( 'timezone_string', 'America/Chicago' );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'post_status' => bbp_get_pending_status_id(), 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assertSame( '0000-00-00 00:00:00', get_post_field( 'post_date_gmt', $topic_id ) );
		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'content' => 'Pending revision.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Pending revision.', get_post_field( 'post_content', $topic_id ) );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_id ) );
	}

	/**
	 * An author cannot extend the REST edit window by moving a post's date.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_cannot_change_topic_date_through_rest() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$original = get_post_field( 'post_date_gmt', $topic_id );
		$future   = gmdate( 'Y-m-d\\TH:i:s', time() + DAY_IN_SECONDS );

		foreach ( array( 'date' => $future, 'date_gmt' => $future, 'date_gmt_null' => null ) as $field => $value ) {
			$param    = ( 'date_gmt_null' === $field ) ? 'date_gmt' : $field;
			$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( $param => $value ) );

			$this->assertSame( 403, $response->get_status() );
			$this->assertSame( 'bbp_rest_cannot_change_date', $response->get_data()['code'] );
			$this->assertSame( $original, get_post_field( 'post_date_gmt', $topic_id ) );
		}
	}

	/**
	 * Sending unchanged status and dates must not block an ordinary edit.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_can_roundtrip_unchanged_status_and_date() {
		update_option( 'timezone_string', 'America/Chicago' );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$post     = get_post( $topic_id );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'status' => bbp_get_public_status_id(), 'date' => mysql_to_rfc3339( $post->post_date ), 'date_gmt' => mysql_to_rfc3339( $post->post_date_gmt ), 'content' => 'Updated content.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated content.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * Raw REST object content must enter the reply moderation workflow.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item
	 */
	public function test_participant_rest_reply_object_content_becomes_pending() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id, 'post_content' => 'Original content.', 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		update_option( 'moderation_keys', 'review phrase' );

		$response = $this->update_item( bbp_get_reply_post_type(), $reply_id, $user_id, array( 'content' => array( 'raw' => 'A review phrase.' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $reply_id ) );
		$this->assertSame( 'A review phrase.', get_post_field( 'post_content', $reply_id ) );
	}

	/**
	 * A recent pending reply can be edited without changing its status.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_can_edit_recent_pending_reply_through_rest() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id, 'post_status' => bbp_get_pending_status_id(), 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assertSame( '0000-00-00 00:00:00', get_post_field( 'post_date_gmt', $reply_id ) );
		$response = $this->update_item( bbp_get_reply_post_type(), $reply_id, $user_id, array( 'content' => 'Pending reply revision.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Pending reply revision.', get_post_field( 'post_content', $reply_id ) );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $reply_id ) );
	}
}
