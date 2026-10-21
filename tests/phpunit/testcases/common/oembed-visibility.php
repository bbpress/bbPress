<?php

/**
 * oEmbed must respect bbPress visibility.
 *
 * @group common
 * @group visibility
 */
class BBP_Tests_Common_Oembed_Visibility extends BBP_UnitTestCase {

	/**
	 * Set up an attachment query with a normalized request ID.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $query_var     Requested query variable.
	 * @return WP_Query Query object.
	 */
	protected function get_attachment_query( $attachment_id, $query_var = 'attachment_id' ) {
		$query = new WP_Query();

		$query->post          = get_post( $attachment_id );
		$query->is_singular   = true;
		$query->is_attachment = true;
		$query->set( 'post_type', 'attachment' );
		$query->set( 'p', 0 );
		$query->set( 'page_id', 0 );
		$query->set( 'attachment_id', 0 );

		// WordPress normalizes subpost_id into attachment_id.
		$query_var = ( 'subpost_id' === $query_var )
			? 'attachment_id'
			: $query_var;
		$query->set( $query_var, $attachment_id );

		return $query;
	}

	/**
	 * @covers ::bbp_forum_enforce_attachments
	 * @covers ::bbp_do_not_redirect_restricted_attachments
	 * @covers ::bbp_get_attachment_request_id
	 * @covers ::bbp_user_can_view_attachment
	 */
	public function test_attachment_page_inherits_forum_visibility() {
		global $wp_query;

		$previous = $wp_query;
		$cases    = array(
			'public ancestor'             => array( 'publish', '', false ),
			'private ancestor'            => array( bbp_get_private_status_id(), '', true ),
			'hidden ancestor'             => array( bbp_get_hidden_status_id(), '', true ),
			'password-protected ancestor' => array( 'publish', 'ancestor-secret', true ),
		);

		$this->set_current_user( 0 );

		try {
			foreach ( $cases as $case => $args ) {
				list( $status, $password, $expected_404 ) = $args;

				$root_id = $this->factory->forum->create(
					array(
						'post_status'   => $status,
						'post_password' => $password,
					)
				);
				$forum_id = $this->factory->forum->create( array( 'post_parent' => $root_id ) );
				$topic_id = $this->factory->topic->create(
					array(
						'post_parent' => $forum_id,
						'topic_meta'  => array( 'forum_id' => $forum_id ),
					)
				);
				$reply_id = $this->factory->reply->create(
					array(
						'post_parent' => $topic_id,
						'reply_meta'  => array(
							'forum_id' => $forum_id,
							'topic_id' => $topic_id,
						),
					)
				);

				foreach ( compact( 'forum_id', 'topic_id', 'reply_id' ) as $parent_id ) {
					$attachment_id = wp_insert_attachment(
						array(
							'post_title'     => 'Attachment page ' . $case,
							'post_status'    => 'inherit',
							'post_parent'    => $parent_id,
							'post_mime_type' => 'text/plain',
							'guid'           => home_url( '/attachment-page-' . $parent_id . '-' . sanitize_title( $case ) . '.txt' ),
						)
					);
					$redirect_url = wp_get_attachment_url( $attachment_id );
					$requests     = array(
						'attachment_id' => 'attachment_id',
						'p'             => 'p',
						'page_id'       => 'page_id',
						'subpost_id'    => 'subpost_id',
					);

					foreach ( $requests as $request_arg => $query_var ) {
						$query       = $this->get_attachment_query( $attachment_id, $query_var );
						$wp_query    = $query;
						$request_url = home_url( '/?' . $request_arg . '=' . $attachment_id );

						do_action( 'bbp_template_redirect' );
						$this->assertSame( $expected_404, $query->is_404(), $case . ' for ' . get_post_type( $parent_id ) . ' via ' . $request_arg );

						$actual = bbp_do_not_redirect_restricted_attachments( $redirect_url, $request_url );
						$this->assertSame( $expected_404 ? false : $redirect_url, $actual, $case . ' redirect for ' . get_post_type( $parent_id ) . ' via ' . $request_arg );
					}
				}
			}
		} finally {
			$wp_query = $previous;
		}
	}

