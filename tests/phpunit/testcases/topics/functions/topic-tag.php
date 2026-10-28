<?php

/**
 * Tests for the topic component functions.
 *
 * @group topics
 * @group topic_tags
 * @group functions
 * @group topic
 * @group topic_tag
 */
class BBP_Tests_Topics_Functions_Topic_Tag extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_edit_topic_tag_handler
	 * @todo   Cover successful edits in an integration test because bbp_redirect() exits.
	 */
	public function test_bbp_edit_topic_tag_handler() {
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$term        = wp_insert_term( 'Original', bbp_get_topic_tag_tax_id() );
		$this->assertFalse( is_wp_error( $term ) );

		try {
			unset( $_POST['tag-id'] );
			$this->assertNull( bbp_edit_topic_tag_handler( 'bbp-update-topic-tag' ) );

			$_POST['tag-id'] = $term['term_id'];
			$this->assertNull( bbp_edit_topic_tag_handler( 'unsupported' ) );

			$_REQUEST['_wpnonce'] = 'invalid';
			$this->assertNull( bbp_edit_topic_tag_handler( 'bbp-update-topic-tag' ) );
			$this->assertSame( 'bbp_manage_topic_tag_update_nonce', bbpress()->errors->get_error_code() );
			bbpress()->errors->remove( 'bbp_manage_topic_tag_update_nonce' );

			$this->assertNull( bbp_edit_topic_tag_handler( 'bbp-merge-topic-tag' ) );
			$this->assertSame( 'bbp_manage_topic_tag_merge_nonce', bbpress()->errors->get_error_code() );
			bbpress()->errors->remove( 'bbp_manage_topic_tag_merge_nonce' );

			$this->assertNull( bbp_edit_topic_tag_handler( 'bbp-delete-topic-tag' ) );
			$this->assertSame( 'bbp_manage_topic_tag_delete_nonce', bbpress()->errors->get_error_code() );
			$this->assertSame( 'Original', get_term( $term['term_id'], bbp_get_topic_tag_tax_id() )->name );
		} finally {
			bbpress()->errors->remove( 'bbp_manage_topic_tag_update_nonce' );
			bbpress()->errors->remove( 'bbp_manage_topic_tag_merge_nonce' );
			bbpress()->errors->remove( 'bbp_manage_topic_tag_delete_nonce' );
			$_POST = $old_post;
			$_REQUEST = $old_request;
		}
	}

	/**
	 * @covers ::bbp_spam_topic_tags
	 */
	public function test_bbp_spam_topic_tags() {
		$topic_id = $this->factory->topic->create();
		$taxonomy = bbp_get_topic_tag_tax_id();
		wp_set_object_terms( $topic_id, array( 'alpha', 'beta' ), $taxonomy );

		$this->assertSame( array( $taxonomy => '' ), bbp_spam_topic_tags( $topic_id ) );
		$this->assertSame( array( 'alpha', 'beta' ), get_post_meta( $topic_id, '_bbp_spam_topic_tags', true ) );

		$empty_topic_id = $this->factory->topic->create();
		$this->assertSame( array( $taxonomy => '' ), bbp_spam_topic_tags( $empty_topic_id ) );
		$this->assertFalse( metadata_exists( 'post', $empty_topic_id, '_bbp_spam_topic_tags' ) );
	}

	/**
	 * @covers ::bbp_unspam_topic_tags
	 */
	public function test_bbp_unspam_topic_tags() {
		$topic_id = $this->factory->topic->create();
		$taxonomy = bbp_get_topic_tag_tax_id();
		update_post_meta( $topic_id, '_bbp_spam_topic_tags', array( 'alpha', 'beta' ) );

		$this->assertSame( array( $taxonomy => array( 'alpha', 'beta' ) ), bbp_unspam_topic_tags( $topic_id ) );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_spam_topic_tags' ) );
		$this->assertSame( array( $taxonomy => '' ), bbp_unspam_topic_tags( $topic_id ) );
	}

	/**
	 * @covers ::bbp_get_topic_tags
	 * @covers ::bbp_get_topic_tag_names
	 * @ticket 3706
	 */
	public function test_bbp_get_topic_tag_names() {
		$topic_id = $this->factory->topic->create();
		$this->assertSame( array(), bbp_get_topic_tags( $topic_id ) );
		$this->assertSame( '', bbp_get_topic_tag_names( $topic_id ) );
		wp_set_object_terms( $topic_id, array( 'alpha', 'beta' ), bbp_get_topic_tag_tax_id() );
		$this->assertEqualSets( array( 'alpha', 'beta' ), wp_list_pluck( bbp_get_topic_tags( $topic_id ), 'name' ) );
		$this->assertSame( 'alpha, beta', bbp_get_topic_tag_names( $topic_id ) );
		$this->assertSame( 'alpha | beta', bbp_get_topic_tag_names( $topic_id, ' | ' ) );

		$received = null;
		$filter   = static function( $topic_tags, $filtered_topic_id ) use ( &$received ) {
			$received = compact( 'topic_tags', 'filtered_topic_id' );

			return array();
		};

		add_filter( 'bbp_get_topic_tags', $filter, 10, 2 );
		try {
			$this->assertSame( array(), bbp_get_topic_tags( $topic_id ) );
		} finally {
			remove_filter( 'bbp_get_topic_tags', $filter, 10 );
		}

		$this->assertSame( $topic_id, $received['filtered_topic_id'] );
		$this->assertEqualSets( array( 'alpha', 'beta' ), wp_list_pluck( $received['topic_tags'], 'name' ) );
	}

	/**
	 * @covers ::bbp_update_topic_tag_count
	 * @ticket 3706
	 */
	public function test_bbp_update_topic_tag_count_counts_public_topic_statuses() {
		$taxonomy = bbp_get_topic_tag_tax_id();
		$term     = wp_insert_term( 'Counted tag', $taxonomy );
		$this->assertFalse( is_wp_error( $term ) );

		$forum_id  = $this->factory->forum->create();
		$topic_ids = array(
			$this->factory->topic->create( array( 'post_parent' => $forum_id ) ),
			$this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_status' => bbp_get_closed_status_id() ) ),
			$this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_status' => bbp_get_pending_status_id() ) ),
		);

		foreach ( $topic_ids as $topic_id ) {
			wp_set_object_terms( $topic_id, $term['term_id'], $taxonomy );
		}

		$taxonomy_object              = clone get_taxonomy( $taxonomy );
		$taxonomy_object->object_type = array( bbp_get_topic_post_type(), bbp_get_topic_post_type() . ':variant', 'missing-post-type' );
		$term_object                  = get_term( $term['term_id'], $taxonomy );
		$actions                      = array();
		$before                       = static function( $term_taxonomy_id, $taxonomy_name ) use ( &$actions ) {
			$actions[] = array( 'before', $term_taxonomy_id, $taxonomy_name );
		};
		$after                        = static function( $term_taxonomy_id, $taxonomy_name ) use ( &$actions ) {
			$actions[] = array( 'after', $term_taxonomy_id, $taxonomy_name );
		};

		add_action( 'edit_term_taxonomy', $before, 10, 2 );
		add_action( 'edited_term_taxonomy', $after, 10, 2 );
		try {
			bbp_update_topic_tag_count( array( $term_object->term_taxonomy_id ), $taxonomy_object );
		} finally {
			remove_action( 'edit_term_taxonomy', $before, 10 );
			remove_action( 'edited_term_taxonomy', $after, 10 );
		}

		clean_term_cache( $term['term_id'], $taxonomy );
		$this->assertSame( 2, get_term( $term['term_id'], $taxonomy )->count );
		$this->assertSame(
			array(
				array( 'before', $term_object->term_taxonomy_id, $taxonomy ),
				array( 'after', $term_object->term_taxonomy_id, $taxonomy ),
			),
			$actions
		);

		$this->assertNull( bbp_update_topic_tag_count( array(), $taxonomy_object ) );
		$empty_taxonomy              = clone $taxonomy_object;
		$empty_taxonomy->object_type = array();
		$this->assertNull( bbp_update_topic_tag_count( array( $term_object->term_taxonomy_id ), $empty_taxonomy ) );
	}

	/**
	 * @covers ::bbp_check_topic_tag_edit
	 * @todo   Cover denied edits in an integration test because bbp_redirect() exits.
	 */
	public function test_bbp_check_topic_tag_edit() {
		$this->assertNull( bbp_check_topic_tag_edit() );
	}
}
