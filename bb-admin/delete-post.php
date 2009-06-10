<?php
require('admin-action.php');

$post_id = (int) $_GET['id'];

if ( !bb_current_user_can( 'delete_post', $post_id ) ) {
	wp_redirect( bb_get_uri(null, null, BB_URI_CONTEXT_HEADER) );
	exit;
}

bb_check_admin_referer( 'delete-post_' . $post_id );

$status  = (int) $_GET['status'];
$bb_post = bb_get_post ( $post_id );

if ( !$bb_post )
	bb_die(__('There is a problem with that post, pardner.'));

if ( 0 == $status && 0 != $bb_post->post_status ) // We're undeleting
	add_filter('bb_delete_post', 'bb_topics_replied_on_undelete_post');

bb_delete_post( $post_id, $status );

$topic = get_topic( $bb_post->topic_id );

if ( $sendto = wp_get_referer() ); // sic
elseif ( $topic->topic_posts == 0 ) {
	$sendto = get_forum_link( $topic->forum_id );
} else {
	$the_page = get_page_number( $bb_post->post_position );
	$sendto = get_topic_link( $bb_post->topic_id, $the_page );
}

bb_safe_redirect( $sendto );
exit;
