<?php
function bb_bozo_posts( $where ) {
	if ( !$id = bb_get_current_user_info( 'id' ) )
		return $where;

	return preg_replace(
		'/(\w+\.)?post_status = ["\']?0["\']?/',
		"( \\1post_status = 0 OR \\1post_status > 1 AND \\1poster_id = '$id' )",
	$where);
}

function bb_bozo_topics( $where ) {
	if ( !$id = bb_get_current_user_info( 'id' ) )
		return $where;

	return preg_replace(
		'/(\w+\.)?topic_status = ["\']?0["\']?/',
		"( \\1topic_status = 0 OR \\1topic_status > 1 AND \\1topic_poster = '$id' )",
	$where);
}

// Gets those users with the bozo bit.  Does not grab users who have been bozoed on a specific topic.
function bb_get_bozos( $page = 1 ) {
	global $bbdb, $bb_table_prefix, $bb_last_countable_query;
	$page = (int) $page;
	$limit = bb_get_option('page_topics');
	if ( 1 < $page )
		$limit = ($limit * ($page - 1)) . ", $limit";
	$bozo_mkey = $bb_table_prefix . 'bozo_topics';
	$bb_last_countable_query = "SELECT user_id FROM $bbdb->usermeta WHERE meta_key='is_bozo' AND meta_value='1' ORDER BY umeta_id DESC LIMIT $limit";
	if ( $ids = (array) $bbdb->get_col( $bb_last_countable_query ) )
		bb_cache_users( $ids );
	return $ids;
}

function bb_current_user_is_bozo( $topic_id = false ) {
	global $bb_current_user;
	if ( bb_current_user_can('browse_deleted') && 'all' == @$_GET['view'] )
		return false;
	if ( !$topic_id )
		return isset($bb_current_user->data->is_bozo) && $bb_current_user->data->is_bozo;
	global $topic;
	$topic = get_topic( $topic_id );
	$id = bb_get_current_user_info( 'id' );
	return isset($topic->bozos[$id]) && $topic->bozos[$id];
}

function bb_bozo_pre_permalink() {
	if ( is_topic() )
		add_filter( 'get_topic_where', 'bb_bozo_topics' );
}

function bb_bozo_latest_filter() {
	global $bb_current_user;
	if ( isset($bb_current_user->data->bozo_topics) && $bb_current_user->data->bozo_topics )
		add_filter( 'get_latest_topics_where', 'bb_bozo_topics' );
}

function bb_bozo_topic_db_filter() {
	global $topic, $topic_id;
	if ( bb_current_user_is_bozo( $topic->topic_id ? $topic->topic_id : $topic_id ) ) {
		add_filter( 'get_thread_where', 'bb_bozo_posts' );
		add_filter( 'get_thread_post_ids_where', 'bb_bozo_posts' );
	}
}

function bb_bozo_profile_db_filter() {
	global $user;
	if ( bb_get_current_user_info( 'id' ) == $user->ID && @is_array($user->bozo_topics) )
		add_filter( 'get_recent_user_replies_where', 'bb_bozo_posts' );
}

function bb_bozo_recount_topics() {
	global $bbdb;
	if ( isset($_POST['topic-bozo-posts']) && 1 == $_POST['topic-bozo-posts'] ):
	echo "\t<li>\n";
		$old = (array) $bbdb->get_col("SELECT topic_id FROM $bbdb->topicmeta WHERE meta_key = 'bozos'");
		$old = array_flip($old);
		if ( $topics = (array) $bbdb->get_col("SELECT topic_id, poster_id, COUNT(post_id) FROM $bbdb->posts WHERE post_status > 1 GROUP BY topic_id, poster_id") ) :
			echo "\t\t" . __("Counting bozo posts...") . "<br />\n";
			$unique_topics = array_unique($topics);
			$posters = (array) $bbdb->get_col('', 1);
			$counts = (array) $bbdb->get_col('', 2);
			foreach ($unique_topics as $i):
				$bozos = array();
				$indices = array_keys($topics, $i);
				foreach ( $indices as $index )
					$bozos[(int) $posters[$index]] = (int) $counts[$index]; 
				if ( $bozos ) :
					bb_update_topicmeta( $i, 'bozos', $bozos );
					unset($indices, $index, $old[$i]);
				endif;
			endforeach;
			unset($topics, $t, $i, $counts, $posters, $bozos);
		endif;
		if ( $old ) :
			$old = join(',', array_flip($old));
			$bbdb->query("DELETE FROM $bbdb->topicmeta WHERE topic_id IN ($old) AND meta_key = 'bozos'");
		endif;
		echo "\t\t" . __("Done counting bozo posts.");
		echo "\n\t</li>";
	endif;
}

