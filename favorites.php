<?php
require_once('bb-config.php');

if ( $user_id == $current_user->ID ) :
	if ( !current_user_can('edit_favorites') ) :
		die('You cannot edit your favorites.  How did you get here?');
	endif;
else :
	if ( !current_user_can('edit_others_favorites') ) :
		die("You cannot edit others' favorites.  How did you get here?");
	endif;
endif;

if ( isset( $_GET['fav'] ) && isset( $_GET['topic_id'] ) ) :
	nocache_headers();
	$fav = (int) $_GET['fav'];
	$topic_id = (int) $_GET['topic_id'];
	$topic = get_topic( $topic_id );
	if ( !$topic || 0 != $topic->topic_status )
		die;

	if ( $fav ) {
		$fav = $user->favorites ? explode(',', $user->favorites) : array();
		if ( ! in_array( $topic_id, $fav ) ) {
			$fav[] = $topic_id;
			$fav = implode(',', $fav);
			update_usermeta( $user->ID, $table_prefix . 'favorites', $fav);
		}
	} else {
		$fav = explode(',', $user->favorites);
		if ( is_int( $pos = array_search($topic_id, $fav) ) ) {
			array_splice($fav, $pos, 1);
			$fav = implode(',', $fav);
			update_usermeta( $user->ID, $table_prefix . 'favorites', $fav);
		}
	}

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
