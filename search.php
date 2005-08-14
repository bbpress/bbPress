<?php
require_once('bb-config.php');

$q = trim( @$_GET['q'] );
$likeit = preg_replace('/\s+/', '%', $q);

if ( !empty( $q ) ) :

if ( strlen( preg_replace('/[^a-z0-9]/i', '', $q) ) > 2 ) {
	$users = $bbdb->get_results("SELECT * FROM $bbdb->users WHERE user_login LIKE ('%$likeit%')");
	if ( $users )
		bb_append_meta( $users, 'user' );
}

//Not appending topicmeta to titles at the moment!
$titles = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE LOWER(topic_title) LIKE ('%$likeit%') AND topic_status = 0 ORDER BY topic_time DESC LIMIT 5");

$recent = $bbdb->get_results("SELECT $bbdb->posts.*, MAX(post_time) as post_time FROM $bbdb->posts RIGHT JOIN $bbdb->topics ON $bbdb->topics.topic_id = $bbdb->posts.topic_id
				WHERE LOWER(post_text) LIKE ('%$likeit%') AND post_status = 0 AND topic_status = 0
				GROUP BY $bbdb->topics.topic_id ORDER BY post_time DESC LIMIT 5");

$relevant = $bbdb->get_results("SELECT $bbdb->posts.* FROM $bbdb->posts RIGHT JOIN $bbdb->topics ON $bbdb->posts.topic_id = $bbdb->topics.topic_id
				WHERE MATCH(post_text) AGAINST ('$q') AND post_status = 0 AND topic_status = 0 LIMIT 5");

bb_do_action('do_search', $q);

// Cache topics
if ( $recent ) :
	foreach ($recent as $post) {
		$topic_ids[] = $post->topic_id;
		$post_cache[$post->post_id] = $post;
	}
endif;

if ( $relevant ) :
	foreach ($relevant as $post) {
		$topic_ids[] = $post->topic_id;
		$post_cache[$post->post_id] = $post;
	}
endif;

if ( $recent || $relevant ) :
	$topic_ids = join(',', $topic_ids);
	if ( $topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)") )
		$topics = bb_append_meta( $topics, 'topic' );
endif;

endif;

$q = stripslashes( $q );

bb_add_filter('get_post_time', 'strtotime');
bb_add_filter('get_post_time', 'bb_offset_time');

if (file_exists( BBPATH . 'my-templates/search.php' ))
	require( BBPATH . 'my-templates/search.php' );
else	require( BBPATH . 'bb-templates/search.php' );

?>