	/**
	 * @covers ::bbp_forum_enforce_attachments
	 * @covers ::bbp_user_can_view_attachment
	 */
	public function test_attachment_page_uses_resolved_post_type_for_visibility() {
		global $wp_query;

		$previous = $wp_query;
		$forum_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$attachment_id = wp_insert_attachment(
			array(
				'post_status'    => 'inherit',
				'post_parent'    => $topic_id,
				'post_mime_type' => 'text/plain',
				'guid'           => home_url( '/restricted-single-attachment.txt' ),
			)
		);
		$no_role_id     = $this->factory->user->create();
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$keymaster_id   = $this->factory->user->create();

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		$cases = array(
			'anonymous'   => array( 0, true ),
			'no role'     => array( $no_role_id, true ),
			'participant' => array( $participant_id, true ),
			'moderator'   => array( $moderator_id, false ),
			'keymaster'   => array( $keymaster_id, false ),
		);

		try {
			foreach ( $cases as $case => $args ) {
				list( $user_id, $expected_404 ) = $args;

				$this->set_current_user( $user_id );

				$query                = $this->get_attachment_query( $attachment_id, 'p' );
				$query->is_attachment = false;
				$query->is_single     = true;
				$wp_query             = $query;

				do_action( 'bbp_template_redirect' );

				$this->assertSame( $expected_404, $query->is_404(), $case );
			}
		} finally {
			$wp_query = $previous;
		}
	}

	/**
	 * @covers ::bbp_forum_enforce_attachments
	 * @covers ::bbp_user_can_view_attachment
	 */
	public function test_restricted_attachment_feed_does_not_retain_denied_post() {
		global $wp_query;

		$previous = $wp_query;
		$forum_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$attachment_id = wp_insert_attachment(
			array(
				'post_status'    => 'inherit',
				'post_parent'    => $topic_id,
				'post_mime_type' => 'text/plain',
				'guid'           => home_url( '/restricted-feed-attachment.txt' ),
			)
		);
		$query = $this->get_attachment_query( $attachment_id );
		$post  = get_post( $attachment_id );

		$query->posts      = array( $post );
		$query->post_count = 1;
		$query->is_feed    = true;
		$wp_query          = $query;

		$this->set_current_user( 0 );

		try {
			do_action( 'bbp_template_redirect' );

			$this->assertTrue( $query->is_404() );
			$this->assertSame( array(), $query->posts );
			$this->assertNull( $query->post );
			$this->assertSame( 0, $query->post_count );
		} finally {
			$wp_query = $previous;
		}
	}

	/**
	 * @covers ::bbp_do_not_redirect_restricted_attachments
	 * @covers ::bbp_get_attachment_request_id
	 * @covers ::bbp_user_can_view_attachment
	 */
	public function test_attachment_canonical_redirect_uses_attachment_id_when_query_vars_conflict() {
		global $wp_query;

		$previous = $wp_query;
		$forum_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$attachment_id = wp_insert_attachment(
			array(
				'post_status'    => 'inherit',
				'post_parent'    => $topic_id,
				'post_mime_type' => 'text/plain',
				'guid'           => home_url( '/restricted-conflicting-query-vars.txt' ),
			)
		);
		$decoy_id = wp_insert_attachment(
			array(
				'post_status'    => 'inherit',
				'post_parent'    => 0,
				'post_mime_type' => 'text/plain',
				'guid'           => home_url( '/public-conflicting-query-vars.txt' ),
			)
		);
		$query = $this->get_attachment_query( $attachment_id );
		$query->post              = get_post( $decoy_id );
		$query->queried_object    = get_post( $decoy_id );
		$query->queried_object_id = $decoy_id;
		$query->set( 'page_id', $decoy_id );
		$wp_query = $query;

		$no_role_id     = $this->factory->user->create();
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$keymaster_id   = $this->factory->user->create();

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		$redirect_url  = wp_get_attachment_url( $attachment_id );
		$requested_url = home_url( '/?attachment_id=' . $attachment_id . '&page_id=' . $decoy_id );
		$cases         = array(
			'anonymous'   => array( 0, false ),
			'no role'     => array( $no_role_id, false ),
			'participant' => array( $participant_id, false ),
			'moderator'   => array( $moderator_id, $redirect_url ),
			'keymaster'   => array( $keymaster_id, $redirect_url ),
		);

		try {
			foreach ( $cases as $case => $args ) {
				list( $user_id, $expected ) = $args;

				$this->set_current_user( $user_id );
				$this->assertSame( $expected, bbp_do_not_redirect_restricted_attachments( $redirect_url, $requested_url ), $case );
			}
		} finally {
			$wp_query = $previous;
		}
	}

