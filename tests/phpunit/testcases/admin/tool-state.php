<?php

/**
 * Shared state, native metadata writes and notice conventions across tools.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Tool_State extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
	}

	public function callbacks() {
		return array(
			array( 'bbp_admin_repair_topic_meta' ),
			array( 'bbp_admin_repair_forum_meta' ),
			array( 'bbp_admin_repair_topic_reply_count' ),
			array( 'bbp_admin_repair_topic_hidden_reply_count' ),
			array( 'bbp_admin_repair_topic_voice_count' ),
			array( 'bbp_admin_repair_forum_subforum_count' ),
			array( 'bbp_admin_repair_forum_topic_count' ),
			array( 'bbp_admin_repair_forum_reply_count' ),
			array( 'bbp_admin_repair_forum_hidden_reply_count' ),
			array( 'bbp_admin_repair_topic_tag_count' ),
			array( 'bbp_admin_repair_user_topic_count' ),
			array( 'bbp_admin_repair_user_reply_count' ),
			array( 'bbp_admin_repair_user_favorites' ),
			array( 'bbp_admin_repair_user_topic_subscriptions' ),
			array( 'bbp_admin_repair_user_forum_subscriptions' ),
			array( 'bbp_admin_repair_user_roles' ),
			array( 'bbp_admin_repair_freshness' ),
			array( 'bbp_admin_repair_sticky' ),
			array( 'bbp_admin_repair_closed_topics' ),
			array( 'bbp_admin_repair_forum_visibility' ),
			array( 'bbp_admin_repair_reply_menu_order' ),
			array( 'bbp_admin_upgrade_user_engagements' ),
			array( 'bbp_admin_upgrade_user_favorites' ),
			array( 'bbp_admin_upgrade_user_topic_subscriptions' ),
			array( 'bbp_admin_upgrade_user_forum_subscriptions' ),
			array( 'bbp_admin_upgrade_group_forum_relationships' ),
			array( 'bbp_admin_upgrade_remove_favorites_from_usermeta' ),
			array( 'bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta' ),
			array( 'bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta' ),
		);
	}

	/** @dataProvider callbacks */
	public function test_checkpoint_query_failure_cannot_be_mistaken_for_a_fresh_run( $callback ) {
		$this->assertTrue( is_callable( $callback ), $callback );
		bbp_update_forum_visibility_version();
		$db     = bbp_db();
		$before = $db->get_results( "SELECT * FROM {$db->options} WHERE option_name LIKE '_bbp_tool_batch_%' ORDER BY option_name", ARRAY_A );
		$reject = static function ( $sql ) use ( $db ) {
			return false !== strpos( $sql, 'SELECT `option_value`' ) && false !== strpos( $sql, '_bbp_tool_batch_' )
				? 'SELECT option_value FROM bbp_missing_tool_options' : $sql;
		};
		$suppressed = $db->suppress_errors( true );
		add_filter( 'query', $reject );
		try {
			$result = bbp_admin_repair_tool_normalize_result( call_user_func( $callback ) );
			$this->assertFalse( $result['success'], $callback );
			$this->assertFalse( $result['complete'], $callback );
		} finally {
			remove_filter( 'query', $reject );
			$db->suppress_errors( $suppressed );
		}
		$this->assertSame( $before, $db->get_results( "SELECT * FROM {$db->options} WHERE option_name LIKE '_bbp_tool_batch_%' ORDER BY option_name", ARRAY_A ) );
	}

	public function scalars() {
		return array( array( 42 ), array( '42' ), array( false ), array( true ), array( 1.5 ), array( '' ), array( array( 'nested' => array( 42 ) ) ) );
	}

	/** @dataProvider scalars */
	public function test_verified_option_writes_compare_native_storage_instead_of_cached_php_types( $value ) {
		add_option( '_bbp_test_native_option', 'previous' );
		$this->assertTrue( bbp_admin_repair_update_option( '_bbp_test_native_option', $value ) );
		wp_cache_delete_multiple( array( '_bbp_test_native_option', 'alloptions', 'notoptions' ), 'options' );
		$this->assertTrue( bbp_admin_repair_option_matches( '_bbp_test_native_option', $value ) );
		$this->assertTrue( bbp_admin_repair_tool_save_option( '_bbp_test_native_option', $value ) );
	}

	public function test_stale_negative_cache_does_not_hide_an_existing_checkpoint() {
		$tool  = 'bbp-test-checkpoint';
		$name  = bbp_admin_repair_tool_batch_option_name( $tool );
		$state = array( 'cursor' => 3, 'upper_id' => 9 );
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( $tool, $state ) );
		wp_cache_delete( $name, 'options' );
		$missing          = wp_cache_get( 'notoptions', 'options' );
		$missing          = is_array( $missing ) ? $missing : array();
		$missing[ $name ] = true;
		wp_cache_set( 'notoptions', $missing, 'options' );
		$this->assertSame( array( true, $state ), bbp_admin_repair_tool_get_checkpoint( $tool ) );
	}

	public function test_checkpoint_preparation_and_dry_run_are_scoped_to_each_tool() {
		$first  = array( 'cursor' => 9, 'upper_id' => 3 );
		$second = array( 'cursor' => 2, 'upper_id' => 8, 'offset' => 1 );
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-first', $first ) );
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-second', $second ) );
		$this->assertSame( array( true, false, true ), bbp_admin_repair_tool_load_checkpoint( 'bbp-first' ) );
		$this->assertSame( array( true, $second, false ), bbp_admin_repair_tool_load_checkpoint( 'bbp-second', 'bbp_admin_repair_tool_is_valid_checkpoint', array( 'offset' ) ) );
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-first', array( 'cursor' => 0, 'upper_id' => 3 ), false, true ) );
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-second', $second, true, true ) );
		$this->assertSame( array( true, $first ), bbp_admin_repair_tool_get_checkpoint( 'bbp-first' ) );
		$this->assertSame( array( true, $second ), bbp_admin_repair_tool_get_checkpoint( 'bbp-second' ) );
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-first', $first, true ) );
		$this->assertSame( array( true, $second, false ), bbp_admin_repair_tool_load_checkpoint( 'bbp-second' ) );
	}

	public function test_procedural_batch_lifecycle_loads_saves_and_completes_shared_state() {
		$tool         = 'bbp-test-procedural-batch';
		$state        = array( 'cursor' => 0, 'upper_id' => 3 );
		$batch        = bbp_admin_repair_tool_batch_status( array( 'repaired' => 0 ) );
		$loaded_state = false;

		$this->assertTrue( bbp_admin_repair_tool_batch_start( $tool, $loaded_state, $batch ) );
		$this->assertFalse( $loaded_state );
		$this->assertTrue( bbp_admin_repair_tool_batch_save( $tool, $state, $batch, false ) );
		$this->assertSame( $state, get_option( bbp_admin_repair_tool_batch_option_name( $tool ) ) );

		$loaded       = bbp_admin_repair_tool_batch_status( array( 'repaired' => 0 ) );
		$loaded_state = false;
		$this->assertTrue( bbp_admin_repair_tool_batch_start( $tool, $loaded_state, $loaded ) );
		$this->assertSame( $state, $loaded_state );
		$this->assertTrue( bbp_admin_repair_tool_batch_continue( $loaded, true ) );
		$this->assertTrue( bbp_admin_repair_tool_batch_save( $tool, $state, $loaded, true ) );
		$this->assertTrue( $loaded['complete'] );
		$this->assertFalse( bbp_admin_repair_tool_batch_continue( $loaded, true ) );
		$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool ), false ) );
	}

	public function test_procedural_batch_failure_uses_the_shared_status_contract() {
		$batch = bbp_admin_repair_tool_batch_status( array( 'repaired' => 0 ) );

		$this->assertFalse( bbp_admin_repair_tool_batch_fail( $batch, 'source-read' ) );
		$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $batch['code'] );
		$this->assertSame( 'source-read', $batch['error'] );
		$this->assertFalse( $batch['complete'] );

		bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
		$this->assertSame( 'source-read', $batch['error'] );
	}

	public function test_native_field_verification_rejects_successful_filters_and_transformed_values() {
		$topic = $this->factory->topic->create();
		add_post_meta( $topic, '_bbp_status', 'private' );
		add_filter( 'update_post_metadata', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_metadata_update( 'post', $topic, '_bbp_status', 'publish' ) );
		} finally {
			remove_filter( 'update_post_metadata', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_metadata_update( 'post', $topic, '_bbp_status', 'publish' ) );
		$this->assertTrue( bbp_admin_repair_metadata_update( 'post', $topic, '_bbp_status', 'publish' ) );
	}

	public function test_legacy_relationship_upgrade_verifies_successful_add_filters_before_advancing() {
		$user  = $this->factory->user->create();
		$topic = $this->factory->topic->create();
		$key   = bbp_db()->prefix . '_bbp_favorites';
		update_user_meta( $user, $key, (string) $topic );
		add_filter( 'add_post_metadata', '__return_true' );
		try {
			$result = bbp_admin_upgrade_user_meta_batch( 'bbp-user-favorites-move', $key, '_bbp_favorite' );
			$this->assertSame( 4, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( (string) $topic, get_user_meta( $user, $key, true ) );
		} finally {
			remove_filter( 'add_post_metadata', '__return_true' );
		}
		$result = bbp_admin_upgrade_user_meta_batch( 'bbp-user-favorites-move', $key, '_bbp_favorite' );
		$this->assertSame( 0, $result['code'] );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( array( (string) $user ), get_post_meta( $topic, '_bbp_favorite', false ) );
	}
	public function test_field_verification_requires_the_canonical_key_used_by_native_getters() {
		$topic = $this->factory->topic->create();
		add_post_meta( $topic, '_BBP_STATUS', 'private' );
		$this->assertFalse( bbp_admin_repair_metadata_update( 'post', $topic, '_bbp_status', 'publish' ) );
		$this->assertSame( '', get_post_meta( $topic, '_bbp_status', true ) );
	}

}
