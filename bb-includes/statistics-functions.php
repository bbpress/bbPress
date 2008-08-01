<?php
/**
 * bbPress Forum Content Statistics Functions
 *
 * @package bbPress
 */

/**
 * get_total_users() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 * @global int $bb_total_users
 *
 * @return int
 */
function get_total_forums() {
	global $bbdb, $bb_total_forums;
	if ( isset($bb_total_forums) )
		return $bb_total_forums;
	$bb_total_forums = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->forums");
	return $bb_total_forums;
}

/**
 * total_users() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 */
function total_forums() {
	echo apply_filters('total_forums', get_total_forums() );
}

/**
 * get_total_users() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 * @global int $bb_total_users
 *
 * @return int
 */
function get_total_users() {
	global $bbdb, $bb_total_users;
	if ( isset($bb_total_users) )
		return $bb_total_users;
	$bb_total_users = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->users");
	return $bb_total_users;
}

/**
 * total_users() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 */
function total_users() {
	echo apply_filters('total_users', get_total_users() );
}

/**
 * get_total_posts() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 * @global int $bb_total_posts
 *
 * @return int
 */
function get_total_posts() {
	global $bbdb, $bb_total_posts;
	if ( isset($bb_total_posts) )
		return $bb_total_posts;
	$bb_total_posts = $bbdb->get_var("SELECT SUM(posts) FROM $bbdb->forums");
	return $bb_total_posts;
}

/**
 * total_users() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 */
function total_posts() {
	echo apply_filters('total_posts', get_total_posts() );
}

/**
 * get_total_topics() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 * @global int $bb_total_topics
 *
 * @return int
 */
function get_total_topics() {
	global $bbdb, $bb_total_topics;
	if ( isset($bb_total_topics) )
		return $bb_total_topics;
	$bb_total_topics = $bbdb->get_var("SELECT SUM(topics) FROM $bbdb->forums");
	return $bb_total_topics;
}

/**
 * total_topics() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 */
function total_topics() {
	echo apply_filters('total_topics', get_total_topics());
}

/**
 * get_popular_topics() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 *
 * @return bbdb|BB_Cache
 */
function get_popular_topics( $num = 10 ) {
	$query = new BB_Query( 'topic', array('per_page' => $num, 'order_by' => 'topic_posts', 'append_meta' => 0) );
	return $query->results;
}

/**
 * get_recent_registrants() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 *
 * @return array
 */
function get_recent_registrants( $num = 10 ) {
	global $bbdb;
	return bb_append_meta( (array) $bbdb->get_results( $bbdb->prepare(
		"SELECT * FROM $bbdb->users ORDER BY user_registered DESC LIMIT %d",
		$num
	) ), 'user');
}

/**
 * bb_inception() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 */
function bb_inception( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );
	$time = apply_filters( 'bb_inception', bb_get_inception( array('format' => 'mysql') + $args), $args );
	echo _bb_time_function_return( $time, $args );
}

/**
 * bb_get_inception() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 * @global bbdb $bbdb
 * @global int $bb_inception
 *
 * @return int
 */
function bb_get_inception( $args = '' ) {
	$args = _bb_parse_time_function_args( $args );

	global $bbdb, $bb_inception;
	if ( !isset($bb_inception) )
		$bb_inception = $bbdb->get_var("SELECT topic_start_time FROM $bbdb->topics ORDER BY topic_start_time LIMIT 1");

	return apply_filters( 'bb_get_inception', _bb_time_function_return( $bb_inception, $args ) );
}

/**
 * get_registrations_per_day() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 *
 * @return int|float
 */
function get_registrations_per_day() {
	return get_total_users() / ceil( ( time() - bb_get_inception( 'timestamp' ) ) / 3600 / 24 );
}

/**
 * registrations_per_day() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 */
function registrations_per_day() {
	echo apply_filters('registrations_per_day', bb_number_format_i18n(get_registrations_per_day(),3));
}

/**
 * get_posts_per_day() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 *
 * @return int|float
 */
function get_posts_per_day() {
	return get_total_posts() / ceil( ( time() - bb_get_inception( 'timestamp' ) ) / 3600 / 24 );
}

/**
 * posts_per_day() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 */
function posts_per_day() {
	echo apply_filters('posts_per_day', bb_number_format_i18n(get_posts_per_day(),3));
}

/**
 * get_topics_per_day() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 *
 * @return int|float
 */
function get_topics_per_day() {
	return get_total_topics() / ceil( ( time() - bb_get_inception( 'timestamp' ) ) / 3600 / 24 );
}

/**
 * topics_per_day() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since {@internal Unknown}}
 */
function topics_per_day() {
	echo apply_filters('topics_per_day', bb_number_format_i18n(get_topics_per_day(),3));
}

?>
