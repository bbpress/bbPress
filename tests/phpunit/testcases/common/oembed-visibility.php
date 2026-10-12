<?php

/**
 * oEmbed must respect bbPress visibility.
 *
 * @group common
 * @group visibility
 */
class BBP_Tests_Common_Oembed_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_filter_oembed_request_post_id
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
	 * @covers ::bbp_filter_oembed_request_post_id
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
		$this->assertFalse( apply_filters( 'redirect_canonical', get_permalink( $topic_id ), $url ) );

		bbp_publicize_forum( $forum_id, bbp_get_hidden_status_id() );
		$this->assertNotFalse( get_oembed_response_data_for_url( $url, array( 'width' => 600 ) ) );
		$this->assertSame( get_permalink( $topic_id ), apply_filters( 'redirect_canonical', get_permalink( $topic_id ), $url ) );
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
