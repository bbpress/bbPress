<?php
/**
 * Tests for core abstraction function wrappers
 *
 * @group core
 * @group abstraction
 */

class BBP_Tests_Core_Abstraction extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_global_object
	 * @todo   Implement test_bbp_get_global_object().
	 */
	public function test_bbp_get_global_object() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_db
	 * @todo   Implement test_bbp_db().
	 */
	public function test_bbp_db() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
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
		$this->assertTrue( bbp_bump_count_meta( 'term', $term_id, '_bbp_test_count', 2 ) );
		$this->assertSame( 2, (int) get_term_meta( $term_id, '_bbp_test_count', true ) );
		$this->assertTrue( bbp_bump_count_meta( 'comment', $comment_id, '_bbp_test_count', 3 ) );
		$this->assertSame( 3, (int) get_comment_meta( $comment_id, '_bbp_test_count', true ) );
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
	 * @todo   Implement test_bbp_rewrite().
	 */
	public function test_bbp_rewrite() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_root_url
	 * @todo   Implement test_bbp_get_root_url().
	 */
	public function test_bbp_get_root_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_paged_slug
	 * @todo   Implement test_bbp_get_paged_slug().
	 */
	public function test_bbp_get_paged_slug() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_use_pretty_urls
	 * @todo   Implement test_bbp_use_pretty_urls().
	 */
	public function test_bbp_use_pretty_urls() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
