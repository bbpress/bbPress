<?php
require('admin-header.php');

$post_id = (int) $_GET['id'];
$post    =  get_post ( $post_id );
if ( $current_user->user_type < 2 ) {
	header('Location: ' . bb_get_option('uri') );
	die();
}

if ( !$post )
	die('There is a problem with that post, pardner.');

bb_delete_post( $post_id );

header( 'Location: ' . $_SERVER['HTTP_REFERER'] );

?>