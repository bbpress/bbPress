<?php
function bb_specialchars( $text, $quotes = 0 ) {
	return wp_specialchars( $text, $quotes );
}

function bb_make_clickable( $ret ) {
	return make_clickable( $ret );
}

function bb_apply_filters($tag, $string, $filter = true) {
	$args = func_get_args();
	return call_user_func_array('apply_filters', $args);
}

function bb_add_filter($tag, $function_to_add, $priority = 10) {
	$args = func_get_args();
	return call_user_func_array('add_filter', $args);
}

function bb_remove_filter($tag, $function_to_remove, $priority = 10) {
	$args = func_get_args();
	return call_user_func_array('remove_filter', $args);
}

function bb_do_action($tag) {
	$args = func_get_args();
	return call_user_func_array('do_action', $args);
}

function bb_add_action($tag, $function_to_add, $priority = 10) {
	$args = func_get_args();
	return call_user_func_array('add_action', $args);
}

function bb_remove_action($tag, $function_to_remove, $priority = 10) {
	$args = func_get_args();
	return call_user_func_array('remove_action', $args);
}

function bb_add_query_arg() {
	$args = func_get_args();
	return call_user_func_array('add_query_arg', $args);
}

function bb_remove_query_arg($key, $query = '') {
	return remove_query_arg($key, $query);
}

if ( !function_exists('language_attributes') ) :
function language_attributes( $xhtml = 0 ) {
	bb_language_attributes( $xhtml );
}
endif;

function cast_meta_value( $data ) {
	return bb_maybe_unserialize( $data );
}

function option( $option ) {
	return bb_option( $option );
}


// Use topic_time
function topic_date( $format = '', $id = 0 ) {
	echo gmdate( $format, get_topic_timestamp( $id ) );
}
function get_topic_date( $format = '', $id = 0 ){
	return gmdate( $format, get_topic_timestamp( $id ) );
}
function get_topic_timestamp( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return bb_gmtstrtotime( $topic->topic_time );
}

// Use topic_start_time
function topic_start_date( $format = '', $id = 0 ) {
	echo gmdate( $format, get_topic_start_timestamp( $id ) );
}
function get_topic_start_timestamp( $id = 0 ) {
	global $topic;
	if ( $id )
		$topic = get_topic( $id );
	return bb_gmtstrtotime( $topic->topic_start_time );
}

// Use bb_post_time
function post_date( $format ) {
	echo gmdate( $format, get_post_timestamp() );
}
function get_post_timestamp() {
	global $bb_post;
	return bb_gmtstrtotime( $bb_post->post_time );
}

function get_inception() {
	return bb_get_inception( 'timestamp' );
}

?>
