<?php

require('./bb-load.php');

bb_repermalink();

$bb_db_override = false;
do_action( 'bb_index.php_pre_db' );

if ( isset($_GET['new']) && '1' == $_GET['new'] ) :
	$forums = false;
elseif ( !$bb_db_override ) :
	$forums = get_forums(); // Comment to hide forums
	$topics = get_latest_topics();
	$super_stickies = get_sticky_topics();
endif;

bb_load_template( 'front-page.php', array('bb_db_override', 'super_stickies') );

?>
