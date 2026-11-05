<?php

// Simulate Gutenberg's media controllers when the plugin is not loaded.
if ( ! class_exists( 'Gutenberg_REST_Attachments_Controller', false ) ) {
	class Gutenberg_REST_Attachments_Controller extends WP_REST_Attachments_Controller {}
}

if ( ! class_exists( 'Gutenberg_REST_Attachments_Controller_7_1', false ) ) {
	class Gutenberg_REST_Attachments_Controller_7_1 extends WP_REST_Attachments_Controller {}
}

/**
 * Tests for bbPress REST API integration.
 *
 * @group common
 * @group rest
 */
class BBP_Tests_Common_REST extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_register_rest_attachment_controller
	 */
	public function test_register_rest_attachment_controller_replaces_only_core_controller() {
		$post_type = get_post_type_object( 'attachment' );
		$original  = $post_type->rest_controller_class;

		try {
			$post_type->rest_controller_class = 'WP_REST_Attachments_Controller';
			bbp_register_rest_attachment_controller();
			$this->assertSame( 'BBP_REST_Attachments_Controller', $post_type->rest_controller_class );

			$post_type->rest_controller_class = 'Custom_REST_Attachments_Controller';
			bbp_register_rest_attachment_controller();
			$this->assertSame( 'Custom_REST_Attachments_Controller', $post_type->rest_controller_class );
		} finally {
			$post_type->rest_controller_class = $original;
		}
	}

	/**
	 * Gutenberg's controller must retain its media features and bbPress checks.
	 *
	 * @covers ::bbp_register_rest_attachment_controller
	 */
	public function test_register_rest_attachment_controller_wraps_gutenberg_controllers() {
		$post_type = get_post_type_object( 'attachment' );
		$original  = $post_type->rest_controller_class;

		try {
			foreach ( array( 'Gutenberg_REST_Attachments_Controller', 'Gutenberg_REST_Attachments_Controller_7_1' ) as $gutenberg_class ) {
				$post_type->rest_controller_class = $gutenberg_class;
				bbp_register_rest_attachment_controller();

				$this->assertNotSame( $gutenberg_class, $post_type->rest_controller_class );
				$this->assertTrue( is_subclass_of( $post_type->rest_controller_class, $gutenberg_class ) );
			}
		} finally {
			$post_type->rest_controller_class = $original;
		}
	}

	/**
	 * Gutenberg media routes must inherit forum visibility for reads.
	 *
	 * @covers ::bbp_register_rest_attachment_controller
	 */
	public function test_gutenberg_media_routes_respect_forum_visibility() {
		$post_type           = get_post_type_object( 'attachment' );
		$original            = $post_type->rest_controller_class;
		$original_controller = $post_type->rest_controller;
		$original_server     = isset( $GLOBALS['wp_rest_server'] ) ? $GLOBALS['wp_rest_server'] : null;
		$public              = $this->create_forum_content();
		$private             = $this->create_forum_content( bbp_get_private_status_id() );
		$hidden              = $this->create_forum_content( bbp_get_hidden_status_id() );
		$public_id           = $this->create_attachment( $public['topic_id'] );
		$private_id          = $this->create_attachment( $private['topic_id'] );
		$hidden_id           = $this->create_attachment( $hidden['topic_id'] );
		$post_id             = $this->factory->post->create();
		$normal_id           = $this->create_attachment( $post_id );
		$participant_id      = $this->factory->user->create();
		$moderator_id        = $this->factory->user->create();
		$keymaster_id        = $this->factory->user->create();

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		try {
			foreach ( array(
				'Gutenberg_REST_Attachments_Controller'     => 'BBP_REST_Gutenberg_Attachments_Controller',
				'Gutenberg_REST_Attachments_Controller_7_1' => 'BBP_REST_Gutenberg_Attachments_Controller_7_1',
			) as $gutenberg_class => $bridge_class ) {
				$post_type->rest_controller_class = $gutenberg_class;
				$post_type->rest_controller       = null;
				$GLOBALS['wp_rest_server']       = null;
				$server                           = rest_get_server();
				$routes                           = $server->get_routes();

				$this->assertSame( $bridge_class, $post_type->rest_controller_class );
				$this->assertInstanceOf( $bridge_class, $post_type->get_rest_controller() );
				$this->assertInstanceOf( $bridge_class, $routes['/wp/v2/media'][0]['callback'][0] );
				$this->assertInstanceOf( $bridge_class, $routes['/wp/v2/media/(?P<id>[\\d]+)'][0]['callback'][0] );

				$this->set_current_user( $participant_id );
				$this->assertFalse( bbp_user_can_view_attachment( $hidden_id ) );

				$this->assertSame( 200, $this->get_item( 'media', $public_id )->get_status() );
				$this->assertSame( 200, $this->get_item( 'media', $normal_id )->get_status() );
				$this->assertSame( 200, $this->get_item( 'media', $private_id, $participant_id )->get_status() );
				$this->assertSame( 401, $this->get_item( 'media', $hidden_id )->get_status() );
				$this->assertSame( 403, $this->get_item( 'media', $hidden_id, $participant_id )->get_status() );
				$this->assertSame( 200, $this->get_item( 'media', $hidden_id, $moderator_id )->get_status() );
				$this->assertSame( 200, $this->get_item( 'media', $hidden_id, $keymaster_id )->get_status() );

				$media_ids = wp_list_pluck( $this->get_items( 'media', $participant_id )->get_data(), 'id' );
				$this->assertContains( $public_id, $media_ids );
				$this->assertContains( $private_id, $media_ids );
				$this->assertContains( $normal_id, $media_ids );
				$this->assertNotContains( $hidden_id, $media_ids );
			}
		} finally {
			$post_type->rest_controller_class = $original;
			$post_type->rest_controller       = $original_controller;
			$GLOBALS['wp_rest_server']       = $original_server;
		}
	}

	/**
	 * Gutenberg media writes must retain bbPress parent checks.
	 *
	 * @covers ::bbp_register_rest_attachment_controller
	 */
	public function test_gutenberg_media_writes_require_bbpress_parent_access() {
		$post_type = get_post_type_object( 'attachment' );
		$original  = $post_type->rest_controller_class;
		$posts     = $this->create_forum_content();
		$user_id   = $this->factory->user->create( array( 'role' => 'editor' ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );
		$attachment_id = $this->create_attachment( $posts['topic_id'] );

		try {
			$post_type->rest_controller_class = 'Gutenberg_REST_Attachments_Controller';
			bbp_register_rest_attachment_controller();
			$controller = new $post_type->rest_controller_class( 'attachment' );
			$request    = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
			$request->set_param( 'id', $attachment_id );

			$this->assertTrue( $controller->update_item_permissions_check( $request ) );
			$this->assertTrue( $controller->delete_item_permissions_check( $request ) );

			wp_update_post( array( 'ID' => $posts['forum_id'], 'post_status' => bbp_get_hidden_status_id() ) );

			foreach ( array( 'update_item_permissions_check', 'delete_item_permissions_check', 'post_process_item_permissions_check', 'edit_media_item_permissions_check' ) as $method ) {
				$permission = $controller->$method( $request );
				$this->assertWPError( $permission );
				$this->assertSame( 403, $permission->get_error_data()['status'] );
			}

			if ( method_exists( $controller, 'sideload_item_permissions_check' ) ) {
				$permission = $controller->sideload_item_permissions_check( $request );
				$this->assertWPError( $permission );
				$this->assertSame( 403, $permission->get_error_data()['status'] );
			}
		} finally {
			$post_type->rest_controller_class = $original;
		}
	}

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
	 * Public forum content inherits a password from its forum ancestors.
	 *
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 * @covers BBP_REST_Attachments_Controller::check_read_permission
	 */
	public function test_rest_requires_ancestor_forum_password() {
		$parent_id = $this->factory->forum->create(
			array(
				'post_password' => 'parent-secret',
			)
		);
		$posts     = $this->create_forum_content( bbp_get_public_status_id(), $parent_id );
		$media_ids = array();

		foreach ( $posts as $post_id ) {
			$media_ids[] = $this->create_attachment( $post_id );
		}

		$this->assert_item_status( 401, $posts );
		foreach ( $media_ids as $media_id ) {
			$this->assertSame( 401, $this->get_item( 'media', $media_id )->get_status() );
		}

		$this->assertNotContains( $posts['forum_id'], wp_list_pluck( $this->get_items( bbp_get_forum_post_type() )->get_data(), 'id' ) );
		$this->assertNotContains( $posts['topic_id'], wp_list_pluck( $this->get_items( bbp_get_topic_post_type() )->get_data(), 'id' ) );
		$this->assertNotContains( $posts['reply_id'], wp_list_pluck( $this->get_items( bbp_get_reply_post_type() )->get_data(), 'id' ) );
		$this->assertEmpty( array_intersect( $media_ids, wp_list_pluck( $this->get_items( 'media' )->get_data(), 'id' ) ) );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher     = new PasswordHash( 8, true );
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'parent-secret' );

		try {
			$this->assert_item_status( 200, $posts );
			foreach ( $media_ids as $media_id ) {
				$this->assertSame( 200, $this->get_item( 'media', $media_id )->get_status() );
			}

			$this->assertContains( $posts['forum_id'], wp_list_pluck( $this->get_items( bbp_get_forum_post_type() )->get_data(), 'id' ) );
			$this->assertContains( $posts['topic_id'], wp_list_pluck( $this->get_items( bbp_get_topic_post_type() )->get_data(), 'id' ) );
			$this->assertContains( $posts['reply_id'], wp_list_pluck( $this->get_items( bbp_get_reply_post_type() )->get_data(), 'id' ) );
			$this->assertEmpty( array_diff( $media_ids, wp_list_pluck( $this->get_items( 'media' )->get_data(), 'id' ) ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * WordPress retains responsibility for a requested object's own password.
	 *
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_rest_preserves_core_handling_for_object_password() {
		$posts = $this->create_forum_content();
		wp_update_post(
			array(
				'ID'            => $posts['topic_id'],
				'post_password' => 'topic-secret',
			)
		);

		$response = $this->get_item( bbp_get_topic_post_type(), $posts['topic_id'] );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['content']['protected'] );
		$this->assertSame( '', $data['content']['rendered'] );
	}

	/**
	 * Author edit APIs must recheck a password added after the post was created.
	 *
	 * @covers BBP_REST_Posts_Controller::check_update_permission
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 * @covers ::bbp_filter_rest_password_required_revisions
	 */
	public function test_author_edit_surfaces_require_new_ancestor_password() {
		$author_id    = $this->factory->user->create();
		$moderator_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$parent_id    = $this->factory->forum->create();
		$forum_id     = $this->factory->forum->create( array( 'post_parent' => $parent_id ) );
		$topic_id     = $this->factory->topic->create(
			array(
				'post_author'  => $author_id,
				'post_parent'  => $forum_id,
				'post_content' => 'Original topic content.',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id     = $this->factory->reply->create(
			array(
				'post_author'  => $author_id,
				'post_parent'  => $topic_id,
				'post_content' => 'Original reply content.',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_keymaster_role() );
		$this->set_current_user( $moderator_id );

		$posts = array(
			bbp_get_topic_post_type() => $topic_id,
			bbp_get_reply_post_type() => $reply_id,
		);
		$revisions = array();
		$autosaves = array();

		foreach ( $posts as $post_type => $post_id ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => 'Updated content.' ) );
			$revisions[ $post_type ] = key( wp_get_post_revisions( $post_id ) );
			$autosaves[ $post_type ] = wp_create_post_autosave(
				array( 'post_ID' => $post_id, 'post_type' => $post_type, 'post_title' => get_the_title( $post_id ), 'content' => 'Moderator autosave.' )
			);
			$this->assertNotEmpty( $revisions[ $post_type ] );
			$this->assertIsInt( $autosaves[ $post_type ] );
		}

		$password = wp_generate_password( 24, false );
		wp_update_post( array( 'ID' => $parent_id, 'post_password' => $password ) );

		foreach ( $posts as $post_type => $post_id ) {
			$this->set_current_user( $author_id );
			$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}", $author_id, array( 'context' => 'edit' ) )->get_status() );
			$collection = $this->dispatch_request( 'GET', "/wp/v2/{$post_type}", $author_id, array( 'context' => 'edit' ) );
			$this->assertSame( 200, $collection->get_status() );
			$this->assertNotContains( $post_id, wp_list_pluck( $collection->get_data(), 'id' ) );
			$this->assertSame( 403, $this->update_item( $post_type, $post_id, $author_id, array( 'content' => 'Blocked overwrite.' ) )->get_status() );
			$this->assertSame( 'Updated content.', get_post_field( 'post_content', $post_id ) );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/revisions", $author_id )->get_status() );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/revisions/{$revisions[ $post_type ]}", $author_id )->get_status() );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/autosaves", $author_id )->get_status() );
			$this->assertSame( 403, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/autosaves/{$autosaves[ $post_type ]}", $author_id )->get_status() );
			$this->assertSame( 403, $this->dispatch_request( 'POST', "/wp/v2/{$post_type}/{$post_id}/autosaves", $author_id, array( 'content' => 'Blocked autosave.' ) )->get_status() );
		}

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$hasher     = new PasswordHash( 8, true );
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'incorrect-password' );
		$this->assertSame( 403, $this->update_item( bbp_get_topic_post_type(), $topic_id, $author_id, array( 'content' => 'Blocked overwrite.' ) )->get_status() );
		$_COOKIE[ $cookie ] = $hasher->HashPassword( $password );

		try {
			foreach ( $posts as $post_type => $post_id ) {
				$this->assertSame( 200, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}", $author_id, array( 'context' => 'edit' ) )->get_status() );
				$collection = $this->dispatch_request( 'GET', "/wp/v2/{$post_type}", $author_id, array( 'context' => 'edit' ) );
				$this->assertSame( 200, $collection->get_status() );
				$this->assertContains( $post_id, wp_list_pluck( $collection->get_data(), 'id' ) );
				$this->assertSame( 200, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/revisions", $author_id )->get_status() );
				$this->assertSame( 200, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/autosaves", $author_id )->get_status() );
				$this->assertSame( 200, $this->update_item( $post_type, $post_id, $author_id, array( 'content' => 'Authorized edit.' ) )->get_status() );
			}
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}

		$this->assertSame( 200, $this->dispatch_request( 'GET', "/wp/v2/{$post_type}/{$post_id}/revisions", $moderator_id )->get_status() );
	}

	/**
	 * A granted edit-others capability does not bypass the forum password.
	 *
	 * @covers BBP_REST_Posts_Controller::check_update_permission
	 */
	public function test_other_editor_requires_new_forum_password() {
		$editor_id = $this->factory->user->create();
		$author_id = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create(
			array( 'post_author' => $author_id, 'post_parent' => $forum_id, 'post_content' => 'Original content.', 'topic_meta' => array( 'forum_id' => $forum_id ) )
		);

		bbp_set_user_role( $editor_id, bbp_get_participant_role() );
		get_userdata( $editor_id )->add_cap( 'edit_others_topics' );
		$this->set_current_user( $editor_id );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertSame( 200, $this->update_item( bbp_get_topic_post_type(), $topic_id, $editor_id, array( 'content' => 'Public edit.' ) )->get_status() );

		wp_update_post( array( 'ID' => $forum_id, 'post_password' => wp_generate_password( 24, false ) ) );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertSame( 403, $this->update_item( bbp_get_topic_post_type(), $topic_id, $editor_id, array( 'content' => 'Blocked edit.' ) )->get_status() );
		$this->assertSame( 'Public edit.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * Published replies inherit the read permission of their parent topic.
	 *
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_rest_hides_published_replies_in_unapproved_topics() {
		$public    = $this->create_forum_content();
		$unapproved = $this->create_forum_content();

		bbp_unapprove_topic( $unapproved['topic_id'] );

		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $unapproved['topic_id'] ) );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $unapproved['reply_id'] ) );
		$this->assertSame( 200, $this->get_item( bbp_get_reply_post_type(), $public['reply_id'] )->get_status() );
		$this->assertSame( 401, $this->get_item( bbp_get_topic_post_type(), $unapproved['topic_id'] )->get_status() );
		$this->assertSame( 401, $this->get_item( bbp_get_reply_post_type(), $unapproved['reply_id'] )->get_status() );
		$this->assertSame( 401, $this->get_item( 'media', $this->create_attachment( $unapproved['reply_id'] ) )->get_status() );

		$replies   = $this->get_items( bbp_get_reply_post_type() );
		$reply_ids = wp_list_pluck( $replies->get_data(), 'id' );

		$this->assertSame( 200, $replies->get_status() );
		$this->assertContains( $public['reply_id'], $reply_ids );
		$this->assertNotContains( $unapproved['reply_id'], $reply_ids );

		$participant_id = $this->factory->user->create();
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );

		$this->assertSame( 403, $this->get_item( bbp_get_topic_post_type(), $unapproved['topic_id'], $participant_id )->get_status() );
		$this->assertSame( 403, $this->get_item( bbp_get_reply_post_type(), $unapproved['reply_id'], $participant_id )->get_status() );

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

		$this->assertSame( 200, $this->get_item( bbp_get_topic_post_type(), $unapproved['topic_id'], $moderator_id )->get_status() );
		$this->assertSame( 200, $this->get_item( bbp_get_reply_post_type(), $unapproved['reply_id'], $moderator_id )->get_status() );

		$forum_moderator_id = $this->factory->user->create();
		bbp_set_user_role( $forum_moderator_id, bbp_get_participant_role() );
		bbp_add_moderator( $unapproved['forum_id'], $forum_moderator_id );

		$this->assertSame( 200, $this->get_item( bbp_get_topic_post_type(), $unapproved['topic_id'], $forum_moderator_id )->get_status() );
		$this->assertSame( 200, $this->get_item( bbp_get_reply_post_type(), $unapproved['reply_id'], $forum_moderator_id )->get_status() );
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
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_visibility_map_status_remains_restricted_for_rest() {
		$posts          = $this->create_forum_content();
		$participant_id = $this->factory->user->create();
		$hidden_user_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$filter         = function( $visibilities ) {
			$visibilities['archived'] = 'Archived';

			return $visibilities;
		};

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $hidden_user_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		get_user_by( 'id', $hidden_user_id )->add_cap( 'read_hidden_forums' );
		wp_update_post( array( 'ID' => $posts['forum_id'], 'post_status' => 'archived' ) );
		add_filter( 'bbp_get_forum_visibilities', $filter );

		try {
			$this->assertSame( 'archived', get_post_status( $posts['forum_id'] ) );
			$this->assert_item_status( 401, $posts );
			$this->assert_item_status( 403, $posts, $participant_id );
			$this->assert_item_status( 200, $posts, $hidden_user_id );
			$this->assert_item_status( 200, $posts, $moderator_id );
		} finally {
			remove_filter( 'bbp_get_forum_visibilities', $filter );
		}
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_anonymous_user_can_read_public_content_without_forum() {
		$topic_ids = $this->factory->topic->create_many(
			2,
			array(
				'post_parent' => 0,
				'topic_meta'  => array( 'forum_id' => 0 ),
			)
		);
		$reply_id  = $this->factory->reply->create(
			array(
				'post_parent' => $topic_ids[0],
				'reply_meta'  => array(
					'forum_id' => 0,
					'topic_id' => $topic_ids[0],
				),
			)
		);

		foreach ( $topic_ids as $topic_id ) {
			$this->assertSame( 200, $this->get_item( bbp_get_topic_post_type(), $topic_id )->get_status() );
		}
		$this->assertSame( 200, $this->get_item( bbp_get_reply_post_type(), $reply_id )->get_status() );

		$collection_ids = wp_list_pluck( $this->get_items( bbp_get_topic_post_type() )->get_data(), 'id' );
		$this->assertEmpty( array_diff( $topic_ids, $collection_ids ) );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 * @covers ::bbp_trash_forum_topics
	 */
	public function test_anonymous_user_cannot_read_restricted_forum_content_after_forum_is_trashed() {
		foreach ( array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ) as $status ) {
			$posts = $this->create_forum_content( $status );

			$this->assert_item_status( 401, $posts );
			$this->set_current_user( 0 );
			wp_trash_post( $posts['forum_id'] );

			$this->assertSame( 'trash', get_post_status( $posts['forum_id'] ) );
			$this->assertSame( 'trash', get_post_status( $posts['topic_id'] ) );
			$this->assertSame( 'trash', get_post_status( $posts['reply_id'] ) );
			$this->assertSame( 401, $this->get_item( bbp_get_topic_post_type(), $posts['topic_id'] )->get_status() );
			$this->assertSame( 401, $this->get_item( bbp_get_reply_post_type(), $posts['reply_id'] )->get_status() );
		}
	}

	/**
	 * @covers BBP_REST_Posts_Controller::check_read_permission
	 */
	public function test_anonymous_user_cannot_read_published_content_with_trashed_forum() {
		foreach ( array( bbp_get_public_status_id(), bbp_get_hidden_status_id() ) as $status ) {
			$posts = $this->create_forum_content( $status );

			wp_update_post(
				array(
					'ID'          => $posts['forum_id'],
					'post_status' => 'trash',
				)
			);

			$this->assertSame( 'publish', get_post_status( $posts['topic_id'] ) );
			$this->assertSame( 'publish', get_post_status( $posts['reply_id'] ) );
			$this->assertSame( 401, $this->get_item( bbp_get_topic_post_type(), $posts['topic_id'] )->get_status() );
			$this->assertSame( 401, $this->get_item( bbp_get_reply_post_type(), $posts['reply_id'] )->get_status() );
			$this->assertNotContains( $posts['topic_id'], wp_list_pluck( $this->get_items( bbp_get_topic_post_type() )->get_data(), 'id' ) );
			$this->assertNotContains( $posts['reply_id'], wp_list_pluck( $this->get_items( bbp_get_reply_post_type() )->get_data(), 'id' ) );
		}
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
	 * @covers ::bbp_make_post_attachments_private
	 */
	public function test_permanently_deleted_hidden_forum_keeps_attachments_private() {
		$posts           = $this->create_forum_content( bbp_get_hidden_status_id() );
		$attachment_ids = array();

		foreach ( $posts as $parent_id ) {
			$attachment_ids[] = $this->create_attachment( $parent_id );
		}

		$this->set_current_user( 0 );
		wp_delete_post( $posts['forum_id'], true );

		foreach ( $attachment_ids as $attachment_id ) {
			$this->assertSame( 'private', get_post_status( $attachment_id ) );
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
	 * @covers BBP_REST_Attachment_Parent_Controller::check_is_post_type_allowed
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
	 * An empty parent ID must not resolve to the global post.
	 *
	 * @covers ::bbp_user_can_view_attachment_parent
	 */
	public function test_attachment_parent_permission_rejects_empty_id() {
		$posts         = $this->create_forum_content();
		$original_post = $GLOBALS['post'] ?? null;

		try {
			$GLOBALS['post'] = get_post( $posts['topic_id'] );
			$this->assertFalse( bbp_user_can_view_attachment_parent( 0 ) );
		} finally {
			$GLOBALS['post'] = $original_post;
		}
	}

	/**
	 * Media writes must respect the attachment's current bbPress parent.
	 *
	 * @covers BBP_REST_Attachments_Controller::check_update_permission
	 * @covers BBP_REST_Attachments_Controller::check_delete_permission
	 */
	public function test_media_writes_require_current_bbpress_parent_access() {
		$posts   = $this->create_forum_content();
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$attachment_id = $this->create_attachment( $posts['topic_id'] );
		$controller    = new BBP_REST_Attachments_Controller( 'attachment' );
		$request       = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'id', $attachment_id );

		$this->assertTrue( current_user_can( 'edit_post', $attachment_id ) );
		$this->assertTrue( current_user_can( 'delete_post', $attachment_id ) );
		$this->assertTrue( $controller->update_item_permissions_check( $request ) );
		$this->assertTrue( $controller->delete_item_permissions_check( $request ) );

		wp_update_post( array( 'ID' => $posts['forum_id'], 'post_status' => bbp_get_hidden_status_id() ) );

		$this->assertFalse( bbp_user_can_view_attachment( $attachment_id ) );

		foreach ( array( 'update_item_permissions_check', 'delete_item_permissions_check', 'post_process_item_permissions_check', 'edit_media_item_permissions_check' ) as $method ) {
			$permission = $controller->$method( $request );
			$this->assertWPError( $permission );
			$this->assertSame( 403, $permission->get_error_data()['status'] );
		}

		if ( method_exists( $controller, 'sideload_item_permissions_check' ) ) {
			$permission = $controller->sideload_item_permissions_check( $request );
			$this->assertWPError( $permission );
			$this->assertSame( 403, $permission->get_error_data()['status'] );
		}

		$request->set_param( 'post', 0 );
		$this->assertWPError( $controller->update_item_permissions_check( $request ) );
		$this->assertSame( 'Restricted attachment', get_post( $attachment_id )->post_title );
	}

	/**
	 * Reparenting and creating media must respect the requested bbPress parent.
	 *
	 * @covers BBP_REST_Attachments_Controller::create_item_permissions_check
	 * @covers BBP_REST_Attachments_Controller::update_item_permissions_check
	 */
	public function test_media_writes_require_requested_bbpress_parent_access() {
		$public     = $this->create_forum_content();
		$restricted = $this->create_forum_content();
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		wp_update_post( array( 'ID' => $restricted['topic_id'], 'post_author' => $user_id ) );
		wp_update_post( array( 'ID' => $restricted['forum_id'], 'post_password' => 'new-password' ) );
		$this->set_current_user( $user_id );

		$attachment_id = $this->create_attachment( $public['topic_id'] );
		$controller    = new BBP_REST_Attachments_Controller( 'attachment' );
		$request       = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'post', $restricted['topic_id'] );

		$this->assertTrue( current_user_can( 'edit_post', $attachment_id ) );
		$this->assertTrue( current_user_can( 'edit_post', $restricted['topic_id'] ) );
		$this->assertFalse( bbp_user_can_view_attachment_parent( $restricted['topic_id'] ) );
		$permission = $controller->create_item_permissions_check( $request );
		$this->assertWPError( $permission );
		$this->assertSame( 403, $permission->get_error_data()['status'] );

		$request->set_param( 'id', $attachment_id );
		$permission = $controller->update_item_permissions_check( $request );
		$this->assertWPError( $permission );
		$this->assertSame( 403, $permission->get_error_data()['status'] );
		$this->assertSame( $public['topic_id'], get_post( $attachment_id )->post_parent );

		$other_public = $this->create_forum_content();
		$request->set_param( 'post', $other_public['topic_id'] );
		$this->assertTrue( $controller->update_item_permissions_check( $request ) );
		$request->set_param( 'id', null );
		$this->assertTrue( $controller->create_item_permissions_check( $request ) );
		$this->assertSame( 200, $this->update_item( 'media', $attachment_id, $user_id, array( 'post' => $other_public['topic_id'] ) )->get_status() );
		$this->assertSame( $other_public['topic_id'], get_post( $attachment_id )->post_parent );
	}

	/**
	 * Reparenting needs the same target edit capability as a new upload.
	 *
	 * @covers BBP_REST_Attachments_Controller::update_item_permissions_check
	 */
	public function test_media_reparenting_requires_requested_parent_edit_capability() {
		$source = $this->create_forum_content();
		$target = $this->create_forum_content();
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$attachment_id = $this->create_attachment( $source['topic_id'] );
		$controller    = new BBP_REST_Attachments_Controller( 'attachment' );
		$request       = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'id', $attachment_id );
		$request->set_param( 'post', $target['topic_id'] );

		$this->assertTrue( current_user_can( 'edit_post', $attachment_id ) );
		$this->assertTrue( bbp_user_can_view_attachment_parent( $target['topic_id'] ) );
		$this->assertFalse( current_user_can( 'edit_post', $target['topic_id'] ) );

		$permission = $controller->update_item_permissions_check( $request );
		$this->assertWPError( $permission );
		$this->assertSame( 403, $permission->get_error_data()['status'] );

		$request->set_param( 'post', $source['topic_id'] );
		$this->assertFalse( current_user_can( 'edit_post', $source['topic_id'] ) );
		$this->assertTrue( $controller->update_item_permissions_check( $request ) );
	}

	/**
	 * Ordinary WordPress media keeps its existing permission checks.
	 *
	 * @covers BBP_REST_Attachments_Controller::check_update_permission
	 * @covers BBP_REST_Attachments_Controller::check_delete_permission
	 */
	public function test_media_write_permissions_preserve_ordinary_wordpress_media() {
		$post_id = $this->factory->post->create();
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$this->set_current_user( $user_id );

		$attachment_id = $this->create_attachment( $post_id );
		$controller    = new BBP_REST_Attachments_Controller( 'attachment' );
		$request       = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'id', $attachment_id );

		$this->assertTrue( $controller->update_item_permissions_check( $request ) );
		$this->assertTrue( $controller->delete_item_permissions_check( $request ) );

		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $subscriber_id );

		$this->assertWPError( $controller->update_item_permissions_check( $request ) );
		$this->assertWPError( $controller->delete_item_permissions_check( $request ) );
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
	 * Closing content prevents new content without freezing existing edits.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_can_edit_topic_and_reply_in_closed_forum_through_rest() {
		$topic_owner_id = $this->factory->user->create();
		$reply_owner_id = $this->factory->user->create();
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create( array( 'post_author' => $topic_owner_id, 'post_parent' => $forum_id, 'post_content' => 'Original content.', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id       = $this->factory->reply->create( array( 'post_author' => $reply_owner_id, 'post_parent' => $topic_id, 'post_content' => 'Original reply.', 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );

		bbp_set_user_role( $topic_owner_id, bbp_get_participant_role() );
		bbp_set_user_role( $reply_owner_id, bbp_get_participant_role() );
		bbp_close_topic( $topic_id );
		bbp_close_forum( $forum_id );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $topic_owner_id, array( 'content' => 'REST edit.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'REST edit.', get_post_field( 'post_content', $topic_id ) );

		$response = $this->update_item( bbp_get_reply_post_type(), $reply_id, $reply_owner_id, array( 'content' => 'REST reply edit.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'REST reply edit.', get_post_field( 'post_content', $reply_id ) );
	}

	/**
	 * Topics cannot be edited while their parent is a category.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_cannot_edit_topic_in_category_forum_through_rest() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'post_content' => 'Original content.', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_categorize_forum( $forum_id );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'content' => 'REST edit.' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'Original content.', get_post_field( 'post_content', $topic_id ) );
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
	 * A pending topic's GMT date must not lock out its recent author.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_can_edit_recent_pending_topic_through_rest() {
		update_option( 'timezone_string', 'America/Chicago' );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'post_status' => bbp_get_pending_status_id(), 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$this->assertSame( get_post_field( 'post_date', $topic_id ), get_date_from_gmt( get_post_field( 'post_date_gmt', $topic_id ) ) );
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
	 * @dataProvider data_unchanged_post_dates
	 *
	 * @param string $post_date     Local post date.
	 * @param string $post_date_gmt GMT post date.
	 */
	public function test_participant_can_roundtrip_unchanged_status_and_date( $post_date, $post_date_gmt ) {
		update_option( 'timezone_string', 'America/Chicago' );
		update_option( '_bbp_edit_lock', 0 );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'post_date' => $post_date, 'post_date_gmt' => $post_date_gmt, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$post     = get_post( $topic_id );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'status' => bbp_get_public_status_id(), 'date' => mysql_to_rfc3339( $post->post_date ), 'date_gmt' => mysql_to_rfc3339( $post->post_date_gmt ), 'content' => 'Updated content.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated content.', get_post_field( 'post_content', $topic_id ) );
		$this->assertSame( $post_date, get_post_field( 'post_date', $topic_id ) );
		$this->assertSame( $post_date_gmt, get_post_field( 'post_date_gmt', $topic_id ) );
	}

	/**
	 * Data provider for unchanged post dates exposed by REST.
	 *
	 * @return array[] Local and GMT dates.
	 */
	public function data_unchanged_post_dates() {
		return array(
			'first repeated hour'  => array( '2025-11-02 01:30:00', '2025-11-02 06:30:00' ),
			'second repeated hour' => array( '2025-11-02 01:30:00', '2025-11-02 07:30:00' ),
			'ordinary date'        => array( '2025-07-01 12:30:00', '2025-07-01 17:30:00' ),
		);
	}

	/**
	 * A derived GMT date for a legacy pending post must round-trip unchanged.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_can_roundtrip_legacy_zero_gmt_date() {
		global $wpdb;

		update_option( 'timezone_string', 'America/Chicago' );
		update_option( '_bbp_edit_lock', 0 );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'post_status' => bbp_get_pending_status_id(), 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$post_date = get_post_field( 'post_date', $topic_id );

		$wpdb->update( $wpdb->posts, array( 'post_date_gmt' => '0000-00-00 00:00:00' ), array( 'ID' => $topic_id ) );
		clean_post_cache( $topic_id );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'date_gmt' => mysql_to_rfc3339( get_gmt_from_date( $post_date ) ), 'content' => 'Updated content.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated content.', get_post_field( 'post_content', $topic_id ) );
		$this->assertSame( $post_date, get_post_field( 'post_date', $topic_id ) );
	}

	/**
	 * A participant cannot move a topic to the other repeated-hour occurrence.
	 *
	 * @covers BBP_REST_Posts_Controller::update_item_permissions_check
	 */
	public function test_participant_cannot_change_repeated_hour_occurrence() {
		update_option( 'timezone_string', 'America/Chicago' );
		update_option( '_bbp_edit_lock', 0 );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'   => $user_id,
				'post_parent'   => $forum_id,
				'post_date'     => '2025-11-02 01:30:00',
				'post_date_gmt' => '2025-11-02 07:30:00',
				'topic_meta'    => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'date_gmt' => mysql_to_rfc3339( '2025-11-02 06:30:00' ) ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'bbp_rest_cannot_change_date', $response->get_data()['code'] );
		$this->assertSame( '2025-11-02 01:30:00', get_post_field( 'post_date', $topic_id ) );
		$this->assertSame( '2025-11-02 07:30:00', get_post_field( 'post_date_gmt', $topic_id ) );
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

		$this->assertSame( get_post_field( 'post_date', $reply_id ), get_date_from_gmt( get_post_field( 'post_date_gmt', $reply_id ) ) );
		$response = $this->update_item( bbp_get_reply_post_type(), $reply_id, $user_id, array( 'content' => 'Pending reply revision.' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Pending reply revision.', get_post_field( 'post_content', $reply_id ) );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $reply_id ) );
	}

	/**
	 * bbPress metadata must not interfere with ordinary post updates.
	 *
	 * @covers BBPress::register_meta
	 */
	public function test_count_meta_does_not_interfere_with_post_rest_updates() {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id = $this->factory->post->create( array( 'post_author' => $user_id ) );
		bbpress()->register_meta();
		$old_rest_server = isset( $GLOBALS['wp_rest_server'] ) ? $GLOBALS['wp_rest_server'] : null;
		$GLOBALS['wp_rest_server'] = null;

		$response = $this->get_item( 'posts', $post_id, $user_id );
		$data     = $response->get_data();
		$bbp_meta = preg_grep( '/^_bbp_/', array_keys( $data['meta'] ) );

		$update = $this->update_item(
			'posts',
			$post_id,
			$user_id,
			array(
				'content' => 'Updated article.',
				'meta'    => $data['meta'],
			)
		);

		$GLOBALS['wp_rest_server'] = $old_rest_server;

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $bbp_meta );
		$this->assertSame( 200, $update->get_status() );
		$this->assertSame( 'Updated article.', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Hidden counts stay out of REST even when custom fields are enabled.
	 *
	 * @coversNothing
	 */
	public function test_hidden_counts_are_not_exposed_with_custom_fields_support() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		add_post_type_support( bbp_get_forum_post_type(), 'custom-fields' );
		add_post_type_support( bbp_get_topic_post_type(), 'custom-fields' );
		bbpress()->register_meta();
		$old_rest_server = isset( $GLOBALS['wp_rest_server'] ) ? $GLOBALS['wp_rest_server'] : null;
		$GLOBALS['wp_rest_server'] = null;

		$response = $this->get_item( bbp_get_forum_post_type(), $forum_id );
		$data     = $response->get_data();
		$topic_response = $this->get_item( bbp_get_topic_post_type(), $topic_id );
		$topic_data     = $topic_response->get_data();

		remove_post_type_support( bbp_get_forum_post_type(), 'custom-fields' );
		remove_post_type_support( bbp_get_topic_post_type(), 'custom-fields' );
		$GLOBALS['wp_rest_server'] = $old_rest_server;

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( '_bbp_topic_count', $data['meta'] );
		$this->assertArrayNotHasKey( '_bbp_topic_count_hidden', $data['meta'] );
		$this->assertArrayNotHasKey( '_bbp_reply_count_hidden', $data['meta'] );
		$this->assertArrayNotHasKey( '_bbp_total_topic_count_hidden', $data['meta'] );
		$this->assertArrayNotHasKey( '_bbp_total_reply_count_hidden', $data['meta'] );
		$this->assertSame( 200, $topic_response->get_status() );
		$this->assertArrayHasKey( '_bbp_reply_count', $topic_data['meta'] );
		$this->assertArrayNotHasKey( '_bbp_reply_count_hidden', $topic_data['meta'] );
	}

	/**
	 * Derived counts cannot be overwritten through REST post metadata.
	 *
	 * @coversNothing
	 */
	public function test_participant_cannot_write_topic_count_meta_through_rest() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		add_post_type_support( bbp_get_topic_post_type(), 'custom-fields' );
		bbpress()->register_meta();
		$old_rest_server = isset( $GLOBALS['wp_rest_server'] ) ? $GLOBALS['wp_rest_server'] : null;
		$GLOBALS['wp_rest_server'] = null;

		$before   = (int) get_post_meta( $topic_id, '_bbp_reply_count', true );
		$response = $this->update_item( bbp_get_topic_post_type(), $topic_id, $user_id, array( 'meta' => array( '_bbp_reply_count' => 999 ) ) );

		remove_post_type_support( bbp_get_topic_post_type(), 'custom-fields' );
		$GLOBALS['wp_rest_server'] = $old_rest_server;

		$this->assertNotSame( 999, (int) get_post_meta( $topic_id, '_bbp_reply_count', true ) );
		$this->assertSame( $before, (int) get_post_meta( $topic_id, '_bbp_reply_count', true ) );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Derived user activity metadata cannot be overwritten through REST.
	 *
	 * @coversNothing
	 */
	public function test_participant_cannot_write_user_activity_meta_through_rest() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbpress()->register_meta();
		$old_rest_server = isset( $GLOBALS['wp_rest_server'] ) ? $GLOBALS['wp_rest_server'] : null;
		$GLOBALS['wp_rest_server'] = null;
		foreach ( array( '_bbp_topic_count', '_bbp_reply_count', '_bbp_last_posted' ) as $meta_key ) {
			$before   = (int) get_user_meta( $user_id, $meta_key, true );
			$response = $this->dispatch_request( 'POST', '/wp/v2/users/' . $user_id, $user_id, array( 'meta' => array( $meta_key => 999 ) ) );

			$this->assertSame( $before, (int) get_user_meta( $user_id, $meta_key, true ) );
			$this->assertSame( 403, $response->get_status(), $meta_key );
		}

		$GLOBALS['wp_rest_server'] = $old_rest_server;
	}

	/**
	 * @covers BBP_REST_Posts_Controller::create_item_permissions_check
	 */
	public function test_moderator_create_obeys_strict_block_list() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		update_option( 'disallowed_keys', 'blocked phrase' );

		$response = $this->dispatch_request( 'POST', '/wp/v2/' . bbp_get_topic_post_type(), $user_id, array( 'title' => 'REST topic', 'content' => 'A blocked phrase.' ) );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'bbp_rest_disallowed_content', $response->get_data()['code'] );

		$response = $this->dispatch_request( 'POST', '/wp/v2/' . bbp_get_topic_post_type(), $user_id, array( 'title' => 'REST topic', 'content' => 'Clean moderator topic.' ) );
		$this->assertSame( 201, $response->get_status() );
	}

	/**
	 * Frozen forums return a clear REST permission error for new content.
	 *
	 * @covers BBP_REST_Posts_Controller::create_item_permissions_check
	 */
	public function test_frozen_blocks_moderator_topic_creation_through_rest() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$forum_id = $this->factory->forum->create();
		update_option( '_bbp_forums_status', 'frozen' );

		$response = $this->dispatch_request( 'POST', '/wp/v2/' . bbp_get_topic_post_type(), $user_id, array( 'parent' => $forum_id, 'title' => 'Frozen topic', 'content' => 'Cannot post.' ) );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'bbp_rest_forums_closed', $response->get_data()['code'] );
	}

	/**
	 * @covers BBP_REST_Posts_Controller::create_item_permissions_check
	 */
	public function test_frozen_blocks_moderator_forum_creation_through_rest() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		update_option( '_bbp_forums_status', 'frozen' );

		$response = $this->dispatch_request( 'POST', '/wp/v2/' . bbp_get_forum_post_type(), $user_id, array( 'title' => 'Ordinary forum' ) );
		$this->assertSame( 403, $response->get_status() );
	}
}
