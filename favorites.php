<?php
require_once('./bb-load.php');

bb_auth();

if ( !bb_current_user_can( 'edit_favorites_of', $user_id ) )
	die(__('You cannot edit those favorites.  How did you get here?'));

if ( isset( $_GET['fav'] ) && isset( $_GET['topic_id'] ) ) :
	nocache_headers();
	$fav = (int) $_GET['fav'];
	$topic_id = (int) $_GET['topic_id'];

	bb_check_admin_referer( 'toggle-favorite_' . $topic_id );

	$topic = get_topic( $topic_id );
	if ( !$topic || 0 != $topic->topic_status )
		die;

	if ( $fav )
		bb_add_user_favorite( $user_id, $topic_id );
	else
		bb_remove_user_favorite( $user_id, $topic_id );

	if ( false !== strpos( $_SERVER['HTTP_REFERER'], bb_get_option('uri') ) )
		@header('Location: ' . $_SERVER['HTTP_REFERER'] );
	else
		@header('Location: ' . get_topic_link( $topic_id ) );
	exit;
endif;

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( $user->ID, 'favorites' );
	header("Location: $sendto");
}

$topics = get_user_favorites( $user->ID, true );

if (file_exists( BBPATH . 'my-templates/favorites.php' ))
	require( BBPATH . 'my-templates/favorites.php' );
else	require( BBPATH . 'bb-templates/favorites.php' );

?>
