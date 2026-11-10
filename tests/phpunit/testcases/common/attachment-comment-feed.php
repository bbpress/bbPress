<?php

/**
 * Comment feeds for attachments to bbPress content.
 *
 * @group common
 */
class BBP_Tests_Common_Attachment_Comment_Feed extends BBP_UnitTestCase {

	private function create_attachment_comment( $parent_id, $date = '2020-01-01 00:00:00', $title = 'Attachment comment feed' ) {
		$attachment_id = wp_insert_attachment(
			array(
				'post_parent'    => $parent_id,
				'post_title'     => $title,
				'post_status'    => 'inherit',
				'comment_status' => 'open',
				'post_mime_type' => 'text/plain',
			)
		);

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $attachment_id,
				'comment_approved' => 1,
				'comment_date'     => $date,
				'comment_date_gmt' => $date,
			)
		);
	}

	private function create_topic( $forum_id ) {
		return $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
	}

	private function feed_comments( $args = array() ) {
		$query = new WP_Query( array_merge( array( 'feed' => 'comments-rss2' ), $args ) );

		return array_map( 'intval', wp_list_pluck( $query->comments, 'comment_ID' ) );
	}

	private function render_comment_feed( $query, $callback ) {
		$old_wp_query     = $GLOBALS['wp_query'];
		$old_wp_the_query = $GLOBALS['wp_the_query'];
		$old_post         = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_comment      = isset( $GLOBALS['comment'] ) ? $GLOBALS['comment'] : null;
		$buffer_level     = ob_get_level();
		$templates        = array(
			'do_feed_rdf'  => 'feed-rdf.php',
			'do_feed_rss'  => 'feed-rss.php',
			'do_feed_rss2' => 'feed-rss2-comments.php',
			'do_feed_atom' => 'feed-atom-comments.php',
		);
		$GLOBALS['wp_query']     = $query;
		$GLOBALS['wp_the_query'] = $query;
		$GLOBALS['post']         = $query->post;
		$GLOBALS['comment']      = $query->comment;
		$query->rewind_posts();
		$query->rewind_comments();

		$previous_error_handler = null;
		$previous_error_handler = set_error_handler(
			static function( $error_level, $message, $file, $line ) use ( &$previous_error_handler ) {
				if ( ( E_WARNING === $error_level ) && ( 0 === strpos( $message, 'Cannot modify header information' ) ) ) {
					return true;
				}

				return is_callable( $previous_error_handler )
					? call_user_func( $previous_error_handler, $error_level, $message, $file, $line )
					: false;
			},
			E_ALL
		);

		try {
			ob_start();
			load_template( ABSPATH . WPINC . '/' . $templates[ $callback ], false );
			$output = ob_get_clean();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			restore_error_handler();
			$GLOBALS['wp_query']     = $old_wp_query;
			$GLOBALS['wp_the_query'] = $old_wp_the_query;
			$GLOBALS['post']         = $old_post;
			$GLOBALS['comment']      = $old_comment;
		}

		return $output;
	}

	private function go_to_comment_feed( $url ) {
		$status = null;
		$filter = static function( $status_header, $code ) use ( &$status ) {
			$status = $code;

			return $status_header;
		};
		add_filter( 'status_header', $filter, 10, 2 );

		try {
			// Hooks are registered at bootstrap; avoid re-firing bbp_init per request.
			WP_UnitTestCase::go_to( $url );
		} finally {
			remove_filter( 'status_header', $filter, 10 );
		}

		return array( $status, $GLOBALS['wp_query'] );
	}

	public function test_comment_feed_query_helper_distinguishes_singular_queries() {
		$this->assertFalse( bbp_is_attachment_comment_feed_query( null, false ) );

		$collection = new WP_Query( array( 'feed' => 'comments-rss2' ) );
		$this->assertTrue( bbp_is_attachment_comment_feed_query( $collection, false ) );
		$this->assertFalse( bbp_is_attachment_comment_feed_query( $collection, true ) );

		$post_id  = self::factory()->post->create();
		$singular = new WP_Query( array( 'p' => $post_id, 'feed' => 'comments-rss2' ) );
		$this->assertFalse( bbp_is_attachment_comment_feed_query( $singular, false ) );
		$this->assertTrue( bbp_is_attachment_comment_feed_query( $singular, true ) );

		$ordinary = new WP_Query( array( 'p' => $post_id ) );
		$this->assertFalse( bbp_is_attachment_comment_feed_query( $ordinary, false ) );
		$this->assertFalse( bbp_is_attachment_comment_feed_query( $ordinary, true ) );
	}

	public function test_custom_feed_query_filters_comments_and_related_posts() {
		$this->set_current_user( 0 );
		$public          = $this->factory->forum->create();
		$hidden          = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$allowed_comment = (int) $this->create_attachment_comment( $this->create_topic( $public ) );
		$denied_comment  = (int) $this->create_attachment_comment( $this->create_topic( $hidden ) );
		$allowed_post    = (int) get_comment( $allowed_comment )->comment_post_ID;
		$denied_post     = (int) get_comment( $denied_comment )->comment_post_ID;
		$query           = new WP_Query( array( 'feed' => 'comments-rss2' ) );

		$this->assertSame( array( $allowed_comment ), array_map( 'intval', wp_list_pluck( $query->comments, 'comment_ID' ) ) );
		$this->assertSame( array( $allowed_post ), array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) ) );
		$this->assertSame( 1, $query->comment_count );
		$this->assertSame( 1, $query->post_count );
		$this->assertSame( $allowed_post, $query->post->ID );
		$this->assertNotContains( $denied_post, array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) ) );

		$id_query = new WP_Query( array( 'feed' => 'comments-rss2', 'fields' => 'ids' ) );
		$this->assertSame( array( $allowed_post ), array_map( 'intval', $id_query->posts ) );
	}

	public function test_comment_feed_templates_render_filtered_results() {
		$this->set_current_user( 0 );
		$public          = $this->factory->forum->create();
		$hidden          = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$allowed_comment = (int) $this->create_attachment_comment( $this->create_topic( $public ), '2020-01-01 00:00:00', 'Public feed attachment marker' );
		$denied_comment  = (int) $this->create_attachment_comment( $this->create_topic( $hidden ), '2020-02-01 00:00:00', 'Restricted feed attachment marker' );
		$allowed_post    = (int) get_comment( $allowed_comment )->comment_post_ID;
		$denied_post     = (int) get_comment( $denied_comment )->comment_post_ID;
		$query           = new WP_Query( array( 'feed' => 'comments-rss' ) );
		$this->assertTrue( $query->is_comment_feed() );
		$this->assertSame( array( $allowed_comment ), array_map( 'intval', wp_list_pluck( $query->comments, 'comment_ID' ) ) );
		$this->assertSame( array( $allowed_post ), array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) ) );
		$this->assertNotContains( $denied_post, array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) ) );

		foreach ( array( 'do_feed_rss', 'do_feed_rdf' ) as $callback ) {
			$output = $this->render_comment_feed( $query, $callback );
			$this->assertStringContainsString( 'Public feed attachment marker', $output );
			$this->assertStringNotContainsString( 'Restricted feed attachment marker', $output );
		}

		foreach ( array( 'do_feed_rss2', 'do_feed_atom' ) as $callback ) {
			$output = $this->render_comment_feed( $query, $callback );
			$this->assertStringContainsString( 'Public feed attachment marker', $output );
			$this->assertStringNotContainsString( 'Restricted feed attachment marker', $output );
		}
	}

	public function test_singular_comment_feed_templates_render_filtered_results() {
		$this->set_current_user( 0 );
		$public          = $this->factory->forum->create();
		$hidden          = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$allowed_comment = (int) $this->create_attachment_comment( $this->create_topic( $public ), '2020-01-01 00:00:00', 'Public singular attachment marker' );
		$denied_comment  = (int) $this->create_attachment_comment( $this->create_topic( $hidden ), '2020-02-01 00:00:00', 'Restricted singular attachment marker' );
		$allowed_post    = (int) get_comment( $allowed_comment )->comment_post_ID;
		$denied_post     = (int) get_comment( $denied_comment )->comment_post_ID;
		$allowed_query   = new WP_Query( array( 'attachment_id' => $allowed_post, 'feed' => 'comments-rss2' ) );
		$denied_query    = new WP_Query( array( 'attachment_id' => $denied_post, 'feed' => 'comments-rss2' ) );

		$this->assertSame( array( $allowed_comment ), array_map( 'intval', wp_list_pluck( $allowed_query->comments, 'comment_ID' ) ) );
		$this->assertSame( array(), $denied_query->comments );
		$this->assertSame( array(), $denied_query->posts );

		foreach ( array( 'do_feed_rss2', 'do_feed_atom' ) as $callback ) {
			$allowed_output = $this->render_comment_feed( $allowed_query, $callback );
			$denied_output  = $this->render_comment_feed( $denied_query, $callback );
			$this->assertStringContainsString( 'Public singular attachment marker', $allowed_output );
			$this->assertStringNotContainsString( 'Restricted singular attachment marker', $denied_output );
			$this->assertStringNotContainsString( '<item>', $denied_output );
			$this->assertStringNotContainsString( '<entry>', $denied_output );
		}
	}

	public function test_main_query_comment_feeds_filter_collection_and_singular_results() {
		$this->set_current_user( 0 );
		$public          = $this->factory->forum->create();
		$hidden          = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$allowed_comment = (int) $this->create_attachment_comment( $this->create_topic( $public ), '2020-01-01 00:00:00', 'Public main query marker' );
		$denied_comment  = (int) $this->create_attachment_comment( $this->create_topic( $hidden ), '2020-02-01 00:00:00', 'Restricted main query marker' );
		$allowed_post    = (int) get_comment( $allowed_comment )->comment_post_ID;
		$denied_post     = (int) get_comment( $denied_comment )->comment_post_ID;

		list( $status, $query ) = $this->go_to_comment_feed( home_url( '/?feed=comments-rss2' ) );
		$this->assertSame( 200, $status );
		$this->assertTrue( $query->is_main_query() );
		$this->assertSame( array( $allowed_comment ), array_map( 'intval', wp_list_pluck( $query->comments, 'comment_ID' ) ) );
		$output = $this->render_comment_feed( $query, 'do_feed_rss2' );
		$this->assertStringContainsString( 'Public main query marker', $output );
		$this->assertStringNotContainsString( 'Restricted main query marker', $output );

		list( $status, $query ) = $this->go_to_comment_feed( home_url( '/?attachment_id=' . $denied_post . '&feed=rss2' ) );
		do_action( 'template_redirect' );
		$this->assertSame( 200, $status );
		$this->assertTrue( $query->is_main_query() );
		$this->assertFalse( $query->is_singular() );
		$this->assertFalse( $query->is_attachment() );
		$this->assertFalse( $query->is_single() );
		$this->assertFalse( $query->is_404() );
		$this->assertTrue( $query->is_comment_feed() );
		$this->assertEmpty( $query->comments );
		$this->assertEmpty( $query->posts );
		$denied_output = $this->render_comment_feed( $query, 'do_feed_rss2' );
		$this->assertStringNotContainsString( 'Restricted main query marker', $denied_output );
		$this->assertStringNotContainsString( '<item>', $denied_output );

		list( $status, $query ) = $this->go_to_comment_feed( home_url( '/?attachment_id=999999999&feed=rss2' ) );
		do_action( 'template_redirect' );
		$this->assertSame( 200, $status );
		$this->assertTrue( $query->is_main_query() );
		$this->assertFalse( $query->is_singular() );
		$this->assertFalse( $query->is_attachment() );
		$this->assertFalse( $query->is_single() );
		$this->assertFalse( $query->is_404() );
		$this->assertTrue( $query->is_comment_feed() );
		$this->assertEmpty( $query->comments );
		$this->assertEmpty( $query->posts );
		$missing_output = $this->render_comment_feed( $query, 'do_feed_rss2' );
		$this->assertStringNotContainsString( '<item>', $missing_output );
		$this->assertSame( substr_count( $missing_output, '<item>' ), substr_count( $denied_output, '<item>' ) );

		foreach ( array( $denied_post, 999999999 ) as $attachment_id ) {
			list( $status, $query ) = $this->go_to_comment_feed( home_url( '/?post_type=attachment&p=' . $attachment_id . '&feed=rss2' ) );
			do_action( 'template_redirect' );
			$this->assertSame( 200, $status );
			$this->assertFalse( $query->is_singular() );
			$this->assertFalse( $query->is_attachment() );
			$this->assertFalse( $query->is_single() );
			$this->assertFalse( $query->is_404() );
			$this->assertTrue( $query->is_comment_feed() );
			$this->assertEmpty( $query->comments );
			$this->assertEmpty( $query->posts );
		}
	}

	public function test_anonymous_feed_excludes_restricted_attachments() {
		$this->set_current_user( 0 );
		$public  = $this->factory->forum->create();
		$hidden  = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$private = $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$locked  = $this->factory->forum->create( array( 'post_password' => 'feed-password' ) );
		$nested  = $this->factory->forum->create( array( 'post_parent' => $hidden ) );
		$topic   = $this->create_topic( $hidden );
		$reply   = $this->factory->reply->create( array( 'post_parent' => $topic ) );

		// The stored hierarchy takes precedence over missing or stale metadata.
		delete_post_meta( $topic, '_bbp_forum_id' );
		update_post_meta( $reply, '_bbp_forum_id', $public );
		update_post_meta( $reply, '_bbp_topic_id', $this->create_topic( $public ) );

		$allowed = $this->create_attachment_comment( $this->create_topic( $public ) );
		$denied  = array();

		foreach ( array( $hidden, $topic, $reply, $this->create_topic( $private ), $this->create_topic( $locked ), $this->create_topic( $nested ) ) as $parent_id ) {
			$denied[] = $this->create_attachment_comment( $parent_id );
		}

		foreach ( array( 'comments-rss2', 'comments-atom', 'comments-rss' ) as $feed ) {
			$comments = $this->feed_comments( array( 'feed' => $feed ) );
			$this->assertContains( $allowed, $comments );
			foreach ( $denied as $comment_id ) {
				$this->assertNotContains( $comment_id, $comments );
			}
		}
	}

	public function test_feed_filter_is_bounded_by_core_limit() {
		$this->set_current_user( 0 );
		$post_id = self::factory()->post->create();
		$allowed = $this->create_attachment_comment( $post_id );
		$hidden  = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$denied  = $this->create_attachment_comment( $this->create_topic( $hidden ), '2020-02-01 00:00:00' );
		$limit   = get_option( 'posts_per_rss' );
		update_option( 'posts_per_rss', 1 );

		try {
			$this->assertSame( array(), $this->feed_comments() );
			$this->assertSame( array(), $this->feed_comments( array( 'feed' => 'comments-atom' ) ) );
			update_option( 'posts_per_rss', 2 );
			$this->assertSame( array( $allowed ), $this->feed_comments() );
		} finally {
			update_option( 'posts_per_rss', $limit );
		}

		$this->assertNotNull( get_comment( $denied ) );
		$this->assertContains( $denied, array_map( 'intval', get_comments( array( 'fields' => 'ids' ) ) ) );
	}

	public function test_roles_do_not_share_cached_feed_permissions() {
		$private = $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$hidden  = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$private_comment = $this->create_attachment_comment( $this->create_topic( $private ) );
		$hidden_comment  = $this->create_attachment_comment( $this->create_topic( $hidden ) );

		foreach ( array( bbp_get_keymaster_role(), bbp_get_moderator_role(), bbp_get_participant_role(), '' ) as $role ) {
			$user_id = empty( $role ) ? 0 : self::factory()->user->create();
			if ( $user_id ) {
				bbp_set_user_role( $user_id, $role );
			}
			$this->set_current_user( $user_id );
			$comments = $this->feed_comments();
			$this->assertSame( current_user_can( 'read_private_forums' ), in_array( $private_comment, $comments, true ) );
			$this->assertSame( current_user_can( 'read_hidden_forums' ), in_array( $hidden_comment, $comments, true ) );
		}
	}

	public function test_password_cookie_controls_collection_and_singular_feeds() {
		$this->set_current_user( 0 );
		$forum_id    = $this->factory->forum->create( array( 'post_password' => 'feed-password' ) );
		$comment_id  = $this->create_attachment_comment( $this->create_topic( $forum_id ) );
		$comment     = get_comment( $comment_id );
		$cookie_name = 'wp-postpass_' . COOKIEHASH;
		$old_cookie  = isset( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : null;
		unset( $_COOKIE[ $cookie_name ] );

		try {
			$this->assertNotContains( $comment_id, $this->feed_comments() );
			$this->assertNotContains( $comment_id, $this->feed_comments( array( 'attachment_id' => $comment->comment_post_ID ) ) );
			require_once ABSPATH . WPINC . '/class-phpass.php';
			$hasher                  = new PasswordHash( 8, true );
			$_COOKIE[ $cookie_name ] = $hasher->HashPassword( 'feed-password' );
			$this->assertContains( $comment_id, $this->feed_comments() );
			$this->assertContains( $comment_id, $this->feed_comments( array( 'attachment_id' => $comment->comment_post_ID ) ) );
			unset( $_COOKIE[ $cookie_name ] );
			$this->assertNotContains( $comment_id, $this->feed_comments() );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie_name ] );
			} else {
				$_COOKIE[ $cookie_name ] = $old_cookie;
			}
		}
	}

	public function test_singular_attachment_comment_feed_uses_same_visibility() {
		$this->set_current_user( 0 );
		$hidden     = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$comment_id = $this->create_attachment_comment( $this->create_topic( $hidden ) );
		$comment    = get_comment( $comment_id );
		$this->assertNotContains( $comment_id, $this->feed_comments( array( 'attachment_id' => $comment->comment_post_ID ) ) );
	}

	public function test_visibility_change_does_not_reuse_public_feed_cache() {
		$this->set_current_user( 0 );
		$forum_id   = $this->factory->forum->create();
		$comment_id = $this->create_attachment_comment( $this->create_topic( $forum_id ) );
		$this->assertContains( $comment_id, $this->feed_comments() );
		bbp_hide_forum( $forum_id );
		$this->assertNotContains( $comment_id, $this->feed_comments() );
		bbp_publicize_forum( $forum_id );
		$this->assertContains( $comment_id, $this->feed_comments() );
	}

	public function test_non_public_parent_statuses_and_passwords_are_respected() {
		$this->set_current_user( 0 );
		$forum_id = $this->factory->forum->create();
		$denied   = array();

		foreach ( array( 'pending', 'draft', 'trash', bbp_get_spam_status_id() ) as $status ) {
			$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_status' => $status ) );
			$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
			$denied[] = $this->create_attachment_comment( $topic_id );
			$denied[] = $this->create_attachment_comment( $reply_id );
		}

		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_password' => 'topic-password' ) );
		$denied[] = $this->create_attachment_comment( $topic_id );
		$denied[] = $this->create_attachment_comment( $this->factory->reply->create( array( 'post_parent' => $topic_id ) ) );
		$unknown  = $this->factory->forum->create();
		$denied[] = $this->create_attachment_comment( $this->create_topic( $unknown ) );
		wp_update_post( array( 'ID' => $unknown, 'post_status' => 'draft' ) );

		$comments = $this->feed_comments();
		foreach ( $denied as $comment_id ) {
			$parent = bbp_get_attachment_parent( get_comment( $comment_id )->comment_post_ID );
			$this->assertNotContains( $comment_id, $comments, $parent->post_type . ':' . $parent->post_status . ':forum=' . bbp_get_topic_forum_id( $parent->ID ) );
		}
	}

	public function test_missing_stored_forum_parent_fails_closed() {
		$this->set_current_user( 0 );
		$hidden        = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$topic_id      = $this->create_topic( $hidden );
		$reply_id      = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$topic_comment = $this->create_attachment_comment( $topic_id );
		$reply_comment = $this->create_attachment_comment( $reply_id );

		wp_update_post( array( 'ID' => $topic_id, 'post_parent' => 0 ) );
		update_post_meta( $topic_id, '_bbp_forum_id', $hidden );

		$comments = $this->feed_comments();
		$this->assertNotContains( $topic_comment, $comments );
		$this->assertNotContains( $reply_comment, $comments );
		$this->assertFalse( bbp_user_can_view_attachment_parent( get_post( $topic_id ) ) );
		$this->assertFalse( bbp_user_can_view_attachment_parent( get_post( $reply_id ) ) );
	}

	public function test_search_and_archive_comment_feeds_use_same_visibility() {
		$this->set_current_user( 0 );
		$hidden  = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$denied  = $this->create_attachment_comment( $this->create_topic( $hidden ) );
		$allowed = $this->create_attachment_comment( self::factory()->post->create() );
		$queries = array(
			array( 'post_type' => 'attachment', 'post_status' => 'inherit', 's' => 'Attachment comment feed' ),
			array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'year' => (int) gmdate( 'Y' ) ),
		);

		foreach ( $queries as $args ) {
			$comments = $this->feed_comments( $args );
			$this->assertNotContains( $denied, $comments );
			$this->assertContains( $allowed, $comments );
		}
	}

	public function test_feed_filter_does_not_enumerate_historical_parents() {
		$this->set_current_user( 0 );
		$hidden = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$this->create_attachment_comment( $this->create_topic( $hidden ), '2020-02-01 00:00:00' );
		$public = $this->factory->forum->create();

		for ( $index = 0; $index < 25; ++$index ) {
			$this->create_attachment_comment( $this->create_topic( $public ) );
		}

		$queries = array();
		$capture = static function( $sql ) use ( &$queries ) {
			$queries[] = $sql;

			return $sql;
		};
		add_filter( 'query', $capture );

		try {
			$this->feed_comments();
		} finally {
			remove_filter( 'query', $capture );
		}

		$sql = implode( "\n", $queries );
		$this->assertStringNotContainsString( 'SELECT DISTINCT parent.ID', $sql );
		$this->assertStringNotContainsString( 'bbp_feed_attachment', $sql );
	}

	public function test_repeated_collection_feed_avoids_unbounded_queries() {
		$this->set_current_user( 0 );
		$forum_id = $this->factory->forum->create();
		$this->create_attachment_comment( $this->create_topic( $forum_id ) );
		$queries = array();
		$capture = static function( $sql ) use ( &$queries ) {
			if ( false !== strpos( $sql, 'comment_ID FROM' ) ) {
				$queries[] = $sql;
			}

			return $sql;
		};
		add_filter( 'query', $capture );

		try {
			$this->feed_comments();
			$this->feed_comments();
		} finally {
			remove_filter( 'query', $capture );
		}

		if ( version_compare( $GLOBALS['wp_version'], '6.1', '>=' ) ) {
			$this->assertCount( 1, $queries );
		}

		$sql = implode( "\n", $queries );
		$this->assertStringNotContainsString( 'SELECT DISTINCT parent.ID', $sql );
		$this->assertStringNotContainsString( 'bbp_feed_attachment', $sql );
	}

	public function test_comment_approval_and_attachment_reparenting_are_rechecked() {
		$this->set_current_user( 0 );
		$forum_id   = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$topic_id   = $this->create_topic( $forum_id );
		$comment_id = $this->create_attachment_comment( self::factory()->post->create() );
		$comment    = get_comment( $comment_id );
		$this->assertContains( $comment_id, $this->feed_comments() );
		wp_update_post( array( 'ID' => $comment->comment_post_ID, 'post_parent' => $topic_id ) );
		$this->assertNotContains( $comment_id, $this->feed_comments() );
		wp_set_comment_status( $comment_id, 'hold' );
		$this->assertNotContains( $comment_id, $this->feed_comments() );
		wp_set_comment_status( $comment_id, 'approve' );
		$this->assertNotContains( $comment_id, $this->feed_comments() );
		wp_update_post( array( 'ID' => $comment->comment_post_ID, 'post_parent' => 0 ) );
		$this->assertContains( $comment_id, $this->feed_comments() );
	}

	public function test_normal_posts_comments_and_privileged_edit_queries_are_unchanged() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
		$this->set_current_user( $admin_id );
		$forum_id   = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$topic_id   = $this->create_topic( $forum_id );
		$comment_id = $this->create_attachment_comment( $topic_id );
		$invalid    = $this->factory->forum->create();
		$child      = $this->factory->forum->create( array( 'post_parent' => $invalid ) );
		$invalid_comment = $this->create_attachment_comment( $this->create_topic( $child ) );
		wp_update_post( array( 'ID' => $invalid, 'post_status' => 'draft' ) );
		$post_id    = self::factory()->post->create();
		$ordinary   = self::factory()->comment->create( array( 'comment_post_ID' => $post_id, 'comment_approved' => 1 ) );
		$dangling   = $this->create_attachment_comment( 0 );
		$dangling_attachment = get_comment( $dangling )->comment_post_ID;
		wp_update_post( array( 'ID' => $dangling_attachment, 'post_parent' => 999999999 ) );
		$this->assertContains( $comment_id, $this->feed_comments() );
		$this->assertNotContains( $invalid_comment, $this->feed_comments() );
		$this->assertContains( $ordinary, $this->feed_comments() );
		$this->assertContains( $dangling, $this->feed_comments() );
		$this->assertTrue( current_user_can( 'edit_comment', $comment_id ) );
		$this->assertTrue( (bool) wp_update_comment( array( 'comment_ID' => $comment_id, 'comment_content' => 'Edited comment' ) ) );
		$this->set_current_user( 0 );
		$query = new WP_Query( array( 'p' => $post_id ) );
		$this->assertSame( $post_id, $query->post->ID );
		$this->assertContains( $ordinary, $this->feed_comments() );
		$this->assertSame( 'Edited comment', get_comment( $comment_id )->comment_content );
	}

	/**
	 * @group ms-required
	 */
	public function test_switched_blog_uses_its_own_attachment_parents() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$this->set_current_user( 0 );
		$allowed = $this->create_attachment_comment( self::factory()->post->create() );
		$this->assertContains( $allowed, $this->feed_comments() );
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );

		try {
			update_option( 'posts_per_rss', 10 );
			$blog_allowed = $this->create_attachment_comment( self::factory()->post->create() );
			$hidden       = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
			$denied       = $this->create_attachment_comment( $this->create_topic( $hidden ) );
			$comments     = $this->feed_comments();
			$this->assertContains( $blog_allowed, $comments );
			$this->assertNotContains( $denied, $comments );
		} finally {
			restore_current_blog();
		}

		$this->assertContains( $allowed, $this->feed_comments() );
	}
}
