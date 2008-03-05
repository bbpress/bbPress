<?php

class BB_Cache {
	var $use_cache = false;
	var $flush_freq = 100;
	var $flush_time = 172800; // 2 days

	function BB_Cache() {
		if ( false === bb_get_option( 'use_cache' ) || !is_writable(BB_PATH . 'bb-cache/') )
			$this->use_cache = false;
		else
			$this->flush_old();
	}

	function get_user( $user_id, $use_cache = true ) {
		global $bbdb, $bb_user_cache;
		$user_id = (int) $user_id;

		if ( $use_cache && $this->use_cache && file_exists(BB_PATH . 'bb-cache/bb_user-' . $user_id) ) :
			$bb_user_cache[$user_id] = $this->read_cache(BB_PATH . 'bb-cache/bb_user-' . $user_id);
			return $bb_user_cache[$user_id];
		else :
			if ( $user = $bbdb->get_row( $bbdb->prepare( "SELECT * FROM $bbdb->users WHERE ID = %d", $user_id ) ) ) :
				bb_append_meta( $user, 'user' );
			else :
				$bb_user_cache[$user_id] = false;
			endif;
		endif;

		if ( $this->use_cache && $bb_user_cache[$user_id] )
			$this->write_cache(BB_PATH . 'bb-cache/bb_user-' . $user_id, $bb_user_cache[$user_id]);
		return $bb_user_cache[$user_id];
	}

	function append_current_user_meta( $user ) {
		return $this->append_user_meta( $user );
	}

	function append_user_meta( $user ) {
		global $bb_user_cache;
		if ( $this->use_cache && file_exists(BB_PATH . 'bb-cache/bb_user-' . $user->ID) ) :
			$bb_user_cache[$user->ID] = $this->read_cache(BB_PATH . 'bb-cache/bb_user-' . $user->ID);
			return $bb_user_cache[$user->ID];
		else :
			$bb_user_cache[$user->ID] = bb_append_meta( $user, 'user' );
		endif;

		if ( $this->use_cache )
			$this->write_cache(BB_PATH . 'bb-cache/bb_user-' . $user->ID, $bb_user_cache[$user->ID]);
		return $bb_user_cache[$user->ID];
	}

	// NOT bbdb::prepared
	function cache_users( $ids, $use_cache = true ) {
		global $bbdb, $bb_user_cache;

		$ids = array_map( 'intval', $ids );

		if ( $use_cache && $this->use_cache ) :
				foreach ( $ids as $i => $user_id ) :
				if ( file_exists(BB_PATH . 'bb-cache/bb_user-' . $user_id) ) :
					$bb_user_cache[$user_id] = $this->read_cache(BB_PATH . 'bb-cache/bb_user-' . $user_id);
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
					$this->write_cache(BB_PATH . 'bb-cache/bb_user-' . $user_id, $bb_user_cache[$user_id]);
		return;
	}

	// NOT bbdb::prepared
	function get_topic( $topic_id, $use_cache = true ) {
		global $bbdb, $bb_topic_cache;
		$topic_id = (int) $topic_id;

		$normal = true;
		if ( 'AND topic_status = 0' != $where = apply_filters('get_topic_where', 'AND topic_status = 0') )
			$normal = false;

		if ( $use_cache && $this->use_cache && $normal && file_exists(BB_PATH . 'bb-cache/bb_topic-' . $topic_id) ) :
			$bb_topic_cache[$topic_id] = $this->read_cache(BB_PATH . 'bb-cache/bb_topic-' . $topic_id);
			return $bb_topic_cache[$topic_id];
		else :
			if ( $topic = $bbdb->get_row("SELECT * FROM $bbdb->topics WHERE topic_id = $topic_id $where") ) :
				bb_append_meta( $topic, 'topic' );
			else :
				$bb_topic_cache[$topic_id] = false;
			endif;
		endif;

		if ( $this->use_cache && $normal && $bb_topic_cache[$topic_id] )
			$this->write_cache(BB_PATH . 'bb-cache/bb_topic-' . $topic_id, $bb_topic_cache[$topic_id]);
		return $bb_topic_cache[$topic_id];
	}

