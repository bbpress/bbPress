<?php
require_once( './bb-load.php' );

if ( !$q = trim( @$_GET['search'] ) )
	$q = trim( @$_GET['q'] );

$bb_query_form = new BB_Query_Form;

if ( $q = stripslashes( $q ) ) {
	/* Paging hack */
	global $page;
	if ( !$page ) $page = 1;
	$per_page = ceil( (int) bb_get_option( 'page_topics' ) / 2 );
	$search_start = $per_page * ( $page - 1 );
	$search_stop = $per_page;
	
	/* Recent */
	add_filter( 'bb_recent_search_fields',   create_function( '$f', 'return $f . ", MAX(post_time) AS post_time";' ) );
	add_filter( 'bb_recent_search_group_by', create_function( '', 'return "t.topic_id";' ) );
	$bb_query_form->BB_Query_Form( 'post', array(), array( 'order_by' => 'p.post_time', 'page' => 1, 'per_page' => -1, 'post_status' => 0, 'topic_status' => 0, 'post_text' => $q, 'forum_id', 'tag', 'topic_author', 'post_author' ), 'bb_recent_search' );
	$recent = $bb_query_form->results;
	if ( $recent ) {
		$recent_count = isset( $bb_query_form->count ) ? $bb_query_form->count : count( $recent );
		$recent = array_slice( $recent, $search_start, $search_stop );
	} else {
		$recent_count = 0;
	}
	
	/* Relevant */
	$bb_query_form->BB_Query_Form( 'topic', array( 'search' => $q ), array( 'page' => 1, 'post_status' => 0, 'per_page' => -1, 'topic_status' => 0, 'search', 'forum_id', 'tag', 'topic_author', 'post_author' ), 'bb_relevant_search' );
	$relevant = $bb_query_form->results;
	if ( $relevant ) {
		$relevant_count = isset( $bb_query_form->count ) ? $bb_query_form->count : count( $relevant );
		$relevant = array_slice( $relevant, $search_start, $search_stop );
	} else {
		$relevant_count = 0;
	}
	
	$search_count = max( $recent_count, $relevant_count );
	
	$q = $bb_query_form->get( 'search' );
}

do_action( 'do_search', $q );

// Cache topics
// NOT bbdb::prepared
if ( $recent ) :
	$topic_ids = array();
	foreach ($recent as $bb_post) {
		$topic_ids[] = (int) $bb_post->topic_id;
	}
	$topic_ids = join($topic_ids);
	if ( $topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)") )
		$topics = bb_append_meta( $topics, 'topic' );
endif;

bb_load_template( 'search.php', array( 'q', 'recent', 'relevant', 'page', 'per_page', 'search_count' ), $q );

?>