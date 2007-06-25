<?php
require_once('./bb-load.php');

bb_repermalink();

$view = bb_slug_sanitize($view);

$stickies = $topics = $view_count = false;

if ( isset($bb_views[$view]) ) {
	if ( $bb_views[$view]['sticky'] )
		list($stickies, $sticky_count) = bb_view_query( $view, array('sticky' => '-no') ); // -no = yes
	list($topics,   $topic_count)  = bb_view_query( $view );
	$view_count = max($sticky_count, $topic_count);
}

do_action( 'bb_custom_view', $view, $page );

do_action( 'bb_view.php', '' );

bb_load_template( 'view.php', array('view_count', 'stickies') );

?>
