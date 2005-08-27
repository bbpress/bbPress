<?php

function get_total_users() {
	global $bbdb, $bb_total_users;
	if ( isset($bb_total_users) )
		return $bb_total_users;
	$bb_total_users = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->users");
	return $bb_total_users;
}

function total_users() {
	echo bb_apply_filters('total_users', get_total_users() );
}

function get_total_posts() {
	global $bbdb, $bb_total_posts;
	if ( isset($bb_total_posts) )
		return $bb_total_posts;
	$bb_total_posts = $bbdb->get_var("SELECT SUM(posts) FROM $bbdb->forums");
	return $bb_total_posts;
}

function total_posts() {
	echo bb_apply_filters('total_posts', get_total_posts() );
}

function get_total_topics() {
	global $bbdb, $bb_total_topics;
	if ( isset($bb_total_topics) )
		return $bb_total_topics;
	$bb_total_topics = $bbdb->get_var("SELECT SUM(topics) FROM $bbdb->forums");
	return $bb_total_topics;
}

function total_topics() {
	echo bb_apply_filters('total_topics', get_total_topics());
}

function get_popular_topics( $num = 10 ) {
	global $bbdb;
	$num = (int) $num;
	return $bbdb->get_results("SELECT * FROM $bbdb->topics ORDER BY topic_posts DESC LIMIT $num");
}

function get_recent_registrants( $num = 10 ) {
	global $bbdb;
	$num = (int) $num;
	return bb_append_meta($bbdb->get_results("SELECT * FROM $bbdb->users ORDER BY user_registered DESC LIMIT $num"), 'user');
}

function get_inception() {
	global $bbdb, $bb_inception;
	if ( isset($bb_inception) )
		return $bb_inception;
	$bb_inception = $bbdb->get_var("SELECT topic_start_time FROM $bbdb->topics ORDER BY topic_start_time LIMIT 1");
	$bb_inception = strtotime($bb_inception . ' +0000');
	return $bb_inception;
}
function get_registrations_per_day() {
	return get_total_users() / ( time() - get_inception() ) * 3600 * 24;
}

function registrations_per_day() {
	echo bb_apply_filters('registrations_per_day', number_format(get_registrations_per_day(),3));
}

function get_posts_per_day() {
	return get_total_posts() / ( time() - get_inception() ) * 3600 * 24;
}

function posts_per_day() {
	echo bb_apply_filters('posts_per_day', number_format(get_posts_per_day(),3));
}

function get_topics_per_day() {
	return get_total_topics() / ( time() - get_inception() ) * 3600 * 24;
}

function topics_per_day() {
	echo bb_apply_filters('topics_per_day', number_format(get_topics_per_day(),3));
}

?>
