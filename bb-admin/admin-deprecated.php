<?php

function bb_get_deleted_posts( $page = 1, $limit = false, $status = 1, $topic_status = 0 ) {
	$query_vars = array( 'limit' => $limit, 'page' => $page, 'status' => $status );
	if ( false !== $topic_status )
		$query_vars['topic_status'] = $topic_status;

	$post_query = new BB_Query( 'post', $query_vars, 'bb_get_deleted_posts' );
	return $post_query->results;
}

?>