	/**
	 * @covers ::bbp_do_not_redirect_restricted_attachments
	 * @covers ::bbp_get_attachment_request_id
	 */
	public function test_attachment_canonical_redirect_uses_resolved_slug_object() {
		global $wp_query;

		$previous = $wp_query;
		$forum_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$attachment_id = wp_insert_attachment(
			array(
				'post_name'      => 'restricted-attachment-slug',
				'post_status'    => 'inherit',
				'post_parent'    => $topic_id,
				'post_mime_type' => 'image/jpeg',
				'guid'           => home_url( '/restricted-attachment-slug.jpg' ),
			)
		);
		$query                    = $this->get_attachment_query( $attachment_id );
		$query->is_404            = true;
		$query->is_attachment     = false;
		$query->is_singular       = false;
		$query->queried_object    = get_post( $attachment_id );
		$query->queried_object_id = $attachment_id;
		$query->set( 'attachment_id', 0 );
		$query->set( 'page', 2 );
		$wp_query = $query;

		try {
			$requested_url = home_url( '/?attachment=restricted-attachment-slug&page=2' );
			$redirect_url  = get_attachment_link( $attachment_id );

			$this->assertSame( $attachment_id, bbp_get_attachment_request_id() );
			$this->assertFalse( bbp_do_not_redirect_restricted_attachments( $redirect_url, $requested_url ) );
		} finally {
			$wp_query = $previous;
		}
	}

	/**
	 * @covers ::bbp_forum_enforce_attachments
	 * @covers ::bbp_do_not_redirect_restricted_attachments
	 * @covers ::bbp_user_can_view_attachment
	 */
	public function test_authorized_user_can_view_restricted_attachment_pages() {
		global $wp_query;

		$previous     = $wp_query;
		$statuses     = array( bbp_get_private_status_id(), bbp_get_hidden_status_id() );
		$keymaster_id = $this->factory->user->create();

		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->set_current_user( $keymaster_id );

		try {
			foreach ( $statuses as $status ) {
				$forum_id = $this->factory->forum->create( array( 'post_status' => $status ) );
				$topic_id = $this->factory->topic->create(
					array(
						'post_parent' => $forum_id,
						'topic_meta'  => array( 'forum_id' => $forum_id ),
					)
				);
				$reply_id = $this->factory->reply->create(
					array(
						'post_parent' => $topic_id,
						'reply_meta'  => array(
							'forum_id' => $forum_id,
							'topic_id' => $topic_id,
						),
					)
				);

				foreach ( compact( 'forum_id', 'topic_id', 'reply_id' ) as $parent_id ) {
					$attachment_id = wp_insert_attachment(
						array(
							'post_title'     => 'Authorized restricted attachment',
							'post_status'    => 'inherit',
							'post_parent'    => $parent_id,
							'post_mime_type' => 'text/plain',
							'guid'           => home_url( '/authorized-restricted-' . $parent_id . '.txt' ),
						)
					);
					$query         = $this->get_attachment_query( $attachment_id );
					$wp_query      = $query;
					$redirect_url  = wp_get_attachment_url( $attachment_id );
					$requested_url = home_url( '/?attachment_id=' . $attachment_id );

					do_action( 'bbp_template_redirect' );

					$this->assertFalse( $query->is_404(), $status . ' for ' . get_post_type( $parent_id ) );
					$this->assertSame( $redirect_url, bbp_do_not_redirect_restricted_attachments( $redirect_url, $requested_url ) );
				}
			}
		} finally {
			$wp_query = $previous;
		}
	}

	/**
	 * @covers ::bbp_forum_enforce_attachments
	 * @covers ::bbp_do_not_redirect_restricted_attachments
	 * @covers ::bbp_filter_oembed_request_attachment_id
	 * @covers ::bbp_user_can_view_attachment
	 */
	public function test_attachment_page_honors_password_cookie_without_enabling_oembed() {
		global $wp_query;

		$previous = $wp_query;
		$forum_id = $this->factory->forum->create(
			array(
				'post_password' => 'forum-secret',
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => 'Password attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $topic_id,
				'post_mime_type' => 'image/jpeg',
				'guid'           => home_url( '/password-attachment.jpg' ),
			)
		);
		$query         = $this->get_attachment_query( $attachment_id );
		$wp_query      = $query;
		$redirect_url  = wp_get_attachment_url( $attachment_id );
		$requested_url = home_url( '/?attachment_id=' . $attachment_id );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher     = new PasswordHash( 8, true );
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'forum-secret' );

