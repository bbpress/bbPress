<?php

/**
 * Tests for resumable topic-tag recounts.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Tag_Count_Batches extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		parent::tearDown();
	}

	public function batch_size() {
		return 2;
	}

	private function tags( $number = 5 ) {
		$db   = bbp_db();
		$tags = array();
		for ( $i = 0; $i < $number; ++$i ) {
			$name = wp_generate_uuid4();
			$db->insert( $db->terms, array( 'name' => $name, 'slug' => $name ) );
			$term_id = (int) $db->insert_id;
			$db->insert( $db->term_taxonomy, array( 'term_id' => $term_id, 'taxonomy' => bbp_get_topic_tag_tax_id(), 'count' => 99 ) );
			$tags[] = (int) $db->insert_id;
		}
		return $tags;
	}

	private function counts( $ids ) {
		$db    = bbp_db();
		$slots = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		return array_map( 'intval', $db->get_col( $db->prepare( "SELECT count FROM {$db->term_taxonomy} WHERE term_taxonomy_id IN ({$slots}) ORDER BY term_taxonomy_id", $ids ) ) );
	}

	private function snapshot() {
		$db     = bbp_db();
		$result = array();
		foreach ( array( 'term_taxonomy' => 'term_taxonomy_id', 'options' => 'option_id', 'postmeta' => 'meta_id' ) as $table => $id ) {
			$result[ $table ] = hash( 'sha256', serialize( $db->get_results( "SELECT * FROM {$db->$table} ORDER BY {$id}", ARRAY_A ) ) );
		}
		return $result;
	}

	private function finish() {
		for ( $i = 0; $i < 30; ++$i ) {
			$result = bbp_admin_repair_tool_normalize_result( bbp_admin_repair_topic_tag_count() );
			$this->assertTrue( $result['success'], $result['message'] );
			if ( $result['complete'] ) {
				return;
			}
		}
		$this->fail( 'The tag recount did not finish.' );
	}

	public function test_full_preview_matches_completed_run_and_repeated_runs_do_not_rewrite_counts() {
		$tags     = $this->tags();
		$taxonomy = get_taxonomy( bbp_get_topic_tag_tax_id() );
		$other    = wp_insert_term( 'Untouched category', 'category' );
		bbp_db()->update( bbp_db()->term_taxonomy, array( 'count' => 77 ), array( 'term_taxonomy_id' => $other['term_taxonomy_id'] ) );
		$preview = bbp_admin_repair_topic_tag_count_batch( $taxonomy, 'bbp_update_topic_tag_count', true );
		$this->assertTrue( $preview['complete'] );
		$this->assertSame( 5, $preview['repaired'] );
		$this->assertSame( 3, $preview['batches'] );
		$repaired = 0;
		for ( $batch = 0; $batch < 5; ++$batch ) {
			$before  = $this->snapshot();
			$actions = did_action( 'edit_term_taxonomy' );
			$remaining = bbp_admin_repair_topic_tag_count_batch( $taxonomy, 'bbp_update_topic_tag_count', true );
			$this->assertSame( $before, $this->snapshot() );
			$this->assertSame( $actions, did_action( 'edit_term_taxonomy' ) );
			$result = bbp_admin_repair_topic_tag_count_batch( $taxonomy, 'bbp_update_topic_tag_count' );
			$this->assertSame( 0, $result['code'] );
			$this->assertTrue( $remaining['complete'] );
			$this->assertSame( 5 - $repaired, $remaining['repaired'] );
			$repaired += $result['repaired'];
			$this->assertLessThanOrEqual( 2, did_action( 'edit_term_taxonomy' ) - $actions );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( $preview['repaired'], $repaired );
		$this->assertSame( $preview['batches'], $batch + 1 );
		$this->assertSame( array_fill( 0, 5, 0 ), $this->counts( $tags ) );
		$this->assertSame( array( 77 ), $this->counts( array( $other['term_taxonomy_id'] ) ) );
		$before  = $this->snapshot();
		$actions = did_action( 'edit_term_taxonomy' );
		$this->finish();
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( $actions, did_action( 'edit_term_taxonomy' ) );
	}

	public function boundary_sizes() {
		return array( array( 1 ), array( 1000 ) );
	}

	/**
	 * @dataProvider boundary_sizes
	 */
	public function test_boundary_sizes_limit_count_updates( $size ) {
		$tags  = $this->tags( $size + 1 );
		$limit = static function () use ( $size ) {
			return $size;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		try {
			$actions = did_action( 'edit_term_taxonomy' );
			$result  = bbp_admin_repair_topic_tag_count();
			$this->assertSame( 0, $result[0] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( $size, did_action( 'edit_term_taxonomy' ) - $actions );
			$this->assertSame( $tags[ $size - 1 ], get_option( bbp_admin_repair_tool_batch_option_name( 'bbp-topic-tags' ) )['cursor'] );
			$this->finish();
			$this->assertSame( array_fill( 0, $size + 1, 0 ), $this->counts( $tags ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		}
	}

	public function test_partial_count_failures_preserve_progress_and_retry_automatically() {
		$db   = bbp_db();
		$tags = $this->tags();
		$fail = static function ( $query ) use ( $db, $tags ) {
			return ( 0 === strpos( $query, "UPDATE `{$db->term_taxonomy}` SET `count`" ) ) && ( false !== strpos( $query, "term_taxonomy_id` = {$tags[1]}" ) )
				? 'UPDATE bbp_missing_tag_counts SET count = 0'
				: $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$result = bbp_admin_repair_topic_tag_count();
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( 'bbp-topic-tags' ) )['cursor'] );
			$this->assertSame( array( 0, 99 ), $this->counts( array_slice( $tags, 0, 2 ) ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
			$this->assertContains( 'bbp-topic-tags', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
		$this->assertNotContains( 'bbp-topic-tags', bbp_get_pending_upgrades() );
		$this->assertSame( array_fill( 0, 5, 0 ), $this->counts( $tags ) );
	}

	public function test_checkpoint_save_failures_allow_safe_replays() {
		$tags   = $this->tags();
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-topic-tags' );
		$fail   = static function ( $value, $old ) {
			return $old;
		};
		$before = $this->snapshot();
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_topic_tag_count();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->assertFalse( bbp_admin_repair_topic_tag_count()['complete'] );
		$state = get_option( $option );
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_topic_tag_count();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish();
		$this->assertSame( array_fill( 0, 5, 0 ), $this->counts( $tags ) );
	}

	public function test_final_checkpoint_delete_failures_leave_the_recount_pending() {
		$db     = bbp_db();
		$tags   = $this->tags( 3 );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-topic-tags' );
		$this->assertFalse( bbp_admin_repair_topic_tag_count()['complete'] );
		$state = get_option( $option );
		$fail  = static function ( $query ) use ( $db, $option ) {
			return ( 0 === strpos( $query, 'DELETE FROM' ) ) && ( false !== strpos( $query, $db->options ) ) && ( false !== strpos( $query, $option ) )
				? 'DELETE FROM bbp_missing_tag_options'
				: $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
			$this->assertContains( 'bbp-topic-tags', bbp_get_pending_upgrades() );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
		$this->assertNotContains( 'bbp-topic-tags', bbp_get_pending_upgrades() );
		$this->assertSame( array( 0, 0, 0 ), $this->counts( $tags ) );
	}

	public function test_automatic_budget_exhaustion_remains_resumable() {
		$tags = $this->tags();
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
			$this->assertContains( 'bbp-topic-tags', bbp_get_pending_upgrades() );
			$this->assertSame( array( 0, 0, 99, 99, 99 ), $this->counts( $tags ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
		$this->assertNotContains( 'bbp-topic-tags', bbp_get_pending_upgrades() );
	}

	public function test_new_tags_wait_for_the_next_run() {
		$tags = $this->tags( 3 );
		$this->assertFalse( bbp_admin_repair_topic_tag_count()['complete'] );
		$new = $this->tags( 1 );
		$this->finish();
		$this->assertSame( array( 99 ), $this->counts( $new ) );
		$this->finish();
		$this->assertSame( array( 0, 0, 0, 0 ), $this->counts( array_merge( $tags, $new ) ) );
	}

	public function test_full_preview_reports_changes_beyond_a_clean_first_batch() {
		$tags = $this->tags( 3 );
		foreach ( array_slice( $tags, 0, 2 ) as $id ) {
			bbp_db()->update( bbp_db()->term_taxonomy, array( 'count' => 0 ), array( 'term_taxonomy_id' => $id ) );
		}
		$before  = $this->snapshot();
		$preview = bbp_admin_repair_topic_tag_count_dry_run();
		$this->assertSame( 0, $preview[0] );
		$this->assertTrue( $preview['complete'] );
		$this->assertSame( 2, $preview['batches'] );
		$this->assertStringNotContainsString( 'This batch:', $preview[1] );
		$this->assertStringContainsString( '1 topic-tag.', $preview[1] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertFalse( bbp_admin_repair_topic_tag_count()['complete'] );
		$this->assertStringContainsString( '1 topic-tag.', bbp_admin_repair_topic_tag_count_dry_run()[1] );
	}

	public function test_full_preview_ignores_an_existing_run_cursor() {
		$tags = $this->tags();
		$this->assertFalse( bbp_admin_repair_topic_tag_count()['complete'] );
		bbp_db()->update( bbp_db()->term_taxonomy, array( 'count' => 99 ), array( 'term_taxonomy_id' => $tags[0] ) );
		$before  = $this->snapshot();
		$preview = bbp_admin_repair_topic_tag_count_dry_run();
		$this->assertTrue( $preview['complete'] );
		$this->assertSame( 3, $preview['batches'] );
		$this->assertStringContainsString( '4 topic-tags.', $preview[1] );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_clean_final_batches_report_run_completion() {
		$tags = $this->tags( 3 );
		bbp_db()->update( bbp_db()->term_taxonomy, array( 'count' => 0 ), array( 'term_taxonomy_id' => $tags[2] ) );
		$first = bbp_admin_repair_topic_tag_count();
		$this->assertSame( 0, $first[0] );
		$this->assertFalse( $first['complete'] );
		$this->assertStringContainsString( '2 topic-tags.', $first[1] );
		$last = bbp_admin_repair_topic_tag_count();
		$this->assertSame( 0, $last[0] );
		$this->assertTrue( $last['complete'] );
		$this->assertStringContainsString( 'Complete!', $last[1] );
		$this->assertStringContainsString( 'This batch:', $last[1] );
		$this->assertStringContainsString( '0 topic-tags.', $last[1] );
	}

	public function test_status_reader_preserves_the_all_tags_api_and_empty_id_scope() {
		$tags     = $this->tags( 3 );
		$taxonomy = get_taxonomy( bbp_get_topic_tag_tax_id() );
		$before   = $this->snapshot();
		$all      = bbp_admin_repair_topic_tag_count_get_status( $taxonomy );
		$this->assertSame( 0, $all['code'] );
		$this->assertSame( $tags, $all['term_taxonomy_ids'] );
		$this->assertSame( 3, $all['repair_count'] );
		$one = bbp_admin_repair_topic_tag_count_get_status( $taxonomy, array( $tags[1] ) );
		$this->assertSame( array( $tags[1] ), $one['term_taxonomy_ids'] );
		$this->assertSame( 1, $one['repair_count'] );
		$empty = bbp_admin_repair_topic_tag_count_get_status( $taxonomy, array() );
		$this->assertSame( array(), $empty['term_taxonomy_ids'] );
		$this->assertSame( 0, $empty['repair_count'] );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function malformed_states() {
		return array(
			array( 'cursor', -1 ),
			array( 'cursor', '1' ),
			array( 'cursor', 'past-bound' ),
			array( 'upper_id', null ),
			array( 'taxonomy', 'other-taxonomy' )
		);
	}

	/**
	 * @dataProvider malformed_states
	 */
	public function test_bad_checkpoints_restart_without_preview_writes( $field, $value ) {
		$tags   = $this->tags();
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-topic-tags' );
		$this->assertFalse( bbp_admin_repair_topic_tag_count()['complete'] );
		$state           = get_option( $option );
		$state[ $field ] = 'past-bound' === $value ? $state['upper_id'] + 1 : $value;
		update_option( $option, $state, false );
		$before  = $this->snapshot();
		$preview = bbp_admin_repair_topic_tag_count_dry_run();
		$this->assertTrue( $preview['complete'] );
		$this->assertStringNotContainsString( 'Saved progress', $preview[1] );
		$this->assertStringContainsString( '3 topic-tags.', $preview[1] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertStringContainsString( 'Saved progress was invalid.', bbp_admin_repair_topic_tag_count()[1] );
		$this->finish();
		$this->assertSame( array_fill( 0, 5, 0 ), $this->counts( $tags ) );
	}

	public function test_custom_callbacks_remain_bounded_and_respect_term_query_filters() {
		$tags     = $this->tags();
		$taxonomy = get_taxonomy( bbp_get_topic_tag_tax_id() );
		$original = $taxonomy->update_count_callback;
		$calls    = array();
		$taxonomy->update_count_callback = static function ( $ids ) use ( &$calls ) {
			$calls[] = $ids;
			foreach ( $ids as $id ) {
				bbp_db()->update( bbp_db()->term_taxonomy, array( 'count' => 0 ), array( 'term_taxonomy_id' => $id ) );
			}
		};
		$filter = static function ( $terms ) use ( $tags ) {
			return array_values( array_filter( $terms, static function ( $term ) use ( $tags ) {
				return (int) $term->term_taxonomy_id !== $tags[1];
			} ) );
		};
		add_filter( 'get_terms', $filter );
		try {
			$before = $this->snapshot();
			$this->assertFalse( bbp_admin_repair_topic_tag_count_dry_run()['success'] );
			$this->assertSame( $before, $this->snapshot() );
			$this->assertSame( array(), $calls );
			$this->finish();
			$this->assertCount( 3, $calls );
			foreach ( $calls as $ids ) {
				$this->assertLessThanOrEqual( 2, count( $ids ) );
			}
			$this->assertSame( array( 0, 99, 0, 0, 0 ), $this->counts( $tags ) );
		} finally {
			remove_filter( 'get_terms', $filter );
			$taxonomy->update_count_callback = $original;
		}
	}


	public function custom_failures() {
		return array( array( 'false' ), array( 'error' ) );
	}

	/**
	 * @dataProvider custom_failures
	 */
	public function test_explicit_custom_callback_failures_remain_pending( $type ) {
		$tags     = $this->tags( 3 );
		$taxonomy = get_taxonomy( bbp_get_topic_tag_tax_id() );
		$original = $taxonomy->update_count_callback;
		$fail     = true;
		$taxonomy->update_count_callback = static function ( $ids ) use ( &$fail, $type ) {
			if ( $fail ) {
				return ( 'error' === $type ) ? new WP_Error( 'count_failed' ) : false;
			}
			foreach ( $ids as $id ) {
				bbp_db()->update( bbp_db()->term_taxonomy, array( 'count' => 0 ), array( 'term_taxonomy_id' => $id ) );
			}
		};
		try {
			$result = bbp_admin_repair_topic_tag_count();
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( 'bbp-topic-tags' ) )['cursor'] );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
			$this->assertContains( 'bbp-topic-tags', bbp_get_pending_upgrades() );
			$fail = false;
			$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
			$this->assertNotContains( 'bbp-topic-tags', bbp_get_pending_upgrades() );
			$this->assertSame( array( 0, 0, 0 ), $this->counts( $tags ) );
		} finally {
			$taxonomy->update_count_callback = $original;
		}
	}

	public function test_custom_term_query_errors_stop_before_count_or_checkpoint_writes() {
		$this->tags();
		$taxonomy = get_taxonomy( bbp_get_topic_tag_tax_id() );
		$original = $taxonomy->update_count_callback;
		$taxonomy->update_count_callback = static function () {
			throw new Exception( 'The failing term query must not invoke the callback.' );
		};
		$filter = static function () {
			return new WP_Error( 'term_query_failed' );
		};
		add_filter( 'terms_pre_query', $filter );
		try {
			$before = $this->snapshot();
			$result = bbp_admin_repair_topic_tag_count();
			$this->assertSame( 3, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'terms_pre_query', $filter );
			$taxonomy->update_count_callback = $original;
		}
	}

	public function test_status_filters_and_registered_object_types_keep_the_existing_count_rules() {
		$db       = bbp_db();
		$taxonomy = get_taxonomy( bbp_get_topic_tag_tax_id() );
		$original = $taxonomy->object_type;
		$tags     = $this->tags( 1 );
		$term_id  = (int) $db->get_var( $db->prepare( "SELECT term_id FROM {$db->term_taxonomy} WHERE term_taxonomy_id = %d", $tags[0] ) );
		$topic_id = $this->factory->topic->create();
		$objects  = array(
			$topic_id,
			$this->factory->topic->create( array( 'post_status' => bbp_get_spam_status_id() ) ),
			$this->factory->post->create(),
			$this->factory->reply->create( array( 'post_parent' => $topic_id ) )
		);
		foreach ( $objects as $object_id ) {
			wp_set_object_terms( $object_id, array( $term_id ), $taxonomy->name );
		}
		$taxonomy->object_type = array( bbp_get_topic_post_type() . ':suffix', 'post' );
		$statuses = static function () {
			return array( 'PUBLISH', bbp_get_spam_status_id() );
		};
		add_filter( 'bbp_get_public_topic_statuses', $statuses );
		try {
			$this->finish();
			$this->assertSame( array( 3 ), $this->counts( $tags ) );
			add_filter( 'bbp_get_public_topic_statuses', '__return_empty_array', 20 );
			$this->finish();
			$this->assertSame( array( 0 ), $this->counts( $tags ) );
		} finally {
			remove_filter( 'bbp_get_public_topic_statuses', '__return_empty_array', 20 );
			remove_filter( 'bbp_get_public_topic_statuses', $statuses );
			$taxonomy->object_type = $original;
		}
	}

	public function test_tag_checkpoints_and_counts_are_isolated_per_blog() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		$tags   = $this->tags( 3 );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-topic-tags' );
		$this->assertFalse( bbp_admin_repair_topic_tag_count()['complete'] );
		$state   = get_option( $option );
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );
		try {
			$other = $this->tags( 3 );
			$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count' ) );
			$this->assertSame( array( 0, 0, 0 ), $this->counts( $other ) );
			$this->assertFalse( get_option( $option, false ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $state, get_option( $option ) );
		$this->assertSame( array( 0, 0, 99 ), $this->counts( $tags ) );
		$this->finish();
		$this->assertSame( array( 0, 0, 0 ), $this->counts( $tags ) );
	}

	public function test_read_errors_leave_counts_and_progress_unchanged() {
		$db   = bbp_db();
		$tags = $this->tags();
		foreach ( array( "SELECT MAX(term_taxonomy_id) FROM {$db->term_taxonomy}", 'SELECT taxonomy.term_taxonomy_id', 'SELECT `termtaxonomy`.`term_taxonomy_id`' ) as $prefix ) {
			$fail = static function ( $query ) use ( $prefix ) {
				return ( 0 === strpos( $query, $prefix ) ) ? 'SELECT ID FROM bbp_missing_tag_source' : $query;
			};
			$before   = $this->snapshot();
			$suppress = $db->suppress_errors( true );
			add_filter( 'query', $fail );
			try {
				foreach ( array( 'bbp_admin_repair_topic_tag_count_dry_run', 'bbp_admin_repair_topic_tag_count' ) as $callback ) {
					$result = call_user_func( $callback );
					$this->assertSame( 3, $result[0] );
					$this->assertFalse( $result['success'] );
					$this->assertFalse( $result['complete'] );
					$this->assertSame( $before, $this->snapshot() );
				}
			} finally {
				remove_filter( 'query', $fail );
				$db->suppress_errors( $suppress );
			}
		}
		$this->finish();
		$this->assertSame( array_fill( 0, 5, 0 ), $this->counts( $tags ) );
	}
}
