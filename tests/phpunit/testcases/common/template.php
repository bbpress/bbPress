<?php

/**
 * Tests for common template functions.
 */
class BBP_Tests_Common_Template extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forums_url
	 * @covers ::bbp_get_forums_url
	 */
	public function test_forums_url_returns_and_outputs_escaped_url() {
		$path     = '/page/2/?first=1&second="quoted"';
		$slug     = function() {
			return 'discussion-board';
		};
		add_filter( 'bbp_get_root_slug', $slug );
		$expected = home_url( 'discussion-board' . $path );
		$escaped  = esc_url( $expected );

		try {
			$this->assertSame( home_url( 'discussion-board/' ), bbp_get_forums_url() );
			$this->assertSame( $expected, bbp_get_forums_url( $path ) );
			$this->assertNotSame( $expected, $escaped );
			$this->assertStringContainsString( '&#038;', $escaped );
			$this->assertStringNotContainsString( '"', $escaped );
			$this->expectOutputString( $escaped );
			bbp_forums_url( $path );
		} finally {
			remove_filter( 'bbp_get_root_slug', $slug );
		}
	}

	/**
	 * @covers ::bbp_topics_url
	 * @covers ::bbp_get_topics_url
	 */
	public function test_topics_url_returns_and_outputs_escaped_url() {
		$path     = '/page/3/?first=1&second="quoted"';
		$slug     = function() {
			return 'discussions';
		};
		add_filter( 'bbp_get_topic_archive_slug', $slug );
		$expected = home_url( 'discussions' . $path );
		$escaped  = esc_url( $expected );

		try {
			$this->assertSame( home_url( 'discussions/' ), bbp_get_topics_url() );
			$this->assertSame( $expected, bbp_get_topics_url( $path ) );
			$this->assertNotSame( $expected, $escaped );
			$this->assertStringContainsString( '&#038;', $escaped );
			$this->assertStringNotContainsString( '"', $escaped );
			$this->expectOutputString( $escaped );
			bbp_topics_url( $path );
		} finally {
			remove_filter( 'bbp_get_topic_archive_slug', $slug );
		}
	}

	/**
	 * @covers ::bbp_head
	 */
	public function test_head_runs_extension_action() {
		$called   = 0;
		$level    = ob_get_level();
		$callback = function() use ( &$called ) {
			$this->assertSame( 'bbp_head', current_filter() );
			++$called;
		};
		add_action( 'bbp_head', $callback );
		ob_start();

		try {
			bbp_head();
			$this->assertSame( 1, $called );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_action( 'bbp_head', $callback );
		}
	}

	/**
	 * @covers ::bbp_footer
	 */
	public function test_footer_runs_extension_action() {
		$called   = 0;
		$level    = ob_get_level();
		$callback = function() use ( &$called ) {
			$this->assertSame( 'bbp_footer', current_filter() );
			++$called;
		};
		add_action( 'bbp_footer', $callback );
		ob_start();

		try {
			bbp_footer();
			$this->assertSame( 1, $called );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_action( 'bbp_footer', $callback );
		}
	}

	/**
	 * @covers ::bbp_is_site_public
	 */
	public function test_is_site_public_uses_current_site_option_and_filter() {
		$site_id    = get_current_blog_id();
		$old_public = get_option( 'blog_public', 1 );
		$filter     = function( $public, $filtered_site_id ) use ( $site_id ) {
			$this->assertSame( 1, (int) $public );
			$this->assertSame( $site_id, $filtered_site_id );
			return false;
		};

		try {
			update_option( 'blog_public', 0 );
			$this->assertFalse( bbp_is_site_public() );

			update_option( 'blog_public', 1 );
			$this->assertTrue( bbp_is_site_public( $site_id ) );

			add_filter( 'bbp_is_site_public', $filter, 10, 2 );
			$this->assertFalse( bbp_is_site_public() );
		} finally {
			remove_filter( 'bbp_is_site_public', $filter, 10 );
			update_option( 'blog_public', $old_public );
		}
	}

	/**
	 * @covers ::bbp_is_site_public
	 * @group multisite
	 */
	public function test_is_site_public_uses_supplied_site_id() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$current_site_id = get_current_blog_id();
		$other_site_id   = self::factory()->blog->create();
		$old_public      = get_blog_option( $current_site_id, 'blog_public', 1 );
		$filtered_id     = null;
		$filter          = function( $public, $site_id ) use ( &$filtered_id ) {
			$filtered_id = $site_id;
			return $public;
		};

		update_blog_option( $current_site_id, 'blog_public', 1 );
		update_blog_option( $other_site_id, 'blog_public', 0 );
		add_filter( 'bbp_is_site_public', $filter, 10, 2 );

		try {
			$this->assertFalse( bbp_is_site_public( $other_site_id ) );
			$this->assertSame( $other_site_id, $filtered_id );

			switch_to_blog( $other_site_id );
			$this->assertFalse( bbp_is_site_public() );
			$this->assertSame( $other_site_id, $filtered_id );
		} finally {
			if ( get_current_blog_id() !== $current_site_id ) {
				restore_current_blog();
			}
			remove_filter( 'bbp_is_site_public', $filter, 10 );
			update_blog_option( $current_site_id, 'blog_public', $old_public );
		}
	}

	/**
	 * @covers ::bbp_is_forum
	 * @covers ::bbp_is_topic
	 * @covers ::bbp_is_reply
	 */
	public function test_object_type_predicates_identify_bbpress_posts() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$post_id  = $this->factory->post->create();

		$this->assertTrue( bbp_is_forum( $forum_id ) );
		$this->assertTrue( bbp_is_forum( (string) $forum_id ) );
		$this->assertFalse( bbp_is_forum( $topic_id ) );
		$this->assertFalse( bbp_is_forum( $post_id ) );
		$this->assertFalse( bbp_is_forum( 0 ) );
		$this->assertFalse( bbp_is_forum( PHP_INT_MAX ) );

		$this->assertTrue( bbp_is_topic( $topic_id ) );
		$this->assertTrue( bbp_is_topic( (string) $topic_id ) );
		$this->assertFalse( bbp_is_topic( $reply_id ) );
		$this->assertFalse( bbp_is_topic( $post_id ) );
		$this->assertFalse( bbp_is_topic( 0 ) );
		$this->assertFalse( bbp_is_topic( PHP_INT_MAX ) );

		$this->assertTrue( bbp_is_reply( $reply_id ) );
		$this->assertTrue( bbp_is_reply( (string) $reply_id ) );
		$this->assertFalse( bbp_is_reply( $forum_id ) );
		$this->assertFalse( bbp_is_reply( $post_id ) );
		$this->assertFalse( bbp_is_reply( 0 ) );
		$this->assertFalse( bbp_is_reply( PHP_INT_MAX ) );
	}

	/**
	 * @covers ::bbp_is_forum
	 * @covers ::bbp_is_topic
	 * @covers ::bbp_is_reply
	 * @dataProvider object_type_predicate_provider
	 */
	public function test_object_type_predicate_filters( $function, $factory_name ) {
		$post_id = $this->factory->post->create();
		$called  = 0;
		$filter_name = $function;
		$filter  = function( $retval, $filtered_post_id ) use ( &$called, $post_id ) {
			++$called;
			$this->assertFalse( $retval );
			$this->assertSame( $post_id, $filtered_post_id );
			return true;
		};
		add_filter( $filter_name, $filter, 10, 2 );

		try {
			$this->assertTrue( call_user_func( $function, $post_id ) );
			$this->assertSame( 1, $called );
		} finally {
			remove_filter( $filter_name, $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_is_forum
	 * @covers ::bbp_is_topic
	 * @covers ::bbp_is_reply
	 * @dataProvider object_type_predicate_provider
	 */
	public function test_object_type_predicate_filters_can_reject_match( $function, $factory_name ) {
		$post_id     = $this->create_bbp_post( $factory_name );
		$filter_name = $function;
		$filter  = function( $retval, $filtered_post_id ) use ( $post_id ) {
			$this->assertTrue( $retval );
			$this->assertSame( $post_id, $filtered_post_id );
			return false;
		};
		add_filter( $filter_name, $filter, 10, 2 );

		try {
			$this->assertFalse( call_user_func( $function, $post_id ) );
		} finally {
			remove_filter( $filter_name, $filter, 10 );
		}
	}

	public static function object_type_predicate_provider() {
		return array(
			'forum' => array( 'bbp_is_forum', 'forum' ),
			'topic' => array( 'bbp_is_topic', 'topic' ),
			'reply' => array( 'bbp_is_reply', 'reply' ),
		);
	}

	/**
	 * @covers ::bbp_is_favorites
	 * @covers ::bbp_is_subscriptions
	 * @covers ::bbp_is_topics_created
	 * @covers ::bbp_is_replies_created
	 * @covers ::bbp_is_user_home
	 * @covers ::bbp_is_single_user
	 * @covers ::bbp_is_single_user_edit
	 * @covers ::bbp_is_single_user_profile
	 * @covers ::bbp_is_single_user_topics
	 * @covers ::bbp_is_single_user_replies
	 * @covers ::bbp_is_single_user_engagements
	 * @covers ::bbp_is_single_view
	 * @covers ::bbp_is_edit
	 * @covers ::bbp_is_forum_edit
	 * @covers ::bbp_is_topic_edit
	 * @covers ::bbp_is_reply_edit
	 * @dataProvider query_flag_predicate_provider
	 */
	public function test_query_flag_predicates_require_strict_true_and_apply_filter( $function, $property ) {
		$wp_query   = bbp_get_wp_query();
		$had_value  = property_exists( $wp_query, $property );
		$old_value  = $had_value ? $wp_query->{$property} : null;
		$old_name   = bbp_get_query_name();
		$filter     = function( $retval ) {
			$this->assertTrue( $retval );
			return false;
		};
		$override   = function( $retval ) {
			$this->assertFalse( $retval );
			return true;
		};

		try {
			bbp_reset_query_name();
			unset( $wp_query->{$property} );
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$property} = 1;
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$property} = false;
			add_filter( $function, $override );
			$this->assertTrue( call_user_func( $function ) );
			remove_filter( $function, $override );

			$wp_query->{$property} = true;
			$this->assertTrue( call_user_func( $function ) );

			add_filter( $function, $filter );
			$this->assertFalse( call_user_func( $function ) );
		} finally {
			remove_filter( $function, $filter );
			remove_filter( $function, $override );
			bbp_set_query_name( $old_name );
			if ( $had_value ) {
				$wp_query->{$property} = $old_value;
			} else {
				unset( $wp_query->{$property} );
			}
		}
	}

	public static function query_flag_predicate_provider() {
		return array(
			'favorites'               => array( 'bbp_is_favorites', 'bbp_is_single_user_favs' ),
			'subscriptions'           => array( 'bbp_is_subscriptions', 'bbp_is_single_user_subs' ),
			'topics created'          => array( 'bbp_is_topics_created', 'bbp_is_single_user_topics' ),
			'replies created'         => array( 'bbp_is_replies_created', 'bbp_is_single_user_replies' ),
			'user home'               => array( 'bbp_is_user_home', 'bbp_is_single_user_home' ),
			'single user'             => array( 'bbp_is_single_user', 'bbp_is_single_user' ),
			'single user edit'        => array( 'bbp_is_single_user_edit', 'bbp_is_single_user_edit' ),
			'single user profile'     => array( 'bbp_is_single_user_profile', 'bbp_is_single_user_profile' ),
			'single user topics'      => array( 'bbp_is_single_user_topics', 'bbp_is_single_user_topics' ),
			'single user replies'     => array( 'bbp_is_single_user_replies', 'bbp_is_single_user_replies' ),
			'single user engagements' => array( 'bbp_is_single_user_engagements', 'bbp_is_single_user_engagements' ),
			'single view'             => array( 'bbp_is_single_view', 'bbp_is_view' ),
			'edit'                    => array( 'bbp_is_edit', 'bbp_is_edit' ),
			'forum edit'              => array( 'bbp_is_forum_edit', 'bbp_is_forum_edit' ),
			'topic edit'              => array( 'bbp_is_topic_edit', 'bbp_is_topic_edit' ),
			'reply edit'              => array( 'bbp_is_reply_edit', 'bbp_is_reply_edit' ),
		);
	}

	/**
	 * @covers ::bbp_get_query_name
	 * @covers ::bbp_is_query_name
	 * @covers ::bbp_reset_query_name
	 * @covers ::bbp_set_query_name
	 */
	public function test_query_name_helpers_set_compare_and_reset_value() {
		$old_name = bbp_get_query_name();

		try {
			bbp_reset_query_name();
			$this->assertSame( '', bbp_get_query_name() );
			$this->assertTrue( bbp_is_query_name() );

			bbp_set_query_name( 'bbp_unit_test' );
			$this->assertSame( 'bbp_unit_test', bbp_get_query_name() );
			$this->assertTrue( bbp_is_query_name( 'bbp_unit_test' ) );
			$this->assertFalse( bbp_is_query_name( 'bbp_other' ) );
		} finally {
			bbp_set_query_name( $old_name );
		}
	}

	/**
	 * @covers ::bbp_is_forum_archive
	 * @covers ::bbp_is_single_forum
	 * @covers ::bbp_is_single_topic
	 * @covers ::bbp_is_topic_archive
	 * @covers ::bbp_is_single_reply
	 * @covers ::bbp_is_single_view
	 * @dataProvider query_name_predicate_provider
	 */
	public function test_query_name_predicates( $function, $query_name ) {
		$old_name = bbp_get_query_name();

		try {
			bbp_reset_query_name();
			$this->assertFalse( call_user_func( $function ) );

			bbp_set_query_name( $query_name );
			$this->assertTrue( call_user_func( $function ) );
		} finally {
			bbp_set_query_name( $old_name );
		}
	}

	public static function query_name_predicate_provider() {
		return array(
			'forum archive' => array( 'bbp_is_forum_archive', 'bbp_forum_archive' ),
			'single forum'  => array( 'bbp_is_single_forum', 'bbp_single_forum' ),
			'single topic'  => array( 'bbp_is_single_topic', 'bbp_single_topic' ),
			'topic archive' => array( 'bbp_is_topic_archive', 'bbp_topic_archive' ),
			'single reply'  => array( 'bbp_is_single_reply', 'bbp_single_reply' ),
			'single view'   => array( 'bbp_is_single_view', 'bbp_single_view' ),
		);
	}

	/**
	 * @covers ::bbp_is_single_forum
	 * @covers ::bbp_is_single_topic
	 * @covers ::bbp_is_single_reply
	 * @dataProvider single_predicate_edit_provider
	 */
	public function test_single_predicates_exclude_edit_state( $function, $query_name, $edit_property ) {
		$wp_query  = bbp_get_wp_query();
		$old_name  = bbp_get_query_name();
		$had_value = property_exists( $wp_query, $edit_property );
		$old_value = $had_value ? $wp_query->{$edit_property} : null;

		try {
			bbp_set_query_name( $query_name );
			$wp_query->{$edit_property} = true;
			$this->assertFalse( call_user_func( $function ) );
		} finally {
			bbp_set_query_name( $old_name );
			if ( $had_value ) {
				$wp_query->{$edit_property} = $old_value;
			} else {
				unset( $wp_query->{$edit_property} );
			}
		}
	}

	public static function single_predicate_edit_provider() {
		return array(
			'forum' => array( 'bbp_is_single_forum', 'bbp_single_forum', 'bbp_is_forum_edit' ),
			'topic' => array( 'bbp_is_single_topic', 'bbp_single_topic', 'bbp_is_topic_edit' ),
			'reply' => array( 'bbp_is_single_reply', 'bbp_single_reply', 'bbp_is_reply_edit' ),
		);
	}

	/**
	 * @covers ::bbp_is_single_forum
	 * @covers ::bbp_is_single_topic
	 * @covers ::bbp_is_single_reply
	 * @dataProvider singular_predicate_provider
	 */
	public function test_single_predicates_exclude_edit_state_for_singular_query( $function, $factory_name, $edit_property ) {
		$post_id  = $this->create_bbp_post( $factory_name );
		$wp_query = bbp_get_wp_query();
		$states   = array(
			'is_singular'       => $wp_query->is_singular,
			'queried_object'    => property_exists( $wp_query, 'queried_object' ) ? $wp_query->queried_object : null,
			'queried_object_id' => property_exists( $wp_query, 'queried_object_id' ) ? $wp_query->queried_object_id : null,
			$edit_property      => property_exists( $wp_query, $edit_property ) ? $wp_query->{$edit_property} : null,
		);
		$existing = array(
			'queried_object'    => property_exists( $wp_query, 'queried_object' ),
			'queried_object_id' => property_exists( $wp_query, 'queried_object_id' ),
			$edit_property      => property_exists( $wp_query, $edit_property ),
		);

		try {
			$wp_query->is_singular       = true;
			$wp_query->queried_object    = get_post( $post_id );
			$wp_query->queried_object_id = $post_id;
			$wp_query->{$edit_property}  = false;
			$this->assertTrue( call_user_func( $function ) );

			$wp_query->{$edit_property} = true;
			$this->assertFalse( call_user_func( $function ) );
		} finally {
			$wp_query->is_singular = $states['is_singular'];
			foreach ( $existing as $property => $had_value ) {
				if ( $had_value ) {
					$wp_query->{$property} = $states[ $property ];
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	public static function singular_predicate_provider() {
		return array(
			'forum' => array( 'bbp_is_single_forum', 'forum', 'bbp_is_forum_edit' ),
			'topic' => array( 'bbp_is_single_topic', 'topic', 'bbp_is_topic_edit' ),
			'reply' => array( 'bbp_is_single_reply', 'reply', 'bbp_is_reply_edit' ),
		);
	}

	/**
	 * @covers ::bbp_is_user_home_edit
	 */
	public function test_user_home_edit_requires_both_query_flags() {
		$wp_query = bbp_get_wp_query();
		$states   = array(
			'bbp_is_single_user_home' => property_exists( $wp_query, 'bbp_is_single_user_home' ) ? $wp_query->bbp_is_single_user_home : null,
			'bbp_is_single_user_edit' => property_exists( $wp_query, 'bbp_is_single_user_edit' ) ? $wp_query->bbp_is_single_user_edit : null,
		);
		$existing = array(
			'bbp_is_single_user_home' => property_exists( $wp_query, 'bbp_is_single_user_home' ),
			'bbp_is_single_user_edit' => property_exists( $wp_query, 'bbp_is_single_user_edit' ),
		);

		try {
			$wp_query->bbp_is_single_user_home = true;
			$wp_query->bbp_is_single_user_edit = false;
			$this->assertFalse( bbp_is_user_home_edit() );

			$wp_query->bbp_is_single_user_home = false;
			$wp_query->bbp_is_single_user_edit = true;
			$this->assertFalse( bbp_is_user_home_edit() );

			$wp_query->bbp_is_single_user_home = true;
			$wp_query->bbp_is_single_user_edit = true;
			$this->assertTrue( bbp_is_user_home_edit() );
		} finally {
			foreach ( $states as $property => $value ) {
				if ( $existing[ $property ] ) {
					$wp_query->{$property} = $value;
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	/**
	 * @covers ::bbp_is_topic_merge
	 * @covers ::bbp_is_topic_split
	 * @covers ::bbp_is_reply_move
	 * @dataProvider edit_action_predicate_provider
	 */
	public function test_edit_action_predicates_require_edit_state_and_matching_action( $function, $edit_property, $action, $other_action, $other_property ) {
		$wp_query     = bbp_get_wp_query();
		$old_get      = $_GET;
		$filter_added = false;
		$states       = array(
			$edit_property  => property_exists( $wp_query, $edit_property ) ? $wp_query->{$edit_property} : null,
			$other_property => property_exists( $wp_query, $other_property ) ? $wp_query->{$other_property} : null,
		);
		$existing = array(
			$edit_property  => property_exists( $wp_query, $edit_property ),
			$other_property => property_exists( $wp_query, $other_property ),
		);

		try {
			$wp_query->{$edit_property}  = true;
			$wp_query->{$other_property} = false;
			unset( $_GET['action'] );
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$edit_property} = false;
			$_GET['action'] = $action;
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$edit_property} = true;
			$_GET['action'] = $other_action;
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$edit_property}  = false;
			$wp_query->{$other_property} = true;
			$_GET['action']              = $action;
			$this->assertFalse( call_user_func( $function ) );

			$wp_query->{$edit_property}  = true;
			$wp_query->{$other_property} = false;
			$_GET['action'] = $action;
			$this->assertTrue( call_user_func( $function ) );

			add_filter( $function, '__return_false' );
			$filter_added = true;
			$this->assertFalse( call_user_func( $function ) );
		} finally {
			if ( $filter_added ) {
				remove_filter( $function, '__return_false' );
			}
			$_GET = $old_get;
			foreach ( $states as $property => $value ) {
				if ( $existing[ $property ] ) {
					$wp_query->{$property} = $value;
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	public static function edit_action_predicate_provider() {
		return array(
			'topic merge' => array( 'bbp_is_topic_merge', 'bbp_is_topic_edit', 'merge', 'split', 'bbp_is_reply_edit' ),
			'topic split' => array( 'bbp_is_topic_split', 'bbp_is_topic_edit', 'split', 'merge', 'bbp_is_reply_edit' ),
			'reply move'  => array( 'bbp_is_reply_move', 'bbp_is_reply_edit', 'move', 'merge', 'bbp_is_topic_edit' ),
		);
	}

	/**
	 * @covers ::bbp_is_topic_tag
	 * @covers ::bbp_is_topic_tag_edit
	 */
	public function test_topic_tag_predicates_use_query_state_and_respect_feature_setting() {
		$wp_query      = bbp_get_wp_query();
		$query_var     = 'bbp_topic_tag';
		$edit_property = 'bbp_is_topic_tag_edit';
		$had_query_var = array_key_exists( $query_var, $wp_query->query_vars );
		$old_query_var = $had_query_var ? $wp_query->query_vars[ $query_var ] : null;
		$had_edit      = property_exists( $wp_query, $edit_property );
		$old_edit      = $had_edit ? $wp_query->{$edit_property} : null;

		add_filter( 'bbp_allow_topic_tags', '__return_true' );

		try {
			$wp_query->set( $query_var, '' );
			$wp_query->{$edit_property} = false;
			$this->assertFalse( bbp_is_topic_tag() );
			$this->assertFalse( bbp_is_topic_tag_edit() );

			$wp_query->set( $query_var, 'unit-test-tag' );
			$this->assertTrue( bbp_is_topic_tag() );
			add_filter( 'bbp_is_topic_tag', '__return_false' );
			$this->assertFalse( bbp_is_topic_tag() );
			remove_filter( 'bbp_is_topic_tag', '__return_false' );

			$wp_query->{$edit_property} = 1;
			$this->assertFalse( bbp_is_topic_tag_edit() );

			$wp_query->{$edit_property} = true;
			$this->assertTrue( bbp_is_topic_tag_edit() );
			add_filter( 'bbp_is_topic_tag_edit', '__return_false' );
			$this->assertFalse( bbp_is_topic_tag_edit() );
			remove_filter( 'bbp_is_topic_tag_edit', '__return_false' );
			$this->assertFalse( bbp_is_topic_tag() );

			add_filter( 'bbp_allow_topic_tags', '__return_false', 20 );
			$this->assertFalse( bbp_is_topic_tag() );
			$this->assertFalse( bbp_is_topic_tag_edit() );
		} finally {
			remove_filter( 'bbp_is_topic_tag_edit', '__return_false' );
			remove_filter( 'bbp_is_topic_tag', '__return_false' );
			remove_filter( 'bbp_allow_topic_tags', '__return_false', 20 );
			remove_filter( 'bbp_allow_topic_tags', '__return_true' );
			if ( $had_query_var ) {
				$wp_query->set( $query_var, $old_query_var );
			} else {
				unset( $wp_query->query_vars[ $query_var ] );
			}
			if ( $had_edit ) {
				$wp_query->{$edit_property} = $old_edit;
			} else {
				unset( $wp_query->{$edit_property} );
			}
		}
	}

	/**
	 * @covers ::bbp_is_topic_tag
	 * @covers ::bbp_is_topic_tag_edit
	 */
	public function test_topic_tag_predicate_filters_can_override_query_state() {
		$tag_filter  = '__return_true';
		$edit_filter = '__return_true';

		add_filter( 'bbp_allow_topic_tags', '__return_true' );
		add_filter( 'bbp_is_topic_tag', $tag_filter );

		try {
			$this->assertTrue( bbp_is_topic_tag() );
			remove_filter( 'bbp_is_topic_tag', $tag_filter );
			add_filter( 'bbp_is_topic_tag_edit', $edit_filter );
			$this->assertTrue( bbp_is_topic_tag_edit() );
		} finally {
			remove_filter( 'bbp_is_topic_tag_edit', $edit_filter );
			remove_filter( 'bbp_is_topic_tag', $tag_filter );
			remove_filter( 'bbp_allow_topic_tags', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_is_search
	 * @covers ::bbp_is_search_results
	 */
	public function test_search_predicates_use_query_name_query_state_and_request() {
		$wp_query       = bbp_get_wp_query();
		$rewrite_id     = bbp_get_search_rewrite_id();
		$old_name       = bbp_get_query_name();
		$old_request    = $_REQUEST;
		$search_states  = array(
			'bbp_is_search'    => property_exists( $wp_query, 'bbp_is_search' ) ? $wp_query->bbp_is_search : null,
			'bbp_search_terms' => property_exists( $wp_query, 'bbp_search_terms' ) ? $wp_query->bbp_search_terms : null,
		);
		$search_exists  = array(
			'bbp_is_search'    => property_exists( $wp_query, 'bbp_is_search' ),
			'bbp_search_terms' => property_exists( $wp_query, 'bbp_search_terms' ),
		);

		add_filter( 'bbp_allow_search', '__return_true' );

		try {
			bbp_reset_query_name();
			$wp_query->bbp_is_search    = false;
			$wp_query->bbp_search_terms = '';
			unset( $_REQUEST[ $rewrite_id ] );
			$this->assertFalse( bbp_is_search() );
			$this->assertFalse( bbp_is_search_results() );

			$wp_query->bbp_is_search = 1;
			$this->assertFalse( bbp_is_search() );

			$wp_query->bbp_is_search = true;
			$this->assertTrue( bbp_is_search() );

			$wp_query->bbp_is_search = false;
			bbp_set_query_name( $rewrite_id );
			$this->assertTrue( bbp_is_search() );

			bbp_reset_query_name();
			$_REQUEST[ $rewrite_id ] = '';
			$this->assertTrue( bbp_is_search() );

			unset( $_REQUEST[ $rewrite_id ] );
			$wp_query->bbp_search_terms = 'query terms';
			$this->assertTrue( bbp_is_search_results() );

			$wp_query->bbp_search_terms = '';
			bbp_set_query_name( 'bbp_search_results' );
			$this->assertTrue( bbp_is_search_results() );

			bbp_reset_query_name();
			$_REQUEST[ $rewrite_id ] = 'request terms';
			$this->assertTrue( bbp_is_search_results() );
		} finally {
			remove_filter( 'bbp_allow_search', '__return_true' );
			bbp_set_query_name( $old_name );
			$_REQUEST = $old_request;
			foreach ( $search_states as $property => $value ) {
				if ( $search_exists[ $property ] ) {
					$wp_query->{$property} = $value;
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	/**
	 * @covers ::bbp_is_search
	 * @covers ::bbp_is_search_results
	 */
	public function test_search_predicates_respect_feature_setting_and_filters() {
		$wp_query      = bbp_get_wp_query();
		$search_exists = property_exists( $wp_query, 'bbp_is_search' );
		$results_exist = property_exists( $wp_query, 'bbp_search_terms' );
		$old_search    = $search_exists ? $wp_query->bbp_is_search : null;
		$old_results   = $results_exist ? $wp_query->bbp_search_terms : null;

		try {
			$wp_query->bbp_is_search    = true;
			$wp_query->bbp_search_terms = 'query terms';
			add_filter( 'bbp_allow_search', '__return_true' );
			add_filter( 'bbp_is_search', '__return_false' );
			add_filter( 'bbp_is_search_results', '__return_false' );

			$this->assertFalse( bbp_is_search() );
			$this->assertFalse( bbp_is_search_results() );

			remove_filter( 'bbp_is_search', '__return_false' );
			remove_filter( 'bbp_is_search_results', '__return_false' );
			add_filter( 'bbp_allow_search', '__return_false', 20 );
			add_filter( 'bbp_is_search', '__return_true' );
			add_filter( 'bbp_is_search_results', '__return_true' );
			$this->assertFalse( bbp_is_search() );
			$this->assertFalse( bbp_is_search_results() );
		} finally {
			remove_filter( 'bbp_is_search_results', '__return_true' );
			remove_filter( 'bbp_is_search', '__return_true' );
			remove_filter( 'bbp_allow_search', '__return_false', 20 );
			remove_filter( 'bbp_is_search_results', '__return_false' );
			remove_filter( 'bbp_is_search', '__return_false' );
			remove_filter( 'bbp_allow_search', '__return_true' );
			if ( $search_exists ) {
				$wp_query->bbp_is_search = $old_search;
			} else {
				unset( $wp_query->bbp_is_search );
			}
			if ( $results_exist ) {
				$wp_query->bbp_search_terms = $old_results;
			} else {
				unset( $wp_query->bbp_search_terms );
			}
		}
	}

	/**
	 * @covers ::bbp_has_shortcode
	 */
	public function test_has_shortcode_detects_registered_codes_and_passes_filter_context() {
		$codes = array_keys( bbpress()->shortcodes->codes );
		$this->assertGreaterThanOrEqual( 2, count( $codes ) );

		$found_codes = array_slice( $codes, 0, 2 );
		$text        = 'Before [' . $found_codes[0] . '] between [' . $found_codes[1] . '] after';
		$called = 0;
		$filter = function( $retval, $found, $filtered_text ) use ( &$called, $found_codes, $text ) {
			++$called;
			$this->assertTrue( $retval );
			$this->assertSame( $found_codes, $found );
			$this->assertSame( $text, $filtered_text );
			return false;
		};

		$this->assertFalse( bbp_has_shortcode( '' ) );
		$this->assertFalse( bbp_has_shortcode( '[gallery]' ) );
		$this->assertFalse( bbp_has_shortcode( '[bbp-not-registered]' ) );
		$this->assertTrue( bbp_has_shortcode( $text ) );

		add_filter( 'bbp_has_shortcode', $filter, 10, 3 );
		try {
			$this->assertFalse( bbp_has_shortcode( $text ) );
			$this->assertSame( 1, $called );
		} finally {
			remove_filter( 'bbp_has_shortcode', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_has_shortcode
	 */
	public function test_has_shortcode_uses_singular_global_post_content() {
		global $post;

		$codes        = array_keys( bbpress()->shortcodes->codes );
		$code         = reset( $codes );
		$post_id      = $this->factory->post->create(
			array( 'post_content' => '[' . $code . ']' )
		);
		$plain_id     = $this->factory->post->create( array( 'post_content' => 'No shortcode' ) );
		$old_post     = $post;
		$wp_query     = bbp_get_wp_query();
		$old_singular = $wp_query->is_singular;

		try {
			$post                  = get_post( $post_id );
			$wp_query->is_singular = false;
			$this->assertFalse( bbp_has_shortcode() );

			$wp_query->is_singular = true;
			$this->assertTrue( bbp_has_shortcode() );

			$post = get_post( $plain_id );
			$this->assertFalse( bbp_has_shortcode() );
		} finally {
			$post                  = $old_post;
			$wp_query->is_singular = $old_singular;
		}
	}

	/**
	 * @covers ::bbp_body_class
	 * @dataProvider body_class_specific_state_provider
	 */
	public function test_body_class_uses_specific_and_general_state_classes( $properties, $action, $expected, $unexpected, $specific_predicate ) {
		$wp_query    = bbp_get_wp_query();
		$old_name    = bbp_get_query_name();
		$old_get     = $_GET;
		$old_request = $_REQUEST;
		$old_values  = array();
		$old_exists  = array();

		foreach ( $properties as $property => $value ) {
			$old_exists[ $property ] = property_exists( $wp_query, $property );
			$old_values[ $property ] = $old_exists[ $property ] ? $wp_query->{$property} : null;
		}

		add_filter( 'bbp_allow_search', '__return_true' );

		try {
			bbp_reset_query_name();
			unset( $_REQUEST[ bbp_get_search_rewrite_id() ] );
			foreach ( $properties as $property => $value ) {
				$wp_query->{$property} = $value;
			}

			if ( null === $action ) {
				unset( $_GET['action'] );
			} else {
				$_GET['action'] = $action;
			}

			if ( null !== $specific_predicate ) {
				$this->assertTrue( call_user_func( $specific_predicate ) );
			}

			$classes = bbp_body_class( array( 'existing', 'bbpress' ) );
			foreach ( $expected as $class ) {
				$this->assertContains( $class, $classes );
			}
			foreach ( $unexpected as $class ) {
				$this->assertNotContains( $class, $classes );
			}
			$this->assertContains( 'existing', $classes );
			$this->assertContains( 'bbpress', $classes );
			$this->assertContains( 'no-js', $classes );
			$this->assertSame( 1, array_count_values( $classes )['bbpress'] );
		} finally {
			remove_filter( 'bbp_allow_search', '__return_true' );
			bbp_set_query_name( $old_name );
			$_GET = $old_get;
			$_REQUEST = $old_request;
			foreach ( $old_values as $property => $value ) {
				if ( $old_exists[ $property ] ) {
					$wp_query->{$property} = $value;
				} else {
					unset( $wp_query->{$property} );
				}
			}
		}
	}

	/**
	 * @covers ::bbp_body_class
	 */
	public function test_body_class_passes_filter_context() {
		$wp_classes     = array( 'wp-class' );
		$custom_classes = array( 'custom-class' );
		$deprecated     = function( $classes, $bbp_classes, $filtered_wp_classes, $filtered_custom_classes ) use ( $wp_classes, $custom_classes ) {
			$this->assertContains( bbp_get_forum_post_type() . '-archive', $bbp_classes );
			$this->assertSame( $wp_classes, $filtered_wp_classes );
			$this->assertSame( $custom_classes, $filtered_custom_classes );
			$classes[] = 'deprecated-filter';

			return $classes;
		};
		$current        = function( $classes, $bbp_classes, $filtered_wp_classes, $filtered_custom_classes ) use ( $wp_classes, $custom_classes ) {
			$this->assertContains( 'deprecated-filter', $classes );
			$this->assertContains( bbp_get_forum_post_type() . '-archive', $bbp_classes );
			$this->assertSame( $wp_classes, $filtered_wp_classes );
			$this->assertSame( $custom_classes, $filtered_custom_classes );
			$classes[] = 'current-filter';

			return $classes;
		};

		add_filter( 'bbp_is_forum_archive', '__return_true' );
		add_filter( 'bbp_get_the_body_class', $deprecated, 10, 4 );
		add_filter( 'bbp_body_class', $current, 10, 4 );

		try {
			$classes = bbp_body_class( $wp_classes, $custom_classes );
			$this->assertContains( 'deprecated-filter', $classes );
			$this->assertContains( 'current-filter', $classes );
		} finally {
			remove_filter( 'bbp_body_class', $current, 10 );
			remove_filter( 'bbp_get_the_body_class', $deprecated, 10 );
			remove_filter( 'bbp_is_forum_archive', '__return_true' );
		}
	}

	/**
	 * @covers ::is_bbpress
	 */
	public function test_is_bbpress_uses_conditional_and_applies_filter() {
		$filter = function( $is_bbpress ) {
			$this->assertTrue( $is_bbpress );

			return false;
		};

		add_filter( 'bbp_is_forum_archive', '__return_true' );

		try {
			$this->assertTrue( is_bbpress() );

			add_filter( 'is_bbpress', $filter );
			$this->assertFalse( is_bbpress() );
		} finally {
			remove_filter( 'is_bbpress', $filter );
			remove_filter( 'bbp_is_forum_archive', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_wp_login_action
	 * @covers ::bbp_get_wp_login_action
	 */
	public function test_wp_login_action_builds_filters_and_outputs_url() {
		$args     = array(
			'action'  => 'resetpass',
			'context' => 'login',
			'url'     => 'custom-login.php',
		);
		$expected = site_url( 'custom-login.php?action=resetpass', 'login' );
		$filter   = function( $url, $parsed_args, $original_args ) use ( $expected, $args ) {
			$this->assertSame( $expected, $url );
			$this->assertSame( $args, $parsed_args );
			$this->assertSame( $args, $original_args );

			return add_query_arg( 'filtered', 'yes', $url );
		};

		$this->assertSame( site_url( 'wp-login.php' ), bbp_get_wp_login_action() );
		add_filter( 'bbp_get_wp_login_action', $filter, 10, 3 );

		try {
			$filtered = add_query_arg( 'filtered', 'yes', $expected );
			$this->assertSame( $filtered, bbp_get_wp_login_action( $args ) );
			$this->expectOutputString( esc_url( $filtered ) );
			bbp_wp_login_action( $args );
		} finally {
			remove_filter( 'bbp_get_wp_login_action', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_redirect_to_field
	 */
	public function test_redirect_to_field_removes_loggedout_and_passes_filter_context() {
		$url      = 'https://example.org/forums/?loggedout=true&topic=123';
		$redirect = remove_query_arg( 'loggedout', $url );
		$field    = '<input type="hidden" id="bbp_redirect_to" name="redirect_to" value="' . esc_url( $redirect ) . '" />';
		$filter   = function( $redirect_field, $redirect_to ) use ( $field, $redirect ) {
			$this->assertSame( $field, $redirect_field );
			$this->assertSame( $redirect, $redirect_to );

			return str_replace( ' />', ' data-filtered="true" />', $redirect_field );
		};

		add_filter( 'bbp_redirect_to_field', $filter, 10, 2 );

		try {
			$this->expectOutputString( str_replace( ' />', ' data-filtered="true" />', $field ) );
			bbp_redirect_to_field( $url );
		} finally {
			remove_filter( 'bbp_redirect_to_field', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_sanitize_val
	 * @covers ::bbp_get_sanitize_val
	 */
	public function test_sanitize_val_handles_input_types_and_passes_filter_context() {
		$old_request       = $_REQUEST;
		$_REQUEST['field'] = wp_slash( 'A & "quoted" value' );
		$text_value        = esc_attr( 'A & "quoted" value' );
		$raw_value         = esc_attr( $_REQUEST['field'] );
		$filter            = function( $value, $request, $input_type ) use ( $text_value ) {
			$this->assertSame( $text_value, $value );
			$this->assertSame( 'field', $request );
			$this->assertSame( 'textarea', $input_type );

			return $value . '-filtered';
		};

		try {
			$this->assertFalse( bbp_get_sanitize_val( 'missing' ) );
			$this->assertSame( $text_value, bbp_get_sanitize_val( 'field' ) );
			$this->assertSame( $raw_value, bbp_get_sanitize_val( 'field', 'password' ) );

			add_filter( 'bbp_get_sanitize_val', $filter, 10, 3 );
			$this->assertSame( $text_value . '-filtered', bbp_get_sanitize_val( 'field', 'textarea' ) );
			$this->expectOutputString( $text_value . '-filtered' );
			bbp_sanitize_val( 'field', 'textarea' );
		} finally {
			remove_filter( 'bbp_get_sanitize_val', $filter, 10 );
			$_REQUEST = $old_request;
		}
	}

	/**
	 * @covers ::bbp_tab_index_attribute
	 * @covers ::bbp_get_tab_index_attribute
	 */
	public function test_tab_index_attribute_handles_values_filter_and_output() {
		$filter = function( $attribute, $tab ) {
			$this->assertSame( ' tabindex="8"', $attribute );
			$this->assertSame( '8.9', $tab );

			return ' data-tabindex="8"';
		};

		$this->assertSame( '', bbp_get_tab_index_attribute() );
		$this->assertSame( '', bbp_get_tab_index_attribute( 'invalid' ) );
		$this->assertSame( ' tabindex="0"', bbp_get_tab_index_attribute( 0 ) );

		add_filter( 'bbp_get_tab_index_attribute', $filter, 10, 2 );

		try {
			$this->assertSame( ' data-tabindex="8"', bbp_get_tab_index_attribute( '8.9' ) );
			$this->expectOutputString( ' data-tabindex="8"' );
			bbp_tab_index_attribute( '8.9' );
		} finally {
			remove_filter( 'bbp_get_tab_index_attribute', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_dropdown
	 * @covers ::bbp_get_dropdown
	 */
	public function test_dropdown_renders_preloaded_hierarchy_and_passes_filter_context() {
		$parent_id = $this->factory->forum->create( array( 'post_title' => 'Parent & Forum' ) );
		$child_id  = $this->factory->forum->create(
			array(
				'post_parent' => $parent_id,
				'post_title'  => 'Child Forum',
			)
		);
		$args      = array(
			'posts'              => array( get_post( $parent_id ), get_post( $child_id ) ),
			'selected'           => $child_id,
			'select_id'          => 'forum<selector',
			'select_class'       => 'custom"class',
			'tab'                => '7.8',
			'show_none'          => 'Choose & Forum',
			'disable_categories' => false,
		);
		$filter    = function( $html, $parsed_args, $original_args ) use ( $args, $child_id ) {
			$this->assertSame( $args, $original_args );
			$this->assertSame( $child_id, $parsed_args['selected'] );
			$this->assertInstanceOf( 'BBP_Walker_Dropdown', $parsed_args['walker'] );

			return $html . '<!-- filtered -->';
		};

		add_filter( 'bbp_get_dropdown', $filter, 10, 3 );

		try {
			$html = bbp_get_dropdown( $args );
			$this->assertStringContainsString( '<select name="forum&lt;selector" id="forum&lt;selector" class="custom&quot;class" tabindex="7">', $html );
			$this->assertStringContainsString( '<option value="" class="level-0">Choose &amp; Forum</option>', $html );
			$this->assertStringContainsString( 'value="' . $parent_id . '">Parent &amp; Forum</option>', $html );
			$this->assertStringContainsString( 'class="level-1" value="' . $child_id . '" selected=\'selected\'>&nbsp;&nbsp;&nbsp;Child Forum</option>', $html );
			$this->assertStringEndsWith( '</select><!-- filtered -->', $html );

			$this->expectOutputString( $html );
			bbp_dropdown( $args );
		} finally {
			remove_filter( 'bbp_get_dropdown', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_dropdown
	 */
	public function test_dropdown_normalizes_arguments_and_supports_options_only() {
		$topic_id = $this->factory->topic->create( array( 'post_title' => 'Only Topic' ) );
		$args     = array(
			'post_type'    => bbp_get_topic_post_type(),
			'posts'        => array( get_post( $topic_id ) ),
			'selected'     => -1,
			'include'      => '1,2',
			'exclude'      => '3,4',
			'options_only' => true,
			'show_none'    => true,
		);
		$filter   = function( $html, $parsed_args ) {
			$this->assertSame( 0, $parsed_args['selected'] );
			$this->assertSame( array( '1', '2' ), $parsed_args['include'] );
			$this->assertSame( array( '3', '4' ), $parsed_args['exclude'] );

			return $html;
		};

		add_filter( 'bbp_get_dropdown', $filter, 10, 2 );

		try {
			$html = bbp_get_dropdown( $args );
			$this->assertStringNotContainsString( '<select', $html );
			$this->assertStringNotContainsString( '</select>', $html );
			$this->assertStringContainsString( 'No topics available', $html );
			$this->assertStringContainsString( 'value="' . $topic_id . '">Only Topic</option>', $html );
			$this->assertStringNotContainsString( 'selected', $html );
		} finally {
			remove_filter( 'bbp_get_dropdown', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_dropdown
	 */
	public function test_dropdown_disables_forum_categories_and_closed_forums() {
		$category_id = $this->factory->forum->create(
			array(
				'post_title' => 'Category Forum',
				'forum_meta' => array( 'forum_type' => 'category' ),
			)
		);
		$closed_id   = $this->factory->forum->create( array( 'post_title' => 'Closed Forum' ) );
		bbp_close_forum( $closed_id );

		$html = bbp_get_dropdown(
			array(
				'posts'    => array( get_post( $category_id ), get_post( $closed_id ) ),
				'selected' => $category_id,
			)
		);

		$this->assertStringContainsString( '<option class="level-0" disabled="disabled" value="">Category Forum</option>', $html );
		$this->assertStringContainsString( '<option class="level-0" disabled="disabled" value="">Closed Forum</option>', $html );
		$this->assertStringNotContainsString( 'selected', $html );
	}

	/**
	 * @covers ::bbp_the_content
	 * @covers ::bbp_get_the_content
	 */
	public function test_the_content_renders_fallback_textarea_and_passes_filter_context() {
		$args    = array(
			'context'       => 'topic',
			'before'        => '<section>',
			'after'         => '</section>',
			'textarea_rows' => 5,
			'tabindex'      => 8,
			'editor_class'  => 'custom-editor',
		);
		$content = function() {
			return 'Topic &amp; content';
		};
		$filter  = function( $html, $original_args, $post_content ) use ( $args ) {
			$this->assertSame( $args, $original_args );
			$this->assertSame( 'Topic &amp; content', $post_content );

			return $html . '<!-- filtered -->';
		};

		add_filter( 'bbp_use_wp_editor', '__return_false' );
		add_filter( 'bbp_get_form_topic_content', $content, 99 );
		add_filter( 'bbp_get_the_content', $filter, 10, 3 );

		try {
			$html = bbp_get_the_content( $args );
			$this->assertStringStartsWith( '<section>', trim( $html ) );
			$this->assertStringContainsString( '<textarea id="bbp_topic_content" class="custom-editor" name="bbp_topic_content" cols="60" rows="5"  tabindex="8">Topic &amp; content</textarea>', $html );
			$this->assertStringEndsWith( '</section><!-- filtered -->', trim( $html ) );

			$this->expectOutputString( $html );
			bbp_the_content( $args );
		} finally {
			remove_filter( 'bbp_get_the_content', $filter, 10 );
			remove_filter( 'bbp_get_form_topic_content', $content, 99 );
			remove_filter( 'bbp_use_wp_editor', '__return_false' );
		}
	}

	/**
	 * @covers ::bbp_get_the_content
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_content_configures_editor_hooks_and_removes_textarea_escaping() {
		$plugins_seen  = false;
		$buttons_seen  = false;
		$quicktags_seen = false;
		$content        = function() {
			return 'Editor content';
		};
		$plugins_probe  = function( $plugins, $editor_id ) use ( &$plugins_seen ) {
			$plugins_seen = true;
			$this->assertSame( 'bbp_topic_content', $editor_id );
			$this->assertNotContains( 'fullscreen', $plugins );
			$this->assertContains( 'tabfocus', $plugins );

			return $plugins;
		};
		$buttons_probe  = function( $buttons ) use ( &$buttons_seen ) {
			$buttons_seen = true;
			$this->assertNotContains( 'underline', $buttons );
			$this->assertNotContains( 'justifycenter', $buttons );
			$this->assertContains( 'image', $buttons );

			return $buttons;
		};
		$quicktags_probe = function( $settings, $editor_id ) use ( &$quicktags_seen ) {
			$quicktags_seen = true;
			$this->assertSame( 'bbp_topic_content', $editor_id );
			$buttons = explode( ',', $settings['buttons'] );
			$this->assertNotContains( 'ins', $buttons );
			$this->assertNotContains( 'more', $buttons );
			$this->assertNotContains( 'spell', $buttons );

			return $settings;
		};

		add_filter( 'bbp_use_wp_editor', '__return_true' );
		add_filter( 'user_can_richedit', '__return_true' );
		add_filter( 'bbp_get_form_topic_content', $content, 99 );
		add_filter( 'teeny_mce_plugins', $plugins_probe, 20, 2 );
		add_filter( 'teeny_mce_buttons', $buttons_probe, 20 );
		add_filter( 'quicktags_settings', $quicktags_probe, 20, 2 );
		$this->assertNotFalse( has_filter( 'bbp_get_form_forum_content', 'esc_textarea' ) );
		$this->assertNotFalse( has_filter( 'bbp_get_form_topic_content', 'esc_textarea' ) );
		$this->assertNotFalse( has_filter( 'bbp_get_form_reply_content', 'esc_textarea' ) );

		$html = bbp_get_the_content(
			array(
				'context'   => 'topic',
				'tinymce'  => true,
				'teeny'     => true,
				'quicktags' => true,
			)
		);

		$this->assertStringContainsString( 'id="wp-bbp_topic_content-wrap"', $html );
		$this->assertStringContainsString( 'id="bbp_topic_content"', $html );
		$this->assertStringContainsString( '>Editor content</textarea>', $html );
		$this->assertTrue( $plugins_seen );
		$this->assertTrue( $buttons_seen );
		$this->assertTrue( $quicktags_seen );
		$this->assertFalse( has_filter( 'tiny_mce_plugins', 'bbp_get_tiny_mce_plugins' ) );
		$this->assertFalse( has_filter( 'teeny_mce_plugins', 'bbp_get_tiny_mce_plugins' ) );
		$this->assertFalse( has_filter( 'teeny_mce_buttons', 'bbp_get_teeny_mce_buttons' ) );
		$this->assertFalse( has_filter( 'quicktags_settings', 'bbp_get_quicktags_settings' ) );
		$this->assertFalse( has_filter( 'bbp_get_form_forum_content', 'esc_textarea' ) );
		$this->assertFalse( has_filter( 'bbp_get_form_topic_content', 'esc_textarea' ) );
		$this->assertFalse( has_filter( 'bbp_get_form_reply_content', 'esc_textarea' ) );
	}

	/**
	 * @covers ::bbp_get_tiny_mce_plugins
	 */
	public function test_tiny_mce_plugins_replaces_fullscreen_with_tabfocus_and_applies_filter() {
		$filter = function( $plugins ) {
			$this->assertSame( array( 0 => 'link', 2 => 'code', 3 => 'tabfocus' ), $plugins );

			$plugins[] = 'filtered-plugin';

			return $plugins;
		};

		add_filter( 'bbp_get_tiny_mce_plugins', $filter );

		try {
			$this->assertSame(
				array( 0 => 'link', 2 => 'code', 3 => 'tabfocus', 4 => 'filtered-plugin' ),
				bbp_get_tiny_mce_plugins( array( 'link', 'fullscreen', 'code' ) )
			);
		} finally {
			remove_filter( 'bbp_get_tiny_mce_plugins', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_teeny_mce_buttons
	 */
	public function test_teeny_mce_buttons_removes_alignment_adds_image_and_applies_filter() {
		$filter = function( $buttons ) {
			$this->assertSame( array( 0 => 'bold', 3 => 'link', 4 => 'image' ), $buttons );

			$buttons[] = 'filtered-button';

			return $buttons;
		};

		add_filter( 'bbp_get_teeny_mce_buttons', $filter );

		try {
			$this->assertSame(
				array( 0 => 'bold', 3 => 'link', 4 => 'image', 5 => 'filtered-button' ),
				bbp_get_teeny_mce_buttons( array( 'bold', 'underline', 'justifycenter', 'link' ) )
			);
		} finally {
			remove_filter( 'bbp_get_teeny_mce_buttons', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_quicktags_settings
	 */
	public function test_quicktags_settings_removes_buttons_preserves_settings_and_applies_filter() {
		$filter = function( $settings ) {
			$this->assertSame( 'strong,em,link', $settings['buttons'] );
			$this->assertSame( 42, $settings['id'] );

			$settings['filtered'] = true;

			return $settings;
		};

		add_filter( 'bbp_get_quicktags_settings', $filter );

		try {
			$this->assertSame(
				array(
					'buttons'  => 'strong,em,link',
					'id'       => 42,
					'filtered' => true,
				),
				bbp_get_quicktags_settings(
					array(
						'buttons' => 'strong,ins,em,more,spell,link',
						'id'      => 42,
					)
				)
			);
		} finally {
			remove_filter( 'bbp_get_quicktags_settings', $filter );
		}
	}

	/**
	 * @covers ::bbp_view_id
	 * @covers ::bbp_get_view_id
	 */
	public function test_view_id_uses_explicit_current_and_query_values_and_applies_filter() {
		global $wp_query;

		$bbp              = bbpress();
		$old_current_view = $bbp->current_view_id;
		$rewrite_id       = bbp_get_view_rewrite_id();
		$had_query_view   = array_key_exists( $rewrite_id, $wp_query->query_vars );
		$old_query_view   = get_query_var( $rewrite_id );
		$filter_calls     = array();
		$filter           = function( $view_id, $view ) use ( &$filter_calls ) {
			$filter_calls[] = array( $view_id, $view );

			if ( 'explicit-view' === $view ) {
				return $view_id . '-filtered';
			}

			return $view_id;
		};
		add_filter( 'bbp_get_view_id', $filter, 10, 2 );

		try {
			$bbp->current_view_id = '';
			set_query_var( $rewrite_id, 'query-view' );
			$this->assertSame( 'query-view', bbp_get_view_id() );

			$bbp->current_view_id = 'current-view';
			$this->assertSame( 'current-view', bbp_get_view_id() );
			$this->assertSame( 'explicit-view-filtered', bbp_get_view_id( 'explicit-view' ) );
			$this->assertSame( '123', bbp_get_view_id( 123 ) );
			$this->assertSame( 'current-view', bbp_get_view_id( false ) );

			$bbp->current_view_id = '';
			set_query_var( $rewrite_id, '' );
			$this->assertSame( '', bbp_get_view_id() );

			set_query_var( $rewrite_id, array( 'malformed-view' ) );
			$this->assertSame( '', bbp_get_view_id() );

			$this->expectOutputString( 'output-view' );
			bbp_view_id( 'output-view' );

			$this->assertSame(
				array(
					array( 'query-view', '' ),
					array( 'current-view', '' ),
					array( 'explicit-view', 'explicit-view' ),
					array( '123', 123 ),
					array( 'current-view', false ),
					array( '', '' ),
					array( '', '' ),
					array( 'output-view', 'output-view' ),
				),
				$filter_calls
			);
		} finally {
			remove_filter( 'bbp_get_view_id', $filter, 10 );
			$bbp->current_view_id = $old_current_view;
			if ( $had_query_view ) {
				set_query_var( $rewrite_id, $old_query_view );
			} else {
				unset( $wp_query->query_vars[ $rewrite_id ] );
			}
		}
	}

	/**
	 * @covers ::bbp_get_view_id
	 * @covers ::bbp_get_view_title
	 * @covers ::bbp_get_view_url
	 * @ticket BBP3734
	 */
	public function test_view_helpers_reject_malformed_query_values() {
		global $wp_query;

		$bbp              = bbpress();
		$old_current_view = $bbp->current_view_id;
		$rewrite_id       = bbp_get_view_rewrite_id();
		$had_query_view   = array_key_exists( $rewrite_id, $wp_query->query_vars );
		$old_query_view   = get_query_var( $rewrite_id );

		try {
			$bbp->current_view_id = '';
			set_query_var( $rewrite_id, array( 'malformed-view' ) );
			$this->assertSame( '', bbp_get_view_id() );
			$this->assertFalse( bbp_get_view_title() );
			$this->assertSame( home_url(), bbp_get_view_url() );
		} finally {
			$bbp->current_view_id = $old_current_view;
			if ( $had_query_view ) {
				set_query_var( $rewrite_id, $old_query_view );
			} else {
				unset( $wp_query->query_vars[ $rewrite_id ] );
			}
		}
	}

	/**
	 * @covers ::bbp_get_view_id
	 * @covers ::bbp_get_view_title
	 * @covers ::bbp_get_view_url
	 * @ticket BBP3734
	 */
	public function test_view_helpers_support_numeric_registered_view_ids() {
		$bbp            = bbpress();
		$old_views      = $bbp->views;
		$old_permalinks = get_option( 'permalink_structure' );

		bbp_register_view( '123', 'Numeric View' );

		try {
			$this->set_permalink_structure();
			$this->assertSame( '123', bbp_get_view_id( 123 ) );
			$this->assertSame( 'Numeric View', bbp_get_view_title( 123 ) );
			$this->assertSame(
				add_query_arg( bbp_get_view_rewrite_id(), '123', home_url( '/' ) ),
				bbp_get_view_url( 123 )
			);
		} finally {
			$bbp->views = $old_views;
			$this->set_permalink_structure( $old_permalinks );
		}
	}

	/**
	 * @covers ::bbp_view_title
	 * @covers ::bbp_get_view_title
	 */
	public function test_view_title_returns_and_outputs_registered_title() {
		global $wp_query;

		$bbp              = bbpress();
		$old_views        = $bbp->views;
		$old_current_view = $bbp->current_view_id;
		$rewrite_id       = bbp_get_view_rewrite_id();
		$had_query_view   = array_key_exists( $rewrite_id, $wp_query->query_vars );
		$old_query_view   = get_query_var( $rewrite_id );
		$view_id          = 'unit-test-template-view';
		$title            = 'A &amp; B';

		bbp_register_view( $view_id, 'A & B' );

		try {
			$this->assertSame( $title, bbp_get_view_title( $view_id ) );

			$bbp->current_view_id = $view_id;
			$this->assertSame( $title, bbp_get_view_title() );

			$bbp->current_view_id = '';
			set_query_var( $rewrite_id, $view_id );
			$this->assertSame( $title, bbp_get_view_title() );

			$this->expectOutputString( $title );
			bbp_view_title( $view_id );
		} finally {
			$bbp->views           = $old_views;
			$bbp->current_view_id = $old_current_view;
			if ( $had_query_view ) {
				set_query_var( $rewrite_id, $old_query_view );
			} else {
				unset( $wp_query->query_vars[ $rewrite_id ] );
			}
		}
	}

	/**
	 * @covers ::bbp_get_view_title
	 * @ticket BBP3733
	 */
	public function test_view_title_returns_false_for_unregistered_view() {
		global $wp_query;

		$bbp              = bbpress();
		$old_current_view = $bbp->current_view_id;
		$rewrite_id       = bbp_get_view_rewrite_id();
		$had_query_view   = array_key_exists( $rewrite_id, $wp_query->query_vars );
		$old_query_view   = get_query_var( $rewrite_id );

		try {
			$bbp->current_view_id = '';
			set_query_var( $rewrite_id, '' );
			$this->assertFalse( bbp_get_view_title() );
			$this->assertFalse( bbp_get_view_title( 'missing-view' ) );

			$bbp->current_view_id = 'missing-current-view';
			$this->assertFalse( bbp_get_view_title() );

			$bbp->current_view_id = '';
			set_query_var( $rewrite_id, 'missing-query-view' );
			$this->assertFalse( bbp_get_view_title() );
		} finally {
			$bbp->current_view_id = $old_current_view;
			if ( $had_query_view ) {
				set_query_var( $rewrite_id, $old_query_view );
			} else {
				unset( $wp_query->query_vars[ $rewrite_id ] );
			}
		}
	}

	/**
	 * @covers ::bbp_view_url
	 * @covers ::bbp_get_view_url
	 */
	public function test_view_url_supports_pretty_and_plain_permalinks_and_applies_filter() {
		global $wp_query;

		$bbp              = bbpress();
		$old_permalinks   = get_option( 'permalink_structure' );
		$old_current_view = $bbp->current_view_id;
		$rewrite_id       = bbp_get_view_rewrite_id();
		$had_query_view   = array_key_exists( $rewrite_id, $wp_query->query_vars );
		$old_query_view   = get_query_var( $rewrite_id );
		$filtered_view    = null;
		$filter           = function( $url, $view ) use ( &$filtered_view ) {
			$filtered_view = $view;

			return add_query_arg(
				array(
					'first'  => '1',
					'second' => '"quoted"',
				),
				$url
			);
		};

		try {
			$bbp->current_view_id = '';
			set_query_var( $rewrite_id, '' );
			$this->set_permalink_structure();
			$this->assertSame( home_url(), bbp_get_view_url() );

			add_filter( 'bbp_get_view_link', $filter, 10, 2 );
			$plain_url = add_query_arg(
				array(
					bbp_get_view_rewrite_id() => 'unit-test-view',
					'first'                   => '1',
					'second'                  => '"quoted"',
				),
				home_url( '/' )
			);
			$this->assertSame( $plain_url, bbp_get_view_url( 'unit-test-view' ) );
			$this->assertSame( 'unit-test-view', $filtered_view );
			$this->expectOutputString( esc_url( $plain_url ) );
			bbp_view_url( 'unit-test-view' );
			remove_filter( 'bbp_get_view_link', $filter, 10 );

			$this->set_permalink_structure( '/%postname%/' );
			$this->assertSame(
				home_url( '/forums/view/unit-test-view/' ),
				bbp_get_view_url( 'unit-test-view' )
			);
		} finally {
			remove_filter( 'bbp_get_view_link', $filter, 10 );
			$this->set_permalink_structure( $old_permalinks );
			$bbp->current_view_id = $old_current_view;
			if ( $had_query_view ) {
				set_query_var( $rewrite_id, $old_query_view );
			} else {
				unset( $wp_query->query_vars[ $rewrite_id ] );
			}
		}
	}

	public static function body_class_specific_state_provider() {
		return array(
			'topic merge' => array(
				array( 'bbp_is_topic_edit' => true ),
				'merge',
				array( bbp_get_topic_post_type() . '-merge', bbp_get_topic_post_type() . '-edit' ),
				array( bbp_get_topic_post_type() . '-split' ),
				'bbp_is_topic_merge',
			),
			'topic split' => array(
				array( 'bbp_is_topic_edit' => true ),
				'split',
				array( bbp_get_topic_post_type() . '-split', bbp_get_topic_post_type() . '-edit' ),
				array( bbp_get_topic_post_type() . '-merge' ),
				'bbp_is_topic_split',
			),
			'topic edit' => array(
				array( 'bbp_is_topic_edit' => true ),
				null,
				array( bbp_get_topic_post_type() . '-edit' ),
				array( bbp_get_topic_post_type() . '-merge', bbp_get_topic_post_type() . '-split' ),
				null,
			),
			'topic edit with reply action' => array(
				array( 'bbp_is_topic_edit' => true ),
				'move',
				array( bbp_get_topic_post_type() . '-edit' ),
				array( bbp_get_topic_post_type() . '-merge', bbp_get_topic_post_type() . '-split' ),
				null,
			),
			'reply move' => array(
				array( 'bbp_is_reply_edit' => true ),
				'move',
				array( bbp_get_reply_post_type() . '-move', bbp_get_reply_post_type() . '-edit' ),
				array(),
				'bbp_is_reply_move',
			),
			'reply edit' => array(
				array( 'bbp_is_reply_edit' => true ),
				null,
				array( bbp_get_reply_post_type() . '-edit' ),
				array( bbp_get_reply_post_type() . '-move' ),
				null,
			),
			'reply edit with topic action' => array(
				array( 'bbp_is_reply_edit' => true ),
				'merge',
				array( bbp_get_reply_post_type() . '-edit' ),
				array( bbp_get_reply_post_type() . '-move' ),
				null,
			),
			'user home edit' => array(
				array(
					'bbp_is_single_user_home' => true,
					'bbp_is_single_user_edit' => true,
					'bbp_is_single_user'      => true,
				),
				null,
				array( 'bbp-user-home-edit', 'bbp-user-edit', 'single', 'singular' ),
				array( 'bbp-user-page', 'bbp-user-home' ),
				'bbp_is_user_home_edit',
			),
			'user edit' => array(
				array(
					'bbp_is_single_user_home' => false,
					'bbp_is_single_user_edit' => true,
					'bbp_is_single_user'      => true,
				),
				null,
				array( 'bbp-user-edit', 'single', 'singular' ),
				array( 'bbp-user-home-edit' ),
				null,
			),
			'search results' => array(
				array(
					'bbp_is_search'    => true,
					'bbp_search_terms' => 'query terms',
				),
				null,
				array( 'bbp-search-results', 'forum-search-results', 'bbp-search', 'forum-search' ),
				array(),
				'bbp_is_search_results',
			),
			'search results without search' => array(
				array(
					'bbp_is_search'    => false,
					'bbp_search_terms' => 'query terms',
				),
				null,
				array( 'bbp-search-results', 'forum-search-results' ),
				array( 'bbp-search', 'forum-search' ),
				'bbp_is_search_results',
			),
			'search' => array(
				array(
					'bbp_is_search'    => true,
					'bbp_search_terms' => '',
				),
				null,
				array( 'bbp-search', 'forum-search' ),
				array( 'bbp-search-results', 'forum-search-results' ),
				null,
			),
		);
	}

	private function create_bbp_post( $factory_name ) {
		if ( 'reply' === $factory_name ) {
			$topic_id = $this->factory->topic->create();
			return $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		}

		return $this->factory->{$factory_name}->create();
	}
}
