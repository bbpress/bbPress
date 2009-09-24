<?php
require_once('./bb-load.php');

if ( !$q = trim( @$_GET['search'] ) )
	$q = trim( @$_GET['q'] );

$bb_query_form = new BB_Query_Form;

if ( $q = stripslashes( $q ) ) {
	add_filter( 'bb_recent_search_fields',   create_function( '$f', 'return $f . ", MAX(post_time) AS post_time";' ) );
	add_filter( 'bb_recent_search_group_by', create_function( '', 'return "t.topic_id";' ) );
	$bb_query_form->BB_Query_Form( 'post', array(), array( 'order_by' => 'p.post_time', 'per_page' => 5, 'post_status' => 0, 'topic_status' => 0, 'post_text' => $q, 'forum_id', 'tag', 'topic_author', 'post_author' ), 'bb_recent_search' );
	$recent = $bb_query_form->results;

	$bb_query_form->BB_Query_Form( 'topic', array( 'search' => $q ), array( 'post_status' => 0, 'topic_status' => 0, 'search', 'forum_id', 'tag', 'topic_author', 'post_author' ), 'bb_relevant_search' );
	$relevant = $bb_query_form->results;

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

bb_load_template( 'search.php', array('q', 'recent', 'relevant'), $q );

?>
