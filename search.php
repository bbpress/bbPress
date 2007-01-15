<?php
require_once('./bb-load.php');

$q = trim( @$_GET['q'] );
$likeit = preg_replace('/\s+/', '%', $q);

if ( !empty( $q ) ) :

if ( $users = bb_user_search( $q ) && is_wp_error($users) ) {
	$error = $users;
	$users = false;
}

//Not appending topicmeta to titles at the moment!
$titles = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE LOWER(topic_title) LIKE ('%$likeit%') AND topic_status = 0 ORDER BY topic_time DESC LIMIT 5");

$recent = $bbdb->get_results("SELECT $bbdb->posts.*, MAX(post_time) as post_time FROM $bbdb->posts RIGHT JOIN $bbdb->topics ON $bbdb->topics.topic_id = $bbdb->posts.topic_id
				WHERE LOWER(post_text) LIKE ('%$likeit%') AND post_status = 0 AND topic_status = 0
				GROUP BY $bbdb->topics.topic_id ORDER BY post_time DESC LIMIT 5");

$relevant = $bbdb->get_results("SELECT $bbdb->posts.* FROM $bbdb->posts RIGHT JOIN $bbdb->topics ON $bbdb->posts.topic_id = $bbdb->topics.topic_id
				WHERE MATCH(post_text) AGAINST ('$q') AND post_status = 0 AND topic_status = 0 LIMIT 5");

do_action('do_search', $q);

// Cache topics
if ( $recent ) :
	foreach ($recent as $bb_post) {
		$topic_ids[] = $bb_post->topic_id;
		$bb_post_cache[$bb_post->post_id] = $bb_post;
	}
endif;

if ( $relevant ) :
	foreach ($relevant as $bb_post) {
		$topic_ids[] = $bb_post->topic_id;
		$bb_post_cache[$bb_post->post_id] = $bb_post;
	}
endif;

if ( $recent || $relevant ) :
	$topic_ids = join(',', $topic_ids);
	if ( $topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)") )
		$topics = bb_append_meta( $topics, 'topic' );
endif;

endif;

$q = stripslashes( $q );

add_filter('bb_get_post_time', 'strtotime');
add_filter('bb_get_post_time', 'bb_offset_time');

if (file_exists( BBPATH . 'my-templates/search.php' ))
	require( BBPATH . 'my-templates/search.php' );
else	require( BBPATH . 'bb-templates/search.php' );

?>
