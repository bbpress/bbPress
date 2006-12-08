<?php

class BB_Cache {
	var $use_cache = false;
	var $flush_freq = 100;
	var $flush_time = 172800; // 2 days

	function BB_Cache() {
		if ( false === bb_get_option( 'use_cache' ) || !is_writable(BBPATH . 'bb-cache/') )
			$this->use_cache = false;
		else	$this->flush_old();
	}

	function get_user( $user_id, $use_cache = true ) {
		global $bbdb, $bb_user_cache;
		$user_id = (int) $user_id;

		if ( $use_cache && $this->use_cache && file_exists(BBPATH . 'bb-cache/bb_user-' . $user_id) ) :
			$bb_user_cache[$user_id] = $this->read_cache(BBPATH . 'bb-cache/bb_user-' . $user_id);
			return $bb_user_cache[$user_id];
		else :
			if ( $user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE ID = $user_id") ) :
				bb_append_meta( $user, 'user' );
			else :
				$bb_user_cache[$user_id] = false;
			endif;
		endif;

		if ( $this->use_cache && $bb_user_cache[$user_id] )
			$this->write_cache(BBPATH . 'bb-cache/bb_user-' . $user_id, $bb_user_cache[$user_id]);
		return $bb_user_cache[$user_id];
	}

	function append_current_user_meta( $user ) {
		global $bb_user_cache;
		if ( $this->use_cache && file_exists(BBPATH . 'bb-cache/bb_user-' . $user->ID) ) :
			$bb_user_cache[$user->ID] = $this->read_cache(BBPATH . 'bb-cache/bb_user-' . $user->ID);
			return $bb_user_cache[$user->ID];
		else :
			$bb_user_cache[$user->ID] = bb_append_meta( $user, 'user' );
		endif;

		if ( $this->use_cache )
			$this->write_cache(BBPATH . 'bb-cache/bb_user-' . $user->ID, $bb_user_cache[$user->ID]);
		return $bb_user_cache[$user->ID];
	}

	function cache_users( $ids, $use_cache = true ) {
		global $bbdb, $bb_user_cache;

		if ( $use_cache && $this->use_cache ) :
				foreach ( $ids as $i => $user_id ) :
				if ( file_exists(BBPATH . 'bb-cache/bb_user-' . $user_id) ) :
					$bb_user_cache[$user_id] = $this->read_cache(BBPATH . 'bb-cache/bb_user-' . $user_id);
					unset($ids[$i]);
				endif;
			endforeach;
			if ( 0 < count($ids) ) :
				$this->cache_users( $ids, false ); // grab from DB what we don't have in hard cache
				return;
			endif;
		elseif ( 0 < count($ids) ) :
			$sids = join(',', $ids);
			if ( $users = $bbdb->get_results("SELECT * FROM $bbdb->users WHERE ID IN ($sids)") )
				bb_append_meta( $users, 'user' );
		endif;

		if ( $this->use_cache )
			foreach ( $ids as $user_id )
				if ( $bb_user_cache[$user_id] )
					$this->write_cache(BBPATH . 'bb-cache/bb_user-' . $user_id, $bb_user_cache[$user_id]);
		return;
	}

	function get_topic( $topic_id, $use_cache = true ) {
		global $bbdb, $bb_topic_cache;
		$topic_id = (int) $topic_id;

		$normal = true;
		if ( 'AND topic_status = 0' != $where = apply_filters('get_topic_where', 'AND topic_status = 0') )
			$normal = false;

		if ( $use_cache && $this->use_cache && $normal && file_exists(BBPATH . 'bb-cache/bb_topic-' . $topic_id) ) :
			$bb_topic_cache[$topic_id] = $this->read_cache(BBPATH . 'bb-cache/bb_topic-' . $topic_id);
			return $bb_topic_cache[$topic_id];
		else :
			if ( $topic = $bbdb->get_row("SELECT * FROM $bbdb->topics WHERE topic_id = $topic_id $where") ) :
				bb_append_meta( $topic, 'topic' );
			else :
				$bb_topic_cache[$topic_id] = false;
			endif;
		endif;

		if ( $this->use_cache && $normal && $bb_topic_cache[$topic_id] )
			$this->write_cache(BBPATH . 'bb-cache/bb_topic-' . $topic_id, $bb_topic_cache[$topic_id]);
		return $bb_topic_cache[$topic_id];
	}

	function get_thread( $topic_id, $page = 1, $reverse = 0 ) {
		global $bbdb, $bb_post_cache;
		$topic_id = (int) $topic_id;
		$page = (int) $page;
		$reverse = $reverse ? 1 : 0;
		$normal = true;
		if ( 'AND post_status = 0' != $where = apply_filters('get_thread_where', 'AND post_status = 0') )
			$normal = false;

		$limit = bb_get_option('page_topics');
		if ( 1 < $page )
			$limit = ($limit * ($page - 1)) . ", $limit";
		$order = $reverse ? 'DESC' : 'ASC';
		$file = BBPATH . 'bb-cache/bb_thread-' . $topic_id . '-' . $page . '-' . $reverse;

		if ( $this->use_cache && $normal && file_exists($file) ) :
			$thread = $this->read_cache($file);
			foreach ($thread as $bb_post)
				$bb_post_cache[$bb_post->post_id] = $bb_post;
			return $thread;
		else :
			$thread = $this->cache_posts( "SELECT * FROM $bbdb->posts WHERE topic_id = $topic_id $where ORDER BY post_time $order LIMIT $limit" );
		endif;

		if ( $this->use_cache && $normal && $thread )
			$this->write_cache($file, $thread);
		return $thread;
	}

	function cache_posts( $query ) { // soft cache
		global $bbdb, $bb_post_cache;
		if ( $posts = $bbdb->get_results( $query ) )
			foreach( (array) $posts as $bb_post )
				$bb_post_cache[$bb_post->post_id] = $bb_post;
		return $posts;
	}

	function get_forums() {
		global $bbdb;

		if ( $this->use_cache && file_exists(BBPATH . 'bb-cache/bb_forums') )
			return $this->read_cache(BBPATH . 'bb-cache/bb_forums');

		$forums = $bbdb->get_results("SELECT * FROM $bbdb->forums ORDER BY forum_order");
		if ( $this->use_cache && $forums )
			$this->write_cache(BBPATH . 'bb-cache/bb_forums', $forums);
		return $forums;
	}

	function get_forum( $forum_id ) {
		global $bbdb;
		$forum_id = (int) $forum_id;

		if ( $this->use_cache && file_exists(BBPATH . 'bb-cache/bb_forum-' . $forum_id) )
			return $this->read_cache(BBPATH . 'bb-cache/bb_forum-' . $forum_id);

		$forum = $bbdb->get_row("SELECT * FROM $bbdb->forums WHERE forum_id = $forum_id");
		if ( $this->use_cache && $forum )
			$this->write_cache(BBPATH . 'bb-cache/bb_forum-' . $forum_id, $forum);
		return $forum;
	}

	function read_cache($file) {
		return unserialize(file_get_contents($file));
	}

	function write_cache($file, $data) {
		if ( !$this->use_cache )
			return;
		$data = serialize($data);
		$f = fopen($file, 'w');
		flock($f, LOCK_EX);
		fwrite($f, $data);
		flock($f, LOCK_UN);
		fclose($f);
	}

	function flush_one( $type, $id = 0, $page = 0 ) {
		if ( !$this->use_cache )
			return;
		switch ( $type ) :
		case 'user' :
			$file = BBPATH . 'bb-cache/bb_user-' . $id;
			break;
		case 'topic' :
			$file = BBPATH . 'bb-cache/bb_topic-' . $id;
			break;
		case 'forums' :
			$file = BBPATH . 'bb-cache/bb_forums';
			break;
		endswitch;

		if ( file_exists($file) )
			unlink($file);
	}

	function flush_many( $type, $id, $start = 0 ) {
		if ( !$this->use_cache )
			return;
		switch ( $type ) :
		case 'thread' :
			$files = glob( BBPATH . 'bb-cache/bb_thread-' . $id . '-*');
			break;
		case 'forum' :
			$files = array(BBPATH . 'bb-cache/bb_forum-' . $id, BBPATH . 'bb-cache/bb_forums');
			break;
		endswitch;

		if ( is_array($files) )
			foreach ( $files as $file )
				@unlink($file);
	}

	function flush_old() {
		$cache_data = 0;
		if  ( file_exists(BBPATH . 'bb-cache/bb_cache_data') ) :
			$cache_data = $this->read_cache(BBPATH . 'bb-cache/bb_cache_data');
			if ( ++$cache_data > $this->flush_freq ) :
				$cache_data = 0;
				$handle=opendir(BBPATH . 'bb-cache');	//http://us2.php.net/manual/en/function.filemtime.php#42065
				while (false!==($file = readdir($handle))) {
					if ($file != "." && $file != "..") { 
						$Diff = time() - filemtime(BBPATH . "bb-cache/$file");
						if ($Diff > $this->flush_time)
							unlink(BBPATH . "bb-cache/$file");
					}
				}
				closedir($handle);
			endif;
		endif;
		$this->write_cache(BBPATH . 'bb-cache/bb_cache_data', $cache_data);
	}

}
?>
