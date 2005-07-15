<?php
require_once('bb-config.php');

if ( isset( $_GET['fav'] ) && isset( $_GET['topic_id'] ) && $current_user ) :
	nocache_headers();
	$fav = (int) $_GET['fav'];
	$topic_id = (int) $_GET['topic_id'];
	$topic = get_topic( $topic_id );
	if ( !$topic || 0 != $topic->topic_status )
		die;

	if ( $fav ) {
		$fav = $current_user->favorites ? explode(',', $current_user->favorites) : array();
		if ( ! in_array( $topic_id, $fav ) ) {
			$fav[] = $topic_id;
			$fav = implode(',', $fav);
			update_usermeta( $current_user->ID, 'favorites', $fav);
		}
	} else {
		$fav = explode(',', $current_user->favorites);
		if ( is_int( $pos = array_search($topic_id, $fav) ) ) {
			array_splice($fav, $pos, 1);
			$fav = implode(',', $fav);
			$fav = trim(substr($fav, -255),','); // limit to size of meta_value.
			update_usermeta( $current_user->ID, 'favorites', $fav);
		}
	}

	if ( false !== strpos( $_SERVER['HTTP_REFERER'], bb_get_option('uri') ) )
		@header('Location: ' . $_SERVER['HTTP_REFERER'] );
	else
		@header('Location: ' . get_topic_link( $topic_id ) );
	exit;
endif;

if( !$current_user ) {
	$sendto = bb_get_option('uri');
	header("Location: $sendto");
}

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( $current_user->ID, 'favorites' );
	header("Location: $sendto");
}

$topics = get_user_favorites( $current_user->ID, true );

include('bb-templates/favorites.php');

?>