function bb_bozo_recount_users() {
	global $bbdb, $bb_table_prefix;
	if ( isset($_POST['topics-replied-with-bozos']) && 1 == $_POST['topics-replied-with-bozos'] ) :
		if ( $users = (array) $bbdb->get_col("SELECT ID FROM $bbdb->users") ) :
			$no_bozos = array();
			$bozo_mkey = $bb_table_prefix . 'bozo_topics';
			_e("Counting bozo topics for each user...\n");
			foreach ( $users as $user ) :
				$topics_replied = (int) $bbdb->get_var("SELECT COUNT(DISTINCT topic_id) FROM $bbdb->posts WHERE post_status = 0 AND poster_id = '$user'");
				bb_update_usermeta( $user, $bb_table_prefix. 'topics_replied', $topics_replied );
				$bozo_keys = (array) $bbdb->get_col("SELECT topic_id, COUNT(post_id) FROM $bbdb->posts WHERE post_status > 1 AND poster_id = '$user' GROUP BY topic_id");
				$bozo_values = (array) $bbdb->get_col('', 1);
				if ( $c = count($bozo_keys) ) :
					for ( $i=0; $i < $c; $i++ )
						$bozo_topics[(int) $bozo_keys[$i]] = (int) $bozo_values[$i];
					bb_update_usermeta( $user, $bozo_mkey, $bozo_topics );
				else :
					$no_bozos[] = $user;
				endif;
			endforeach;
			if ( $no_bozos ) :
				$no_bozos = join(',', $no_bozos);
				$bbdb->query("DELETE FROM $bbdb->usermeta WHERE user_id IN ($no_bozos) AND meta_key = '$bozo_mkey'");
			endif;
			unset($users, $user, $topics_replied, $bozo_keys, $bozo_values, $bozo_topics);
		endif;
		_e("Done counting bozo topics.\n\n");
	endif;
}

function bb_bozo_post_del_class( $status ) {
	if ( 1 < $status && bb_current_user_can('browse_deleted') )
		return 'bozo';
}

function bb_bozo_add_recount_list() {
	global $recount_list;
	$recount_list[20] = array('topics-replied-with-bozos', __('Count topics to which each user has replied and count each users&#039; bozo posts'), 'bb_bozo_recount_users');
	$recount_list[21] = array('topic-bozo-posts', __('Count bozo posts on every topic'), 'bb_bozo_recount_topics');
	return;
}

function bb_bozo_topic_pages_add( $add ) {
	global $topic;
	if ( isset($_GET['view']) && 'all' == $_GET['view'] && bb_current_user_can('browse_deleted') ) :
		$add += @array_sum($topic->bozos);
	endif;
	if ( bb_current_user_is_bozo( $topic->topic_id ) )
		$add += $topic->bozos[bb_get_current_user_info( 'id' )];
	return $add;
}

function bb_bozo_get_topic_posts( $topic_posts ) {
	global $topic;
	if ( bb_current_user_is_bozo( $topic->topic_id ) )
		$topic_posts += $topic->bozos[bb_get_current_user_info( 'id' )];
	return $topic_posts;
}

function bb_bozo_new_post( $post_id ) {
	$bb_post = bb_get_post( $post_id );
	if ( 1 < $bb_post->post_status )
		bb_bozon( $bb_post->poster_id, $bb_post->topic_id );
	$topic = get_topic( $bb_post->topic_id, false );
	if ( 0 == $topic->topic_posts )
		bb_delete_topic( $topic->topic_id, 2 );
}

function bb_bozo_pre_post_status( $status, $post_id, $topic_id ) {
	if ( !$post_id && bb_current_user_is_bozo() )
		$status = 2;
	elseif ( bb_current_user_is_bozo( $topic_id ) )
		$status = 2;
	return $status;
}

function bb_bozo_delete_post( $post_id, $new_status, $old_status ) {
	$bb_post = bb_get_post( $post_id );
	if ( 1 < $new_status && 2 > $old_status )
		bb_bozon( $bb_post->poster_id, $bb_post->topic_id );
	elseif ( 2 > $new_status && 1 < $old_status )
		bb_fermion( $bb_post->poster_id, $bb_post->topic_id );
}