	// NOT bbdb::prepared
	function get_thread( $topic_id, $page = 1, $reverse = 0 ) {
		global $bbdb, $bb_post_cache;
		$topic_id = (int) $topic_id;
		$page = (int) $page;
		$reverse = $reverse ? 1 : 0;
		$normal = true;
		if ( 'AND post_status = 0' != $where = apply_filters('get_thread_where', 'AND post_status = 0') )
			$normal = false;

		$limit = (int) bb_get_option('page_topics');
		if ( 1 < $page )
			$limit = ($limit * ($page - 1)) . ", $limit";
		$order = $reverse ? 'DESC' : 'ASC';
		$file = BB_PATH . 'bb-cache/bb_thread-' . $topic_id . '-' . $page . '-' . $reverse;

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

	// NOT bbdb::prepared
	function cache_posts( $query ) { // soft cache
		global $bbdb, $bb_post_cache;
		if ( $posts = (array) $bbdb->get_results( $query ) )
			foreach( $posts as $bb_post )
				$bb_post_cache[$bb_post->post_id] = $bb_post;
		return $posts;
	}

	// NOT bbdb::prepared
	function get_forums() {
		global $bbdb, $bb_forum_cache;

		$normal = true;
		if ( '' != $where = apply_filters('get_forums_where', '') )
			$normal = false;

		if ( $normal && isset($bb_forum_cache[-1]) && $bb_forum_cache[-1] ) {
			$forums = $bb_forum_cache;
			unset($forums[-1]);
			return $forums;
		}

		if ( $this->use_cache && $normal && file_exists(BB_PATH . 'bb-cache/bb_forums') )
			return $this->read_cache(BB_PATH . 'bb-cache/bb_forums');

		$forums = (array) $bbdb->get_results("SELECT * FROM $bbdb->forums $where ORDER BY forum_order");
		if ( $this->use_cache && $normal && $forums )
			$this->write_cache(BB_PATH . 'bb-cache/bb_forums', $forums);

		$_forums = array();
		foreach ( $forums as $forum )
			$_forums[(int) $forum->forum_id] = $bb_forum_cache[(int) $forum->forum_id] = $forum;

		$bb_forum_cache[-1] = true;

		return $_forums;
	}

	function get_forum( $forum_id ) {
		global $bbdb, $bb_forum_cache;
		$forum_id = (int) $forum_id;

		$normal = true;
		if ( '' != $where = apply_filters('get_forum_where', '') )
			$normal = false;

		if ( $normal && $forum_id && isset($bb_forum_cache[$forum_id]) )
			return $bb_forum_cache[$forum_id];

		if ( $this->use_cache && $normal && file_exists(BB_PATH . 'bb-cache/bb_forum-' . $forum_id) )
			return $this->read_cache(BB_PATH . 'bb-cache/bb_forum-' . $forum_id);

		if ( $forum = $bbdb->get_row("SELECT * FROM $bbdb->forums WHERE forum_id = $forum_id $where") )
			$bb_forum_cache[$forum_id] = $forum;

		if ( $this->use_cache && $normal && $forum )
			$this->write_cache(BB_PATH . 'bb-cache/bb_forum-' . $forum_id, $forum);

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

	function flush_one( $type, $id = false, $page = 0 ) {
		switch ( $type ) :
		case 'user' :
			$id = (int) $id;
			global $bb_user_cache;
			unset($bb_user_cache[$id]);
			$file = BB_PATH . 'bb-cache/bb_user-' . $id;
			break;
		case 'topic' :
			if ( !is_numeric($id) )
				break;
			$id = (int) $id;
			global $bb_topic_cache;
			unset($bb_topic_cache[$id]);
			$file = BB_PATH . 'bb-cache/bb_topic-' . $id;
			break;
		case 'forums' :
			global $bb_forum_cache;
			unset($bb_forum_cache[-1]);
			$file = BB_PATH . 'bb-cache/bb_forums';
			break;
		endswitch;

		if ( !$this->use_cache )
			return;

		if ( file_exists($file) )
			unlink($file);
	}

	function flush_many( $type, $id, $start = 0 ) {
		switch ( $type ) :
		case 'thread' :
			$files = glob( BB_PATH . 'bb-cache/bb_thread-' . $id . '-*');
			break;
		case 'forum' :
			global $bb_forum_cache;
			unset($bb_forum_cache[$id], $bb_forum_cache[-1]);
			$files = array(BB_PATH . 'bb-cache/bb_forum-' . $id, BB_PATH . 'bb-cache/bb_forums');
			break;
		endswitch;

		if ( !$this->use_cache )
			return;

		if ( is_array($files) )
			foreach ( $files as $file )
				@unlink($file);
	}

	function flush_old() {
		$cache_data = 0;
		if  ( file_exists(BB_PATH . 'bb-cache/bb_cache_data') ) :
			$cache_data = $this->read_cache(BB_PATH . 'bb-cache/bb_cache_data');
			if ( ++$cache_data > $this->flush_freq ) :
				$cache_data = 0;
				$handle = opendir(BB_PATH . 'bb-cache');	//http://us2.php.net/manual/en/function.filemtime.php#42065
				while ( false !== ( $file = readdir($handle) ) ) {
					if ( $file != "." && $file != ".." && is_file(BB_PATH . "bb-cache/$file") ) { 
						$Diff = time() - filemtime(BB_PATH . "bb-cache/$file");
						if ( $Diff > $this->flush_time )
							unlink(BB_PATH . "bb-cache/$file");
					}
				}
				closedir($handle);
			endif;
		endif;
		$this->write_cache(BB_PATH . 'bb-cache/bb_cache_data', $cache_data);
	}

	function flush_all() {
		$handle = opendir( BB_PATH . 'bb-cache' );
		while ( false !== ( $file = readdir($handle) ) )
			if ( 0 !== strpos($file, '.') )
				unlink(BB_PATH . "bb-cache/$file");
		closedir($handle);
	}

}
?>
