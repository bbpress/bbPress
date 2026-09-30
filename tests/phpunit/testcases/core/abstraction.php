<?php
/**
 * Tests for core abstraction function wrappers
 *
 * @group core
 * @group abstraction
 */

class BBP_Tests_Core_Abstraction extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_setup_admin
	 * @covers ::bbp_setup_converter
	 */
	public function test_bbp_setup_admin_and_converter_are_reused() {
		$admin = bbp_setup_admin();

		$this->assertInstanceOf( 'BBP_Admin', $admin );
		$this->assertSame( $admin, bbpress()->admin );
		$this->assertSame( $admin, bbp_setup_admin() );

		$converter = bbp_setup_converter();
		$this->assertInstanceOf( 'BBP_Converter', $converter );
		$this->assertSame( $converter, $admin->converter );
		$this->assertSame( $converter, bbp_setup_converter() );
	}

	/**
	 * @covers ::bbp_get_global_object
	 */
	public function test_bbp_get_global_object() {
		$name   = 'bbp_test_global_object';
		$object = new stdClass();
		$filter = function( $value, $global_name, $type, $default ) use ( $name, $object ) {
			$this->assertSame( $name, $global_name );
			$this->assertSame( 'stdClass', $type );
			$this->assertSame( 'fallback', $default );
			$this->assertSame( $object, $value );
			return 'filtered';
		};

		try {
			$this->assertSame( 'fallback', bbp_get_global_object( '', '', 'fallback' ) );
			$this->assertSame( 'fallback', bbp_get_global_object( $name, '', 'fallback' ) );

			$GLOBALS[ $name ] = null;
			$this->assertSame( 'fallback', bbp_get_global_object( $name, '', 'fallback' ) );

			$GLOBALS[ $name ] = $object;
			$this->assertSame( $object, bbp_get_global_object( $name ) );
			$this->assertSame( 'fallback', bbp_get_global_object( $name, 'WP_Query', 'fallback' ) );

			add_filter( 'bbp_get_global_object', $filter, 10, 4 );
			$this->assertSame( 'filtered', bbp_get_global_object( $name, 'stdClass', 'fallback' ) );
		} finally {
			remove_filter( 'bbp_get_global_object', $filter, 10 );
			unset( $GLOBALS[ $name ] );
		}
	}

	/**
	 * @covers ::bbp_get_wp_query
	 */
	public function test_bbp_get_wp_query() {
		$this->assertSame( $GLOBALS['wp_query'], bbp_get_wp_query() );
	}

	/**
	 * @covers ::bbp_get_wp_roles
	 */
	public function test_bbp_get_wp_roles() {
		$this->assertSame( $GLOBALS['wp_roles'], bbp_get_wp_roles() );
	}

	/**
	 * @covers ::bbp_db
	 */
	public function test_bbp_db() {
		$this->assertSame( $GLOBALS['wpdb'], bbp_db() );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_handles_missing_and_non_negative_values() {
		$post_id = self::factory()->post->create();

		$this->assertTrue( bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count', 2, 3 ) );
		$this->assertSame( 5, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
		$this->assertTrue( bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count', -10 ) );
		$this->assertSame( 0, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
		$this->assertFalse( bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count', -1 ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_validates_required_values_before_filters() {
		$filtered = 0;
		$callback = function( $check ) use ( &$filtered ) {
			$filtered++;
			return $check;
		};

		add_filter( 'bbp_pre_bump_count_meta', $callback );
		$this->assertFalse( bbp_bump_count_meta( 'post', 0, '_bbp_test_count' ) );
		$this->assertFalse( bbp_bump_count_meta( 'post', 1, '' ) );
		$this->assertFalse( bbp_bump_count_meta( 'post', 1, '_bbp_test_count', 0 ) );
		remove_filter( 'bbp_pre_bump_count_meta', $callback, 10 );

		$this->assertSame( 0, $filtered );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_can_be_short_circuited_before_type_validation() {
		$meta_types_filtered = 0;
		$received            = array();
		$pre_callback        = function( $check, $meta_type, $object_id, $meta_key, $difference, $default ) use ( &$received ) {
			$received = func_get_args();
			return false;
		};
		$types_callback = function( $meta_types ) use ( &$meta_types_filtered ) {
			$meta_types_filtered++;
			return $meta_types;
		};

		add_filter( 'bbp_pre_bump_count_meta', $pre_callback, 10, 6 );
		add_filter( 'bbp_bump_count_meta_types', $types_callback );
		$result = bbp_bump_count_meta( 'invalid', 1, '_bbp_test_count' );
		remove_filter( 'bbp_bump_count_meta_types', $types_callback, 10 );
		remove_filter( 'bbp_pre_bump_count_meta', $pre_callback, 10 );

		$this->assertFalse( $result );
		$this->assertSame( array( null, 'invalid', 1, '_bbp_test_count', 1, 0 ), $received );
		$this->assertSame( 0, $meta_types_filtered );

		$pre_callback = function() {
			return true;
		};

		add_filter( 'bbp_pre_bump_count_meta', $pre_callback );
		$result = bbp_bump_count_meta( 'invalid', 1, '_bbp_test_count' );
		remove_filter( 'bbp_pre_bump_count_meta', $pre_callback, 10 );

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_supports_user_term_and_comment_metadata() {
		$post_id    = self::factory()->post->create();
		$user_id    = self::factory()->user->create();
		$term_id    = self::factory()->term->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		$this->assertTrue( bbp_bump_count_meta( 'user', $user_id, '_bbp_test_count', 1 ) );
		$this->assertSame( 1, (int) get_user_meta( $user_id, '_bbp_test_count', true ) );
		$this->assertTrue( bbp_bump_count_meta( 'user', $user_id, '_bbp_test_count', 1 ) );
		$this->assertSame( 2, (int) get_user_meta( $user_id, '_bbp_test_count', true ) );
		$this->assertTrue( bbp_bump_count_meta( 'term', $term_id, '_bbp_test_count', 2 ) );
		$this->assertSame( 2, (int) get_term_meta( $term_id, '_bbp_test_count', true ) );
		$this->assertTrue( bbp_bump_count_meta( 'term', $term_id, '_bbp_test_count', 2 ) );
		$this->assertSame( 4, (int) get_term_meta( $term_id, '_bbp_test_count', true ) );
		$this->assertTrue( bbp_bump_count_meta( 'comment', $comment_id, '_bbp_test_count', 3 ) );
		$this->assertSame( 3, (int) get_comment_meta( $comment_id, '_bbp_test_count', true ) );
		$this->assertTrue( bbp_bump_count_meta( 'comment', $comment_id, '_bbp_test_count', 3 ) );
		$this->assertSame( 6, (int) get_comment_meta( $comment_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_types_are_filterable() {
		$term_id  = self::factory()->term->create();
		$callback = function() {
			return array( 'post' );
		};

		add_filter( 'bbp_bump_count_meta_types', $callback );
		$result = bbp_bump_count_meta( 'term', $term_id, '_bbp_test_count' );
		remove_filter( 'bbp_bump_count_meta_types', $callback, 10 );

		$this->assertFalse( $result );
		$this->assertSame( '', get_term_meta( $term_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_does_not_retry_a_filtered_update() {
		$post_id = self::factory()->post->create();
		$updates = 0;
		$callback = function() use ( &$updates ) {
			$updates++;
			return false;
		};

		update_post_meta( $post_id, '_bbp_test_count', 5 );
		add_filter( 'update_post_metadata', $callback );
		$result = bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count' );
		remove_filter( 'update_post_metadata', $callback, 10 );

		$this->assertFalse( $result );
		$this->assertSame( 1, $updates );
		$this->assertSame( 5, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_filters_an_update_before_adding_metadata() {
		$post_id  = self::factory()->post->create();
		$previous = null;
		$callback = function( $check, $filtered_post_id, $meta_key, $meta_value, $prev_value ) use ( &$previous ) {
			$previous = $prev_value;
			return false;
		};

		add_filter( 'update_post_metadata', $callback, 10, 5 );
		$result = bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count' );
		remove_filter( 'update_post_metadata', $callback, 10 );

		$this->assertFalse( $result );
		$this->assertSame( '', $previous );
		$this->assertSame( '', get_post_meta( $post_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 * @ticket BBP3678
	 */
	public function test_bbp_bump_count_meta_uses_the_database_after_a_filtered_read() {
		$post_id = self::factory()->post->create();
		$callback = function( $value, $filtered_post_id, $meta_key ) use ( $post_id ) {
			return ( ( $post_id === $filtered_post_id ) && ( '_bbp_test_count' === $meta_key ) )
				? 100
				: $value;
		};

		update_post_meta( $post_id, '_bbp_test_count', 5 );
		add_filter( 'get_post_metadata', $callback, 10, 3 );
		$result = bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count' );
		remove_filter( 'get_post_metadata', $callback, 10 );

		$this->assertTrue( $result );
		$this->assertSame( 6, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_returns_false_when_adding_metadata_fails() {
		$post_id = self::factory()->post->create();
		$callback = function() {
			return false;
		};

		add_filter( 'add_post_metadata', $callback );
		$result = bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count' );
		remove_filter( 'add_post_metadata', $callback, 10 );

		$this->assertFalse( $result );
		$this->assertSame( '', get_post_meta( $post_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 * @ticket BBP3678
	 */
	public function test_bbp_bump_count_meta_preserves_an_interleaved_update_from_zero() {
		$post_id     = self::factory()->post->create();
		$interleaved = false;
		$callback    = function( $check, $filtered_post_id, $meta_key ) use ( $post_id, &$interleaved ) {
			if ( ( $post_id === $filtered_post_id ) && ( '_bbp_test_count' === $meta_key ) && ! $interleaved ) {
				$interleaved = true;
				bbp_bump_count_meta( 'post', $post_id, $meta_key );
			}

			return $check;
		};

		update_post_meta( $post_id, '_bbp_test_count', 0 );
		add_filter( 'update_post_metadata', $callback, 10, 3 );
		bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count' );
		remove_filter( 'update_post_metadata', $callback, 10 );

		$this->assertSame( 2, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 * @ticket BBP3678
	 */
	public function test_bbp_bump_count_meta_preserves_interleaved_decrements() {
		$post_id      = self::factory()->post->create();
		$interleaved  = false;
		$filter_calls = 0;
		$callback     = function( $check, $filtered_post_id, $meta_key ) use ( $post_id, &$interleaved, &$filter_calls ) {
			$filter_calls++;

			if ( ( $post_id === $filtered_post_id ) && ( '_bbp_test_count' === $meta_key ) && ! $interleaved ) {
				$interleaved = true;
				bbp_bump_count_meta( 'post', $post_id, $meta_key, -1 );
			}

			return $check;
		};

		update_post_meta( $post_id, '_bbp_test_count', 3 );
		add_filter( 'update_post_metadata', $callback, 10, 3 );
		bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count', -1 );
		remove_filter( 'update_post_metadata', $callback, 10 );

		$this->assertSame( 1, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
		$this->assertSame( 2, $filter_calls );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 * @ticket BBP3678
	 */
	public function test_bbp_bump_count_meta_refreshes_a_stale_zero_before_decrementing() {
		global $wpdb;

		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, '_bbp_test_count', 0 );
		get_post_meta( $post_id, '_bbp_test_count', true );

		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => 1 ),
			array(
				'post_id'  => $post_id,
				'meta_key' => '_bbp_test_count'
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		$this->assertTrue( bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count', -1 ) );
		$this->assertSame( 0, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 * @ticket BBP3678
	 */
	public function test_bbp_bump_count_meta_max_attempts_is_filterable() {
		$post_id     = self::factory()->post->create();
		$interleaved = false;
		$callback    = function( $check, $filtered_post_id, $meta_key ) use ( $post_id, &$interleaved ) {
			if ( ( $post_id === $filtered_post_id ) && ( '_bbp_test_count' === $meta_key ) && ! $interleaved ) {
				$interleaved = true;
				bbp_bump_count_meta( 'post', $post_id, $meta_key, -1 );
			}

			return $check;
		};
		$max_attempts = function() {
			return 1;
		};

		update_post_meta( $post_id, '_bbp_test_count', 3 );
		add_filter( 'update_post_metadata', $callback, 10, 3 );
		add_filter( 'bbp_bump_count_meta_max_attempts', $max_attempts );
		$result = bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count', -1 );
		remove_filter( 'bbp_bump_count_meta_max_attempts', $max_attempts, 10 );
		remove_filter( 'update_post_metadata', $callback, 10 );

		$this->assertFalse( $result );
		$this->assertSame( 2, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 * @ticket BBP3678
	 */
	public function test_bbp_bump_count_meta_preserves_an_interleaved_add() {
		$post_id     = self::factory()->post->create();
		$interleaved = false;
		$callback    = function( $check, $filtered_post_id, $meta_key ) use ( $post_id, &$interleaved ) {
			if ( ( $post_id === $filtered_post_id ) && ( '_bbp_test_count' === $meta_key ) && ! $interleaved ) {
				$interleaved = true;
				bbp_bump_count_meta( 'post', $post_id, $meta_key );
			}

			return $check;
		};

		add_filter( 'add_post_metadata', $callback, 10, 3 );
		bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count' );
		remove_filter( 'add_post_metadata', $callback, 10 );

		$this->assertSame( 2, (int) get_post_meta( $post_id, '_bbp_test_count', true ) );
	}

	/**
	 * @covers ::bbp_bump_count_meta
	 */
	public function test_bbp_bump_count_meta_runs_standard_metadata_actions() {
		$post_id        = self::factory()->post->create();
		$updated        = 0;
		$legacy_updated = 0;
		$callback = function( $meta_id, $updated_post_id, $meta_key, $meta_value ) use ( $post_id, &$updated ) {
			if ( ( $post_id === $updated_post_id ) && ( '_bbp_test_count' === $meta_key ) && ( 2 === $meta_value ) ) {
				$updated++;
			}
		};
		$legacy_callback = function( $meta_id, $updated_post_id, $meta_key, $meta_value ) use ( $post_id, &$legacy_updated ) {
			if ( ( $post_id === $updated_post_id ) && ( '_bbp_test_count' === $meta_key ) && ( 2 === $meta_value ) ) {
				$legacy_updated++;
			}
		};

		update_post_meta( $post_id, '_bbp_test_count', 1 );
		add_action( 'updated_post_meta', $callback, 10, 4 );
		add_action( 'updated_postmeta', $legacy_callback, 10, 4 );
		bbp_bump_count_meta( 'post', $post_id, '_bbp_test_count' );
		remove_action( 'updated_post_meta', $callback, 10 );
		remove_action( 'updated_postmeta', $legacy_callback, 10 );

		$this->assertSame( 1, $updated );
		$this->assertSame( 1, $legacy_updated );
	}

	/**
	 * @covers ::bbp_rewrite
	 */
	public function test_bbp_rewrite() {
		$filter = function( $value, $name, $type, $default ) {
			if ( 'wp_rewrite' === $name ) {
				$this->assertSame( 'WP_Rewrite', $type );
				$this->assertSame( '', $default->root );
				$this->assertSame( 'page', $default->pagination_base );
			}

			return $value;
		};

		add_filter( 'bbp_get_global_object', $filter, 10, 4 );
		try {
			$this->assertSame( $GLOBALS['wp_rewrite'], bbp_rewrite() );

			$rewrite = $GLOBALS['wp_rewrite'];
			unset( $GLOBALS['wp_rewrite'] );
			try {
				$fallback = bbp_rewrite();
				$this->assertSame( '', $fallback->root );
				$this->assertSame( 'page', $fallback->pagination_base );
			} finally {
				$GLOBALS['wp_rewrite'] = $rewrite;
			}
		} finally {
			remove_filter( 'bbp_get_global_object', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_root_url
	 */
	public function test_bbp_get_root_url() {
		$this->assertSame( '', bbp_get_root_url() );

		$rewrite_filter = function() {
			return new stdClass();
		};

		add_filter( 'bbp_get_global_object', $rewrite_filter );
		try {
			$this->assertSame( '', bbp_get_root_url() );
		} finally {
			remove_filter( 'bbp_get_global_object', $rewrite_filter );
		}

		$filter = function() {
			return '/filtered/';
		};

		add_filter( 'bbp_get_root_url', $filter );
		try {
			$this->assertSame( '/filtered/', bbp_get_root_url() );
		} finally {
			remove_filter( 'bbp_get_root_url', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_paged_slug
	 */
	public function test_bbp_get_paged_slug() {
		$this->assertSame( 'page', bbp_get_paged_slug() );

		$rewrite_filter = function() {
			return new stdClass();
		};

		add_filter( 'bbp_get_global_object', $rewrite_filter );
		try {
			$this->assertSame( 'page', bbp_get_paged_slug() );
		} finally {
			remove_filter( 'bbp_get_global_object', $rewrite_filter );
		}

		$filter = function() {
			return 'filtered-page';
		};

		add_filter( 'bbp_get_paged_slug', $filter );
		try {
			$this->assertSame( 'filtered-page', bbp_get_paged_slug() );
		} finally {
			remove_filter( 'bbp_get_paged_slug', $filter );
		}
	}

	/**
	 * @covers ::bbp_use_pretty_urls
	 */
	public function test_bbp_use_pretty_urls() {
		$this->assertFalse( bbp_use_pretty_urls() );

		$this->set_permalink_structure( '/%postname%/' );
		$this->assertTrue( bbp_use_pretty_urls() );
		$this->set_permalink_structure();

		$rewrite_filter = function() {
			return new stdClass();
		};

		add_filter( 'bbp_get_global_object', $rewrite_filter );
		try {
			$this->assertFalse( bbp_use_pretty_urls() );
		} finally {
			remove_filter( 'bbp_get_global_object', $rewrite_filter );
		}

		add_filter( 'bbp_pretty_urls', '__return_true' );
		try {
			$this->assertTrue( bbp_use_pretty_urls() );
		} finally {
			remove_filter( 'bbp_pretty_urls', '__return_true' );
		}

		add_filter( 'bbp_pretty_urls', '__return_false' );
		try {
			$this->assertFalse( bbp_use_pretty_urls() );
		} finally {
			remove_filter( 'bbp_pretty_urls', '__return_false' );
		}
	}

	/**
	 * @covers ::bbp_make_first_page_canonical
	 */
	public function test_bbp_make_first_page_canonical() {
		add_filter( 'bbp_pretty_urls', '__return_true' );
		try {
			$this->assertSame( '/forums/', bbp_make_first_page_canonical( '/forums/page/1/' ) );
			$this->assertSame( '/forums/page/10/', bbp_make_first_page_canonical( '/forums/page/10/' ) );
		} finally {
			remove_filter( 'bbp_pretty_urls', '__return_true' );
		}

		add_filter( 'bbp_pretty_urls', '__return_false' );
		try {
			$this->assertSame( '/forums/?foo=1&#038;bar=2', bbp_make_first_page_canonical( '/forums/?foo=1&#038;paged=1&#038;bar=2' ) );
			$this->assertSame( '/forums/?foo=1&#038;paged=10', bbp_make_first_page_canonical( '/forums/?foo=1&#038;paged=10' ) );
		} finally {
			remove_filter( 'bbp_pretty_urls', '__return_false' );
		}

		$this->assertSame( '', bbp_make_first_page_canonical() );

		$filter = function( $canonical, $original ) {
			$this->assertSame( '/forums/', $canonical );
			$this->assertSame( '/forums/page/1/', $original );
			return '/filtered/';
		};

		add_filter( 'bbp_pretty_urls', '__return_true' );
		add_filter( 'bbp_make_first_page_canonical', $filter, 10, 2 );
		try {
			$this->assertSame( '/filtered/', bbp_make_first_page_canonical( '/forums/page/1/' ) );
		} finally {
			remove_filter( 'bbp_make_first_page_canonical', $filter );
			remove_filter( 'bbp_pretty_urls', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_paginate_links
	 */
	public function test_bbp_paginate_links_returns_requested_link_type() {
		$links = bbp_paginate_links( array(
			'base'      => 'https://example.org/forums/%_%',
			'format'    => '?paged=%#%',
			'total'     => 3,
			'current'   => 2,
			'prev_next' => false,
			'type'      => 'array',
		) );

		$this->assertIsArray( $links );
		$this->assertCount( 3, $links );
		$this->assertStringContainsString( 'paged=3', implode( ' ', $links ) );
	}

	/**
	 * @covers ::bbp_paginate_links
	 */
	public function test_bbp_paginate_links_canonicalizes_the_first_page() {
		add_filter( 'bbp_pretty_urls', '__return_true' );
		try {
			$links = bbp_paginate_links( array(
				'base'      => 'https://example.org/forums/page/%#%/',
				'total'     => 2,
				'current'   => 2,
				'prev_next' => false,
			) );

			$this->assertStringContainsString( 'href="https://example.org/forums/"', $links );
			$this->assertStringNotContainsString( '/page/1/', $links );
		} finally {
			remove_filter( 'bbp_pretty_urls', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_paginate_links
	 */
	public function test_bbp_paginate_links_adds_view_all_when_requested() {
		$view_all = function() {
			return true;
		};

		add_filter( 'bbp_get_view_all', $view_all );
		try {
			$links = bbp_paginate_links( array(
				'base'      => 'https://example.org/forums/%_%',
				'format'    => '?paged=%#%',
				'total'     => 2,
				'current'   => 1,
				'prev_next' => false,
			) );

			$this->assertStringContainsString( 'view=all', $links );
		} finally {
			remove_filter( 'bbp_get_view_all', $view_all );
		}
	}

	/**
	 * @covers ::bbp_get_major_wp_version
	 */
	public function test_bbp_get_major_wp_version() {
		$version = $GLOBALS['wp_version'];

		try {
			$GLOBALS['wp_version'] = '7.1.2';
			$this->assertSame( 7.1, bbp_get_major_wp_version() );
		} finally {
			$GLOBALS['wp_version'] = $version;
		}
	}

	/**
	 * @covers ::bbp_switch_to_site
	 * @covers ::bbp_restore_current_site
	 */
	public function test_bbp_switch_to_site_and_restore_current_site() {
		$original_site = get_current_blog_id();

		if ( is_multisite() ) {
			$site_id = self::factory()->blog->create();
			bbp_switch_to_site( $site_id );
			try {
				$this->assertSame( $site_id, get_current_blog_id() );
			} finally {
				bbp_restore_current_site();
			}
		} else {
			bbp_switch_to_site( 999 );
			bbp_restore_current_site();
		}

		$this->assertSame( $original_site, get_current_blog_id() );
		if ( is_multisite() ) {
			$this->assertFalse( ms_is_switched() );
		}
	}

	/**
	 * @covers ::bbp_default_intercept
	 * @covers ::bbp_is_intercepted
	 */
	public function test_bbp_default_intercept_and_is_intercepted() {
		$default = bbp_default_intercept();

		$this->assertIsString( $default );
		$this->assertNotSame( '', $default );
		$this->assertSame( $default, bbp_default_intercept() );
		$this->assertFalse( bbp_is_intercepted( $default ) );
		$this->assertTrue( bbp_is_intercepted( 'other' ) );
	}

	/**
	 * @covers ::bbp_maybe_intercept
	 */
	public function test_bbp_maybe_intercept() {
		$default = bbp_default_intercept();
		$filter  = function( $value ) {
			return 'intercepted';
		};

		$this->assertSame( $default, bbp_maybe_intercept( 'bbp_test_action' ) );
		$this->assertSame( $default, bbp_maybe_intercept( 'bbp_test_action', array( 'original' ) ) );

		add_filter( 'pre_bbp_test_action', $filter );
		try {
			$this->assertSame( 'intercepted', bbp_maybe_intercept( 'bbp_test_action', array( 'original' ) ) );
			$this->assertSame( 'intercepted', bbp_maybe_intercept( 'pre_bbp_test_action', array( 'original' ) ) );
		} finally {
			remove_filter( 'pre_bbp_test_action', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_empty_datetime
	 */
	public function test_bbp_get_empty_datetime() {
		$filter = function( $value, $db_version ) {
			$this->assertSame( '0000-00-00 00:00:00', $value );
			$this->assertSame( bbp_db()->db_version(), $db_version );
			return '1970-01-01 00:00:00';
		};

		$this->assertSame( '0000-00-00 00:00:00', bbp_get_empty_datetime() );
		add_filter( 'bbp_get_default_zero_date', $filter, 10, 2 );
		try {
			$this->assertSame( '1970-01-01 00:00:00', bbp_get_empty_datetime() );
		} finally {
			remove_filter( 'bbp_get_default_zero_date', $filter );
		}
	}
}