		try {
			do_action( 'bbp_template_redirect' );

			$this->assertFalse( $query->is_404() );
			$this->assertSame( $redirect_url, bbp_do_not_redirect_restricted_attachments( $redirect_url, $requested_url ) );
			$this->assertSame( 0, bbp_filter_oembed_request_attachment_id( $attachment_id ) );
		} finally {
			$wp_query = $previous;

			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * @covers ::bbp_forum_enforce_attachments
	 * @covers ::bbp_do_not_redirect_restricted_attachments
	 */
	public function test_attachment_page_preserves_wordpress_media_visibility() {
		global $wp_query;

		$previous   = $wp_query;
		$post_id    = $this->factory->post->create();
		$parent_ids = array( 0, $post_id );

		$this->set_current_user( 0 );

		try {
			foreach ( $parent_ids as $parent_id ) {
				$attachment_id = wp_insert_attachment(
					array(
						'post_title'     => 'Ordinary WordPress attachment',
						'post_status'    => 'inherit',
						'post_parent'    => $parent_id,
						'post_mime_type' => 'text/plain',
						'guid'           => home_url( '/ordinary-wordpress-attachment-' . $parent_id . '.txt' ),
					)
				);
				$query = new WP_Query();
				$query->post          = get_post( $attachment_id );
				$query->is_singular   = true;
				$query->is_attachment = true;
				$query->set( 'attachment_id', $attachment_id );
				$query->set( 'post_type', 'attachment' );
				$wp_query = $query;

				do_action( 'bbp_template_redirect' );
				$this->assertFalse( $query->is_404() );

				$redirect_url   = wp_get_attachment_url( $attachment_id );
				$requested_urls = array(
					home_url( '/?attachment_id=' . $attachment_id ),
					home_url( '/?p=' . $attachment_id ),
				);

				foreach ( $requested_urls as $requested_url ) {
					$this->assertSame(
						$redirect_url,
						bbp_do_not_redirect_restricted_attachments( $redirect_url, $requested_url )
					);
				}
			}
		} finally {
			$wp_query = $previous;
		}
	}

	/**
	 * @covers ::bbp_filter_oembed_request_attachment_id
	 * @covers ::get_oembed_response_data_for_url
	 */
	public function test_attachment_oembed_inherits_forum_visibility() {
		$user_id = $this->factory->user->create(
			array(
				'display_name' => 'Restricted uploader',
			)
		);
		$statuses = array(
			'publish'                         => true,
			bbp_get_private_status_id()       => false,
			bbp_get_hidden_status_id()        => false,
		);

		$this->set_current_user( 0 );

		foreach ( $statuses as $status => $expected ) {
			$forum_id = $this->factory->forum->create(
				array(
					'post_status' => $status,
				)
			);
			$topic_id = $this->factory->topic->create(
				array(
					'post_parent' => $forum_id,
					'topic_meta'  => array( 'forum_id' => $forum_id ),
				)
			);
			$reply_id = $this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'reply_meta'  => array(
						'forum_id' => $forum_id,
						'topic_id' => $topic_id,
					),
				)
			);

			foreach ( compact( 'forum_id', 'topic_id', 'reply_id' ) as $parent_id ) {
				$post_type     = get_post_type( $parent_id );
				$attachment_id = wp_insert_attachment(
					array(
						'post_author'    => $user_id,
						'post_title'     => 'Restricted ' . $post_type . ' attachment ' . $status,
						'post_status'    => 'inherit',
						'post_parent'    => $parent_id,
						'post_mime_type' => 'image/jpeg',
						'guid'           => home_url( '/restricted-' . $post_type . '-' . $status . '.jpg' ),
					)
				);
				$response = get_oembed_response_data_for_url(
					home_url( '/?attachment_id=' . $attachment_id ),
					array( 'width' => 600 )
				);

				if ( $expected ) {
					$this->assertNotFalse( $response );

					if ( $topic_id === $parent_id ) {
						$this->assertSame( 'Restricted ' . $post_type . ' attachment ' . $status, $response->title );
						$this->assertSame( 'Restricted uploader', $response->author_name );
					}
				} else {
					$this->assertFalse( $response );
				}
			}
		}
	}

	/**
	 * @covers ::bbp_filter_oembed_request_attachment_id
	 * @covers ::get_oembed_response_data_for_url
	 */
	public function test_attachment_oembed_inherits_ancestor_forum_visibility() {
		$user_id = $this->factory->user->create(
			array(
				'display_name' => 'Nested restricted uploader',
			)
		);
		$cases = array(
			'public ancestor'             => array( 'publish', '', true ),
			'private ancestor'            => array( bbp_get_private_status_id(), '', false ),
			'hidden ancestor'             => array( bbp_get_hidden_status_id(), '', false ),
			'password-protected ancestor' => array( 'publish', 'ancestor-secret', false ),
		);

		$this->set_current_user( 0 );

		foreach ( $cases as $case => $args ) {
			list( $status, $password, $can_embed ) = $args;

			$root_id = $this->factory->forum->create(
				array(
					'post_status'   => $status,
					'post_password' => $password,
				)
			);
			$ancestor_id = $this->factory->forum->create(
				array(
					'post_parent' => $root_id,
				)
			);
			$forum_id = $this->factory->forum->create(
				array(
					'post_parent' => $ancestor_id,
				)
			);
			$topic_id = $this->factory->topic->create(
				array(
					'post_parent' => $forum_id,
					'topic_meta'  => array( 'forum_id' => $forum_id ),
				)
			);
			$reply_id = $this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'reply_meta'  => array(
						'forum_id' => $forum_id,
						'topic_id' => $topic_id,
					),
				)
			);

			foreach ( compact( 'forum_id', 'topic_id', 'reply_id' ) as $parent_id ) {
				$post_type     = get_post_type( $parent_id );
				$attachment_id = wp_insert_attachment(
					array(
						'post_author'    => $user_id,
						'post_title'     => 'Nested ' . $post_type . ' attachment ' . $case,
						'post_status'    => 'inherit',
						'post_parent'    => $parent_id,
						'post_mime_type' => 'image/jpeg',
						'guid'           => home_url( '/nested-' . $post_type . '-' . sanitize_title( $case ) . '.jpg' ),
					)
				);
				$response = get_oembed_response_data_for_url(
					home_url( '/?attachment_id=' . $attachment_id ),
					array( 'width' => 600 )
				);

				if ( $can_embed ) {
					$this->assertNotFalse( $response );
				} else {
					$this->assertFalse( $response );
				}
			}
		}
	}

	/**
	 * @covers ::get_oembed_response_data_for_url
	 */
	public function test_hidden_forum_topic_has_no_oembed_response_for_visitor() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_title'  => 'Hidden embed marker',
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		bbp_hide_forum( $forum_id );
		$this->set_current_user( 0 );

		$url = home_url( '/?p=' . $topic_id );
		$this->assertFalse( get_oembed_response_data_for_url( $url, array( 'width' => 600 ) ) );
		$this->assertSame( '', bbp_redirect_canonical( get_permalink( $topic_id ), $url ) );

		bbp_publicize_forum( $forum_id, bbp_get_hidden_status_id() );
		$this->assertNotFalse( get_oembed_response_data_for_url( $url, array( 'width' => 600 ) ) );
		$this->assertSame( get_permalink( $topic_id ), bbp_redirect_canonical( get_permalink( $topic_id ), $url ) );
	}

	/**
	 * @covers ::bbp_get_reply_title_fallback
	 */
	public function test_reply_title_fallback_does_not_reveal_unreadable_topic_title() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_title'  => 'Unapproved topic marker',
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		$this->set_current_user( 0 );

		$this->assertStringNotContainsString( 'Unapproved topic marker', bbp_get_reply_title_fallback( '', $reply_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );
		wp_update_post( array( 'ID' => $reply_id, 'post_status' => bbp_get_public_status_id() ) );
		$this->assertStringContainsString( 'Unapproved topic marker', bbp_get_reply_title_fallback( '', $reply_id ) );
	}
}
