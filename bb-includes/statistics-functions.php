<?php

function get_total_users() {
	global $bbdb;
	return $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->users");
}

function total_users() {
	echo bb_apply_filters('total_users', get_total_users() );
}

function get_total_posts() {
	global $bbdb;
	return $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts");
}

function total_posts() {
	echo bb_apply_filters('total_posts', get_total_posts() );
}

function get_popular_topics( $num = 5 ) {
	global $bbdb;
	$num = (int) $num;
	return $bbdb->get_results("SELECT * FROM $bbdb->topics ORDER BY topic_posts DESC LIMIT $num");
}

?>