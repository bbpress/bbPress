<?php

class BB_Cache {
	var $use_cache = false;
	var $flush_freq = 100;
	var $flush_time = 172800; // 2 days

	function get_user( $user_id, $use_cache = true ) {
		return bb_get_user( $user_id );
	}

	function append_current_user_meta( $user ) {
		return bb_append_meta( $user, 'user' );
	}

	function append_user_meta( $user ) {
		return bb_append_meta( $user, 'user' );
	}

	// NOT bbdb::prepared
	function cache_users( $ids, $use_cache = true ) {
		return bb_cache_users( $ids );
	}

	// NOT bbdb::prepared
	function get_topic( $topic_id, $use_cache = true ) {
		return get_topic( $topic_id, $use_cache );
	}

	// NOT bbdb::prepared
	function get_thread( $topic_id, $page = 1, $reverse = 0 ) {
		return get_thread( $topic_id, $page, $reverse );
	}

	// NOT bbdb::prepared
	function cache_posts( $query ) { // soft cache
		return bb_cache_posts( $query );
	}

	// NOT bbdb::prepared
	function get_forums() {
		return get_forums();
	}

	function get_forum( $forum_id ) {
		return get_forum( $forum_id );
	}

	function read_cache($file) {
		return false;
	}

	function write_cache($file, $data) {
		return false;
	}

	function flush_one( $type, $id = false, $page = 0 ) {
		return true;
	}

	function flush_many( $type, $id, $start = 0 ) {
		return true;
	}

	function flush_old() {
		return true;
	}

	function flush_all() {
		return true;
	}

}
?>
