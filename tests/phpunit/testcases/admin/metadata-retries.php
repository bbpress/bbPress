<?php

/**
 * Source preservation and verified retries across shared metadata repairs.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Metadata_Retries extends BBP_UnitTestCase {

	private $size = 1;

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		parent::tearDown();
	}

	public function batch_size() {
		return $this->size;
	}

	public function duplicate_lists() {
		return array( array( 1, false ), array( 1000, false ), array( 1, true ), array( 1000, true ) );
	}

	public function metadata_fields() {
		return array(
			array( null, array(), 1, false ),
			array( 0, array(), 1, true ),
			array( 1, array( array( '_bbp_preview', 1 ) ), 1, false ),
			array( 1, array( array( '_bbp_preview', 1 ), array( '_bbp_preview', 1 ) ), 1, true ),
			array( 1, array( array( '_bbp_preview', 9 ) ), 2, true ),
			array( 1, array( array( '_bbp_preview', 9 ), array( '_bbp_preview', 9 ) ), 2, true ),
			array( 1, array( array( '_BBP_PREVIEW', 9 ), array( '_BBP_PREVIEW', 9 ) ), 3, true ),
			array( null, array( array( '_bbp_preview', 9 ), array( '_BBP_PREVIEW', 9 ) ), 2, true ),
			array( array( 3, 4 ), array( array( '_bbp_preview', array( 3, 4 ) ) ), 1, false ),
		);
	}

	/** @dataProvider metadata_fields */
	public function test_metadata_status_matches_the_bounded_plan( $value, $rows, $work, $changed ) {
		$db   = bbp_db();
		$post = $this->factory->post->create();
		foreach ( $rows as $row ) {
			add_post_meta( $post, $row[0], $row[1] );
		}
		$before = $db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A );
		$status = bbp_admin_repair_metadata_status( 'post', '_bbp_preview', array( $post => $value ) );
		$this->assertSame( array( 'changed' => $changed ), $status[ $post ] );
		$this->assertSame( $before, $db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ) );
		$run = bbp_admin_repair_single_metadata( 'post', $post, '_bbp_preview', $value, count( $rows ) + 1 );
		$this->assertSame( 0, $run['code'] );
		$this->assertTrue( $run['complete'] );
		$this->assertSame( $work, $run['work'] );
		$this->assertSame( $changed, $run['changed'] );
	}

	public function test_dry_run_replays_the_bounded_metadata_plan_without_assessment_queries() {
		$db   = bbp_db();
		$post = $this->factory->post->create();
		$key  = '_bbp_preview';
		add_post_meta( $post, strtoupper( $key ), 9 );
		add_post_meta( $post, $key, 8 );
		add_post_meta( $post, $key, 7 );
		$before      = $this->rows( 'post', $post, $key );
		$assessments = 0;
		$capture     = static function ( $query ) use ( &$assessments ) {
			if ( false !== strpos( $query, 'SUM(BINARY meta_key' ) ) {
				++$assessments;
			}
			return $query;
		};
		$preview = null;
		$planned = 0;
		$changed = 0;
		add_filter( 'query', $capture );
		try {
			for ( $i = 0; $i < 10; ++$i ) {
				$result   = bbp_admin_repair_single_metadata( 'post', $post, $key, 1, 1, true, $preview );
				$planned += $result['operations'];
				$changed += (int) $result['changed'];
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			remove_filter( 'query', $capture );
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 0, $assessments );
		$this->assertSame( 1, $changed );
		$this->assertSame( $before, $this->rows( 'post', $post, $key ) );

		$performed = 0;
		for ( $i = 0; $i < 10; ++$i ) {
			$result     = bbp_admin_repair_single_metadata( 'post', $post, $key, 1, 1 );
			$performed += $result['operations'];
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( $performed, $planned );
		$this->assertSame( array( '1' ), get_post_meta( $post, $key, false ) );
	}

	public function test_custom_engagement_source_queries_remain_read_only() {
		$db    = bbp_db();
		$user  = $this->factory->user->create();
		$topic = $this->factory->topic->create( array( 'post_author' => $user ) );
		delete_post_meta( $topic, '_bbp_engagement' );
		$row = $db->get_row( $db->prepare( "SELECT meta_id, post_id, meta_value, 1 AS is_source FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_topic_id'", $topic ) );
		$source = bbp_admin_repair_get_engagements_query( bbp_get_public_topic_statuses(), bbp_get_public_reply_statuses() );
		$source = 'SELECT DISTINCT ' . substr( $source, 7 );
		$before = $db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A );
		$preview = bbp_admin_repair_engagement_rows( array( $row ), $source, true );
		$this->assertSame( 0, $preview['code'] );
		$this->assertSame( 1, $preview['added'] );
		$this->assertSame( array( $topic => true ), $preview['topics'] );
		$this->assertSame( $before, $db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ) );
	}

	public function alias_keys() {
		return array( array( '_BBP_STICKY_TOPICS' ), array( '_bbp_sticky_topics ' ) );
	}

	public function transformed_values() {
		return array( array( 'list' ), array( 'post-count' ), array( 'user-count' ) );
	}

	public function deletion_reports() {
		return array( array( false ), array( true ) );
	}

	private function rows( $type, $id, $key ) {
		$db     = bbp_db();
		$table  = $db->{$type . 'meta'};
		$column = 'user' === $type ? 'umeta_id' : 'meta_id';
		return $db->get_results( $db->prepare( "SELECT * FROM {$table} WHERE {$type}_id = %d AND meta_key = %s ORDER BY {$column}", $id, $key ), ARRAY_A );
	}

	private function finish_sticky() {
		$changed = 0;
		for ( $i = 0; $i < 100; ++$i ) {
			$db = bbp_db();
			$before = array( $db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ), $db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A ) );
			$preview = bbp_admin_repair_sticky_batch( true );
			$this->assertSame( $before, array( $db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ), $db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A ) ) );
			$result = bbp_admin_repair_sticky_batch();
			$this->assertSame( 0, $result['code'] );
			$this->assertTrue( $preview['complete'] );
			$this->assertGreaterThanOrEqual( $result['repaired'], $preview['repaired'] );
			$changed += $result['repaired'];
			if ( $result['complete'] ) {
				return $changed;
			}
		}
		$this->fail( 'Sticky cleanup did not finish.' );
	}

	/** @dataProvider duplicate_lists */
	public function test_different_duplicate_lists_preserve_the_original_source_at_every_batch_size( $size, $canonical ) {
		$forum = $this->factory->forum->create();
		$other = $this->factory->forum->create();
		$valid = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		$wrong = $this->factory->topic->create( array( 'post_parent' => $other ) );
		$stale = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		$key   = '_bbp_sticky_topics';
		add_post_meta( $forum, $key, array( $valid, $wrong ) );
		add_post_meta( $forum, $key, array( $stale ) );
		add_post_meta( $forum, $key, array( $stale, $wrong ) );
		if ( $canonical ) {
			add_post_meta( $forum, $key, array( $valid ) );
		}
		$this->size = $size;
		$this->assertSame( 1, $this->finish_sticky() );
		$this->assertSame( array( array( $valid ) ), get_post_meta( $forum, $key, false ) );
	}

	/** @dataProvider alias_keys */
	public function test_older_key_aliases_cannot_replace_the_original_source_after_failed_writes( $alias ) {
		$forum = $this->factory->forum->create();
		$other = $this->factory->forum->create();
		$valid = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		$wrong = $this->factory->topic->create( array( 'post_parent' => $other ) );
		$key   = '_bbp_sticky_topics';
		add_post_meta( $forum, $alias, 'duplicate' );
		add_post_meta( $forum, $key, array( $valid, $wrong ) );
		$before = $this->rows( 'post', $forum, $key )[1];
		$this->size = 1000;
		$alter = static function () { return array( 999999 ); };
		add_filter( 'sanitize_post_meta__bbp_sticky_topics', $alter );
		try {
			$result = bbp_admin_repair_sticky_batch();
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'metadata-write', $result['error'] );
			$this->assertFalse( $result['complete'] );
			$rows = $this->rows( 'post', $forum, $key );
			$this->assertSame( $before, $rows[1] );
			$this->assertSame( array( $valid, $wrong ), get_post_meta( $forum, $key, true ) );
		} finally {
			remove_filter( 'sanitize_post_meta__bbp_sticky_topics', $alter );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-sticky', 'bbp_admin_repair_sticky' ) );
		$this->assertSame( array( array( $valid ) ), get_post_meta( $forum, $key, false ) );
		$this->assertCount( 1, $this->rows( 'post', $forum, $key ) );
		$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( 'bbp-sync-all-topics-sticky' ), false ) );
	}

	/** @dataProvider transformed_values */
	public function test_transformed_insertions_do_not_accumulate_on_retry( $kind ) {
		$user  = 'user-count' === $kind;
		$type  = $user ? 'user' : 'post';
		$id    = $user ? $this->factory->user->create() : $this->factory->forum->create();
		$key   = 'list' === $kind ? '_bbp_sticky_topics' : ( $user ? bbp_db()->prefix . '_bbp_topic_count' : '_bbp_topic_count' );
		$value = 'list' === $kind ? array( 123 ) : 7;
		$old   = 'list' === $kind ? array( 123, 456 ) : 99;
		$bad   = 'list' === $kind ? array( 999999 ) : 8;
		update_metadata( $type, $id, $key, $old );
		$before = $this->rows( $type, $id, $key );
		$hook   = 'sanitize_' . $type . '_meta_' . $key . ( 'post-count' === $kind ? '_for_' . bbp_get_forum_post_type() : '' );
		$alter  = static function () use ( $bad ) { return $bad; };
		add_filter( $hook, $alter, 20 );
		try {
			for ( $i = 0; $i < 2; ++$i ) {
				$result = $this->assertMetadataWritesBounded( $type, 2, static function () use ( $type, $id, $key, $value ) {
					return bbp_admin_repair_single_metadata( $type, $id, $key, $value, 1 );
				} );
				$this->assertNotSame( 0, $result['code'] );
				$this->assertSame( $before, $this->rows( $type, $id, $key ) );
			}
		} finally {
			remove_filter( $hook, $alter, 20 );
		}
	}

	/** @dataProvider transformed_values */
	public function test_failed_insertions_without_original_rows_remain_bounded( $kind ) {
		$user  = 'user-count' === $kind;
		$type  = $user ? 'user' : 'post';
		$id    = $user ? $this->factory->user->create() : $this->factory->forum->create();
		$key   = 'list' === $kind ? '_bbp_sticky_topics' : ( $user ? bbp_db()->prefix . '_bbp_topic_count' : '_bbp_topic_count' );
		$value = 'list' === $kind ? array( 123 ) : 7;
		$bad   = 'list' === $kind ? array( 999999 ) : 8;
		delete_metadata( $type, $id, $key );
		$hook  = 'sanitize_' . $type . '_meta_' . $key . ( 'post-count' === $kind ? '_for_' . bbp_get_forum_post_type() : '' );
		$alter = static function () use ( $bad ) { return $bad; };
		add_filter( $hook, $alter, 20 );
		add_filter( 'delete_' . $type . '_metadata_by_mid', '__return_false' );
		try {
			for ( $i = 0; $i < 4; ++$i ) {
				$result = bbp_admin_repair_single_metadata( $type, $id, $key, $value, 1 );
				$this->assertNotSame( 0, $result['code'] );
				$this->assertFalse( $result['complete'] );
				$this->assertCount( min( $i + 1, 2 ), $this->rows( $type, $id, $key ) );
			}
		} finally {
			remove_filter( $hook, $alter, 20 );
			remove_filter( 'delete_' . $type . '_metadata_by_mid', '__return_false' );
		}
		for ( $i = 0; $i < 4; ++$i ) {
			$result = bbp_admin_repair_single_metadata( $type, $id, $key, $value, 1 );
			$this->assertSame( 0, $result['code'] );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( array( is_array( $value ) ? $value : (string) $value ), get_metadata( $type, $id, $key, false ) );
	}

	public function test_partial_cleanup_verifies_each_falsely_reported_deletion() {
		$forum = $this->factory->forum->create();
		$key   = '_bbp_sticky_topics';
		add_post_meta( $forum, $key, array( 123 ) );
		for ( $i = 0; $i < 3; ++$i ) {
			add_post_meta( $forum, $key, array( 456 + $i ) );
		}
		$before = $this->rows( 'post', $forum, $key );
		add_filter( 'delete_post_metadata_by_mid', '__return_true' );
		try {
			for ( $i = 0; $i < 2; ++$i ) {
				$result = bbp_admin_repair_single_metadata( 'post', $forum, $key, array( 123 ), 1 );
				$this->assertNotSame( 0, $result['code'] );
				$this->assertFalse( $result['complete'] );
				$this->assertSame( $before, $this->rows( 'post', $forum, $key ) );
			}
		} finally {
			remove_filter( 'delete_post_metadata_by_mid', '__return_true' );
		}
	}

	/** @dataProvider deletion_reports */
	public function test_failed_rollback_stays_bounded_and_recovers_automatically( $reported ) {
		$forum = $this->factory->forum->create();
		$other = $this->factory->forum->create();
		$valid = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		$wrong = $this->factory->topic->create( array( 'post_parent' => $other ) );
		$key   = '_bbp_sticky_topics';
		add_post_meta( $forum, $key, array( $valid, $wrong ) );
		$before = $this->rows( 'post', $forum, $key )[0];
		$this->size = 1000;
		$alter = static function () { return array( 999999 ); };
		$deny  = static function () use ( $reported ) { return $reported; };
		add_filter( 'sanitize_post_meta__bbp_sticky_topics', $alter );
		add_filter( 'delete_post_metadata_by_mid', $deny );
		try {
			for ( $i = 0; $i < 4; ++$i ) {
				$result = bbp_admin_repair_sticky_batch();
				$this->assertNotSame( 0, $result['code'] );
				$this->assertFalse( $result['complete'] );
				$rows = $this->rows( 'post', $forum, $key );
				$this->assertCount( 2, $rows );
				$this->assertSame( $before, $rows[0] );
			}
		} finally {
			remove_filter( 'sanitize_post_meta__bbp_sticky_topics', $alter );
			remove_filter( 'delete_post_metadata_by_mid', $deny );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-sticky', 'bbp_admin_repair_sticky' ) );
		$this->assertSame( array( array( $valid ) ), get_post_meta( $forum, $key, false ) );
		$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( 'bbp-sync-all-topics-sticky' ), false ) );
	}

	/** @dataProvider deletion_reports */
	public function test_existing_row_ids_returned_by_add_filters_are_never_rolled_back( $notify ) {
		$forum = $this->factory->forum->create();
		$key   = '_bbp_sticky_topics';
		$id    = add_post_meta( $forum, $key, array( 123, 456 ) );
		$before = $this->rows( 'post', $forum, $key );
		$reuse = static function ( $check, $object, $meta_key ) use ( $id, $key, $notify ) {
			if ( $key !== $meta_key ) {
				return $check;
			}
			if ( $notify ) {
				do_action( 'added_post_meta', $id, $object, $meta_key );
			}
			return $id;
		};
		add_filter( 'add_post_metadata', $reuse, 10, 3 );
		try {
			for ( $i = 0; $i < 2; ++$i ) {
				$this->assertNotSame( 0, bbp_admin_repair_single_metadata( 'post', $forum, $key, array( 123 ), 1 )['code'] );
				$this->assertSame( $before, $this->rows( 'post', $forum, $key ) );
			}
		} finally {
			remove_filter( 'add_post_metadata', $reuse, 10 );
		}
	}

	public function test_post_insert_corruption_rolls_back_only_new_rows_without_repeating_actions() {
		$db    = bbp_db();
		$forum = $this->factory->forum->create();
		$key   = '_bbp_sticky_topics';
		add_post_meta( $forum, $key, array( 123, 456 ) );
		$before = $this->rows( 'post', $forum, $key );
		$added = 0;
		$corrupt = static function ( $id, $object, $meta_key ) use ( $db, $forum, $key, &$added ) {
			if ( $object === $forum && $meta_key === $key ) {
				++$added;
				$db->update( $db->postmeta, array( 'meta_value' => 'corrupt' ), array( 'meta_id' => $id ) );
			}
		};
		add_action( 'added_post_meta', $corrupt, 10, 3 );
		try {
			for ( $i = 0; $i < 2; ++$i ) {
				$this->assertNotSame( 0, bbp_admin_repair_single_metadata( 'post', $forum, $key, array( 123 ), 1 )['code'] );
				$this->assertSame( $before, $this->rows( 'post', $forum, $key ) );
			}
			$this->assertSame( 2, $added );
		} finally {
			remove_action( 'added_post_meta', $corrupt, 10 );
		}
	}

	public function test_engagement_retries_roll_back_only_the_failed_insertion() {
		$db    = bbp_db();
		$user  = $this->factory->user->create();
		$topic = $this->factory->topic->create( array( 'post_author' => $user ) );
		delete_post_meta( $topic, '_bbp_engagement' );
		$source  = $this->rows( 'post', $topic, '_bbp_topic_id' );
		$added   = 0;
		$deleted = 0;
		$corrupt = static function ( $id, $object, $key ) use ( $db, $topic, &$added ) {
			if ( (int) $object === $topic && '_bbp_engagement' === $key ) {
				++$added;
				$db->update( $db->postmeta, array( 'meta_value' => 'corrupt' ), array( 'meta_id' => $id ) );
			}
		};
		$count_delete = static function ( $ids, $object, $key ) use ( $topic, &$deleted ) {
			if ( (int) $object === $topic && '_bbp_engagement' === $key ) {
				$deleted += count( (array) $ids );
			}
		};
		add_action( 'added_post_meta', $corrupt, 10, 3 );
		add_action( 'deleted_post_meta', $count_delete, 10, 3 );
		try {
			$checkpoint = null;
			for ( $i = 0; $i < 3; ++$i ) {
				$result = bbp_admin_upgrade_user_engagements_batch();
				$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
				$this->assertSame( 'metadata-write', $result['error'] );
				$this->assertFalse( $result['complete'] );
				$this->assertSame( array(), $this->rows( 'post', $topic, '_bbp_engagement' ) );
				$this->assertSame( $source, $this->rows( 'post', $topic, '_bbp_topic_id' ) );
				$current = bbp_admin_repair_tool_get_checkpoint( 'bbp-user-topic-engagements-move' );
				if ( null !== $checkpoint ) {
					$this->assertSame( $checkpoint, $current );
				}
				$checkpoint = $current;
			}
			$this->assertSame( 3, $added );
			$this->assertSame( 3, $deleted );
		} finally {
			remove_action( 'added_post_meta', $corrupt, 10 );
			remove_action( 'deleted_post_meta', $count_delete, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-topic-engagements-move', 'bbp_admin_upgrade_user_engagements' ) );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-topic-engagements-move', 'bbp_admin_upgrade_user_engagements' ) );
		$this->assertSame( array( (string) $user ), get_post_meta( $topic, '_bbp_engagement', false ) );
	}

	public function metadata_types() {
		return array( array( 'post' ), array( 'user' ) );
	}

	/** @dataProvider metadata_types */
	public function test_field_verification_distinguishes_absence_zero_lists_aliases_and_duplicates( $type ) {
		$key = '_bbp_test_verified_field';
		$ids = 'post' === $type ? $this->factory->topic->create_many( 5 ) : $this->factory->user->create_many( 5 );
		add_metadata( $type, $ids[0], $key, 0 );
		add_metadata( $type, $ids[1], $key, array( 12, 34 ) );
		add_metadata( $type, $ids[2], strtoupper( $key ), 7 );
		add_metadata( $type, $ids[3], $key, 9 );
		add_metadata( $type, $ids[3], $key, 9 );
		$expected = array( $ids[0] => 0, $ids[1] => array( 12, 34 ), $ids[2] => 7, $ids[3] => 9, $ids[4] => null );
		$queries = 0;
		$count   = static function ( $query ) use ( &$queries ) {
			if ( false !== strpos( $query, ' AS object_id, COUNT(*) AS row_count' ) ) {
				++$queries;
			}
			return $query;
		};
		add_filter( 'query', $count );
		try {
			$this->assertSame( array( $ids[2], $ids[3] ), bbp_admin_repair_metadata_mismatches( $type, $key, $expected ) );
		} finally {
			remove_filter( 'query', $count );
		}
		$this->assertSame( 1, $queries );
		$this->assertSame( array( $ids[0] ), bbp_admin_repair_metadata_mismatches( $type, $key, array( $ids[0] => null ) ) );
		$this->assertSame( array( $ids[4] ), bbp_admin_repair_metadata_mismatches( $type, $key, array( $ids[4] => 0 ) ) );
	}

}