function bb_bozon( $user_id, $topic_id = 0 ) {
	global $bb_table_prefix;

	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;

	if ( !$topic_id )
		bb_update_usermeta( $user_id, 'is_bozo', 1 );
	else {
		$topic = get_topic( $topic_id );
		$user = bb_get_user( $user_id );

		if ( isset($topic->bozos[$user_id]) )
			$topic->bozos[$user_id]++;
		elseif ( is_array($topic->bozos) )
			$topic->bozos[$user_id] = 1;
		else
			$topic->bozos = array($user_id => 1);
		bb_update_topicmeta( $topic_id, 'bozos', $topic->bozos );
		
		if ( isset($user->bozo_topics[$topic_id]) )
			$user->bozo_topics[$topic_id]++;
		elseif ( is_array($user->bozo_topics) )
			$user->bozo_topics[$topic_id] = 1;
		else
			$user->bozo_topics = array($topic_id => 1);
		bb_update_usermeta( $uid, $bb_table_prefix . 'bozo_topics', $user->bozo_topics );
	}
}

function bb_fermion( $user_id, $topic_id = 0 ) {
	global $bb_table_prefix;

	$user_id = (int) $user_id;
	$topic_id = (int) $topic_id;

	if ( !$topic_id )
		bb_delete_usermeta( $user_id, 'is_bozo' );
	else {
		$topic = get_topic( $topic_id );
		$user = bb_get_user( $user_id );
		if ( --$topic->bozos[$user_id] < 1 )
			unset($topic->bozos[$user_id]);
		bb_update_topicmeta( $topic_id, 'bozos', $topic->bozos );
		
		if ( --$user->bozo_topics[$topic_id] < 1 )
			unset($user->bozo_topics[$topic_id]);
		bb_update_usermeta( $uid, $bb_table_prefix . 'bozo_topics', $user->bozo_topics );
	}
}

function bb_bozo_profile_admin_keys( $a ) {
	global $user;
	$a['is_bozo'] = array(
		0,							// Required
		__('This user is a bozo'),	// Label
		'checkbox',					// Type
		'1',						// Value
		''							// Default when not set
	);
	return $a;
} 

function bb_bozo_add_admin_page() {
	global $bb_submenu;
	$bb_submenu['users.php'][] = array(__('Bozos'), 'moderate', 'bb_bozo_admin_page');
}

function bb_bozo_admin_page() {
	class BB_Bozo_Users extends BB_Users_By_Role {
		var $title = '';

		function BB_Bozo_Users( $page = '' ) { // constructor
			$this->raw_page = ( '' == $page ) ? false : (int) $page;
			$this->page = (int) ( '' == $page ) ? 1 : $page;
			$this->title = __('These users have been marked as bozos');

			$this->prepare_query();
			$this->query();
			$this->do_paging();
		}

		function query() {
			global $bbdb;
			$this->results = bb_get_bozos( $this->page );

			if ( $this->results )
				$this->total_users_for_query = bb_count_last_query();
			else
				$this->search_errors = new WP_Error('no_matching_users_found', __('No matching users were found!'));

			if ( is_wp_error( $this->search_errors ) )
				bb_admin_notice( $this->search_errors );
		}
	}

	$bozos = new BB_Bozo_Users( $_GET['userspage'] );
	$bozos->display( false, bb_current_user_can( 'edit_users' ) );
}

add_filter( 'pre_post_status', 'bb_bozo_pre_post_status', 5, 3 );
add_action( 'bb_new_post', 'bb_bozo_new_post', 5 );
add_action( 'bb_delete_post', 'bb_bozo_delete_post', 5, 3 );

add_action( 'pre_permalink', 'bb_bozo_pre_permalink' );
add_action( 'bb_index.php_pre_db', 'bb_bozo_latest_filter' );
add_action( 'bb_forum.php_pre_db', 'bb_bozo_latest_filter' );
add_action( 'bb_topic.php_pre_db', 'bb_bozo_topic_db_filter' );
add_action( 'bb_profile.php_pre_db', 'bb_bozo_profile_db_filter' );

add_action( 'bb_recount_list', 'bb_bozo_add_recount_list' );
add_action( 'topic_pages_add', 'bb_bozo_topic_pages_add' );

add_action( 'post_del_class', 'bb_bozo_post_del_class' );
add_filter( 'get_topic_posts', 'bb_bozo_get_topic_posts' );

add_filter( 'get_profile_admin_keys', 'bb_bozo_profile_admin_keys' );
add_action( 'bb_admin_menu_generator', 'bb_bozo_add_admin_page' );
?>
