<?php

function get_total_users() {
	global $bbdb, $bb_total_users;
	if ( isset($bb_total_users) )
		return $bb_total_users;
	$bb_total_users = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->users");
	return $bb_total_users;
}

function total_users() {
	echo apply_filters('total_users', get_total_users() );
}

function get_total_posts() {
	global $bbdb, $bb_total_posts;
	if ( isset($bb_total_posts) )
		return $bb_total_posts;
	$bb_total_posts = $bbdb->get_var("SELECT SUM(posts) FROM $bbdb->forums");
	return $bb_total_posts;
}

function total_posts() {
	echo apply_filters('total_posts', get_total_posts() );
}

function get_total_topics() {
	global $bbdb, $bb_total_topics;
	if ( isset($bb_total_topics) )
		return $bb_total_topics;
	$bb_total_topics = $bbdb->get_var("SELECT SUM(topics) FROM $bbdb->forums");
	return $bb_total_topics;
}

function total_topics() {
	echo apply_filters('total_topics', get_total_topics());
}

function get_popular_topics( $num = 10 ) {
	$query = new BB_Query( 'topic', array('per_page' => $num, 'order_by' => 'topic_posts', 'append_meta' => 0) );
	return $query->results;
}

function get_recent_registrants( $num = 10 ) {
	global $bbdb;
	$num = (int) $num;
	return bb_append_meta( (array) $bbdb->get_results("SELECT * FROM $bbdb->users ORDER BY user_registered DESC LIMIT $num"), 'user');
}

function bb_inception( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );
	$time = apply_filters( 'bb_inception', bb_get_inception( array('format' => 'mysql') + $args), $args );
	echo _bb_time_function_return( $time, $args );
}

function bb_get_inception( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );

	global $bbdb, $bb_inception;
	if ( !isset($bb_inception) )
		$bb_inception = $bbdb->get_var("SELECT topic_start_time FROM $bbdb->topics ORDER BY topic_start_time LIMIT 1");

	return apply_filters( 'bb_get_inception', _bb_time_function_return( $bb_inception, $args ) );
}
function get_registrations_per_day() {
	return get_total_users() / ( time() - bb_get_inception( 'timestamp' ) ) * 3600 * 24;
}

function registrations_per_day() {
	echo apply_filters('registrations_per_day', bb_number_format_i18n(get_registrations_per_day(),3));
}

function get_posts_per_day() {
	return get_total_posts() / ( time() - bb_get_inception( 'timestamp' ) ) * 3600 * 24;
}

function posts_per_day() {
	echo apply_filters('posts_per_day', bb_number_format_i18n(get_posts_per_day(),3));
}

function get_topics_per_day() {
	return get_total_topics() / ( time() - bb_get_inception( 'timestamp' ) ) * 3600 * 24;
}

function topics_per_day() {
	echo apply_filters('topics_per_day', bb_number_format_i18n(get_topics_per_day(),3));
}

?>
